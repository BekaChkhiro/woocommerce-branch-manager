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
    const DB_VERSION = '1.3.0';

    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::maybe_upgrade_database();
        self::create_options();
        self::create_capabilities();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation time
        update_option( 'wbim_activated_at', current_time( 'mysql' ) );
    }

    /**
     * Check and upgrade database if needed
     *
     * @return void
     */
    public static function maybe_upgrade_database() {
        global $wpdb;

        // Always check for missing columns (fixes broken migrations)
        self::ensure_transfers_table_columns();
        self::ensure_stock_table_columns();

        $current_version = get_option( 'wbim_db_version', '1.0.0' );

        // If already at latest version, skip
        if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        // Upgrade to 1.1.0 - ensure transfers table has all columns
        if ( version_compare( $current_version, '1.1.0', '<' ) ) {
            self::upgrade_to_1_1_0();
        }

        // Upgrade to 1.2.0 - add stock_status column
        if ( version_compare( $current_version, '1.2.0', '<' ) ) {
            self::upgrade_to_1_2_0();
        }

        // Upgrade to 1.3.0 - add is_default column to branches
        if ( version_compare( $current_version, '1.3.0', '<' ) ) {
            self::upgrade_to_1_3_0();
        }

        update_option( 'wbim_db_version', self::DB_VERSION );
    }

    /**
     * Upgrade to version 1.3.0
     * Adds is_default column to branches table
     *
     * @return void
     */
    private static function upgrade_to_1_3_0() {
        global $wpdb;

        $table_branches = $wpdb->prefix . 'wbim_branches';

        // Check if is_default column exists
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table_branches} LIKE 'is_default'"
        );

        if ( empty( $column_exists ) ) {
            $wpdb->query(
                "ALTER TABLE {$table_branches} ADD COLUMN is_default TINYINT(1) DEFAULT 0 AFTER is_active"
            );
        }
    }

    /**
     * Ensure transfers table has all required columns
     *
     * @return void
     */
    private static function ensure_transfers_table_columns() {
        global $wpdb;

        $table_transfers = $wpdb->prefix . 'wbim_transfers';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_transfers}'" );
        if ( ! $table_exists ) {
            return;
        }

        // Get existing columns
        $existing_columns = array();
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_transfers}" );
        foreach ( $columns as $column ) {
            $existing_columns[] = $column->Field;
        }

        // Add missing columns
        if ( ! in_array( 'source_branch_id', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN source_branch_id bigint(20) UNSIGNED NOT NULL DEFAULT 0" );
        }

        if ( ! in_array( 'destination_branch_id', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN destination_branch_id bigint(20) UNSIGNED NOT NULL DEFAULT 0" );
        }

        if ( ! in_array( 'transfer_number', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN transfer_number varchar(50) NOT NULL DEFAULT ''" );
        }

        if ( ! in_array( 'status', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'draft'" );
        }

        if ( ! in_array( 'notes', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN notes text DEFAULT NULL" );
        }

        if ( ! in_array( 'created_by', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN created_by bigint(20) UNSIGNED DEFAULT NULL" );
        }

        if ( ! in_array( 'sent_by', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN sent_by bigint(20) UNSIGNED DEFAULT NULL" );
        }

        if ( ! in_array( 'received_by', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN received_by bigint(20) UNSIGNED DEFAULT NULL" );
        }

        if ( ! in_array( 'created_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP" );
        }

        if ( ! in_array( 'updated_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN updated_at datetime DEFAULT NULL" );
        }

        if ( ! in_array( 'sent_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN sent_at datetime DEFAULT NULL" );
        }

        if ( ! in_array( 'received_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN received_at datetime DEFAULT NULL" );
        }

        // Fix any transfers with empty or NULL status - set to draft
        $wpdb->query( "UPDATE {$table_transfers} SET status = 'draft' WHERE status IS NULL OR status = ''" );

        // Also fix transfer_items table
        self::ensure_transfer_items_table_columns();
    }

    /**
     * Ensure transfer_items table has all required columns
     *
     * @return void
     */
    private static function ensure_transfer_items_table_columns() {
        global $wpdb;

        $table = $wpdb->prefix . 'wbim_transfer_items';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( ! $table_exists ) {
            return;
        }

        // Get existing columns
        $existing_columns = array();
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table}" );
        foreach ( $columns as $column ) {
            $existing_columns[] = $column->Field;
        }

        // Add missing columns
        if ( ! in_array( 'product_name', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN product_name varchar(255) NOT NULL DEFAULT ''" );
        }

        if ( ! in_array( 'sku', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN sku varchar(100) DEFAULT ''" );
        }

        if ( ! in_array( 'quantity', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN quantity int(11) NOT NULL DEFAULT 0" );
        }

        if ( ! in_array( 'created_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP" );
        }

        if ( ! in_array( 'updated_at', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN updated_at datetime DEFAULT NULL" );
        }
    }

    /**
     * Upgrade database to version 1.1.0
     *
     * @return void
     */
    private static function upgrade_to_1_1_0() {
        global $wpdb;

        $table_transfers = $wpdb->prefix . 'wbim_transfers';

        // Check if destination_branch_id column exists
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_transfers} LIKE 'destination_branch_id'" );

        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN destination_branch_id bigint(20) UNSIGNED NOT NULL AFTER source_branch_id" );
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD KEY destination_branch_id (destination_branch_id)" );
        }

        // Check if source_branch_id column exists
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_transfers} LIKE 'source_branch_id'" );

        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN source_branch_id bigint(20) UNSIGNED NOT NULL AFTER transfer_number" );
            $wpdb->query( "ALTER TABLE {$table_transfers} ADD KEY source_branch_id (source_branch_id)" );
        }

        // Ensure all other columns exist
        $columns_to_check = array(
            'transfer_number' => "varchar(50) NOT NULL AFTER id",
            'status'          => "varchar(20) NOT NULL DEFAULT 'draft' AFTER destination_branch_id",
            'notes'           => "text DEFAULT NULL AFTER status",
            'created_by'      => "bigint(20) UNSIGNED DEFAULT NULL AFTER notes",
            'sent_by'         => "bigint(20) UNSIGNED DEFAULT NULL AFTER created_by",
            'received_by'     => "bigint(20) UNSIGNED DEFAULT NULL AFTER sent_by",
            'created_at'      => "datetime DEFAULT CURRENT_TIMESTAMP AFTER received_by",
            'updated_at'      => "datetime DEFAULT NULL AFTER created_at",
            'sent_at'         => "datetime DEFAULT NULL AFTER updated_at",
            'received_at'     => "datetime DEFAULT NULL AFTER sent_at",
        );

        foreach ( $columns_to_check as $column => $definition ) {
            $exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_transfers} LIKE '{$column}'" );
            if ( empty( $exists ) ) {
                $wpdb->query( "ALTER TABLE {$table_transfers} ADD COLUMN {$column} {$definition}" );
            }
        }
    }

    /**
     * Upgrade database to version 1.2.0
     * Adds stock_status column to stock table
     *
     * @return void
     */
    private static function upgrade_to_1_2_0() {
        global $wpdb;

        $table_stock = $wpdb->prefix . 'wbim_stock';

        // Check if stock_status column exists
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_stock} LIKE 'stock_status'" );

        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN stock_status VARCHAR(20) DEFAULT 'instock' AFTER quantity" );
        }
    }

    /**
     * Ensure stock table has all required columns
     *
     * @return void
     */
    private static function ensure_stock_table_columns() {
        global $wpdb;

        $table_stock = $wpdb->prefix . 'wbim_stock';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stock}'" );
        if ( ! $table_exists ) {
            return;
        }

        // Get existing columns
        $existing_columns = array();
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_stock}" );
        foreach ( $columns as $column ) {
            $existing_columns[] = $column->Field;
        }

        // Add stock_status column if missing
        if ( ! in_array( 'stock_status', $existing_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN stock_status VARCHAR(20) DEFAULT 'instock' AFTER quantity" );
        }
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
            stock_status VARCHAR(20) DEFAULT 'instock',
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
