<?php
/**
 * REST API Class
 *
 * Handles REST API endpoints for the plugin.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API class
 *
 * @since 1.0.0
 */
class WBIM_REST_API {

    /**
     * API namespace
     *
     * @var string
     */
    const NAMESPACE = 'wbim/v1';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes() {
        // Check if API is enabled
        $settings = get_option( 'wbim_settings', array() );
        if ( isset( $settings['enable_api'] ) && ! $settings['enable_api'] ) {
            return;
        }

        // Branches endpoints
        register_rest_route(
            self::NAMESPACE,
            '/branches',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_branches' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_branch' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_branch_schema(),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/branches/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_branch' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_branch' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                    'args'                => $this->get_branch_schema(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_branch' ),
                    'permission_callback' => array( $this, 'check_manage_permission' ),
                ),
            )
        );

        // Stock endpoints
        register_rest_route(
            self::NAMESPACE,
            '/stock',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_stock' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                    'args'                => array(
                        'branch_id'   => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'product_id'  => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'category_id' => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'set_stock' ),
                    'permission_callback' => array( $this, 'check_stock_permission' ),
                    'args'                => $this->get_stock_schema(),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/stock/product/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_product_stock' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/stock/branch/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_branch_stock' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/stock/adjust',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'adjust_stock' ),
                'permission_callback' => array( $this, 'check_stock_permission' ),
                'args'                => array(
                    'product_id'   => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                    'variation_id' => array(
                        'type'    => 'integer',
                        'default' => 0,
                    ),
                    'branch_id'    => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                    'adjustment'   => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                    'note'         => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                ),
            )
        );

        // Transfers endpoints
        register_rest_route(
            self::NAMESPACE,
            '/transfers',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_transfers' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                    'args'                => array(
                        'from_branch_id' => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'to_branch_id'   => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'status'         => array(
                            'type'    => 'string',
                            'default' => '',
                        ),
                        'per_page'       => array(
                            'type'    => 'integer',
                            'default' => 20,
                        ),
                        'page'           => array(
                            'type'    => 'integer',
                            'default' => 1,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_transfer' ),
                    'permission_callback' => array( $this, 'check_transfer_permission' ),
                    'args'                => $this->get_transfer_schema(),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/transfers/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_transfer' ),
                    'permission_callback' => array( $this, 'check_read_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_transfer' ),
                    'permission_callback' => array( $this, 'check_transfer_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_transfer' ),
                    'permission_callback' => array( $this, 'check_transfer_permission' ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/transfers/(?P<id>\d+)/status',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_transfer_status' ),
                'permission_callback' => array( $this, 'check_transfer_permission' ),
                'args'                => array(
                    'status' => array(
                        'required' => true,
                        'type'     => 'string',
                        'enum'     => array( 'pending', 'in_transit', 'completed', 'cancelled' ),
                    ),
                ),
            )
        );

        // Reports endpoints
        register_rest_route(
            self::NAMESPACE,
            '/reports/stock',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_stock_report' ),
                'permission_callback' => array( $this, 'check_reports_permission' ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/reports/sales',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_sales_report' ),
                'permission_callback' => array( $this, 'check_reports_permission' ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/reports/low-stock',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_low_stock_report' ),
                'permission_callback' => array( $this, 'check_reports_permission' ),
            )
        );
    }

    /**
     * Check read permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_read_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'ავტორიზაცია საჭიროა.', 'wbim' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'wbim_view_branch_stock' );
    }

    /**
     * Check manage permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_manage_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'ავტორიზაცია საჭიროა.', 'wbim' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'wbim_manage_branches' );
    }

    /**
     * Check stock permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_stock_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'ავტორიზაცია საჭიროა.', 'wbim' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'wbim_manage_stock' );
    }

    /**
     * Check transfer permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_transfer_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'ავტორიზაცია საჭიროა.', 'wbim' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'wbim_manage_transfers' );
    }

    /**
     * Check reports permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_reports_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'ავტორიზაცია საჭიროა.', 'wbim' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'wbim_view_reports' );
    }

    // =========================================================================
    // BRANCHES
    // =========================================================================

    /**
     * Get branches
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_branches( $request ) {
        $args = array(
            'is_active' => $request->get_param( 'active' ),
        );

        $branches = WBIM_Branch::get_all( $args );

        return $this->success_response( $branches );
    }

    /**
     * Get single branch
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_branch( $request ) {
        $id = $request->get_param( 'id' );
        $branch = WBIM_Branch::get_by_id( $id );

        if ( ! $branch ) {
            return $this->error_response( 'not_found', __( 'ფილიალი ვერ მოიძებნა.', 'wbim' ), 404 );
        }

        return $this->success_response( $branch );
    }

    /**
     * Create branch
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_branch( $request ) {
        $data = array(
            'name'       => $request->get_param( 'name' ),
            'address'    => $request->get_param( 'address' ),
            'city'       => $request->get_param( 'city' ),
            'phone'      => $request->get_param( 'phone' ),
            'email'      => $request->get_param( 'email' ),
            'manager_id' => $request->get_param( 'manager_id' ),
            'lat'        => $request->get_param( 'lat' ),
            'lng'        => $request->get_param( 'lng' ),
            'is_active'  => $request->get_param( 'is_active' ),
        );

        $result = WBIM_Branch::create( $data );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        $branch = WBIM_Branch::get_by_id( $result );

        return $this->success_response( $branch, 201 );
    }

    /**
     * Update branch
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_branch( $request ) {
        $id = $request->get_param( 'id' );

        $data = array_filter( array(
            'name'       => $request->get_param( 'name' ),
            'address'    => $request->get_param( 'address' ),
            'city'       => $request->get_param( 'city' ),
            'phone'      => $request->get_param( 'phone' ),
            'email'      => $request->get_param( 'email' ),
            'manager_id' => $request->get_param( 'manager_id' ),
            'lat'        => $request->get_param( 'lat' ),
            'lng'        => $request->get_param( 'lng' ),
            'is_active'  => $request->get_param( 'is_active' ),
        ), function( $value ) {
            return $value !== null;
        } );

        $result = WBIM_Branch::update( $id, $data );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        $branch = WBIM_Branch::get_by_id( $id );

        return $this->success_response( $branch );
    }

    /**
     * Delete branch
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_branch( $request ) {
        $id = $request->get_param( 'id' );
        $force = $request->get_param( 'force' );

        $result = WBIM_Branch::delete( $id, $force );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        return $this->success_response( array( 'deleted' => true ) );
    }

    // =========================================================================
    // STOCK
    // =========================================================================

    /**
     * Get stock
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_stock( $request ) {
        $args = array(
            'branch_id'   => $request->get_param( 'branch_id' ),
            'category_id' => $request->get_param( 'category_id' ),
        );

        $stock = WBIM_Stock::get_all( $args );

        return $this->success_response( $stock );
    }

    /**
     * Get product stock
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_product_stock( $request ) {
        $product_id = $request->get_param( 'id' );
        $variation_id = $request->get_param( 'variation_id' ) ?: 0;

        $stock = WBIM_Stock::get_product_stock( $product_id, $variation_id );

        return $this->success_response( $stock );
    }

    /**
     * Get branch stock
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_branch_stock( $request ) {
        $branch_id = $request->get_param( 'id' );
        $args = array(
            'limit'  => $request->get_param( 'per_page' ) ?: 50,
            'offset' => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * 50,
        );

        $stock = WBIM_Stock::get_branch_stock( $branch_id, $args );

        return $this->success_response( $stock );
    }

    /**
     * Set stock
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function set_stock( $request ) {
        $product_id = $request->get_param( 'product_id' );
        $variation_id = $request->get_param( 'variation_id' ) ?: 0;
        $branch_id = $request->get_param( 'branch_id' );
        $quantity = $request->get_param( 'quantity' );

        $result = WBIM_Stock::set( $product_id, $variation_id, $branch_id, array(
            'quantity' => $quantity,
            'note'     => __( 'API-ით განახლებული', 'wbim' ),
        ) );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        // Sync WC stock
        WBIM_Stock::sync_wc_stock( $product_id, $variation_id );

        $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );

        return $this->success_response( $stock );
    }

    /**
     * Adjust stock
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function adjust_stock( $request ) {
        $product_id = $request->get_param( 'product_id' );
        $variation_id = $request->get_param( 'variation_id' ) ?: 0;
        $branch_id = $request->get_param( 'branch_id' );
        $adjustment = $request->get_param( 'adjustment' );
        $note = $request->get_param( 'note' );

        $result = WBIM_Stock::adjust( $product_id, $variation_id, $branch_id, $adjustment, 'adjustment', null, $note ?: __( 'API-ით კორექტირებული', 'wbim' ) );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        // Sync WC stock
        WBIM_Stock::sync_wc_stock( $product_id, $variation_id );

        $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );

        return $this->success_response( $stock );
    }

    // =========================================================================
    // TRANSFERS
    // =========================================================================

    /**
     * Get transfers
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_transfers( $request ) {
        $args = array(
            'source_branch_id'      => $request->get_param( 'from_branch_id' ),
            'destination_branch_id' => $request->get_param( 'to_branch_id' ),
            'status'                => $request->get_param( 'status' ),
            'limit'                 => $request->get_param( 'per_page' ),
            'offset'                => ( $request->get_param( 'page' ) - 1 ) * $request->get_param( 'per_page' ),
        );

        $transfers = WBIM_Transfer::get_all( $args );
        $total = WBIM_Transfer::get_count( array( 'status' => $request->get_param( 'status' ) ) );

        return $this->success_response( $transfers, 200, array(
            'total'    => $total,
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
        ) );
    }

    /**
     * Get single transfer
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_transfer( $request ) {
        $id = $request->get_param( 'id' );
        $transfer = WBIM_Transfer::get_by_id( $id );

        if ( ! $transfer ) {
            return $this->error_response( 'not_found', __( 'გადატანა ვერ მოიძებნა.', 'wbim' ), 404 );
        }

        // Get items
        $items = WBIM_Transfer_Item::get_by_transfer( $id );
        $transfer->items = $items;

        return $this->success_response( $transfer );
    }

    /**
     * Create transfer
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_transfer( $request ) {
        $data = array(
            'source_branch_id'      => $request->get_param( 'from_branch_id' ),
            'destination_branch_id' => $request->get_param( 'to_branch_id' ),
            'notes'                 => $request->get_param( 'notes' ),
        );

        $transfer_id = WBIM_Transfer::create( $data );

        if ( is_wp_error( $transfer_id ) ) {
            return $this->error_response( $transfer_id->get_error_code(), $transfer_id->get_error_message() );
        }

        // Add items
        $items = $request->get_param( 'items' );
        if ( ! empty( $items ) && is_array( $items ) ) {
            foreach ( $items as $item ) {
                WBIM_Transfer_Item::create( array(
                    'transfer_id'  => $transfer_id,
                    'product_id'   => $item['product_id'],
                    'variation_id' => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
                    'quantity'     => $item['quantity'],
                ) );
            }
        }

        $transfer = WBIM_Transfer::get_by_id( $transfer_id );
        $transfer->items = WBIM_Transfer_Item::get_by_transfer( $transfer_id );

        return $this->success_response( $transfer, 201 );
    }

    /**
     * Update transfer
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_transfer( $request ) {
        $id = $request->get_param( 'id' );

        $data = array_filter( array(
            'notes' => $request->get_param( 'notes' ),
        ), function( $value ) {
            return $value !== null;
        } );

        if ( ! empty( $data ) ) {
            $result = WBIM_Transfer::update( $id, $data );

            if ( is_wp_error( $result ) ) {
                return $this->error_response( $result->get_error_code(), $result->get_error_message() );
            }
        }

        $transfer = WBIM_Transfer::get_by_id( $id );
        $transfer->items = WBIM_Transfer_Item::get_by_transfer( $id );

        return $this->success_response( $transfer );
    }

    /**
     * Update transfer status
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_transfer_status( $request ) {
        $id = $request->get_param( 'id' );
        $status = $request->get_param( 'status' );

        $result = WBIM_Transfer::update_status( $id, $status );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        $transfer = WBIM_Transfer::get_by_id( $id );
        $transfer->items = WBIM_Transfer_Item::get_by_transfer( $id );

        return $this->success_response( $transfer );
    }

    /**
     * Delete transfer
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_transfer( $request ) {
        $id = $request->get_param( 'id' );

        $result = WBIM_Transfer::delete( $id );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message() );
        }

        return $this->success_response( array( 'deleted' => true ) );
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    /**
     * Get stock report
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_stock_report( $request ) {
        $args = array(
            'branch_id'   => $request->get_param( 'branch_id' ),
            'category_id' => $request->get_param( 'category_id' ),
        );

        $report = WBIM_Reports::get_stock_report( $args );

        return $this->success_response( $report );
    }

    /**
     * Get sales report
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_sales_report( $request ) {
        $args = array(
            'branch_id' => $request->get_param( 'branch_id' ),
            'date_from' => $request->get_param( 'date_from' ),
            'date_to'   => $request->get_param( 'date_to' ),
        );

        $report = WBIM_Reports::get_sales_report( $args );

        return $this->success_response( $report );
    }

    /**
     * Get low stock report
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_low_stock_report( $request ) {
        $args = array(
            'branch_id' => $request->get_param( 'branch_id' ),
        );

        $report = WBIM_Reports::get_low_stock_report( $args );

        return $this->success_response( $report );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get branch schema
     *
     * @return array
     */
    private function get_branch_schema() {
        return array(
            'name'       => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'address'    => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'city'       => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'phone'      => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'email'      => array(
                'type'              => 'string',
                'format'            => 'email',
                'sanitize_callback' => 'sanitize_email',
            ),
            'manager_id' => array(
                'type' => 'integer',
            ),
            'lat'        => array(
                'type' => 'number',
            ),
            'lng'        => array(
                'type' => 'number',
            ),
            'is_active'  => array(
                'type'    => 'integer',
                'default' => 1,
            ),
        );
    }

    /**
     * Get stock schema
     *
     * @return array
     */
    private function get_stock_schema() {
        return array(
            'product_id'   => array(
                'type'     => 'integer',
                'required' => true,
            ),
            'variation_id' => array(
                'type'    => 'integer',
                'default' => 0,
            ),
            'branch_id'    => array(
                'type'     => 'integer',
                'required' => true,
            ),
            'quantity'     => array(
                'type'     => 'integer',
                'required' => true,
            ),
        );
    }

    /**
     * Get transfer schema
     *
     * @return array
     */
    private function get_transfer_schema() {
        return array(
            'from_branch_id' => array(
                'type'     => 'integer',
                'required' => true,
            ),
            'to_branch_id'   => array(
                'type'     => 'integer',
                'required' => true,
            ),
            'items'          => array(
                'type'     => 'array',
                'required' => true,
                'items'    => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id'   => array(
                            'type'     => 'integer',
                            'required' => true,
                        ),
                        'variation_id' => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'quantity'     => array(
                            'type'     => 'integer',
                            'required' => true,
                        ),
                    ),
                ),
            ),
            'notes'          => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
        );
    }

    /**
     * Success response
     *
     * @param mixed $data   Response data.
     * @param int   $status HTTP status code.
     * @param array $meta   Meta data.
     * @return WP_REST_Response
     */
    private function success_response( $data, $status = 200, $meta = array() ) {
        $response = array(
            'success' => true,
            'data'    => $data,
        );

        if ( ! empty( $meta ) ) {
            $response['meta'] = $meta;
        }

        return new WP_REST_Response( $response, $status );
    }

    /**
     * Error response
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @return WP_Error
     */
    private function error_response( $code, $message, $status = 400 ) {
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }
}
