<?php
/**
 * Product Data Tab - Branch Inventory
 *
 * Shows branch inventory management in the WooCommerce product edit page.
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables passed from parent: $product
$product_id = $product->get_id();
$is_variable = $product->is_type( 'variable' );
$branches = WBIM_Branch::get_active();

// Get variations for variable products
$variations = array();
if ( $is_variable ) {
    $variation_ids = $product->get_children();
    foreach ( $variation_ids as $var_id ) {
        $variation = wc_get_product( $var_id );
        if ( $variation ) {
            $variations[] = array(
                'id'         => $var_id,
                'name'       => wc_get_formatted_variation( $variation, true, false ),
                'sku'        => $variation->get_sku(),
                'attributes' => $variation->get_variation_attributes(),
            );
        }
    }
}
?>

<div id="wbim_inventory_data" class="panel woocommerce_options_panel hidden">
    <?php wp_nonce_field( 'wbim_save_product_stock', 'wbim_product_nonce' ); ?>

    <?php if ( empty( $branches ) ) : ?>
        <div class="wbim-product-notice">
            <p>
                <?php
                printf(
                    /* translators: %s: add branch URL */
                    esc_html__( 'ფილიალის მარაგის სამართავად, ჯერ %s.', 'wbim' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=wbim-branches&action=add' ) ) . '">' . esc_html__( 'დაამატეთ ფილიალი', 'wbim' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php elseif ( $is_variable ) : ?>
        <!-- Variable Product Interface -->
        <div class="wbim-variable-stock-manager">
            <!-- All Variations View -->
            <div id="wbim-all-variations-view" class="wbim-variations-view">
                <h4><?php esc_html_e( 'ყველა ვარიაციის მარაგი', 'wbim' ); ?></h4>
                <table class="widefat wbim-variations-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ვარიაცია', 'wbim' ); ?></th>
                            <?php foreach ( $branches as $branch ) : ?>
                                <th><?php echo esc_html( $branch->name ); ?></th>
                            <?php endforeach; ?>
                            <th><?php esc_html_e( 'ჯამი', 'wbim' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $variations as $var ) : ?>
                            <?php
                            $var_total = 0;
                            ?>
                            <tr data-variation-id="<?php echo esc_attr( $var['id'] ); ?>">
                                <td>
                                    <strong><?php echo esc_html( $var['name'] ); ?></strong>
                                    <?php if ( $var['sku'] ) : ?>
                                        <br><small><?php echo esc_html( $var['sku'] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ( $branches as $branch ) : ?>
                                    <?php
                                    $stock = WBIM_Stock::get( $product_id, $branch->id, $var['id'] );
                                    $quantity = $stock ? $stock->quantity : 0;
                                    $var_total += $quantity;
                                    ?>
                                    <td>
                                        <input type="number"
                                               name="wbim_variation_stock[<?php echo esc_attr( $var['id'] ); ?>][<?php echo esc_attr( $branch->id ); ?>][quantity]"
                                               value="<?php echo esc_attr( $quantity ); ?>"
                                               min="0"
                                               step="1"
                                               class="wbim-var-quantity-input"
                                               data-variation-id="<?php echo esc_attr( $var['id'] ); ?>"
                                               data-branch-id="<?php echo esc_attr( $branch->id ); ?>" />
                                    </td>
                                <?php endforeach; ?>
                                <td class="wbim-var-total">
                                    <strong><?php echo esc_html( $var_total ); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></th>
                            <?php
                            $grand_total = 0;
                            foreach ( $branches as $branch ) :
                                $branch_total = 0;
                                foreach ( $variations as $var ) {
                                    $stock = WBIM_Stock::get( $product_id, $branch->id, $var['id'] );
                                    $branch_total += $stock ? $stock->quantity : 0;
                                }
                                $grand_total += $branch_total;
                                ?>
                                <th class="wbim-branch-total" data-branch-id="<?php echo esc_attr( $branch->id ); ?>">
                                    <?php echo esc_html( $branch_total ); ?>
                                </th>
                            <?php endforeach; ?>
                            <th id="wbim-grand-total"><?php echo esc_html( $grand_total ); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>


            <p class="description" style="margin-top: 15px;">
                <?php esc_html_e( 'WooCommerce-ის მთავარი მარაგი ავტომატურად სინქრონიზდება ყველა ფილიალის მარაგის ჯამით.', 'wbim' ); ?>
            </p>
        </div>

    <?php else : ?>
        <!-- Simple Product Interface -->
        <?php $stock_statuses = WBIM_Stock::get_stock_status_options(); ?>
        <div class="wbim-branch-stock-table">
            <h4><?php esc_html_e( 'ფილიალის მარაგი', 'wbim' ); ?></h4>

            <table class="widefat wbim-stock-table-with-status">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'სტატუსი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'დაბალი მარაგის ზღვარი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'თაროს ადგილმდებარეობა', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_stock = 0;
                    foreach ( $branches as $branch ) :
                        $stock = WBIM_Stock::get( $product_id, $branch->id, 0 );
                        $quantity = $stock ? $stock->quantity : 0;
                        $current_status = $stock && isset( $stock->stock_status ) ? $stock->stock_status : 'instock';
                        $threshold = $stock ? $stock->low_stock_threshold : 0;
                        $location = $stock ? $stock->shelf_location : '';
                        $total_stock += $quantity;
                        $show_quantity = in_array( $current_status, array( 'instock', 'low' ), true );
                        ?>
                        <tr data-branch-id="<?php echo esc_attr( $branch->id ); ?>">
                            <td>
                                <strong><?php echo esc_html( $branch->name ); ?></strong>
                                <?php if ( $branch->city ) : ?>
                                    <br><small class="description"><?php echo esc_html( $branch->city ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="wbim-status-cell">
                                <div class="wbim-status-select-wrapper" data-status="<?php echo esc_attr( $current_status ); ?>">
                                    <select name="wbim_branch_stock[<?php echo esc_attr( $branch->id ); ?>][stock_status]"
                                            class="wbim-status-select wbim-status-<?php echo esc_attr( $current_status ); ?>">
                                        <?php foreach ( $stock_statuses as $status_key => $status_label ) : ?>
                                            <option value="<?php echo esc_attr( $status_key ); ?>"
                                                    <?php selected( $current_status, $status_key ); ?>
                                                    data-status="<?php echo esc_attr( $status_key ); ?>">
                                                <?php echo esc_html( $status_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="wbim-status-indicator"></span>
                                </div>
                            </td>
                            <td class="wbim-quantity-cell" <?php echo ! $show_quantity ? 'style="opacity: 0.5;"' : ''; ?>>
                                <input type="number"
                                       name="wbim_branch_stock[<?php echo esc_attr( $branch->id ); ?>][quantity]"
                                       value="<?php echo esc_attr( $quantity ); ?>"
                                       min="0"
                                       step="1"
                                       class="short wbim-quantity-input"
                                       <?php echo ! $show_quantity ? 'disabled' : ''; ?> />
                            </td>
                            <td>
                                <input type="number"
                                       name="wbim_branch_stock[<?php echo esc_attr( $branch->id ); ?>][low_stock_threshold]"
                                       value="<?php echo esc_attr( $threshold ); ?>"
                                       min="0"
                                       step="1"
                                       class="short" />
                            </td>
                            <td>
                                <input type="text"
                                       name="wbim_branch_stock[<?php echo esc_attr( $branch->id ); ?>][shelf_location]"
                                       value="<?php echo esc_attr( $location ); ?>"
                                       class="short"
                                       placeholder="<?php esc_attr_e( 'მაგ: A1-B2', 'wbim' ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2"><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></th>
                        <th colspan="3">
                            <span id="wbim-total-stock"><?php echo esc_html( $total_stock ); ?></span>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <p class="description">
                <?php esc_html_e( 'WooCommerce-ის მთავარი მარაგი ავტომატურად სინქრონიზდება ყველა ფილიალის მარაგის ჯამით.', 'wbim' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php
    // Show recent stock history for both simple and variable products
    $history = WBIM_Stock_Log::get_product_history( $product_id, 0, 5 );
    if ( ! empty( $history ) ) :
        ?>
        <div class="wbim-product-history">
            <h4><?php esc_html_e( 'ბოლო ცვლილებები', 'wbim' ); ?></h4>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'თარიღი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ქმედება', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ცვლილება', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'მომხმარებელი', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $history as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( WBIM_Utils::format_date( $log->created_at, true ) ); ?></td>
                            <td><?php echo esc_html( $log->branch_name ); ?></td>
                            <td><?php echo esc_html( WBIM_Stock_Log::get_action_label( $log->action_type ) ); ?></td>
                            <td>
                                <?php
                                $change = $log->quantity_change;
                                $class = $change > 0 ? 'positive' : ( $change < 0 ? 'negative' : '' );
                                $prefix = $change > 0 ? '+' : '';
                                ?>
                                <span class="wbim-change <?php echo esc_attr( $class ); ?>">
                                    <?php echo esc_html( $prefix . $change ); ?>
                                </span>
                                <small>(<?php echo esc_html( $log->quantity_before . ' → ' . $log->quantity_after ); ?>)</small>
                            </td>
                            <td><?php echo esc_html( $log->user_name ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-history&product_id=' . $product_id ) ); ?>">
                    <?php esc_html_e( 'სრული ისტორიის ნახვა →', 'wbim' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
    #wbim_inventory_data {
        padding: 15px;
    }

    .wbim-product-notice {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 12px 15px;
        margin: 10px 0;
    }

    .wbim-branch-stock-table,
    .wbim-variable-stock-manager {
        margin: 10px 0;
    }

    .wbim-branch-stock-table h4,
    .wbim-product-history h4,
    .wbim-variable-stock-manager h4 {
        margin: 0 0 10px;
        font-size: 14px;
    }

    .wbim-branch-stock-table table,
    .wbim-variations-table {
        margin-bottom: 10px;
    }

    .wbim-branch-stock-table input.short {
        width: 100px;
    }

    .wbim-product-history {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .wbim-change.positive {
        color: #46b450;
    }

    .wbim-change.negative {
        color: #dc3232;
    }

    #wbim-total-stock,
    #wbim-grand-total,
    .wbim-variation-total {
        font-weight: bold;
        font-size: 16px;
    }

    .wbim-quantity-input {
        font-weight: bold !important;
    }

    /* Variable product styles */
    .wbim-variations-table input[type="number"] {
        width: 70px;
        text-align: center;
    }

    .wbim-variations-table td,
    .wbim-variations-table th {
        text-align: center;
        vertical-align: middle;
    }

    .wbim-variations-table td:first-child,
    .wbim-variations-table th:first-child {
        text-align: left;
    }

    .wbim-var-total {
        background: #f6f7f7;
    }

    .wbim-single-variation table {
        max-width: 800px;
    }

    .wbim-single-variation input.short {
        width: 100px;
    }

    /* Stock Status Select */
    .wbim-status-cell {
        min-width: 220px;
        padding: 12px !important;
    }

    .wbim-status-select-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
        width: 100%;
    }

    .wbim-status-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        width: 100%;
        padding: 12px 40px 12px 16px;
        font-size: 14px;
        font-weight: 600;
        border: 2px solid #ddd;
        border-radius: 8px;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .wbim-status-select:hover {
        border-color: #0073aa;
    }

    .wbim-status-select:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.15);
    }

    .wbim-status-indicator {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        border-radius: 8px 0 0 8px;
        transition: background-color 0.2s ease;
    }

    /* Status colors */
    .wbim-status-select.wbim-status-instock {
        background-color: #d4edda;
        border-color: #28a745;
        color: #155724;
    }
    .wbim-status-select-wrapper[data-status="instock"] .wbim-status-indicator {
        background-color: #28a745;
    }

    .wbim-status-select.wbim-status-low {
        background-color: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }
    .wbim-status-select-wrapper[data-status="low"] .wbim-status-indicator {
        background-color: #ffc107;
    }

    .wbim-status-select.wbim-status-outofstock {
        background-color: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }
    .wbim-status-select-wrapper[data-status="outofstock"] .wbim-status-indicator {
        background-color: #dc3545;
    }

    .wbim-status-select.wbim-status-preorder {
        background-color: #d1ecf1;
        border-color: #17a2b8;
        color: #0c5460;
    }
    .wbim-status-select-wrapper[data-status="preorder"] .wbim-status-indicator {
        background-color: #17a2b8;
    }

    .wbim-quantity-cell {
        transition: opacity 0.3s ease;
    }

    .wbim-quantity-cell.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    .wbim-stock-table-with-status th {
        white-space: nowrap;
    }

    .wbim-stock-table-with-status {
        table-layout: auto;
    }

    .wbim-stock-table-with-status td {
        vertical-align: middle;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // Update total when quantities change (simple product)
    $('#wbim_inventory_data').on('input', '.wbim-quantity-input', function() {
        var total = 0;
        $(this).closest('tbody').find('.wbim-quantity-input').each(function() {
            total += parseInt($(this).val()) || 0;
        });
        $(this).closest('table').find('#wbim-total-stock, .wbim-variation-total').text(total);
    });

    // Update totals for all variations view
    $('#wbim_inventory_data').on('input', '.wbim-var-quantity-input', function() {
        var $row = $(this).closest('tr');
        var $table = $(this).closest('table');

        // Update row total
        var rowTotal = 0;
        $row.find('.wbim-var-quantity-input').each(function() {
            rowTotal += parseInt($(this).val()) || 0;
        });
        $row.find('.wbim-var-total strong').text(rowTotal);

        // Update column totals
        var branchId = $(this).data('branch-id');
        var branchTotal = 0;
        $table.find('.wbim-var-quantity-input[data-branch-id="' + branchId + '"]').each(function() {
            branchTotal += parseInt($(this).val()) || 0;
        });
        $table.find('.wbim-branch-total[data-branch-id="' + branchId + '"]').text(branchTotal);

        // Update grand total
        var grandTotal = 0;
        $table.find('.wbim-var-total strong').each(function() {
            grandTotal += parseInt($(this).text()) || 0;
        });
        $('#wbim-grand-total').text(grandTotal);
    });

    // Sync from variation accordion to "ფილიალის მარაგი" tab
    $(document).on('input', '.wbim-variation-stock-input', function() {
        var variationId = $(this).data('variation-id');
        var branchId = $(this).data('branch-id');
        var newValue = $(this).val();

        // Find and update corresponding input in product tab
        var $tabInput = $('#wbim_inventory_data input[name="wbim_variation_stock[' + variationId + '][' + branchId + '][quantity]"]');
        if ($tabInput.length) {
            $tabInput.val(newValue).trigger('input');
        }
    });

    // Sync from "ფილიალის მარაგი" tab to variation accordion
    $('#wbim_inventory_data').on('input', '.wbim-var-quantity-input', function() {
        var variationId = $(this).data('variation-id');
        var branchId = $(this).data('branch-id');
        var newValue = $(this).val();

        // Find and update corresponding input in variation accordion
        var $accordionInput = $('.wbim-variation-stock-input[data-variation-id="' + variationId + '"][data-branch-id="' + branchId + '"]');
        if ($accordionInput.length) {
            $accordionInput.val(newValue);
        }
    });

    // Stock status select change handler
    $('#wbim_inventory_data').on('change', '.wbim-status-select', function() {
        var $select = $(this);
        var $row = $select.closest('tr');
        var $wrapper = $select.closest('.wbim-status-select-wrapper');
        var $quantityCell = $row.find('.wbim-quantity-cell');
        var $quantityInput = $quantityCell.find('.wbim-quantity-input');
        var status = $select.val();

        // Update wrapper data attribute for indicator color
        $wrapper.attr('data-status', status);

        // Update select class for background color
        $select.removeClass('wbim-status-instock wbim-status-low wbim-status-outofstock wbim-status-preorder');
        $select.addClass('wbim-status-' + status);

        // Statuses that show quantity field
        var showQuantityStatuses = ['instock', 'low'];

        if (showQuantityStatuses.indexOf(status) !== -1) {
            $quantityCell.css('opacity', '1');
            $quantityInput.prop('disabled', false);
        } else {
            $quantityCell.css('opacity', '0.5');
            $quantityInput.prop('disabled', true);
        }
    });
});
</script>
