<?php
/**
 * Order Allocation Model
 *
 * Handles order-to-branch allocation database operations.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Order Allocation model class
 *
 * @since 1.0.0
 */
class WBIM_Order_Allocation {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_order_allocation';

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
     * Get allocations for an order
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public static function get_by_order( $order_id ) {
        global $wpdb;

        $table = self::get_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, b.name as branch_name, b.address as branch_address, b.phone as branch_phone
                FROM {$table} a
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON a.branch_id = b.id
                WHERE a.order_id = %d
                ORDER BY a.id ASC",
                $order_id
            )
        );
    }

    /**
     * Get allocation for a specific order item
     *
     * @param int $order_item_id Order item ID.
     * @return object|null
     */
    public static function get_by_order_item( $order_item_id ) {
        global $wpdb;

        $table = self::get_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, b.name as branch_name, b.address as branch_address, b.phone as branch_phone
                FROM {$table} a
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON a.branch_id = b.id
                WHERE a.order_item_id = %d",
                $order_item_id
            )
        );
    }

    /**
     * Get allocation by ID
     *
     * @param int $id Allocation ID.
     * @return object|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $table = self::get_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, b.name as branch_name
                FROM {$table} a
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON a.branch_id = b.id
                WHERE a.id = %d",
                $id
            )
        );
    }

    /**
     * Create allocation
     *
     * @param array $data Allocation data.
     * @return int|WP_Error Allocation ID on success, WP_Error on failure.
     */
    public static function create( $data ) {
        global $wpdb;

        $table = self::get_table();

        // Validate required fields
        $required = array( 'order_id', 'order_item_id', 'product_id', 'branch_id', 'quantity' );
        foreach ( $required as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                return new WP_Error(
                    'missing_field',
                    sprintf( __( 'Missing required field: %s', 'wbim' ), $field )
                );
            }
        }

        // Check if allocation already exists for this order item
        $existing = self::get_by_order_item( $data['order_item_id'] );
        if ( $existing ) {
            return new WP_Error(
                'allocation_exists',
                __( 'Allocation already exists for this order item.', 'wbim' )
            );
        }

        $insert_data = array(
            'order_id'      => absint( $data['order_id'] ),
            'order_item_id' => absint( $data['order_item_id'] ),
            'product_id'    => absint( $data['product_id'] ),
            'variation_id'  => isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0,
            'branch_id'     => absint( $data['branch_id'] ),
            'quantity'      => absint( $data['quantity'] ),
            'created_at'    => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $table,
            $insert_data,
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to create allocation.', 'wbim' )
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update allocation (change branch)
     *
     * @param int $id        Allocation ID.
     * @param int $branch_id New branch ID.
     * @return bool|WP_Error
     */
    public static function update( $id, $branch_id ) {
        global $wpdb;

        $table = self::get_table();

        // Check if allocation exists
        $existing = self::get_by_id( $id );
        if ( ! $existing ) {
            return new WP_Error(
                'not_found',
                __( 'Allocation not found.', 'wbim' )
            );
        }

        // Validate branch exists
        $branch = WBIM_Branch::get_by_id( $branch_id );
        if ( ! $branch ) {
            return new WP_Error(
                'invalid_branch',
                __( 'Invalid branch ID.', 'wbim' )
            );
        }

        $result = $wpdb->update(
            $table,
            array(
                'branch_id'  => absint( $branch_id ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to update allocation.', 'wbim' )
            );
        }

        return true;
    }

    /**
     * Delete allocation
     *
     * @param int $id Allocation ID.
     * @return bool|WP_Error
     */
    public static function delete( $id ) {
        global $wpdb;

        $table = self::get_table();

        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to delete allocation.', 'wbim' )
            );
        }

        return true;
    }

    /**
     * Delete all allocations for an order
     *
     * @param int $order_id Order ID.
     * @return bool|WP_Error
     */
    public static function delete_by_order( $order_id ) {
        global $wpdb;

        $table = self::get_table();

        $result = $wpdb->delete(
            $table,
            array( 'order_id' => $order_id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'Failed to delete allocations.', 'wbim' )
            );
        }

        return true;
    }

    /**
     * Get orders by branch
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return array
     */
    public static function get_orders_by_branch( $branch_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
            'limit'     => 50,
            'offset'    => 0,
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        $where = array( 'a.branch_id = %d' );
        $values = array( $branch_id );

        // Join with orders table for status filter
        $orders_table = $wpdb->prefix . 'wc_orders';
        $use_hpos = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
            && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        if ( $use_hpos ) {
            // HPOS enabled
            if ( ! empty( $args['status'] ) ) {
                $where[] = 'o.status = %s';
                $values[] = 'wc-' . ltrim( $args['status'], 'wc-' );
            }

            if ( ! empty( $args['date_from'] ) ) {
                $where[] = 'o.date_created_gmt >= %s';
                $values[] = $args['date_from'] . ' 00:00:00';
            }

            if ( ! empty( $args['date_to'] ) ) {
                $where[] = 'o.date_created_gmt <= %s';
                $values[] = $args['date_to'] . ' 23:59:59';
            }

            $where_clause = implode( ' AND ', $where );
            $values[] = $args['limit'];
            $values[] = $args['offset'];

            $sql = "SELECT DISTINCT a.order_id, o.status, o.date_created_gmt as order_date, o.total_amount as order_total
                    FROM {$table} a
                    INNER JOIN {$orders_table} o ON a.order_id = o.id
                    WHERE {$where_clause}
                    ORDER BY o.date_created_gmt DESC
                    LIMIT %d OFFSET %d";
        } else {
            // Legacy post-based orders
            if ( ! empty( $args['status'] ) ) {
                $where[] = 'p.post_status = %s';
                $values[] = 'wc-' . ltrim( $args['status'], 'wc-' );
            }

            if ( ! empty( $args['date_from'] ) ) {
                $where[] = 'p.post_date >= %s';
                $values[] = $args['date_from'] . ' 00:00:00';
            }

            if ( ! empty( $args['date_to'] ) ) {
                $where[] = 'p.post_date <= %s';
                $values[] = $args['date_to'] . ' 23:59:59';
            }

            $where_clause = implode( ' AND ', $where );
            $values[] = $args['limit'];
            $values[] = $args['offset'];

            $sql = "SELECT DISTINCT a.order_id, p.post_status as status, p.post_date as order_date
                    FROM {$table} a
                    INNER JOIN {$wpdb->posts} p ON a.order_id = p.ID
                    WHERE {$where_clause}
                    ORDER BY p.post_date DESC
                    LIMIT %d OFFSET %d";
        }

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get count of orders by branch
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return int
     */
    public static function get_orders_count_by_branch( $branch_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        $where = array( 'a.branch_id = %d' );
        $values = array( $branch_id );

        $use_hpos = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
            && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        if ( $use_hpos ) {
            $orders_table = $wpdb->prefix . 'wc_orders';

            if ( ! empty( $args['status'] ) ) {
                $where[] = 'o.status = %s';
                $values[] = 'wc-' . ltrim( $args['status'], 'wc-' );
            }

            $where_clause = implode( ' AND ', $where );

            $sql = "SELECT COUNT(DISTINCT a.order_id)
                    FROM {$table} a
                    INNER JOIN {$orders_table} o ON a.order_id = o.id
                    WHERE {$where_clause}";
        } else {
            if ( ! empty( $args['status'] ) ) {
                $where[] = 'p.post_status = %s';
                $values[] = 'wc-' . ltrim( $args['status'], 'wc-' );
            }

            $where_clause = implode( ' AND ', $where );

            $sql = "SELECT COUNT(DISTINCT a.order_id)
                    FROM {$table} a
                    INNER JOIN {$wpdb->posts} p ON a.order_id = p.ID
                    WHERE {$where_clause}";
        }

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Check if stock was deducted for an order
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    public static function is_stock_deducted( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        return 'yes' === $order->get_meta( '_wbim_stock_deducted' );
    }

    /**
     * Mark stock as deducted for an order
     *
     * @param int  $order_id Order ID.
     * @param bool $deducted Whether stock is deducted.
     * @return void
     */
    public static function set_stock_deducted( $order_id, $deducted = true ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->update_meta_data( '_wbim_stock_deducted', $deducted ? 'yes' : 'no' );
            $order->save();
        }
    }

    /**
     * Get branch for order (single branch or first if multiple)
     *
     * @param int $order_id Order ID.
     * @return object|null Branch object or null.
     */
    public static function get_order_branch( $order_id ) {
        $allocations = self::get_by_order( $order_id );

        if ( empty( $allocations ) ) {
            // Check order meta
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $branch_id = $order->get_meta( '_wbim_branch_id' );
                if ( $branch_id ) {
                    return WBIM_Branch::get_by_id( $branch_id );
                }
            }
            return null;
        }

        return WBIM_Branch::get_by_id( $allocations[0]->branch_id );
    }

    /**
     * Check if order has multiple branches
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    public static function has_multiple_branches( $order_id ) {
        $allocations = self::get_by_order( $order_id );

        if ( count( $allocations ) <= 1 ) {
            return false;
        }

        $branches = array_unique( array_column( $allocations, 'branch_id' ) );

        return count( $branches ) > 1;
    }
}
