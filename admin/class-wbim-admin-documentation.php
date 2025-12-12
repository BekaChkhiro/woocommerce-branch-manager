<?php
/**
 * Admin Documentation Class
 *
 * Handles the documentation/help page in the admin area.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Documentation class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Documentation {

    /**
     * Constructor
     */
    public function __construct() {
        // Nothing to initialize
    }

    /**
     * Render documentation page
     *
     * @return void
     */
    public function render_documentation_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wbim' ) );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

        $tabs = array(
            'overview'    => __( 'მიმოხილვა', 'wbim' ),
            'branches'    => __( 'ფილიალები', 'wbim' ),
            'stock'       => __( 'მარაგის მართვა', 'wbim' ),
            'transfers'   => __( 'გადატანები', 'wbim' ),
            'orders'      => __( 'შეკვეთები', 'wbim' ),
            'reports'     => __( 'რეპორტები', 'wbim' ),
            'api'         => __( 'REST API', 'wbim' ),
            'roles'       => __( 'როლები და უფლებები', 'wbim' ),
            'faq'         => __( 'ხშირი კითხვები', 'wbim' ),
        );

        ?>
        <div class="wrap wbim-documentation-wrap">
            <h1>
                <span class="dashicons dashicons-book-alt" style="font-size: 30px; margin-right: 10px;"></span>
                <?php esc_html_e( 'დოკუმენტაცია და ინსტრუქცია', 'wbim' ); ?>
            </h1>

            <nav class="nav-tab-wrapper wbim-nav-tabs">
                <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-documentation&tab=' . $tab_id ) ); ?>"
                       class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="wbim-documentation-content">
                <?php
                switch ( $tab ) {
                    case 'branches':
                        $this->render_branches_docs();
                        break;
                    case 'stock':
                        $this->render_stock_docs();
                        break;
                    case 'transfers':
                        $this->render_transfers_docs();
                        break;
                    case 'orders':
                        $this->render_orders_docs();
                        break;
                    case 'reports':
                        $this->render_reports_docs();
                        break;
                    case 'api':
                        $this->render_api_docs();
                        break;
                    case 'roles':
                        $this->render_roles_docs();
                        break;
                    case 'faq':
                        $this->render_faq_docs();
                        break;
                    default:
                        $this->render_overview_docs();
                        break;
                }
                ?>
            </div>
        </div>

        <style>
            .wbim-documentation-wrap {
                max-width: 1200px;
            }
            .wbim-documentation-content {
                background: #fff;
                padding: 30px;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .wbim-doc-section {
                margin-bottom: 40px;
            }
            .wbim-doc-section h2 {
                color: #1d2327;
                border-bottom: 2px solid #2271b1;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .wbim-doc-section h3 {
                color: #2271b1;
                margin-top: 25px;
            }
            .wbim-doc-section p {
                font-size: 14px;
                line-height: 1.8;
                color: #50575e;
            }
            .wbim-doc-section ul, .wbim-doc-section ol {
                margin-left: 20px;
                line-height: 1.8;
            }
            .wbim-doc-section li {
                margin-bottom: 8px;
            }
            .wbim-doc-note {
                background: #fff8e5;
                border-left: 4px solid #dba617;
                padding: 15px 20px;
                margin: 20px 0;
            }
            .wbim-doc-note.info {
                background: #e7f3ff;
                border-left-color: #2271b1;
            }
            .wbim-doc-note.success {
                background: #e7f7e7;
                border-left-color: #00a32a;
            }
            .wbim-doc-note.warning {
                background: #fcf0f1;
                border-left-color: #d63638;
            }
            .wbim-doc-code {
                background: #f0f0f1;
                padding: 15px 20px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 13px;
                overflow-x: auto;
                margin: 15px 0;
            }
            .wbim-doc-code pre {
                margin: 0;
                white-space: pre-wrap;
            }
            .wbim-doc-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .wbim-doc-table th,
            .wbim-doc-table td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: left;
            }
            .wbim-doc-table th {
                background: #f6f7f7;
                font-weight: 600;
            }
            .wbim-doc-table tr:nth-child(even) {
                background: #fafafa;
            }
            .wbim-doc-steps {
                counter-reset: step-counter;
                list-style: none;
                margin-left: 0;
                padding-left: 0;
            }
            .wbim-doc-steps li {
                counter-increment: step-counter;
                position: relative;
                padding-left: 50px;
                margin-bottom: 20px;
            }
            .wbim-doc-steps li::before {
                content: counter(step-counter);
                position: absolute;
                left: 0;
                top: 0;
                width: 30px;
                height: 30px;
                background: #2271b1;
                color: #fff;
                border-radius: 50%;
                text-align: center;
                line-height: 30px;
                font-weight: bold;
            }
            .wbim-doc-feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .wbim-doc-feature-card {
                background: #f6f7f7;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ddd;
            }
            .wbim-doc-feature-card h4 {
                margin-top: 0;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .wbim-doc-feature-card .dashicons {
                color: #2271b1;
            }
            .wbim-doc-screenshot {
                max-width: 100%;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin: 15px 0;
            }
            .wbim-doc-workflow {
                display: flex;
                align-items: center;
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
                margin: 20px 0;
                padding: 20px;
                background: #f6f7f7;
                border-radius: 8px;
            }
            .wbim-doc-workflow-step {
                background: #fff;
                padding: 10px 20px;
                border-radius: 20px;
                border: 2px solid #2271b1;
                font-weight: 500;
            }
            .wbim-doc-workflow-arrow {
                color: #2271b1;
                font-size: 20px;
            }
            .wbim-faq-item {
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
            }
            .wbim-faq-question {
                background: #f6f7f7;
                padding: 15px 20px;
                cursor: pointer;
                font-weight: 600;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .wbim-faq-question:hover {
                background: #f0f0f1;
            }
            .wbim-faq-answer {
                padding: 20px;
                display: none;
                border-top: 1px solid #ddd;
            }
            .wbim-faq-item.active .wbim-faq-answer {
                display: block;
            }
            .wbim-faq-item.active .wbim-faq-toggle {
                transform: rotate(180deg);
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                $('.wbim-faq-question').on('click', function() {
                    $(this).closest('.wbim-faq-item').toggleClass('active');
                });
            });
        </script>
        <?php
    }

    /**
     * Render overview documentation
     *
     * @return void
     */
    private function render_overview_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'WooCommerce Branch Inventory Manager - მიმოხილვა', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'WooCommerce Branch Inventory Manager არის მძლავრი პლაგინი, რომელიც საშუალებას გაძლევთ მართოთ მარაგი რამდენიმე ფილიალში ან ლოკაციაზე. იდეალურია მაღაზიათა ქსელებისთვის, საწყობებისთვის და დისტრიბუციის ცენტრებისთვის.', 'wbim' ); ?></p>

            <div class="wbim-doc-feature-grid">
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-store"></span> <?php esc_html_e( 'ფილიალების მართვა', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'შექმენით და მართეთ შეუზღუდავი რაოდენობის ფილიალები, მიუთითეთ მისამართი, კონტაქტი და მენეჯერი.', 'wbim' ); ?></p>
                </div>
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-archive"></span> <?php esc_html_e( 'მარაგის თვალყურის დევნა', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'აკონტროლეთ მარაგი თითოეულ ფილიალში, დააყენეთ დაბალი მარაგის ზღვარი და მიიღეთ შეტყობინებები.', 'wbim' ); ?></p>
                </div>
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-randomize"></span> <?php esc_html_e( 'გადატანის სისტემა', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'გადაიტანეთ პროდუქტები ფილიალებს შორის, თვალი ადევნეთ სტატუსს და დაბეჭდეთ დოკუმენტები.', 'wbim' ); ?></p>
                </div>
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'რეპორტები და ანალიტიკა', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'დეტალური რეპორტები მარაგის, გაყიდვებისა და გადატანების შესახებ CSV და PDF ექსპორტით.', 'wbim' ); ?></p>
                </div>
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'როლები და უფლებები', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'ფილიალის მენეჯერის როლი, მორგებული უფლებები და წვდომის კონტროლი.', 'wbim' ); ?></p>
                </div>
                <div class="wbim-doc-feature-card">
                    <h4><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'REST API', 'wbim' ); ?></h4>
                    <p><?php esc_html_e( 'სრული API ინტეგრაციისთვის გარე სისტემებთან და მობილურ აპლიკაციებთან.', 'wbim' ); ?></p>
                </div>
            </div>

            <h3><?php esc_html_e( 'სწრაფი დაწყება', 'wbim' ); ?></h3>
            <ol class="wbim-doc-steps">
                <li>
                    <strong><?php esc_html_e( 'შექმენით ფილიალები', 'wbim' ); ?></strong><br>
                    <?php esc_html_e( 'გადადით "ფილიალები" მენიუში და დაამატეთ თქვენი მაღაზიები ან საწყობები.', 'wbim' ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'დააყენეთ მარაგი', 'wbim' ); ?></strong><br>
                    <?php esc_html_e( 'გადადით "მარაგები" განყოფილებაში და მიუთითეთ რამდენი პროდუქტია თითოეულ ფილიალში.', 'wbim' ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'დააკონფიგურირეთ პარამეტრები', 'wbim' ); ?></strong><br>
                    <?php esc_html_e( 'გადადით "პარამეტრები" განყოფილებაში და დააყენეთ checkout-ის, შეტყობინებებისა და ჩვენების პარამეტრები.', 'wbim' ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'ტესტირება', 'wbim' ); ?></strong><br>
                    <?php esc_html_e( 'შექმენით სატესტო შეკვეთა და დარწმუნდით, რომ ფილიალის არჩევა მუშაობს სწორად.', 'wbim' ); ?>
                </li>
            </ol>

            <div class="wbim-doc-note info">
                <strong><?php esc_html_e( 'რჩევა:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'დაიწყეთ რამდენიმე პროდუქტით და ფილიალით, რათა გაიგოთ როგორ მუშაობს სისტემა, შემდეგ დაამატეთ დანარჩენი.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render branches documentation
     *
     * @return void
     */
    private function render_branches_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'ფილიალების მართვა', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'ფილიალი წარმოადგენს ფიზიკურ ლოკაციას სადაც ინახება პროდუქცია - მაღაზია, საწყობი, დისტრიბუციის ცენტრი და ა.შ.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'ფილიალის შექმნა', 'wbim' ); ?></h3>
            <ol class="wbim-doc-steps">
                <li><?php esc_html_e( 'გადადით მენიუში "ფილიალები" → "ფილიალები"', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'დააჭირეთ ღილაკს "ფილიალის დამატება"', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'შეავსეთ საჭირო ველები და შეინახეთ', 'wbim' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'ფილიალის ველები', 'wbim' ); ?></h3>
            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ველი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'სავალდებულო', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e( 'სახელი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'ფილიალის დასახელება (მაგ: "ვაკის ფილიალი")', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'დიახ', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'კოდი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'უნიკალური კოდი იდენტიფიკაციისთვის (მაგ: "VAK-001")', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'მისამართი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'ფილიალის სრული მისამართი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'ქალაქი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'ქალაქი სადაც მდებარეობს ფილიალი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'ტელეფონი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'საკონტაქტო ტელეფონი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'ელ-ფოსტა', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'ფილიალის ელ-ფოსტა შეტყობინებებისთვის', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'მენეჯერი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'მომხმარებელი რომელიც მართავს ფილიალს', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'კოორდინატები', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'გრძედი და განედი რუკაზე ჩვენებისთვის', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'სტატუსი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'აქტიური/არააქტიური - არააქტიური ფილიალი არ გამოჩნდება checkout-ში', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'დიახ', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="wbim-doc-note">
                <strong><?php esc_html_e( 'შენიშვნა:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'თუ გსურთ რუკაზე ფილიალების ჩვენება checkout-ში, აუცილებელია კოორდინატების მითითება და Google Maps API key-ის დაყენება პარამეტრებში.', 'wbim' ); ?>
            </div>

            <h3><?php esc_html_e( 'ფილიალის რედაქტირება და წაშლა', 'wbim' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'ფილიალის რედაქტირებისთვის დააჭირეთ "რედაქტირება" ღილაკს სიაში', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ფილიალის წასაშლელად დააჭირეთ "წაშლა" - ეს წაშლის ფილიალთან დაკავშირებულ მარაგსაც', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'თუ გსურთ დროებით გამორთოთ ფილიალი, შეცვალეთ სტატუსი "არააქტიურზე"', 'wbim' ); ?></li>
            </ul>

            <div class="wbim-doc-note warning">
                <strong><?php esc_html_e( 'გაფრთხილება:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'ფილიალის წაშლა ვერ გაუქმდება! წაშლამდე დარწმუნდით რომ გადაიტანეთ მარაგი სხვა ფილიალში.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render stock documentation
     *
     * @return void
     */
    private function render_stock_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'მარაგის მართვა', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'მარაგის მართვის სისტემა საშუალებას გაძლევთ აკონტროლოთ პროდუქციის რაოდენობა თითოეულ ფილიალში ცალ-ცალკე.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'მარაგის ნახვა და რედაქტირება', 'wbim' ); ?></h3>
            <ol class="wbim-doc-steps">
                <li><?php esc_html_e( 'გადადით მენიუში "ფილიალები" → "მარაგები"', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'გამოიყენეთ ფილტრები ფილიალის ან კატეგორიის მიხედვით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'რაოდენობის შესაცვლელად დააჭირეთ ველს და შეიყვანეთ ახალი მნიშვნელობა', 'wbim' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'მარაგის იმპორტი CSV-დან', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'დიდი რაოდენობის მარაგის დასაყენებლად გამოიყენეთ CSV იმპორტი:', 'wbim' ); ?></p>

            <div class="wbim-doc-code">
                <pre>product_id,branch_id,quantity
123,1,50
124,1,30
123,2,25
124,2,40</pre>
            </div>

            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'სვეტი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>product_id</td>
                        <td><?php esc_html_e( 'პროდუქტის ID WooCommerce-დან', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>branch_id</td>
                        <td><?php esc_html_e( 'ფილიალის ID', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>quantity</td>
                        <td><?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'დაბალი მარაგის შეტყობინებები', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'სისტემა ავტომატურად გაგზავნის შეტყობინებას როცა მარაგი დაბალ ზღვარს ჩამოსცდება:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'გლობალური ზღვარი - პარამეტრებში დაყენებული ყველა პროდუქტისთვის', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ინდივიდუალური ზღვარი - თითოეული პროდუქტისთვის ცალკე', 'wbim' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'მარაგის კორექტირება', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'მარაგის ხელით კორექტირებისას:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'ყველა ცვლილება იწერება ისტორიაში', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'შეგიძლიათ დაამატოთ შენიშვნა რატომ შეიცვალა მარაგი', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ისტორია ხილულია "ისტორია" განყოფილებაში', 'wbim' ); ?></li>
            </ul>

            <div class="wbim-doc-note info">
                <strong><?php esc_html_e( 'ავტომატური განახლება:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'მარაგი ავტომატურად მცირდება შეკვეთის გაფორმებისას და იზრდება შეკვეთის გაუქმებისას.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render transfers documentation
     *
     * @return void
     */
    private function render_transfers_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'გადატანების სისტემა', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'გადატანების სისტემა საშუალებას გაძლევთ გადაიტანოთ პროდუქცია ერთი ფილიალიდან მეორეში ოფიციალური დოკუმენტაციით.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'გადატანის სტატუსები', 'wbim' ); ?></h3>
            <div class="wbim-doc-workflow">
                <span class="wbim-doc-workflow-step"><?php esc_html_e( 'დრაფტი', 'wbim' ); ?></span>
                <span class="wbim-doc-workflow-arrow">→</span>
                <span class="wbim-doc-workflow-step"><?php esc_html_e( 'მოლოდინში', 'wbim' ); ?></span>
                <span class="wbim-doc-workflow-arrow">→</span>
                <span class="wbim-doc-workflow-step"><?php esc_html_e( 'ტრანზიტში', 'wbim' ); ?></span>
                <span class="wbim-doc-workflow-arrow">→</span>
                <span class="wbim-doc-workflow-step"><?php esc_html_e( 'დასრულებული', 'wbim' ); ?></span>
            </div>

            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'სტატუსი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'მარაგის ცვლილება', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e( 'დრაფტი', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'გადატანა იქმნება, შეგიძლიათ პროდუქტების დამატება/წაშლა', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არ იცვლება', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'მოლოდინში', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'გადატანა მზადაა გასაგზავნად, ელოდება დადასტურებას', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'არ იცვლება', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'ტრანზიტში', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'პროდუქცია გაიგზავნა, გზაშია', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'მცირდება წყაროში', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'დასრულებული', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'პროდუქცია მიღებულია დანიშნულებაში', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'იზრდება დანიშნულებაში', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'გაუქმებული', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'გადატანა გაუქმდა', 'wbim' ); ?></td>
                        <td><?php esc_html_e( 'უბრუნდება წყაროს (თუ იყო ტრანზიტში)', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'გადატანის შექმნა', 'wbim' ); ?></h3>
            <ol class="wbim-doc-steps">
                <li><?php esc_html_e( 'გადადით "გადატანები" → "ახალი გადატანა"', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'აირჩიეთ წყარო ფილიალი (საიდან გადაიტანება)', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'აირჩიეთ დანიშნულების ფილიალი (სად გადაიტანება)', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'დაამატეთ პროდუქტები და მიუთითეთ რაოდენობა', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'დაამატეთ შენიშვნები (არასავალდებულო)', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'შეინახეთ დრაფტად ან გაგზავნეთ პირდაპირ', 'wbim' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'PDF დოკუმენტი', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'თითოეული გადატანისთვის შეგიძლიათ ჩამოტვირთოთ PDF დოკუმენტი რომელიც შეიცავს:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'გადატანის ნომერი და თარიღი', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'წყარო და დანიშნულების ფილიალები', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'პროდუქტების სია რაოდენობებით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ხელმოწერის ადგილი', 'wbim' ); ?></li>
            </ul>

            <div class="wbim-doc-note">
                <strong><?php esc_html_e( 'შენიშვნა:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'სისტემა ავტომატურად ამოწმებს არის თუ არა საკმარისი მარაგი წყარო ფილიალში გადატანის გაგზავნამდე.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render orders documentation
     *
     * @return void
     */
    private function render_orders_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'შეკვეთები და ფილიალები', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'პლაგინი ინტეგრირდება WooCommerce შეკვეთების სისტემასთან და საშუალებას აძლევს მომხმარებლებს აირჩიონ ფილიალი checkout-ის დროს.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'Checkout-ის პარამეტრები', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'პარამეტრებში შეგიძლიათ დააკონფიგურიროთ:', 'wbim' ); ?></p>
            <ul>
                <li><strong><?php esc_html_e( 'ფილიალის არჩევის ტიპი:', 'wbim' ); ?></strong>
                    <ul>
                        <li><?php esc_html_e( 'ჩამოსაშლელი მენიუ - მარტივი dropdown', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'რადიო ღილაკები - ყველა ფილიალი ხილულია', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'რუკა - Google Maps ინტეგრაცია', 'wbim' ); ?></li>
                    </ul>
                </li>
                <li><strong><?php esc_html_e( 'ავტომატური არჩევა:', 'wbim' ); ?></strong>
                    <ul>
                        <li><?php esc_html_e( 'ყველაზე მეტი მარაგი - აირჩევს ფილიალს სადაც ყველაზე მეტი პროდუქტია', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'უახლოესი - აირჩევს გეოლოკაციით უახლოეს ფილიალს', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'ნაგულისხმევი - აირჩევს პარამეტრებში მითითებულ ფილიალს', 'wbim' ); ?></li>
                    </ul>
                </li>
                <li><strong><?php esc_html_e( 'სავალდებულო არჩევა:', 'wbim' ); ?></strong> <?php esc_html_e( 'მომხმარებელი ვერ გააფორმებს შეკვეთას ფილიალის არჩევის გარეშე', 'wbim' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'მარაგის შემცირება', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'შეკვეთის გაფორმებისას:', 'wbim' ); ?></p>
            <ol>
                <li><?php esc_html_e( 'სისტემა ამოწმებს არის თუ არა საკმარისი მარაგი არჩეულ ფილიალში', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'მარაგი ავტომატურად მცირდება', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ფილიალის ინფორმაცია ინახება შეკვეთასთან', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'თუ მარაგი არასაკმარისია, გამოჩნდება შეცდომის შეტყობინება', 'wbim' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'ფილიალის ინფორმაცია შეკვეთაში', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'შეკვეთის დეტალებში ხილულია:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'არჩეული ფილიალის სახელი და მისამართი', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ფილიალის საკონტაქტო ინფორმაცია', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'შესაძლებლობა ფილიალის შეცვლის (ადმინისთვის)', 'wbim' ); ?></li>
            </ul>

            <div class="wbim-doc-note info">
                <strong><?php esc_html_e( 'რჩევა:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'თუ გსურთ რომ მომხმარებელმა დაინახოს მარაგი ფილიალის არჩევისას, ჩართეთ "მარაგი გადახდისას" პარამეტრი.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render reports documentation
     *
     * @return void
     */
    private function render_reports_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'რეპორტები და ანალიტიკა', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'რეპორტების სისტემა გაძლევთ სრულ სურათს მარაგის, გაყიდვებისა და გადატანების შესახებ.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'ხელმისაწვდომი რეპორტები', 'wbim' ); ?></h3>

            <h4><?php esc_html_e( '1. მარაგის რეპორტი', 'wbim' ); ?></h4>
            <p><?php esc_html_e( 'აჩვენებს მიმდინარე მარაგს ყველა ფილიალში:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'პროდუქტების სია მარაგით თითოეულ ფილიალში', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ჯამური მარაგი და ღირებულება', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ფილტრაცია ფილიალისა და კატეგორიის მიხედვით', 'wbim' ); ?></li>
            </ul>

            <h4><?php esc_html_e( '2. გაყიდვების რეპორტი', 'wbim' ); ?></h4>
            <p><?php esc_html_e( 'აჩვენებს გაყიდვების სტატისტიკას:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'შეკვეთების რაოდენობა ფილიალების მიხედვით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'შემოსავალი პერიოდის მიხედვით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'გრაფიკი გაყიდვების დინამიკით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'დაჯგუფება დღის, კვირის ან თვის მიხედვით', 'wbim' ); ?></li>
            </ul>

            <h4><?php esc_html_e( '3. გადატანების რეპორტი', 'wbim' ); ?></h4>
            <p><?php esc_html_e( 'აჩვენებს გადატანების სტატისტიკას:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'გადატანების რაოდენობა სტატუსების მიხედვით', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ყველაზე ხშირი მიმართულებები', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ყველაზე ხშირად გადატანილი პროდუქტები', 'wbim' ); ?></li>
            </ul>

            <h4><?php esc_html_e( '4. დაბალი მარაგის რეპორტი', 'wbim' ); ?></h4>
            <p><?php esc_html_e( 'აჩვენებს პროდუქტებს რომლებიც საჭიროებენ შევსებას:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'კრიტიკული მარაგი (0 ან ძალიან დაბალი)', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'გაფრთხილების ზონაში მყოფი პროდუქტები', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ხელმისაწვდომობა სხვა ფილიალებში', 'wbim' ); ?></li>
            </ul>

            <h4><?php esc_html_e( '5. მოძრაობის ისტორია', 'wbim' ); ?></h4>
            <p><?php esc_html_e( 'აჩვენებს მარაგის ყველა ცვლილებას:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'გაყიდვები, გადატანები, კორექტირებები', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'წინა და ახალი მნიშვნელობები', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ვინ შეცვალა და როდის', 'wbim' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'ექსპორტი', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'ყველა რეპორტი ექსპორტირდება:', 'wbim' ); ?></p>
            <ul>
                <li><strong>CSV</strong> - <?php esc_html_e( 'Excel-ში გასახსნელად', 'wbim' ); ?></li>
                <li><strong>PDF</strong> - <?php esc_html_e( 'დასაბეჭდად და გასაზიარებლად', 'wbim' ); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render API documentation
     *
     * @return void
     */
    private function render_api_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'REST API', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'პლაგინი გთავაზობთ სრულ REST API-ს ინტეგრაციისთვის გარე სისტემებთან და მობილურ აპლიკაციებთან.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'ავტორიზაცია', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'API იყენებს WooCommerce REST API ავტორიზაციას. შექმენით API keys აქ:', 'wbim' ); ?></p>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys' ) ); ?>" class="button">
                <?php esc_html_e( 'API Keys მართვა', 'wbim' ); ?>
            </a></p>

            <div class="wbim-doc-code">
                <pre>Authorization: Basic base64(consumer_key:consumer_secret)</pre>
            </div>

            <h3><?php esc_html_e( 'Base URL', 'wbim' ); ?></h3>
            <div class="wbim-doc-code">
                <pre><?php echo esc_html( rest_url( 'wbim/v1/' ) ); ?></pre>
            </div>

            <h3><?php esc_html_e( 'Endpoints', 'wbim' ); ?></h3>

            <h4><?php esc_html_e( 'ფილიალები', 'wbim' ); ?></h4>
            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'მეთოდი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'Endpoint', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td>/branches</td>
                        <td><?php esc_html_e( 'ყველა ფილიალის სია', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td>/branches/{id}</td>
                        <td><?php esc_html_e( 'კონკრეტული ფილიალი', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td>/branches</td>
                        <td><?php esc_html_e( 'ახალი ფილიალის შექმნა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>PUT</td>
                        <td>/branches/{id}</td>
                        <td><?php esc_html_e( 'ფილიალის რედაქტირება', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>DELETE</td>
                        <td>/branches/{id}</td>
                        <td><?php esc_html_e( 'ფილიალის წაშლა', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e( 'მარაგი', 'wbim' ); ?></h4>
            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'მეთოდი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'Endpoint', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td>/stock</td>
                        <td><?php esc_html_e( 'მარაგის სია ფილტრებით', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td>/stock/product/{id}</td>
                        <td><?php esc_html_e( 'პროდუქტის მარაგი ყველა ფილიალში', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td>/stock/branch/{id}</td>
                        <td><?php esc_html_e( 'ფილიალის მთელი მარაგი', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td>/stock</td>
                        <td><?php esc_html_e( 'მარაგის დაყენება', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>PUT</td>
                        <td>/stock/adjust</td>
                        <td><?php esc_html_e( 'მარაგის კორექტირება (+/-)', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php esc_html_e( 'გადატანები', 'wbim' ); ?></h4>
            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'მეთოდი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'Endpoint', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>GET</td>
                        <td>/transfers</td>
                        <td><?php esc_html_e( 'გადატანების სია', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>GET</td>
                        <td>/transfers/{id}</td>
                        <td><?php esc_html_e( 'კონკრეტული გადატანა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>POST</td>
                        <td>/transfers</td>
                        <td><?php esc_html_e( 'ახალი გადატანის შექმნა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>PUT</td>
                        <td>/transfers/{id}/status</td>
                        <td><?php esc_html_e( 'სტატუსის შეცვლა', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'მაგალითები', 'wbim' ); ?></h3>

            <h4><?php esc_html_e( 'მარაგის დაყენება', 'wbim' ); ?></h4>
            <div class="wbim-doc-code">
                <pre>POST /wp-json/wbim/v1/stock
Content-Type: application/json

{
    "product_id": 123,
    "branch_id": 1,
    "quantity": 50
}</pre>
            </div>

            <h4><?php esc_html_e( 'გადატანის შექმნა', 'wbim' ); ?></h4>
            <div class="wbim-doc-code">
                <pre>POST /wp-json/wbim/v1/transfers
Content-Type: application/json

{
    "from_branch_id": 1,
    "to_branch_id": 2,
    "items": [
        {"product_id": 123, "quantity": 10},
        {"product_id": 124, "quantity": 5}
    ],
    "notes": "გადატანის შენიშვნა"
}</pre>
            </div>
        </div>
        <?php
    }

    /**
     * Render roles documentation
     *
     * @return void
     */
    private function render_roles_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'როლები და უფლებები', 'wbim' ); ?></h2>

            <p><?php esc_html_e( 'პლაგინი ამატებს ახალ როლს და უფლებებს მომხმარებლების მართვისთვის.', 'wbim' ); ?></p>

            <h3><?php esc_html_e( 'ფილიალის მენეჯერი (Branch Manager)', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'ახალი როლი რომელიც საშუალებას აძლევს მომხმარებელს მართოს მხოლოდ მისთვის მინიჭებული ფილიალი.', 'wbim' ); ?></p>

            <h4><?php esc_html_e( 'მენეჯერის უფლებები:', 'wbim' ); ?></h4>
            <table class="wbim-doc-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'უფლება', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'აღწერა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>wbim_view_branch</td>
                        <td><?php esc_html_e( 'ფილიალის ნახვა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>wbim_manage_stock</td>
                        <td><?php esc_html_e( 'მარაგის მართვა (თავის ფილიალში)', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>wbim_manage_transfers</td>
                        <td><?php esc_html_e( 'გადატანების მართვა', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>wbim_view_reports</td>
                        <td><?php esc_html_e( 'რეპორტების ნახვა (თავის ფილიალზე)', 'wbim' ); ?></td>
                    </tr>
                    <tr>
                        <td>wbim_receive_notifications</td>
                        <td><?php esc_html_e( 'შეტყობინებების მიღება', 'wbim' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'მენეჯერის დანიშვნა', 'wbim' ); ?></h3>
            <ol class="wbim-doc-steps">
                <li><?php esc_html_e( 'შექმენით ახალი მომხმარებელი ან აირჩიეთ არსებული', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'მიანიჭეთ როლი "ფილიალის მენეჯერი"', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ფილიალის რედაქტირებისას აირჩიეთ ეს მომხმარებელი მენეჯერად', 'wbim' ); ?></li>
            </ol>

            <h3><?php esc_html_e( 'ადმინისტრატორის უფლებები', 'wbim' ); ?></h3>
            <p><?php esc_html_e( 'ადმინისტრატორებს და მაღაზიის მენეჯერებს (manage_woocommerce) აქვთ სრული წვდომა:', 'wbim' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'ყველა ფილიალის მართვა', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ყველა მარაგის რედაქტირება', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'პარამეტრების შეცვლა', 'wbim' ); ?></li>
                <li><?php esc_html_e( 'ყველა რეპორტის ნახვა', 'wbim' ); ?></li>
            </ul>

            <div class="wbim-doc-note info">
                <strong><?php esc_html_e( 'შენიშვნა:', 'wbim' ); ?></strong>
                <?php esc_html_e( 'ფილიალის მენეჯერი ხედავს მხოლოდ თავის ფილიალის მონაცემებს. ის ვერ ნახავს სხვა ფილიალების მარაგს ან სტატისტიკას.', 'wbim' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render FAQ documentation
     *
     * @return void
     */
    private function render_faq_docs() {
        ?>
        <div class="wbim-doc-section">
            <h2><?php esc_html_e( 'ხშირად დასმული კითხვები', 'wbim' ); ?></h2>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ დავამატო ახალი ფილიალი?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'გადადით მენიუში "ფილიალები" → "ფილიალები" და დააჭირეთ "ფილიალის დამატება" ღილაკს. შეავსეთ საჭირო ველები და შეინახეთ.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ ავტვირთო მარაგი CSV ფაილიდან?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'გადადით "მარაგები" → "იმპორტი". ატვირთეთ CSV ფაილი შემდეგი სვეტებით: product_id, branch_id, quantity. ფაილი უნდა იყოს UTF-8 კოდირებით.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'რატომ არ ჩანს ფილიალი checkout-ში?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'შეამოწმეთ შემდეგი:', 'wbim' ); ?>
                    <ul>
                        <li><?php esc_html_e( 'ფილიალი აქტიურია', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'პარამეტრებში ჩართულია "ფილიალის არჩევა გადახდისას"', 'wbim' ); ?></li>
                        <li><?php esc_html_e( 'ფილიალში არის საკმარისი მარაგი კალათაში არსებული პროდუქტებისთვის', 'wbim' ); ?></li>
                    </ul>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ გადავიტანო პროდუქტები ფილიალებს შორის?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'გადადით "გადატანები" → "ახალი გადატანა". აირჩიეთ წყარო და დანიშნულების ფილიალები, დაამატეთ პროდუქტები და გაგზავნეთ. პროდუქცია ჩამოიჭრება წყაროდან გაგზავნისას და დაემატება დანიშნულებას მიღებისას.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ ვნახო რომელ ფილიალში რა მარაგია?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'გადადით "რეპორტები" → "მარაგის რეპორტი". აქ ნახავთ ყველა პროდუქტს და მის მარაგს თითოეულ ფილიალში. შეგიძლიათ გაფილტროთ ფილიალის ან კატეგორიის მიხედვით.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ მივიღო შეტყობინება დაბალი მარაგის შესახებ?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'გადადით "პარამეტრები" → "შეტყობინებები" და ჩართეთ "დაბალი მარაგის შეტყობინებები". დააყენეთ დაბალი მარაგის ზღვარი "ზოგადი" ტაბში. როცა მარაგი ამ ზღვარს ჩამოსცდება, ფილიალის მენეჯერი მიიღებს ელ-ფოსტას.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'როგორ გამოვიყენო API მობილური აპლიკაციისთვის?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'შექმენით WooCommerce REST API keys (WooCommerce → პარამეტრები → დამატებითი → REST API). გამოიყენეთ Consumer Key და Consumer Secret ავტორიზაციისთვის. API endpoints-ები აღწერილია "REST API" ტაბში.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'შეიძლება თუ არა რუკაზე ფილიალების ჩვენება?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'დიახ! გადადით პარამეტრებში და აირჩიეთ "რუკა" არჩევის ტიპად. დაამატეთ Google Maps API Key. ყველა ფილიალს უნდა ჰქონდეს მითითებული კოორდინატები (გრძედი და განედი).', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'რა მოხდება თუ პლაგინს წავშლი?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'ნაგულისხმევად მონაცემები შენარჩუნდება. თუ გსურთ მონაცემების წაშლა, პარამეტრებში ჩართეთ "წაიშალოს ყველა მონაცემი პლაგინის წაშლისას" პლაგინის წაშლამდე.', 'wbim' ); ?>
                </div>
            </div>

            <div class="wbim-faq-item">
                <div class="wbim-faq-question">
                    <?php esc_html_e( 'თავსებადია თუ არა WooCommerce HPOS-თან?', 'wbim' ); ?>
                    <span class="wbim-faq-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="wbim-faq-answer">
                    <?php esc_html_e( 'დიახ! პლაგინი სრულად თავსებადია WooCommerce High-Performance Order Storage (HPOS) სისტემასთან.', 'wbim' ); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
