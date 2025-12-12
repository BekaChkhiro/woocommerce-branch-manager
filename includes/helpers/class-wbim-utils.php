<?php
/**
 * Utility Helper Class
 *
 * Contains various helper methods used throughout the plugin.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Utils helper class
 *
 * @since 1.0.0
 */
class WBIM_Utils {

    /**
     * Get plugin settings
     *
     * @param string $key     Optional specific setting key.
     * @param mixed  $default Default value if key not found.
     * @return mixed
     */
    public static function get_setting( $key = '', $default = null ) {
        $settings = get_option( 'wbim_settings', array() );

        if ( empty( $key ) ) {
            return $settings;
        }

        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update plugin settings
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public static function update_setting( $key, $value ) {
        $settings = get_option( 'wbim_settings', array() );
        $settings[ $key ] = $value;
        return update_option( 'wbim_settings', $settings );
    }

    /**
     * Check if user has required capability
     *
     * @param string $capability Capability to check.
     * @return bool
     */
    public static function user_can( $capability = 'manage_woocommerce' ) {
        return current_user_can( $capability );
    }

    /**
     * Get users who can manage branches
     *
     * @return array Array of WP_User objects.
     */
    public static function get_managers() {
        return get_users(
            array(
                'capability' => 'edit_posts',
                'orderby'    => 'display_name',
                'order'      => 'ASC',
            )
        );
    }

    /**
     * Format date for display
     *
     * @param string $date   Date string.
     * @param string $format Optional date format.
     * @return string
     */
    public static function format_date( $date, $format = '' ) {
        if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
            return '—';
        }

        if ( empty( $format ) ) {
            $format = self::get_setting( 'date_format', 'd/m/Y H:i' );
        }

        $timestamp = strtotime( $date );
        return wp_date( $format, $timestamp );
    }

    /**
     * Get admin page URL
     *
     * @param string $page Page slug.
     * @param array  $args Optional query arguments.
     * @return string
     */
    public static function get_admin_url( $page = '', $args = array() ) {
        $base_url = admin_url( 'admin.php' );

        $args['page'] = 'wbim' . ( $page ? '-' . $page : '' );

        return add_query_arg( $args, $base_url );
    }

    /**
     * Display admin notice
     *
     * @param string $message Notice message.
     * @param string $type    Notice type (success, error, warning, info).
     * @param bool   $dismiss Whether notice is dismissible.
     * @return void
     */
    public static function admin_notice( $message, $type = 'success', $dismiss = true ) {
        $class = 'notice notice-' . esc_attr( $type );
        if ( $dismiss ) {
            $class .= ' is-dismissible';
        }

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr( $class ),
            wp_kses_post( $message )
        );
    }

    /**
     * Verify nonce and capability
     *
     * @param string $action     Nonce action.
     * @param string $nonce_name Nonce name in request.
     * @param string $capability Required capability.
     * @return bool|WP_Error True if valid, WP_Error if not.
     */
    public static function verify_request( $action, $nonce_name = '_wpnonce', $capability = 'manage_woocommerce' ) {
        // Check nonce
        if ( ! isset( $_REQUEST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST[ $nonce_name ] ), $action ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Security check failed. Please refresh the page and try again.', 'wbim' )
            );
        }

        // Check capability
        if ( ! current_user_can( $capability ) ) {
            return new WP_Error(
                'insufficient_permissions',
                __( 'You do not have permission to perform this action.', 'wbim' )
            );
        }

        return true;
    }

    /**
     * Sanitize array recursively
     *
     * @param array $array Array to sanitize.
     * @return array
     */
    public static function sanitize_array( $array ) {
        foreach ( $array as $key => &$value ) {
            if ( is_array( $value ) ) {
                $value = self::sanitize_array( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
        }
        return $array;
    }

    /**
     * Get product name with variation info
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return string
     */
    public static function get_product_name( $product_id, $variation_id = 0 ) {
        if ( $variation_id > 0 ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                return $variation->get_formatted_name();
            }
        }

        $product = wc_get_product( $product_id );
        if ( $product ) {
            return $product->get_name();
        }

        return sprintf( '#%d', $product_id );
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     *
     * @param float $lat1 Latitude 1.
     * @param float $lng1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lng2 Longitude 2.
     * @param string $unit Unit (km or miles).
     * @return float Distance.
     */
    public static function calculate_distance( $lat1, $lng1, $lat2, $lng2, $unit = 'km' ) {
        $earth_radius = 'km' === $unit ? 6371 : 3959;

        $lat1 = deg2rad( $lat1 );
        $lat2 = deg2rad( $lat2 );
        $lng1 = deg2rad( $lng1 );
        $lng2 = deg2rad( $lng2 );

        $delta_lat = $lat2 - $lat1;
        $delta_lng = $lng2 - $lng1;

        $a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
             cos( $lat1 ) * cos( $lat2 ) *
             sin( $delta_lng / 2 ) * sin( $delta_lng / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Find nearest branch to coordinates
     *
     * @param float $lat        Latitude.
     * @param float $lng        Longitude.
     * @param bool  $active_only Only consider active branches.
     * @return object|null Branch object or null.
     */
    public static function find_nearest_branch( $lat, $lng, $active_only = true ) {
        $branches = WBIM_Branch::get_all(
            array(
                'is_active' => $active_only ? 1 : null,
            )
        );

        if ( empty( $branches ) ) {
            return null;
        }

        $nearest = null;
        $min_distance = PHP_FLOAT_MAX;

        foreach ( $branches as $branch ) {
            if ( empty( $branch->lat ) || empty( $branch->lng ) ) {
                continue;
            }

            $distance = self::calculate_distance( $lat, $lng, $branch->lat, $branch->lng );

            if ( $distance < $min_distance ) {
                $min_distance = $distance;
                $nearest = $branch;
                $nearest->distance = $distance;
            }
        }

        return $nearest;
    }

    /**
     * Export data to CSV
     *
     * @param array  $data     Data to export.
     * @param array  $headers  Column headers.
     * @param string $filename Filename for download.
     * @return void
     */
    public static function export_csv( $data, $headers, $filename ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );

        // Add BOM for UTF-8
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Headers
        fputcsv( $output, $headers );

        // Data
        foreach ( $data as $row ) {
            fputcsv( $output, (array) $row );
        }

        fclose( $output );
        exit;
    }

    /**
     * Log message for debugging
     *
     * @param mixed  $message Message to log.
     * @param string $level   Log level.
     * @return void
     */
    public static function log( $message, $level = 'debug' ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }

        $log_message = sprintf(
            '[WBIM %s] %s',
            strtoupper( $level ),
            $message
        );

        error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    }

    /**
     * Get status badge HTML
     *
     * @param bool   $is_active Active status.
     * @param string $context   Display context.
     * @return string HTML string.
     */
    public static function get_status_badge( $is_active, $context = 'branch' ) {
        if ( $is_active ) {
            $class = 'wbim-status-badge wbim-status-active';
            $text = __( 'Active', 'wbim' );
        } else {
            $class = 'wbim-status-badge wbim-status-inactive';
            $text = __( 'Inactive', 'wbim' );
        }

        return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( $text ) );
    }

    /**
     * Get transfer status badge HTML
     *
     * @param string $status Transfer status.
     * @return string HTML string.
     */
    public static function get_transfer_status_badge( $status ) {
        $statuses = array(
            'pending'    => array(
                'class' => 'wbim-status-pending',
                'label' => __( 'Pending', 'wbim' ),
            ),
            'in_transit' => array(
                'class' => 'wbim-status-transit',
                'label' => __( 'In Transit', 'wbim' ),
            ),
            'completed'  => array(
                'class' => 'wbim-status-completed',
                'label' => __( 'Completed', 'wbim' ),
            ),
            'cancelled'  => array(
                'class' => 'wbim-status-cancelled',
                'label' => __( 'Cancelled', 'wbim' ),
            ),
        );

        $status_info = isset( $statuses[ $status ] ) ? $statuses[ $status ] : array(
            'class' => 'wbim-status-default',
            'label' => $status,
        );

        return sprintf(
            '<span class="wbim-status-badge %s">%s</span>',
            esc_attr( $status_info['class'] ),
            esc_html( $status_info['label'] )
        );
    }
}
