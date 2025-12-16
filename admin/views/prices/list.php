<?php
/**
 * Branch Prices List View
 *
 * @package WBIM
 * @since 1.4.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get branches
$branches = WBIM_Branch::get_all( array( 'is_active' => 1 ) );

// Get filter parameters
$selected_branch = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0;
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 20;

// Get products with WooCommerce prices
global $wpdb;

$where = array( "p.post_type = 'product'", "p.post_status = 'publish'" );
$values = array();

if ( ! empty( $search ) ) {
    $like = '%' . $wpdb->esc_like( $search ) . '%';
    $where[] = '(p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)';
    $values[] = $like;
    $values[] = $like;
}

$where_clause = implode( ' AND ', $where );

// Count total
$count_sql = "SELECT COUNT(DISTINCT p.ID)
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
    WHERE {$where_clause}";

if ( ! empty( $values ) ) {
    $total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
} else {
    $total_items = (int) $wpdb->get_var( $count_sql );
}

$total_pages = ceil( $total_items / $per_page );
$offset = ( $paged - 1 ) * $per_page;

// Get products
$values_with_limit = $values;
$values_with_limit[] = $per_page;
$values_with_limit[] = $offset;

$sql = "SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value as sku,
        pm_price.meta_value as regular_price,
        pm_sale.meta_value as sale_price
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
    LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_regular_price'
    LEFT JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
    WHERE {$where_clause}
    ORDER BY p.post_title ASC
    LIMIT %d OFFSET %d";

$products = $wpdb->get_results( $wpdb->prepare( $sql, $values_with_limit ) );
?>

<div class="wrap wbim-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'ფილიალის ფასები', 'wbim' ); ?></h1>

    <hr class="wp-header-end">

    <div class="wbim-prices-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wbim-prices">

            <div class="wbim-filter-row">
                <div class="wbim-filter-item">
                    <label for="branch_id"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></label>
                    <select name="branch_id" id="branch_id">
                        <option value=""><?php esc_html_e( 'ყველა ფილიალი', 'wbim' ); ?></option>
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $selected_branch, $branch->id ); ?>>
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wbim-filter-item">
                    <label for="search"><?php esc_html_e( 'ძიება', 'wbim' ); ?></label>
                    <input type="text" name="s" id="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'პროდუქტის სახელი ან SKU...', 'wbim' ); ?>">
                </div>

                <div class="wbim-filter-item">
                    <button type="submit" class="button"><?php esc_html_e( 'ფილტრი', 'wbim' ); ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="wbim-prices-table-wrapper">
        <table class="wp-list-table widefat fixed striped wbim-prices-table">
            <thead>
                <tr>
                    <th class="column-product"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                    <th class="column-sku"><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                    <th class="column-default-price"><?php esc_html_e( 'ძირითადი ფასი', 'wbim' ); ?></th>
                    <?php foreach ( $branches as $branch ) : ?>
                        <th class="column-branch-price">
                            <?php echo esc_html( $branch->name ); ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="column-actions"><?php esc_html_e( 'მოქმედებები', 'wbim' ); ?></th>
                </tr>
            </thead>
            <tbody id="wbim-prices-tbody">
                <?php if ( empty( $products ) ) : ?>
                    <tr>
                        <td colspan="<?php echo 4 + count( $branches ); ?>">
                            <?php esc_html_e( 'პროდუქტები ვერ მოიძებნა', 'wbim' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $products as $product ) :
                        $branch_prices = WBIM_Branch_Price::get_prices_by_branch( $product->ID, 0 );
                    ?>
                        <tr data-product-id="<?php echo esc_attr( $product->ID ); ?>">
                            <td class="column-product">
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $product->ID ) ); ?>">
                                        <?php echo esc_html( $product->post_title ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="column-sku">
                                <?php echo esc_html( $product->sku ?: '-' ); ?>
                            </td>
                            <td class="column-default-price">
                                <?php
                                if ( $product->sale_price ) {
                                    echo '<del>' . wc_price( $product->regular_price ) . '</del> ';
                                    echo wc_price( $product->sale_price );
                                } elseif ( $product->regular_price ) {
                                    echo wc_price( $product->regular_price );
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <?php foreach ( $branches as $branch ) :
                                $branch_price = isset( $branch_prices[ $branch->id ][1] ) ? $branch_prices[ $branch->id ][1] : null;
                            ?>
                                <td class="column-branch-price" data-branch-id="<?php echo esc_attr( $branch->id ); ?>">
                                    <div class="wbim-price-cell">
                                        <input type="number"
                                            class="wbim-price-input wbim-regular-price"
                                            step="0.01"
                                            min="0"
                                            placeholder="<?php esc_attr_e( 'ფასი', 'wbim' ); ?>"
                                            value="<?php echo esc_attr( $branch_price ? $branch_price['regular_price'] : '' ); ?>"
                                            data-product-id="<?php echo esc_attr( $product->ID ); ?>"
                                            data-branch-id="<?php echo esc_attr( $branch->id ); ?>"
                                            data-field="regular_price">
                                        <input type="number"
                                            class="wbim-price-input wbim-sale-price"
                                            step="0.01"
                                            min="0"
                                            placeholder="<?php esc_attr_e( 'ფასდაკლება', 'wbim' ); ?>"
                                            value="<?php echo esc_attr( $branch_price ? $branch_price['sale_price'] : '' ); ?>"
                                            data-product-id="<?php echo esc_attr( $product->ID ); ?>"
                                            data-branch-id="<?php echo esc_attr( $branch->id ); ?>"
                                            data-field="sale_price">
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td class="column-actions">
                                <button type="button" class="button button-small wbim-save-row-prices" data-product-id="<?php echo esc_attr( $product->ID ); ?>">
                                    <?php esc_html_e( 'შენახვა', 'wbim' ); ?>
                                </button>
                                <button type="button" class="button button-small wbim-edit-tiers" data-product-id="<?php echo esc_attr( $product->ID ); ?>">
                                    <?php esc_html_e( 'საბითუმო', 'wbim' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $paged,
                ) );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Wholesale Tiers Modal -->
<div id="wbim-tiers-modal" class="wbim-modal" style="display: none;">
    <div class="wbim-modal-content">
        <div class="wbim-modal-header">
            <h2><?php esc_html_e( 'საბითუმო ფასები', 'wbim' ); ?></h2>
            <button type="button" class="wbim-modal-close">&times;</button>
        </div>
        <div class="wbim-modal-body">
            <p class="wbim-modal-product-name"></p>

            <div class="wbim-tiers-container">
                <div class="wbim-tiers-branch-select">
                    <label for="wbim-tier-branch"><?php esc_html_e( 'ფილიალი', 'wbim' ); ?></label>
                    <select id="wbim-tier-branch">
                        <?php foreach ( $branches as $branch ) : ?>
                            <option value="<?php echo esc_attr( $branch->id ); ?>">
                                <?php echo esc_html( $branch->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <table class="wbim-tiers-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'მინ. რაოდენობა', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'ფასი', 'wbim' ); ?></th>
                            <th><?php esc_html_e( 'ფასდაკლება', 'wbim' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="wbim-tiers-tbody">
                        <!-- Tiers will be loaded here -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                <button type="button" class="button" id="wbim-add-tier">
                                    <?php esc_html_e( '+ დამატება', 'wbim' ); ?>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="wbim-modal-footer">
            <button type="button" class="button button-primary" id="wbim-save-tiers">
                <?php esc_html_e( 'შენახვა', 'wbim' ); ?>
            </button>
            <button type="button" class="button wbim-modal-close">
                <?php esc_html_e( 'დახურვა', 'wbim' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
.wbim-prices-filters {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.wbim-filter-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.wbim-filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.wbim-filter-item input,
.wbim-filter-item select {
    min-width: 200px;
}

.wbim-prices-table-wrapper {
    overflow-x: auto;
}

.wbim-prices-table .column-product {
    width: 200px;
}

.wbim-prices-table .column-sku {
    width: 100px;
}

.wbim-prices-table .column-default-price {
    width: 120px;
}

.wbim-prices-table .column-branch-price {
    min-width: 140px;
}

.wbim-prices-table .column-actions {
    width: 150px;
}

.wbim-price-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.wbim-price-input {
    width: 100%;
    padding: 4px 8px;
    font-size: 13px;
}

.wbim-price-input.wbim-sale-price {
    background-color: #fff8e5;
}

.wbim-price-input:focus {
    border-color: #007cba;
    box-shadow: 0 0 0 1px #007cba;
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
    max-width: 600px;
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

.wbim-tiers-branch-select {
    margin-bottom: 15px;
}

.wbim-tiers-table {
    width: 100%;
    border-collapse: collapse;
}

.wbim-tiers-table th,
.wbim-tiers-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}

.wbim-tiers-table input {
    width: 100%;
}

.wbim-tier-delete {
    color: #a00;
    cursor: pointer;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentProductId = 0;
    var currentProductName = '';

    // Save row prices
    $('.wbim-save-row-prices').on('click', function() {
        var $btn = $(this);
        var productId = $btn.data('product-id');
        var $row = $btn.closest('tr');
        var items = [];

        $row.find('.wbim-price-cell').each(function() {
            var $cell = $(this);
            var branchId = $cell.closest('td').data('branch-id');
            var regularPrice = $cell.find('.wbim-regular-price').val();
            var salePrice = $cell.find('.wbim-sale-price').val();

            if (regularPrice !== '' || salePrice !== '') {
                items.push({
                    product_id: productId,
                    variation_id: 0,
                    branch_id: branchId,
                    regular_price: regularPrice,
                    sale_price: salePrice,
                    min_quantity: 1
                });
            }
        });

        if (items.length === 0) {
            return;
        }

        $btn.prop('disabled', true).text('<?php esc_html_e( 'ინახება...', 'wbim' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_bulk_update_prices',
                nonce: wbimAdmin.nonce,
                items: items
            },
            success: function(response) {
                if (response.success) {
                    $btn.text('<?php esc_html_e( 'შენახულია!', 'wbim' ); ?>');
                    setTimeout(function() {
                        $btn.text('<?php esc_html_e( 'შენახვა', 'wbim' ); ?>');
                    }, 2000);
                } else {
                    alert(response.data.message);
                    $btn.text('<?php esc_html_e( 'შენახვა', 'wbim' ); ?>');
                }
                $btn.prop('disabled', false);
            },
            error: function() {
                alert('<?php esc_html_e( 'შეცდომა!', 'wbim' ); ?>');
                $btn.prop('disabled', false).text('<?php esc_html_e( 'შენახვა', 'wbim' ); ?>');
            }
        });
    });

    // Edit tiers modal
    $('.wbim-edit-tiers').on('click', function() {
        currentProductId = $(this).data('product-id');
        currentProductName = $(this).closest('tr').find('.column-product strong a').text();

        $('.wbim-modal-product-name').text(currentProductName);
        $('#wbim-tiers-modal').show();
        loadTiers();
    });

    // Close modal
    $('.wbim-modal-close').on('click', function() {
        $('#wbim-tiers-modal').hide();
    });

    // Load tiers for selected branch
    $('#wbim-tier-branch').on('change', function() {
        loadTiers();
    });

    function loadTiers() {
        var branchId = $('#wbim-tier-branch').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_get_product_prices',
                nonce: wbimAdmin.nonce,
                product_id: currentProductId,
                variation_id: 0
            },
            success: function(response) {
                if (response.success) {
                    renderTiers(response.data.prices, branchId);
                }
            }
        });
    }

    function renderTiers(prices, branchId) {
        var $tbody = $('#wbim-tiers-tbody');
        $tbody.empty();

        var branchPrices = prices[branchId] || {};
        var tierKeys = Object.keys(branchPrices).map(Number).sort(function(a, b) { return a - b; });

        if (tierKeys.length === 0) {
            addTierRow(1, '', '');
        } else {
            tierKeys.forEach(function(minQty) {
                var tier = branchPrices[minQty];
                addTierRow(minQty, tier.regular_price || '', tier.sale_price || '');
            });
        }
    }

    function addTierRow(minQty, regularPrice, salePrice) {
        var $tbody = $('#wbim-tiers-tbody');
        var $row = $('<tr>');

        $row.append('<td><input type="number" class="tier-min-qty" min="1" value="' + minQty + '"></td>');
        $row.append('<td><input type="number" class="tier-regular-price" step="0.01" min="0" value="' + regularPrice + '"></td>');
        $row.append('<td><input type="number" class="tier-sale-price" step="0.01" min="0" value="' + salePrice + '"></td>');
        $row.append('<td><span class="wbim-tier-delete dashicons dashicons-trash"></span></td>');

        $tbody.append($row);
    }

    // Add tier
    $('#wbim-add-tier').on('click', function() {
        addTierRow('', '', '');
    });

    // Delete tier
    $(document).on('click', '.wbim-tier-delete', function() {
        var $tbody = $('#wbim-tiers-tbody');
        if ($tbody.find('tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });

    // Save tiers
    $('#wbim-save-tiers').on('click', function() {
        var branchId = $('#wbim-tier-branch').val();
        var items = [];

        $('#wbim-tiers-tbody tr').each(function() {
            var $row = $(this);
            var minQty = parseInt($row.find('.tier-min-qty').val()) || 1;
            var regularPrice = $row.find('.tier-regular-price').val();
            var salePrice = $row.find('.tier-sale-price').val();

            if (regularPrice !== '' || salePrice !== '') {
                items.push({
                    product_id: currentProductId,
                    variation_id: 0,
                    branch_id: branchId,
                    regular_price: regularPrice,
                    sale_price: salePrice,
                    min_quantity: minQty
                });
            }
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_bulk_update_prices',
                nonce: wbimAdmin.nonce,
                items: items
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#wbim-tiers-modal').hide();
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});
</script>
