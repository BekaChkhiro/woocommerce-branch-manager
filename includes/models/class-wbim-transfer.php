<?php
/**
 * Transfer Model
 *
 * Handles all transfer-related database operations.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transfer model class
 *
 * @since 1.0.0
 */
class WBIM_Transfer {

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_transfers';

    /**
     * Items table name
     *
     * @var string
     */
    private static $items_table_name = 'wbim_transfer_items';

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    private static function get_table() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Get the items table name with prefix
     *
     * @return string
     */
    private static function get_items_table() {
        global $wpdb;
        return $wpdb->prefix . self::$items_table_name;
    }

    /**
     * Get all valid statuses
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            self::STATUS_DRAFT     => __( 'მონახაზი', 'wbim' ),
            self::STATUS_PENDING   => __( 'მოლოდინში', 'wbim' ),
            self::STATUS_IN_TRANSIT => __( 'გზაშია', 'wbim' ),
            self::STATUS_COMPLETED => __( 'დასრულებული', 'wbim' ),
            self::STATUS_CANCELLED => __( 'გაუქმებული', 'wbim' ),
        );
    }

    /**
     * Get valid status transitions
     *
     * @param string $current_status Current status.
     * @return array Valid next statuses.
     */
    public static function get_valid_transitions( $current_status ) {
        $transitions = array(
            self::STATUS_DRAFT     => array( self::STATUS_PENDING, self::STATUS_CANCELLED ),
            self::STATUS_PENDING   => array( self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED ),
            self::STATUS_IN_TRANSIT => array( self::STATUS_COMPLETED, self::STATUS_CANCELLED ),
            self::STATUS_COMPLETED => array(),
            self::STATUS_CANCELLED => array(),
        );

        return isset( $transitions[ $current_status ] ) ? $transitions[ $current_status ] : array();
    }

    /**
     * Get transfer by ID
     *
     * @param int $id Transfer ID.
     * @return object|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $table = self::get_table();

        $transfer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*,
                    sb.name as source_branch_name,
                    sb.address as source_branch_address,
                    db.name as destination_branch_name,
                    db.address as destination_branch_address,
                    cu.display_name as created_by_name,
                    su.display_name as sent_by_name,
                    ru.display_name as received_by_name
                FROM {$table} t
                LEFT JOIN {$wpdb->prefix}wbim_branches sb ON t.source_branch_id = sb.id
                LEFT JOIN {$wpdb->prefix}wbim_branches db ON t.destination_branch_id = db.id
                LEFT JOIN {$wpdb->users} cu ON t.created_by = cu.ID
                LEFT JOIN {$wpdb->users} su ON t.sent_by = su.ID
                LEFT JOIN {$wpdb->users} ru ON t.received_by = ru.ID
                WHERE t.id = %d",
                $id
            )
        );

        if ( $transfer ) {
            $transfer->items = WBIM_Transfer_Item::get_by_transfer( $id );
            $transfer->items_count = count( $transfer->items );
            $transfer->total_quantity = array_sum( array_column( $transfer->items, 'quantity' ) );
        }

        return $transfer;
    }

    /**
     * Get all transfers
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'                => '',
            'source_branch_id'      => 0,
            'destination_branch_id' => 0,
            'created_by'            => 0,
            'date_from'             => '',
            'date_to'               => '',
            'search'                => '',
            'orderby'               => 'created_at',
            'order'                 => 'DESC',
            'limit'                 => 20,
            'offset'                => 0,
            'user_branch_filter'    => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $items_table = self::get_items_table();
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 't.status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['source_branch_id'] ) ) {
            $where[] = 't.source_branch_id = %d';
            $values[] = $args['source_branch_id'];
        }

        if ( ! empty( $args['destination_branch_id'] ) ) {
            $where[] = 't.destination_branch_id = %d';
            $values[] = $args['destination_branch_id'];
        }

        if ( ! empty( $args['created_by'] ) ) {
            $where[] = 't.created_by = %d';
            $values[] = $args['created_by'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 't.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 't.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(t.transfer_number LIKE %s OR t.notes LIKE %s OR sb.name LIKE %s OR db.name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Filter by user's branches
        if ( ! empty( $args['user_branch_filter'] ) ) {
            $branch_ids = array_map( 'absint', $args['user_branch_filter'] );
            $placeholders = implode( ',', array_fill( 0, count( $branch_ids ), '%d' ) );
            $where[] = "(t.source_branch_id IN ({$placeholders}) OR t.destination_branch_id IN ({$placeholders}))";
            $values = array_merge( $values, $branch_ids, $branch_ids );
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'transfer_number', 'status', 'created_at', 'source_branch_name', 'destination_branch_name' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        if ( $orderby === 'source_branch_name' ) {
            $orderby = 'sb.name';
        } elseif ( $orderby === 'destination_branch_name' ) {
            $orderby = 'db.name';
        } else {
            $orderby = 't.' . $orderby;
        }

        // Validate order
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT t.*,
                sb.name as source_branch_name,
                db.name as destination_branch_name,
                cu.display_name as created_by_name,
                (SELECT COUNT(*) FROM {$items_table} WHERE transfer_id = t.id) as items_count,
                (SELECT SUM(quantity) FROM {$items_table} WHERE transfer_id = t.id) as total_quantity
            FROM {$table} t
            LEFT JOIN {$wpdb->prefix}wbim_branches sb ON t.source_branch_id = sb.id
            LEFT JOIN {$wpdb->prefix}wbim_branches db ON t.destination_branch_id = db.id
            LEFT JOIN {$wpdb->users} cu ON t.created_by = cu.ID
            WHERE {$where_clause}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get all status labels
     *
     * @return array
     */
    public static function get_all_statuses() {
        return self::get_statuses();
    }

    /**
     * Get status label
     *
     * @param string $status Status key.
     * @return string Status label.
     */
    public static function get_status_label( $status ) {
        $statuses = self::get_statuses();
        return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
    }

    /**
     * Get transfers for a specific branch (as source or destination)
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return array
     */
    public static function get_for_branch( $branch_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'direction' => 'both', // 'source', 'destination', or 'both'
            'status'    => '',
            'limit'     => 20,
            'offset'    => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $where = array();
        $values = array();

        if ( $args['direction'] === 'source' ) {
            $where[] = 't.source_branch_id = %d';
            $values[] = $branch_id;
        } elseif ( $args['direction'] === 'destination' ) {
            $where[] = 't.destination_branch_id = %d';
            $values[] = $branch_id;
        } else {
            $where[] = '(t.source_branch_id = %d OR t.destination_branch_id = %d)';
            $values[] = $branch_id;
            $values[] = $branch_id;
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 't.status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT t.*,
                sb.name as source_branch_name,
                db.name as destination_branch_name,
                cu.display_name as created_by_name
            FROM {$table} t
            LEFT JOIN {$wpdb->prefix}wbim_branches sb ON t.source_branch_id = sb.id
            LEFT JOIN {$wpdb->prefix}wbim_branches db ON t.destination_branch_id = db.id
            LEFT JOIN {$wpdb->users} cu ON t.created_by = cu.ID
            WHERE {$where_clause}
            ORDER BY t.created_at DESC
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Generate unique transfer number
     *
     * @return string
     */
    private static function generate_transfer_number() {
        global $wpdb;
        $table = self::get_table();

        $year = date( 'Y' );
        $prefix = 'TRN-' . $year . '-';

        // Get the last transfer number for this year
        $last_number = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT transfer_number FROM {$table}
                WHERE transfer_number LIKE %s
                ORDER BY id DESC LIMIT 1",
                $prefix . '%'
            )
        );

        if ( $last_number ) {
            $last_seq = (int) substr( $last_number, strlen( $prefix ) );
            $new_seq = $last_seq + 1;
        } else {
            $new_seq = 1;
        }

        return $prefix . str_pad( $new_seq, 5, '0', STR_PAD_LEFT );
    }

    /**
     * Create a new transfer
     *
     * @param array $data Transfer data.
     * @return int|WP_Error Transfer ID on success, WP_Error on failure.
     */
    public static function create( $data ) {
        global $wpdb;

        // Validate required fields
        if ( empty( $data['source_branch_id'] ) || empty( $data['destination_branch_id'] ) ) {
            return new WP_Error(
                'missing_branches',
                __( 'ორივე ფილიალის მითითება სავალდებულოა.', 'wbim' )
            );
        }

        if ( absint( $data['source_branch_id'] ) === absint( $data['destination_branch_id'] ) ) {
            return new WP_Error(
                'same_branch',
                __( 'წყარო და დანიშნულების ფილიალები უნდა იყოს განსხვავებული.', 'wbim' )
            );
        }

        // Validate branches exist
        $source_branch = WBIM_Branch::get_by_id( $data['source_branch_id'] );
        $dest_branch = WBIM_Branch::get_by_id( $data['destination_branch_id'] );

        if ( ! $source_branch || ! $dest_branch ) {
            return new WP_Error(
                'invalid_branch',
                __( 'არასწორი ფილიალი.', 'wbim' )
            );
        }

        $table = self::get_table();
        $transfer_number = self::generate_transfer_number();

        $result = $wpdb->insert(
            $table,
            array(
                'transfer_number'       => $transfer_number,
                'source_branch_id'      => absint( $data['source_branch_id'] ),
                'destination_branch_id' => absint( $data['destination_branch_id'] ),
                'status'                => self::STATUS_DRAFT,
                'notes'                 => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
                'created_by'            => get_current_user_id(),
                'created_at'            => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'გადატანის შექმნა ვერ მოხერხდა.', 'wbim' )
            );
        }

        $transfer_id = $wpdb->insert_id;

        // Ensure status is set (fallback for column issues)
        $wpdb->update(
            $table,
            array( 'status' => self::STATUS_DRAFT ),
            array( 'id' => $transfer_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Add items if provided
        if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
            foreach ( $data['items'] as $item ) {
                if ( ! empty( $item['product_id'] ) && ! empty( $item['quantity'] ) ) {
                    WBIM_Transfer_Item::add(
                        $transfer_id,
                        $item['product_id'],
                        isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
                        $item['quantity']
                    );
                }
            }
        }

        // Trigger action
        do_action( 'wbim_transfer_created', $transfer_id, $data );

        return $transfer_id;
    }

    /**
     * Update transfer
     *
     * @param int   $id   Transfer ID.
     * @param array $data Update data.
     * @return bool|WP_Error
     */
    public static function update( $id, $data ) {
        global $wpdb;

        $transfer = self::get_by_id( $id );
        if ( ! $transfer ) {
            return new WP_Error( 'not_found', __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        // Only drafts can be edited
        if ( $transfer->status !== self::STATUS_DRAFT ) {
            return new WP_Error(
                'cannot_edit',
                __( 'მხოლოდ მონახაზის რედაქტირება შეიძლება.', 'wbim' )
            );
        }

        $table = self::get_table();
        $update_data = array();
        $format = array();

        if ( isset( $data['source_branch_id'] ) ) {
            $update_data['source_branch_id'] = absint( $data['source_branch_id'] );
            $format[] = '%d';
        }

        if ( isset( $data['destination_branch_id'] ) ) {
            $update_data['destination_branch_id'] = absint( $data['destination_branch_id'] );
            $format[] = '%d';
        }

        if ( isset( $data['notes'] ) ) {
            $update_data['notes'] = sanitize_textarea_field( $data['notes'] );
            $format[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return true;
        }

        // Add updated_at timestamp
        $update_data['updated_at'] = current_time( 'mysql' );
        $format[] = '%s';

        // Validate branches are different
        $source = isset( $update_data['source_branch_id'] ) ? $update_data['source_branch_id'] : $transfer->source_branch_id;
        $dest = isset( $update_data['destination_branch_id'] ) ? $update_data['destination_branch_id'] : $transfer->destination_branch_id;

        if ( $source === $dest ) {
            return new WP_Error(
                'same_branch',
                __( 'წყარო და დანიშნულების ფილიალები უნდა იყოს განსხვავებული.', 'wbim' )
            );
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Update transfer status with validation
     *
     * @param int    $id         Transfer ID.
     * @param string $new_status New status.
     * @param int    $user_id    User ID performing action.
     * @return bool|WP_Error
     */
    public static function update_status( $id, $new_status, $user_id = null ) {
        global $wpdb;

        $transfer = self::get_by_id( $id );
        if ( ! $transfer ) {
            return new WP_Error( 'not_found', __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        $old_status = $transfer->status;

        // Fix empty status (database migration issue)
        if ( empty( $old_status ) ) {
            $old_status = self::STATUS_DRAFT;
            $wpdb->update(
                self::get_table(),
                array( 'status' => self::STATUS_DRAFT ),
                array( 'id' => $id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        // Validate transition
        $valid_transitions = self::get_valid_transitions( $old_status );
        if ( ! in_array( $new_status, $valid_transitions, true ) ) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    __( 'არასწორი სტატუსის გადასვლა: %s → %s', 'wbim' ),
                    self::get_statuses()[ $old_status ],
                    self::get_statuses()[ $new_status ]
                )
            );
        }

        $user_id = $user_id ?: get_current_user_id();

        // Handle stock changes based on transition
        $stock_result = self::handle_stock_transition( $transfer, $old_status, $new_status );
        if ( is_wp_error( $stock_result ) ) {
            return $stock_result;
        }

        $table = self::get_table();
        $update_data = array(
            'status'     => $new_status,
            'updated_at' => current_time( 'mysql' ),
        );
        $format = array( '%s', '%s' );

        // Set sent data when moving to pending
        if ( $new_status === self::STATUS_PENDING ) {
            $update_data['sent_by'] = $user_id;
            $update_data['sent_at'] = current_time( 'mysql' );
            $format[] = '%d';
            $format[] = '%s';
        }

        // Set received data when completing
        if ( $new_status === self::STATUS_COMPLETED ) {
            $update_data['received_by'] = $user_id;
            $update_data['received_at'] = current_time( 'mysql' );
            $format[] = '%d';
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'სტატუსის განახლება ვერ მოხერხდა.', 'wbim' ) );
        }

        // Trigger action for notifications
        do_action( 'wbim_transfer_status_changed', $id, $old_status, $new_status, $user_id );

        return true;
    }

    /**
     * Handle stock changes based on status transition
     *
     * @param object $transfer   Transfer object.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @return bool|WP_Error
     */
    private static function handle_stock_transition( $transfer, $old_status, $new_status ) {
        // Draft → Pending: Deduct from source (stock is reserved)
        if ( $old_status === self::STATUS_DRAFT && $new_status === self::STATUS_PENDING ) {
            return self::deduct_from_source( $transfer );
        }

        // In Transit → Completed: Add to destination
        if ( $old_status === self::STATUS_IN_TRANSIT && $new_status === self::STATUS_COMPLETED ) {
            return self::add_to_destination( $transfer );
        }

        // Pending/In Transit → Cancelled: Return to source (only if stock was deducted)
        if ( in_array( $old_status, array( self::STATUS_PENDING, self::STATUS_IN_TRANSIT ), true )
             && $new_status === self::STATUS_CANCELLED ) {
            return self::return_to_source( $transfer );
        }

        return true;
    }

    /**
     * Deduct stock from source branch
     *
     * @param object $transfer Transfer object.
     * @return bool|WP_Error
     */
    private static function deduct_from_source( $transfer ) {
        foreach ( $transfer->items as $item ) {
            // Check available stock
            $stock = WBIM_Stock::get( $item->product_id, $transfer->source_branch_id, $item->variation_id );
            $available = $stock ? $stock->quantity : 0;

            if ( $available < $item->quantity ) {
                $product = wc_get_product( $item->variation_id ?: $item->product_id );
                return new WP_Error(
                    'insufficient_stock',
                    sprintf(
                        __( 'არასაკმარისი მარაგი: %s (საჭირო: %d, ხელმისაწვდომი: %d)', 'wbim' ),
                        $product ? $product->get_name() : '#' . $item->product_id,
                        $item->quantity,
                        $available
                    )
                );
            }

            // Deduct stock
            $result = WBIM_Stock::adjust(
                $item->product_id,
                $item->variation_id,
                $transfer->source_branch_id,
                -$item->quantity,
                'transfer_out',
                $transfer->id,
                sprintf( __( 'გადატანა #%s - გასვლა', 'wbim' ), $transfer->transfer_number )
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Sync WC stock
            WBIM_Stock::sync_wc_stock( $item->product_id, $item->variation_id );
        }

        return true;
    }

    /**
     * Add stock to destination branch
     *
     * @param object $transfer Transfer object.
     * @return bool|WP_Error
     */
    private static function add_to_destination( $transfer ) {
        foreach ( $transfer->items as $item ) {
            $result = WBIM_Stock::adjust(
                $item->product_id,
                $item->variation_id,
                $transfer->destination_branch_id,
                $item->quantity,
                'transfer_in',
                $transfer->id,
                sprintf( __( 'გადატანა #%s - შემოსვლა', 'wbim' ), $transfer->transfer_number )
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Sync WC stock
            WBIM_Stock::sync_wc_stock( $item->product_id, $item->variation_id );
        }

        return true;
    }

    /**
     * Return stock to source branch (on cancellation)
     *
     * @param object $transfer Transfer object.
     * @return bool|WP_Error
     */
    private static function return_to_source( $transfer ) {
        foreach ( $transfer->items as $item ) {
            $result = WBIM_Stock::adjust(
                $item->product_id,
                $item->variation_id,
                $transfer->source_branch_id,
                $item->quantity,
                'transfer_return',
                $transfer->id,
                sprintf( __( 'გადატანა #%s - გაუქმებულია, დაბრუნდა', 'wbim' ), $transfer->transfer_number )
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Sync WC stock
            WBIM_Stock::sync_wc_stock( $item->product_id, $item->variation_id );
        }

        return true;
    }

    /**
     * Get count by status
     *
     * @return array
     */
    public static function get_count_by_status() {
        global $wpdb;

        $table = self::get_table();

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );

        $counts = array(
            self::STATUS_DRAFT     => 0,
            self::STATUS_PENDING   => 0,
            self::STATUS_IN_TRANSIT => 0,
            self::STATUS_COMPLETED => 0,
            self::STATUS_CANCELLED => 0,
            'all'                  => 0,
        );

        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->count;
            $counts['all'] += (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get transfer count with filters
     *
     * @param array $args Query arguments.
     * @return int
     */
    public static function get_count( $args = array() ) {
        global $wpdb;

        $table = self::get_table();
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['source_branch_id'] ) ) {
            $where[] = 'source_branch_id = %d';
            $values[] = $args['source_branch_id'];
        }

        if ( ! empty( $args['destination_branch_id'] ) ) {
            $where[] = 'destination_branch_id = %d';
            $values[] = $args['destination_branch_id'];
        }

        if ( ! empty( $args['created_by'] ) ) {
            $where[] = 'created_by = %d';
            $values[] = $args['created_by'];
        }

        $where_clause = implode( ' AND ', $where );
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Delete transfer (only if draft or cancelled)
     *
     * @param int $id Transfer ID.
     * @return bool|WP_Error
     */
    public static function delete( $id ) {
        global $wpdb;

        $transfer = self::get_by_id( $id );
        if ( ! $transfer ) {
            return new WP_Error( 'not_found', __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        // Only allow deletion of drafts and cancelled
        if ( ! in_array( $transfer->status, array( self::STATUS_DRAFT, self::STATUS_CANCELLED ), true ) ) {
            return new WP_Error(
                'cannot_delete',
                __( 'მხოლოდ მონახაზის ან გაუქმებული გადატანის წაშლა შეიძლება.', 'wbim' )
            );
        }

        // Delete items first
        WBIM_Transfer_Item::delete_by_transfer( $id );

        // Delete transfer
        $table = self::get_table();
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'წაშლა ვერ მოხერხდა.', 'wbim' ) );
        }

        do_action( 'wbim_transfer_deleted', $id );

        return true;
    }

    /**
     * Check if user can manage transfer
     *
     * @param int $transfer_id Transfer ID.
     * @param int $user_id     User ID.
     * @return bool
     */
    public static function user_can_manage( $transfer_id, $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();

        // Admins and shop managers can manage all
        if ( user_can( $user_id, 'manage_woocommerce' ) ) {
            return true;
        }

        // Check if user has transfer management capability
        if ( ! user_can( $user_id, 'wbim_manage_transfers' ) ) {
            return false;
        }

        $transfer = self::get_by_id( $transfer_id );
        if ( ! $transfer ) {
            return false;
        }

        // Creator can manage their own
        if ( (int) $transfer->created_by === $user_id ) {
            return true;
        }

        // Branch managers can manage transfers for their branches
        if ( class_exists( 'WBIM_User_Roles' ) ) {
            $user_branches = WBIM_User_Roles::get_user_branches( $user_id );
            if ( in_array( (int) $transfer->source_branch_id, $user_branches, true ) ||
                 in_array( (int) $transfer->destination_branch_id, $user_branches, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can manage a specific branch
     *
     * @param int $branch_id Branch ID.
     * @param int $user_id   User ID.
     * @return bool
     */
    public static function user_can_manage_branch( $branch_id, $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();

        // Admins and shop managers can manage all branches
        if ( user_can( $user_id, 'manage_woocommerce' ) ) {
            return true;
        }

        // Check if user has transfer capability
        if ( ! user_can( $user_id, 'wbim_manage_transfers' ) ) {
            return false;
        }

        // Check branch assignment
        if ( class_exists( 'WBIM_User_Roles' ) ) {
            return WBIM_User_Roles::user_can_access_branch( $branch_id, $user_id );
        }

        return false;
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status.
     * @return string
     */
    public static function get_status_badge( $status ) {
        $statuses = self::get_statuses();
        $label = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

        $classes = array(
            self::STATUS_DRAFT     => 'wbim-status-draft',
            self::STATUS_PENDING   => 'wbim-status-pending',
            self::STATUS_IN_TRANSIT => 'wbim-status-transit',
            self::STATUS_COMPLETED => 'wbim-status-completed',
            self::STATUS_CANCELLED => 'wbim-status-cancelled',
        );

        $class = isset( $classes[ $status ] ) ? $classes[ $status ] : 'wbim-status-default';

        return sprintf(
            '<span class="wbim-status-badge %s">%s</span>',
            esc_attr( $class ),
            esc_html( $label )
        );
    }
}
