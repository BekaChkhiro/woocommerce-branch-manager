<?php
/**
 * Admin Menus Class
 *
 * Handles registration and rendering of admin menu pages.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Menus class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Menus {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
    }

    /**
     * Register admin menus
     *
     * @return void
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __( 'Branch Inventory', 'wbim' ),
            __( 'ფილიალები', 'wbim' ),
            'manage_woocommerce',
            'wbim',
            array( $this, 'render_dashboard' ),
            'dashicons-store',
            56
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            'wbim',
            __( 'Dashboard', 'wbim' ),
            __( 'Dashboard', 'wbim' ),
            'manage_woocommerce',
            'wbim',
            array( $this, 'render_dashboard' )
        );

        // Branches submenu
        add_submenu_page(
            'wbim',
            __( 'Branches', 'wbim' ),
            __( 'ფილიალები', 'wbim' ),
            'manage_woocommerce',
            'wbim-branches',
            array( $this, 'render_branches' )
        );

        // Stock submenu
        add_submenu_page(
            'wbim',
            __( 'Stock', 'wbim' ),
            __( 'მარაგები', 'wbim' ),
            'manage_woocommerce',
            'wbim-stock',
            array( $this, 'render_stock' )
        );

        // Branch Prices submenu
        add_submenu_page(
            'wbim',
            __( 'Branch Prices', 'wbim' ),
            __( 'ფილიალის ფასები', 'wbim' ),
            'manage_woocommerce',
            'wbim-prices',
            array( $this, 'render_prices' )
        );

        // Transfers submenu
        add_submenu_page(
            'wbim',
            __( 'Transfers', 'wbim' ),
            __( 'გადატანები', 'wbim' ),
            'manage_woocommerce',
            'wbim-transfers',
            array( $this, 'render_transfers' )
        );

        // Reports submenu
        add_submenu_page(
            'wbim',
            __( 'Reports', 'wbim' ),
            __( 'რეპორტები', 'wbim' ),
            'manage_woocommerce',
            'wbim-reports',
            array( $this, 'render_reports' )
        );

        // History submenu
        add_submenu_page(
            'wbim',
            __( 'History', 'wbim' ),
            __( 'ისტორია', 'wbim' ),
            'manage_woocommerce',
            'wbim-history',
            array( $this, 'render_history' )
        );

        // Settings submenu
        add_submenu_page(
            'wbim',
            __( 'Settings', 'wbim' ),
            __( 'პარამეტრები', 'wbim' ),
            'manage_woocommerce',
            'wbim-settings',
            array( $this, 'render_settings' )
        );

        // Documentation submenu
        add_submenu_page(
            'wbim',
            __( 'Documentation', 'wbim' ),
            __( 'ინსტრუქცია', 'wbim' ),
            'manage_woocommerce',
            'wbim-documentation',
            array( $this, 'render_documentation' )
        );
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        include WBIM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render branches page
     *
     * @return void
     */
    public function render_branches() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Check for action
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'add':
            case 'edit':
                include WBIM_PLUGIN_DIR . 'admin/views/branches/edit.php';
                break;

            default:
                include WBIM_PLUGIN_DIR . 'admin/views/branches/list.php';
                break;
        }
    }

    /**
     * Render stock page
     *
     * @return void
     */
    public function render_stock() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Check for action
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'import':
                include WBIM_PLUGIN_DIR . 'admin/views/stock/import.php';
                break;

            default:
                include WBIM_PLUGIN_DIR . 'admin/views/stock/list.php';
                break;
        }
    }

    /**
     * Render branch prices page
     *
     * @return void
     */
    public function render_prices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Use the admin prices class
        if ( class_exists( 'WBIM_Admin_Prices' ) ) {
            $prices = new WBIM_Admin_Prices();
            $prices->render_page();
        } else {
            include WBIM_PLUGIN_DIR . 'admin/views/prices/list.php';
        }
    }

    /**
     * Render transfers page
     *
     * @return void
     */
    public function render_transfers() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Check for action
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'new':
                WBIM_Admin_Transfers::render_new();
                break;

            case 'edit':
            case 'view':
                WBIM_Admin_Transfers::render_edit();
                break;

            case 'pdf':
                // PDF generation handled separately
                if ( class_exists( 'WBIM_PDF_Generator' ) ) {
                    WBIM_PDF_Generator::generate_transfer_pdf( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
                }
                break;

            default:
                WBIM_Admin_Transfers::render_list();
                break;
        }
    }

    /**
     * Render reports page
     *
     * @return void
     */
    public function render_reports() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_view_reports' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Use the admin reports class if available
        if ( class_exists( 'WBIM_Admin_Reports' ) ) {
            $reports = new WBIM_Admin_Reports();
            $reports->render_reports_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Reports', 'wbim' ); ?></h1>
                <div class="wbim-placeholder-notice">
                    <p><?php esc_html_e( 'Reporting functionality will be available in a future update.', 'wbim' ); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render history page
     *
     * @return void
     */
    public function render_history() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        include WBIM_PLUGIN_DIR . 'admin/views/history/list.php';
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Use the admin settings class if available
        if ( class_exists( 'WBIM_Admin_Settings' ) ) {
            $settings = new WBIM_Admin_Settings();
            $settings->render_settings_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Settings', 'wbim' ); ?></h1>
                <div class="wbim-placeholder-notice">
                    <p><?php esc_html_e( 'Settings functionality will be available in a future update.', 'wbim' ); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render documentation page
     *
     * @return void
     */
    public function render_documentation() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Use the admin documentation class
        if ( class_exists( 'WBIM_Admin_Documentation' ) ) {
            $docs = new WBIM_Admin_Documentation();
            $docs->render_documentation_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Documentation', 'wbim' ); ?></h1>
                <div class="wbim-placeholder-notice">
                    <p><?php esc_html_e( 'Documentation is not available.', 'wbim' ); ?></p>
                </div>
            </div>
            <?php
        }
    }
}
