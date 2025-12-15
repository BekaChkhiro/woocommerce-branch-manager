<?php
/**
 * Admin Stock Management
 *
 * Handles stock management pages and WooCommerce product integration.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Stock class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Stock {

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
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ), 5 );

        // Variation inventory fields
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_inventory_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_inventory' ), 10, 2 );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_get_product_stock', array( $this, 'ajax_get_product_stock' ) );
        add_action( 'wp_ajax_wbim_update_stock', array( $this, 'ajax_update_stock' ) );
        add_action( 'wp_ajax_wbim_bulk_update_stock', array( $this, 'ajax_bulk_update_stock' ) );
        add_action( 'wp_ajax_wbim_export_stock', array( $this, 'ajax_export_stock' ) );
        add_action( 'wp_ajax_wbim_import_stock', array( $this, 'ajax_import_stock' ) );
        add_action( 'wp_ajax_wbim_download_template', array( $this, 'ajax_download_template' ) );
        add_action( 'wp_ajax_wbim_export_history', array( $this, 'ajax_export_history' ) );
        add_action( 'wp_ajax_wbim_sync_all_wc_stock', array( $this, 'ajax_sync_all_wc_stock' ) );
        add_action( 'wp_ajax_wbim_preview_import', array( $this, 'ajax_preview_import' ) );
    }

    /**
     * Add product data tab
     *
     * @param array $tabs Product data tabs.
     * @return array
     */
    public function add_product_data_tab( $tabs ) {
        $tabs['wbim_inventory'] = array(
            'label'    => __( 'ფილიალის მარაგი', 'wbim' ),
            'target'   => 'wbim_inventory_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 65,
        );

        return $tabs;
    }

    /**
     * Product data panel content
     *
     * @return void
     */
    public function product_data_panel() {
        global $post;

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        include WBIM_PLUGIN_DIR . 'admin/views/stock/product-tab.php';
    }

    /**
     * Save product data
     *
     * @param int $post_id Product ID.
     * @return void
     */
    public function save_product_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['wbim_product_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_POST['wbim_product_nonce'] ), 'wbim_save_product_stock' ) ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            return;
        }

        // Get product
        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        // Handle variable products - process data from "ფილიალის მარაგი" tab
        // Note: Variation accordion fields are saved via save_variation_inventory() hook
        if ( $product->is_type( 'variable' ) ) {
            error_log( 'WBIM save_product_data: variable product ' . $post_id );
            error_log( 'WBIM save_product_data: wbim_variation_stock = ' . print_r( isset( $_POST['wbim_variation_stock'] ) ? $_POST['wbim_variation_stock'] : 'NOT SET', true ) );

            // Process variation stock from the product tab (format: wbim_variation_stock[variation_id][branch_id][quantity])
            if ( isset( $_POST['wbim_variation_stock'] ) && is_array( $_POST['wbim_variation_stock'] ) ) {
                foreach ( $_POST['wbim_variation_stock'] as $variation_id => $branches ) {
                    $variation_id = absint( $variation_id );

                    error_log( "WBIM save_product_data: processing variation_id = $variation_id" );

                    // Skip if this looks like a loop index (small number) rather than a variation ID
                    // Loop indexes are 0, 1, 2, etc. Variation IDs are typically much larger
                    if ( $variation_id < 100 ) {
                        error_log( "WBIM save_product_data: skipping $variation_id (looks like loop index)" );
                        continue;
                    }

                    if ( ! is_array( $branches ) ) {
                        continue;
                    }

                    foreach ( $branches as $branch_id => $data ) {
                        $branch_id = absint( $branch_id );
                        if ( ! $branch_id ) {
                            continue;
                        }

                        $stock_data = array(
                            'quantity' => isset( $data['quantity'] ) ? intval( $data['quantity'] ) : 0,
                        );

                        if ( isset( $data['low_stock_threshold'] ) ) {
                            $stock_data['low_stock_threshold'] = absint( $data['low_stock_threshold'] );
                        }

                        if ( isset( $data['shelf_location'] ) ) {
                            $stock_data['shelf_location'] = sanitize_text_field( $data['shelf_location'] );
                        }

                        WBIM_Stock::set( $post_id, $variation_id, $branch_id, $stock_data );
                    }

                    // Sync variation with WooCommerce stock
                    WBIM_Stock::sync_wc_stock( $post_id, $variation_id );
                }
            }
            return;
        }

        // Process simple product branch stock
        if ( isset( $_POST['wbim_branch_stock'] ) && is_array( $_POST['wbim_branch_stock'] ) ) {
            // Calculate the new total FIRST before saving
            $new_total = 0;
            foreach ( $_POST['wbim_branch_stock'] as $branch_id => $data ) {
                $new_total += isset( $data['quantity'] ) ? intval( $data['quantity'] ) : 0;
            }

            // Update WC POST data to prevent stock conflict error
            // WooCommerce compares _original_stock with current DB value
            // By setting both to the new total, we prevent the conflict
            $_POST['_original_stock'] = $new_total;
            $_POST['_stock'] = $new_total;

            // Now save the branch stock
            foreach ( $_POST['wbim_branch_stock'] as $branch_id => $data ) {
                $branch_id = absint( $branch_id );
                if ( ! $branch_id ) {
                    continue;
                }

                $stock_data = array(
                    'quantity' => isset( $data['quantity'] ) ? intval( $data['quantity'] ) : 0,
                );

                // Handle stock status
                if ( isset( $data['stock_status'] ) ) {
                    $stock_data['stock_status'] = sanitize_text_field( $data['stock_status'] );
                }

                if ( isset( $data['low_stock_threshold'] ) ) {
                    $stock_data['low_stock_threshold'] = absint( $data['low_stock_threshold'] );
                }

                if ( isset( $data['shelf_location'] ) ) {
                    $stock_data['shelf_location'] = sanitize_text_field( $data['shelf_location'] );
                }

                WBIM_Stock::set( $post_id, 0, $branch_id, $stock_data );
            }

            // Sync with WooCommerce stock
            WBIM_Stock::sync_wc_stock( $post_id );
        }
    }

    /**
     * Variation inventory fields
     *
     * @param int     $loop           Loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     * @return void
     */
    public function variation_inventory_fields( $loop, $variation_data, $variation ) {
        $variation_id = $variation->ID;
        $parent_id = wp_get_post_parent_id( $variation_id );
        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            return;
        }

        // Calculate total stock
        $total_stock = 0;
        $branch_stocks = array();
        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $parent_id, $branch->id, $variation_id );
            $quantity = $stock ? $stock->quantity : 0;
            $branch_stocks[ $branch->id ] = $quantity;
            $total_stock += $quantity;
        }

        ?>
        <div class="wbim-variation-inventory-wrapper">
            <div class="wbim-variation-inventory-header">
                <span class="wbim-variation-inventory-title">
                    <span class="dashicons dashicons-store"></span>
                    <?php esc_html_e( 'ფილიალების მარაგი', 'wbim' ); ?>
                </span>
                <span class="wbim-variation-inventory-total">
                    <?php esc_html_e( 'ჯამი:', 'wbim' ); ?>
                    <strong class="wbim-total-qty" data-loop="<?php echo esc_attr( $loop ); ?>"><?php echo esc_html( $total_stock ); ?></strong>
                </span>
            </div>
            <div class="wbim-variation-inventory-grid">
                <?php foreach ( $branches as $branch ) : ?>
                    <?php $quantity = $branch_stocks[ $branch->id ]; ?>
                    <div class="wbim-variation-branch-item">
                        <label class="wbim-branch-label" for="wbim_var_stock_<?php echo esc_attr( $loop ); ?>_<?php echo esc_attr( $branch->id ); ?>">
                            <?php echo esc_html( $branch->name ); ?>
                        </label>
                        <input type="number"
                               id="wbim_var_stock_<?php echo esc_attr( $loop ); ?>_<?php echo esc_attr( $branch->id ); ?>"
                               name="wbim_variation_stock[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $branch->id ); ?>]"
                               value="<?php echo esc_attr( $quantity ); ?>"
                               min="0"
                               step="1"
                               data-loop="<?php echo esc_attr( $loop ); ?>"
                               data-branch-id="<?php echo esc_attr( $branch->id ); ?>"
                               data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
                               class="wbim-variation-stock-input" />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save variation inventory
     *
     * @param int $variation_id Variation ID.
     * @param int $loop         Loop index.
     * @return void
     */
    public function save_variation_inventory( $variation_id, $loop ) {
        // Check permissions - allow admin and shop_manager
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_stock' ) ) {
            return;
        }

        $parent_id = wp_get_post_parent_id( $variation_id );

        if ( isset( $_POST['wbim_variation_stock'][ $loop ] ) && is_array( $_POST['wbim_variation_stock'][ $loop ] ) ) {
            foreach ( $_POST['wbim_variation_stock'][ $loop ] as $branch_id => $quantity ) {
                $branch_id = absint( $branch_id );
                if ( ! $branch_id ) {
                    continue;
                }

                WBIM_Stock::set(
                    $parent_id,
                    $variation_id,
                    $branch_id,
                    array( 'quantity' => intval( $quantity ) )
                );
            }

            // Sync with WooCommerce stock
            WBIM_Stock::sync_wc_stock( $parent_id, $variation_id );
        }
    }

    /**
     * Render stock management page
     *
     * @return void
     */
    public function render_stock_page() {
        // Check permissions
        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Handle actions
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

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
     * Render stock history page
     *
     * @return void
     */
    public function render_history_page() {
        // Check permissions
        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        include WBIM_PLUGIN_DIR . 'admin/views/history/list.php';
    }

    /**
     * AJAX: Get product stock data
     *
     * @return void
     */
    public function ajax_get_product_stock() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'wbim' ) ) );
        }

        $stock = WBIM_Stock::get_product_stock_by_branch( $product_id, $variation_id );
        $branches = WBIM_Branch::get_active();

        $data = array();
        foreach ( $branches as $branch ) {
            $branch_stock = isset( $stock[ $branch->id ] ) ? $stock[ $branch->id ] : null;
            $data[] = array(
                'branch_id'           => $branch->id,
                'branch_name'         => $branch->name,
                'quantity'            => $branch_stock ? $branch_stock['quantity'] : 0,
                'low_stock_threshold' => $branch_stock ? $branch_stock['low_stock_threshold'] : 0,
                'shelf_location'      => $branch_stock ? $branch_stock['shelf_location'] : '',
            );
        }

        wp_send_json_success( array( 'stock' => $data ) );
    }

    /**
     * AJAX: Update stock
     *
     * @return void
     */
    public function ajax_update_stock() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
        $quantity = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 0;

        error_log( "WBIM ajax_update_stock: product_id=$product_id, variation_id=$variation_id, branch_id=$branch_id, quantity=$quantity" );

        if ( ! $product_id || ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wbim' ) ) );
        }

        $stock_data = array( 'quantity' => $quantity );

        if ( isset( $_POST['low_stock_threshold'] ) ) {
            $stock_data['low_stock_threshold'] = absint( $_POST['low_stock_threshold'] );
        }

        if ( isset( $_POST['shelf_location'] ) ) {
            $stock_data['shelf_location'] = sanitize_text_field( wp_unslash( $_POST['shelf_location'] ) );
        }

        if ( isset( $_POST['stock_status'] ) ) {
            $stock_data['stock_status'] = sanitize_text_field( wp_unslash( $_POST['stock_status'] ) );
        }

        $result = WBIM_Stock::set( $product_id, $variation_id, $branch_id, $stock_data );
        error_log( "WBIM ajax_update_stock result: " . ( is_wp_error( $result ) ? $result->get_error_message() : 'success' ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Sync WooCommerce stock
        WBIM_Stock::sync_wc_stock( $product_id, $variation_id );

        wp_send_json_success(
            array(
                'message'     => __( 'Stock updated successfully.', 'wbim' ),
                'total_stock' => WBIM_Stock::get_total( $product_id, $variation_id ),
            )
        );
    }

    /**
     * AJAX: Bulk update stock
     *
     * @return void
     */
    public function ajax_bulk_update_stock() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        $updates = isset( $_POST['updates'] ) ? $_POST['updates'] : array();

        if ( empty( $updates ) || ! is_array( $updates ) ) {
            wp_send_json_error( array( 'message' => __( 'No updates provided.', 'wbim' ) ) );
        }

        $success = 0;
        $errors = array();

        foreach ( $updates as $update ) {
            $product_id = isset( $update['product_id'] ) ? absint( $update['product_id'] ) : 0;
            $variation_id = isset( $update['variation_id'] ) ? absint( $update['variation_id'] ) : 0;
            $branch_id = isset( $update['branch_id'] ) ? absint( $update['branch_id'] ) : 0;
            $quantity = isset( $update['quantity'] ) ? intval( $update['quantity'] ) : 0;

            if ( ! $product_id || ! $branch_id ) {
                continue;
            }

            $result = WBIM_Stock::set( $product_id, $variation_id, $branch_id, array( 'quantity' => $quantity ) );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            } else {
                $success++;
                WBIM_Stock::sync_wc_stock( $product_id, $variation_id );
            }
        }

        if ( $success > 0 ) {
            wp_send_json_success(
                array(
                    'message' => sprintf(
                        /* translators: %d: number of items updated */
                        __( '%d stock entries updated successfully.', 'wbim' ),
                        $success
                    ),
                    'errors'  => $errors,
                )
            );
        } else {
            wp_send_json_error( array( 'message' => __( 'No stock entries were updated.', 'wbim' ) ) );
        }
    }

    /**
     * AJAX: Export stock
     *
     * @return void
     */
    public function ajax_export_stock() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wbim' ) );
        }

        $branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;

        WBIM_CSV_Handler::export(
            array(
                'branch_id' => $branch_id,
            )
        );
    }

    /**
     * AJAX: Download import template
     *
     * @return void
     */
    public function ajax_download_template() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wbim' ) );
        }

        WBIM_CSV_Handler::download_template();
    }

    /**
     * AJAX: Import stock
     *
     * @return void
     */
    public function ajax_import_stock() {
        try {
            // Increase memory and time limits for large imports
            @ini_set( 'memory_limit', '512M' );
            @set_time_limit( 300 );

            check_ajax_referer( 'wbim_admin', 'nonce' );

            if ( ! current_user_can( 'wbim_manage_stock' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Permission denied.', 'wbim' ),
                    'debug'   => 'User does not have wbim_manage_stock capability',
                ) );
            }

            if ( ! isset( $_FILES['import_file'] ) ) {
                wp_send_json_error( array(
                    'message' => __( 'ფაილი არ არის ატვირთული.', 'wbim' ),
                    'debug'   => 'No file in $_FILES[import_file]',
                ) );
            }

            // Get branch ID
            $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

            if ( empty( $branch_id ) ) {
                wp_send_json_error( array(
                    'message' => __( 'გთხოვთ აირჩიოთ ფილიალი.', 'wbim' ),
                    'debug'   => 'branch_id is empty or 0',
                ) );
            }

            // Validate file
            $file_path = WBIM_CSV_Handler::validate_upload( $_FILES['import_file'] );

            if ( is_wp_error( $file_path ) ) {
                wp_send_json_error( array(
                    'message' => $file_path->get_error_message(),
                    'debug'   => 'File validation failed: ' . $file_path->get_error_code(),
                ) );
            }

            // Import options
            $options = array(
                'update_existing'            => isset( $_POST['update_existing'] ) && 'true' === $_POST['update_existing'],
                'skip_empty'                 => true,
                'distribute_to_variations'   => isset( $_POST['distribute_to_variations'] ) && 'true' === $_POST['distribute_to_variations'],
            );

            // Determine file type and import accordingly
            $file_type = WBIM_CSV_Handler::get_file_type( $file_path );

            if ( 'json' === $file_type ) {
                $results = WBIM_CSV_Handler::import_json( $file_path, $branch_id, $options );
            } else {
                // For CSV files, update import method to use selected branch
                $results = WBIM_CSV_Handler::import_with_branch( $file_path, $branch_id, $options );
            }

            // Check if there were critical errors (no rows processed)
            if ( $results['total_rows'] === 0 && ! empty( $results['errors'] ) ) {
                wp_send_json_error( array(
                    'message' => __( 'იმპორტი ვერ მოხერხდა.', 'wbim' ),
                    'debug'   => implode( '; ', array_slice( $results['errors'], 0, 5 ) ),
                    'errors'  => $results['errors'],
                ) );
            }

            // Format response
            $message = sprintf(
                __( 'იმპორტი დასრულდა: %1$d პროდუქტი %2$d-დან წარმატებით იმპორტირდა.', 'wbim' ),
                $results['success'],
                $results['total_rows']
            );

            if ( $results['skipped'] > 0 ) {
                $message .= ' ' . sprintf(
                    __( '%d გამოტოვდა.', 'wbim' ),
                    $results['skipped']
                );
            }

            wp_send_json_success(
                array(
                    'message' => $message,
                    'results' => $results,
                )
            );
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => __( 'დაფიქსირდა შეცდომა.', 'wbim' ),
                'debug'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ) );
        }
    }

    /**
     * AJAX: Export history
     *
     * @return void
     */
    public function ajax_export_history() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wbim' ) );
        }

        $args = array(
            'branch_id'   => isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0,
            'product_id'  => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
            'action_type' => isset( $_GET['action_type'] ) ? sanitize_key( $_GET['action_type'] ) : '',
            'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
            'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
        );

        WBIM_CSV_Handler::export_history( $args );
    }

    /**
     * AJAX: Sync all WC stock
     *
     * @return void
     */
    public function ajax_sync_all_wc_stock() {
        check_ajax_referer( 'wbim_admin', 'nonce' );

        if ( ! current_user_can( 'wbim_manage_stock' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wbim' ) ) );
        }

        global $wpdb;

        // Get all products with branch stock
        $stock_table = $wpdb->prefix . 'wbim_stock';

        // Get unique product/variation combinations
        $items = $wpdb->get_results(
            "SELECT DISTINCT product_id, variation_id
             FROM {$stock_table}
             ORDER BY product_id, variation_id"
        );

        $synced = 0;
        $errors = array();

        foreach ( $items as $item ) {
            $result = WBIM_Stock::sync_wc_stock( $item->product_id, $item->variation_id );
            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            } else {
                $synced++;
            }
        }

        if ( $synced > 0 ) {
            wp_send_json_success(
                array(
                    'message' => sprintf(
                        /* translators: %d: number of products synced */
                        __( '%d პროდუქტის მარაგი სინქრონიზებულია WooCommerce-თან.', 'wbim' ),
                        $synced
                    ),
                    'synced' => $synced,
                    'errors' => $errors,
                )
            );
        } else {
            wp_send_json_error(
                array(
                    'message' => __( 'სინქრონიზაცია ვერ მოხერხდა.', 'wbim' ),
                    'errors'  => $errors,
                )
            );
        }
    }

    /**
     * AJAX: Preview import
     *
     * @return void
     */
    public function ajax_preview_import() {
        try {
            check_ajax_referer( 'wbim_admin', 'nonce' );

            if ( ! current_user_can( 'wbim_manage_stock' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Permission denied.', 'wbim' ),
                    'debug'   => 'User does not have wbim_manage_stock capability',
                ) );
            }

            if ( ! isset( $_FILES['import_file'] ) ) {
                wp_send_json_error( array(
                    'message' => __( 'ფაილი არ არის ატვირთული.', 'wbim' ),
                    'debug'   => 'No file in $_FILES[import_file]',
                ) );
            }

            // Get branch ID
            $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

            if ( empty( $branch_id ) ) {
                wp_send_json_error( array(
                    'message' => __( 'გთხოვთ აირჩიოთ ფილიალი.', 'wbim' ),
                    'debug'   => 'branch_id is empty or 0',
                ) );
            }

            // Validate file
            $file_path = WBIM_CSV_Handler::validate_upload( $_FILES['import_file'] );

            if ( is_wp_error( $file_path ) ) {
                wp_send_json_error( array(
                    'message' => $file_path->get_error_message(),
                    'debug'   => 'File validation failed: ' . $file_path->get_error_code(),
                    'file_info' => array(
                        'name' => $_FILES['import_file']['name'] ?? 'unknown',
                        'type' => $_FILES['import_file']['type'] ?? 'unknown',
                        'size' => $_FILES['import_file']['size'] ?? 0,
                        'error' => $_FILES['import_file']['error'] ?? 'unknown',
                    ),
                ) );
            }

            // Determine file type and preview accordingly
            $file_type = WBIM_CSV_Handler::get_file_type( $file_path );

            if ( 'json' === $file_type ) {
                $preview_data = WBIM_CSV_Handler::preview_json( $file_path, $branch_id, 20 );
            } else {
                $preview_data = WBIM_CSV_Handler::preview_with_branch( $file_path, $branch_id, 20 );
            }

            if ( is_wp_error( $preview_data ) ) {
                wp_send_json_error( array(
                    'message' => $preview_data->get_error_message(),
                    'debug'   => 'Preview failed: ' . $preview_data->get_error_code(),
                    'file_type' => $file_type,
                ) );
            }

            wp_send_json_success( $preview_data );
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => __( 'დაფიქსირდა შეცდომა.', 'wbim' ),
                'debug'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ) );
        }
    }
}
