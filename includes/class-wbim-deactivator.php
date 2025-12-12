<?php
/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deactivator class
 *
 * @since 1.0.0
 */
class WBIM_Deactivator {

    /**
     * Deactivate the plugin
     *
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'wbim_daily_cleanup' );
        wp_clear_scheduled_hook( 'wbim_low_stock_check' );

        // Clear transients
        delete_transient( 'wbim_branches_count' );
        delete_transient( 'wbim_dashboard_stats' );

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store deactivation time
        update_option( 'wbim_deactivated_at', current_time( 'mysql' ) );
    }
}
