<?php
/**
 * Branch Price Model
 *
 * Handles branch-specific pricing and wholesale pricing.
 *
 * @package WBIM
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Branch Price model class
 *
 * @since 1.4.0
 */
class WBIM_Branch_Price {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_branch_prices';

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
     * Get a specific price record
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID (0 for simple products).
     * @param int $min_quantity Minimum quantity tier (default 1 for regular price).
     * @return object|null
     */
    public static function get( $product_id, $branch_id, $variation_id = 0, $min_quantity = 1 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d AND branch_id = %d AND variation_id = %d AND min_quantity = %d",
                $product_id,
                $branch_id,
                $variation_id,
                $min_quantity
            )
        );
    }

    /**
     * Get all prices for a product across all branches
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID (0 for simple products).
     * @return array
     */
    public static function get_product_prices( $product_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, b.name as branch_name, b.is_active as branch_active
                FROM {$table} p
                LEFT JOIN {$wpdb->prefix}wbim_branches b ON p.branch_id = b.id
                WHERE p.product_id = %d AND p.variation_id = %d
                ORDER BY b.sort_order ASC, p.min_quantity ASC",
                $product_id,
                $variation_id
            )
        );
    }

    /**
     * Get all prices for a branch
     *
     * @param int   $branch_id Branch ID.
     * @param array $args      Query arguments.
     * @return array
     */
    public static function get_branch_prices( $branch_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'limit'  => 50,
            'offset' => 0,
            'search' => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $where = array( 'p.branch_id = %d' );
        $values = array( $branch_id );

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(post.post_title LIKE %s OR pm.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode( ' AND ', $where );
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, post.post_title as product_name, pm.meta_value as sku
                FROM {$table} p
                LEFT JOIN {$wpdb->posts} post ON p.product_id = post.ID
                LEFT JOIN {$wpdb->postmeta} pm ON post.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE {$where_clause}
                ORDER BY post.post_title ASC, p.min_quantity ASC
                LIMIT %d OFFSET %d",
                $values
            )
        );
    }

    /**
     * Get all price tiers for a product at a specific branch
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID.
     * @return array
     */
    public static function get_price_tiers( $product_id, $branch_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE product_id = %d AND branch_id = %d AND variation_id = %d
                ORDER BY min_quantity ASC",
                $product_id,
                $branch_id,
                $variation_id
            )
        );
    }

    /**
     * Get applicable price for given quantity (wholesale logic)
     *
     * @param int  $product_id   Product ID.
     * @param int  $branch_id    Branch ID.
     * @param int  $variation_id Variation ID.
     * @param int  $quantity     Quantity being purchased.
     * @param bool $sale         Whether to return sale price if available.
     * @return float|null
     */
    public static function get_applicable_price( $product_id, $branch_id, $variation_id = 0, $quantity = 1, $sale = true ) {
        global $wpdb;

        $table = self::get_table();

        // Get the highest min_quantity tier that applies to this quantity
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $price_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE product_id = %d AND branch_id = %d AND variation_id = %d AND min_quantity <= %d
                ORDER BY min_quantity DESC
                LIMIT 1",
                $product_id,
                $branch_id,
                $variation_id,
                $quantity
            )
        );

        if ( ! $price_record ) {
            return null;
        }

        // Return sale price if available and requested, otherwise regular price
        if ( $sale && ! empty( $price_record->sale_price ) && $price_record->sale_price > 0 ) {
            return (float) $price_record->sale_price;
        }

        return ! empty( $price_record->regular_price ) ? (float) $price_record->regular_price : null;
    }

    /**
     * Get both regular and sale prices for given quantity
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity     Quantity being purchased.
     * @return array|null Array with 'regular_price' and 'sale_price' keys, or null if no price set.
     */
    public static function get_prices( $product_id, $branch_id, $variation_id = 0, $quantity = 1 ) {
        global $wpdb;

        $table = self::get_table();

        // Get the highest min_quantity tier that applies to this quantity
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $price_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT regular_price, sale_price, min_quantity FROM {$table}
                WHERE product_id = %d AND branch_id = %d AND variation_id = %d AND min_quantity <= %d
                ORDER BY min_quantity DESC
                LIMIT 1",
                $product_id,
                $branch_id,
                $variation_id,
                $quantity
            )
        );

        if ( ! $price_record ) {
            return null;
        }

        return array(
            'regular_price' => ! empty( $price_record->regular_price ) ? (float) $price_record->regular_price : null,
            'sale_price'    => ! empty( $price_record->sale_price ) ? (float) $price_record->sale_price : null,
            'min_quantity'  => (int) $price_record->min_quantity,
        );
    }

    /**
     * Set price for a product at a branch
     *
     * @param int   $product_id   Product ID.
     * @param int   $variation_id Variation ID.
     * @param int   $branch_id    Branch ID.
     * @param array $data         Price data (regular_price, sale_price, min_quantity).
     * @return bool|WP_Error
     */
    public static function set( $product_id, $variation_id, $branch_id, $data ) {
        global $wpdb;

        $table = self::get_table();

        $min_quantity = isset( $data['min_quantity'] ) ? absint( $data['min_quantity'] ) : 1;
        if ( $min_quantity < 1 ) {
            $min_quantity = 1;
        }

        // Get current record
        $current = self::get( $product_id, $branch_id, $variation_id, $min_quantity );

        // Prepare update data
        $update_data = array(
            'updated_at' => current_time( 'mysql' ),
        );
        $format = array( '%s' );

        if ( isset( $data['regular_price'] ) ) {
            $update_data['regular_price'] = $data['regular_price'] !== '' ? floatval( $data['regular_price'] ) : null;
            $format[] = $data['regular_price'] !== '' ? '%f' : null;
        }

        if ( isset( $data['sale_price'] ) ) {
            $update_data['sale_price'] = $data['sale_price'] !== '' ? floatval( $data['sale_price'] ) : null;
            $format[] = $data['sale_price'] !== '' ? '%f' : null;
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
        } else {
            // Insert new record
            $insert_data = array_merge(
                array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'branch_id'    => $branch_id,
                    'min_quantity' => $min_quantity,
                ),
                $update_data
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $table,
                $insert_data
            );
        }

        if ( false === $result ) {
            return new WP_Error(
                'db_error',
                __( 'ფასის შენახვა ვერ მოხერხდა.', 'wbim' )
            );
        }

        // Trigger action
        do_action( 'wbim_branch_price_updated', $product_id, $variation_id, $branch_id, $min_quantity );

        return true;
    }

    /**
     * Delete a price record
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID.
     * @param int $min_quantity Minimum quantity tier.
     * @return bool
     */
    public static function delete( $product_id, $branch_id, $variation_id = 0, $min_quantity = 1 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            array(
                'product_id'   => $product_id,
                'branch_id'    => $branch_id,
                'variation_id' => $variation_id,
                'min_quantity' => $min_quantity,
            ),
            array( '%d', '%d', '%d', '%d' )
        );

        if ( $result ) {
            do_action( 'wbim_branch_price_deleted', $product_id, $variation_id, $branch_id, $min_quantity );
        }

        return false !== $result;
    }

    /**
     * Delete all prices for a product
     *
     * @param int      $product_id   Product ID.
     * @param int|null $variation_id Variation ID (null to delete all variations).
     * @return bool
     */
    public static function delete_product_prices( $product_id, $variation_id = null ) {
        global $wpdb;

        $table = self::get_table();

        if ( null === $variation_id ) {
            // Delete all prices for this product including variations
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete(
                $table,
                array( 'product_id' => $product_id ),
                array( '%d' )
            );
        } else {
            // Delete specific variation prices
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

    /**
     * Delete all prices for a branch
     *
     * @param int $branch_id Branch ID.
     * @return bool
     */
    public static function delete_branch_prices( $branch_id ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table,
            array( 'branch_id' => $branch_id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Bulk update prices
     *
     * @param array $items Array of items with product_id, variation_id, branch_id, regular_price, sale_price, min_quantity.
     * @return array Results with success and error counts.
     */
    public static function bulk_update( $items ) {
        $results = array(
            'success'  => 0,
            'errors'   => 0,
            'messages' => array(),
        );

        foreach ( $items as $item ) {
            $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
            $branch_id = isset( $item['branch_id'] ) ? absint( $item['branch_id'] ) : 0;

            if ( ! $product_id || ! $branch_id ) {
                $results['errors']++;
                $results['messages'][] = __( 'არასწორი პროდუქტი ან ფილიალი.', 'wbim' );
                continue;
            }

            $result = self::set( $product_id, $variation_id, $branch_id, array(
                'regular_price' => isset( $item['regular_price'] ) ? $item['regular_price'] : null,
                'sale_price'    => isset( $item['sale_price'] ) ? $item['sale_price'] : null,
                'min_quantity'  => isset( $item['min_quantity'] ) ? $item['min_quantity'] : 1,
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
     * Get prices formatted for product edit page (by branch)
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return array Associative array with branch_id as key.
     */
    public static function get_prices_by_branch( $product_id, $variation_id = 0 ) {
        $prices = self::get_product_prices( $product_id, $variation_id );
        $result = array();

        foreach ( $prices as $price ) {
            if ( ! isset( $result[ $price->branch_id ] ) ) {
                $result[ $price->branch_id ] = array();
            }

            $result[ $price->branch_id ][ $price->min_quantity ] = array(
                'regular_price' => $price->regular_price,
                'sale_price'    => $price->sale_price,
            );
        }

        return $result;
    }

    /**
     * Check if product has branch-specific price
     *
     * @param int $product_id   Product ID.
     * @param int $branch_id    Branch ID.
     * @param int $variation_id Variation ID.
     * @return bool
     */
    public static function has_branch_price( $product_id, $branch_id, $variation_id = 0 ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE product_id = %d AND branch_id = %d AND variation_id = %d
                AND (regular_price IS NOT NULL OR sale_price IS NOT NULL)",
                $product_id,
                $branch_id,
                $variation_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Get all products with branch-specific prices
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'   => 0,
            'category_id' => 0,
            'search'      => '',
            'orderby'     => 'product_name',
            'order'       => 'ASC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        // Base query
        $where = array( "post.post_type IN ('product', 'product_variation')", "post.post_status = 'publish'" );
        $values = array();

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 'p.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(post.post_title LIKE %s OR pm_sku.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON post.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = $args['category_id'];
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'product_name', 'sku', 'regular_price' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'product_name';

        // Map orderby to actual column
        $orderby_map = array(
            'product_name'  => 'post.post_title',
            'sku'           => 'pm_sku.meta_value',
            'regular_price' => 'p.regular_price',
        );
        $orderby_sql = $orderby_map[ $orderby ];

        // Validate order
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT DISTINCT
                p.*,
                post.post_title as product_name,
                post.post_type,
                pm_sku.meta_value as sku,
                b.name as branch_name
            FROM {$table} p
            LEFT JOIN {$wpdb->posts} post ON p.product_id = post.ID
            LEFT JOIN {$wpdb->postmeta} pm_sku ON post.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}wbim_branches b ON p.branch_id = b.id
            {$join_terms}
            WHERE {$where_clause}
            ORDER BY {$orderby_sql} {$order}, p.min_quantity ASC
            LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get count of products with branch-specific prices
     *
     * @param array $args Query arguments.
     * @return int
     */
    public static function get_count( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'branch_id'   => 0,
            'category_id' => 0,
            'search'      => '',
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table();

        $where = array( "post.post_type = 'product'", "post.post_status = 'publish'" );
        $values = array();

        // Branch filter
        if ( ! empty( $args['branch_id'] ) ) {
            $where[] = 'p.branch_id = %d';
            $values[] = $args['branch_id'];
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(post.post_title LIKE %s OR pm.meta_value LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Category filter
        $join_terms = '';
        if ( ! empty( $args['category_id'] ) ) {
            $join_terms = "INNER JOIN {$wpdb->term_relationships} tr ON post.ID = tr.object_id";
            $where[] = 'tr.term_taxonomy_id = %d';
            $values[] = $args['category_id'];
        }

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT COUNT(DISTINCT p.product_id, p.branch_id)
            FROM {$table} p
            LEFT JOIN {$wpdb->posts} post ON p.product_id = post.ID
            LEFT JOIN {$wpdb->postmeta} pm ON post.ID = pm.post_id AND pm.meta_key = '_sku'
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
     * Format price for display
     *
     * @param float $price Price value.
     * @return string
     */
    public static function format_price( $price ) {
        if ( null === $price || '' === $price ) {
            return '-';
        }
        return wc_price( $price );
    }
}
