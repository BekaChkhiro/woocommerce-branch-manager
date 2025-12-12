<?php
/**
 * Branch Model
 *
 * Handles all branch-related database operations.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Branch model class
 *
 * @since 1.0.0
 */
class WBIM_Branch {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'wbim_branches';

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
     * Get all branches
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'is_active' => null,
            'orderby'   => 'sort_order',
            'order'     => 'ASC',
            'limit'     => 0,
            'offset'    => 0,
            'search'    => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $table = self::get_table();
        $where = array( '1=1' );
        $values = array();

        // Filter by active status
        if ( null !== $args['is_active'] ) {
            $where[] = 'is_active = %d';
            $values[] = (int) $args['is_active'];
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(name LIKE %s OR city LIKE %s OR address LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Build WHERE clause
        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'name', 'city', 'sort_order', 'created_at', 'updated_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';

        // Validate order
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        // Build query
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

        // Add limit
        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        }

        // Prepare and execute
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare( $sql, $values );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql );
    }

    /**
     * Get active branches
     *
     * @return array
     */
    public static function get_active() {
        return self::get_all( array( 'is_active' => 1 ) );
    }

    /**
     * Get branch by ID
     *
     * @param int $id Branch ID.
     * @return object|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Create a new branch
     *
     * @param array $data Branch data.
     * @return int|WP_Error Branch ID on success, WP_Error on failure.
     */
    public static function create( $data ) {
        global $wpdb;

        // Validate data
        $validation = self::validate( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $table = self::get_table();

        // Prepare data for insert
        $insert_data = self::prepare_data( $data );
        $insert_data['created_at'] = current_time( 'mysql' );
        $insert_data['updated_at'] = current_time( 'mysql' );

        // Get format array
        $format = self::get_format( $insert_data );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table, $insert_data, $format );

        if ( false === $result ) {
            return new WP_Error(
                'db_insert_error',
                __( 'Could not create branch. Database error.', 'wbim' ),
                array( 'status' => 500 )
            );
        }

        // Clear cache
        delete_transient( 'wbim_branches_count' );

        return $wpdb->insert_id;
    }

    /**
     * Update a branch
     *
     * @param int   $id   Branch ID.
     * @param array $data Branch data.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update( $id, $data ) {
        global $wpdb;

        // Check if branch exists
        $existing = self::get_by_id( $id );
        if ( ! $existing ) {
            return new WP_Error(
                'branch_not_found',
                __( 'Branch not found.', 'wbim' ),
                array( 'status' => 404 )
            );
        }

        // Validate data
        $validation = self::validate( $data, $id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $table = self::get_table();

        // Prepare data for update
        $update_data = self::prepare_data( $data );
        $update_data['updated_at'] = current_time( 'mysql' );

        // Get format array
        $format = self::get_format( $update_data );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_update_error',
                __( 'Could not update branch. Database error.', 'wbim' ),
                array( 'status' => 500 )
            );
        }

        return true;
    }

    /**
     * Delete a branch
     *
     * @param int  $id    Branch ID.
     * @param bool $force Force delete (true) or soft delete (false).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function delete( $id, $force = false ) {
        global $wpdb;

        // Check if branch exists
        $existing = self::get_by_id( $id );
        if ( ! $existing ) {
            return new WP_Error(
                'branch_not_found',
                __( 'Branch not found.', 'wbim' ),
                array( 'status' => 404 )
            );
        }

        $table = self::get_table();

        if ( $force ) {
            // Check for stock records
            $stock_table = $wpdb->prefix . 'wbim_stock';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stock_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$stock_table} WHERE branch_id = %d",
                    $id
                )
            );

            if ( $stock_count > 0 ) {
                return new WP_Error(
                    'branch_has_stock',
                    __( 'Cannot delete branch with stock records. Remove stock first or use soft delete.', 'wbim' ),
                    array( 'status' => 400 )
                );
            }

            // Hard delete
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete(
                $table,
                array( 'id' => $id ),
                array( '%d' )
            );
        } else {
            // Soft delete (deactivate)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $table,
                array(
                    'is_active'  => 0,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        }

        if ( false === $result ) {
            return new WP_Error(
                'db_delete_error',
                __( 'Could not delete branch. Database error.', 'wbim' ),
                array( 'status' => 500 )
            );
        }

        // Clear cache
        delete_transient( 'wbim_branches_count' );

        return true;
    }

    /**
     * Get branch count
     *
     * @param int|null $is_active Filter by active status (null for all).
     * @return int
     */
    public static function get_count( $is_active = null ) {
        global $wpdb;

        $cache_key = 'wbim_branches_count_' . ( $is_active ?? 'all' );
        $count = wp_cache_get( $cache_key, 'wbim' );

        if ( false !== $count ) {
            return (int) $count;
        }

        $table = self::get_table();

        if ( null !== $is_active ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE is_active = %d",
                    $is_active
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        wp_cache_set( $cache_key, $count, 'wbim', HOUR_IN_SECONDS );

        return (int) $count;
    }

    /**
     * Reorder branches
     *
     * @param array $ordered_ids Array of branch IDs in new order.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function reorder( $ordered_ids ) {
        global $wpdb;

        if ( empty( $ordered_ids ) || ! is_array( $ordered_ids ) ) {
            return new WP_Error(
                'invalid_data',
                __( 'Invalid order data provided.', 'wbim' ),
                array( 'status' => 400 )
            );
        }

        $table = self::get_table();

        foreach ( $ordered_ids as $order => $id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table,
                array( 'sort_order' => (int) $order ),
                array( 'id' => (int) $id ),
                array( '%d' ),
                array( '%d' )
            );
        }

        return true;
    }

    /**
     * Toggle branch active status
     *
     * @param int $id Branch ID.
     * @return bool|WP_Error New status on success, WP_Error on failure.
     */
    public static function toggle_status( $id ) {
        global $wpdb;

        $branch = self::get_by_id( $id );
        if ( ! $branch ) {
            return new WP_Error(
                'branch_not_found',
                __( 'Branch not found.', 'wbim' ),
                array( 'status' => 404 )
            );
        }

        $new_status = $branch->is_active ? 0 : 1;

        $table = self::get_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            array(
                'is_active'  => $new_status,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'db_update_error',
                __( 'Could not update branch status.', 'wbim' ),
                array( 'status' => 500 )
            );
        }

        // Clear cache
        delete_transient( 'wbim_branches_count' );

        return $new_status;
    }

    /**
     * Validate branch data
     *
     * @param array    $data Branch data.
     * @param int|null $id   Branch ID for update validation.
     * @return bool|WP_Error True if valid, WP_Error if not.
     */
    private static function validate( $data, $id = null ) {
        $errors = new WP_Error();

        // Name is required
        if ( empty( $data['name'] ) ) {
            $errors->add(
                'name_required',
                __( 'Branch name is required.', 'wbim' )
            );
        } elseif ( strlen( $data['name'] ) > 255 ) {
            $errors->add(
                'name_too_long',
                __( 'Branch name must be 255 characters or less.', 'wbim' )
            );
        }

        // Email validation
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors->add(
                'invalid_email',
                __( 'Please enter a valid email address.', 'wbim' )
            );
        }

        // Latitude validation
        if ( ! empty( $data['lat'] ) ) {
            $lat = floatval( $data['lat'] );
            if ( $lat < -90 || $lat > 90 ) {
                $errors->add(
                    'invalid_lat',
                    __( 'Latitude must be between -90 and 90.', 'wbim' )
                );
            }
        }

        // Longitude validation
        if ( ! empty( $data['lng'] ) ) {
            $lng = floatval( $data['lng'] );
            if ( $lng < -180 || $lng > 180 ) {
                $errors->add(
                    'invalid_lng',
                    __( 'Longitude must be between -180 and 180.', 'wbim' )
                );
            }
        }

        // Manager validation
        if ( ! empty( $data['manager_id'] ) ) {
            $user = get_user_by( 'id', $data['manager_id'] );
            if ( ! $user ) {
                $errors->add(
                    'invalid_manager',
                    __( 'Selected manager does not exist.', 'wbim' )
                );
            }
        }

        if ( $errors->has_errors() ) {
            return $errors;
        }

        return true;
    }

    /**
     * Prepare data for database operation
     *
     * @param array $data Raw data.
     * @return array Prepared data.
     */
    private static function prepare_data( $data ) {
        $prepared = array();

        $fields = array(
            'name'       => 'sanitize_text_field',
            'address'    => 'sanitize_textarea_field',
            'city'       => 'sanitize_text_field',
            'phone'      => 'sanitize_text_field',
            'email'      => 'sanitize_email',
            'manager_id' => 'absint',
            'lat'        => 'floatval',
            'lng'        => 'floatval',
            'is_active'  => 'absint',
            'sort_order' => 'absint',
        );

        foreach ( $fields as $field => $sanitize_callback ) {
            if ( isset( $data[ $field ] ) ) {
                if ( in_array( $field, array( 'manager_id', 'lat', 'lng' ), true ) && empty( $data[ $field ] ) ) {
                    $prepared[ $field ] = null;
                } else {
                    $prepared[ $field ] = call_user_func( $sanitize_callback, $data[ $field ] );
                }
            }
        }

        return $prepared;
    }

    /**
     * Get format array for database operations
     *
     * @param array $data Prepared data.
     * @return array Format array.
     */
    private static function get_format( $data ) {
        $format = array();

        $type_map = array(
            'name'       => '%s',
            'address'    => '%s',
            'city'       => '%s',
            'phone'      => '%s',
            'email'      => '%s',
            'manager_id' => '%d',
            'lat'        => '%f',
            'lng'        => '%f',
            'is_active'  => '%d',
            'sort_order' => '%d',
            'created_at' => '%s',
            'updated_at' => '%s',
        );

        foreach ( array_keys( $data ) as $key ) {
            $format[] = isset( $type_map[ $key ] ) ? $type_map[ $key ] : '%s';
        }

        return $format;
    }

    /**
     * Get manager name by branch
     *
     * @param object $branch Branch object.
     * @return string Manager display name or dash if none.
     */
    public static function get_manager_name( $branch ) {
        if ( empty( $branch->manager_id ) ) {
            return '—';
        }

        $user = get_user_by( 'id', $branch->manager_id );
        if ( ! $user ) {
            return '—';
        }

        return $user->display_name;
    }
}
