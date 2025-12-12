<?php
/**
 * Edit/View Transfer View
 *
 * @package WBIM
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status_class = 'wbim-status-' . $transfer->status;
?>
<div class="wrap wbim-edit-transfer-page">
    <h1>
        <?php
        printf(
            /* translators: %s: Transfer number */
            esc_html__( 'გადატანა #%s', 'wbim' ),
            esc_html( $transfer->transfer_number )
        );
        ?>
        <span class="wbim-status <?php echo esc_attr( $status_class ); ?>">
            <?php echo esc_html( WBIM_Transfer::get_status_label( $transfer->status ) ); ?>
        </span>
    </h1>

    <div id="wbim-transfer-notices"></div>

    <div class="wbim-transfer-layout">
        <!-- Main content -->
        <div class="wbim-transfer-main">
            <!-- Transfer info -->
            <div class="wbim-transfer-info postbox">
                <h2 class="hndle"><?php esc_html_e( 'გადატანის ინფორმაცია', 'wbim' ); ?></h2>
                <div class="inside">
                    <table class="wbim-info-table">
                        <tr>
                            <th><?php esc_html_e( 'წყარო ფილიალი:', 'wbim' ); ?></th>
                            <td><strong><?php echo esc_html( $transfer->source_branch_name ); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'დანიშნულება:', 'wbim' ); ?></th>
                            <td><strong><?php echo esc_html( $transfer->destination_branch_name ); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'შექმნილია:', 'wbim' ); ?></th>
                            <td>
                                <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->created_at ) ) ); ?>
                                <?php if ( $transfer->created_by_name ) : ?>
                                    (<?php echo esc_html( $transfer->created_by_name ); ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ( $transfer->sent_at ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'გაგზავნილია:', 'wbim' ); ?></th>
                            <td>
                                <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->sent_at ) ) ); ?>
                                <?php if ( $transfer->sent_by_name ) : ?>
                                    (<?php echo esc_html( $transfer->sent_by_name ); ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $transfer->received_at ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'მიღებულია:', 'wbim' ); ?></th>
                            <td>
                                <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $transfer->received_at ) ) ); ?>
                                <?php if ( $transfer->received_by_name ) : ?>
                                    (<?php echo esc_html( $transfer->received_by_name ); ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $transfer->notes ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'შენიშვნები:', 'wbim' ); ?></th>
                            <td><?php echo esc_html( $transfer->notes ); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Product search (only for draft) -->
            <?php if ( $is_editable ) : ?>
            <div class="wbim-product-search postbox">
                <h2 class="hndle"><?php esc_html_e( 'პროდუქტის დამატება', 'wbim' ); ?></h2>
                <div class="inside">
                    <div class="wbim-search-container">
                        <input type="text" id="wbim-product-search" class="regular-text"
                               placeholder="<?php esc_attr_e( 'მოძებნეთ პროდუქტი სახელით ან SKU-ით...', 'wbim' ); ?>"
                               autocomplete="off">
                        <span class="spinner"></span>
                        <div id="wbim-search-results" class="wbim-search-results"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transfer items -->
            <div class="wbim-transfer-items postbox">
                <h2 class="hndle">
                    <?php esc_html_e( 'პროდუქტები', 'wbim' ); ?>
                    <span class="wbim-item-count">(<?php echo count( $items ); ?>)</span>
                </h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped" id="wbim-items-table">
                        <thead>
                            <tr>
                                <th class="column-image"><?php esc_html_e( 'სურათი', 'wbim' ); ?></th>
                                <th class="column-product"><?php esc_html_e( 'პროდუქტი', 'wbim' ); ?></th>
                                <th class="column-sku"><?php esc_html_e( 'SKU', 'wbim' ); ?></th>
                                <th class="column-stock"><?php esc_html_e( 'მარაგი', 'wbim' ); ?></th>
                                <th class="column-quantity"><?php esc_html_e( 'რაოდენობა', 'wbim' ); ?></th>
                                <?php if ( $is_editable ) : ?>
                                <th class="column-actions"><?php esc_html_e( 'მოქმედება', 'wbim' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="wbim-items-body">
                            <?php if ( empty( $items ) ) : ?>
                                <tr class="wbim-no-items">
                                    <td colspan="<?php echo $is_editable ? '6' : '5'; ?>">
                                        <?php esc_html_e( 'პროდუქტები არ არის დამატებული.', 'wbim' ); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $items as $item ) :
                                    $stock = WBIM_Stock::get( $item->product_id, $transfer->source_branch_id, $item->variation_id );
                                    $stock_qty = $stock ? $stock->quantity : 0;
                                ?>
                                <tr data-item-id="<?php echo esc_attr( $item->id ); ?>">
                                    <td class="column-image">
                                        <?php echo isset( $item->product_image ) ? $item->product_image : ''; ?>
                                    </td>
                                    <td class="column-product">
                                        <?php if ( isset( $item->product_url ) && $item->product_url ) : ?>
                                            <a href="<?php echo esc_url( $item->product_url ); ?>" target="_blank">
                                                <?php echo esc_html( $item->product_name ); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo esc_html( $item->product_name ); ?>
                                        <?php endif; ?>
                                        <?php if ( isset( $item->variation_attributes ) && ! empty( $item->variation_attributes ) ) : ?>
                                            <br><small><?php echo esc_html( implode( ', ', $item->variation_attributes ) ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-sku"><?php echo esc_html( $item->sku ?: '-' ); ?></td>
                                    <td class="column-stock">
                                        <span class="wbim-source-stock <?php echo $stock_qty < $item->quantity ? 'wbim-stock-warning' : ''; ?>">
                                            <?php echo esc_html( $stock_qty ); ?>
                                        </span>
                                    </td>
                                    <td class="column-quantity">
                                        <?php if ( $is_editable ) : ?>
                                            <input type="number" class="wbim-item-qty small-text"
                                                   value="<?php echo esc_attr( $item->quantity ); ?>"
                                                   min="1" max="<?php echo esc_attr( $stock_qty ); ?>"
                                                   data-item-id="<?php echo esc_attr( $item->id ); ?>">
                                        <?php else : ?>
                                            <?php echo esc_html( $item->quantity ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ( $is_editable ) : ?>
                                    <td class="column-actions">
                                        <button type="button" class="button button-small wbim-remove-item"
                                                data-item-id="<?php echo esc_attr( $item->id ); ?>">
                                            <?php esc_html_e( 'წაშლა', 'wbim' ); ?>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="wbim-total-label"><?php esc_html_e( 'ჯამი:', 'wbim' ); ?></td>
                                <td class="wbim-total-qty" colspan="<?php echo $is_editable ? '2' : '1'; ?>">
                                    <strong id="wbim-total-quantity">
                                        <?php
                                        $total = 0;
                                        foreach ( $items as $item ) {
                                            $total += $item->quantity;
                                        }
                                        echo esc_html( $total );
                                        ?>
                                    </strong>
                                    <?php esc_html_e( 'ერთეული', 'wbim' ); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="wbim-transfer-sidebar">
            <!-- Status actions -->
            <div class="wbim-status-actions postbox">
                <h2 class="hndle"><?php esc_html_e( 'სტატუსის ცვლილება', 'wbim' ); ?></h2>
                <div class="inside">
                    <?php if ( ! empty( $valid_transitions ) ) : ?>
                        <div class="wbim-status-buttons">
                            <?php foreach ( $valid_transitions as $new_status ) :
                                $button_class = 'button';
                                $button_label = WBIM_Transfer::get_status_label( $new_status );

                                switch ( $new_status ) {
                                    case WBIM_Transfer::STATUS_PENDING:
                                        $button_class .= ' button-primary';
                                        $button_label = __( 'გაგზავნა', 'wbim' );
                                        break;
                                    case WBIM_Transfer::STATUS_IN_TRANSIT:
                                        $button_class .= ' button-primary';
                                        $button_label = __( 'გზაშია', 'wbim' );
                                        break;
                                    case WBIM_Transfer::STATUS_COMPLETED:
                                        $button_class .= ' button-primary wbim-complete-btn';
                                        $button_label = __( 'დასრულება', 'wbim' );
                                        break;
                                    case WBIM_Transfer::STATUS_CANCELLED:
                                        $button_class .= ' wbim-cancel-btn';
                                        $button_label = __( 'გაუქმება', 'wbim' );
                                        break;
                                }
                            ?>
                                <button type="button" class="<?php echo esc_attr( $button_class ); ?> wbim-change-status"
                                        data-status="<?php echo esc_attr( $new_status ); ?>">
                                    <?php echo esc_html( $button_label ); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php if ( $transfer->status === WBIM_Transfer::STATUS_DRAFT ) : ?>
                            <p class="description">
                                <?php esc_html_e( 'გაგზავნის შემდეგ მარაგი გამოიკლება წყარო ფილიალიდან.', 'wbim' ); ?>
                            </p>
                        <?php elseif ( $transfer->status === WBIM_Transfer::STATUS_PENDING ) : ?>
                            <p class="description">
                                <?php esc_html_e( 'მიღების შემდეგ მარაგი დაემატება დანიშნულების ფილიალს.', 'wbim' ); ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="description">
                            <?php
                            if ( $transfer->status === WBIM_Transfer::STATUS_COMPLETED ) {
                                esc_html_e( 'გადატანა დასრულებულია.', 'wbim' );
                            } elseif ( $transfer->status === WBIM_Transfer::STATUS_CANCELLED ) {
                                esc_html_e( 'გადატანა გაუქმებულია.', 'wbim' );
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="wbim-quick-actions postbox">
                <h2 class="hndle"><?php esc_html_e( 'მოქმედებები', 'wbim' ); ?></h2>
                <div class="inside">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers' ) ); ?>" class="button button-block">
                        <?php esc_html_e( 'უკან გადატანებზე', 'wbim' ); ?>
                    </a>

                    <?php if ( $transfer->status !== WBIM_Transfer::STATUS_DRAFT ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbim-transfers&action=pdf&id=' . $transfer->id ) ); ?>"
                           class="button button-block button-primary">
                            <span class="dashicons dashicons-pdf" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php esc_html_e( 'PDF გადმოწერა', 'wbim' ); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ( $is_editable ) : ?>
                        <button type="button" class="button button-block wbim-delete-transfer" id="wbim-delete-transfer">
                            <?php esc_html_e( 'წაშლა', 'wbim' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {
    var transferId = <?php echo (int) $transfer->id; ?>;
    var sourceBranchId = <?php echo (int) $transfer->source_branch_id; ?>;
    var isEditable = <?php echo $is_editable ? 'true' : 'false'; ?>;
    var searchTimeout;

    // Show notice
    function showNotice(message, type) {
        var $notice = $('<div class="wbim-notice wbim-notice-' + type + '">' + message + '</div>');
        $('#wbim-transfer-notices').html($notice);
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }

    // Update total quantity
    function updateTotalQuantity() {
        var total = 0;
        $('.wbim-item-qty').each(function() {
            total += parseInt($(this).val()) || 0;
        });
        $('#wbim-total-quantity').text(total);
        $('.wbim-item-count').text('(' + $('#wbim-items-body tr:not(.wbim-no-items)').length + ')');
    }

    <?php if ( $is_editable ) : ?>
    // Product search
    $('#wbim-product-search').on('keyup', function() {
        var search = $(this).val();
        var $results = $('#wbim-search-results');
        var $spinner = $(this).siblings('.spinner');

        clearTimeout(searchTimeout);

        if (search.length < 2) {
            $results.removeClass('has-results').empty();
            return;
        }

        $spinner.addClass('is-active');

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wbim_search_products',
                    nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                    search: search,
                    branch_id: sourceBranchId
                },
                success: function(response) {
                    $spinner.removeClass('is-active');

                    if (response.success && response.data.products.length > 0) {
                        var html = '';
                        $.each(response.data.products, function(i, product) {
                            html += '<div class="wbim-search-result-item" data-product=\'' + JSON.stringify(product) + '\'>';
                            html += '<img src="' + product.image + '" alt="">';
                            html += '<div class="product-info">';
                            html += '<div class="product-name">' + product.name + '</div>';
                            html += '<div class="product-sku">SKU: ' + (product.sku || '-') + '</div>';
                            html += '<div class="product-stock"><?php esc_html_e( 'მარაგი:', 'wbim' ); ?> ' + product.stock + '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                        $results.html(html).addClass('has-results');
                    } else {
                        $results.html('<div class="wbim-search-result-item"><?php esc_html_e( 'პროდუქტი ვერ მოიძებნა.', 'wbim' ); ?></div>').addClass('has-results');
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                }
            });
        }, 300);
    });

    // Click on search result
    $(document).on('click', '.wbim-search-result-item[data-product]', function() {
        var product = $(this).data('product');
        var $results = $('#wbim-search-results');
        var $input = $('#wbim-product-search');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_add_transfer_item',
                nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                transfer_id: transferId,
                product_id: product.product_id,
                variation_id: product.variation_id,
                quantity: 1
            },
            success: function(response) {
                if (response.success) {
                    // Remove no-items row if exists
                    $('#wbim-items-body .wbim-no-items').remove();

                    // Check if item already exists
                    var $existingRow = $('#wbim-items-body tr[data-item-id="' + response.data.item.id + '"]');
                    if ($existingRow.length) {
                        // Update quantity
                        var $qtyInput = $existingRow.find('.wbim-item-qty');
                        $qtyInput.val(response.data.item.quantity);
                    } else {
                        // Add new row
                        var html = '<tr data-item-id="' + response.data.item.id + '">';
                        html += '<td class="column-image">' + response.data.item.image + '</td>';
                        html += '<td class="column-product">' + response.data.item.product_name + '</td>';
                        html += '<td class="column-sku">' + (response.data.item.sku || '-') + '</td>';
                        html += '<td class="column-stock"><span class="wbim-source-stock">' + product.stock + '</span></td>';
                        html += '<td class="column-quantity"><input type="number" class="wbim-item-qty small-text" value="' + response.data.item.quantity + '" min="1" max="' + product.stock + '" data-item-id="' + response.data.item.id + '"></td>';
                        html += '<td class="column-actions"><button type="button" class="button button-small wbim-remove-item" data-item-id="' + response.data.item.id + '"><?php esc_html_e( 'წაშლა', 'wbim' ); ?></button></td>';
                        html += '</tr>';
                        $('#wbim-items-body').append(html);
                    }

                    showNotice(response.data.message, 'success');
                    updateTotalQuantity();
                } else {
                    showNotice(response.data.message, 'error');
                }

                $results.removeClass('has-results').empty();
                $input.val('');
            },
            error: function() {
                showNotice('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>', 'error');
            }
        });
    });

    // Update quantity
    $(document).on('change', '.wbim-item-qty', function() {
        var $input = $(this);
        var itemId = $input.data('item-id');
        var quantity = parseInt($input.val()) || 0;

        if (quantity < 1) {
            quantity = 1;
            $input.val(1);
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_update_transfer_item',
                nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                item_id: itemId,
                quantity: quantity
            },
            success: function(response) {
                if (response.success) {
                    updateTotalQuantity();
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>', 'error');
            }
        });
    });

    // Remove item
    $(document).on('click', '.wbim-remove-item', function() {
        if (!confirm('<?php echo esc_js( __( 'დარწმუნებული ხართ?', 'wbim' ) ); ?>')) {
            return;
        }

        var $button = $(this);
        var itemId = $button.data('item-id');
        var $row = $button.closest('tr');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_remove_transfer_item',
                nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        updateTotalQuantity();

                        // Show no-items row if empty
                        if ($('#wbim-items-body tr').length === 0) {
                            $('#wbim-items-body').html('<tr class="wbim-no-items"><td colspan="6"><?php esc_html_e( 'პროდუქტები არ არის დამატებული.', 'wbim' ); ?></td></tr>');
                        }
                    });
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>', 'error');
            }
        });
    });

    // Delete transfer
    $('#wbim-delete-transfer').on('click', function() {
        if (!confirm('<?php echo esc_js( __( 'დარწმუნებული ხართ რომ გსურთ გადატანის წაშლა?', 'wbim' ) ); ?>')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_delete_transfer',
                nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                transfer_id: transferId
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>', 'error');
            }
        });
    });
    <?php endif; ?>

    // Change status
    $('.wbim-change-status').on('click', function() {
        var $button = $(this);
        var newStatus = $button.data('status');
        var confirmMsg = '';

        switch (newStatus) {
            case 'pending':
                confirmMsg = '<?php echo esc_js( __( 'გადატანის გაგზავნის შემდეგ მარაგი გამოიკლება წყარო ფილიალიდან. გსურთ გაგრძელება?', 'wbim' ) ); ?>';
                break;
            case 'completed':
                confirmMsg = '<?php echo esc_js( __( 'გადატანის დასრულების შემდეგ მარაგი დაემატება დანიშნულების ფილიალს. გსურთ გაგრძელება?', 'wbim' ) ); ?>';
                break;
            case 'cancelled':
                confirmMsg = '<?php echo esc_js( __( 'გადატანის გაუქმების შემდეგ მარაგი დაბრუნდება წყარო ფილიალში (თუ უკვე გამოკლებულია). გსურთ გაგრძელება?', 'wbim' ) ); ?>';
                break;
            default:
                confirmMsg = '<?php echo esc_js( __( 'დარწმუნებული ხართ?', 'wbim' ) ); ?>';
        }

        if (!confirm(confirmMsg)) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wbim_update_transfer_status',
                nonce: '<?php echo wp_create_nonce( 'wbim_admin_nonce' ); ?>',
                transfer_id: transferId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to reflect changes
                    location.reload();
                } else {
                    var errorMsg = response.data.message;
                    if (response.data.errors) {
                        errorMsg += '\n' + response.data.errors.join('\n');
                    }
                    showNotice(errorMsg, 'error');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showNotice('<?php echo esc_js( __( 'სერვერის შეცდომა.', 'wbim' ) ); ?>', 'error');
                $button.prop('disabled', false);
            }
        });
    });

    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wbim-product-search').length) {
            $('#wbim-search-results').removeClass('has-results').empty();
        }
    });
});
</script>
