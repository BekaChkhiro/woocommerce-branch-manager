<?php
/**
 * Stock Log Model
 *
 * Handles all stock log/history database operations.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stock Log model class
 *
 * @since 1.0.0
 */
class WBIM_Stock_Log {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_stock_log';

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
     * Log a stock change
     *
     * @param array $data Log data.
     * @return int|WP_Error Log ID on success, WP_Error on failure.
     */
    public static function log( $data ) {
        global $wpdb;

        $table = self::get_table();

        $insert_data = array(
            'product_id'      => absint( $data['product_id'] ),
            'variation_id'    => isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0,
            'branch_id'       => absint( $data['branch_id'] ),
            'quantity_change' => intval( $data['quantity_change'] ),
            'quantity_before' => isset( $data['quantity_before'] ) ? intval( $data['quantity_before'] ) : null,
            'quantity_after'  => isset( $data['quantity_after'] ) ? intval( $data['quantity_after'] ) : null,
            'action_type'     => sanitize_key( $data['action_type'] ),
            'reference_id'    => isset( $data['reference_id'] ) ? absint( $data['reference_id'] ) : null,
            'reference_type'  => isset( $data['reference_type'] ) ? sanitize_key( $data['reference_type'] ) : null,
            'note'            => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : null,
            'user_id'         => get_current_user_id(),
            'created_at'      => current_time( 'mysql' ),
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            $insert_data,
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to log stock change.', 'wbim' )
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get log entry by ID
     *
     * @param int $id Log ID.
     * @return object|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*,
                    p.post_title as product_name,
                    b.name as branch_name,
                    u.display_name as user_name
                FROM {$table} l
                LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON l.branch_id = b.id
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                WHERE l.id = %d",
                $id
            )
        );
    }

    /**
     * Get all log entries
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'product_id'   => 0,
            'variation_id' => null,
            'branch_id'    => 0,
            'action_type'  => '',
            'user_id'      => 0,
            'date_from'    => '',
            'date_to'      => '',
            'search'       => '',
            'orderby'      => 'created_at',
            'order'        => 'DESC',
            'limit'        => 50,
            'offset'       => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['product_id'] ) ) {
            $where[] = 'l.product_id = %d';
            $values[] = $args['product_id'];
        }

        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 'l.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'l.action_type = %s';
            $values[] = $args['action_type'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'l.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'l.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'l.user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( null !== $args['variation_id'] ) {
            $where[] = 'l.variation_id = %d';
            $values[] = absint( $args['variation_id'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(p.post_title LIKE %s OR l.note LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'created_at', 'product_id', 'branch_id', 'action_type' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

        // Validate order
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT l.*,
                p.post_title as product_name,
                b.name as branch_name,
                u.display_name as user_name
            FROM {$table} l
            LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
            LEFT JOIN {$wpdb->prefix}wbim_branches b ON l.branch_id = b.id
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY l.{$orderby} {$order}
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get log entries for a product
     *
     * @param int   $product_id   Product ID.
     * @param int   $variation_id Variation ID.
     * @param int   $branch_id    Optional branch ID.
     * @param int   $limit        Number of entries.
     * @return array
     */
    public static function get_by_product( $product_id, $variation_id = 0, $branch_id = 0, $limit = 20 ) {
        global $wpdb;

        $table = self::get_table();
        $where = 'l.product_id = %d AND l.variation_id = %d';
        $values = array( $product_id, $variation_id );

        if ( $branch_id > 0 ) {
            $where .= ' AND l.branch_id = %d';
            $values[] = $branch_id;
        }

        $values[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*,
                    b.name as branch_name,
                    u.display_name as user_name
                FROM {$table} l
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON l.branch_id = b.id
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                WHERE {$where}
                ORDER BY l.created_at DESC
                LIMIT %d",
                $values
            )
        );
    }

    /**
     * Get log entries for a branch
     *
     * @param int $branch_id Branch ID.
     * @param int $limit     Number of entries.
     * @return array
     */
    public static function get_by_branch( $branch_id, $limit = 50 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*,
                    p.post_title as product_name,
                    u.display_name as user_name
                FROM {$table} l
                LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                WHERE l.branch_id = %d
                ORDER BY l.created_at DESC
                LIMIT %d",
                $branch_id,
                $limit
            )
        );
    }

    /**
     * Get log count
     *
     * @param array $args Filter arguments.
     * @return int
     */
    public static function get_count( $args = array() ) {
        global $wpdb;

        $table = self::get_table();
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['product_id'] ) ) {
            $where[] = 'l.product_id = %d';
            $values[] = $args['product_id'];
        }

        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 'l.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'l.action_type = %s';
            $values[] = $args['action_type'];
        }

        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'l.user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'l.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'l.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        if ( isset( $args['variation_id'] ) && null !== $args['variation_id'] ) {
            $where[] = 'l.variation_id = %d';
            $values[] = absint( $args['variation_id'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(p.post_title LIKE %s OR l.note LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );
        $needs_join = ! empty( $args['search'] );

        if ( $needs_join ) {
            $sql = "SELECT COUNT(*) FROM {$table} l
                    LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
                    WHERE {$where_clause}";
        } else {
            $sql = "SELECT COUNT(*) FROM {$table} l WHERE {$where_clause}";
        }

        if ( empty( $values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var( $sql );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get product stock history
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID (default 0).
     * @param int $limit        Number of entries.
     * @return array
     */
    public static function get_product_history( $product_id, $variation_id = 0, $limit = 50 ) {
        return self::get_all(
            array(
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'limit'        => $limit,
            )
        );
    }

    /**
     * Get branch stock history
     *
     * @param int $branch_id Branch ID.
     * @param int $limit     Number of entries.
     * @return array
     */
    public static function get_branch_history( $branch_id, $limit = 50 ) {
        return self::get_all(
            array(
                'branch_id' => $branch_id,
                'limit'     => $limit,
            )
        );
    }

    /**
     * Create a log entry (alias for log method)
     *
     * @param array $data Log data.
     * @return int|WP_Error Log ID on success, WP_Error on failure.
     */
    public static function create( $data ) {
        return self::log( $data );
    }

    /**
     * Get action type label
     *
     * @param string $action_type Action type key.
     * @return string
     */
    public static function get_action_label( $action_type ) {
        $labels = array(
            'sale'         => __( 'Sale', 'wbim' ),
            'restock'      => __( 'Restock', 'wbim' ),
            'transfer_in'  => __( 'Transfer In', 'wbim' ),
            'transfer_out' => __( 'Transfer Out', 'wbim' ),
            'adjustment'   => __( 'Adjustment', 'wbim' ),
            'return'       => __( 'Return', 'wbim' ),
        );

        return isset( $labels[ $action_type ] ) ? $labels[ $action_type ] : $action_type;
    }

    /**
     * Get action types for dropdown
     *
     * @return array
     */
    public static function get_action_types() {
        return array(
            'sale'         => __( 'Sale', 'wbim' ),
            'restock'      => __( 'Restock', 'wbim' ),
            'transfer_in'  => __( 'Transfer In', 'wbim' ),
            'transfer_out' => __( 'Transfer Out', 'wbim' ),
            'adjustment'   => __( 'Adjustment', 'wbim' ),
            'return'       => __( 'Return', 'wbim' ),
        );
    }

    /**
     * Clean old log entries
     *
     * @param int $days Number of days to keep.
     * @return int Number of deleted rows.
     */
    public static function cleanup( $days = 365 ) {
        global $wpdb;

        $table = self::get_table();
        $date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $date
            )
        );
    }
}
