<?php
/**
 * Admin Bulk Pricing Class
 *
 * Handles bulk/wholesale pricing fields in the product edit page.
 *
 * @package WBIM
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Bulk Pricing class
 *
 * @since 1.1.0
 */
class WBIM_Admin_Bulk_Pricing {

    /**
     * Number of pricing tiers
     *
     * @var int
     */
    private $num_tiers = 3;

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
        // Product data tab
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ), 70 );
        add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panel' ) );

        // Save simple product data
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ), 10 );

        // Variable product variation fields
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

        // Admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        if ( 'product' !== $post_type ) {
            return;
        }

        // Add inline styles for bulk pricing fields
        wp_add_inline_style( 'woocommerce_admin_styles', $this->get_admin_css() );
    }

    /**
     * Get admin CSS for bulk pricing fields
     *
     * @return string
     */
    private function get_admin_css() {
        return '
        #wbim_bulk_pricing_data .wbim-bulk-pricing-section {
            padding: 15px;
        }
        #wbim_bulk_pricing_data .wbim-bulk-pricing-header {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 12px 15px;
            margin: -15px -15px 20px -15px;
            font-weight: 600;
            font-size: 14px;
        }
        #wbim_bulk_pricing_data .wbim-bulk-pricing-checkboxes {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        #wbim_bulk_pricing_data .wbim-bulk-pricing-tier {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        #wbim_bulk_pricing_data .wbim-bulk-pricing-tier-label {
            grid-column: 1 / -1;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 5px;
            font-size: 13px;
        }
        #wbim_bulk_pricing_data .wbim-tier-field label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
            color: #50575e;
        }
        #wbim_bulk_pricing_data .wbim-tier-field input {
            width: 100%;
        }
        #wbim_bulk_pricing_data .wbim-bulk-pricing-help {
            color: #646970;
            font-size: 12px;
            font-style: italic;
            margin-top: 15px;
            padding: 10px;
            background: #fff8e5;
            border-left: 4px solid #dba617;
        }
        /* Variation bulk pricing styles */
        .wbim-variation-bulk-pricing {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .wbim-variation-bulk-pricing h4 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            color: #1d2327;
            font-size: 13px;
        }
        .wbim-variation-bulk-pricing .wbim-var-tier {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
        }
        .wbim-variation-bulk-pricing .wbim-var-tier-label {
            grid-column: 1 / -1;
            font-weight: 600;
            font-size: 12px;
            color: #50575e;
        }
        @media (max-width: 782px) {
            #wbim_bulk_pricing_data .wbim-bulk-pricing-tier,
            .wbim-variation-bulk-pricing .wbim-var-tier {
                grid-template-columns: 1fr;
            }
        }
        ';
    }

    /**
     * Add product data tab
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public function add_product_data_tab( $tabs ) {
        $tabs['wbim_bulk_pricing'] = array(
            'label'    => __( 'საბითუმო ფასები', 'wbim' ),
            'target'   => 'wbim_bulk_pricing_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 70,
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

        $product_id = $post->ID;

        ?>
        <div id="wbim_bulk_pricing_data" class="panel woocommerce_options_panel">
            <div class="wbim-bulk-pricing-section">
                <div class="wbim-bulk-pricing-header">
                    <?php esc_html_e( 'საბითუმო/რაოდენობაზე დაფუძნებული ფასდაკლება', 'wbim' ); ?>
                </div>

                <div class="wbim-bulk-pricing-checkboxes">
                    <?php
                    // Enable bulk pricing checkbox
                    woocommerce_wp_checkbox( array(
                        'id'          => '_wbim_enable_bulk_pricing',
                        'label'       => __( 'საბითუმო ფასების ჩართვა', 'wbim' ),
                        'description' => __( 'ჩართეთ რაოდენობაზე დაფუძნებული ფასდაკლება ამ პროდუქტისთვის', 'wbim' ),
                        'desc_tip'    => true,
                        'value'       => get_post_meta( $product_id, '_wbim_enable_bulk_pricing', true ),
                    ) );

                    // Enable quantity buttons checkbox
                    woocommerce_wp_checkbox( array(
                        'id'          => '_wbim_enable_qty_buttons',
                        'label'       => __( 'რაოდენობის ღილაკების ჩართვა', 'wbim' ),
                        'description' => __( 'აჩვენეთ სწრაფი არჩევის ღილაკები პროდუქტის გვერდზე', 'wbim' ),
                        'desc_tip'    => true,
                        'value'       => get_post_meta( $product_id, '_wbim_enable_qty_buttons', true ),
                    ) );

                    // Apply to all variations checkbox (only for variable products)
                    $product = wc_get_product( $product_id );
                    if ( $product && $product->is_type( 'variable' ) ) :
                    ?>
                    <div class="wbim-apply-variations-option" style="margin-top: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #2271b1; border-radius: 2px;">
                        <?php
                        woocommerce_wp_checkbox( array(
                            'id'          => '_wbim_apply_to_all_variations',
                            'label'       => __( 'ყველა ვარიაციაზე გავრცელება', 'wbim' ),
                            'description' => __( 'ზემოთ მითითებული საბითუმო ფასები გავრცელდება ყველა ვარიაციაზე შენახვისას', 'wbim' ),
                            'desc_tip'    => true,
                            'value'       => get_post_meta( $product_id, '_wbim_apply_to_all_variations', true ),
                        ) );
                        ?>
                        <p class="description" style="margin-left: 25px; color: #646970;">
                            <?php esc_html_e( 'შენიშვნა: ეს გადაწერს ვარიაციების არსებულ საბითუმო ფასებს.', 'wbim' ); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="wbim-bulk-pricing-tiers">
                    <?php
                    for ( $i = 1; $i <= $this->num_tiers; $i++ ) {
                        $qty   = get_post_meta( $product_id, '_wbim_bulk_qty_' . $i, true );
                        $price = get_post_meta( $product_id, '_wbim_bulk_price_' . $i, true );
                        ?>
                        <div class="wbim-bulk-pricing-tier">
                            <div class="wbim-bulk-pricing-tier-label">
                                <?php printf( esc_html__( 'საფეხური %d', 'wbim' ), $i ); ?>
                            </div>
                            <div class="wbim-tier-field">
                                <label for="_wbim_bulk_qty_<?php echo esc_attr( $i ); ?>">
                                    <?php esc_html_e( 'მინიმალური რაოდენობა', 'wbim' ); ?>
                                </label>
                                <input type="number"
                                       id="_wbim_bulk_qty_<?php echo esc_attr( $i ); ?>"
                                       name="_wbim_bulk_qty_<?php echo esc_attr( $i ); ?>"
                                       value="<?php echo esc_attr( $qty ); ?>"
                                       min="1"
                                       step="1"
                                       placeholder="<?php esc_attr_e( 'მაგ: 10', 'wbim' ); ?>">
                            </div>
                            <div class="wbim-tier-field">
                                <label for="_wbim_bulk_price_<?php echo esc_attr( $i ); ?>">
                                    <?php
                                    printf(
                                        esc_html__( 'ერთეულის ფასი (%s)', 'wbim' ),
                                        get_woocommerce_currency_symbol()
                                    );
                                    ?>
                                </label>
                                <input type="number"
                                       id="_wbim_bulk_price_<?php echo esc_attr( $i ); ?>"
                                       name="_wbim_bulk_price_<?php echo esc_attr( $i ); ?>"
                                       value="<?php echo esc_attr( $price ); ?>"
                                       min="0"
                                       step="any"
                                       placeholder="<?php esc_attr_e( 'მაგ: 9.50', 'wbim' ); ?>">
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="wbim-bulk-pricing-help">
                    <?php esc_html_e( 'მაგალითი: თუ საფეხური 1 = 10 ცალი @ ₾9.50, მაშინ 10 ან მეტი ცალის შეკვეთისას თითოეული ღირს ₾9.50.', 'wbim' ); ?>
                </div>
            </div>
        </div>
        <?php

        // Add nonce field
        wp_nonce_field( 'wbim_save_bulk_pricing', 'wbim_bulk_pricing_nonce' );
    }

    /**
     * Save simple product bulk pricing data
     *
     * @param int $post_id Product ID.
     * @return void
     */
    public function save_product_data( $post_id ) {
        // Check nonce
        if ( ! isset( $_POST['wbim_bulk_pricing_nonce'] ) ||
             ! wp_verify_nonce( $_POST['wbim_bulk_pricing_nonce'], 'wbim_save_bulk_pricing' ) ) {
            return;
        }

        // Check user permission
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Save enable bulk pricing
        $enable_bulk_pricing = isset( $_POST['_wbim_enable_bulk_pricing'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wbim_enable_bulk_pricing', $enable_bulk_pricing );

        // Save enable quantity buttons
        $enable_qty_buttons = isset( $_POST['_wbim_enable_qty_buttons'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wbim_enable_qty_buttons', $enable_qty_buttons );

        // Save apply to all variations setting
        $apply_to_variations = isset( $_POST['_wbim_apply_to_all_variations'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wbim_apply_to_all_variations', $apply_to_variations );

        // Collect tier data
        $tier_data = array();
        for ( $i = 1; $i <= $this->num_tiers; $i++ ) {
            $qty_key   = '_wbim_bulk_qty_' . $i;
            $price_key = '_wbim_bulk_price_' . $i;

            $qty   = isset( $_POST[ $qty_key ] ) ? sanitize_text_field( $_POST[ $qty_key ] ) : '';
            $price = isset( $_POST[ $price_key ] ) ? sanitize_text_field( $_POST[ $price_key ] ) : '';

            // Save quantity
            if ( '' !== $qty && is_numeric( $qty ) && floatval( $qty ) > 0 ) {
                update_post_meta( $post_id, $qty_key, absint( $qty ) );
                $tier_data[ $i ]['qty'] = absint( $qty );
            } else {
                delete_post_meta( $post_id, $qty_key );
            }

            // Save price
            if ( '' !== $price && is_numeric( $price ) && floatval( $price ) >= 0 ) {
                update_post_meta( $post_id, $price_key, wc_format_decimal( $price ) );
                $tier_data[ $i ]['price'] = wc_format_decimal( $price );
            } else {
                delete_post_meta( $post_id, $price_key );
            }
        }

        // Apply to all variations if checked
        if ( 'yes' === $apply_to_variations ) {
            $this->apply_bulk_pricing_to_variations( $post_id, $enable_bulk_pricing, $enable_qty_buttons, $tier_data );
        }
    }

    /**
     * Apply bulk pricing settings to all variations
     *
     * @param int    $product_id         Parent product ID.
     * @param string $enable_bulk        Enable bulk pricing value.
     * @param string $enable_buttons     Enable quantity buttons value.
     * @param array  $tier_data          Pricing tier data.
     * @return void
     */
    private function apply_bulk_pricing_to_variations( $product_id, $enable_bulk, $enable_buttons, $tier_data ) {
        $product = wc_get_product( $product_id );

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        $variations = $product->get_children();

        foreach ( $variations as $variation_id ) {
            // Apply enable settings
            update_post_meta( $variation_id, '_wbim_enable_bulk_pricing', $enable_bulk );
            update_post_meta( $variation_id, '_wbim_enable_qty_buttons', $enable_buttons );

            // Apply tier data
            for ( $i = 1; $i <= $this->num_tiers; $i++ ) {
                if ( isset( $tier_data[ $i ]['qty'] ) ) {
                    update_post_meta( $variation_id, '_wbim_bulk_qty_' . $i, $tier_data[ $i ]['qty'] );
                } else {
                    delete_post_meta( $variation_id, '_wbim_bulk_qty_' . $i );
                }

                if ( isset( $tier_data[ $i ]['price'] ) ) {
                    update_post_meta( $variation_id, '_wbim_bulk_price_' . $i, $tier_data[ $i ]['price'] );
                } else {
                    delete_post_meta( $variation_id, '_wbim_bulk_price_' . $i );
                }
            }
        }
    }

    /**
     * Add variation bulk pricing fields
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     * @return void
     */
    public function add_variation_fields( $loop, $variation_data, $variation ) {
        $variation_id = $variation->ID;

        ?>
        <div class="wbim-variation-bulk-pricing">
            <h4><?php esc_html_e( 'საბითუმო ფასები', 'wbim' ); ?></h4>

            <p class="form-row form-row-full">
                <label>
                    <input type="checkbox"
                           class="checkbox"
                           name="_wbim_var_enable_bulk_pricing[<?php echo esc_attr( $loop ); ?>]"
                           value="yes"
                           <?php checked( get_post_meta( $variation_id, '_wbim_enable_bulk_pricing', true ), 'yes' ); ?>>
                    <?php esc_html_e( 'საბითუმო ფასების ჩართვა', 'wbim' ); ?>
                </label>
            </p>

            <p class="form-row form-row-full">
                <label>
                    <input type="checkbox"
                           class="checkbox"
                           name="_wbim_var_enable_qty_buttons[<?php echo esc_attr( $loop ); ?>]"
                           value="yes"
                           <?php checked( get_post_meta( $variation_id, '_wbim_enable_qty_buttons', true ), 'yes' ); ?>>
                    <?php esc_html_e( 'რაოდენობის ღილაკების ჩართვა', 'wbim' ); ?>
                </label>
            </p>

            <?php
            for ( $i = 1; $i <= $this->num_tiers; $i++ ) {
                $qty   = get_post_meta( $variation_id, '_wbim_bulk_qty_' . $i, true );
                $price = get_post_meta( $variation_id, '_wbim_bulk_price_' . $i, true );
                ?>
                <div class="wbim-var-tier">
                    <div class="wbim-var-tier-label">
                        <?php printf( esc_html__( 'საფეხური %d', 'wbim' ), $i ); ?>
                    </div>
                    <p class="form-row">
                        <label><?php esc_html_e( 'მინ. რაოდენობა', 'wbim' ); ?></label>
                        <input type="number"
                               name="_wbim_var_bulk_qty_<?php echo esc_attr( $i ); ?>[<?php echo esc_attr( $loop ); ?>]"
                               value="<?php echo esc_attr( $qty ); ?>"
                               min="1"
                               step="1"
                               class="short">
                    </p>
                    <p class="form-row">
                        <label><?php printf( esc_html__( 'ფასი (%s)', 'wbim' ), get_woocommerce_currency_symbol() ); ?></label>
                        <input type="number"
                               name="_wbim_var_bulk_price_<?php echo esc_attr( $i ); ?>[<?php echo esc_attr( $loop ); ?>]"
                               value="<?php echo esc_attr( $price ); ?>"
                               min="0"
                               step="any"
                               class="short wc_input_price">
                    </p>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Save variation bulk pricing fields
     *
     * @param int $variation_id Variation ID.
     * @param int $loop         Variation loop index.
     * @return void
     */
    public function save_variation_fields( $variation_id, $loop ) {
        // Check user permission
        if ( ! current_user_can( 'edit_post', $variation_id ) ) {
            return;
        }

        // Save enable bulk pricing
        $enable_bulk_pricing = isset( $_POST['_wbim_var_enable_bulk_pricing'][ $loop ] ) ? 'yes' : 'no';
        update_post_meta( $variation_id, '_wbim_enable_bulk_pricing', $enable_bulk_pricing );

        // Save enable quantity buttons
        $enable_qty_buttons = isset( $_POST['_wbim_var_enable_qty_buttons'][ $loop ] ) ? 'yes' : 'no';
        update_post_meta( $variation_id, '_wbim_enable_qty_buttons', $enable_qty_buttons );

        // Save pricing tiers
        for ( $i = 1; $i <= $this->num_tiers; $i++ ) {
            $qty_key   = '_wbim_var_bulk_qty_' . $i;
            $price_key = '_wbim_var_bulk_price_' . $i;

            // Save quantity
            if ( isset( $_POST[ $qty_key ][ $loop ] ) ) {
                $qty = sanitize_text_field( $_POST[ $qty_key ][ $loop ] );
                if ( '' !== $qty && is_numeric( $qty ) && floatval( $qty ) > 0 ) {
                    update_post_meta( $variation_id, '_wbim_bulk_qty_' . $i, absint( $qty ) );
                } else {
                    delete_post_meta( $variation_id, '_wbim_bulk_qty_' . $i );
                }
            }

            // Save price
            if ( isset( $_POST[ $price_key ][ $loop ] ) ) {
                $price = sanitize_text_field( $_POST[ $price_key ][ $loop ] );
                if ( '' !== $price && is_numeric( $price ) && floatval( $price ) >= 0 ) {
                    update_post_meta( $variation_id, '_wbim_bulk_price_' . $i, wc_format_decimal( $price ) );
                } else {
                    delete_post_meta( $variation_id, '_wbim_bulk_price_' . $i );
                }
            }
        }
    }

    /**
     * Get bulk pricing tiers for a product
     *
     * @param int $product_id Product or variation ID.
     * @return array
     */
    public static function get_bulk_pricing_tiers( $product_id ) {
        $tiers = array();

        for ( $i = 1; $i <= 3; $i++ ) {
            $qty   = get_post_meta( $product_id, '_wbim_bulk_qty_' . $i, true );
            $price = get_post_meta( $product_id, '_wbim_bulk_price_' . $i, true );

            if ( $qty && $price && intval( $qty ) > 0 && floatval( $price ) >= 0 ) {
                $tiers[] = array(
                    'qty'   => intval( $qty ),
                    'price' => floatval( $price ),
                );
            }
        }

        // Sort by quantity descending
        usort( $tiers, function( $a, $b ) {
            return $b['qty'] - $a['qty'];
        });

        return $tiers;
    }

    /**
     * Check if bulk pricing is enabled for a product
     *
     * @param int $product_id Product or variation ID.
     * @return bool
     */
    public static function is_bulk_pricing_enabled( $product_id ) {
        return 'yes' === get_post_meta( $product_id, '_wbim_enable_bulk_pricing', true );
    }

    /**
     * Check if quantity buttons are enabled for a product
     *
     * @param int $product_id Product or variation ID.
     * @return bool
     */
    public static function are_qty_buttons_enabled( $product_id ) {
        return 'yes' === get_post_meta( $product_id, '_wbim_enable_qty_buttons', true );
    }
}
