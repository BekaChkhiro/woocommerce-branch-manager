<?php
/**
 * Admin Dashboard Class
 *
 * Handles the main dashboard page in the admin area.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Dashboard class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Dashboard {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_wbim_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
    }

    /**
     * Enqueue scripts for dashboard
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wbim-branches' ) === false ) {
            return;
        }

        // Only on main page (not subpages)
        if ( isset( $_GET['action'] ) ) {
            return;
        }

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Dashboard charts script
        wp_enqueue_script(
            'wbim-charts',
            WBIM_PLUGIN_URL . 'admin/js/wbim-charts.js',
            array( 'jquery', 'chartjs' ),
            WBIM_VERSION,
            true
        );
    }

    /**
     * AJAX handler for dashboard data
     *
     * @return void
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'wbim_view_branch_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $stats = WBIM_Reports::get_dashboard_stats();
        wp_send_json_success( $stats );
    }

    /**
     * Get dashboard stats
     *
     * @return array
     */
    public static function get_stats() {
        return WBIM_Reports::get_dashboard_stats();
    }
}
