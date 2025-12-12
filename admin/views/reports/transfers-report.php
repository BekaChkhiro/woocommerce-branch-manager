<?php
/**
 * Transfers Report View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$statuses = array(
    ''           => __( 'ყველა სტატუსი', 'wbim' ),
    'draft'      => __( 'დრაფტი', 'wbim' ),
    'pending'    => __( 'მოლოდინში', 'wbim' ),
    'in_transit' => __( 'ტრანზიტში', 'wbim' ),
    'completed'  => __( 'დასრულებული', 'wbim' ),
    'cancelled'  => __( 'გაუქმებული', 'wbim' ),
);
?>

<div class="wbim-report wbim-transfers-report">
    <!-- Filters -->
    <div class="wbim-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-reports">
            <input type="hidden" name="tab" value="transfers">

            <div class="wbim-filter-row">
                <div class="wbim-filter-item">
                    <label for="from_branch_id"><?php esc_html_e( 'საიდან', 'wbim' ); ?></label>
                    <select name="from_branch_id" id="from_branch_id">
                        <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $from_branch_id, $branch->id ); ?>>
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wbim-filter-item">
                    <label for="to_branch_id"><?php esc_html_e( 'სად', 'wbim' ); ?></label>
                    <select name="to_branch_id" id="to_branch_id">
                        <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $to_branch_id, $branch->id ); ?>>
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
                    <label for="status"><?php esc_html_e( 'სტატუსი', 'wbim' ); ?></label>
                    <select name="status" id="status">
                        <?php foreach ( $statuses as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
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
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="wbim-summary-cards">
        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-randomize"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['total'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ გადატანები', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card wbim-card-success">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['completed'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'დასრულებული', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card wbim-card-warning">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['pending'] + $report['summary']['in_transit'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'მოლოდინში', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['summary']['total_items'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'გადატანილი ერთეულები', 'wbim' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="wbim-charts-row">
        <div class="wbim-chart-container">
            <h3><?php esc_html_e( 'გადატანები თვეების მიხედვით', 'wbim' ); ?></h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="wbim-transfers-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Branch Pairs -->
    <?php if ( ! empty( $report['branch_pairs'] ) ) : ?>
    <div class="wbim-section">
        <h3><?php esc_html_e( 'ფილიალებს შორის გადატანები', 'wbim' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'საიდან', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'სად', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'გადატანები', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'ერთეულები', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $report['branch_pairs'] as $pair ) : ?>
                    <tr>
                        <td><?php echo esc_html( $pair->source_name ); ?></td>
                        <td><?php echo esc_html( $pair->destination_name ); ?></td>
                        <td><?php echo esc_html( $pair->transfer_count ); ?></td>
                        <td><?php echo esc_html( $pair->total_items ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Top Products -->
    <?php if ( ! empty( $report['top_products'] ) ) : ?>
    <div class="wbim-section">
        <h3><?php esc_html_e( 'ყველაზე ხშირად გადატანილი პროდუქტები', 'wbim' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'სულ რაოდენობა', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'გადატანები', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $report['top_products'] as $product ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $product->product_id ) ); ?>">
                                <?php echo esc_html( $product->product_name ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $product->sku ); ?></td>
                        <td><?php echo esc_html( $product->total_quantity ); ?></td>
                        <td><?php echo esc_html( $product->transfer_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Monthly Table -->
    <div class="wbim-report-table-wrapper">
        <h3><?php esc_html_e( 'თვიური სტატისტიკა', 'wbim' ); ?></h3>
        <table class="wp-list-table widefat fixed striped wbim-report-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'თვე', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'დრაფტი', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'მოლოდინში', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'ტრანზიტში', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'დასრულებული', 'wbim' ); ?></th>
                    <th><?php esc_html_e( 'გაუქმებული', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $report['monthly'] ) ) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e( 'მონაცემები არ მოიძებნა.', 'wbim' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $report['monthly'] as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['month'] ); ?></td>
                            <td><?php echo esc_html( $row['draft'] ); ?></td>
                            <td><?php echo esc_html( $row['pending'] ); ?></td>
                            <td><?php echo esc_html( $row['in_transit'] ); ?></td>
                            <td class="status-completed"><?php echo esc_html( $row['completed'] ); ?></td>
                            <td class="status-cancelled"><?php echo esc_html( $row['cancelled'] ); ?></td>
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

    // Transfers Chart
    if ($('#wbim-transfers-chart').length && chartData.labels.length > 0) {
        new Chart($('#wbim-transfers-chart'), {
            type: 'bar',
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
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Export buttons
    $('.wbim-export-btn').on('click', function() {
        var format = $(this).data('format');
        var filters = {
            from_branch_id: $('#from_branch_id').val(),
            to_branch_id: $('#to_branch_id').val(),
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val(),
            status: $('#status').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_export_report',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wbim_admin_nonce' ) ); ?>',
                report_type: 'transfers',
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
