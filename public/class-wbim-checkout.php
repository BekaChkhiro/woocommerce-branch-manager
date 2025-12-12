<?php
/**
 * Checkout Integration
 *
 * Handles branch selection at checkout.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checkout integration class
 *
 * @since 1.0.0
 */
class WBIM_Checkout {

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
        // Add branch selector to checkout
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_branch_selector' ), 5 );

        // Validate branch selection
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_branch_selection' ) );

        // Save branch selection to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_branch_to_order' ), 10, 2 );

        // Process order allocation after order is created
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order_allocation' ), 10, 3 );

        // Add branch info to order confirmation
        add_action( 'woocommerce_thankyou', array( $this, 'display_branch_on_thankyou' ), 5 );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_get_branch_stock', array( $this, 'ajax_get_branch_stock' ) );
        add_action( 'wp_ajax_nopriv_wbim_get_branch_stock', array( $this, 'ajax_get_branch_stock' ) );
        add_action( 'wp_ajax_wbim_check_cart_availability', array( $this, 'ajax_check_cart_availability' ) );
        add_action( 'wp_ajax_nopriv_wbim_check_cart_availability', array( $this, 'ajax_check_cart_availability' ) );
        add_action( 'wp_ajax_wbim_set_customer_location', array( $this, 'ajax_set_customer_location' ) );
        add_action( 'wp_ajax_nopriv_wbim_set_customer_location', array( $this, 'ajax_set_customer_location' ) );
    }

    /**
     * Display branch selector on checkout
     */
    public function display_branch_selector() {
        $settings = get_option( 'wbim_settings', array() );

        // Check if branch selection is enabled at checkout
        if ( empty( $settings['enable_checkout_selection'] ) || 'yes' !== $settings['enable_checkout_selection'] ) {
            return;
        }

        $selector_type = isset( $settings['branch_selector_type'] ) ? $settings['branch_selector_type'] : 'dropdown';
        $auto_select = isset( $settings['auto_select_method'] ) ? $settings['auto_select_method'] : 'most_stock';

        // Get branches that can fulfill the cart
        $cart_items = WC()->cart->get_cart();
        $fulfillment_options = $this->get_cart_fulfillment_branches( $cart_items );

        // Get customer location if available
        $customer_location = $this->get_customer_location();

        // Get selected branch (from session or auto-select)
        $selected_branch = WC()->session->get( 'wbim_selected_branch' );

        if ( ! $selected_branch && ! empty( $fulfillment_options ) ) {
            $selected_branch = $this->auto_select_branch( $fulfillment_options, $auto_select, $customer_location );
            if ( $selected_branch ) {
                WC()->session->set( 'wbim_selected_branch', $selected_branch );
            }
        }

        // Prepare data for template
        $template_data = array(
            'branches'          => $fulfillment_options,
            'selected_branch'   => $selected_branch,
            'customer_location' => $customer_location,
            'show_stock'        => ! empty( $settings['show_stock_at_checkout'] ) && 'yes' === $settings['show_stock_at_checkout'],
            'required'          => ! empty( $settings['require_branch_selection'] ) && 'yes' === $settings['require_branch_selection'],
        );

        // Load appropriate template
        switch ( $selector_type ) {
            case 'radio':
                $this->load_template( 'checkout/branch-selector-radio.php', $template_data );
                break;
            case 'map':
                $this->load_template( 'checkout/branch-selector-map.php', $template_data );
                break;
            default:
                $this->load_template( 'checkout/branch-selector-dropdown.php', $template_data );
                break;
        }
    }

    /**
     * Get branches that can fulfill the cart
     *
     * @param array $cart_items Cart items.
     * @return array
     */
    private function get_cart_fulfillment_branches( $cart_items ) {
        $branches = WBIM_Branch::get_active();
        $fulfillment_branches = array();

        foreach ( $branches as $branch ) {
            $can_fulfill = true;
            $branch_stock_info = array();

            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $product_id = $cart_item['product_id'];
                $variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
                $quantity = $cart_item['quantity'];

                $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
                $available = $stock ? $stock->quantity : 0;

                $branch_stock_info[ $cart_item_key ] = array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'needed'       => $quantity,
                    'available'    => $available,
                    'sufficient'   => $available >= $quantity,
                );

                if ( $available < $quantity ) {
                    $can_fulfill = false;
                }
            }

            $branch_data = array(
                'id'           => $branch->id,
                'name'         => $branch->name,
                'address'      => $branch->address,
                'phone'        => $branch->phone,
                'latitude'     => $branch->lat,
                'longitude'    => $branch->lng,
                'can_fulfill'  => $can_fulfill,
                'stock_info'   => $branch_stock_info,
            );

            // Calculate distance if customer location is available
            $customer_location = $this->get_customer_location();
            if ( $customer_location && $branch->lat && $branch->lng ) {
                $branch_data['distance'] = $this->calculate_distance(
                    $customer_location['lat'],
                    $customer_location['lng'],
                    $branch->lat,
                    $branch->lng
                );
            }

            $fulfillment_branches[] = $branch_data;
        }

        // Sort by can_fulfill first, then by distance if available
        usort( $fulfillment_branches, function( $a, $b ) {
            // Can fulfill comes first
            if ( $a['can_fulfill'] !== $b['can_fulfill'] ) {
                return $b['can_fulfill'] - $a['can_fulfill'];
            }
            // Then by distance
            if ( isset( $a['distance'] ) && isset( $b['distance'] ) ) {
                return $a['distance'] - $b['distance'];
            }
            return 0;
        } );

        return $fulfillment_branches;
    }

    /**
     * Auto select branch based on method
     *
     * @param array  $branches         Available branches.
     * @param string $method           Selection method.
     * @param array  $customer_location Customer location.
     * @return int|null
     */
    private function auto_select_branch( $branches, $method, $customer_location = null ) {
        if ( empty( $branches ) ) {
            return null;
        }

        // Filter to only branches that can fulfill
        $fulfillable = array_filter( $branches, function( $b ) {
            return $b['can_fulfill'];
        } );

        if ( empty( $fulfillable ) ) {
            $fulfillable = $branches; // Fallback to all if none can fulfill
        }

        switch ( $method ) {
            case 'nearest':
                if ( $customer_location ) {
                    usort( $fulfillable, function( $a, $b ) {
                        $dist_a = isset( $a['distance'] ) ? $a['distance'] : PHP_INT_MAX;
                        $dist_b = isset( $b['distance'] ) ? $b['distance'] : PHP_INT_MAX;
                        return $dist_a - $dist_b;
                    } );
                }
                return reset( $fulfillable )['id'];

            case 'most_stock':
                usort( $fulfillable, function( $a, $b ) {
                    $stock_a = array_sum( array_column( $a['stock_info'], 'available' ) );
                    $stock_b = array_sum( array_column( $b['stock_info'], 'available' ) );
                    return $stock_b - $stock_a;
                } );
                return reset( $fulfillable )['id'];

            case 'default':
                $settings = get_option( 'wbim_settings', array() );
                if ( ! empty( $settings['default_branch_id'] ) ) {
                    foreach ( $fulfillable as $branch ) {
                        if ( $branch['id'] == $settings['default_branch_id'] ) {
                            return $branch['id'];
                        }
                    }
                }
                return reset( $fulfillable )['id'];

            case 'first_available':
            default:
                return reset( $fulfillable )['id'];
        }
    }

    /**
     * Get customer location from session or geolocation
     *
     * @return array|null
     */
    private function get_customer_location() {
        // Check session first
        $location = WC()->session->get( 'wbim_customer_location' );
        if ( $location ) {
            return $location;
        }

        // Try to get from WooCommerce geolocation
        $geolocation = WC_Geolocation::geolocate_ip();
        if ( ! empty( $geolocation['country'] ) ) {
            // This gives country/state, not lat/lng - would need additional service
            return null;
        }

        return null;
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1 Latitude 1.
     * @param float $lng1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lng2 Longitude 2.
     * @return float Distance in kilometers.
     */
    private function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
        $earth_radius = 6371; // km

        $lat1_rad = deg2rad( $lat1 );
        $lat2_rad = deg2rad( $lat2 );
        $delta_lat = deg2rad( $lat2 - $lat1 );
        $delta_lng = deg2rad( $lng2 - $lng1 );

        $a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
             cos( $lat1_rad ) * cos( $lat2_rad ) *
             sin( $delta_lng / 2 ) * sin( $delta_lng / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Load template file
     *
     * @param string $template Template file name.
     * @param array  $data     Data to pass to template.
     */
    private function load_template( $template, $data = array() ) {
        extract( $data );

        $template_path = WBIM_PLUGIN_DIR . 'public/views/' . $template;

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Validate branch selection
     */
    public function validate_branch_selection() {
        $settings = get_option( 'wbim_settings', array() );

        if ( empty( $settings['enable_checkout_selection'] ) || 'yes' !== $settings['enable_checkout_selection'] ) {
            return;
        }

        $required = ! empty( $settings['require_branch_selection'] ) && 'yes' === $settings['require_branch_selection'];

        if ( $required && empty( $_POST['wbim_branch_id'] ) ) {
            wc_add_notice( __( 'გთხოვთ აირჩიოთ ფილიალი.', 'wbim' ), 'error' );
        }

        // Validate selected branch can fulfill the order
        if ( ! empty( $_POST['wbim_branch_id'] ) ) {
            $branch_id = absint( $_POST['wbim_branch_id'] );
            $cart_items = WC()->cart->get_cart();

            foreach ( $cart_items as $cart_item ) {
                $product_id = $cart_item['product_id'];
                $variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
                $quantity = $cart_item['quantity'];

                $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );
                $available = $stock ? $stock->quantity : 0;

                if ( $available < $quantity ) {
                    $product = wc_get_product( $variation_id ? $variation_id : $product_id );
                    wc_add_notice(
                        sprintf(
                            __( '%s - არასაკმარისი მარაგი არჩეულ ფილიალში. საჭიროა: %d, ხელმისაწვდომია: %d', 'wbim' ),
                            $product->get_name(),
                            $quantity,
                            $available
                        ),
                        'error'
                    );
                }
            }
        }
    }

    /**
     * Save branch selection to order
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_branch_to_order( $order, $data ) {
        if ( ! empty( $_POST['wbim_branch_id'] ) ) {
            $branch_id = absint( $_POST['wbim_branch_id'] );
            $order->update_meta_data( '_wbim_branch_id', $branch_id );

            // Save branch name for easy reference
            $branch = WBIM_Branch::get_by_id( $branch_id );
            if ( $branch ) {
                $order->update_meta_data( '_wbim_branch_name', $branch->name );
            }
        }
    }

    /**
     * Process order allocation after order is created
     *
     * @param int      $order_id    Order ID.
     * @param array    $posted_data Posted data.
     * @param WC_Order $order       Order object.
     */
    public function process_order_allocation( $order_id, $posted_data, $order ) {
        $branch_id = $order->get_meta( '_wbim_branch_id' );

        if ( ! $branch_id ) {
            // Auto-select branch if not selected
            $settings = get_option( 'wbim_settings', array() );
            $auto_select = isset( $settings['auto_select_method'] ) ? $settings['auto_select_method'] : 'most_stock';

            $cart_items = array();
            foreach ( $order->get_items() as $item ) {
                $cart_items[] = array(
                    'product_id'   => $item->get_product_id(),
                    'variation_id' => $item->get_variation_id(),
                    'quantity'     => $item->get_quantity(),
                );
            }

            $fulfillment_options = $this->get_order_fulfillment_branches( $cart_items );
            $branch_id = $this->auto_select_branch( $fulfillment_options, $auto_select );

            if ( $branch_id ) {
                $order->update_meta_data( '_wbim_branch_id', $branch_id );
                $branch = WBIM_Branch::get_by_id( $branch_id );
                if ( $branch ) {
                    $order->update_meta_data( '_wbim_branch_name', $branch->name );
                }
                $order->save();
            }
        }

        if ( ! $branch_id ) {
            return;
        }

        // Create allocations for each order item
        foreach ( $order->get_items() as $item_id => $item ) {
            $allocation_data = array(
                'order_id'      => $order_id,
                'order_item_id' => $item_id,
                'product_id'    => $item->get_product_id(),
                'variation_id'  => $item->get_variation_id(),
                'branch_id'     => $branch_id,
                'quantity'      => $item->get_quantity(),
            );

            WBIM_Order_Allocation::create( $allocation_data );
        }

        // Add order note
        $branch = WBIM_Branch::get_by_id( $branch_id );
        if ( $branch ) {
            $order->add_order_note(
                sprintf(
                    __( 'შეკვეთა მინიჭებულია ფილიალზე: %s', 'wbim' ),
                    $branch->name
                )
            );
        }
    }

    /**
     * Get branches that can fulfill order items
     *
     * @param array $items Order items.
     * @return array
     */
    private function get_order_fulfillment_branches( $items ) {
        $branches = WBIM_Branch::get_active();
        $fulfillment_branches = array();

        foreach ( $branches as $branch ) {
            $can_fulfill = true;
            $branch_stock_info = array();

            foreach ( $items as $key => $item ) {
                $product_id = $item['product_id'];
                $variation_id = isset( $item['variation_id'] ) ? $item['variation_id'] : 0;
                $quantity = $item['quantity'];

                $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
                $available = $stock ? $stock->quantity : 0;

                $branch_stock_info[ $key ] = array(
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'needed'       => $quantity,
                    'available'    => $available,
                    'sufficient'   => $available >= $quantity,
                );

                if ( $available < $quantity ) {
                    $can_fulfill = false;
                }
            }

            $fulfillment_branches[] = array(
                'id'          => $branch->id,
                'name'        => $branch->name,
                'can_fulfill' => $can_fulfill,
                'stock_info'  => $branch_stock_info,
            );
        }

        return $fulfillment_branches;
    }

    /**
     * Display branch info on thank you page
     *
     * @param int $order_id Order ID.
     */
    public function display_branch_on_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $branch_id = $order->get_meta( '_wbim_branch_id' );
        if ( ! $branch_id ) {
            return;
        }

        $branch = WBIM_Branch::get_by_id( $branch_id );
        if ( ! $branch ) {
            return;
        }

        ?>
        <div class="wbim-order-branch">
            <h2><?php esc_html_e( 'ფილიალის ინფორმაცია', 'wbim' ); ?></h2>
            <table class="wbim-branch-details">
                <tr>
                    <th><?php esc_html_e( 'ფილიალი:', 'wbim' ); ?></th>
                    <td><?php echo esc_html( $branch->name ); ?></td>
                </tr>
                <?php if ( $branch->address ) : ?>
                <tr>
                    <th><?php esc_html_e( 'მისამართი:', 'wbim' ); ?></th>
                    <td><?php echo esc_html( $branch->address ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $branch->phone ) : ?>
                <tr>
                    <th><?php esc_html_e( 'ტელეფონი:', 'wbim' ); ?></th>
                    <td><?php echo esc_html( $branch->phone ); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX: Get branch stock for products
     */
    public function ajax_get_branch_stock() {
        check_ajax_referer( 'wbim_checkout_nonce', 'nonce' );

        $branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

        if ( ! $branch_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი ფილიალი.', 'wbim' ) ) );
        }

        $cart_items = WC()->cart->get_cart();
        $stock_info = array();

        foreach ( $cart_items as $cart_item_key => $cart_item ) {
            $product_id = $cart_item['product_id'];
            $variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
            $quantity = $cart_item['quantity'];

            $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            $stock_info[ $cart_item_key ] = array(
                'available'  => $available,
                'needed'     => $quantity,
                'sufficient' => $available >= $quantity,
            );
        }

        wp_send_json_success( array( 'stock_info' => $stock_info ) );
    }

    /**
     * AJAX: Check cart availability across branches
     */
    public function ajax_check_cart_availability() {
        check_ajax_referer( 'wbim_checkout_nonce', 'nonce' );

        $cart_items = WC()->cart->get_cart();
        $fulfillment_options = $this->get_cart_fulfillment_branches( $cart_items );

        wp_send_json_success( array( 'branches' => $fulfillment_options ) );
    }

    /**
     * Set customer location (from AJAX/JavaScript geolocation)
     */
    public function ajax_set_customer_location() {
        check_ajax_referer( 'wbim_checkout_nonce', 'nonce' );

        $lat = isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : 0;
        $lng = isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : 0;

        if ( $lat && $lng ) {
            WC()->session->set( 'wbim_customer_location', array(
                'lat' => $lat,
                'lng' => $lng,
            ) );
            wp_send_json_success();
        }

        wp_send_json_error();
    }
}
