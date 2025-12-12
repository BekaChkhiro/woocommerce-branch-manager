<?php
/**
 * Stock Model
 *
 * Handles all stock-related database operations.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stock model class
 *
 * @since 1.0.0
 */
class WBIM_Stock {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_stock';

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
     * Get stock for a product at a branch
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID (0 for simple products).
     * @return object|null
     */
    public static function get( $product_id, $branch_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d AND branch_id = %d AND variation_id = %d",
                $product_id,
                $branch_id,
                $variation_id
            )
        );
    }

    /**
     * Get all stock for a product across all branches
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID (0 for simple products).
     * @return array
     */
    public static function get_product_stock( $product_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, b.name as branch_name, b.is_active as branch_active
                FROM {$table} s
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON s.branch_id = b.id
                WHERE s.product_id = %d AND s.variation_id = %d
                ORDER BY b.sort_order ASC",
                $product_id,
                $variation_id
            )
        );
    }

    /**
     * Alias for get_product_stock for backwards compatibility
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return array
     */
    public static function get_by_product( $product_id, $variation_id = 0 ) {
        return self::get_product_stock( $product_id, $variation_id );
    }

    /**
     * Get all stock for a branch
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return array
     */
    public static function get_branch_stock( $branch_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'limit'        => 50,
            'offset'       => 0,
            'low_stock'    => false,
            'out_of_stock' => false,
            'search'       => '',
            'category_id'  => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $where = array( 's.branch_id = %d' );
        $values = array( $branch_id );

        // Low stock filter
        if ( $args['low_stock'] ) {
            $where[] = 's.quantity <= s.low_stock_threshold AND s.quantity > 0';
        }

        // Out of stock filter
        if ( $args['out_of_stock'] ) {
            $where[] = 's.quantity = 0';
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR pm.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = $args['category_id'];
        }

        $where_clause = implode( ' AND ', $where );
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as product_name, pm.meta_value as sku
                FROM {$table} s
                LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                {$join_terms}
                WHERE {$where_clause}
                ORDER BY p.post_title ASC
                LIMIT %d OFFSET %d",
                $values
            )
        );
    }

    /**
     * Alias for get_branch_stock for backwards compatibility
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return array
     */
    public static function get_by_branch( $branch_id, $args = array() ) {
        return self::get_branch_stock( $branch_id, $args );
    }

    /**
     * Get all stock records with filters for list table
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'    => 0,
            'category_id'  => 0,
            'stock_status' => '', // 'instock', 'lowstock', 'outofstock'
            'search'       => '',
            'orderby'      => 'product_name',
            'order'        => 'ASC',
            'limit'        => 50,
            'offset'       => 0,
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        // Base query - get products that have stock records
        $where = array( "p.post_type IN ('product', 'product_variation')", "p.post_status = 'publish'" );
        $values = array();

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = $args['category_id'];
        }

        $where_clause = implode( ' AND ', $where );

        // Get active branches for dynamic columns
        $branches = WBIM_Branch::get_all( array( 'is_active' => 1 ) );

        // Build dynamic SELECT for each branch
        $branch_selects = array();
        foreach ( $branches as $branch ) {
            $branch_selects[] = $wpdb->prepare(
                "(SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = p.ID AND branch_id = %d) as branch_{$branch->id}",
                $branch->id
            );
        }
        $branch_select_sql = ! empty( $branch_selects ) ? ', ' . implode( ', ', $branch_selects ) : '';

        // Validate orderby
        $allowed_orderby = array( 'product_name', 'sku', 'total_stock' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'product_name';

        // Map orderby to actual column
        $orderby_map = array(
            'product_name' => 'p.post_title',
            'sku'          => 'pm_sku.meta_value',
            'total_stock'  => 'total_stock',
        );
        $orderby_sql = $orderby_map[ $orderby ];

        // Validate order
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT DISTINCT p.ID as product_id,
                0 as variation_id,
                p.post_title as product_name,
                p.post_type,
                pm_sku.meta_value as sku,
                (SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = p.ID) as total_stock,
                pm_stock.meta_value as wc_stock
                {$branch_select_sql}
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            {$join_terms}
            WHERE {$where_clause}
            ORDER BY {$orderby_sql} {$order}
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

        // Filter by stock status if needed
        if ( ! empty( $args['stock_status'] ) && ! empty( $results ) ) {
            $settings = get_option( 'wbim_settings', array() );
            $low_threshold = isset( $settings['low_stock_threshold'] ) ? (int) $settings['low_stock_threshold'] : 5;

            $results = array_filter( $results, function( $item ) use ( $args, $low_threshold ) {
                $total = (int) $item->total_stock;

                switch ( $args['stock_status'] ) {
                    case 'instock':
                        return $total > $low_threshold;
                    case 'lowstock':
                        return $total > 0 && $total <= $low_threshold;
                    case 'outofstock':
                        return $total === 0;
                    default:
                        return true;
                }
            });
        }

        return $results;
    }

    /**
     * Get stock count with filters
     *
     * @param array $args Query arguments.
     * @return int
     */
    public static function get_count( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'    => 0,
            'category_id'  => 0,
            'stock_status' => '',
            'search'       => '',
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        $where = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
        $values = array();

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR pm.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = $args['category_id'];
        }

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            {$join_terms}
            WHERE {$where_clause}";

        if ( empty( $values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var( $sql );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Set stock with full data
     *
     * @param int   $product_id   Product ID.
     * @param int   $variation_id Variation ID.
     * @param int   $branch_id    Branch ID.
     * @param array $data         Stock data (quantity, low_stock_threshold, shelf_location).
     * @return bool|WP_Error
     */
    public static function set( $product_id, $variation_id, $branch_id, $data ) {
        global $wpdb;

        $table = self::get_table();

        error_log( "WBIM Stock::set() called - product_id: $product_id, variation_id: $variation_id, branch_id: $branch_id, data: " . print_r( $data, true ) );

        // Get current stock - note: get() params are (product_id, branch_id, variation_id)
        $current = self::get( $product_id, $branch_id, $variation_id ? $variation_id : 0 );
        error_log( "WBIM Stock::set() current: " . ( $current ? "id={$current->id}, qty={$current->quantity}" : 'null' ) );

        $old_quantity = $current ? (int) $current->quantity : 0;
        $new_quantity = isset( $data['quantity'] ) ? (int) $data['quantity'] : $old_quantity;

        // Prepare update data
        $update_data = array(
            'quantity'   => $new_quantity,
            'updated_at' => current_time( 'mysql' ),
        );
        $format = array( '%d', '%s' );

        if ( isset( $data['low_stock_threshold'] ) ) {
            $update_data['low_stock_threshold'] = (int) $data['low_stock_threshold'];
            $format[] = '%d';
        }

        if ( isset( $data['shelf_location'] ) ) {
            $update_data['shelf_location'] = sanitize_text_field( $data['shelf_location'] );
            $format[] = '%s';
        }

        if ( $current ) {
            // Update existing record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $table,
                $update_data,
                array( 'id' => $current->id ),
                $format,
                array( '%d' )
            );
            error_log( "WBIM Stock::set() UPDATE result: " . var_export( $result, true ) . ", error: " . $wpdb->last_error );
        } else {
            // Insert new record
            $insert_data = array_merge(
                array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'branch_id'    => $branch_id,
                ),
                $update_data
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $table,
                $insert_data,
                array_merge( array( '%d', '%d', '%d' ), $format )
            );
            error_log( "WBIM Stock::set() INSERT result: " . var_export( $result, true ) . ", insert_id: " . $wpdb->insert_id . ", error: " . $wpdb->last_error );
        }

        if ( false === $result ) {
            error_log( "WBIM Stock::set() FAILED!" );
            return new WP_Error(
                'db_error',
                __( 'Failed to update stock.', 'wbim' )
            );
        }

        error_log( "WBIM Stock::set() SUCCESS - new quantity: $new_quantity" );

        // Log the change if quantity changed
        if ( $new_quantity !== $old_quantity ) {
            $note = isset( $data['note'] ) ? $data['note'] : '';
            WBIM_Stock_Log::create(
                array(
                    'product_id'      => $product_id,
                    'variation_id'    => $variation_id,
                    'branch_id'       => $branch_id,
                    'quantity_change' => $new_quantity - $old_quantity,
                    'quantity_before' => $old_quantity,
                    'quantity_after'  => $new_quantity,
                    'action_type'     => 'adjustment',
                    'note'            => $note,
                )
            );

            // Trigger action for WC sync
            do_action( 'wbim_stock_updated', $product_id, $variation_id, $branch_id, $new_quantity );
        }

        return true;
    }

    /**
     * Set stock quantity (legacy method)
     *
     * @param int    $product_id   Product ID.
     * @param int    $branch_id    Branch ID.
     * @param int    $quantity     New quantity.
     * @param int    $variation_id Variation ID.
     * @param string $note         Optional note.
     * @return bool|WP_Error
     */
    public static function set_quantity( $product_id, $branch_id, $quantity, $variation_id = 0, $note = '' ) {
        return self::set( $product_id, $variation_id, $branch_id, array(
            'quantity' => $quantity,
            'note'     => $note,
        ) );
    }

    /**
     * Adjust stock quantity (add or subtract)
     *
     * @param int    $product_id     Product ID.
     * @param int    $variation_id   Variation ID.
     * @param int    $branch_id      Branch ID.
     * @param int    $quantity_change Quantity change (positive or negative).
     * @param string $action_type    Action type for logging.
     * @param int    $reference_id   Optional reference ID.
     * @param string $note           Optional note.
     * @return bool|WP_Error
     */
    public static function adjust( $product_id, $variation_id, $branch_id, $quantity_change, $action_type = 'adjustment', $reference_id = null, $note = '' ) {
        global $wpdb;

        $table = self::get_table();

        // Get current stock
        $current = self::get( $product_id, $branch_id, $variation_id );
        $old_quantity = $current ? (int) $current->quantity : 0;
        $new_quantity = $old_quantity + $quantity_change;

        // Prevent negative stock
        if ( $new_quantity < 0 ) {
            return new WP_Error(
                'insufficient_stock',
                __( 'Insufficient stock for this operation.', 'wbim' )
            );
        }

        if ( $current ) {
            // Update existing record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $table,
                array(
                    'quantity'   => $new_quantity,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $current->id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        } else {
            // Insert new record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $table,
                array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'branch_id'    => $branch_id,
                    'quantity'     => $new_quantity,
                    'updated_at'   => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%d', '%s' )
            );
        }

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to adjust stock quantity.', 'wbim' )
            );
        }

        // Log the change
        WBIM_Stock_Log::create(
            array(
                'product_id'      => $product_id,
                'variation_id'    => $variation_id,
                'branch_id'       => $branch_id,
                'quantity_change' => $quantity_change,
                'quantity_before' => $old_quantity,
                'quantity_after'  => $new_quantity,
                'action_type'     => $action_type,
                'reference_id'    => $reference_id,
                'reference_type'  => $action_type,
                'note'            => $note,
            )
        );

        // Trigger action for WC sync
        do_action( 'wbim_stock_updated', $product_id, $variation_id, $branch_id, $new_quantity );

        return true;
    }

    /**
     * Legacy adjust_quantity method
     *
     * @param int    $product_id   Product ID.
     * @param int    $branch_id    Branch ID.
     * @param int    $change       Quantity change.
     * @param int    $variation_id Variation ID.
     * @param string $action_type  Action type.
     * @param string $note         Note.
     * @param int    $reference_id Reference ID.
     * @param string $reference_type Reference type.
     * @return bool|WP_Error
     */
    public static function adjust_quantity( $product_id, $branch_id, $change, $variation_id = 0, $action_type = 'adjustment', $note = '', $reference_id = null, $reference_type = '' ) {
        return self::adjust( $product_id, $variation_id, $branch_id, $change, $action_type, $reference_id, $note );
    }

    /**
     * Get total stock across all branches
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return int
     */
    public static function get_total( $product_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = %d AND variation_id = %d",
                $product_id,
                $variation_id
            )
        );

        return (int) $total;
    }

    /**
     * Legacy alias for get_total
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return int
     */
    public static function get_total_quantity( $product_id, $variation_id = 0 ) {
        return self::get_total( $product_id, $variation_id );
    }

    /**
     * Sync WooCommerce stock with branch total
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return bool|WP_Error
     */
    public static function sync_wc_stock( $product_id, $variation_id = 0 ) {
        $total = self::get_total( $product_id, $variation_id );

        $target_id = $variation_id > 0 ? $variation_id : $product_id;
        $product = wc_get_product( $target_id );

        if ( ! $product ) {
            return new WP_Error(
                'product_not_found',
                __( 'Product not found.', 'wbim' )
            );
        }

        // Update WooCommerce stock
        $product->set_stock_quantity( $total );
        $product->set_manage_stock( true );

        if ( $total > 0 ) {
            $product->set_stock_status( 'instock' );
        } else {
            $product->set_stock_status( 'outofstock' );
        }

        $product->save();

        return true;
    }

    /**
     * Get products with low stock
     *
     * @param int|null $branch_id Optional branch ID to filter.
     * @param int      $limit     Number of items to return.
     * @return array
     */
    public static function get_low_stock_products( $branch_id = null, $limit = 20 ) {
        global $wpdb;

        $table = self::get_table();
        $settings = get_option( 'wbim_settings', array() );
        $default_threshold = isset( $settings['low_stock_threshold'] ) ? (int) $settings['low_stock_threshold'] : 5;

        $where = "s.quantity <= CASE WHEN s.low_stock_threshold > 0 THEN s.low_stock_threshold ELSE {$default_threshold} END AND s.quantity > 0";

        if ( null !== $branch_id ) {
            $where .= $wpdb->prepare( ' AND s.branch_id = %d', $branch_id );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as product_name, b.name as branch_name, pm.meta_value as sku
                FROM {$table} s
                LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON s.branch_id = b.id
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE {$where}
                ORDER BY s.quantity ASC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Legacy alias for get_low_stock_products
     *
     * @param int|null $branch_id Branch ID.
     * @param int      $limit     Limit.
     * @return array
     */
    public static function get_low_stock( $branch_id = null, $limit = 20 ) {
        return self::get_low_stock_products( $branch_id, $limit );
    }

    /**
     * Bulk update stock records
     *
     * @param array $items Array of items with product_id, variation_id, branch_id, quantity.
     * @return array Results with success and error counts.
     */
    public static function bulk_update( $items ) {
        $results = array(
            'success' => 0,
            'errors'  => 0,
            'messages' => array(),
        );

        foreach ( $items as $item ) {
            $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
            $branch_id = isset( $item['branch_id'] ) ? absint( $item['branch_id'] ) : 0;
            $quantity = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 0;

            if ( ! $product_id || ! $branch_id ) {
                $results['errors']++;
                $results['messages'][] = __( 'Invalid product or branch ID.', 'wbim' );
                continue;
            }

            if ( $quantity < 0 ) {
                $results['errors']++;
                $results['messages'][] = sprintf(
                    /* translators: %d: Product ID */
                    __( 'Invalid quantity for product #%d.', 'wbim' ),
                    $product_id
                );
                continue;
            }

            $result = self::set( $product_id, $variation_id, $branch_id, array(
                'quantity' => $quantity,
                'note'     => __( 'Bulk update', 'wbim' ),
            ) );

            if ( is_wp_error( $result ) ) {
                $results['errors']++;
                $results['messages'][] = $result->get_error_message();
            } else {
                $results['success']++;
            }
        }

        return $results;
    }

    /**
     * Update shelf location
     *
     * @param int    $product_id      Product ID.
     * @param int    $branch_id       Branch ID.
     * @param string $shelf_location  Shelf location.
     * @param int    $variation_id    Variation ID.
     * @return bool|WP_Error
     */
    public static function update_shelf_location( $product_id, $branch_id, $shelf_location, $variation_id = 0 ) {
        return self::set( $product_id, $variation_id, $branch_id, array(
            'shelf_location' => $shelf_location,
        ) );
    }

    /**
     * Update low stock threshold
     *
     * @param int $product_id         Product ID.
     * @param int $branch_id          Branch ID.
     * @param int $threshold          Threshold value.
     * @param int $variation_id       Variation ID.
     * @return bool|WP_Error
     */
    public static function update_threshold( $product_id, $branch_id, $threshold, $variation_id = 0 ) {
        return self::set( $product_id, $variation_id, $branch_id, array(
            'low_stock_threshold' => $threshold,
        ) );
    }

    /**
     * Get stock data formatted for product edit page
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return array Associative array with branch_id as key.
     */
    public static function get_product_stock_by_branch( $product_id, $variation_id = 0 ) {
        $stocks = self::get_product_stock( $product_id, $variation_id );
        $result = array();

        foreach ( $stocks as $stock ) {
            $result[ $stock->branch_id ] = array(
                'quantity'            => (int) $stock->quantity,
                'low_stock_threshold' => (int) $stock->low_stock_threshold,
                'shelf_location'      => $stock->shelf_location,
            );
        }

        return $result;
    }

    /**
     * Delete all stock records for a product
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID (0 to delete all variations).
     * @return bool
     */
    public static function delete_product_stock( $product_id, $variation_id = null ) {
        global $wpdb;

        $table = self::get_table();

        if ( null === $variation_id ) {
            // Delete all stock for this product including variations
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete(
                $table,
                array( 'product_id' => $product_id ),
                array( '%d' )
            );
        } else {
            // Delete specific variation stock
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete(
                $table,
                array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                ),
                array( '%d', '%d' )
            );
        }

        return false !== $result;
    }
}
