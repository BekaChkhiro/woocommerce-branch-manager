<?php
/**
 * Stock Report View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wbim-report wbim-stock-report">
    <!-- Filters -->
    <div class="wbim-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-reports">
            <input type="hidden" name="tab" value="stock">

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
                <span class="dashicons dashicons-archive"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( $report['totals']['total_stock'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ მარაგი', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo wp_kses_post( wc_price( $report['totals']['total_value'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'სულ ღირებულება', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( number_format_i18n( count( $report['items'] ) ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'პროდუქტები', 'wbim' ); ?></span>
            </div>
        </div>

        <div class="wbim-summary-card">
            <div class="wbim-card-icon">
                <span class="dashicons dashicons-store"></span>
            </div>
            <div class="wbim-card-content">
                <span class="wbim-card-value"><?php echo esc_html( count( $report['branches'] ) ); ?></span>
                <span class="wbim-card-label"><?php esc_html_e( 'ფილიალები', 'wbim' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Report Table -->
    <div class="wbim-report-table-wrapper">
        <table class="wp-list-table widefat fixed striped wbim-report-table">
            <thead>
                <tr>
                    <th class="column-product"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th class="column-sku"><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                    <?php foreach ( $report['branches'] as $branch ) : ?>
                        <th class="column-branch"><?php echo esc_html( $branch->name ); ?></th>
                    <?php endforeach; ?>
                    <th class="column-total"><?php esc_html_e( 'ჯამი', 'wbim' ); ?></th>
                    <th class="column-value"><?php esc_html_e( 'ღირებულება', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $report['items'] ) ) : ?>
                    <tr>
                        <td colspan="<?php echo esc_attr( count( $report['branches'] ) + 4 ); ?>">
                            <?php esc_html_e( 'მონაცემები არ მოიძებნა.', 'wbim' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $report['items'] as $item ) : ?>
                        <tr>
                            <td class="column-product">
                                <a href="<?php echo esc_url( get_edit_post_link( $item->product_id ) ); ?>">
                                    <?php echo esc_html( $item->product_name ); ?>
                                </a>
                            </td>
                            <td class="column-sku"><?php echo esc_html( $item->sku ); ?></td>
                            <?php foreach ( $report['branches'] as $branch ) : ?>
                                <?php
                                $key = 'branch_' . $branch->id;
                                $qty = isset( $item->$key ) ? (int) $item->$key : 0;
                                $class = $qty === 0 ? 'out-of-stock' : ( $qty <= 5 ? 'low-stock' : '' );
                                ?>
                                <td class="column-branch <?php echo esc_attr( $class ); ?>">
                                    <?php echo esc_html( $qty ); ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="column-total"><strong><?php echo esc_html( $item->total_stock ); ?></strong></td>
                            <td class="column-value">
                                <?php
                                $price = floatval( $item->price );
                                echo wp_kses_post( wc_price( $price * (int) $item->total_stock ) );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td><strong><?php esc_html_e( 'სულ', 'wbim' ); ?></strong></td>
                    <td></td>
                    <?php foreach ( $report['branches'] as $branch ) : ?>
                        <?php $key = 'branch_' . $branch->id; ?>
                        <td><strong><?php echo esc_html( number_format_i18n( $report['totals'][ $key ] ) ); ?></strong></td>
                    <?php endforeach; ?>
                    <td><strong><?php echo esc_html( number_format_i18n( $report['totals']['total_stock'] ) ); ?></strong></td>
                    <td><strong><?php echo wp_kses_post( wc_price( $report['totals']['total_value'] ) ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.wbim-export-btn').on('click', function() {
        var format = $(this).data('format');
        var filters = {
            branch_id: $('#branch_id').val(),
            category_id: $('#category_id').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_export_report',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wbim_admin_nonce' ) ); ?>',
                report_type: 'stock',
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
