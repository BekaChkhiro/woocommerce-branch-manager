<?php
/**
 * Admin Branch Prices Management
 *
 * Handles branch-specific pricing pages and AJAX operations.
 *
 * @package WBIM
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Prices class
 *
 * @since 1.4.0
 */
class WBIM_Admin_Prices {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // WooCommerce product data tabs
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ), 10 );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_get_product_prices', array( $this, 'ajax_get_product_prices' ) );
        add_action( 'wp_ajax_wbim_save_branch_price', array( $this, 'ajax_save_branch_price' ) );
        add_action( 'wp_ajax_wbim_delete_branch_price', array( $this, 'ajax_delete_branch_price' ) );
        add_action( 'wp_ajax_wbim_bulk_update_prices', array( $this, 'ajax_bulk_update_prices' ) );
        add_action( 'wp_ajax_wbim_search_products_for_prices', array( $this, 'ajax_search_products' ) );
    }

    /**
     * Add product data tab for branch prices
     *
     * @param array $tabs Product data tabs.
     * @return array
     */
    public function add_product_data_tab( $tabs ) {
        $tabs['wbim_prices'] = array(
            'label'    => __( 'ფილიალის ფასები', 'wbim' ),
            'target'   => 'wbim_prices_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 66,
        );

        return $tabs;
    }

    /**
     * Product data panel content for branch prices
     *
     * @return void
     */
    public function product_data_panel() {
        global $post;

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        include WBIM_PLUGIN_DIR . 'admin/views/prices/product-tab.php';
    }

    /**
     * Save product price data
     *
     * @param int $post_id Product ID.
     * @return void
     */
    public function save_product_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['wbim_prices_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_POST['wbim_prices_nonce'] ), 'wbim_save_product_prices' ) ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Get product
        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        // Process simple product branch prices
        if ( isset( $_POST['wbim_branch_prices'] ) && is_array( $_POST['wbim_branch_prices'] ) ) {
            foreach ( $_POST['wbim_branch_prices'] as $branch_id => $tiers ) {
                $branch_id = absint( $branch_id );
                if ( ! $branch_id || ! is_array( $tiers ) ) {
                    continue;
                }

                foreach ( $tiers as $min_quantity => $data ) {
                    $min_quantity = absint( $min_quantity );
                    if ( $min_quantity < 1 ) {
                        $min_quantity = 1;
                    }

                    $regular_price = isset( $data['regular_price'] ) ? $data['regular_price'] : '';
                    $sale_price = isset( $data['sale_price'] ) ? $data['sale_price'] : '';

                    // If both prices are empty, delete the record
                    if ( '' === $regular_price && '' === $sale_price ) {
                        WBIM_Branch_Price::delete( $post_id, $branch_id, 0, $min_quantity );
                    } else {
                        WBIM_Branch_Price::set( $post_id, 0, $branch_id, array(
                            'regular_price' => $regular_price,
                            'sale_price'    => $sale_price,
                            'min_quantity'  => $min_quantity,
                        ) );
                    }
                }
            }
        }

        // Process variable product prices
        if ( isset( $_POST['wbim_variation_prices'] ) && is_array( $_POST['wbim_variation_prices'] ) ) {
            foreach ( $_POST['wbim_variation_prices'] as $variation_id => $branches ) {
                $variation_id = absint( $variation_id );
                if ( $variation_id < 100 || ! is_array( $branches ) ) {
                    continue;
                }

                foreach ( $branches as $branch_id => $tiers ) {
                    $branch_id = absint( $branch_id );
                    if ( ! $branch_id || ! is_array( $tiers ) ) {
                        continue;
                    }

                    foreach ( $tiers as $min_quantity => $data ) {
                        $min_quantity = absint( $min_quantity );
                        if ( $min_quantity < 1 ) {
                            $min_quantity = 1;
                        }

                        $regular_price = isset( $data['regular_price'] ) ? $data['regular_price'] : '';
                        $sale_price = isset( $data['sale_price'] ) ? $data['sale_price'] : '';

                        // If both prices are empty, delete the record
                        if ( '' === $regular_price && '' === $sale_price ) {
                            WBIM_Branch_Price::delete( $post_id, $branch_id, $variation_id, $min_quantity );
                        } else {
                            WBIM_Branch_Price::set( $post_id, $variation_id, $branch_id, array(
                                'regular_price' => $regular_price,
                                'sale_price'    => $sale_price,
                                'min_quantity'  => $min_quantity,
                            ) );
                        }
                    }
                }
            }
        }
    }

    /**
     * Render prices admin page
     *
     * @return void
     */
    public function render_page() {
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'edit':
                include WBIM_PLUGIN_DIR . 'admin/views/prices/edit.php';
                break;

            default:
                include WBIM_PLUGIN_DIR . 'admin/views/prices/list.php';
                break;
        }
    }

    /**
     * AJAX: Get product prices
     *
     * @return void
     */
    public function ajax_get_product_prices() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არაავტორიზებული წვდომა', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'პროდუქტი ვერ მოიძებნა', 'wbim' ) ) );
        }

        $prices = WBIM_Branch_Price::get_prices_by_branch( $product_id, $variation_id );
        $branches = WBIM_Branch::get_all( array( 'is_active' => 1 ) );

        wp_send_json_success( array(
            'prices'   => $prices,
            'branches' => $branches,
        ) );
    }

    /**
     * AJAX: Save branch price
     *
     * @return void
     */
    public function ajax_save_branch_price() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არაავტორიზებული წვდომა', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
        $min_quantity = isset( $_POST['min_quantity'] ) ? absint( $_POST['min_quantity'] ) : 1;
        $regular_price = isset( $_POST['regular_price'] ) ? sanitize_text_field( $_POST['regular_price'] ) : '';
        $sale_price = isset( $_POST['sale_price'] ) ? sanitize_text_field( $_POST['sale_price'] ) : '';

        if ( ! $product_id || ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები', 'wbim' ) ) );
        }

        $result = WBIM_Branch_Price::set( $product_id, $variation_id, $branch_id, array(
            'regular_price' => $regular_price,
            'sale_price'    => $sale_price,
            'min_quantity'  => $min_quantity,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'ფასი შენახულია', 'wbim' ) ) );
    }

    /**
     * AJAX: Delete branch price
     *
     * @return void
     */
    public function ajax_delete_branch_price() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არაავტორიზებული წვდომა', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
        $min_quantity = isset( $_POST['min_quantity'] ) ? absint( $_POST['min_quantity'] ) : 1;

        if ( ! $product_id || ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები', 'wbim' ) ) );
        }

        $result = WBIM_Branch_Price::delete( $product_id, $branch_id, $variation_id, $min_quantity );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'წაშლა ვერ მოხერხდა', 'wbim' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'ფასი წაშლილია', 'wbim' ) ) );
    }

    /**
     * AJAX: Bulk update prices
     *
     * @return void
     */
    public function ajax_bulk_update_prices() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არაავტორიზებული წვდომა', 'wbim' ) ) );
        }

        $items = isset( $_POST['items'] ) ? $_POST['items'] : array();

        if ( empty( $items ) ) {
            wp_send_json_error( array( 'message' => __( 'მონაცემები არ მოწოდებულა', 'wbim' ) ) );
        }

        $results = WBIM_Branch_Price::bulk_update( $items );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %1$d: success count, %2$d: error count */
                __( 'განახლდა: %1$d, შეცდომა: %2$d', 'wbim' ),
                $results['success'],
                $results['errors']
            ),
            'results' => $results,
        ) );
    }

    /**
     * AJAX: Search products for price assignment
     *
     * @return void
     */
    public function ajax_search_products() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არაავტორიზებული წვდომა', 'wbim' ) ) );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;

        global $wpdb;

        $like = '%' . $wpdb->esc_like( $search ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_type, pm.meta_value as sku
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status = 'publish'
                AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
                ORDER BY p.post_title ASC
                LIMIT %d",
                $like,
                $like,
                $limit
            )
        );

        $results = array();
        foreach ( $products as $product ) {
            $wc_product = wc_get_product( $product->ID );
            if ( ! $wc_product ) {
                continue;
            }

            $results[] = array(
                'id'    => $product->ID,
                'text'  => $product->post_title . ( $product->sku ? ' (' . $product->sku . ')' : '' ),
                'sku'   => $product->sku,
                'type'  => $wc_product->get_type(),
                'price' => $wc_product->get_price(),
            );
        }

        wp_send_json_success( $results );
    }
}

// Initialize
new WBIM_Admin_Prices();
