<?php
/**
 * Sales Report View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wbim-report wbim-sales-report">
    <!-- Filters -->
    <div class="wbim-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-reports">
            <input type="hidden" name="tab" value="sales">

            <div class="wbim-filter-row">
                <div class="wbim-filter-item">
                    <label for="branch_id"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></label>
                    <select name="branch_id" id="branch_id">
                        <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $branch_id, $branch->id ); ?>>
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wbim-filter-item">
                    <label for="date_from"><?php esc_html_e( 'თარიღიდან', 'wbim' ); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                </div>

                <div class="wbim-filter-item">
                    <label for="date_to"><?php esc_html_e( 'თარიღამდე', 'wbim' ); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                </div>

                <div class="wbim-filter-item">
                    <label for="group_by"><?php esc_html_e( 'დაჯგუფება', 'wbim' ); ?></label>
                    <select name="group_by" id="group_by">
                        <option value="day" <?php selected( $group_by, 'day' ); ?>><?php esc_html_e( 'დღეების მიხედვით', 'wbim' ); ?></option>
                        <option value="week" <?php selected( $group_by, 'week' ); ?>><?php esc_html_e( 'კვირების მიხედვით', 'wbim' ); ?></option>
                        <option value="month" <?php selected( $group_by, 'month' ); ?>><?php esc_html_e( 'თვეების მიხედვით', 'wbim' ); ?></option>
                    </select>
                </div>

                <div class="wbim-filter-item">
                    <label>&nbsp;</label>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'ფილტრი', 'wbim' ); ?>
                    </button>
                </div>

                <div class="wbim-filter-item wbim-export-buttons">
                    <label>&nbsp;</label>
                    <button type="button" class="button wbim-export-btn" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'CSV', 'wbim' ); ?>
                    </button>
                    <button type="button" class="button wbim-export-btn" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e( 'PDF', 'wbim' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="wbim-summary-cards">
        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo wp_kses_post( wc_price( $report['summary']['total_revenue'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ შემოსავალი', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['total_orders'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ შეკვეთები', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo wp_kses_post( wc_price( $report['summary']['average_order'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'საშუალო შეკვეთა', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['total_items'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'გაყიდული ერთეულები', 'wbim' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="wbim-charts-row">
        <div class="wbim-chart-container">
            <h3><?php esc_html_e( 'გაყიდვები დროის მიხედვით', 'wbim' ); ?></h3>
            <canvas id="wbim-sales-line-chart"></canvas>
        </div>

        <div class="wbim-chart-container">
            <h3><?php esc_html_e( 'შემოსავალი ფილიალების მიხედვით', 'wbim' ); ?></h3>
            <canvas id="wbim-revenue-pie-chart"></canvas>
        </div>
    </div>

    <!-- Branch Summary -->
    <?php if ( ! empty( $report['summary']['by_branch'] ) ) : ?>
    <div class="wbim-branch-summary">
        <h3><?php esc_html_e( 'ფილიალების სტატისტიკა', 'wbim' ); ?></h3>
        <div class="wbim-branch-cards">
            <?php foreach ( $report['branches'] as $branch ) : ?>
                <?php
                $branch_data = isset( $report['summary']['by_branch'][ $branch->id ] )
                    ? $report['summary']['by_branch'][ $branch->id ]
                    : array( 'orders' => 0, 'revenue' => 0, 'items' => 0 );
                ?>
                <div class="wbim-branch-card">
                    <h4><?php echo esc_html( $branch->name ); ?></h4>
                    <div class="wbim-branch-stats">
                        <div class="wbim-stat">
                            <span class="value"><?php echo esc_html( number_format_i18n( $branch_data['orders'] ) ); ?></span>
                            <span class="label"><?php esc_html_e( 'შეკვეთები', 'wbim' ); ?></span>
                        </div>
                        <div class="wbim-stat">
                            <span class="value"><?php echo wp_kses_post( wc_price( $branch_data['revenue'] ) ); ?></span>
                            <span class="label"><?php esc_html_e( 'შემოსავალი', 'wbim' ); ?></span>
                        </div>
                        <div class="wbim-stat">
                            <span class="value"><?php echo esc_html( number_format_i18n( $branch_data['items'] ) ); ?></span>
                            <span class="label"><?php esc_html_e( 'ერთეულები', 'wbim' ); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Report Table -->
    <div class="wbim-report-table-wrapper">
        <h3><?php esc_html_e( 'დეტალური მონაცემები', 'wbim' ); ?></h3>
        <table class="wp-list-table widefat fixed striped wbim-report-table">
            <thead>
                <tr>
                    <th class="column-period"><?php esc_html_e( 'პერიოდი', 'wbim' ); ?></th>
                    <?php foreach ( $report['branches'] as $branch ) : ?>
                        <th class="column-branch" colspan="2"><?php echo esc_html( $branch->name ); ?></th>
                    <?php endforeach; ?>
                    <th class="column-total" colspan="2"><?php esc_html_e( 'სულ', 'wbim' ); ?></th>
                </tr>
                <tr class="sub-header">
                    <th></th>
                    <?php foreach ( $report['branches'] as $branch ) : ?>
                        <th><?php esc_html_e( 'შეკვ.', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'შემოს.', 'wbim' ); ?></th>
                    <?php endforeach; ?>
                    <th><?php esc_html_e( 'შეკვ.', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'შემოს.', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $report['items'] ) ) : ?>
                    <tr>
                        <td colspan="<?php echo esc_attr( ( count( $report['branches'] ) * 2 ) + 3 ); ?>">
                            <?php esc_html_e( 'მონაცემები არ მოიძებნა.', 'wbim' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $report['items'] as $item ) : ?>
                        <?php
                        $row_orders = 0;
                        $row_revenue = 0;
                        ?>
                        <tr>
                            <td class="column-period"><?php echo esc_html( $item['period'] ); ?></td>
                            <?php foreach ( $report['branches'] as $branch ) : ?>
                                <?php
                                $key = 'branch_' . $branch->id;
                                $orders = isset( $item[ $key ]['orders'] ) ? $item[ $key ]['orders'] : 0;
                                $revenue = isset( $item[ $key ]['revenue'] ) ? $item[ $key ]['revenue'] : 0;
                                $row_orders += $orders;
                                $row_revenue += $revenue;
                                ?>
                                <td><?php echo esc_html( $orders ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( $revenue ) ); ?></td>
                            <?php endforeach; ?>
                            <td><strong><?php echo esc_html( $row_orders ); ?></strong></td>
                            <td><strong><?php echo wp_kses_post( wc_price( $row_revenue ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Chart data
    var chartData = <?php echo wp_json_encode( $report['chart'] ); ?>;
    var branches = <?php echo wp_json_encode( wp_list_pluck( $report['branches'], 'name', 'id' ) ); ?>;
    var branchRevenue = <?php echo wp_json_encode( $report['summary']['by_branch'] ); ?>;

    // Sales Line Chart
    if ($('#wbim-sales-line-chart').length && chartData.labels.length > 0) {
        new Chart($('#wbim-sales-line-chart'), {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₾' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Revenue Pie Chart
    if ($('#wbim-revenue-pie-chart').length && Object.keys(branchRevenue).length > 0) {
        var pieLabels = [];
        var pieData = [];
        var pieColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

        $.each(branchRevenue, function(branchId, data) {
            if (branches[branchId]) {
                pieLabels.push(branches[branchId]);
                pieData.push(data.revenue);
            }
        });

        new Chart($('#wbim-revenue-pie-chart'), {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColors.slice(0, pieLabels.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₾' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Export buttons
    $('.wbim-export-btn').on('click', function() {
        var format = $(this).data('format');
        var filters = {
            branch_id: $('#branch_id').val(),
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val(),
            group_by: $('#group_by').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_export_report',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wbim_admin_nonce' ) ); ?>',
                report_type: 'sales',
                format: format,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});
</script>
