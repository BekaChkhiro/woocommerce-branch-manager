<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation tasks including database table creation.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activator class
 *
 * @since 1.0.0
 */
class WBIM_Activator {

    /**
     * Database version
     *
     * @var string
     */
    const DB_VERSION = '1.1.0';

    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::create_options();
        self::create_capabilities();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation time
        update_option( 'wbim_activated_at', current_time( 'mysql' ) );
    }

    /**
     * Check plugin requirements
     *
     * @return void
     */
    private static function check_requirements() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            deactivate_plugins( plugin_basename( WBIM_PLUGIN_DIR . 'woo-branch-inventory-manager.php' ) );
            wp_die(
                esc_html__( 'WooCommerce Branch Inventory Manager requires WordPress 5.8 or higher.', 'wbim' ),
                esc_html__( 'Plugin Activation Error', 'wbim' ),
                array( 'back_link' => true )
            );
        }

        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( WBIM_PLUGIN_DIR . 'woo-branch-inventory-manager.php' ) );
            wp_die(
                esc_html__( 'WooCommerce Branch Inventory Manager requires PHP 7.4 or higher.', 'wbim' ),
                esc_html__( 'Plugin Activation Error', 'wbim' ),
                array( 'back_link' => true )
            );
        }

        // Check WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( WBIM_PLUGIN_DIR . 'woo-branch-inventory-manager.php' ) );
            wp_die(
                esc_html__( 'WooCommerce Branch Inventory Manager requires WooCommerce to be installed and activated.', 'wbim' ),
                esc_html__( 'Plugin Activation Error', 'wbim' ),
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Branches table
        $table_branches = $wpdb->prefix . 'wbim_branches';
        $sql_branches   = "CREATE TABLE {$table_branches} (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            address TEXT,
            city VARCHAR(100),
            phone VARCHAR(50),
            email VARCHAR(100),
            manager_id BIGINT UNSIGNED DEFAULT NULL,
            lat DECIMAL(10,8) DEFAULT NULL,
            lng DECIMAL(11,8) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY manager_id (manager_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) {$charset_collate};";

        dbDelta( $sql_branches );

        // Stock table
        $table_stock = $wpdb->prefix . 'wbim_stock';
        $sql_stock   = "CREATE TABLE {$table_stock} (
            id INT NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT 0,
            branch_id INT NOT NULL,
            quantity INT DEFAULT 0,
            low_stock_threshold INT DEFAULT 0,
            shelf_location VARCHAR(50),
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_branch (product_id, variation_id, branch_id),
            KEY branch_id (branch_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        dbDelta( $sql_stock );

        // Transfers table
        $table_transfers = $wpdb->prefix . 'wbim_transfers';
        $sql_transfers   = "CREATE TABLE {$table_transfers} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_number varchar(50) NOT NULL,
            source_branch_id bigint(20) UNSIGNED NOT NULL,
            destination_branch_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            notes text DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            sent_by bigint(20) UNSIGNED DEFAULT NULL,
            received_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            received_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transfer_number (transfer_number),
            KEY source_branch_id (source_branch_id),
            KEY destination_branch_id (destination_branch_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_transfers );

        // Transfer items table
        $table_transfer_items = $wpdb->prefix . 'wbim_transfer_items';
        $sql_transfer_items   = "CREATE TABLE {$table_transfer_items} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            variation_id bigint(20) UNSIGNED DEFAULT 0,
            product_name varchar(255) NOT NULL,
            sku varchar(100) DEFAULT '',
            quantity int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY transfer_id (transfer_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) {$charset_collate};";

        dbDelta( $sql_transfer_items );

        // Stock log table
        $table_stock_log = $wpdb->prefix . 'wbim_stock_log';
        $sql_stock_log   = "CREATE TABLE {$table_stock_log} (
            id INT NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT 0,
            branch_id INT NOT NULL,
            quantity_change INT NOT NULL,
            quantity_before INT DEFAULT NULL,
            quantity_after INT DEFAULT NULL,
            action_type ENUM('sale', 'restock', 'transfer_in', 'transfer_out', 'adjustment', 'return') NOT NULL,
            reference_id BIGINT UNSIGNED DEFAULT NULL,
            reference_type VARCHAR(50) DEFAULT NULL,
            note TEXT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_branch (product_id, branch_id),
            KEY created_at (created_at),
            KEY action_type (action_type)
        ) {$charset_collate};";

        dbDelta( $sql_stock_log );

        // Order allocation table
        $table_order_allocation = $wpdb->prefix . 'wbim_order_allocation';
        $sql_order_allocation   = "CREATE TABLE {$table_order_allocation} (
            id INT NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT 0,
            branch_id INT NOT NULL,
            quantity INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_item_id (order_item_id),
            KEY order_id (order_id),
            KEY branch_id (branch_id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        dbDelta( $sql_order_allocation );

        // Store database version
        update_option( 'wbim_db_version', self::DB_VERSION );
    }

    /**
     * Create default options
     *
     * @return void
     */
    private static function create_options() {
        $default_settings = array(
            'default_branch'             => 0,
            'auto_allocate'              => 'nearest',
            'low_stock_threshold'        => 5,
            'enable_email_notifications' => true,
            'notification_email'         => get_option( 'admin_email' ),
            'google_maps_api_key'        => '',
            'remove_data_on_uninstall'   => false,
            'date_format'                => 'd/m/Y',
            'stock_display_frontend'     => false,
        );

        add_option( 'wbim_settings', $default_settings );
    }

    /**
     * Create custom capabilities
     *
     * @return void
     */
    private static function create_capabilities() {
        // Use the User Roles class if available
        if ( class_exists( 'WBIM_User_Roles' ) ) {
            WBIM_User_Roles::create_roles();
            return;
        }

        // Fallback for basic capabilities
        $admin_role = get_role( 'administrator' );
        $shop_manager_role = get_role( 'shop_manager' );

        $capabilities = array(
            'wbim_manage_branches',
            'wbim_manage_stock',
            'wbim_manage_transfers',
            'wbim_view_reports',
            'wbim_manage_settings',
            'wbim_view_branch_stock',
        );

        foreach ( $capabilities as $cap ) {
            if ( $admin_role ) {
                $admin_role->add_cap( $cap );
            }
            if ( $shop_manager_role ) {
                $shop_manager_role->add_cap( $cap );
            }
        }
    }
}
