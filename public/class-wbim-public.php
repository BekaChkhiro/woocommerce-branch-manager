<?php
/**
 * Public Frontend Class
 *
 * Handles frontend display of branch stock information.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public frontend class
 *
 * @since 1.0.0
 */
class WBIM_Public {

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
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Product page branch selector (before add to cart button)
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_branch_selector' ), 10 );

        // Product page stock display
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_branch_stock' ), 25 );

        // Shop/archive page stock display
        add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_branch_stock_archive' ), 15 );

        // Variable product stock display via AJAX
        add_action( 'wp_ajax_wbim_get_variation_stock', array( $this, 'ajax_get_variation_stock' ) );
        add_action( 'wp_ajax_nopriv_wbim_get_variation_stock', array( $this, 'ajax_get_variation_stock' ) );

        // Add branch data to cart item
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_branch_to_cart_item' ), 10, 3 );

        // Cart item branch display
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_branch' ), 10, 2 );

        // Make cart items with different branches unique
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'cart_item_quantity' ), 10, 3 );

        // Restore branch from cart session
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

        // Order item branch display
        add_action( 'woocommerce_order_item_meta_end', array( $this, 'display_order_item_branch' ), 10, 4 );

        // Save branch to order item meta
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_branch_to_order_item' ), 10, 4 );

        // Email branch information
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_email_branch_info' ), 10, 4 );

        // Validate cart item has branch
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_branch_selection' ), 10, 5 );
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_assets() {
        // Only load assets on frontend, not in admin
        if ( is_admin() ) {
            return;
        }

        $settings = get_option( 'wbim_settings', array() );

        // Check if we should load assets
        if ( ! is_product() && ! is_checkout() && ! is_cart() ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'wbim-public',
            WBIM_PLUGIN_URL . 'public/css/wbim-public.css',
            array(),
            WBIM_VERSION
        );

        // JS
        wp_enqueue_script(
            'wbim-public',
            WBIM_PLUGIN_URL . 'public/js/wbim-public.js',
            array( 'jquery' ),
            WBIM_VERSION,
            true
        );

        // Get default branch
        $default_branch = WBIM_Branch::get_default();
        $default_branch_id = $default_branch ? $default_branch->id : 0;

        // Localize script
        wp_localize_script( 'wbim-public', 'wbim_public', array(
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'wbim_public_nonce' ),
            'default_branch_id' => $default_branch_id,
            'i18n'              => array(
                'loading'          => __( 'იტვირთება...', 'wbim' ),
                'in_stock'         => __( 'მარაგშია', 'wbim' ),
                'out_of_stock'     => __( 'ამოწურულია', 'wbim' ),
                'low_stock'        => __( 'დაბალი მარაგი', 'wbim' ),
                'available'        => __( 'მარაგში', 'wbim' ),
                'select_branch'    => __( 'აირჩიეთ ფილიალი', 'wbim' ),
                'select_variation' => __( 'აირჩიეთ ვარიაცია', 'wbim' ),
            ),
        ) );

        // Checkout specific
        if ( is_checkout() ) {
            wp_localize_script( 'wbim-public', 'wbim_checkout', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wbim_checkout_nonce' ),
            ) );
        }
    }

    /**
     * Display branch selector on product page before add to cart
     */
    public function display_branch_selector() {
        global $product;

        if ( ! $product ) {
            return;
        }

        $settings = get_option( 'wbim_settings', array() );
        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            return;
        }

        $product_id = $product->get_id();
        $is_variable = $product->is_type( 'variable' );

        // Get stock for each branch
        $branch_stock = array();
        $has_any_stock = false;

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id );
            $qty = $stock ? (int) $stock->quantity : 0;

            // Default to 'instock' - this allows status-based stock management
            // where quantity can be 0 but product is still purchasable
            if ( $stock && ! empty( $stock->stock_status ) ) {
                $status = $stock->stock_status;
            } else {
                // No record or no status set - default to 'instock'
                $status = 'instock';
            }

            $branch_stock[ $branch->id ] = array(
                'quantity' => $qty,
                'status'   => $status,
            );

            // Has stock if status is not outofstock OR quantity > 0
            if ( 'outofstock' !== $status || $qty > 0 ) {
                $has_any_stock = true;
            }
        }

        // For variable products, check if any variation has stock in any branch
        if ( $is_variable && ! $has_any_stock ) {
            $variations = $product->get_children();
            foreach ( $variations as $variation_id ) {
                foreach ( $branches as $branch ) {
                    $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
                    if ( $stock ) {
                        $var_status = ! empty( $stock->stock_status ) ? $stock->stock_status : 'instock';
                        // Has stock if status is purchasable OR quantity > 0
                        if ( 'outofstock' !== $var_status || $stock->quantity > 0 ) {
                            $has_any_stock = true;
                            break 2;
                        }
                    }
                }
            }
        }

        // Always show branch selector if branches exist
        // Users can see availability status per branch
        // Comment out the stock check to always display selector:
        // if ( ! $has_any_stock && ! $is_variable ) {
        //     return;
        // }
        // if ( $is_variable && ! $has_any_stock ) {
        //     return;
        // }

        $show_quantity = ! empty( $settings['show_exact_quantity'] ) && 'yes' === $settings['show_exact_quantity'];
        $required = ! empty( $settings['require_branch_selection'] ) && 'yes' === $settings['require_branch_selection'];

        ?>
        <div class="wbim-branch-selector-wrapper" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-is-variable="<?php echo $is_variable ? '1' : '0'; ?>">
            <div class="wbim-branch-selector-label">
                <?php esc_html_e( 'აირჩიეთ ფილიალი', 'wbim' ); ?>
                <?php if ( $required ) : ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </div>

            <?php if ( $is_variable ) : ?>
                <p class="wbim-variable-notice">
                    <?php esc_html_e( 'აირჩიეთ ვარიაცია მარაგის სანახავად', 'wbim' ); ?>
                </p>
            <?php endif; ?>

            <div class="wbim-branch-buttons">
                <?php foreach ( $branches as $branch ) : ?>
                    <?php
                    $stock_info = $branch_stock[ $branch->id ];
                    $qty = $stock_info['quantity'];
                    $status = $stock_info['status'];
                    $is_out_of_stock = 'outofstock' === $status && ! $is_variable;

                    // Map status to CSS class
                    $status_class_map = array(
                        'instock'    => 'wbim-branch-in-stock',
                        'low'        => 'wbim-branch-low-stock',
                        'outofstock' => 'wbim-branch-out-of-stock',
                        'preorder'   => 'wbim-branch-preorder',
                    );

                    // Map status to display text
                    $status_text_map = array(
                        'instock'    => __( 'მარაგშია', 'wbim' ),
                        'low'        => __( 'მცირე რაოდენობა', 'wbim' ),
                        'outofstock' => __( 'არ არის მარაგში', 'wbim' ),
                        'preorder'   => __( 'წინასწარი შეკვეთით', 'wbim' ),
                    );

                    $stock_class = $is_variable ? '' : ( isset( $status_class_map[ $status ] ) ? $status_class_map[ $status ] : 'wbim-branch-out-of-stock' );
                    $status_text = isset( $status_text_map[ $status ] ) ? $status_text_map[ $status ] : __( 'არ არის მარაგში', 'wbim' );

                    // Check if we can show quantity (only for instock and low)
                    $show_quantity = ! empty( get_option( 'wbim_settings', array() )['show_exact_quantity'] )
                                     && 'yes' === get_option( 'wbim_settings', array() )['show_exact_quantity'];
                    $can_show_qty = in_array( $status, array( 'instock', 'low' ), true ) && $qty > 0;
                    ?>
                    <div class="wbim-branch-button <?php echo esc_attr( $stock_class ); ?> <?php echo $is_out_of_stock ? 'wbim-branch-disabled' : ''; ?>"
                         data-branch-id="<?php echo esc_attr( $branch->id ); ?>"
                         data-branch-name="<?php echo esc_attr( $branch->name ); ?>"
                         data-stock="<?php echo esc_attr( $qty ); ?>"
                         data-status="<?php echo esc_attr( $status ); ?>">

                        <div class="wbim-branch-button-content">
                            <div class="wbim-branch-button-header">
                                <span class="wbim-branch-button-name"><?php echo esc_html( $branch->name ); ?></span>
                                <span class="wbim-branch-button-check">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </span>
                            </div>

                            <div class="wbim-branch-button-stock">
                                <?php if ( $is_variable ) : ?>
                                    <span class="wbim-stock-variable"><?php esc_html_e( 'აირჩიეთ ვარიაცია', 'wbim' ); ?></span>
                                <?php else : ?>
                                    <span class="wbim-stock-status wbim-status-<?php echo esc_attr( $status ); ?>">
                                        <?php echo esc_html( $status_text ); ?>
                                        <?php if ( $show_quantity && $can_show_qty ) : ?>
                                            <span class="wbim-stock-qty">(<?php echo esc_html( $qty ); ?> <?php esc_html_e( 'ცალი', 'wbim' ); ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $branch->address ) || ! empty( $branch->phone ) ) : ?>
                                <div class="wbim-branch-contact-toggle">
                                    <span class="wbim-contact-trigger">
                                        <?php esc_html_e( 'კონტაქტი', 'wbim' ); ?>
                                        <svg class="wbim-contact-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 12 15 18 9"></polyline>
                                        </svg>
                                    </span>
                                    <div class="wbim-branch-contact-details">
                                        <?php if ( ! empty( $branch->address ) ) : ?>
                                            <div class="wbim-contact-row">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <span><?php echo esc_html( $branch->address ); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $branch->phone ) ) : ?>
                                            <div class="wbim-contact-row">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                </svg>
                                                <a href="tel:<?php echo esc_attr( $branch->phone ); ?>"><?php echo esc_html( $branch->phone ); ?></a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="wbim_branch_id" id="wbim_branch_id" value="" <?php echo $required ? 'required' : ''; ?>>
            <input type="hidden" name="wbim_branch_name" id="wbim_branch_name" value="">
        </div>
        <?php
    }

    /**
     * Validate branch selection before add to cart
     *
     * @param bool   $passed     Validation passed.
     * @param int    $product_id Product ID.
     * @param int    $quantity   Quantity.
     * @param int    $variation_id Variation ID.
     * @param array  $variations Variations.
     * @return bool
     */
    public function validate_branch_selection( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
        $settings = get_option( 'wbim_settings', array() );
        $global_required = ! empty( $settings['require_branch_selection'] ) && 'yes' === $settings['require_branch_selection'];

        // Check if product has any branch stock records
        $has_branch_stock = $this->product_has_branch_stock( $product_id, $variation_id );

        // Require branch selection if:
        // 1. Global setting is enabled, OR
        // 2. Product has branch stock records
        $required = $global_required || $has_branch_stock;

        if ( ! $required ) {
            return $passed;
        }

        $branch_id = isset( $_POST['wbim_branch_id'] ) ? absint( $_POST['wbim_branch_id'] ) : 0;

        if ( ! $branch_id ) {
            if ( $has_branch_stock ) {
                wc_add_notice( __( 'გთხოვთ აირჩიოთ ფილიალი საიდანაც გსურთ პროდუქტის შეძენა.', 'wbim' ), 'error' );
            } else {
                wc_add_notice( __( 'გთხოვთ აირჩიოთ ფილიალი.', 'wbim' ), 'error' );
            }
            return false;
        }

        // Check stock in selected branch
        $stock = WBIM_Stock::get( $product_id, $branch_id, $variation_id );
        $available_qty = $stock ? (int) $stock->quantity : 0;
        $stock_status = $stock && isset( $stock->stock_status ) ? $stock->stock_status : 'outofstock';

        // Check stock status - only 'outofstock' prevents purchase
        if ( 'outofstock' === $stock_status ) {
            $branch = WBIM_Branch::get_by_id( $branch_id );
            $branch_name = $branch ? $branch->name : '';
            wc_add_notice(
                sprintf(
                    __( 'არჩეულ ფილიალში "%s" პროდუქტი არ არის მარაგში.', 'wbim' ),
                    $branch_name
                ),
                'error'
            );
            return false;
        }

        // For statuses with quantity (instock, low), check actual quantity if provided
        if ( in_array( $stock_status, array( 'instock', 'low' ), true ) && $available_qty > 0 ) {
            if ( $available_qty < $quantity ) {
                $branch = WBIM_Branch::get_by_id( $branch_id );
                $branch_name = $branch ? $branch->name : '';
                wc_add_notice(
                    sprintf(
                        __( 'არჩეულ ფილიალში "%s" საკმარისი მარაგი არ არის. ხელმისაწვდომია: %d', 'wbim' ),
                        $branch_name,
                        $available_qty
                    ),
                    'error'
                );
                return false;
            }
        }

        // For instock/low without quantity (status only), or preorder - allow purchase
        return $passed;
    }

    /**
     * Check if product has any branch stock records
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     * @return bool
     */
    private function product_has_branch_stock( $product_id, $variation_id = 0 ) {
        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            return false;
        }

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            if ( $stock ) {
                // Default to 'instock' if record exists but status is not set
                $status = ! empty( $stock->stock_status ) ? $stock->stock_status : 'instock';
                // Check stock status - anything except outofstock indicates available
                if ( 'outofstock' !== $status ) {
                    return true;
                }
                // Also check quantity for backwards compatibility
                if ( $stock->quantity > 0 ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add branch data to cart item
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array
     */
    public function add_branch_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['wbim_branch_id'] ) && ! empty( $_POST['wbim_branch_id'] ) ) {
            $branch_id = absint( $_POST['wbim_branch_id'] );
            $branch = WBIM_Branch::get_by_id( $branch_id );

            if ( $branch ) {
                $cart_item_data['wbim_branch_id'] = $branch_id;
                $cart_item_data['wbim_branch_name'] = $branch->name;
                // Make cart items with different branches unique
                $cart_item_data['unique_key'] = md5( microtime() . rand() );
            }
        }

        return $cart_item_data;
    }

    /**
     * Restore branch data from session
     *
     * @param array $cart_item Cart item.
     * @param array $values    Session values.
     * @return array
     */
    public function get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['wbim_branch_id'] ) ) {
            $cart_item['wbim_branch_id'] = $values['wbim_branch_id'];
        }
        if ( isset( $values['wbim_branch_name'] ) ) {
            $cart_item['wbim_branch_name'] = $values['wbim_branch_name'];
        }
        return $cart_item;
    }

    /**
     * Display branch quantity in cart (optional modification)
     *
     * @param string $product_quantity Quantity HTML.
     * @param string $cart_item_key    Cart item key.
     * @param array  $cart_item        Cart item.
     * @return string
     */
    public function cart_item_quantity( $product_quantity, $cart_item_key, $cart_item ) {
        // Just return as is, but this hook allows us to modify if needed
        return $product_quantity;
    }

    /**
     * Save branch to order item meta
     *
     * @param WC_Order_Item_Product $item          Order item.
     * @param string                $cart_item_key Cart item key.
     * @param array                 $values        Cart item values.
     * @param WC_Order              $order         Order object.
     */
    public function save_branch_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['wbim_branch_id'] ) ) {
            $item->add_meta_data( '_wbim_branch_id', $values['wbim_branch_id'], true );
            $item->add_meta_data( '_wbim_branch_name', $values['wbim_branch_name'], true );

            // Also save to order meta if not set
            if ( ! $order->get_meta( '_wbim_branch_id' ) ) {
                $order->update_meta_data( '_wbim_branch_id', $values['wbim_branch_id'] );
                $order->update_meta_data( '_wbim_branch_name', $values['wbim_branch_name'] );
            }
        }
    }

    /**
     * Display branch stock on single product page
     */
    public function display_branch_stock() {
        $settings = get_option( 'wbim_settings', array() );

        // Check if branch stock display is enabled
        if ( empty( $settings['show_branch_stock_product'] ) || 'yes' !== $settings['show_branch_stock_product'] ) {
            return;
        }

        global $product;

        if ( ! $product ) {
            return;
        }

        $product_id = $product->get_id();
        $variation_id = 0;

        // For variable products, we'll update via AJAX when variation is selected
        if ( $product->is_type( 'variable' ) ) {
            $this->display_branch_stock_variable( $product );
            return;
        }

        $this->render_branch_stock_display( $product_id, $variation_id );
    }

    /**
     * Display branch stock for variable products
     *
     * @param WC_Product $product Product object.
     */
    private function display_branch_stock_variable( $product ) {
        ?>
        <div class="wbim-branch-stock-container wbim-variable-product" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
            <h4 class="wbim-stock-title"><?php esc_html_e( 'ფილიალების მარაგი', 'wbim' ); ?></h4>
            <p class="wbim-select-variation"><?php esc_html_e( 'აირჩიეთ ვარიაცია მარაგის სანახავად', 'wbim' ); ?></p>
            <div class="wbim-branch-stock-list" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Render branch stock display
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     */
    private function render_branch_stock_display( $product_id, $variation_id = 0 ) {
        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            return;
        }

        $settings = get_option( 'wbim_settings', array() );
        $show_quantity = ! empty( $settings['show_exact_quantity'] ) && 'yes' === $settings['show_exact_quantity'];

        $template_data = array(
            'product_id'    => $product_id,
            'variation_id'  => $variation_id,
            'branches'      => $branches,
            'show_quantity' => $show_quantity,
        );

        $this->load_template( 'product/branch-stock-display.php', $template_data );
    }

    /**
     * Display branch stock on archive pages
     */
    public function display_branch_stock_archive() {
        $settings = get_option( 'wbim_settings', array() );

        // Check if archive stock display is enabled
        if ( empty( $settings['show_branch_stock_archive'] ) || 'yes' !== $settings['show_branch_stock_archive'] ) {
            return;
        }

        global $product;

        if ( ! $product || $product->is_type( 'variable' ) ) {
            return;
        }

        $product_id = $product->get_id();
        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            return;
        }

        // Show compact version for archive
        $available_count = 0;
        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id );
            if ( $stock && $stock->quantity > 0 ) {
                $available_count++;
            }
        }

        ?>
        <div class="wbim-archive-stock">
            <?php if ( $available_count > 0 ) : ?>
                <span class="wbim-archive-available">
                    <?php
                    printf(
                        esc_html( _n( '%d ფილიალში', '%d ფილიალში', $available_count, 'wbim' ) ),
                        $available_count
                    );
                    ?>
                </span>
            <?php else : ?>
                <span class="wbim-archive-unavailable">
                    <?php esc_html_e( 'ფილიალებში არ არის', 'wbim' ); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Get variation stock by branch
     */
    public function ajax_get_variation_stock() {
        // Verify nonce
        if ( ! check_ajax_referer( 'wbim_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'უსაფრთხოების შემოწმება ვერ მოხერხდა.', 'wbim' ),
                'debug'   => 'nonce_failed'
            ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $variation_id ) {
            wp_send_json_error( array(
                'message' => __( 'ვარიაცია არ არის მითითებული.', 'wbim' ),
                'debug'   => 'no_variation_id'
            ) );
        }

        $branches = WBIM_Branch::get_active();

        if ( empty( $branches ) ) {
            wp_send_json_error( array(
                'message' => __( 'ფილიალები არ მოიძებნა.', 'wbim' ),
                'debug'   => 'no_branches'
            ) );
        }

        $settings = get_option( 'wbim_settings', array() );

        $stock_data = array();

        foreach ( $branches as $branch ) {
            $stock = WBIM_Stock::get( $product_id, $branch->id, $variation_id );
            $quantity = 0;
            $low_threshold = 0;
            $stock_status = 'instock'; // Default to instock

            if ( $stock && is_object( $stock ) ) {
                $quantity = isset( $stock->quantity ) ? (int) $stock->quantity : 0;
                $low_threshold = isset( $stock->low_stock_threshold ) ? (int) $stock->low_stock_threshold : 0;
                $stock_status = ! empty( $stock->stock_status ) ? $stock->stock_status : 'instock';
            }

            // Map stock_status to display status
            $status_map = array(
                'instock'    => array( 'status' => 'in_stock', 'text' => __( 'მარაგშია', 'wbim' ), 'class' => 'wbim-stock-in' ),
                'low'        => array( 'status' => 'low_stock', 'text' => __( 'მცირე რაოდენობა', 'wbim' ), 'class' => 'wbim-stock-low' ),
                'outofstock' => array( 'status' => 'out_of_stock', 'text' => __( 'არ არის მარაგში', 'wbim' ), 'class' => 'wbim-stock-out' ),
                'preorder'   => array( 'status' => 'preorder', 'text' => __( 'წინასწარი შეკვეთით', 'wbim' ), 'class' => 'wbim-stock-preorder' ),
            );

            $status_info = isset( $status_map[ $stock_status ] ) ? $status_map[ $stock_status ] : $status_map['instock'];

            $stock_data[] = array(
                'branch_id'    => $branch->id,
                'branch_name'  => $branch->name,
                'quantity'     => $quantity,
                'status'       => $status_info['status'],
                'status_text'  => $status_info['text'],
                'status_class' => $status_info['class'],
                'stock_status' => $stock_status,
            );
        }

        wp_send_json_success( array(
            'stock'        => $stock_data,
            'product_id'   => $product_id,
            'variation_id' => $variation_id
        ) );
    }

    /**
     * Display branch info in cart
     *
     * @param array $item_data Cart item data.
     * @param array $cart_item Cart item.
     * @return array
     */
    public function display_cart_item_branch( $item_data, $cart_item ) {
        // Check if this cart item has branch assigned
        if ( ! empty( $cart_item['wbim_branch_name'] ) ) {
            $item_data[] = array(
                'key'     => __( 'ფილიალი', 'wbim' ),
                'value'   => $cart_item['wbim_branch_name'],
                'display' => '<strong>' . esc_html( $cart_item['wbim_branch_name'] ) . '</strong>',
            );
        }

        return $item_data;
    }

    /**
     * Display branch info on order items
     *
     * @param int          $item_id Order item ID.
     * @param WC_Order_Item $item    Order item.
     * @param WC_Order      $order   Order object.
     * @param bool          $plain   Plain text format.
     */
    public function display_order_item_branch( $item_id, $item, $order, $plain = false ) {
        $allocation = WBIM_Order_Allocation::get_by_order_item( $item_id );

        if ( ! $allocation ) {
            return;
        }

        if ( $plain ) {
            echo "\n" . esc_html__( 'ფილიალი:', 'wbim' ) . ' ' . esc_html( $allocation->branch_name );
        } else {
            ?>
            <p class="wbim-order-item-branch">
                <strong><?php esc_html_e( 'ფილიალი:', 'wbim' ); ?></strong>
                <?php echo esc_html( $allocation->branch_name ); ?>
            </p>
            <?php
        }
    }

    /**
     * Display branch info in order emails
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Sent to admin.
     * @param bool     $plain_text    Plain text.
     * @param WC_Email $email         Email object.
     */
    public function display_email_branch_info( $order, $sent_to_admin, $plain_text, $email = null ) {
        $branch_id = $order->get_meta( '_wbim_branch_id' );

        if ( ! $branch_id ) {
            return;
        }

        $branch = WBIM_Branch::get_by_id( $branch_id );

        if ( ! $branch ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n\n" . esc_html__( 'ფილიალის ინფორმაცია', 'wbim' ) . "\n";
            echo esc_html__( 'ფილიალი:', 'wbim' ) . ' ' . esc_html( $branch->name ) . "\n";
            if ( $branch->address ) {
                echo esc_html__( 'მისამართი:', 'wbim' ) . ' ' . esc_html( $branch->address ) . "\n";
            }
            if ( $branch->phone ) {
                echo esc_html__( 'ტელეფონი:', 'wbim' ) . ' ' . esc_html( $branch->phone ) . "\n";
            }
        } else {
            ?>
            <div class="wbim-email-branch" style="margin-bottom: 20px;">
                <h2 style="margin: 0 0 10px;"><?php esc_html_e( 'ფილიალის ინფორმაცია', 'wbim' ); ?></h2>
                <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5;">
                    <tr>
                        <th style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                        <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html( $branch->name ); ?></td>
                    </tr>
                    <?php if ( $branch->address ) : ?>
                    <tr>
                        <th style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e( 'მისამართი', 'wbim' ); ?></th>
                        <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html( $branch->address ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $branch->phone ) : ?>
                    <tr>
                        <th style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e( 'ტელეფონი', 'wbim' ); ?></th>
                        <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html( $branch->phone ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php
        }
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
}
