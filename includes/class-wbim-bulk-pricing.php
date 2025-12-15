<?php
/**
 * Bulk Pricing Frontend Class
 *
 * Handles bulk pricing display on product pages and cart pricing calculations.
 *
 * @package WBIM
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bulk Pricing class
 *
 * @since 1.1.0
 */
class WBIM_Bulk_Pricing {

    /**
     * Processed cart items to prevent duplicate processing
     *
     * @var array
     */
    private static $processed_items = array();

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
        // Frontend display - before branch selector (priority 5)
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_quantity_buttons' ), 5 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Cart pricing
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_pricing' ), 15, 1 );

        // Clear processed items when cart is emptied or item removed
        add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_processed_item' ), 10, 2 );
        add_action( 'woocommerce_cart_emptied', array( $this, 'clear_processed_items' ) );

        // AJAX handlers
        add_action( 'wp_ajax_wbim_get_bulk_price', array( $this, 'ajax_get_bulk_price' ) );
        add_action( 'wp_ajax_nopriv_wbim_get_bulk_price', array( $this, 'ajax_get_bulk_price' ) );

        // Variable product variation data
        add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_bulk_data' ), 10, 3 );
    }

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( ! is_product() ) {
            return;
        }

        global $product;

        // Ensure $product is a valid WC_Product object
        if ( ! $product || ! is_object( $product ) || ! ( $product instanceof WC_Product ) ) {
            // Try to get the product from the queried object
            $product = wc_get_product( get_queried_object_id() );
        }

        if ( ! $product || ! is_object( $product ) ) {
            return;
        }

        // Check if bulk pricing is enabled for this product or any variation
        $has_bulk_pricing = $this->product_has_bulk_pricing( $product );

        if ( ! $has_bulk_pricing ) {
            return;
        }

        // Enqueue bulk pricing JS
        wp_enqueue_script(
            'wbim-bulk-pricing',
            WBIM_PLUGIN_URL . 'public/js/wbim-bulk-pricing.js',
            array( 'jquery' ),
            WBIM_VERSION,
            true
        );

        // Localize script data
        wp_localize_script( 'wbim-bulk-pricing', 'wbim_bulk_pricing', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'wbim_bulk_pricing_nonce' ),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_pos'    => get_option( 'woocommerce_currency_pos', 'left' ),
            'decimal_sep'     => wc_get_price_decimal_separator(),
            'thousand_sep'    => wc_get_price_thousand_separator(),
            'decimals'        => wc_get_price_decimals(),
            'is_variable'     => $product->is_type( 'variable' ),
            'product_id'      => $product->get_id(),
            'i18n'            => array(
                'choose_qty'   => __( 'აირჩიეთ რაოდენობა:', 'wbim' ),
                'pcs'          => __( 'ცალი', 'wbim' ),
                'save'         => __( 'დაზოგე', 'wbim' ),
                'per_unit'     => __( '/ცალი', 'wbim' ),
                'select_var'   => __( 'აირჩიეთ ვარიაცია', 'wbim' ),
            ),
        ) );
    }

    /**
     * Check if product has bulk pricing enabled
     *
     * @param WC_Product $product Product object.
     * @return bool
     */
    private function product_has_bulk_pricing( $product ) {
        $product_id = $product->get_id();

        // Check simple product
        if ( WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $product_id ) ) {
            return true;
        }

        // Check variations
        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_children();
            foreach ( $variations as $variation_id ) {
                if ( WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $variation_id ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Display quantity buttons on product page
     *
     * @return void
     */
    public function display_quantity_buttons() {
        global $product;

        // Ensure $product is a valid WC_Product object
        if ( ! $product || ! is_object( $product ) || ! ( $product instanceof WC_Product ) ) {
            return;
        }

        $product_id  = $product->get_id();
        $is_variable = $product->is_type( 'variable' );

        // For simple products, check if bulk pricing is enabled
        if ( ! $is_variable ) {
            if ( ! WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $product_id ) ||
                 ! WBIM_Admin_Bulk_Pricing::are_qty_buttons_enabled( $product_id ) ) {
                return;
            }

            $tiers         = WBIM_Admin_Bulk_Pricing::get_bulk_pricing_tiers( $product_id );
            $regular_price = floatval( $product->get_regular_price() );

            if ( empty( $tiers ) ) {
                return;
            }

            $this->render_quantity_buttons( $tiers, $regular_price, $product_id );
        } else {
            // For variable products, render empty container (populated via JS)
            $this->render_variable_buttons_container( $product );
        }
    }

    /**
     * Render quantity buttons for simple products
     *
     * @param array $tiers         Pricing tiers.
     * @param float $regular_price Regular price.
     * @param int   $product_id    Product ID.
     * @return void
     */
    private function render_quantity_buttons( $tiers, $regular_price, $product_id ) {
        $currency = get_woocommerce_currency_symbol();

        ?>
        <div class="wbim-bulk-pricing-wrapper" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <h4 class="wbim-bulk-pricing-title"><?php esc_html_e( 'აირჩიეთ რაოდენობა:', 'wbim' ); ?></h4>
            <div class="wbim-qty-buttons">
                <?php
                // Sort tiers by quantity ascending for display
                usort( $tiers, function( $a, $b ) {
                    return $a['qty'] - $b['qty'];
                });

                foreach ( $tiers as $tier ) :
                    $total    = $tier['qty'] * $tier['price'];
                    $savings  = 0;

                    if ( $regular_price > 0 && $tier['price'] < $regular_price ) {
                        $savings = round( ( ( $regular_price - $tier['price'] ) / $regular_price ) * 100 );
                    }
                    ?>
                    <button type="button"
                            class="wbim-qty-btn"
                            data-qty="<?php echo esc_attr( $tier['qty'] ); ?>"
                            data-price="<?php echo esc_attr( $tier['price'] ); ?>"
                            data-total="<?php echo esc_attr( $total ); ?>">
                        <span class="wbim-qty-btn-qty">
                            <?php echo esc_html( $tier['qty'] ); ?>
                            <small><?php esc_html_e( 'ცალი', 'wbim' ); ?></small>
                        </span>
                        <span class="wbim-qty-btn-total">
                            <?php echo wp_kses_post( wc_price( $total ) ); ?>
                        </span>
                        <span class="wbim-qty-btn-unit">
                            <?php echo wp_kses_post( wc_price( $tier['price'] ) ); ?>/<?php esc_html_e( 'ცალი', 'wbim' ); ?>
                        </span>
                        <?php if ( $savings > 0 ) : ?>
                            <span class="wbim-savings-badge">
                                <?php
                                printf(
                                    esc_html__( 'დაზოგე %d%%', 'wbim' ),
                                    $savings
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render variable product buttons container
     *
     * @param WC_Product $product Product object.
     * @return void
     */
    private function render_variable_buttons_container( $product ) {
        // Check if any variation has bulk pricing enabled
        $has_any_bulk = false;
        $variations   = $product->get_children();

        foreach ( $variations as $variation_id ) {
            if ( WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $variation_id ) &&
                 WBIM_Admin_Bulk_Pricing::are_qty_buttons_enabled( $variation_id ) ) {
                $has_any_bulk = true;
                break;
            }
        }

        if ( ! $has_any_bulk ) {
            return;
        }

        ?>
        <div class="wbim-bulk-pricing-wrapper wbim-bulk-variable"
             data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
             style="display: none;">
            <h4 class="wbim-bulk-pricing-title"><?php esc_html_e( 'აირჩიეთ რაოდენობა:', 'wbim' ); ?></h4>
            <div class="wbim-qty-buttons">
                <!-- Buttons populated via JavaScript -->
            </div>
        </div>
        <?php
    }

    /**
     * Add bulk pricing data to variation
     *
     * @param array                $data      Variation data.
     * @param WC_Product           $product   Parent product.
     * @param WC_Product_Variation $variation Variation object.
     * @return array
     */
    public function add_variation_bulk_data( $data, $product, $variation ) {
        $variation_id = $variation->get_id();

        $data['wbim_bulk_pricing'] = array(
            'enabled'       => WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $variation_id ),
            'buttons'       => WBIM_Admin_Bulk_Pricing::are_qty_buttons_enabled( $variation_id ),
            'tiers'         => WBIM_Admin_Bulk_Pricing::get_bulk_pricing_tiers( $variation_id ),
            'regular_price' => floatval( $variation->get_regular_price() ),
        );

        return $data;
    }

    /**
     * Apply bulk pricing in cart
     *
     * @param WC_Cart $cart Cart object.
     * @return void
     */
    public function apply_cart_pricing( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! $cart || empty( $cart->get_cart() ) ) {
            return;
        }

        // Recursion protection
        static $running = false;
        if ( $running ) {
            return;
        }
        $running = true;

        try {
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                // Skip if already processed
                if ( isset( self::$processed_items[ $cart_item_key ] ) ) {
                    continue;
                }

                $product_id   = $cart_item['product_id'];
                $variation_id = $cart_item['variation_id'];
                $quantity     = $cart_item['quantity'];

                // Determine which ID to check
                $check_id = $variation_id ? $variation_id : $product_id;

                // Check if bulk pricing is enabled
                if ( ! WBIM_Admin_Bulk_Pricing::is_bulk_pricing_enabled( $check_id ) ) {
                    continue;
                }

                // Get bulk price for quantity
                $bulk_price = $this->get_bulk_price( $check_id, $quantity );

                if ( $bulk_price > 0 ) {
                    // Get original prices
                    $original_regular = get_post_meta( $check_id, '_regular_price', true );

                    // Store original price if not already stored
                    if ( ! $cart_item['data']->get_meta( '_wbim_original_price' ) ) {
                        $cart_item['data']->add_meta_data( '_wbim_original_price', $cart_item['data']->get_price() );
                        $cart_item['data']->add_meta_data( '_wbim_original_regular', $original_regular );
                    }

                    // Apply bulk price
                    $cart_item['data']->set_price( $bulk_price );

                    // Set sale price if bulk price is lower than regular
                    if ( $original_regular && $bulk_price < floatval( $original_regular ) ) {
                        $cart_item['data']->set_sale_price( $bulk_price );
                    }

                    self::$processed_items[ $cart_item_key ] = true;
                }
            }
        } catch ( Exception $e ) {
            error_log( '[WBIM Bulk Pricing] Error: ' . $e->getMessage() );
        } finally {
            $running = false;
        }
    }

    /**
     * Get bulk price for quantity
     *
     * @param int $product_id Product or variation ID.
     * @param int $quantity   Quantity.
     * @return float
     */
    public function get_bulk_price( $product_id, $quantity ) {
        $tiers = WBIM_Admin_Bulk_Pricing::get_bulk_pricing_tiers( $product_id );

        if ( empty( $tiers ) ) {
            $product = wc_get_product( $product_id );
            return $product ? floatval( $product->get_price() ) : 0;
        }

        // Tiers are sorted descending by quantity
        foreach ( $tiers as $tier ) {
            if ( $quantity >= $tier['qty'] ) {
                return $tier['price'];
            }
        }

        // No tier matched, return regular price
        $product = wc_get_product( $product_id );
        return $product ? floatval( $product->get_price() ) : 0;
    }

    /**
     * Remove processed item from tracking
     *
     * @param string  $cart_item_key Cart item key.
     * @param WC_Cart $cart          Cart object.
     * @return void
     */
    public function remove_processed_item( $cart_item_key, $cart ) {
        if ( isset( self::$processed_items[ $cart_item_key ] ) ) {
            unset( self::$processed_items[ $cart_item_key ] );
        }
    }

    /**
     * Clear all processed items
     *
     * @return void
     */
    public function clear_processed_items() {
        self::$processed_items = array();
    }

    /**
     * AJAX handler for getting bulk price
     *
     * @return void
     */
    public function ajax_get_bulk_price() {
        // Verify nonce
        if ( ! check_ajax_referer( 'wbim_bulk_pricing_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'უსაფრთხოების შემოწმება ვერ მოხერხდა.', 'wbim' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'არასწორი პროდუქტი.', 'wbim' ) ) );
        }

        $price = $this->get_bulk_price( $product_id, $quantity );
        $total = $price * $quantity;

        // Get regular price for savings calculation
        $product       = wc_get_product( $product_id );
        $regular_price = $product ? floatval( $product->get_regular_price() ) : 0;
        $savings       = 0;

        if ( $regular_price > 0 && $price < $regular_price ) {
            $savings = round( ( ( $regular_price - $price ) / $regular_price ) * 100 );
        }

        wp_send_json_success( array(
            'price'         => $price,
            'total'         => $total,
            'price_html'    => wc_price( $price ),
            'total_html'    => wc_price( $total ),
            'savings'       => $savings,
            'regular_price' => $regular_price,
            'regular_total' => $regular_price * $quantity,
        ) );
    }
}
