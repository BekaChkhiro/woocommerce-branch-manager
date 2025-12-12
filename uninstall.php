<?php
/**
 * Uninstall script for WooCommerce Branch Inventory Manager
 *
 * This file runs when the plugin is deleted via the WordPress admin.
 * It cleans up all plugin data from the database.
 *
 * @package WBIM
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Check if user wants to remove all data (can be set in settings)
$settings = get_option( 'wbim_settings', array() );
$remove_data = isset( $settings['remove_data_on_uninstall'] ) ? $settings['remove_data_on_uninstall'] : false;

if ( $remove_data ) {
    // Drop custom tables
    $tables = array(
        $wpdb->prefix . 'wbim_branches',
        $wpdb->prefix . 'wbim_stock',
        $wpdb->prefix . 'wbim_transfers',
        $wpdb->prefix . 'wbim_transfer_items',
        $wpdb->prefix . 'wbim_stock_log',
        $wpdb->prefix . 'wbim_order_allocation',
    );

    foreach ( $tables as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
    }

    // Delete options
    delete_option( 'wbim_settings' );
    delete_option( 'wbim_db_version' );

    // Delete any transients
    delete_transient( 'wbim_branches_count' );

    // Clear any scheduled hooks
    wp_clear_scheduled_hook( 'wbim_daily_cleanup' );
}
