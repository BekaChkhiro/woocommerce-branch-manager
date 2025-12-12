<?php
/**
 * Dashboard View
 *
 * Displays the main plugin dashboard with statistics and quick actions.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get statistics
$stats = WBIM_Reports::get_dashboard_stats();
$total_branches = WBIM_Branch::get_count();
$active_branches = WBIM_Branch::get_count( 1 );
?>

<div class="wrap wbim-dashboard">
    <h1><?php esc_html_e( 'ფილიალების მარაგის მენეჯერი', 'wbim' ); ?></h1>

    <!-- Stats Cards Row -->
    <div class="wbim-stats-cards">
        <div class="wbim-stat-card">
            <div class="wbim-stat-icon wbim-icon-blue">
                <span class="dashicons dashicons-store"></span>
            </div>
            <div class="wbim-stat-content">
                <span class="wbim-stat-number"><?php echo esc_html( $stats['branches_count'] ); ?></span>
                <span class="wbim-stat-label"><?php esc_html_e( 'ფილიალები', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-stat-card">
            <div class="wbim-stat-icon wbim-icon-green">
                <span class="dashicons dashicons-archive"></span>
            </div>
            <div class="wbim-stat-content">
                <span class="wbim-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_stock'] ) ); ?></span>
                <span class="wbim-stat-label"><?php esc_html_e( 'სულ მარაგი', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-stat-card">
            <div class="wbim-stat-icon wbim-icon-yellow">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="wbim-stat-content">
                <span class="wbim-stat-number"><?php echo esc_html( $stats['low_stock_count'] ); ?></span>
                <span class="wbim-stat-label"><?php esc_html_e( 'დაბალი მარაგი', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-stat-card">
            <div class="wbim-stat-icon wbim-icon-purple">
                <span class="dashicons dashicons-randomize"></span>
            </div>
            <div class="wbim-stat-content">
                <span class="wbim-stat-number"><?php echo esc_html( $stats['pending_transfers'] ); ?></span>
                <span class="wbim-stat-label"><?php esc_html_e( 'მოლოდინში გადატანები', 'wbim' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Value Card -->
    <div class="wbim-value-card">
        <div class="wbim-value-content">
            <span class="wbim-value-label"><?php esc_html_e( 'მარაგის ღირებულება', 'wbim' ); ?></span>
            <span class="wbim-value-amount"><?php echo wp_kses_post( wc_price( $stats['stock_value'] ) ); ?></span>
        </div>
        <div class="wbim-value-stats">
            <span class="wbim-mini-stat">
                <span class="dashicons dashicons-warning wbim-text-danger"></span>
                <?php echo esc_html( $stats['out_of_stock_count'] ); ?> <?php esc_html_e( 'ნულოვანი მარაგი', 'wbim' ); ?>
            </span>
        </div>
    </div>

    <!-- Charts Row -->
    <?php if ( $total_branches > 0 ) : ?>
    <div class="wbim-charts-row">
        <div class="wbim-chart-card">
            <h3><?php esc_html_e( 'გაყიდვები ბოლო 7 დღე', 'wbim' ); ?></h3>
            <div class="wbim-chart-container">
                <canvas id="wbim-sales-chart"></canvas>
            </div>
        </div>

        <div class="wbim-chart-card">
            <h3><?php esc_html_e( 'მარაგი ფილიალების მიხედვით', 'wbim' ); ?></h3>
            <div class="wbim-chart-container">
                <canvas id="wbim-stock-chart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tables Row -->
    <div class="wbim-tables-row">
        <!-- Today's Orders -->
        <div class="wbim-table-card">
            <h3>
                <?php esc_html_e( 'დღევანდელი შეკვეთები', 'wbim' ); ?>
                <span class="wbim-badge"><?php echo esc_html( current_time( 'd/m/Y' ) ); ?></span>
            </h3>
            <?php if ( ! empty( $stats['today_orders'] ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'შეკვეთები', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'შემოსავალი', 'wbim' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['today_orders'] as $order ) : ?>
                            <tr>
                                <td><?php echo esc_html( $order->branch_name ); ?></td>
                                <td><?php echo esc_html( $order->order_count ); ?></td>
                                <td><?php echo wp_kses_post( wc_price( $order->revenue ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="wbim-empty-table">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <p><?php esc_html_e( 'დღეს შეკვეთები არ არის.', 'wbim' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Low Stock Alerts -->
        <div class="wbim-table-card">
            <h3>
                <?php esc_html_e( 'დაბალი მარაგის შეტყობინებები', 'wbim' ); ?>
                <?php if ( $stats['low_stock_count'] > 0 ) : ?>
                    <span class="wbim-badge wbim-badge-warning"><?php echo esc_html( $stats['low_stock_count'] ); ?></span>
                <?php endif; ?>
            </h3>
            <?php if ( ! empty( $stats['low_stock_items'] ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'მარაგი', 'wbim' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['low_stock_items'] as $item ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $item->product_id ) ); ?>">
                                        <?php echo esc_html( $item->product_name ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $item->branch_name ); ?></td>
                                <td>
                                    <span class="wbim-stock-qty <?php echo (int) $item->quantity === 0 ? 'wbim-critical' : 'wbim-warning'; ?>">
                                        <?php echo esc_html( $item->quantity ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="wbim-view-all">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-reports&tab=low-stock' ) ); ?>">
                        <?php esc_html_e( 'ყველას ნახვა', 'wbim' ); ?> &rarr;
                    </a>
                </p>
            <?php else : ?>
                <div class="wbim-empty-table wbim-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e( 'დაბალი მარაგის პროდუქტები არ არის!', 'wbim' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="wbim-quick-actions">
        <h3><?php esc_html_e( 'სწრაფი მოქმედებები', 'wbim' ); ?></h3>
        <div class="wbim-action-buttons">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-branches&action=add' ) ); ?>" class="button button-hero">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'ფილიალის დამატება', 'wbim' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=new' ) ); ?>" class="button button-hero">
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e( 'გადატანის შექმნა', 'wbim' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-reports' ) ); ?>" class="button button-hero">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e( 'რეპორტები', 'wbim' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-settings' ) ); ?>" class="button button-hero">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'პარამეტრები', 'wbim' ); ?>
            </a>
        </div>
    </div>

    <?php if ( $total_branches === 0 ) : ?>
    <!-- Empty State -->
    <div class="wbim-empty-state">
        <span class="dashicons dashicons-store"></span>
        <h3><?php esc_html_e( 'ფილიალები არ არის', 'wbim' ); ?></h3>
        <p><?php esc_html_e( 'დაიწყეთ პირველი ფილიალის შექმნით.', 'wbim' ); ?></p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-branches&action=add' ) ); ?>" class="button button-primary button-hero">
            <?php esc_html_e( 'პირველი ფილიალის შექმნა', 'wbim' ); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Chart colors
    var colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

    // Sales Chart Data
    var salesData = <?php echo wp_json_encode( $stats['week_sales'] ); ?>;

    if ($('#wbim-sales-chart').length && salesData.length > 0) {
        var salesLabels = salesData.map(function(item) {
            var date = new Date(item.date);
            return date.toLocaleDateString('ka-GE', { day: '2-digit', month: '2-digit' });
        });
        var salesValues = salesData.map(function(item) { return item.revenue; });
        var ordersValues = salesData.map(function(item) { return item.orders; });

        new Chart($('#wbim-sales-chart'), {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: '<?php esc_attr_e( 'შემოსავალი', 'wbim' ); ?>',
                    data: salesValues,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: '<?php esc_attr_e( 'შეკვეთები', 'wbim' ); ?>',
                    data: ordersValues,
                    borderColor: '#1cc88a',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    yAxisID: 'y1'
                }]
            },
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
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return '₾' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    // Stock by Branch Chart
    var stockData = <?php echo wp_json_encode( $stats['stock_by_branch'] ); ?>;

    if ($('#wbim-stock-chart').length && stockData.length > 0) {
        var stockLabels = stockData.map(function(item) { return item.branch_name; });
        var stockValues = stockData.map(function(item) { return parseInt(item.total_stock); });

        new Chart($('#wbim-stock-chart'), {
            type: 'doughnut',
            data: {
                labels: stockLabels,
                datasets: [{
                    data: stockValues,
                    backgroundColor: colors.slice(0, stockLabels.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>

<style>
.wbim-dashboard {
    max-width: 1400px;
}
.wbim-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.wbim-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}
.wbim-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wbim-stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: #fff;
}
.wbim-icon-blue { background: #4e73df; }
.wbim-icon-green { background: #1cc88a; }
.wbim-icon-yellow { background: #f6c23e; }
.wbim-icon-purple { background: #6f42c1; }
.wbim-stat-content {
    flex: 1;
}
.wbim-stat-number {
    font-size: 28px;
    font-weight: 600;
    display: block;
    line-height: 1.2;
}
.wbim-stat-label {
    color: #666;
    font-size: 13px;
}
.wbim-value-card {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: #fff;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.wbim-value-label {
    font-size: 14px;
    opacity: 0.9;
    display: block;
    margin-bottom: 5px;
}
.wbim-value-amount {
    font-size: 32px;
    font-weight: 600;
}
.wbim-value-amount .woocommerce-Price-currencySymbol {
    font-size: 24px;
}
.wbim-mini-stat {
    font-size: 13px;
    opacity: 0.9;
}
.wbim-mini-stat .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}
.wbim-text-danger {
    color: #f8d7da !important;
}
.wbim-charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.wbim-chart-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.wbim-chart-card h3 {
    margin: 0 0 15px;
    font-size: 16px;
}
.wbim-chart-container {
    height: 250px;
    position: relative;
}
.wbim-tables-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.wbim-table-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.wbim-table-card h3 {
    margin: 0 0 15px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wbim-badge {
    font-size: 11px;
    padding: 3px 8px;
    background: #e9ecef;
    border-radius: 10px;
    font-weight: normal;
}
.wbim-badge-warning {
    background: #fff3cd;
    color: #856404;
}
.wbim-empty-table {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}
.wbim-empty-table .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ccc;
    margin-bottom: 10px;
}
.wbim-empty-table.wbim-success .dashicons {
    color: #28a745;
}
.wbim-view-all {
    margin: 15px 0 0;
    text-align: right;
}
.wbim-stock-qty {
    font-weight: bold;
}
.wbim-stock-qty.wbim-critical {
    color: #dc3545;
}
.wbim-stock-qty.wbim-warning {
    color: #ffc107;
}
.wbim-quick-actions {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.wbim-quick-actions h3 {
    margin: 0 0 15px;
}
.wbim-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.wbim-action-buttons .button-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    height: auto;
    line-height: 1.4;
}
.wbim-action-buttons .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}
.wbim-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.wbim-empty-state .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #ccc;
    margin-bottom: 20px;
}
.wbim-empty-state h3 {
    margin: 0 0 10px;
}
.wbim-empty-state p {
    color: #666;
    margin-bottom: 20px;
}
</style>
