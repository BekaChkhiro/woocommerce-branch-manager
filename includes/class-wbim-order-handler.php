<?php
/**
 * Order Handler
 *
 * Handles order-stock interactions including allocation, deduction, and return.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Order Handler class
 *
 * @since 1.0.0
 */
class WBIM_Order_Handler {

    /**
     * Instance
     *
     * @var WBIM_Order_Handler
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return WBIM_Order_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
        // Stock deduction based on settings
        $deduct_on = WBIM_Utils::get_setting( 'deduct_stock_on', 'processing' );

        switch ( $deduct_on ) {
            case 'completed':
                add_action( 'woocommerce_order_status_completed', array( $this, 'deduct_order_stock' ), 10, 1 );
                break;
            case 'paid':
                add_action( 'woocommerce_payment_complete', array( $this, 'deduct_order_stock' ), 10, 1 );
                break;
            case 'processing':
            default:
                add_action( 'woocommerce_order_status_processing', array( $this, 'deduct_order_stock' ), 10, 1 );
                break;
        }

        // Stock return on cancellation/refund
        $return_statuses = WBIM_Utils::get_setting( 'return_stock_on', array( 'cancelled', 'refunded', 'failed' ) );

        if ( in_array( 'cancelled', $return_statuses, true ) ) {
            add_action( 'woocommerce_order_status_cancelled', array( $this, 'return_order_stock' ), 10, 1 );
        }
        if ( in_array( 'refunded', $return_statuses, true ) ) {
            add_action( 'woocommerce_order_status_refunded', array( $this, 'return_order_stock' ), 10, 1 );
        }
        if ( in_array( 'failed', $return_statuses, true ) ) {
            add_action( 'woocommerce_order_status_failed', array( $this, 'return_order_stock' ), 10, 1 );
        }

        // Handle re-processing (cancelled -> processing)
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
    }

    /**
     * Deduct stock for order
     *
     * @param int $order_id Order ID.
     * @return bool|WP_Error
     */
    public function deduct_order_stock( $order_id ) {
        // Check if stock was already deducted
        if ( WBIM_Order_Allocation::is_stock_deducted( $order_id ) ) {
            return true;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Invalid order.', 'wbim' ) );
        }

        // Get selected branch from order meta
        $order_branch_id = $order->get_meta( '_wbim_branch_id' );

        $errors = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->managing_stock() ) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            // Check if allocation already exists
            $existing_allocation = WBIM_Order_Allocation::get_by_order_item( $item_id );

            if ( $existing_allocation ) {
                $branch_id = $existing_allocation->branch_id;
            } elseif ( $order_branch_id ) {
                $branch_id = $order_branch_id;
            } else {
                // Auto-select branch
                $branch_id = $this->auto_select_branch( $product_id, $variation_id, $quantity );
            }

            if ( ! $branch_id ) {
                $errors[] = sprintf(
                    __( 'No available branch for product: %s', 'wbim' ),
                    $product->get_name()
                );
                continue;
            }

            // Deduct stock from branch
            $result = WBIM_Stock::adjust(
                $product_id,
                $variation_id,
                $branch_id,
                -$quantity,
                'sale',
                $order_id,
                sprintf( __( 'Order #%d', 'wbim' ), $order_id )
            );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
                continue;
            }

            // Create allocation record if not exists
            if ( ! $existing_allocation ) {
                WBIM_Order_Allocation::create( array(
                    'order_id'      => $order_id,
                    'order_item_id' => $item_id,
                    'product_id'    => $product_id,
                    'variation_id'  => $variation_id,
                    'branch_id'     => $branch_id,
                    'quantity'      => $quantity,
                ) );
            }

            // Sync WC stock
            WBIM_Stock::sync_wc_stock( $product_id, $variation_id );
        }

        // Mark stock as deducted
        WBIM_Order_Allocation::set_stock_deducted( $order_id, true );

        // Add order note
        if ( empty( $errors ) ) {
            $order->add_order_note( __( 'WBIM: მარაგი ჩამოიჭრა ფილიალიდან.', 'wbim' ) );
        } else {
            $order->add_order_note(
                sprintf(
                    __( 'WBIM: მარაგის ჩამოჭრის შეცდომები: %s', 'wbim' ),
                    implode( ', ', $errors )
                )
            );
        }

        return empty( $errors ) ? true : new WP_Error( 'deduction_errors', implode( ', ', $errors ) );
    }

    /**
     * Return stock for order (cancellation/refund)
     *
     * @param int $order_id Order ID.
     * @return bool|WP_Error
     */
    public function return_order_stock( $order_id ) {
        // Check if stock was deducted
        if ( ! WBIM_Order_Allocation::is_stock_deducted( $order_id ) ) {
            return true;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Invalid order.', 'wbim' ) );
        }

        // Get allocations
        $allocations = WBIM_Order_Allocation::get_by_order( $order_id );

        if ( empty( $allocations ) ) {
            // No allocations, try to return based on order meta
            return $this->return_stock_from_meta( $order );
        }

        $errors = array();

        foreach ( $allocations as $allocation ) {
            // Return stock to branch
            $result = WBIM_Stock::adjust(
                $allocation->product_id,
                $allocation->variation_id,
                $allocation->branch_id,
                $allocation->quantity,
                'return',
                $order_id,
                sprintf( __( 'Order #%d cancelled/refunded', 'wbim' ), $order_id )
            );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
                continue;
            }

            // Sync WC stock
            WBIM_Stock::sync_wc_stock( $allocation->product_id, $allocation->variation_id );
        }

        // Mark stock as not deducted
        WBIM_Order_Allocation::set_stock_deducted( $order_id, false );

        // Add order note
        if ( empty( $errors ) ) {
            $order->add_order_note( __( 'WBIM: მარაგი დაბრუნდა ფილიალში.', 'wbim' ) );
        } else {
            $order->add_order_note(
                sprintf(
                    __( 'WBIM: მარაგის დაბრუნების შეცდომები: %s', 'wbim' ),
                    implode( ', ', $errors )
                )
            );
        }

        return empty( $errors ) ? true : new WP_Error( 'return_errors', implode( ', ', $errors ) );
    }

    /**
     * Return stock based on order meta (fallback when no allocations)
     *
     * @param WC_Order $order Order object.
     * @return bool|WP_Error
     */
    private function return_stock_from_meta( $order ) {
        $branch_id = $order->get_meta( '_wbim_branch_id' );

        if ( ! $branch_id ) {
            return true; // No branch info, nothing to return
        }

        $errors = array();

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->managing_stock() ) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            $result = WBIM_Stock::adjust(
                $product_id,
                $variation_id,
                $branch_id,
                $quantity,
                'return',
                $order->get_id(),
                sprintf( __( 'Order #%d cancelled/refunded', 'wbim' ), $order->get_id() )
            );

            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
                continue;
            }

            WBIM_Stock::sync_wc_stock( $product_id, $variation_id );
        }

        WBIM_Order_Allocation::set_stock_deducted( $order->get_id(), false );

        return empty( $errors ) ? true : new WP_Error( 'return_errors', implode( ', ', $errors ) );
    }

    /**
     * Handle order status change (re-processing)
     *
     * @param int      $order_id   Order ID.
     * @param string   $old_status Old status.
     * @param string   $new_status New status.
     * @param WC_Order $order      Order object.
     */
    public function handle_status_change( $order_id, $old_status, $new_status, $order ) {
        // If order is re-processed after cancellation
        $deduct_on = WBIM_Utils::get_setting( 'deduct_stock_on', 'processing' );
        $deduct_status = $deduct_on === 'completed' ? 'completed' : 'processing';

        $return_statuses = array( 'cancelled', 'refunded', 'failed' );

        if ( in_array( $old_status, $return_statuses, true ) && $new_status === $deduct_status ) {
            // Stock was returned, now needs to be deducted again
            $this->deduct_order_stock( $order_id );
        }
    }

    /**
     * Auto-select branch for product
     *
     * @param int         $product_id        Product ID.
     * @param int         $variation_id      Variation ID.
     * @param int         $quantity          Required quantity.
     * @param array|null  $customer_location Customer location (lat, lng).
     * @return int|false Branch ID or false if not available.
     */
    public function auto_select_branch( $product_id, $variation_id, $quantity, $customer_location = null ) {
        $method = WBIM_Utils::get_setting( 'auto_select_method', 'most_stock' );

        switch ( $method ) {
            case 'nearest':
                return $this->select_nearest_branch( $product_id, $variation_id, $quantity, $customer_location );

            case 'default':
                return $this->select_default_branch( $product_id, $variation_id, $quantity );

            case 'first_available':
                return $this->select_first_available_branch( $product_id, $variation_id, $quantity );

            case 'most_stock':
            default:
                return $this->select_branch_with_most_stock( $product_id, $variation_id, $quantity );
        }
    }

    /**
     * Select nearest branch with stock
     *
     * @param int        $product_id        Product ID.
     * @param int        $variation_id      Variation ID.
     * @param int        $quantity          Required quantity.
     * @param array|null $customer_location Customer location.
     * @return int|false
     */
    private function select_nearest_branch( $product_id, $variation_id, $quantity, $customer_location = null ) {
        if ( ! $customer_location || ! isset( $customer_location['lat'] ) || ! isset( $customer_location['lng'] ) ) {
            // Fall back to most stock
            return $this->select_branch_with_most_stock( $product_id, $variation_id, $quantity );
        }

        $branches = WBIM_Branch::get_active();
        $options = array();

        foreach ( $branches as $branch ) {
            if ( ! $branch->lat || ! $branch->lng ) {
                continue;
            }

            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            if ( $available >= $quantity ) {
                $distance = $this->calculate_distance(
                    $customer_location['lat'],
                    $customer_location['lng'],
                    $branch->lat,
                    $branch->lng
                );

                $options[] = array(
                    'branch_id' => $branch->id,
                    'distance'  => $distance,
                    'stock'     => $available,
                );
            }
        }

        if ( empty( $options ) ) {
            return false;
        }

        // Sort by distance
        usort( $options, function( $a, $b ) {
            return $a['distance'] <=> $b['distance'];
        } );

        return $options[0]['branch_id'];
    }

    /**
     * Select default branch if has stock
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity     Required quantity.
     * @return int|false
     */
    private function select_default_branch( $product_id, $variation_id, $quantity ) {
        $default_branch_id = WBIM_Utils::get_setting( 'default_branch', 0 );

        if ( ! $default_branch_id ) {
            return $this->select_first_available_branch( $product_id, $variation_id, $quantity );
        }

        $stock = WBIM_Stock::get( $product_id, $default_branch_id, $variation_id );
        $available = $stock ? $stock->quantity : 0;

        if ( $available >= $quantity ) {
            return $default_branch_id;
        }

        // Default doesn't have enough, fall back
        return $this->select_first_available_branch( $product_id, $variation_id, $quantity );
    }

    /**
     * Select first available branch with enough stock
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity     Required quantity.
     * @return int|false
     */
    private function select_first_available_branch( $product_id, $variation_id, $quantity ) {
        $branches = WBIM_Branch::get_active();

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            if ( $available >= $quantity ) {
                return $branch->id;
            }
        }

        return false;
    }

    /**
     * Select branch with most stock
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @param int $quantity     Required quantity.
     * @return int|false
     */
    private function select_branch_with_most_stock( $product_id, $variation_id, $quantity ) {
        $branches = WBIM_Branch::get_active();
        $best_branch = null;
        $max_stock = 0;

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            if ( $available >= $quantity && $available > $max_stock ) {
                $max_stock = $available;
                $best_branch = $branch->id;
            }
        }

        return $best_branch ?: false;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     *
     * @param float $lat1 Latitude 1.
     * @param float $lng1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lng2 Longitude 2.
     * @return float Distance in kilometers.
     */
    private function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
        $earth_radius = 6371; // km

        $lat_diff = deg2rad( $lat2 - $lat1 );
        $lng_diff = deg2rad( $lng2 - $lng1 );

        $a = sin( $lat_diff / 2 ) * sin( $lat_diff / 2 )
            + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
            * sin( $lng_diff / 2 ) * sin( $lng_diff / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Check if order can be fulfilled
     *
     * @param int $order_id Order ID.
     * @return array Result with can_fulfill and details.
     */
    public function can_fulfill_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array(
                'can_fulfill' => false,
                'reason'      => __( 'Invalid order.', 'wbim' ),
            );
        }

        $order_branch_id = $order->get_meta( '_wbim_branch_id' );
        $issues = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->managing_stock() ) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            // Check allocation
            $allocation = WBIM_Order_Allocation::get_by_order_item( $item_id );
            $branch_id = $allocation ? $allocation->branch_id : $order_branch_id;

            if ( ! $branch_id ) {
                $branch_id = $this->auto_select_branch( $product_id, $variation_id, $quantity );
            }

            if ( ! $branch_id ) {
                $issues[] = sprintf(
                    __( '%s: არცერთ ფილიალს არ აქვს საკმარისი მარაგი', 'wbim' ),
                    $product->get_name()
                );
                continue;
            }

            $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            if ( $available < $quantity ) {
                $branch = WBIM_Branch::get_by_id( $branch_id );
                $issues[] = sprintf(
                    __( '%s: %s-ში მხოლოდ %d ცალია (საჭიროა %d)', 'wbim' ),
                    $product->get_name(),
                    $branch ? $branch->name : __( 'ფილიალი', 'wbim' ),
                    $available,
                    $quantity
                );
            }
        }

        return array(
            'can_fulfill' => empty( $issues ),
            'issues'      => $issues,
        );
    }

    /**
     * Get fulfillment options for a product
     *
     * @param int        $product_id        Product ID.
     * @param int        $variation_id      Variation ID.
     * @param int        $quantity          Required quantity.
     * @param array|null $customer_location Customer location.
     * @return array Array of branch options.
     */
    public function get_fulfillment_options( $product_id, $variation_id, $quantity, $customer_location = null ) {
        $branches = WBIM_Branch::get_active();
        $options = array();

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $available = $stock ? $stock->quantity : 0;

            $option = array(
                'branch_id'       => $branch->id,
                'name'            => $branch->name,
                'address'         => $branch->address,
                'city'            => $branch->city,
                'available_stock' => $available,
                'can_fulfill'     => $available >= $quantity,
                'distance'        => null,
            );

            // Calculate distance if location provided
            if ( $customer_location && isset( $customer_location['lat'] ) && $branch->lat && $branch->lng ) {
                $option['distance'] = $this->calculate_distance(
                    $customer_location['lat'],
                    $customer_location['lng'],
                    $branch->lat,
                    $branch->lng
                );
            }

            $options[] = $option;
        }

        // Sort by availability first, then by distance
        usort( $options, function( $a, $b ) {
            // Can fulfill first
            if ( $a['can_fulfill'] !== $b['can_fulfill'] ) {
                return $b['can_fulfill'] <=> $a['can_fulfill'];
            }

            // Then by distance if available
            if ( $a['distance'] !== null && $b['distance'] !== null ) {
                return $a['distance'] <=> $b['distance'];
            }

            // Then by stock
            return $b['available_stock'] <=> $a['available_stock'];
        } );

        return $options;
    }

    /**
     * Get fulfillment options for multiple products (cart)
     *
     * @param array      $items             Array of items with product_id, variation_id, quantity.
     * @param array|null $customer_location Customer location.
     * @return array Array of branch options with combined availability.
     */
    public function get_cart_fulfillment_options( $items, $customer_location = null ) {
        $branches = WBIM_Branch::get_active();
        $options = array();

        foreach ( $branches as $branch ) {
            $can_fulfill_all = true;
            $item_details = array();

            foreach ( $items as $item ) {
                $stock = WBIM_Stock::get( $item['product_id'], $branch->id, $item['variation_id'] );
                $available = $stock ? $stock->quantity : 0;

                $item_details[] = array(
                    'product_id'   => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'required'     => $item['quantity'],
                    'available'    => $available,
                    'can_fulfill'  => $available >= $item['quantity'],
                );

                if ( $available < $item['quantity'] ) {
                    $can_fulfill_all = false;
                }
            }

            $option = array(
                'branch_id'       => $branch->id,
                'name'            => $branch->name,
                'address'         => $branch->address,
                'city'            => $branch->city,
                'can_fulfill_all' => $can_fulfill_all,
                'item_details'    => $item_details,
                'distance'        => null,
            );

            // Calculate distance
            if ( $customer_location && isset( $customer_location['lat'] ) && $branch->lat && $branch->lng ) {
                $option['distance'] = $this->calculate_distance(
                    $customer_location['lat'],
                    $customer_location['lng'],
                    $branch->lat,
                    $branch->lng
                );
            }

            $options[] = $option;
        }

        // Sort: can fulfill all first, then by distance
        usort( $options, function( $a, $b ) {
            if ( $a['can_fulfill_all'] !== $b['can_fulfill_all'] ) {
                return $b['can_fulfill_all'] <=> $a['can_fulfill_all'];
            }

            if ( $a['distance'] !== null && $b['distance'] !== null ) {
                return $a['distance'] <=> $b['distance'];
            }

            return 0;
        } );

        return $options;
    }

    /**
     * Change order branch
     *
     * @param int      $order_id      Order ID.
     * @param int      $new_branch_id New branch ID.
     * @param int|null $item_id       Specific item ID (null for all items).
     * @return bool|WP_Error
     */
    public function change_order_branch( $order_id, $new_branch_id, $item_id = null ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Invalid order.', 'wbim' ) );
        }

        $branch = WBIM_Branch::get_by_id( $new_branch_id );
        if ( ! $branch ) {
            return new WP_Error( 'invalid_branch', __( 'Invalid branch.', 'wbim' ) );
        }

        $stock_deducted = WBIM_Order_Allocation::is_stock_deducted( $order_id );

        if ( $item_id ) {
            // Change specific item
            return $this->change_item_branch( $order, $item_id, $new_branch_id, $stock_deducted );
        }

        // Change all items
        $errors = array();
        $allocations = WBIM_Order_Allocation::get_by_order( $order_id );

        foreach ( $order->get_items() as $order_item_id => $item ) {
            $result = $this->change_item_branch( $order, $order_item_id, $new_branch_id, $stock_deducted );
            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            }
        }

        // Update order meta
        $order->update_meta_data( '_wbim_branch_id', $new_branch_id );
        $order->save();

        // Add order note
        $order->add_order_note(
            sprintf(
                __( 'WBIM: ფილიალი შეიცვალა: %s', 'wbim' ),
                $branch->name
            )
        );

        return empty( $errors ) ? true : new WP_Error( 'change_errors', implode( ', ', $errors ) );
    }

    /**
     * Change branch for specific order item
     *
     * @param WC_Order $order          Order object.
     * @param int      $item_id        Order item ID.
     * @param int      $new_branch_id  New branch ID.
     * @param bool     $stock_deducted Whether stock was already deducted.
     * @return bool|WP_Error
     */
    private function change_item_branch( $order, $item_id, $new_branch_id, $stock_deducted ) {
        $item = $order->get_item( $item_id );
        if ( ! $item ) {
            return new WP_Error( 'invalid_item', __( 'Invalid order item.', 'wbim' ) );
        }

        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();

        $allocation = WBIM_Order_Allocation::get_by_order_item( $item_id );
        $old_branch_id = $allocation ? $allocation->branch_id : $order->get_meta( '_wbim_branch_id' );

        if ( $old_branch_id == $new_branch_id ) {
            return true; // No change needed
        }

        // Check new branch has stock
        $stock = WBIM_Stock::get( $product_id, $new_branch_id, $variation_id );
        $available = $stock ? $stock->quantity : 0;

        if ( $stock_deducted && $available < $quantity ) {
            return new WP_Error(
                'insufficient_stock',
                sprintf(
                    __( 'ფილიალს არ აქვს საკმარისი მარაგი (საჭირო: %d, ხელმისაწვდომი: %d)', 'wbim' ),
                    $quantity,
                    $available
                )
            );
        }

        if ( $stock_deducted ) {
            // Return stock to old branch
            if ( $old_branch_id ) {
                WBIM_Stock::adjust(
                    $product_id,
                    $variation_id,
                    $old_branch_id,
                    $quantity,
                    'transfer',
                    $order->get_id(),
                    __( 'Branch change - stock returned', 'wbim' )
                );
                WBIM_Stock::sync_wc_stock( $product_id, $variation_id );
            }

            // Deduct from new branch
            WBIM_Stock::adjust(
                $product_id,
                $variation_id,
                $new_branch_id,
                -$quantity,
                'transfer',
                $order->get_id(),
                __( 'Branch change - stock deducted', 'wbim' )
            );
            WBIM_Stock::sync_wc_stock( $product_id, $variation_id );
        }

        // Update or create allocation
        if ( $allocation ) {
            WBIM_Order_Allocation::update( $allocation->id, $new_branch_id );
        } else {
            WBIM_Order_Allocation::create( array(
                'order_id'      => $order->get_id(),
                'order_item_id' => $item_id,
                'product_id'    => $product_id,
                'variation_id'  => $variation_id,
                'branch_id'     => $new_branch_id,
                'quantity'      => $quantity,
            ) );
        }

        return true;
    }
}
