<?php
/**
 * Admin Orders Class
 *
 * Handles order branch management in admin area.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Orders class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Orders {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add branch column to orders list
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_branch_column' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_branch_column' ) );

        // Render branch column content
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_branch_column' ), 10, 2 );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_branch_column_legacy' ), 10, 2 );

        // Add metabox to order edit page
        add_action( 'add_meta_boxes', array( $this, 'add_order_metabox' ) );

        // Save metabox data
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_metabox' ), 10, 2 );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_change_order_branch', array( $this, 'ajax_change_order_branch' ) );
        add_action( 'wp_ajax_wbim_change_item_branch', array( $this, 'ajax_change_item_branch' ) );
        add_action( 'wp_ajax_wbim_manually_deduct_stock', array( $this, 'ajax_manually_deduct_stock' ) );
        add_action( 'wp_ajax_wbim_manually_return_stock', array( $this, 'ajax_manually_return_stock' ) );

        // Branch filter in orders list
        add_action( 'restrict_manage_posts', array( $this, 'add_branch_filter' ), 20 );
        add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'add_branch_filter_hpos' ), 20 );

        // Filter orders by branch
        add_filter( 'request', array( $this, 'filter_orders_by_branch' ) );
        add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'filter_orders_by_branch_hpos' ) );

        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add branch column to orders list
     *
     * @param array $columns Columns.
     * @return array
     */
    public function add_branch_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            // Add branch column after order status
            if ( 'order_status' === $key ) {
                $new_columns['wbim_branch'] = __( 'ფილიალი', 'wbim' );
            }
        }

        return $new_columns;
    }

    /**
     * Render branch column content (HPOS)
     *
     * @param string $column   Column name.
     * @param object $order    Order object.
     */
    public function render_branch_column( $column, $order ) {
        if ( 'wbim_branch' !== $column ) {
            return;
        }

        $this->output_branch_column_content( $order );
    }

    /**
     * Render branch column content (Legacy)
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_branch_column_legacy( $column, $post_id ) {
        if ( 'wbim_branch' !== $column ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( $order ) {
            $this->output_branch_column_content( $order );
        }
    }

    /**
     * Output branch column content
     *
     * @param WC_Order $order Order object.
     */
    private function output_branch_column_content( $order ) {
        $branch_id = $order->get_meta( '_wbim_branch_id' );

        if ( ! $branch_id ) {
            echo '<span class="wbim-no-branch">' . esc_html__( 'მინიჭებული არ არის', 'wbim' ) . '</span>';
            return;
        }

        $branch = WBIM_Branch::get_by_id( $branch_id );

        if ( $branch ) {
            echo '<span class="wbim-branch-name">' . esc_html( $branch->name ) . '</span>';

            // Show if multiple branches are involved
            if ( WBIM_Order_Allocation::has_multiple_branches( $order->get_id() ) ) {
                echo '<br><small class="wbim-multiple-branches">' . esc_html__( '+ სხვა ფილიალები', 'wbim' ) . '</small>';
            }
        } else {
            echo '<span class="wbim-branch-deleted">' . esc_html__( 'წაშლილი ფილიალი', 'wbim' ) . '</span>';
        }
    }

    /**
     * Add order metabox
     */
    public function add_order_metabox() {
        $screen = class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
            && wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'wbim_order_branch',
            __( 'ფილიალის მინიჭება', 'wbim' ),
            array( $this, 'render_order_metabox' ),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render order metabox
     *
     * @param WP_Post|WC_Order $post_or_order Post or Order object.
     */
    public function render_order_metabox( $post_or_order ) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

        if ( ! $order ) {
            return;
        }

        $order_id = $order->get_id();
        $branch_id = $order->get_meta( '_wbim_branch_id' );
        $stock_deducted = WBIM_Order_Allocation::is_stock_deducted( $order_id );
        $branches = WBIM_Branch::get_active();
        $allocations = WBIM_Order_Allocation::get_by_order( $order_id );

        wp_nonce_field( 'wbim_order_branch_nonce', 'wbim_order_branch_nonce' );
        ?>
        <div class="wbim-order-branch-metabox">
            <!-- Main branch selector -->
            <p>
                <label for="wbim_branch_id"><strong><?php esc_html_e( 'ძირითადი ფილიალი:', 'wbim' ); ?></strong></label>
                <select name="wbim_branch_id" id="wbim_branch_id" class="wbim-branch-select" style="width: 100%;">
                    <option value=""><?php esc_html_e( '-- აირჩიეთ ფილიალი --', 'wbim' ); ?></option>
                    <?php foreach ( $branches as $branch ) : ?>
                        <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $branch_id, $branch->id ); ?>>
                            <?php echo esc_html( $branch->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <!-- Stock status -->
            <p class="wbim-stock-status">
                <strong><?php esc_html_e( 'მარაგის სტატუსი:', 'wbim' ); ?></strong><br>
                <?php if ( $stock_deducted ) : ?>
                    <span class="wbim-status-deducted">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'მარაგი ჩამოჭრილია', 'wbim' ); ?>
                    </span>
                <?php else : ?>
                    <span class="wbim-status-not-deducted">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'მარაგი არ არის ჩამოჭრილი', 'wbim' ); ?>
                    </span>
                <?php endif; ?>
            </p>

            <!-- Manual stock actions -->
            <div class="wbim-stock-actions">
                <?php if ( ! $stock_deducted && $branch_id ) : ?>
                    <button type="button" class="button wbim-deduct-stock" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                        <?php esc_html_e( 'მარაგის ჩამოჭრა', 'wbim' ); ?>
                    </button>
                <?php elseif ( $stock_deducted ) : ?>
                    <button type="button" class="button wbim-return-stock" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                        <?php esc_html_e( 'მარაგის დაბრუნება', 'wbim' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Item allocations -->
            <?php if ( ! empty( $allocations ) ) : ?>
            <hr>
            <div class="wbim-item-allocations">
                <p><strong><?php esc_html_e( 'პროდუქტების მინიჭება:', 'wbim' ); ?></strong></p>
                <table class="wbim-allocations-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'რაოდ.', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $allocations as $allocation ) : ?>
                            <?php
                            $product = wc_get_product( $allocation->variation_id ? $allocation->variation_id : $allocation->product_id );
                            $product_name = $product ? $product->get_name() : __( 'წაშლილი პროდუქტი', 'wbim' );
                            ?>
                            <tr>
                                <td><?php echo esc_html( $product_name ); ?></td>
                                <td><?php echo esc_html( $allocation->quantity ); ?></td>
                                <td>
                                    <select class="wbim-item-branch-select" data-allocation-id="<?php echo esc_attr( $allocation->id ); ?>" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                                        <?php foreach ( $branches as $branch ) : ?>
                                            <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $allocation->branch_id, $branch->id ); ?>>
                                                <?php echo esc_html( $branch->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <style>
            .wbim-order-branch-metabox .wbim-status-deducted { color: #46b450; }
            .wbim-order-branch-metabox .wbim-status-not-deducted { color: #dc3232; }
            .wbim-order-branch-metabox .wbim-stock-actions { margin: 10px 0; }
            .wbim-order-branch-metabox .wbim-allocations-table { font-size: 12px; margin-top: 10px; }
            .wbim-order-branch-metabox .wbim-allocations-table th,
            .wbim-order-branch-metabox .wbim-allocations-table td { padding: 5px; }
            .wbim-order-branch-metabox .wbim-item-branch-select { width: 100%; font-size: 11px; }
        </style>
        <?php
    }

    /**
     * Save order metabox data
     *
     * @param int     $order_id Order ID.
     * @param WP_Post $post     Post object (legacy).
     */
    public function save_order_metabox( $order_id, $post = null ) {
        if ( ! isset( $_POST['wbim_order_branch_nonce'] ) ||
             ! wp_verify_nonce( $_POST['wbim_order_branch_nonce'], 'wbim_order_branch_nonce' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $old_branch_id = $order->get_meta( '_wbim_branch_id' );
        $new_branch_id = isset( $_POST['wbim_branch_id'] ) ? absint( $_POST['wbim_branch_id'] ) : 0;

        if ( $old_branch_id != $new_branch_id ) {
            if ( $new_branch_id ) {
                $order->update_meta_data( '_wbim_branch_id', $new_branch_id );

                $branch = WBIM_Branch::get_by_id( $new_branch_id );
                if ( $branch ) {
                    $order->update_meta_data( '_wbim_branch_name', $branch->name );
                    $order->add_order_note(
                        sprintf(
                            __( 'ფილიალი შეცვლილია: %s', 'wbim' ),
                            $branch->name
                        )
                    );
                }
            } else {
                $order->delete_meta_data( '_wbim_branch_id' );
                $order->delete_meta_data( '_wbim_branch_name' );
            }

            $order->save();
        }
    }

    /**
     * AJAX: Change order branch
     */
    public function ajax_change_order_branch() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი შეკვეთა.', 'wbim' ) ) );
        }

        $order_handler = new WBIM_Order_Handler();
        $result = $order_handler->change_order_branch( $order_id, $branch_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'ფილიალი წარმატებით შეიცვალა.', 'wbim' ) ) );
    }

    /**
     * AJAX: Change item branch
     */
    public function ajax_change_item_branch() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $allocation_id = isset( $_POST['allocation_id'] ) ? absint( $_POST['allocation_id'] ) : 0;
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $allocation_id || ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი პარამეტრები.', 'wbim' ) ) );
        }

        // Get current allocation
        $allocation = WBIM_Order_Allocation::get_by_id( $allocation_id );
        if ( ! $allocation ) {
            wp_send_json_error( array( 'message' => __( 'მინიჭება ვერ მოიძებნა.', 'wbim' ) ) );
        }

        // Check if stock was deducted
        $stock_deducted = WBIM_Order_Allocation::is_stock_deducted( $allocation->order_id );

        if ( $stock_deducted ) {
            // Need to transfer stock between branches
            $old_branch_id = $allocation->branch_id;

            // Return stock to old branch
            $stock_entry = WBIM_Stock::get( $allocation->product_id, $old_branch_id, $allocation->variation_id );
            if ( $stock_entry ) {
                WBIM_Stock::update( $stock_entry->id, array(
                    'quantity' => $stock_entry->quantity + $allocation->quantity,
                ) );
            }

            // Deduct from new branch
            $new_stock = WBIM_Stock::get( $allocation->product_id, $branch_id, $allocation->variation_id );
            if ( $new_stock ) {
                WBIM_Stock::update( $new_stock->id, array(
                    'quantity' => max( 0, $new_stock->quantity - $allocation->quantity ),
                ) );
            }
        }

        // Update allocation
        $result = WBIM_Order_Allocation::update( $allocation_id, $branch_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Add order note
        $order = wc_get_order( $order_id );
        $branch = WBIM_Branch::get_by_id( $branch_id );
        $product = wc_get_product( $allocation->variation_id ? $allocation->variation_id : $allocation->product_id );

        if ( $order && $branch && $product ) {
            $order->add_order_note(
                sprintf(
                    __( 'პროდუქტის ფილიალი შეიცვალა: %s - %s', 'wbim' ),
                    $product->get_name(),
                    $branch->name
                )
            );
        }

        wp_send_json_success( array( 'message' => __( 'ფილიალი წარმატებით შეიცვალა.', 'wbim' ) ) );
    }

    /**
     * AJAX: Manually deduct stock
     */
    public function ajax_manually_deduct_stock() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი შეკვეთა.', 'wbim' ) ) );
        }

        $order_handler = new WBIM_Order_Handler();
        $result = $order_handler->deduct_order_stock( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'მარაგი წარმატებით ჩამოიჭრა.', 'wbim' ) ) );
    }

    /**
     * AJAX: Manually return stock
     */
    public function ajax_manually_return_stock() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი შეკვეთა.', 'wbim' ) ) );
        }

        $order_handler = new WBIM_Order_Handler();
        $result = $order_handler->return_order_stock( $order_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'მარაგი წარმატებით დაბრუნდა.', 'wbim' ) ) );
    }

    /**
     * Add branch filter to orders list (Legacy)
     *
     * @param string $post_type Post type.
     */
    public function add_branch_filter( $post_type ) {
        if ( 'shop_order' !== $post_type ) {
            return;
        }

        $this->render_branch_filter();
    }

    /**
     * Add branch filter to orders list (HPOS)
     *
     * @param string $order_type Order type.
     */
    public function add_branch_filter_hpos( $order_type ) {
        if ( 'shop_order' !== $order_type ) {
            return;
        }

        $this->render_branch_filter();
    }

    /**
     * Render branch filter dropdown
     */
    private function render_branch_filter() {
        $branches = WBIM_Branch::get_all();
        $selected = isset( $_GET['wbim_branch'] ) ? absint( $_GET['wbim_branch'] ) : 0;
        ?>
        <select name="wbim_branch" id="wbim_branch_filter">
            <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
            <option value="0" <?php selected( $selected, -1 ); ?>><?php esc_html_e( 'მინიჭების გარეშე', 'wbim' ); ?></option>
            <?php foreach ( $branches as $branch ) : ?>
                <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $selected, $branch->id ); ?>>
                    <?php echo esc_html( $branch->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Filter orders by branch (Legacy)
     *
     * @param array $query_vars Query vars.
     * @return array
     */
    public function filter_orders_by_branch( $query_vars ) {
        global $typenow;

        if ( 'shop_order' !== $typenow ) {
            return $query_vars;
        }

        if ( ! isset( $_GET['wbim_branch'] ) || '' === $_GET['wbim_branch'] ) {
            return $query_vars;
        }

        $branch_filter = intval( $_GET['wbim_branch'] );

        if ( -1 === $branch_filter ) {
            // No branch assigned
            $query_vars['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_wbim_branch_id',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_wbim_branch_id',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        } else {
            $query_vars['meta_key'] = '_wbim_branch_id';
            $query_vars['meta_value'] = $branch_filter;
        }

        return $query_vars;
    }

    /**
     * Filter orders by branch (HPOS)
     *
     * @param array $args Query args.
     * @return array
     */
    public function filter_orders_by_branch_hpos( $args ) {
        if ( ! isset( $_GET['wbim_branch'] ) || '' === $_GET['wbim_branch'] ) {
            return $args;
        }

        $branch_filter = intval( $_GET['wbim_branch'] );

        if ( -1 === $branch_filter ) {
            // No branch assigned - this requires custom query
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_wbim_branch_id',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_wbim_branch_id',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        } else {
            $args['meta_query'][] = array(
                'key'   => '_wbim_branch_id',
                'value' => $branch_filter,
            );
        }

        return $args;
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_scripts( $hook ) {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Check if we're on orders page
        $is_orders_page = in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true )
            || strpos( $screen->id, 'shop_order' ) !== false;

        if ( ! $is_orders_page ) {
            return;
        }

        wp_enqueue_script(
            'wbim-admin-orders',
            WBIM_PLUGIN_URL . 'admin/js/wbim-admin-orders.js',
            array( 'jquery' ),
            WBIM_VERSION,
            true
        );

        wp_localize_script( 'wbim-admin-orders', 'wbim_admin_orders', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wbim_admin_nonce' ),
            'i18n'     => array(
                'confirm_deduct'  => __( 'დარწმუნებული ხართ, რომ გსურთ მარაგის ჩამოჭრა?', 'wbim' ),
                'confirm_return'  => __( 'დარწმუნებული ხართ, რომ გსურთ მარაგის დაბრუნება?', 'wbim' ),
                'processing'      => __( 'მუშავდება...', 'wbim' ),
                'success'         => __( 'წარმატებით შესრულდა!', 'wbim' ),
                'error'           => __( 'შეცდომა!', 'wbim' ),
            ),
        ) );
    }
}
