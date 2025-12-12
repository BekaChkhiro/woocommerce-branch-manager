<?php
/**
 * Reports Class
 *
 * Core class for generating report data.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reports class
 *
 * @since 1.0.0
 */
class WBIM_Reports {

    /**
     * Get stock report data
     *
     * @param array $args Report arguments.
     * @return array
     */
    public static function get_stock_report( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'   => 0,
            'category_id' => 0,
            'date'        => current_time( 'Y-m-d' ),
            'orderby'     => 'product_name',
            'order'       => 'ASC',
            'limit'       => 0,
            'offset'      => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $stock_table = $wpdb->prefix . 'wbim_stock';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        // Get active branches
        $branches = WBIM_Branch::get_active();
        if ( empty( $branches ) ) {
            return array(
                'items'    => array(),
                'branches' => array(),
                'totals'   => array(),
            );
        }

        // Build branch columns
        // For variations: match by variation_id, for simple products: match by product_id with variation_id = 0
        $branch_selects = array();
        $branch_ids = array();
        foreach ( $branches as $branch ) {
            $branch_ids[] = $branch->id;
            $branch_selects[] = $wpdb->prepare(
                "COALESCE((SELECT SUM(s.quantity) FROM {$stock_table} s
                  WHERE s.branch_id = %d
                  AND (
                      (p.post_type = 'product_variation' AND s.variation_id = p.ID)
                      OR (p.post_type = 'product' AND s.product_id = p.ID AND s.variation_id = 0)
                  )
                ), 0) as branch_%d",
                $branch->id,
                $branch->id
            );
        }
        $branch_select_sql = implode( ', ', $branch_selects );

        // Base query
        $where = array( "p.post_type IN ('product', 'product_variation')", "p.post_status = 'publish'" );
        $values = array();

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = "EXISTS (SELECT 1 FROM {$stock_table} s WHERE s.branch_id = %d AND s.quantity > 0 AND (
                (p.post_type = 'product_variation' AND s.variation_id = p.ID)
                OR (p.post_type = 'product' AND s.product_id = p.ID AND s.variation_id = 0)
            ))";
            $values[] = absint( $args['branch_id'] );
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = absint( $args['category_id'] );
        }

        $where_clause = implode( ' AND ', $where );

        // Orderby
        $orderby_map = array(
            'product_name' => 'p.post_title',
            'sku'          => 'pm_sku.meta_value',
            'total_stock'  => 'total_stock',
        );
        $orderby = isset( $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : 'p.post_title';
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT
                p.ID as product_id,
                p.post_title as product_name,
                p.post_type,
                pm_sku.meta_value as sku,
                pm_price.meta_value as price,
                COALESCE((SELECT SUM(quantity) FROM {$stock_table} s2
                    WHERE (p.post_type = 'product_variation' AND s2.variation_id = p.ID)
                       OR (p.post_type = 'product' AND s2.product_id = p.ID AND s2.variation_id = 0)
                ), 0) as total_stock,
                {$branch_select_sql}
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            {$join_terms}
            WHERE {$where_clause}
            ORDER BY {$orderby} {$order}";

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        }

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        $items = $wpdb->get_results( $sql );

        // Calculate totals
        $totals = array(
            'total_stock' => 0,
            'total_value' => 0,
        );
        foreach ( $branch_ids as $branch_id ) {
            $totals[ 'branch_' . $branch_id ] = 0;
        }

        foreach ( $items as $item ) {
            $totals['total_stock'] += (int) $item->total_stock;
            $price = floatval( $item->price );
            $totals['total_value'] += $price * (int) $item->total_stock;

            foreach ( $branch_ids as $branch_id ) {
                $key = 'branch_' . $branch_id;
                $totals[ $key ] += (int) $item->$key;
            }
        }

        return array(
            'items'    => $items,
            'branches' => $branches,
            'totals'   => $totals,
        );
    }

    /**
     * Get sales report by branch
     *
     * @param array $args Report arguments.
     * @return array
     */
    public static function get_sales_report( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id' => 0,
            'date_from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'   => current_time( 'Y-m-d' ),
            'group_by'  => 'day', // day, week, month
        );

        $args = wp_parse_args( $args, $defaults );

        $allocation_table = $wpdb->prefix . 'wbim_order_allocation';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        // Get branches
        $branches = WBIM_Branch::get_active();
        if ( empty( $branches ) ) {
            return array(
                'items'    => array(),
                'branches' => array(),
                'summary'  => array(),
                'chart'    => array(),
            );
        }

        // Date format for grouping
        $date_format = '%Y-%m-%d';
        $php_format = 'Y-m-d';
        if ( $args['group_by'] === 'week' ) {
            $date_format = '%x-W%v';
            $php_format = 'o-\WW';
        } elseif ( $args['group_by'] === 'month' ) {
            $date_format = '%Y-%m';
            $php_format = 'Y-m';
        }

        // Build query for each branch
        $results = array();
        $summary = array(
            'total_orders'   => 0,
            'total_revenue'  => 0,
            'total_items'    => 0,
            'average_order'  => 0,
            'by_branch'      => array(),
        );

        foreach ( $branches as $branch ) {
            if ( $args['branch_id'] && $args['branch_id'] != $branch->id ) {
                continue;
            }

            // Use HPOS-compatible query
            if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
                 \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                // HPOS query
                $sql = $wpdb->prepare(
                    "SELECT
                        DATE_FORMAT(o.date_created_gmt, %s) as period,
                        COUNT(DISTINCT oa.order_id) as order_count,
                        SUM(oa.quantity) as items_sold,
                        SUM(oi.product_net_revenue) as revenue
                    FROM {$allocation_table} oa
                    INNER JOIN {$wpdb->prefix}wc_orders o ON oa.order_id = o.id
                    INNER JOIN {$wpdb->prefix}wc_order_product_lookup oi ON oa.order_item_id = oi.order_item_id
                    WHERE oa.branch_id = %d
                    AND DATE(o.date_created_gmt) BETWEEN %s AND %s
                    AND o.status IN ('wc-completed', 'wc-processing')
                    GROUP BY period
                    ORDER BY period ASC",
                    $date_format,
                    $branch->id,
                    $args['date_from'],
                    $args['date_to']
                );
            } else {
                // Legacy posts table query
                $sql = $wpdb->prepare(
                    "SELECT
                        DATE_FORMAT(p.post_date, %s) as period,
                        COUNT(DISTINCT oa.order_id) as order_count,
                        SUM(oa.quantity) as items_sold,
                        COALESCE(SUM(oim.meta_value), 0) as revenue
                    FROM {$allocation_table} oa
                    INNER JOIN {$wpdb->posts} p ON oa.order_id = p.ID
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oa.order_item_id = oim.order_item_id AND oim.meta_key = '_line_total'
                    WHERE oa.branch_id = %d
                    AND DATE(p.post_date) BETWEEN %s AND %s
                    AND p.post_status IN ('wc-completed', 'wc-processing')
                    GROUP BY period
                    ORDER BY period ASC",
                    $date_format,
                    $branch->id,
                    $args['date_from'],
                    $args['date_to']
                );
            }

            $branch_data = $wpdb->get_results( $sql );

            // Process results
            $branch_summary = array(
                'orders'  => 0,
                'revenue' => 0,
                'items'   => 0,
            );

            foreach ( $branch_data as $row ) {
                if ( ! isset( $results[ $row->period ] ) ) {
                    $results[ $row->period ] = array(
                        'period' => $row->period,
                    );
                }
                $results[ $row->period ][ 'branch_' . $branch->id ] = array(
                    'orders'  => (int) $row->order_count,
                    'revenue' => floatval( $row->revenue ),
                    'items'   => (int) $row->items_sold,
                );

                $branch_summary['orders'] += (int) $row->order_count;
                $branch_summary['revenue'] += floatval( $row->revenue );
                $branch_summary['items'] += (int) $row->items_sold;
            }

            $summary['by_branch'][ $branch->id ] = $branch_summary;
            $summary['total_orders'] += $branch_summary['orders'];
            $summary['total_revenue'] += $branch_summary['revenue'];
            $summary['total_items'] += $branch_summary['items'];
        }

        // Calculate average
        if ( $summary['total_orders'] > 0 ) {
            $summary['average_order'] = $summary['total_revenue'] / $summary['total_orders'];
        }

        // Sort results by period
        ksort( $results );

        // Prepare chart data
        $chart_data = array(
            'labels'   => array(),
            'datasets' => array(),
        );

        $colors = array( '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796' );
        $color_index = 0;

        foreach ( $branches as $branch ) {
            if ( $args['branch_id'] && $args['branch_id'] != $branch->id ) {
                continue;
            }

            $dataset = array(
                'label'           => $branch->name,
                'data'            => array(),
                'borderColor'     => $colors[ $color_index % count( $colors ) ],
                'backgroundColor' => $colors[ $color_index % count( $colors ) ] . '20',
                'fill'            => true,
            );

            foreach ( $results as $period => $data ) {
                if ( empty( $chart_data['labels'] ) || ! in_array( $period, $chart_data['labels'], true ) ) {
                    $chart_data['labels'][] = $period;
                }
                $branch_key = 'branch_' . $branch->id;
                $dataset['data'][] = isset( $data[ $branch_key ] ) ? $data[ $branch_key ]['revenue'] : 0;
            }

            $chart_data['datasets'][] = $dataset;
            $color_index++;
        }

        return array(
            'items'    => array_values( $results ),
            'branches' => $branches,
            'summary'  => $summary,
            'chart'    => $chart_data,
        );
    }

    /**
     * Get transfers report
     *
     * @param array $args Report arguments.
     * @return array
     */
    public static function get_transfers_report( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'from_branch_id' => 0,
            'to_branch_id'   => 0,
            'date_from'      => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'        => current_time( 'Y-m-d' ),
            'status'         => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $transfers_table = $wpdb->prefix . 'wbim_transfers';
        $items_table = $wpdb->prefix . 'wbim_transfer_items';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        // Build where clause
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['from_branch_id'] ) ) {
            $where[] = 't.source_branch_id = %d';
            $values[] = absint( $args['from_branch_id'] );
        }

        if ( ! empty( $args['to_branch_id'] ) ) {
            $where[] = 't.destination_branch_id = %d';
            $values[] = absint( $args['to_branch_id'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'DATE(t.created_at) >= %s';
            $values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'DATE(t.created_at) <= %s';
            $values[] = $args['date_to'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 't.status = %s';
            $values[] = sanitize_text_field( $args['status'] );
        }

        $where_clause = implode( ' AND ', $where );

        // Summary query
        $summary_sql = "SELECT
                t.status,
                COUNT(*) as count,
                COALESCE(SUM(ti.total_items), 0) as total_items
            FROM {$transfers_table} t
            LEFT JOIN (
                SELECT transfer_id, SUM(quantity) as total_items
                FROM {$items_table}
                GROUP BY transfer_id
            ) ti ON t.id = ti.transfer_id
            WHERE {$where_clause}
            GROUP BY t.status";

        if ( ! empty( $values ) ) {
            $summary_sql = $wpdb->prepare( $summary_sql, $values );
        }

        $summary_results = $wpdb->get_results( $summary_sql );

        // Process summary
        $summary = array(
            'total'       => 0,
            'draft'       => 0,
            'pending'     => 0,
            'in_transit'  => 0,
            'completed'   => 0,
            'cancelled'   => 0,
            'total_items' => 0,
        );

        foreach ( $summary_results as $row ) {
            $summary['total'] += (int) $row->count;
            $summary['total_items'] += (int) $row->total_items;
            if ( isset( $summary[ $row->status ] ) ) {
                $summary[ $row->status ] = (int) $row->count;
            }
        }

        // Monthly breakdown
        $monthly_sql = "SELECT
                DATE_FORMAT(t.created_at, '%%Y-%%m') as month,
                t.status,
                COUNT(*) as count
            FROM {$transfers_table} t
            WHERE {$where_clause}
            GROUP BY month, t.status
            ORDER BY month ASC";

        if ( ! empty( $values ) ) {
            $monthly_sql = $wpdb->prepare( $monthly_sql, $values );
        }

        $monthly_results = $wpdb->get_results( $monthly_sql );

        // Process monthly data
        $monthly = array();
        foreach ( $monthly_results as $row ) {
            if ( ! isset( $monthly[ $row->month ] ) ) {
                $monthly[ $row->month ] = array(
                    'month'      => $row->month,
                    'draft'      => 0,
                    'pending'    => 0,
                    'in_transit' => 0,
                    'completed'  => 0,
                    'cancelled'  => 0,
                );
            }
            if ( isset( $monthly[ $row->month ][ $row->status ] ) ) {
                $monthly[ $row->month ][ $row->status ] = (int) $row->count;
            }
        }

        // Top transferred products
        $top_products_sql = "SELECT
                ti.product_id,
                ti.product_name,
                ti.sku,
                SUM(ti.quantity) as total_quantity,
                COUNT(DISTINCT ti.transfer_id) as transfer_count
            FROM {$items_table} ti
            INNER JOIN {$transfers_table} t ON ti.transfer_id = t.id
            WHERE {$where_clause}
            GROUP BY ti.product_id, ti.product_name, ti.sku
            ORDER BY total_quantity DESC
            LIMIT 10";

        if ( ! empty( $values ) ) {
            $top_products_sql = $wpdb->prepare( $top_products_sql, $values );
        }

        $top_products = $wpdb->get_results( $top_products_sql );

        // Branch pair statistics
        $branch_pairs_sql = "SELECT
                t.source_branch_id,
                sb.name as source_name,
                t.destination_branch_id,
                db.name as destination_name,
                COUNT(*) as transfer_count,
                SUM(ti.total_items) as total_items
            FROM {$transfers_table} t
            LEFT JOIN {$branches_table} sb ON t.source_branch_id = sb.id
            LEFT JOIN {$branches_table} db ON t.destination_branch_id = db.id
            LEFT JOIN (
                SELECT transfer_id, SUM(quantity) as total_items
                FROM {$items_table}
                GROUP BY transfer_id
            ) ti ON t.id = ti.transfer_id
            WHERE {$where_clause}
            GROUP BY t.source_branch_id, t.destination_branch_id
            ORDER BY transfer_count DESC";

        if ( ! empty( $values ) ) {
            $branch_pairs_sql = $wpdb->prepare( $branch_pairs_sql, $values );
        }

        $branch_pairs = $wpdb->get_results( $branch_pairs_sql );

        // Chart data
        $chart_data = array(
            'labels'   => array_keys( $monthly ),
            'datasets' => array(
                array(
                    'label'           => __( 'დასრულებული', 'wbim' ),
                    'data'            => array_column( array_values( $monthly ), 'completed' ),
                    'backgroundColor' => '#1cc88a',
                ),
                array(
                    'label'           => __( 'მოლოდინში', 'wbim' ),
                    'data'            => array_column( array_values( $monthly ), 'pending' ),
                    'backgroundColor' => '#f6c23e',
                ),
                array(
                    'label'           => __( 'გაუქმებული', 'wbim' ),
                    'data'            => array_column( array_values( $monthly ), 'cancelled' ),
                    'backgroundColor' => '#e74a3b',
                ),
            ),
        );

        return array(
            'summary'       => $summary,
            'monthly'       => array_values( $monthly ),
            'top_products'  => $top_products,
            'branch_pairs'  => $branch_pairs,
            'chart'         => $chart_data,
        );
    }

    /**
     * Get low stock report
     *
     * @param array $args Report arguments.
     * @return array
     */
    public static function get_low_stock_report( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'      => 0,
            'category_id'    => 0,
            'threshold_type' => 'default', // default or custom
            'include_zero'   => true,
        );

        $args = wp_parse_args( $args, $defaults );

        $stock_table = $wpdb->prefix . 'wbim_stock';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        // Get default threshold from settings
        $settings = get_option( 'wbim_settings', array() );
        $default_threshold = isset( $settings['low_stock_threshold'] ) ? (int) $settings['low_stock_threshold'] : 5;

        // Build where clause
        $where = array( "p.post_type IN ('product', 'product_variation')", "p.post_status = 'publish'" );
        $values = array();

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 's.branch_id = %d';
            $values[] = absint( $args['branch_id'] );
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = absint( $args['category_id'] );
        }

        // Threshold condition
        $threshold_condition = $args['threshold_type'] === 'custom'
            ? 's.quantity <= s.low_stock_threshold AND s.low_stock_threshold > 0'
            : 's.quantity <= ' . $default_threshold;

        if ( ! $args['include_zero'] ) {
            $threshold_condition .= ' AND s.quantity > 0';
        }

        $where[] = '(' . $threshold_condition . ')';

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT
                s.id,
                s.product_id,
                s.variation_id,
                s.branch_id,
                s.quantity,
                s.low_stock_threshold,
                CASE
                    WHEN s.variation_id > 0 THEN CONCAT(parent.post_title, ' - ', p.post_title)
                    ELSE p.post_title
                END as product_name,
                pm_sku.meta_value as sku,
                b.name as branch_name,
                CASE
                    WHEN s.quantity = 0 THEN 'critical'
                    WHEN s.low_stock_threshold > 0 AND s.quantity <= s.low_stock_threshold THEN 'warning'
                    WHEN s.quantity <= {$default_threshold} THEN 'warning'
                    ELSE 'normal'
                END as status
            FROM {$stock_table} s
            INNER JOIN {$wpdb->posts} p ON (
                (s.variation_id > 0 AND s.variation_id = p.ID)
                OR (s.variation_id = 0 AND s.product_id = p.ID)
            )
            LEFT JOIN {$wpdb->posts} parent ON (s.variation_id > 0 AND s.product_id = parent.ID)
            LEFT JOIN {$wpdb->postmeta} pm_sku ON (
                CASE WHEN s.variation_id > 0 THEN s.variation_id ELSE s.product_id END
            ) = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            INNER JOIN {$branches_table} b ON s.branch_id = b.id
            {$join_terms}
            WHERE {$where_clause}
            ORDER BY s.quantity ASC, p.post_title ASC";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        $items = $wpdb->get_results( $sql );

        // Group by status
        $by_status = array(
            'critical' => 0,
            'warning'  => 0,
        );

        foreach ( $items as $item ) {
            if ( isset( $by_status[ $item->status ] ) ) {
                $by_status[ $item->status ]++;
            }
        }

        // Get available stock from other branches for transfer suggestions
        foreach ( $items as &$item ) {
            $other_stock_sql = $wpdb->prepare(
                "SELECT s.branch_id, b.name as branch_name, s.quantity
                FROM {$stock_table} s
                INNER JOIN {$branches_table} b ON s.branch_id = b.id
                WHERE s.product_id = %d
                AND s.variation_id = %d
                AND s.branch_id != %d
                AND s.quantity > 0
                ORDER BY s.quantity DESC
                LIMIT 3",
                $item->product_id,
                $item->variation_id,
                $item->branch_id
            );
            $item->available_from = $wpdb->get_results( $other_stock_sql );
        }
        unset( $item );

        return array(
            'items'     => $items,
            'by_status' => $by_status,
            'threshold' => $default_threshold,
        );
    }

    /**
     * Get stock movement report
     *
     * @param array $args Report arguments.
     * @return array
     */
    public static function get_stock_movement_report( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'   => 0,
            'product_id'  => 0,
            'date_from'   => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'     => current_time( 'Y-m-d' ),
            'action_type' => '',
            'limit'       => 100,
            'offset'      => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $log_table = $wpdb->prefix . 'wbim_stock_log';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        // Build where clause
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 'sl.branch_id = %d';
            $values[] = absint( $args['branch_id'] );
        }

        if ( ! empty( $args['product_id'] ) ) {
            $where[] = 'sl.product_id = %d';
            $values[] = absint( $args['product_id'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'DATE(sl.created_at) >= %s';
            $values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'DATE(sl.created_at) <= %s';
            $values[] = $args['date_to'];
        }

        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'sl.action_type = %s';
            $values[] = sanitize_text_field( $args['action_type'] );
        }

        $where_clause = implode( ' AND ', $where );

        // Get items
        $sql = "SELECT
                sl.*,
                p.post_title as product_name,
                b.name as branch_name,
                u.display_name as user_name
            FROM {$log_table} sl
            LEFT JOIN {$wpdb->posts} p ON sl.product_id = p.ID
            LEFT JOIN {$branches_table} b ON sl.branch_id = b.id
            LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY sl.created_at DESC
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $items = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

        // Get summary by action type
        $summary_values = array_slice( $values, 0, -2 ); // Remove limit and offset
        $summary_sql = "SELECT
                sl.action_type,
                COUNT(*) as count,
                SUM(CASE WHEN sl.quantity_change > 0 THEN sl.quantity_change ELSE 0 END) as total_in,
                SUM(CASE WHEN sl.quantity_change < 0 THEN ABS(sl.quantity_change) ELSE 0 END) as total_out
            FROM {$log_table} sl
            WHERE {$where_clause}
            GROUP BY sl.action_type";

        if ( ! empty( $summary_values ) ) {
            $summary_sql = $wpdb->prepare( $summary_sql, $summary_values );
        }

        $summary = $wpdb->get_results( $summary_sql );

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$log_table} sl WHERE {$where_clause}";
        if ( ! empty( $summary_values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $summary_values );
        }
        $total_count = $wpdb->get_var( $count_sql );

        return array(
            'items'       => $items,
            'summary'     => $summary,
            'total_count' => (int) $total_count,
        );
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    public static function get_dashboard_stats() {
        global $wpdb;

        $stock_table = $wpdb->prefix . 'wbim_stock';
        $transfers_table = $wpdb->prefix . 'wbim_transfers';
        $allocation_table = $wpdb->prefix . 'wbim_order_allocation';
        $branches_table = $wpdb->prefix . 'wbim_branches';

        $stats = array();

        // Total branches
        $stats['branches_count'] = WBIM_Branch::get_count( 1 );

        // Total stock
        $stats['total_stock'] = $wpdb->get_var( "SELECT COALESCE(SUM(quantity), 0) FROM {$stock_table}" );

        // Total stock value
        $stats['stock_value'] = $wpdb->get_var(
            "SELECT COALESCE(SUM(s.quantity * CAST(pm.meta_value AS DECIMAL(10,2))), 0)
            FROM {$stock_table} s
            LEFT JOIN {$wpdb->postmeta} pm ON s.product_id = pm.post_id AND pm.meta_key = '_price'
            WHERE pm.meta_value IS NOT NULL AND pm.meta_value != ''"
        );

        // Low stock count
        $settings = get_option( 'wbim_settings', array() );
        $threshold = isset( $settings['low_stock_threshold'] ) ? (int) $settings['low_stock_threshold'] : 5;
        $stats['low_stock_count'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$stock_table}
                WHERE quantity <= CASE WHEN low_stock_threshold > 0 THEN low_stock_threshold ELSE %d END
                AND quantity > 0",
                $threshold
            )
        );

        // Out of stock count
        $stats['out_of_stock_count'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$stock_table} WHERE quantity = 0"
        );

        // Pending transfers
        $stats['pending_transfers'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$transfers_table} WHERE status IN ('pending', 'in_transit')"
        );

        // Today's orders by branch
        $today = current_time( 'Y-m-d' );

        // Use HPOS-compatible query
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
             \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $stats['today_orders'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        b.id as branch_id,
                        b.name as branch_name,
                        COUNT(DISTINCT oa.order_id) as order_count,
                        SUM(oi.product_net_revenue) as revenue
                    FROM {$allocation_table} oa
                    INNER JOIN {$wpdb->prefix}wc_orders o ON oa.order_id = o.id
                    INNER JOIN {$wpdb->prefix}wc_order_product_lookup oi ON oa.order_item_id = oi.order_item_id
                    INNER JOIN {$branches_table} b ON oa.branch_id = b.id
                    WHERE DATE(o.date_created_gmt) = %s
                    GROUP BY oa.branch_id",
                    $today
                )
            );
        } else {
            $stats['today_orders'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        b.id as branch_id,
                        b.name as branch_name,
                        COUNT(DISTINCT oa.order_id) as order_count,
                        COALESCE(SUM(oim.meta_value), 0) as revenue
                    FROM {$allocation_table} oa
                    INNER JOIN {$wpdb->posts} p ON oa.order_id = p.ID
                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oa.order_item_id = oim.order_item_id AND oim.meta_key = '_line_total'
                    INNER JOIN {$branches_table} b ON oa.branch_id = b.id
                    WHERE DATE(p.post_date) = %s
                    GROUP BY oa.branch_id",
                    $today
                )
            );
        }

        // Recent low stock items
        $stats['low_stock_items'] = WBIM_Stock::get_low_stock_products( null, 5 );

        // Stock by branch for chart
        $stats['stock_by_branch'] = $wpdb->get_results(
            "SELECT
                b.id as branch_id,
                b.name as branch_name,
                COALESCE(SUM(s.quantity), 0) as total_stock,
                COUNT(DISTINCT s.product_id) as product_count
            FROM {$branches_table} b
            LEFT JOIN {$stock_table} s ON b.id = s.branch_id
            WHERE b.is_active = 1
            GROUP BY b.id
            ORDER BY b.sort_order ASC"
        );

        // Sales last 7 days for chart
        $sales_data = array();
        for ( $i = 6; $i >= 0; $i-- ) {
            $date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $sales_data[ $date ] = array(
                'date'    => $date,
                'orders'  => 0,
                'revenue' => 0,
            );
        }

        // Use HPOS-compatible query
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
             \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $week_sales = $wpdb->get_results(
                "SELECT
                    DATE(o.date_created_gmt) as sale_date,
                    COUNT(DISTINCT oa.order_id) as order_count,
                    SUM(oi.product_net_revenue) as revenue
                FROM {$allocation_table} oa
                INNER JOIN {$wpdb->prefix}wc_orders o ON oa.order_id = o.id
                INNER JOIN {$wpdb->prefix}wc_order_product_lookup oi ON oa.order_item_id = oi.order_item_id
                WHERE DATE(o.date_created_gmt) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND o.status IN ('wc-completed', 'wc-processing')
                GROUP BY sale_date
                ORDER BY sale_date ASC"
            );
        } else {
            $week_sales = $wpdb->get_results(
                "SELECT
                    DATE(p.post_date) as sale_date,
                    COUNT(DISTINCT oa.order_id) as order_count,
                    COALESCE(SUM(oim.meta_value), 0) as revenue
                FROM {$allocation_table} oa
                INNER JOIN {$wpdb->posts} p ON oa.order_id = p.ID
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oa.order_item_id = oim.order_item_id AND oim.meta_key = '_line_total'
                WHERE DATE(p.post_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND p.post_status IN ('wc-completed', 'wc-processing')
                GROUP BY sale_date
                ORDER BY sale_date ASC"
            );
        }

        foreach ( $week_sales as $sale ) {
            if ( isset( $sales_data[ $sale->sale_date ] ) ) {
                $sales_data[ $sale->sale_date ]['orders'] = (int) $sale->order_count;
                $sales_data[ $sale->sale_date ]['revenue'] = floatval( $sale->revenue );
            }
        }

        $stats['week_sales'] = array_values( $sales_data );

        return $stats;
    }
}
