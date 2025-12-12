/**
 * WBIM Admin Orders JavaScript
 *
 * @package WBIM
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WBIM Admin Orders Handler
     */
    var WBIMAdminOrders = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Deduct stock button
            $(document).on('click', '.wbim-deduct-stock', this.handleDeductStock);

            // Return stock button
            $(document).on('click', '.wbim-return-stock', this.handleReturnStock);

            // Item branch change
            $(document).on('change', '.wbim-item-branch-select', this.handleItemBranchChange);
        },

        /**
         * Handle deduct stock
         */
        handleDeductStock: function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!confirm(wbim_admin_orders.i18n.confirm_deduct)) {
                return;
            }

            $button.prop('disabled', true).text(wbim_admin_orders.i18n.processing);

            $.ajax({
                url: wbim_admin_orders.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_manually_deduct_stock',
                    nonce: wbim_admin_orders.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        alert(wbim_admin_orders.i18n.success);
                        location.reload();
                    } else {
                        alert(wbim_admin_orders.i18n.error + ' ' + response.data.message);
                        $button.prop('disabled', false).text($button.data('original-text') || 'Deduct Stock');
                    }
                },
                error: function() {
                    alert(wbim_admin_orders.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle return stock
         */
        handleReturnStock: function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!confirm(wbim_admin_orders.i18n.confirm_return)) {
                return;
            }

            $button.prop('disabled', true).text(wbim_admin_orders.i18n.processing);

            $.ajax({
                url: wbim_admin_orders.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_manually_return_stock',
                    nonce: wbim_admin_orders.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        alert(wbim_admin_orders.i18n.success);
                        location.reload();
                    } else {
                        alert(wbim_admin_orders.i18n.error + ' ' + response.data.message);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(wbim_admin_orders.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle item branch change
         */
        handleItemBranchChange: function() {
            var $select = $(this);
            var allocationId = $select.data('allocation-id');
            var orderId = $select.data('order-id');
            var branchId = $select.val();

            $select.prop('disabled', true);

            $.ajax({
                url: wbim_admin_orders.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_change_item_branch',
                    nonce: wbim_admin_orders.nonce,
                    allocation_id: allocationId,
                    order_id: orderId,
                    branch_id: branchId
                },
                success: function(response) {
                    $select.prop('disabled', false);

                    if (response.success) {
                        $select.css('border-color', '#46b450');
                        setTimeout(function() {
                            $select.css('border-color', '');
                        }, 2000);
                    } else {
                        alert(wbim_admin_orders.i18n.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $select.prop('disabled', false);
                    alert(wbim_admin_orders.i18n.error);
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WBIMAdminOrders.init();
    });

})(jQuery);
