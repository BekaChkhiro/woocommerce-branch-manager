<?php
/**
 * Stock Movement Report View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$action_types = array(
    ''             => __( 'ყველა ტიპი', 'wbim' ),
    'sale'         => __( 'გაყიდვა', 'wbim' ),
    'restock'      => __( 'შევსება', 'wbim' ),
    'transfer_in'  => __( 'გადმოტანა', 'wbim' ),
    'transfer_out' => __( 'გადატანა', 'wbim' ),
    'adjustment'   => __( 'კორექტირება', 'wbim' ),
    'return'       => __( 'დაბრუნება', 'wbim' ),
);

$action_labels = array(
    'sale'         => __( 'გაყიდვა', 'wbim' ),
    'restock'      => __( 'შევსება', 'wbim' ),
    'transfer_in'  => __( 'გადმოტანა', 'wbim' ),
    'transfer_out' => __( 'გადატანა', 'wbim' ),
    'adjustment'   => __( 'კორექტირება', 'wbim' ),
    'return'       => __( 'დაბრუნება', 'wbim' ),
);
?>

<div class="wbim-report wbim-movement-report">
    <!-- Filters -->
    <div class="wbim-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-reports">
            <input type="hidden" name="tab" value="movement">

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
                    <label for="product_search"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></label>
                    <select name="product_id" id="product_search" class="wbim-product-search" style="width: 200px;">
                        <?php if ( $product_id ) : ?>
                            <?php $product = wc_get_product( $product_id ); ?>
                            <?php if ( $product ) : ?>
                                <option value="<?php echo esc_attr( $product_id ); ?>" selected>
                                    <?php echo esc_html( $product->get_name() ); ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
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
                    <label for="action_type"><?php esc_html_e( 'ტიპი', 'wbim' ); ?></label>
                    <select name="action_type" id="action_type">
                        <?php foreach ( $action_types as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $action_type, $key ); ?>>
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
            </div>
        </form>
    </div>

    <!-- Summary by Action Type -->
    <?php if ( ! empty( $report['summary'] ) ) : ?>
    <div class="wbim-summary-cards wbim-summary-small">
        <?php foreach ( $report['summary'] as $sum ) : ?>
            <div class="wbim-summary-card wbim-card-small">
                <div class="wbim-card-content">
                    <span class="wbim-card-label">
                        <?php echo esc_html( isset( $action_labels[ $sum->action_type ] ) ? $action_labels[ $sum->action_type ] : $sum->action_type ); ?>
                    </span>
                    <span class="wbim-card-value"><?php echo esc_html( $sum->count ); ?></span>
                    <div class="wbim-card-stats">
                        <span class="wbim-stat-in">+<?php echo esc_html( $sum->total_in ); ?></span>
                        <span class="wbim-stat-out">-<?php echo esc_html( $sum->total_out ); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Report Table -->
    <div class="wbim-report-table-wrapper">
        <table class="wp-list-table widefat fixed striped wbim-report-table">
            <thead>
                <tr>
                    <th class="column-date"><?php esc_html_e( 'თარიღი', 'wbim' ); ?></th>
                    <th class="column-product"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th class="column-branch"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                    <th class="column-type"><?php esc_html_e( 'ტიპი', 'wbim' ); ?></th>
                    <th class="column-change"><?php esc_html_e( 'ცვლილება', 'wbim' ); ?></th>
                    <th class="column-before"><?php esc_html_e( 'წინა', 'wbim' ); ?></th>
                    <th class="column-after"><?php esc_html_e( 'შემდეგ', 'wbim' ); ?></th>
                    <th class="column-user"><?php esc_html_e( 'მომხმარებელი', 'wbim' ); ?></th>
                    <th class="column-note"><?php esc_html_e( 'შენიშვნა', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $report['items'] ) ) : ?>
                    <tr>
                        <td colspan="9"><?php esc_html_e( 'მონაცემები არ მოიძებნა.', 'wbim' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $report['items'] as $item ) : ?>
                        <tr>
                            <td class="column-date">
                                <?php
                                $date = new DateTime( $item->created_at );
                                echo esc_html( $date->format( 'd/m/Y H:i' ) );
                                ?>
                            </td>
                            <td class="column-product">
                                <a href="<?php echo esc_url( get_edit_post_link( $item->product_id ) ); ?>">
                                    <?php echo esc_html( $item->product_name ); ?>
                                </a>
                            </td>
                            <td class="column-branch"><?php echo esc_html( $item->branch_name ); ?></td>
                            <td class="column-type">
                                <span class="wbim-action-badge wbim-action-<?php echo esc_attr( $item->action_type ); ?>">
                                    <?php echo esc_html( isset( $action_labels[ $item->action_type ] ) ? $action_labels[ $item->action_type ] : $item->action_type ); ?>
                                </span>
                            </td>
                            <td class="column-change">
                                <?php
                                $change = (int) $item->quantity_change;
                                $class = $change >= 0 ? 'positive' : 'negative';
                                $sign = $change >= 0 ? '+' : '';
                                ?>
                                <span class="wbim-change-<?php echo esc_attr( $class ); ?>">
                                    <?php echo esc_html( $sign . $change ); ?>
                                </span>
                            </td>
                            <td class="column-before"><?php echo esc_html( $item->quantity_before ); ?></td>
                            <td class="column-after"><?php echo esc_html( $item->quantity_after ); ?></td>
                            <td class="column-user"><?php echo esc_html( $item->user_name ?: '—' ); ?></td>
                            <td class="column-note">
                                <?php if ( $item->note ) : ?>
                                    <span title="<?php echo esc_attr( $item->note ); ?>">
                                        <?php echo esc_html( wp_trim_words( $item->note, 5, '...' ) ); ?>
                                    </span>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="wbim-pagination">
            <?php
            $base_url = add_query_arg( array(
                'page'        => 'wbim-reports',
                'tab'         => 'movement',
                'branch_id'   => $branch_id,
                'product_id'  => $product_id,
                'date_from'   => $date_from,
                'date_to'     => $date_to,
                'action_type' => $action_type,
            ), admin_url( 'admin.php' ) );

            $pagination_args = array(
                'base'      => $base_url . '&paged=%#%',
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo; ' . __( 'წინა', 'wbim' ),
                'next_text' => __( 'შემდეგი', 'wbim' ) . ' &raquo;',
            );

            echo wp_kses_post( paginate_links( $pagination_args ) );
            ?>
        </div>
    <?php endif; ?>
</div>

<style>
.wbim-summary-small {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.wbim-card-small {
    min-width: 120px;
    flex: 0 0 auto;
    padding: 15px;
}
.wbim-card-small .wbim-card-label {
    font-size: 12px;
    color: #666;
    display: block;
    margin-bottom: 5px;
}
.wbim-card-small .wbim-card-value {
    font-size: 24px;
    font-weight: bold;
}
.wbim-card-stats {
    margin-top: 5px;
    font-size: 12px;
}
.wbim-stat-in {
    color: #28a745;
    margin-right: 10px;
}
.wbim-stat-out {
    color: #dc3545;
}
.wbim-action-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}
.wbim-action-sale {
    background: #d4edda;
    color: #155724;
}
.wbim-action-restock {
    background: #cce5ff;
    color: #004085;
}
.wbim-action-transfer_in {
    background: #d1ecf1;
    color: #0c5460;
}
.wbim-action-transfer_out {
    background: #fff3cd;
    color: #856404;
}
.wbim-action-adjustment {
    background: #e2e3e5;
    color: #383d41;
}
.wbim-action-return {
    background: #f8d7da;
    color: #721c24;
}
.wbim-change-positive {
    color: #28a745;
    font-weight: bold;
}
.wbim-change-negative {
    color: #dc3545;
    font-weight: bold;
}
.wbim-pagination {
    margin-top: 20px;
    text-align: center;
}
.wbim-pagination .page-numbers {
    display: inline-block;
    padding: 5px 10px;
    margin: 0 2px;
    background: #fff;
    border: 1px solid #ddd;
    text-decoration: none;
}
.wbim-pagination .page-numbers.current {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Product search
    if ($('.wbim-product-search').length && $.fn.select2) {
        $('.wbim-product-search').select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wbim_search_products',
                        nonce: wbimAdmin.nonce,
                        term: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.data || []
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: '<?php esc_attr_e( 'პროდუქტის ძებნა...', 'wbim' ); ?>',
            allowClear: true
        });
    }
});
</script>
