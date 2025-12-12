<?php
/**
 * Admin Transfers Class
 *
 * Handles transfer management in admin.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Transfers class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Transfers {

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
        // AJAX handlers
        add_action( 'wp_ajax_wbim_create_transfer', array( $this, 'ajax_create_transfer' ) );
        add_action( 'wp_ajax_wbim_update_transfer_status', array( $this, 'ajax_update_transfer_status' ) );
        add_action( 'wp_ajax_wbim_add_transfer_item', array( $this, 'ajax_add_transfer_item' ) );
        add_action( 'wp_ajax_wbim_update_transfer_item', array( $this, 'ajax_update_transfer_item' ) );
        add_action( 'wp_ajax_wbim_remove_transfer_item', array( $this, 'ajax_remove_transfer_item' ) );
        add_action( 'wp_ajax_wbim_search_products', array( $this, 'ajax_search_products' ) );
        add_action( 'wp_ajax_wbim_delete_transfer', array( $this, 'ajax_delete_transfer' ) );
        add_action( 'wp_ajax_wbim_get_transfer_details', array( $this, 'ajax_get_transfer_details' ) );
    }

    /**
     * AJAX: Create new transfer
     */
    public function ajax_create_transfer() {
        // Verify nonce
        if ( ! check_ajax_referer( 'wbim_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'უსაფრთხოების შემოწმება ვერ მოხერხდა. გთხოვთ განაახლოთ გვერდი.', 'wbim' ) ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $source_branch = isset( $_POST['source_branch'] ) ? absint( $_POST['source_branch'] ) : 0;
        $destination_branch = isset( $_POST['destination_branch'] ) ? absint( $_POST['destination_branch'] ) : 0;
        $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

        if ( ! $source_branch || ! $destination_branch ) {
            wp_send_json_error( array( 'message' => __( 'აირჩიეთ წყარო და დანიშნულების ფილიალები.', 'wbim' ) ) );
        }

        if ( $source_branch === $destination_branch ) {
            wp_send_json_error( array( 'message' => __( 'წყარო და დანიშნულება არ შეიძლება იყოს ერთი და იგივე.', 'wbim' ) ) );
        }

        // Check user has access to source branch
        if ( ! WBIM_Transfer::user_can_manage_branch( $source_branch ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ წვდომა ამ ფილიალზე.', 'wbim' ) ) );
        }

        $transfer_id = WBIM_Transfer::create( array(
            'source_branch_id'      => $source_branch,
            'destination_branch_id' => $destination_branch,
            'notes'                 => $notes,
        ) );

        if ( is_wp_error( $transfer_id ) ) {
            wp_send_json_error( array( 'message' => $transfer_id->get_error_message() ) );
        }

        if ( ! $transfer_id ) {
            global $wpdb;
            $db_error = $wpdb->last_error;
            $error_msg = __( 'გადატანის შექმნა ვერ მოხერხდა.', 'wbim' );
            if ( $db_error ) {
                $error_msg .= ' DB: ' . $db_error;
            }
            wp_send_json_error( array( 'message' => $error_msg ) );
        }

        wp_send_json_success( array(
            'message'     => __( 'გადატანა შექმნილია.', 'wbim' ),
            'transfer_id' => $transfer_id,
            'redirect'    => admin_url( 'admin.php?page=wbim-transfers&action=edit&id=' . $transfer_id ),
        ) );
    }

    /**
     * AJAX: Update transfer status
     */
    public function ajax_update_transfer_status() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $transfer_id = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

        if ( ! $transfer_id || ! $new_status ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $transfer_id ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება ამ გადატანის მართვაზე.', 'wbim' ) ) );
        }

        // If moving to pending, validate stock
        if ( $new_status === WBIM_Transfer::STATUS_PENDING ) {
            $transfer = WBIM_Transfer::get_by_id( $transfer_id );
            $validation = WBIM_Transfer_Item::validate_stock( $transfer_id, $transfer->source_branch_id );

            if ( ! $validation['valid'] ) {
                wp_send_json_error( array(
                    'message' => __( 'არასაკმარისი მარაგი:', 'wbim' ),
                    'errors'  => $validation['errors'],
                ) );
            }

            // Check if there are items
            $item_count = WBIM_Transfer_Item::get_count( $transfer_id );
            if ( $item_count === 0 ) {
                wp_send_json_error( array( 'message' => __( 'დაამატეთ პროდუქტები გადატანის გასაგზავნად.', 'wbim' ) ) );
            }
        }

        $result = WBIM_Transfer::update_status( $transfer_id, $new_status );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );

        wp_send_json_success( array(
            'message'    => __( 'სტატუსი განახლდა.', 'wbim' ),
            'new_status' => $transfer->status,
            'status_label' => WBIM_Transfer::get_status_label( $transfer->status ),
        ) );
    }

    /**
     * AJAX: Add item to transfer
     */
    public function ajax_add_transfer_item() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $transfer_id = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        $quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

        if ( ! $transfer_id || ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $transfer_id ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $item_id = WBIM_Transfer_Item::add( $transfer_id, $product_id, $variation_id, $quantity );

        if ( ! $item_id ) {
            wp_send_json_error( array( 'message' => __( 'პროდუქტის დამატება ვერ მოხერხდა.', 'wbim' ) ) );
        }

        $item = WBIM_Transfer_Item::get_with_product( $item_id );

        wp_send_json_success( array(
            'message' => __( 'პროდუქტი დაემატა.', 'wbim' ),
            'item'    => $this->format_item_for_response( $item ),
        ) );
    }

    /**
     * AJAX: Update transfer item quantity
     */
    public function ajax_update_transfer_item() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

        if ( ! $item_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        $item = WBIM_Transfer_Item::get_by_id( $item_id );
        if ( ! $item ) {
            wp_send_json_error( array( 'message' => __( 'პროდუქტი ვერ მოიძებნა.', 'wbim' ) ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $item->transfer_id ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $result = WBIM_Transfer_Item::update( $item_id, $quantity );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'განახლება ვერ მოხერხდა.', 'wbim' ) ) );
        }

        wp_send_json_success( array(
            'message'  => __( 'რაოდენობა განახლდა.', 'wbim' ),
            'quantity' => $quantity,
        ) );
    }

    /**
     * AJAX: Remove item from transfer
     */
    public function ajax_remove_transfer_item() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

        if ( ! $item_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        $item = WBIM_Transfer_Item::get_by_id( $item_id );
        if ( ! $item ) {
            wp_send_json_error( array( 'message' => __( 'პროდუქტი ვერ მოიძებნა.', 'wbim' ) ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $item->transfer_id ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $result = WBIM_Transfer_Item::delete( $item_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'წაშლა ვერ მოხერხდა.', 'wbim' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'პროდუქტი წაიშალა.', 'wbim' ),
        ) );
    }

    /**
     * AJAX: Search products for transfer
     */
    public function ajax_search_products() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( array( 'products' => array() ) );
        }

        $args = array(
            'status'  => 'publish',
            'limit'   => 20,
            's'       => $search,
            'orderby' => 'title',
            'order'   => 'ASC',
        );

        $products = wc_get_products( $args );
        $results = array();

        foreach ( $products as $product ) {
            if ( $product->is_type( 'variable' ) ) {
                // Get variations
                $variations = $product->get_available_variations();
                foreach ( $variations as $variation_data ) {
                    $variation = wc_get_product( $variation_data['variation_id'] );
                    if ( ! $variation ) {
                        continue;
                    }

                    $stock = $branch_id ? WBIM_Stock::get( $product->get_id(), $branch_id, $variation->get_id() ) : null;
                    $stock_qty = $stock ? $stock->quantity : 0;

                    $results[] = array(
                        'product_id'   => $product->get_id(),
                        'variation_id' => $variation->get_id(),
                        'name'         => $product->get_name() . ' - ' . $variation->get_attribute_summary(),
                        'sku'          => $variation->get_sku(),
                        'image'        => wp_get_attachment_image_url( $variation->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                        'stock'        => $stock_qty,
                    );
                }
            } else {
                $stock = $branch_id ? WBIM_Stock::get( $product->get_id(), $branch_id ) : null;
                $stock_qty = $stock ? $stock->quantity : 0;

                $results[] = array(
                    'product_id'   => $product->get_id(),
                    'variation_id' => 0,
                    'name'         => $product->get_name(),
                    'sku'          => $product->get_sku(),
                    'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                    'stock'        => $stock_qty,
                );
            }
        }

        wp_send_json_success( array( 'products' => $results ) );
    }

    /**
     * AJAX: Delete transfer
     */
    public function ajax_delete_transfer() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $transfer_id = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;

        if ( ! $transfer_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $transfer_id ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );
        if ( ! $transfer ) {
            wp_send_json_error( array( 'message' => __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) ) );
        }

        // Only draft transfers can be deleted
        if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT ) {
            wp_send_json_error( array( 'message' => __( 'მხოლოდ დრაფტი გადატანების წაშლა შეიძლება.', 'wbim' ) ) );
        }

        $result = WBIM_Transfer::delete( $transfer_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'წაშლა ვერ მოხერხდა.', 'wbim' ) ) );
        }

        wp_send_json_success( array(
            'message'  => __( 'გადატანა წაიშალა.', 'wbim' ),
            'redirect' => admin_url( 'admin.php?page=wbim-transfers' ),
        ) );
    }

    /**
     * AJAX: Get transfer details
     */
    public function ajax_get_transfer_details() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_manage_transfers' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $transfer_id = isset( $_POST['transfer_id'] ) ? absint( $_POST['transfer_id'] ) : 0;

        if ( ! $transfer_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი მონაცემები.', 'wbim' ) ) );
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );
        if ( ! $transfer ) {
            wp_send_json_error( array( 'message' => __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) ) );
        }

        $items = WBIM_Transfer_Item::get_by_transfer_with_products( $transfer_id );
        $formatted_items = array();
        foreach ( $items as $item ) {
            $formatted_items[] = $this->format_item_for_response( $item );
        }

        wp_send_json_success( array(
            'transfer' => array(
                'id'                    => $transfer->id,
                'transfer_number'       => $transfer->transfer_number,
                'source_branch_id'      => $transfer->source_branch_id,
                'source_branch_name'    => $transfer->source_branch_name,
                'destination_branch_id' => $transfer->destination_branch_id,
                'destination_branch_name' => $transfer->destination_branch_name,
                'status'                => $transfer->status,
                'status_label'          => WBIM_Transfer::get_status_label( $transfer->status ),
                'notes'                 => $transfer->notes,
                'created_at'            => $transfer->created_at,
                'created_by_name'       => $transfer->created_by_name,
            ),
            'items' => $formatted_items,
        ) );
    }

    /**
     * Format item for JSON response
     *
     * @param object $item Transfer item.
     * @return array Formatted item data.
     */
    private function format_item_for_response( $item ) {
        return array(
            'id'           => $item->id,
            'product_id'   => $item->product_id,
            'variation_id' => $item->variation_id,
            'product_name' => $item->product_name,
            'sku'          => $item->sku,
            'quantity'     => $item->quantity,
            'image'        => isset( $item->product_image ) ? $item->product_image : '',
            'product_url'  => isset( $item->product_url ) ? $item->product_url : '',
        );
    }

    /**
     * Render transfers list page
     *
     * @return void
     */
    public static function render_list() {
        // Get filters
        $status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
        $branch_id = isset( $_GET['branch'] ) ? absint( $_GET['branch'] ) : 0;
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page = 20;

        $args = array(
            'limit'  => $per_page,
            'offset' => ( $paged - 1 ) * $per_page,
        );

        if ( $status ) {
            $args['status'] = $status;
        }

        if ( $branch_id ) {
            $args['source_branch_id'] = $branch_id;
        }

        // Branch managers only see their branch transfers
        if ( ! current_user_can( 'manage_woocommerce' ) && current_user_can( 'wbim_manage_transfers' ) ) {
            $user_branches = get_user_meta( get_current_user_id(), 'wbim_assigned_branches', true );
            if ( ! empty( $user_branches ) ) {
                $args['user_branch_filter'] = $user_branches;
            }
        }

        $transfers = WBIM_Transfer::get_all( $args );
        $total_count = WBIM_Transfer::get_count( $args );
        $total_pages = ceil( $total_count / $per_page );

        $branches = WBIM_Branch::get_active();
        $statuses = WBIM_Transfer::get_all_statuses();

        include WBIM_PLUGIN_DIR . 'admin/views/transfers/list.php';
    }

    /**
     * Render transfer edit/view page
     *
     * @return void
     */
    public static function render_edit() {
        $transfer_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( ! $transfer_id ) {
            wp_die( __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );

        if ( ! $transfer ) {
            wp_die( __( 'გადატანა ვერ მოიძებნა.', 'wbim' ) );
        }

        // Check permission
        if ( ! WBIM_Transfer::user_can_manage( $transfer_id ) ) {
            wp_die( __( 'არ გაქვთ უფლება ამ გადატანის სანახავად.', 'wbim' ) );
        }

        $items = WBIM_Transfer_Item::get_by_transfer_with_products( $transfer_id );
        $valid_transitions = WBIM_Transfer::get_valid_transitions( $transfer->status );
        $is_editable = $transfer->status === WBIM_Transfer::STATUS_DRAFT;

        include WBIM_PLUGIN_DIR . 'admin/views/transfers/edit.php';
    }

    /**
     * Render new transfer page
     *
     * @return void
     */
    public static function render_new() {
        $branches = WBIM_Branch::get_active();

        // Filter branches for branch managers
        if ( ! current_user_can( 'manage_woocommerce' ) && current_user_can( 'wbim_manage_transfers' ) ) {
            $user_branches = get_user_meta( get_current_user_id(), 'wbim_assigned_branches', true );
            if ( ! empty( $user_branches ) ) {
                $branches = array_filter( $branches, function( $branch ) use ( $user_branches ) {
                    return in_array( $branch->id, $user_branches );
                } );
            }
        }

        include WBIM_PLUGIN_DIR . 'admin/views/transfers/new.php';
    }
}
