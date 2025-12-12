<?php
/**
 * Low Stock Report View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wbim-report wbim-low-stock-report">
    <!-- Filters -->
    <div class="wbim-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-reports">
            <input type="hidden" name="tab" value="low-stock">

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
                    <label for="category_id"><?php esc_html_e( 'კატეგორია', 'wbim' ); ?></label>
                    <select name="category_id" id="category_id">
                        <option value=""><?php esc_html_e( 'ყველა კატეგორია', 'wbim' ); ?></option>
                        <?php foreach ( $categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $category_id, $category->term_id ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wbim-filter-item">
                    <label>
                        <input type="checkbox" name="include_zero" value="1" <?php checked( $include_zero ); ?>>
                        <?php esc_html_e( 'ნულოვანი მარაგის ჩათვლით', 'wbim' ); ?>
                    </label>
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
        <div class="wbim-summary-card wbim-card-danger">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['by_status']['critical'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'კრიტიკული (0 მარაგი)', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card wbim-card-warning">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-flag"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['by_status']['warning'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'გაფრთხილება (დაბალი მარაგი)', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-info"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( $report['threshold'] ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'ნაგულისხმევი ზღვარი', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( count( $report['items'] ) ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ ჩანაწერები', 'wbim' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Alert Legend -->
    <div class="wbim-alert-legend">
        <span class="wbim-legend-item critical">
            <span class="wbim-legend-dot"></span>
            <?php esc_html_e( 'კრიტიკული - მარაგი = 0', 'wbim' ); ?>
        </span>
        <span class="wbim-legend-item warning">
            <span class="wbim-legend-dot"></span>
            <?php esc_html_e( 'გაფრთხილება - მარაგი <= ზღვარი', 'wbim' ); ?>
        </span>
    </div>

    <!-- Report Table -->
    <div class="wbim-report-table-wrapper">
        <table class="wp-list-table widefat fixed striped wbim-report-table">
            <thead>
                <tr>
                    <th class="column-status" style="width: 30px;"></th>
                    <th class="column-product"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th class="column-sku"><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                    <th class="column-branch"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                    <th class="column-stock"><?php esc_html_e( 'მარაგი', 'wbim' ); ?></th>
                    <th class="column-threshold"><?php esc_html_e( 'ზღვარი', 'wbim' ); ?></th>
                    <th class="column-available"><?php esc_html_e( 'ხელმისაწვდომია', 'wbim' ); ?></th>
                    <th class="column-actions"><?php esc_html_e( 'მოქმედება', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $report['items'] ) ) : ?>
                    <tr>
                        <td colspan="8">
                            <div class="wbim-no-alerts">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'დაბალი მარაგის პროდუქტები არ მოიძებნა!', 'wbim' ); ?>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $report['items'] as $item ) : ?>
                        <tr class="wbim-row-<?php echo esc_attr( $item->status ); ?>">
                            <td class="column-status">
                                <span class="wbim-status-indicator <?php echo esc_attr( $item->status ); ?>"
                                      title="<?php echo esc_attr( $item->status === 'critical' ? __( 'კრიტიკული', 'wbim' ) : __( 'გაფრთხილება', 'wbim' ) ); ?>">
                                </span>
                            </td>
                            <td class="column-product">
                                <a href="<?php echo esc_url( get_edit_post_link( $item->product_id ) ); ?>">
                                    <?php echo esc_html( $item->product_name ); ?>
                                </a>
                                <?php if ( $item->variation_id > 0 ) : ?>
                                    <span class="wbim-variation-badge"><?php esc_html_e( 'ვარიაცია', 'wbim' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-sku"><?php echo esc_html( $item->sku ); ?></td>
                            <td class="column-branch"><?php echo esc_html( $item->branch_name ); ?></td>
                            <td class="column-stock">
                                <span class="wbim-stock-qty <?php echo esc_attr( $item->status ); ?>">
                                    <?php echo esc_html( $item->quantity ); ?>
                                </span>
                            </td>
                            <td class="column-threshold">
                                <?php echo esc_html( $item->low_stock_threshold > 0 ? $item->low_stock_threshold : $report['threshold'] ); ?>
                            </td>
                            <td class="column-available">
                                <?php if ( ! empty( $item->available_from ) ) : ?>
                                    <div class="wbim-available-branches">
                                        <?php foreach ( $item->available_from as $available ) : ?>
                                            <span class="wbim-available-item">
                                                <?php echo esc_html( $available->branch_name ); ?>: <strong><?php echo esc_html( $available->quantity ); ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <span class="wbim-no-available"><?php esc_html_e( 'არ არის', 'wbim' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if ( ! empty( $item->available_from ) ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=new&to_branch=' . $item->branch_id . '&product=' . $item->product_id ) ); ?>"
                                       class="button button-small"
                                       title="<?php esc_attr_e( 'გადატანის შექმნა', 'wbim' ); ?>">
                                        <span class="dashicons dashicons-randomize"></span>
                                        <?php esc_html_e( 'გადატანა', 'wbim' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.wbim-status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.wbim-status-indicator.critical {
    background-color: #dc3545;
}
.wbim-status-indicator.warning {
    background-color: #ffc107;
}
.wbim-stock-qty.critical {
    color: #dc3545;
    font-weight: bold;
}
.wbim-stock-qty.warning {
    color: #856404;
    font-weight: bold;
}
.wbim-row-critical {
    background-color: rgba(220, 53, 69, 0.05) !important;
}
.wbim-row-warning {
    background-color: rgba(255, 193, 7, 0.05) !important;
}
.wbim-alert-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 4px;
}
.wbim-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}
.wbim-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.wbim-legend-item.critical .wbim-legend-dot {
    background-color: #dc3545;
}
.wbim-legend-item.warning .wbim-legend-dot {
    background-color: #ffc107;
}
.wbim-available-branches {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.wbim-available-item {
    font-size: 12px;
    color: #666;
}
.wbim-no-available {
    color: #999;
    font-style: italic;
}
.wbim-no-alerts {
    text-align: center;
    padding: 40px;
    color: #28a745;
}
.wbim-no-alerts .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    display: block;
    margin: 0 auto 10px;
}
.wbim-variation-badge {
    display: inline-block;
    font-size: 10px;
    padding: 2px 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-left: 5px;
}
.column-actions .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    vertical-align: middle;
    margin-right: 3px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Export buttons
    $('.wbim-export-btn').on('click', function() {
        var format = $(this).data('format');
        var filters = {
            branch_id: $('#branch_id').val(),
            category_id: $('#category_id').val(),
            include_zero: $('input[name="include_zero"]').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_export_report',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wbim_admin_nonce' ) ); ?>',
                report_type: 'low-stock',
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
