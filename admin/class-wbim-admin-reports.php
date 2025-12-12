<?php
/**
 * Admin Reports Class
 *
 * Handles the reports pages in the admin area.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Reports class
 *
 * @since 1.0.0
 */
class WBIM_Admin_Reports {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_wbim_export_report', array( $this, 'ajax_export_report' ) );
    }

    /**
     * Enqueue scripts and styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wbim-reports' ) === false ) {
            return;
        }

        $this->enqueue_report_assets();
    }

    /**
     * Enqueue report assets (can be called directly)
     *
     * @return void
     */
    public function enqueue_report_assets() {
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Date picker
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', array(), '1.13.2' );

        // Custom charts script
        wp_enqueue_script(
            'wbim-charts',
            WBIM_PLUGIN_URL . 'admin/js/wbim-charts.js',
            array( 'jquery', 'chartjs' ),
            WBIM_VERSION,
            true
        );
    }

    /**
     * Render reports page
     *
     * @return void
     */
    public function render_reports_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'wbim_view_reports' ) ) {
            wp_die( esc_html__( 'არ გაქვთ უფლება ამ გვერდის ნახვის.', 'wbim' ) );
        }

        // Enqueue assets directly since this may be called after enqueue_scripts hook
        $this->enqueue_report_assets();

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'stock';
        $tabs = array(
            'stock'     => __( 'მარაგის რეპორტი', 'wbim' ),
            'sales'     => __( 'გაყიდვების რეპორტი', 'wbim' ),
            'transfers' => __( 'გადატანების რეპორტი', 'wbim' ),
            'low-stock' => __( 'დაბალი მარაგი', 'wbim' ),
            'movement'  => __( 'მოძრაობის ისტორია', 'wbim' ),
        );

        ?>
        <div class="wrap wbim-reports-wrap">
            <h1><?php esc_html_e( 'რეპორტები', 'wbim' ); ?></h1>

            <nav class="nav-tab-wrapper wbim-nav-tabs">
                <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-reports&tab=' . $tab_id ) ); ?>"
                       class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="wbim-report-content">
                <?php
                switch ( $tab ) {
                    case 'sales':
                        $this->render_sales_report();
                        break;
                    case 'transfers':
                        $this->render_transfers_report();
                        break;
                    case 'low-stock':
                        $this->render_low_stock_report();
                        break;
                    case 'movement':
                        $this->render_movement_report();
                        break;
                    default:
                        $this->render_stock_report();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render stock report
     *
     * @return void
     */
    private function render_stock_report() {
        $branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;
        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;

        $branches = WBIM_Branch::get_active();
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        $report = WBIM_Reports::get_stock_report( array(
            'branch_id'   => $branch_id,
            'category_id' => $category_id,
        ) );

        include WBIM_PLUGIN_DIR . 'admin/views/reports/stock-report.php';
    }

    /**
     * Render sales report
     *
     * @return void
     */
    private function render_sales_report() {
        $branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : current_time( 'Y-m-d' );
        $group_by = isset( $_GET['group_by'] ) ? sanitize_text_field( wp_unslash( $_GET['group_by'] ) ) : 'day';

        $branches = WBIM_Branch::get_active();

        $report = WBIM_Reports::get_sales_report( array(
            'branch_id' => $branch_id,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'group_by'  => $group_by,
        ) );

        include WBIM_PLUGIN_DIR . 'admin/views/reports/sales-report.php';
    }

    /**
     * Render transfers report
     *
     * @return void
     */
    private function render_transfers_report() {
        $from_branch_id = isset( $_GET['from_branch_id'] ) ? absint( $_GET['from_branch_id'] ) : 0;
        $to_branch_id = isset( $_GET['to_branch_id'] ) ? absint( $_GET['to_branch_id'] ) : 0;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : current_time( 'Y-m-d' );
        $status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

        $branches = WBIM_Branch::get_active();

        $report = WBIM_Reports::get_transfers_report( array(
            'from_branch_id' => $from_branch_id,
            'to_branch_id'   => $to_branch_id,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'status'         => $status,
        ) );

        include WBIM_PLUGIN_DIR . 'admin/views/reports/transfers-report.php';
    }

    /**
     * Render low stock report
     *
     * @return void
     */
    private function render_low_stock_report() {
        $branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;
        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
        $include_zero = isset( $_GET['include_zero'] ) ? (bool) $_GET['include_zero'] : true;

        $branches = WBIM_Branch::get_active();
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        $report = WBIM_Reports::get_low_stock_report( array(
            'branch_id'    => $branch_id,
            'category_id'  => $category_id,
            'include_zero' => $include_zero,
        ) );

        include WBIM_PLUGIN_DIR . 'admin/views/reports/low-stock-report.php';
    }

    /**
     * Render movement report
     *
     * @return void
     */
    private function render_movement_report() {
        $branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : current_time( 'Y-m-d' );
        $action_type = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $branches = WBIM_Branch::get_active();

        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;

        $report = WBIM_Reports::get_stock_movement_report( array(
            'branch_id'   => $branch_id,
            'product_id'  => $product_id,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'action_type' => $action_type,
            'limit'       => $per_page,
            'offset'      => $offset,
        ) );

        $total_pages = ceil( $report['total_count'] / $per_page );

        include WBIM_PLUGIN_DIR . 'admin/views/reports/movement-report.php';
    }

    /**
     * AJAX handler for exporting reports
     *
     * @return void
     */
    public function ajax_export_report() {
        check_ajax_referer( 'wbim_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'wbim_view_reports' ) ) {
            wp_send_json_error( array( 'message' => __( 'არ გაქვთ უფლება.', 'wbim' ) ) );
        }

        $report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : '';
        $format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
        $filters = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ) : array();

        // Get report data
        switch ( $report_type ) {
            case 'stock':
                $report = WBIM_Reports::get_stock_report( $filters );
                $data = $this->format_stock_for_export( $report );
                $filename = 'stock-report-' . current_time( 'Y-m-d' );
                break;

            case 'sales':
                $report = WBIM_Reports::get_sales_report( $filters );
                $data = $this->format_sales_for_export( $report );
                $filename = 'sales-report-' . current_time( 'Y-m-d' );
                break;

            case 'transfers':
                $report = WBIM_Reports::get_transfers_report( $filters );
                $data = $this->format_transfers_for_export( $report );
                $filename = 'transfers-report-' . current_time( 'Y-m-d' );
                break;

            case 'low-stock':
                $report = WBIM_Reports::get_low_stock_report( $filters );
                $data = $this->format_low_stock_for_export( $report );
                $filename = 'low-stock-report-' . current_time( 'Y-m-d' );
                break;

            default:
                wp_send_json_error( array( 'message' => __( 'უცნობი რეპორტის ტიპი.', 'wbim' ) ) );
        }

        // Export
        if ( $format === 'csv' ) {
            $url = WBIM_Export::to_csv( $data['rows'], $data['headers'], $filename );
        } else {
            $url = WBIM_Export::to_pdf( $data['rows'], $data['headers'], $data['title'], $filename );
        }

        if ( is_wp_error( $url ) ) {
            wp_send_json_error( array( 'message' => $url->get_error_message() ) );
        }

        wp_send_json_success( array( 'url' => $url ) );
    }

    /**
     * Format stock report for export
     *
     * @param array $report Report data.
     * @return array
     */
    private function format_stock_for_export( $report ) {
        $headers = array(
            __( 'პროდუქტი', 'wbim' ),
            __( 'SKU', 'wbim' ),
        );

        foreach ( $report['branches'] as $branch ) {
            $headers[] = $branch->name;
        }

        $headers[] = __( 'ჯამი', 'wbim' );
        $headers[] = __( 'ღირებულება', 'wbim' );

        $rows = array();
        foreach ( $report['items'] as $item ) {
            $row = array(
                $item->product_name,
                $item->sku,
            );

            foreach ( $report['branches'] as $branch ) {
                $key = 'branch_' . $branch->id;
                $row[] = isset( $item->$key ) ? $item->$key : 0;
            }

            $row[] = $item->total_stock;
            $price = floatval( $item->price );
            $row[] = wc_price( $price * (int) $item->total_stock );

            $rows[] = $row;
        }

        // Add totals row
        $totals_row = array( __( 'სულ', 'wbim' ), '' );
        foreach ( $report['branches'] as $branch ) {
            $key = 'branch_' . $branch->id;
            $totals_row[] = $report['totals'][ $key ];
        }
        $totals_row[] = $report['totals']['total_stock'];
        $totals_row[] = wc_price( $report['totals']['total_value'] );
        $rows[] = $totals_row;

        return array(
            'title'   => __( 'მარაგის რეპორტი', 'wbim' ),
            'headers' => $headers,
            'rows'    => $rows,
        );
    }

    /**
     * Format sales report for export
     *
     * @param array $report Report data.
     * @return array
     */
    private function format_sales_for_export( $report ) {
        $headers = array( __( 'პერიოდი', 'wbim' ) );

        foreach ( $report['branches'] as $branch ) {
            $headers[] = $branch->name . ' - ' . __( 'შეკვეთები', 'wbim' );
            $headers[] = $branch->name . ' - ' . __( 'შემოსავალი', 'wbim' );
        }

        $rows = array();
        foreach ( $report['items'] as $item ) {
            $row = array( $item['period'] );

            foreach ( $report['branches'] as $branch ) {
                $key = 'branch_' . $branch->id;
                if ( isset( $item[ $key ] ) ) {
                    $row[] = $item[ $key ]['orders'];
                    $row[] = wc_price( $item[ $key ]['revenue'] );
                } else {
                    $row[] = 0;
                    $row[] = wc_price( 0 );
                }
            }

            $rows[] = $row;
        }

        return array(
            'title'   => __( 'გაყიდვების რეპორტი', 'wbim' ),
            'headers' => $headers,
            'rows'    => $rows,
        );
    }

    /**
     * Format transfers report for export
     *
     * @param array $report Report data.
     * @return array
     */
    private function format_transfers_for_export( $report ) {
        $headers = array(
            __( 'თვე', 'wbim' ),
            __( 'დრაფტი', 'wbim' ),
            __( 'მოლოდინში', 'wbim' ),
            __( 'ტრანზიტში', 'wbim' ),
            __( 'დასრულებული', 'wbim' ),
            __( 'გაუქმებული', 'wbim' ),
        );

        $rows = array();
        foreach ( $report['monthly'] as $item ) {
            $rows[] = array(
                $item['month'],
                $item['draft'],
                $item['pending'],
                $item['in_transit'],
                $item['completed'],
                $item['cancelled'],
            );
        }

        return array(
            'title'   => __( 'გადატანების რეპორტი', 'wbim' ),
            'headers' => $headers,
            'rows'    => $rows,
        );
    }

    /**
     * Format low stock report for export
     *
     * @param array $report Report data.
     * @return array
     */
    private function format_low_stock_for_export( $report ) {
        $headers = array(
            __( 'პროდუქტი', 'wbim' ),
            __( 'SKU', 'wbim' ),
            __( 'ფილიალი', 'wbim' ),
            __( 'მარაგი', 'wbim' ),
            __( 'ზღვარი', 'wbim' ),
            __( 'სტატუსი', 'wbim' ),
        );

        $rows = array();
        foreach ( $report['items'] as $item ) {
            $status_label = $item->status === 'critical' ? __( 'კრიტიკული', 'wbim' ) : __( 'გაფრთხილება', 'wbim' );
            $rows[] = array(
                $item->product_name,
                $item->sku,
                $item->branch_name,
                $item->quantity,
                $item->low_stock_threshold > 0 ? $item->low_stock_threshold : $report['threshold'],
                $status_label,
            );
        }

        return array(
            'title'   => __( 'დაბალი მარაგის რეპორტი', 'wbim' ),
            'headers' => $headers,
            'rows'    => $rows,
        );
    }
}
