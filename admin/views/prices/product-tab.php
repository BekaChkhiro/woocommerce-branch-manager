<?php
/**
 * Product Branch Prices Tab
 *
 * @package WBIM
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$branches = WBIM_Branch::get_all( array( 'is_active' => 1 ) );
$product_id = $product->get_id();
$is_variable = $product->is_type( 'variable' );

// Get existing prices for simple product
$prices_by_branch = array();
if ( ! $is_variable ) {
    $prices_by_branch = WBIM_Branch_Price::get_prices_by_branch( $product_id, 0 );
}
?>

<div id="wbim_prices_data" class="panel woocommerce_options_panel">
    <?php wp_nonce_field( 'wbim_save_product_prices', 'wbim_prices_nonce' ); ?>

    <?php if ( empty( $branches ) ) : ?>
        <div class="wbim-notice wbim-notice-warning">
            <p><?php esc_html_e( 'ფილიალები ვერ მოიძებნა. გთხოვთ დაამატოთ ფილიალები.', 'wbim' ); ?></p>
        </div>
    <?php elseif ( $is_variable ) : ?>
        <div class="wbim-notice wbim-notice-info">
            <p><?php esc_html_e( 'ვარიაციების ფასები მითითებულია ვარიაციების სექციაში.', 'wbim' ); ?></p>
        </div>
    <?php else : ?>
        <div class="wbim-product-prices">
            <p class="form-field">
                <strong><?php esc_html_e( 'ფილიალის ფასები', 'wbim' ); ?></strong><br>
                <span class="description"><?php esc_html_e( 'მიუთითეთ ფილიალის ფასები. თუ ფასი ცარიელია, გამოყენებული იქნება პროდუქტის ძირითადი ფასი.', 'wbim' ); ?></span>
            </p>

            <table class="wbim-branch-prices-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ფასი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ფასდაკლება', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'საბითუმო', 'wbim' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $branches as $branch ) :
                        $branch_price = isset( $prices_by_branch[ $branch->id ][1] ) ? $prices_by_branch[ $branch->id ][1] : null;
                        $has_tiers = isset( $prices_by_branch[ $branch->id ] ) && count( $prices_by_branch[ $branch->id ] ) > 1;
                    ?>
                        <tr data-branch-id="<?php echo esc_attr( $branch->id ); ?>">
                            <td>
                                <strong><?php echo esc_html( $branch->name ); ?></strong>
                            </td>
                            <td>
                                <input type="number"
                                    name="wbim_branch_prices[<?php echo esc_attr( $branch->id ); ?>][1][regular_price]"
                                    class="wbim-price-input short"
                                    step="0.01"
                                    min="0"
                                    placeholder="<?php echo esc_attr( wc_format_localized_price( $product->get_regular_price() ) ); ?>"
                                    value="<?php echo esc_attr( $branch_price ? $branch_price['regular_price'] : '' ); ?>">
                            </td>
                            <td>
                                <input type="number"
                                    name="wbim_branch_prices[<?php echo esc_attr( $branch->id ); ?>][1][sale_price]"
                                    class="wbim-price-input short"
                                    step="0.01"
                                    min="0"
                                    placeholder="<?php echo esc_attr( wc_format_localized_price( $product->get_sale_price() ) ); ?>"
                                    value="<?php echo esc_attr( $branch_price ? $branch_price['sale_price'] : '' ); ?>">
                            </td>
                            <td>
                                <button type="button" class="button wbim-edit-branch-tiers" data-branch-id="<?php echo esc_attr( $branch->id ); ?>" data-branch-name="<?php echo esc_attr( $branch->name ); ?>">
                                    <?php
                                    if ( $has_tiers ) {
                                        $tier_count = count( $prices_by_branch[ $branch->id ] ) - 1;
                                        printf(
                                            /* translators: %d: tier count */
                                            esc_html__( '%d ფასი', 'wbim' ),
                                            $tier_count
                                        );
                                    } else {
                                        esc_html_e( 'დამატება', 'wbim' );
                                    }
                                    ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Hidden containers for wholesale tiers -->
        <?php foreach ( $branches as $branch ) :
            $branch_tiers = isset( $prices_by_branch[ $branch->id ] ) ? $prices_by_branch[ $branch->id ] : array();
        ?>
            <div class="wbim-tiers-data" id="wbim-tiers-data-<?php echo esc_attr( $branch->id ); ?>" style="display: none;">
                <?php
                $tier_index = 0;
                foreach ( $branch_tiers as $min_qty => $tier ) :
                    if ( (int) $min_qty === 1 ) continue; // Skip regular price tier
                    $tier_index++;
                ?>
                    <div class="wbim-tier-row" data-tier-index="<?php echo esc_attr( $tier_index ); ?>">
                        <input type="hidden" name="wbim_branch_prices[<?php echo esc_attr( $branch->id ); ?>][<?php echo esc_attr( $min_qty ); ?>][regular_price]" value="<?php echo esc_attr( $tier['regular_price'] ); ?>">
                        <input type="hidden" name="wbim_branch_prices[<?php echo esc_attr( $branch->id ); ?>][<?php echo esc_attr( $min_qty ); ?>][sale_price]" value="<?php echo esc_attr( $tier['sale_price'] ); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Wholesale Tiers Modal -->
<div id="wbim-product-tiers-modal" class="wbim-modal" style="display: none;">
    <div class="wbim-modal-content">
        <div class="wbim-modal-header">
            <h2><?php esc_html_e( 'საბითუმო ფასები', 'wbim' ); ?> - <span id="wbim-modal-branch-name"></span></h2>
            <button type="button" class="wbim-modal-close">&times;</button>
        </div>
        <div class="wbim-modal-body">
            <p class="description"><?php esc_html_e( 'მიუთითეთ ფასი რაოდენობის მიხედვით. მაგ: 10+ ცალი = 8 ლარი', 'wbim' ); ?></p>

            <table class="wbim-tiers-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'მინ. რაოდენობა', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ფასი', 'wbim' ); ?></th>
                        <th><?php esc_html_e( 'ფასდაკლება', 'wbim' ); ?></th>
                        <th width="40"></th>
                    </tr>
                </thead>
                <tbody id="wbim-modal-tiers-tbody">
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button" id="wbim-modal-add-tier">
                                <?php esc_html_e( '+ დამატება', 'wbim' ); ?>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="wbim-modal-footer">
            <button type="button" class="button button-primary" id="wbim-modal-save-tiers">
                <?php esc_html_e( 'შენახვა', 'wbim' ); ?>
            </button>
            <button type="button" class="button wbim-modal-close">
                <?php esc_html_e( 'დახურვა', 'wbim' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
#wbim_prices_data {
    padding: 15px;
}

.wbim-branch-prices-table {
    margin-top: 15px;
}

.wbim-branch-prices-table th,
.wbim-branch-prices-table td {
    padding: 10px;
}

.wbim-branch-prices-table .wbim-price-input {
    width: 120px;
}

.wbim-notice {
    padding: 12px;
    margin: 10px 0;
    border-left: 4px solid;
}

.wbim-notice-info {
    background: #e7f3ff;
    border-color: #007cba;
}

.wbim-notice-warning {
    background: #fff8e5;
    border-color: #dba617;
}

/* Modal styles */
.wbim-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wbim-modal-content {
    background: #fff;
    border-radius: 4px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: auto;
}

.wbim-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}

.wbim-modal-header h2 {
    margin: 0;
    font-size: 16px;
}

.wbim-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.wbim-modal-body {
    padding: 20px;
}

.wbim-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.wbim-modal-footer .button {
    margin-left: 10px;
}

.wbim-tiers-table input[type="number"] {
    width: 100%;
}

.wbim-tier-delete {
    color: #a00;
    cursor: pointer;
}

.wbim-tier-delete:hover {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentBranchId = 0;

    // Open tiers modal
    $('.wbim-edit-branch-tiers').on('click', function(e) {
        e.preventDefault();
        currentBranchId = $(this).data('branch-id');
        var branchName = $(this).data('branch-name');

        $('#wbim-modal-branch-name').text(branchName);

        // Load existing tiers
        loadTiersFromForm(currentBranchId);

        $('#wbim-product-tiers-modal').show();
    });

    // Close modal
    $(document).on('click', '.wbim-modal-close', function() {
        $('#wbim-product-tiers-modal').hide();
    });

    function loadTiersFromForm(branchId) {
        var $tbody = $('#wbim-modal-tiers-tbody');
        $tbody.empty();

        var $tiersData = $('#wbim-tiers-data-' + branchId);
        var $rows = $tiersData.find('.wbim-tier-row');

        if ($rows.length === 0) {
            addTierRow(10, '', '');
        } else {
            $rows.each(function() {
                var $row = $(this);
                var minQty = $row.find('input').first().attr('name').match(/\[(\d+)\]/g)[1].replace(/[\[\]]/g, '');
                var regularPrice = $row.find('input[name*="regular_price"]').val();
                var salePrice = $row.find('input[name*="sale_price"]').val();
                addTierRow(minQty, regularPrice, salePrice);
            });
        }
    }

    function addTierRow(minQty, regularPrice, salePrice) {
        var $tbody = $('#wbim-modal-tiers-tbody');
        var $row = $('<tr class="wbim-modal-tier-row">');

        $row.append('<td><input type="number" class="tier-min-qty" min="2" value="' + (minQty || '') + '" placeholder="მაგ: 10"></td>');
        $row.append('<td><input type="number" class="tier-regular-price" step="0.01" min="0" value="' + (regularPrice || '') + '"></td>');
        $row.append('<td><input type="number" class="tier-sale-price" step="0.01" min="0" value="' + (salePrice || '') + '"></td>');
        $row.append('<td><span class="wbim-tier-delete dashicons dashicons-trash"></span></td>');

        $tbody.append($row);
    }

    // Add tier
    $('#wbim-modal-add-tier').on('click', function() {
        addTierRow('', '', '');
    });

    // Delete tier
    $(document).on('click', '.wbim-tier-delete', function() {
        $(this).closest('tr').remove();
    });

    // Save tiers
    $('#wbim-modal-save-tiers').on('click', function() {
        var $tiersData = $('#wbim-tiers-data-' + currentBranchId);
        $tiersData.empty();

        var tierCount = 0;

        $('#wbim-modal-tiers-tbody .wbim-modal-tier-row').each(function() {
            var $row = $(this);
            var minQty = parseInt($row.find('.tier-min-qty').val());
            var regularPrice = $row.find('.tier-regular-price').val();
            var salePrice = $row.find('.tier-sale-price').val();

            if (minQty > 1 && (regularPrice !== '' || salePrice !== '')) {
                tierCount++;
                var $tierRow = $('<div class="wbim-tier-row" data-tier-index="' + tierCount + '">');
                $tierRow.append('<input type="hidden" name="wbim_branch_prices[' + currentBranchId + '][' + minQty + '][regular_price]" value="' + regularPrice + '">');
                $tierRow.append('<input type="hidden" name="wbim_branch_prices[' + currentBranchId + '][' + minQty + '][sale_price]" value="' + salePrice + '">');
                $tiersData.append($tierRow);
            }
        });

        // Update button text
        var $btn = $('.wbim-edit-branch-tiers[data-branch-id="' + currentBranchId + '"]');
        if (tierCount > 0) {
            $btn.text(tierCount + ' ფასი');
        } else {
            $btn.text('დამატება');
        }

        $('#wbim-product-tiers-modal').hide();
    });
});
</script>
