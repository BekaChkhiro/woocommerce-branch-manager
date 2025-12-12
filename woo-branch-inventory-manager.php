<?php
/**
 * Plugin Name: WooCommerce Branch Inventory Manager
 * Plugin URI: https://example.com/wbim
 * Description: Manage inventory across multiple branches/locations for WooCommerce stores.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wbim
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WBIM
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WBIM_VERSION', '1.0.0' );
define( 'WBIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBIM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
final class WBIM {

    /**
     * Single instance of the class
     *
     * @var WBIM
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return WBIM
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     *
     * @return void
     */
    private function includes() {
        // Autoloader
        require_once WBIM_PLUGIN_DIR . 'includes/class-wbim-autoloader.php';

        // Initialize autoloader
        WBIM_Autoloader::init();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Check WooCommerce dependency
        add_action( 'plugins_loaded', array( $this, 'check_woocommerce' ) );

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Initialize admin
        if ( is_admin() ) {
            add_action( 'plugins_loaded', array( $this, 'init_admin' ), 20 );
        }

        // Initialize frontend/public
        add_action( 'plugins_loaded', array( $this, 'init_public' ), 20 );

        // Register activation/deactivation hooks
        register_activation_hook( __FILE__, array( 'WBIM_Activator', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'WBIM_Deactivator', 'deactivate' ) );

        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Check if WooCommerce is active
     *
     * @return void
     */
    public function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Check WooCommerce version
        if ( version_compare( WC_VERSION, '5.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_version_notice' ) );
            return;
        }
    }

    /**
     * Display WooCommerce missing notice
     *
     * @return void
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin name */
                    esc_html__( '%s requires WooCommerce to be installed and activated.', 'wbim' ),
                    '<strong>WooCommerce Branch Inventory Manager</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display WooCommerce version notice
     *
     * @return void
     */
    public function woocommerce_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: Required WooCommerce version */
                    esc_html__( '%s requires WooCommerce version %s or higher.', 'wbim' ),
                    '<strong>WooCommerce Branch Inventory Manager</strong>',
                    '5.0'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wbim',
            false,
            dirname( WBIM_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize admin functionality
     *
     * @return void
     */
    public function init_admin() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        new WBIM_Admin();
        new WBIM_Admin_Orders();
        new WBIM_Admin_Transfers();
        new WBIM_Admin_Reports();
        new WBIM_Admin_Dashboard();
        new WBIM_Admin_Settings();
    }

    /**
     * Initialize public/frontend functionality
     *
     * @return void
     */
    public function init_public() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Order handler (works on both admin and frontend)
        new WBIM_Order_Handler();

        // User roles system
        new WBIM_User_Roles();

        // Notifications system
        new WBIM_Notifications();

        // REST API
        new WBIM_REST_API();

        // Public/Frontend (also needed for AJAX which runs in admin context)
        new WBIM_Public();
        new WBIM_Checkout();
    }

    /**
     * Declare HPOS (High-Performance Order Storage) compatibility
     *
     * @return void
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }
}

/**
 * Initialize the plugin
 *
 * @return WBIM
 */
function wbim() {
    return WBIM::instance();
}

// Start the plugin
wbim();
