<?php
/**
 * Admin Settings Class
 *
 * Handles the settings page in the admin area.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Settings class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Settings {

    /**
     * Settings option key
     *
     * @var string
     */
    private $option_key = 'wbim_settings';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_wbim_save_settings', array( $this, 'ajax_save_settings' ) );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'wbim_settings_group',
            $this->option_key,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // General settings
        $sanitized['low_stock_threshold'] = isset( $input['low_stock_threshold'] ) ? absint( $input['low_stock_threshold'] ) : 5;
        $sanitized['default_branch_id'] = isset( $input['default_branch_id'] ) ? absint( $input['default_branch_id'] ) : 0;
        $sanitized['remove_data_on_uninstall'] = isset( $input['remove_data_on_uninstall'] ) ? (bool) $input['remove_data_on_uninstall'] : false;

        // Display settings
        $sanitized['show_branch_stock_product'] = isset( $input['show_branch_stock_product'] ) ? 'yes' : 'no';
        $sanitized['show_branch_stock_archive'] = isset( $input['show_branch_stock_archive'] ) ? 'yes' : 'no';
        $sanitized['show_exact_quantity'] = isset( $input['show_exact_quantity'] ) ? 'yes' : 'no';
        $sanitized['show_branch_in_cart'] = isset( $input['show_branch_in_cart'] ) ? 'yes' : 'no';
        $sanitized['show_branch_contact'] = isset( $input['show_branch_contact'] ) ? 'yes' : 'no';

        // Checkout settings
        $sanitized['enable_checkout_selection'] = isset( $input['enable_checkout_selection'] ) ? 'yes' : 'no';
        $sanitized['branch_selector_type'] = isset( $input['branch_selector_type'] ) ? sanitize_text_field( $input['branch_selector_type'] ) : 'dropdown';
        $sanitized['auto_select_method'] = isset( $input['auto_select_method'] ) ? sanitize_text_field( $input['auto_select_method'] ) : 'most_stock';
        $sanitized['show_stock_at_checkout'] = isset( $input['show_stock_at_checkout'] ) ? 'yes' : 'no';
        $sanitized['require_branch_selection'] = isset( $input['require_branch_selection'] ) ? 'yes' : 'no';

        // Notification settings
        $sanitized['enable_transfer_notifications'] = isset( $input['enable_transfer_notifications'] ) ? 'yes' : 'no';
        $sanitized['enable_low_stock_notifications'] = isset( $input['enable_low_stock_notifications'] ) ? 'yes' : 'no';
        $sanitized['enable_order_notifications'] = isset( $input['enable_order_notifications'] ) ? 'yes' : 'no';

        // API settings
        $sanitized['enable_api'] = isset( $input['enable_api'] ) ? (bool) $input['enable_api'] : true;

        // PDF/Export settings
        $sanitized['company_name'] = isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : '';
        $sanitized['pdf_footer_text'] = isset( $input['pdf_footer_text'] ) ? sanitize_textarea_field( $input['pdf_footer_text'] ) : '';

        // Map settings
        $sanitized['google_maps_api_key'] = isset( $input['google_maps_api_key'] ) ? sanitize_text_field( $input['google_maps_api_key'] ) : '';

        return $sanitized;
    }

    /**
     * AJAX handler for saving settings
     *
     * @return void
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        $sanitized = $this->sanitize_settings( $settings );

        update_option( $this->option_key, $sanitized );

        wp_send_json_success( array( 'message' => __( 'პარამეტრები შენახულია!', 'wbim' ) ) );
    }

    /**
     * Get settings
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'low_stock_threshold'           => 5,
            'default_branch_id'             => 0,
            'remove_data_on_uninstall'      => false,
            'show_branch_stock_product'     => 'yes',
            'show_branch_stock_archive'     => 'no',
            'show_exact_quantity'           => 'no',
            'show_branch_in_cart'           => 'yes',
            'show_branch_contact'           => 'no',
            'enable_checkout_selection'     => 'yes',
            'branch_selector_type'          => 'dropdown',
            'auto_select_method'            => 'most_stock',
            'show_stock_at_checkout'        => 'yes',
            'require_branch_selection'      => 'no',
            'enable_transfer_notifications' => 'yes',
            'enable_low_stock_notifications'=> 'yes',
            'enable_order_notifications'    => 'yes',
            'enable_api'                    => true,
            'company_name'                  => get_bloginfo( 'name' ),
            'pdf_footer_text'               => '',
            'google_maps_api_key'           => '',
        );

        $settings = get_option( $this->option_key, array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        // Handle form submission
        if ( isset( $_POST['wbim_settings_submit'] ) && check_admin_referer( 'wbim_settings_nonce' ) ) {
            $settings = isset( $_POST['wbim_settings'] ) ? wp_unslash( $_POST['wbim_settings'] ) : array();
            $sanitized = $this->sanitize_settings( $settings );
            update_option( $this->option_key, $sanitized );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'პარამეტრები შენახულია!', 'wbim' ) . '</p></div>';
        }

        $settings = $this->get_settings();
        $branches = WBIM_Branch::get_active();
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        $tabs = array(
            'general'       => __( 'ზოგადი', 'wbim' ),
            'display'       => __( 'ჩვენება', 'wbim' ),
            'checkout'      => __( 'გადახდა', 'wbim' ),
            'notifications' => __( 'შეტყობინებები', 'wbim' ),
            'api'           => __( 'API', 'wbim' ),
            'export'        => __( 'ექსპორტი/PDF', 'wbim' ),
        );

        ?>
        <div class="wrap wbim-settings-wrap">
            <h1><?php esc_html_e( 'პარამეტრები', 'wbim' ); ?></h1>

            <nav class="nav-tab-wrapper wbim-nav-tabs">
                <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-settings&tab=' . $tab_id ) ); ?>"
                       class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="" class="wbim-settings-form">
                <?php wp_nonce_field( 'wbim_settings_nonce' ); ?>

                <div class="wbim-settings-content">
                    <?php
                    switch ( $tab ) {
                        case 'display':
                            $this->render_display_settings( $settings );
                            break;
                        case 'checkout':
                            $this->render_checkout_settings( $settings, $branches );
                            break;
                        case 'notifications':
                            $this->render_notification_settings( $settings );
                            break;
                        case 'api':
                            $this->render_api_settings( $settings );
                            break;
                        case 'export':
                            $this->render_export_settings( $settings );
                            break;
                        default:
                            $this->render_general_settings( $settings, $branches );
                            break;
                    }
                    ?>
                </div>

                <p class="submit">
                    <button type="submit" name="wbim_settings_submit" class="button button-primary">
                        <?php esc_html_e( 'პარამეტრების შენახვა', 'wbim' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <style>
            .wbim-settings-wrap {
                max-width: 1200px;
            }
            .wbim-settings-form {
                background: #fff;
                padding: 20px;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .wbim-settings-section {
                margin-bottom: 30px;
            }
            .wbim-settings-section h2 {
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                margin-bottom: 20px;
            }
            .wbim-settings-row {
                display: flex;
                align-items: flex-start;
                margin-bottom: 15px;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .wbim-settings-row:last-child {
                border-bottom: none;
            }
            .wbim-settings-label {
                width: 250px;
                font-weight: 600;
                padding-top: 5px;
            }
            .wbim-settings-field {
                flex: 1;
            }
            .wbim-settings-field input[type="text"],
            .wbim-settings-field input[type="number"],
            .wbim-settings-field select,
            .wbim-settings-field textarea {
                width: 100%;
                max-width: 400px;
            }
            .wbim-settings-field textarea {
                min-height: 80px;
            }
            .wbim-settings-description {
                color: #666;
                font-size: 12px;
                margin-top: 5px;
            }
            .wbim-settings-checkbox {
                display: flex;
                align-items: center;
            }
            .wbim-settings-checkbox input[type="checkbox"] {
                margin-right: 8px;
            }
            .wbim-api-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                margin-top: 20px;
            }
            .wbim-api-info h3 {
                margin-top: 0;
            }
            .wbim-api-info code {
                display: block;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
                margin: 5px 0;
            }
        </style>
        <?php
    }

    /**
     * Render general settings
     *
     * @param array $settings Current settings.
     * @param array $branches Available branches.
     * @return void
     */
    private function render_general_settings( $settings, $branches ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'ზოგადი პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="low_stock_threshold"><?php esc_html_e( 'დაბალი მარაგის ზღვარი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <input type="number" id="low_stock_threshold" name="wbim_settings[low_stock_threshold]"
                           value="<?php echo esc_attr( $settings['low_stock_threshold'] ); ?>" min="0" step="1">
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'როდის ჩაითვალოს მარაგი დაბალად (ნაგულისხმევი მნიშვნელობა ყველა პროდუქტისთვის)', 'wbim' ); ?>
                    </p>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="default_branch_id"><?php esc_html_e( 'ნაგულისხმევი ფილიალი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <select id="default_branch_id" name="wbim_settings[default_branch_id]">
                        <option value="0"><?php esc_html_e( 'არცერთი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>"
                                <?php selected( $settings['default_branch_id'], $branch->id ); ?>>
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'ფილიალი რომელიც ავტომატურად შეირჩევა გადახდისას', 'wbim' ); ?>
                    </p>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="remove_data_on_uninstall"><?php esc_html_e( 'მონაცემების წაშლა', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="remove_data_on_uninstall" name="wbim_settings[remove_data_on_uninstall]"
                               value="1" <?php checked( $settings['remove_data_on_uninstall'], true ); ?>>
                        <label for="remove_data_on_uninstall">
                            <?php esc_html_e( 'წაიშალოს ყველა მონაცემი პლაგინის წაშლისას', 'wbim' ); ?>
                        </label>
                    </div>
                    <p class="wbim-settings-description" style="color: #dc3545;">
                        <?php esc_html_e( 'გაფრთხილება: ეს მოქმედება წაშლის ყველა ფილიალს, მარაგს და გადატანას!', 'wbim' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render display settings
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_display_settings( $settings ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'ჩვენების პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_branch_stock_product"><?php esc_html_e( 'მარაგი პროდუქტის გვერდზე', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_branch_stock_product" name="wbim_settings[show_branch_stock_product]"
                               value="yes" <?php checked( $settings['show_branch_stock_product'], 'yes' ); ?>>
                        <label for="show_branch_stock_product">
                            <?php esc_html_e( 'აჩვენე ფილიალების მარაგი პროდუქტის გვერდზე', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_branch_stock_archive"><?php esc_html_e( 'მარაგი არქივში', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_branch_stock_archive" name="wbim_settings[show_branch_stock_archive]"
                               value="yes" <?php checked( $settings['show_branch_stock_archive'], 'yes' ); ?>>
                        <label for="show_branch_stock_archive">
                            <?php esc_html_e( 'აჩვენე მარაგი კატეგორიის/მაღაზიის გვერდებზე', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_exact_quantity"><?php esc_html_e( 'ზუსტი რაოდენობა', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_exact_quantity" name="wbim_settings[show_exact_quantity]"
                               value="yes" <?php checked( $settings['show_exact_quantity'], 'yes' ); ?>>
                        <label for="show_exact_quantity">
                            <?php esc_html_e( 'აჩვენე ზუსტი რაოდენობა ("5 მარაგში" ნაცვლად "მარაგშია")', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_branch_in_cart"><?php esc_html_e( 'ფილიალი კალათაში', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_branch_in_cart" name="wbim_settings[show_branch_in_cart]"
                               value="yes" <?php checked( $settings['show_branch_in_cart'], 'yes' ); ?>>
                        <label for="show_branch_in_cart">
                            <?php esc_html_e( 'აჩვენე არჩეული ფილიალი კალათაში', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_branch_contact"><?php esc_html_e( 'ფილიალის კონტაქტი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_branch_contact" name="wbim_settings[show_branch_contact]"
                               value="yes" <?php checked( $settings['show_branch_contact'], 'yes' ); ?>>
                        <label for="show_branch_contact">
                            <?php esc_html_e( 'აჩვენე ფილიალის საკონტაქტო ინფორმაცია პროდუქტის გვერდზე', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render checkout settings
     *
     * @param array $settings Current settings.
     * @param array $branches Available branches.
     * @return void
     */
    private function render_checkout_settings( $settings, $branches ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'გადახდის პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="enable_checkout_selection"><?php esc_html_e( 'ფილიალის არჩევა', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="enable_checkout_selection" name="wbim_settings[enable_checkout_selection]"
                               value="yes" <?php checked( $settings['enable_checkout_selection'], 'yes' ); ?>>
                        <label for="enable_checkout_selection">
                            <?php esc_html_e( 'მომხმარებელს შეეძლოს ფილიალის არჩევა გადახდისას', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="branch_selector_type"><?php esc_html_e( 'არჩევის ტიპი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <select id="branch_selector_type" name="wbim_settings[branch_selector_type]">
                        <option value="dropdown" <?php selected( $settings['branch_selector_type'], 'dropdown' ); ?>>
                            <?php esc_html_e( 'ჩამოსაშლელი მენიუ', 'wbim' ); ?>
                        </option>
                        <option value="radio" <?php selected( $settings['branch_selector_type'], 'radio' ); ?>>
                            <?php esc_html_e( 'რადიო ღილაკები', 'wbim' ); ?>
                        </option>
                        <option value="map" <?php selected( $settings['branch_selector_type'], 'map' ); ?>>
                            <?php esc_html_e( 'რუკა (Google Maps)', 'wbim' ); ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="auto_select_method"><?php esc_html_e( 'ავტომატური არჩევა', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <select id="auto_select_method" name="wbim_settings[auto_select_method]">
                        <option value="most_stock" <?php selected( $settings['auto_select_method'], 'most_stock' ); ?>>
                            <?php esc_html_e( 'ყველაზე მეტი მარაგი', 'wbim' ); ?>
                        </option>
                        <option value="nearest" <?php selected( $settings['auto_select_method'], 'nearest' ); ?>>
                            <?php esc_html_e( 'უახლოესი ფილიალი', 'wbim' ); ?>
                        </option>
                        <option value="default" <?php selected( $settings['auto_select_method'], 'default' ); ?>>
                            <?php esc_html_e( 'ნაგულისხმევი ფილიალი', 'wbim' ); ?>
                        </option>
                        <option value="none" <?php selected( $settings['auto_select_method'], 'none' ); ?>>
                            <?php esc_html_e( 'არაფერი (მომხმარებელმა უნდა აირჩიოს)', 'wbim' ); ?>
                        </option>
                    </select>
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'როგორ შეირჩეს ფილიალი ავტომატურად', 'wbim' ); ?>
                    </p>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="show_stock_at_checkout"><?php esc_html_e( 'მარაგი გადახდისას', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="show_stock_at_checkout" name="wbim_settings[show_stock_at_checkout]"
                               value="yes" <?php checked( $settings['show_stock_at_checkout'], 'yes' ); ?>>
                        <label for="show_stock_at_checkout">
                            <?php esc_html_e( 'აჩვენე მარაგი ფილიალის გვერდით გადახდისას', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="require_branch_selection"><?php esc_html_e( 'სავალდებულო არჩევა', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="require_branch_selection" name="wbim_settings[require_branch_selection]"
                               value="yes" <?php checked( $settings['require_branch_selection'], 'yes' ); ?>>
                        <label for="require_branch_selection">
                            <?php esc_html_e( 'ფილიალის არჩევა სავალდებულოა შეკვეთის გასაფორმებლად', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="google_maps_api_key"><?php esc_html_e( 'Google Maps API Key', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <input type="text" id="google_maps_api_key" name="wbim_settings[google_maps_api_key]"
                           value="<?php echo esc_attr( $settings['google_maps_api_key'] ); ?>" placeholder="AIza...">
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'საჭიროა რუკის ტიპის არჩევისთვის', 'wbim' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render notification settings
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_notification_settings( $settings ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'შეტყობინებების პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="enable_transfer_notifications"><?php esc_html_e( 'გადატანის შეტყობინებები', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="enable_transfer_notifications" name="wbim_settings[enable_transfer_notifications]"
                               value="yes" <?php checked( $settings['enable_transfer_notifications'], 'yes' ); ?>>
                        <label for="enable_transfer_notifications">
                            <?php esc_html_e( 'შეატყობინე ფილიალის მენეჯერს ახალი გადატანის შესახებ', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="enable_low_stock_notifications"><?php esc_html_e( 'დაბალი მარაგის შეტყობინებები', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="enable_low_stock_notifications" name="wbim_settings[enable_low_stock_notifications]"
                               value="yes" <?php checked( $settings['enable_low_stock_notifications'], 'yes' ); ?>>
                        <label for="enable_low_stock_notifications">
                            <?php esc_html_e( 'შეატყობინე როცა მარაგი დაბალია', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="enable_order_notifications"><?php esc_html_e( 'შეკვეთის შეტყობინებები', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="enable_order_notifications" name="wbim_settings[enable_order_notifications]"
                               value="yes" <?php checked( $settings['enable_order_notifications'], 'yes' ); ?>>
                        <label for="enable_order_notifications">
                            <?php esc_html_e( 'შეატყობინე ფილიალის მენეჯერს ახალი შეკვეთის შესახებ', 'wbim' ); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render API settings
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_api_settings( $settings ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'API პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="enable_api"><?php esc_html_e( 'REST API', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <div class="wbim-settings-checkbox">
                        <input type="checkbox" id="enable_api" name="wbim_settings[enable_api]"
                               value="1" <?php checked( $settings['enable_api'], true ); ?>>
                        <label for="enable_api">
                            <?php esc_html_e( 'ჩართე REST API', 'wbim' ); ?>
                        </label>
                    </div>
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'საშუალებას აძლევს გარე აპლიკაციებს წვდომა ჰქონდეს ფილიალების მონაცემებზე', 'wbim' ); ?>
                    </p>
                </div>
            </div>

            <div class="wbim-api-info">
                <h3><?php esc_html_e( 'API Endpoints', 'wbim' ); ?></h3>
                <p><?php esc_html_e( 'ხელმისაწვდომი endpoints:', 'wbim' ); ?></p>

                <h4><?php esc_html_e( 'ფილიალები', 'wbim' ); ?></h4>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/branches' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/branches/{id}' ) ); ?></code>
                <code>POST <?php echo esc_html( rest_url( 'wbim/v1/branches' ) ); ?></code>
                <code>PUT <?php echo esc_html( rest_url( 'wbim/v1/branches/{id}' ) ); ?></code>
                <code>DELETE <?php echo esc_html( rest_url( 'wbim/v1/branches/{id}' ) ); ?></code>

                <h4><?php esc_html_e( 'მარაგი', 'wbim' ); ?></h4>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/stock' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/stock/product/{id}' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/stock/branch/{id}' ) ); ?></code>
                <code>POST <?php echo esc_html( rest_url( 'wbim/v1/stock' ) ); ?></code>
                <code>PUT <?php echo esc_html( rest_url( 'wbim/v1/stock/adjust' ) ); ?></code>

                <h4><?php esc_html_e( 'გადატანები', 'wbim' ); ?></h4>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/transfers' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/transfers/{id}' ) ); ?></code>
                <code>POST <?php echo esc_html( rest_url( 'wbim/v1/transfers' ) ); ?></code>
                <code>PUT <?php echo esc_html( rest_url( 'wbim/v1/transfers/{id}/status' ) ); ?></code>

                <h4><?php esc_html_e( 'რეპორტები', 'wbim' ); ?></h4>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/reports/stock' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/reports/sales' ) ); ?></code>
                <code>GET <?php echo esc_html( rest_url( 'wbim/v1/reports/low-stock' ) ); ?></code>

                <p style="margin-top: 15px;">
                    <strong><?php esc_html_e( 'ავტორიზაცია:', 'wbim' ); ?></strong>
                    <?php esc_html_e( 'გამოიყენეთ WooCommerce REST API ავტორიზაცია (Consumer Key და Consumer Secret)', 'wbim' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render export/PDF settings
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_export_settings( $settings ) {
        ?>
        <div class="wbim-settings-section">
            <h2><?php esc_html_e( 'ექსპორტი და PDF პარამეტრები', 'wbim' ); ?></h2>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="company_name"><?php esc_html_e( 'კომპანიის სახელი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <input type="text" id="company_name" name="wbim_settings[company_name]"
                           value="<?php echo esc_attr( $settings['company_name'] ); ?>">
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'გამოჩნდება PDF დოკუმენტების header-ში', 'wbim' ); ?>
                    </p>
                </div>
            </div>

            <div class="wbim-settings-row">
                <div class="wbim-settings-label">
                    <label for="pdf_footer_text"><?php esc_html_e( 'PDF Footer ტექსტი', 'wbim' ); ?></label>
                </div>
                <div class="wbim-settings-field">
                    <textarea id="pdf_footer_text" name="wbim_settings[pdf_footer_text]"><?php echo esc_textarea( $settings['pdf_footer_text'] ); ?></textarea>
                    <p class="wbim-settings-description">
                        <?php esc_html_e( 'დამატებითი ტექსტი PDF დოკუმენტის ბოლოს', 'wbim' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
