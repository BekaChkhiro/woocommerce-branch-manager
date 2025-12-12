<?php
/**
 * Admin Main Class
 *
 * Handles all admin functionality initialization.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 *
 * @since 1.0.0
 */
class WBIM_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required admin files
     *
     * @return void
     */
    private function includes() {
        require_once WBIM_PLUGIN_DIR . 'admin/class-wbim-admin-menus.php';
        require_once WBIM_PLUGIN_DIR . 'admin/class-wbim-admin-stock.php';
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Initialize admin menus
        new WBIM_Admin_Menus();

        // Initialize admin stock
        new WBIM_Admin_Stock();

        // Enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Admin form handlers
        add_action( 'admin_post_wbim_save_branch', array( $this, 'handle_save_branch' ) );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_reorder_branches', array( $this, 'ajax_reorder_branches' ) );
        add_action( 'wp_ajax_wbim_toggle_branch_status', array( $this, 'ajax_toggle_branch_status' ) );
        add_action( 'wp_ajax_wbim_delete_branch', array( $this, 'ajax_delete_branch' ) );

        // Admin notices
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
    }

    /**
     * Enqueue admin styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_styles( $hook ) {
        // Load on our plugin pages and WooCommerce product pages
        $is_plugin_page = strpos( $hook, 'wbim' ) !== false;
        $is_product_page = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $this->is_product_screen();

        if ( ! $is_plugin_page && ! $is_product_page ) {
            return;
        }

        wp_enqueue_style(
            'wbim-admin',
            WBIM_PLUGIN_URL . 'admin/css/wbim-admin.css',
            array(),
            WBIM_VERSION
        );
    }

    /**
     * Check if current screen is a WooCommerce product
     *
     * @return bool
     */
    private function is_product_screen() {
        $screen = get_current_screen();
        return $screen && 'product' === $screen->post_type;
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Load on our plugin pages and WooCommerce product pages
        $is_plugin_page = strpos( $hook, 'wbim' ) !== false;
        $is_product_page = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $this->is_product_screen();

        if ( ! $is_plugin_page && ! $is_product_page ) {
            return;
        }

        // jQuery UI Sortable for branch ordering
        wp_enqueue_script( 'jquery-ui-sortable' );

        // Google Maps API if key is set
        $api_key = WBIM_Utils::get_setting( 'google_maps_api_key', '' );
        if ( ! empty( $api_key ) && strpos( $hook, 'branches' ) !== false ) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places',
                array(),
                null, // No version for external scripts
                true
            );
        }

        // Main admin script
        wp_enqueue_script(
            'wbim-admin',
            WBIM_PLUGIN_URL . 'admin/js/wbim-admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            WBIM_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'wbim-admin',
            'wbimAdmin',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'wbim_admin_nonce' ),
                'hasGoogleMaps' => ! empty( $api_key ),
                'strings'       => array(
                    'confirmDelete'     => __( 'Are you sure you want to delete this branch?', 'wbim' ),
                    'confirmDeactivate' => __( 'Are you sure you want to deactivate this branch?', 'wbim' ),
                    'saving'            => __( 'Saving...', 'wbim' ),
                    'saved'             => __( 'Saved!', 'wbim' ),
                    'error'             => __( 'An error occurred. Please try again.', 'wbim' ),
                    'selectLocation'    => __( 'Click on the map to set location', 'wbim' ),
                ),
                'defaultLat'    => 41.7151,
                'defaultLng'    => 44.8271,
            )
        );

        // Stock management script (on stock and history pages)
        if ( strpos( $hook, 'wbim-stock' ) !== false || strpos( $hook, 'wbim-history' ) !== false ) {
            wp_enqueue_script(
                'wbim-stock',
                WBIM_PLUGIN_URL . 'admin/js/wbim-stock.js',
                array( 'jquery', 'wbim-admin' ),
                WBIM_VERSION,
                true
            );

            wp_localize_script(
                'wbim-stock',
                'wbimStock',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'wbim_admin' ),
                    'strings' => array(
                        'saving'        => __( 'ინახება...', 'wbim' ),
                        'saved'         => __( 'ცვლილებები შენახულია!', 'wbim' ),
                        'error'         => __( 'დაფიქსირდა შეცდომა. სცადეთ ხელახლა.', 'wbim' ),
                        'invalidFile'   => __( 'გთხოვთ აირჩიოთ ვალიდური CSV ფაილი.', 'wbim' ),
                        'imported'      => __( 'იმპორტირებული', 'wbim' ),
                        'skipped'       => __( 'გამოტოვებული', 'wbim' ),
                        'total'         => __( 'სულ', 'wbim' ),
                        'errors'        => __( 'შეცდომები', 'wbim' ),
                        'moreErrors'    => __( 'მეტი შეცდომა', 'wbim' ),
                        'confirmSync'   => __( 'სინქრონიზაცია განაახლებს WooCommerce-ის მარაგს ფილიალების მარაგის ჯამით. გავაგრძელოთ?', 'wbim' ),
                        'syncing'       => __( 'სინქრონიზაცია...', 'wbim' ),
                        'preview'       => __( 'გადახედვა', 'wbim' ),
                        'startImport'   => __( 'იმპორტის დაწყება', 'wbim' ),
                        'loadingPreview' => __( 'იტვირთება...', 'wbim' ),
                        'previewTitle'  => __( 'იმპორტის გადახედვა', 'wbim' ),
                        'totalRows'     => __( 'სულ სტრიქონები', 'wbim' ),
                        'validRows'     => __( 'ვალიდური', 'wbim' ),
                        'branch'        => __( 'ფილიალი', 'wbim' ),
                        'quantity'      => __( 'რაოდენობა', 'wbim' ),
                        'status'        => __( 'სტატუსი', 'wbim' ),
                        'valid'         => __( 'ვალიდური', 'wbim' ),
                        'moreRows'      => __( 'და კიდევ', 'wbim' ),
                        'rows'          => __( 'სტრიქონი', 'wbim' ),
                        'confirmImport' => __( 'იმპორტის დადასტურება', 'wbim' ),
                        'cancel'        => __( 'გაუქმება', 'wbim' ),
                        'back'          => __( 'უკან', 'wbim' ),
                    ),
                )
            );
        }
    }

    /**
     * Handle branch save form submission
     *
     * @return void
     */
    public function handle_save_branch() {
        // Verify request
        $verify = WBIM_Utils::verify_request( 'wbim_save_branch', 'wbim_branch_nonce' );
        if ( is_wp_error( $verify ) ) {
            wp_die( esc_html( $verify->get_error_message() ) );
        }

        // Get branch ID (0 for new)
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        // Prepare data
        $data = array(
            'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'address'    => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
            'city'       => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
            'phone'      => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
            'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
            'manager_id' => isset( $_POST['manager_id'] ) ? absint( $_POST['manager_id'] ) : 0,
            'lat'        => isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : null,
            'lng'        => isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : null,
            'is_active'  => isset( $_POST['is_active'] ) ? 1 : 0,
            'sort_order' => isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0,
        );

        // Create or update
        if ( $branch_id > 0 ) {
            $result = WBIM_Branch::update( $branch_id, $data );
            $message = 'updated';
        } else {
            $result = WBIM_Branch::create( $data );
            $message = 'created';
        }

        // Handle result
        if ( is_wp_error( $result ) ) {
            // Store error in transient for display
            set_transient( 'wbim_admin_notice', array(
                'type'    => 'error',
                'message' => $result->get_error_message(),
            ), 30 );

            // Redirect back to form
            $redirect_url = add_query_arg(
                array(
                    'page'   => 'wbim-branches',
                    'action' => $branch_id > 0 ? 'edit' : 'add',
                    'id'     => $branch_id,
                ),
                admin_url( 'admin.php' )
            );
        } else {
            // Store success message
            set_transient( 'wbim_admin_notice', array(
                'type'    => 'success',
                'message' => 'created' === $message
                    ? __( 'Branch created successfully.', 'wbim' )
                    : __( 'Branch updated successfully.', 'wbim' ),
            ), 30 );

            // Redirect to list
            $redirect_url = add_query_arg(
                array(
                    'page' => 'wbim-branches',
                ),
                admin_url( 'admin.php' )
            );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * AJAX handler for reordering branches
     *
     * @return void
     */
    public function ajax_reorder_branches() {
        // Verify nonce
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        // Check capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        // Get order data
        $order = isset( $_POST['order'] ) ? array_map( 'absint', $_POST['order'] ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'No order data provided.', 'wbim' ) ) );
        }

        // Update order
        $result = WBIM_Branch::reorder( $order );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved successfully.', 'wbim' ) ) );
    }

    /**
     * AJAX handler for toggling branch status
     *
     * @return void
     */
    public function ajax_toggle_branch_status() {
        // Verify nonce
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        // Check capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        // Get branch ID
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        if ( ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid branch ID.', 'wbim' ) ) );
        }

        // Toggle status
        $result = WBIM_Branch::toggle_status( $branch_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'message'    => __( 'Status updated successfully.', 'wbim' ),
                'new_status' => $result,
            )
        );
    }

    /**
     * AJAX handler for deleting branch
     *
     * @return void
     */
    public function ajax_delete_branch() {
        // Verify nonce
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        // Check capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        // Get branch ID
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        if ( ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid branch ID.', 'wbim' ) ) );
        }

        // Get force delete flag
        $force = isset( $_POST['force'] ) && 'true' === $_POST['force'];

        // Delete branch
        $result = WBIM_Branch::delete( $branch_id, $force );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Branch deleted successfully.', 'wbim' ) ) );
    }

    /**
     * Display admin notices from transient
     *
     * @return void
     */
    public function display_admin_notices() {
        // Check if we're on a plugin page
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'wbim' ) === false ) {
            return;
        }

        // Get notice from transient
        $notice = get_transient( 'wbim_admin_notice' );
        if ( ! $notice ) {
            return;
        }

        // Delete transient
        delete_transient( 'wbim_admin_notice' );

        // Display notice
        WBIM_Utils::admin_notice( $notice['message'], $notice['type'] );
    }
}
