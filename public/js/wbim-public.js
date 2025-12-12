/**
 * WBIM Public JavaScript
 *
 * @package WBIM
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WBIM Public Handler
     */
    var WBIMPublic = {

        /**
         * Initialize
         */
        init: function() {
            console.log('WBIM: Initializing...', {
                ajax_url: typeof wbim_public !== 'undefined' ? wbim_public.ajax_url : 'undefined',
                nonce: typeof wbim_public !== 'undefined' ? wbim_public.nonce : 'undefined',
                i18n: typeof wbim_public !== 'undefined' ? wbim_public.i18n : 'undefined'
            });

            this.bindEvents();
            this.initVariationHandler();
            this.initProductBranchSelector();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Geolocation for checkout
            if ($('.wbim-branch-selector').length && navigator.geolocation) {
                this.requestGeolocation();
            }

            // Branch button click on product page
            $(document).on('click', '.wbim-branch-button', function(e) {
                var $button = $(this);

                // Skip if disabled (out of stock)
                if ($button.hasClass('wbim-branch-disabled')) {
                    return;
                }

                // Remove selected class from all buttons
                $button.siblings('.wbim-branch-button').removeClass('wbim-branch-selected');

                // Add selected class to clicked button
                $button.addClass('wbim-branch-selected');

                // Update hidden inputs
                var branchId = $button.data('branch-id');
                var branchName = $button.data('branch-name');

                $('#wbim_branch_id').val(branchId);
                $('#wbim_branch_name').val(branchName);
            });
        },

        /**
         * Initialize product page branch selector for variable products
         */
        initProductBranchSelector: function() {
            var $wrapper = $('.wbim-branch-selector-wrapper[data-is-variable="1"]');

            if (!$wrapper.length) {
                return;
            }

            var productId = $wrapper.data('product-id');
            var $buttons = $wrapper.find('.wbim-branch-buttons');
            var $notice = $wrapper.find('.wbim-variable-notice');

            // Listen for variation change
            $('form.variations_form').on('found_variation', function(event, variation) {
                WBIMPublic.updateBranchButtonsStock(productId, variation.variation_id, $buttons, $notice);
            });

            // Listen for variation reset
            $('form.variations_form').on('reset_data', function() {
                WBIMPublic.resetBranchButtons($buttons, $notice);
            });
        },

        /**
         * Update branch buttons with variation stock
         */
        updateBranchButtonsStock: function(productId, variationId, $buttons, $notice) {
            $notice.text(wbim_public.i18n.loading).show();

            console.log('WBIM: Fetching variation stock', {
                productId: productId,
                variationId: variationId,
                ajaxUrl: wbim_public.ajax_url,
                nonce: wbim_public.nonce
            });

            $.ajax({
                url: wbim_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_get_variation_stock',
                    nonce: wbim_public.nonce,
                    product_id: productId,
                    variation_id: variationId
                },
                success: function(response) {
                    console.log('WBIM: AJAX Response', response);

                    if (response.success && response.data.stock) {
                        console.log('WBIM: Stock data received', response.data.stock);
                        WBIMPublic.updateButtonsWithStock($buttons, response.data.stock);
                        $notice.hide();
                    } else {
                        console.error('WBIM: Error in response', response);
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        var debugInfo = response.data && response.data.debug ? ' (' + response.data.debug + ')' : '';
                        $notice.text(errorMsg + debugInfo).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WBIM: AJAX Error', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $notice.text(wbim_public.i18n.error || 'Error: ' + error).show();
                }
            });
        },

        /**
         * Update branch buttons with stock data
         */
        updateButtonsWithStock: function($buttons, stockData) {
            $buttons.find('.wbim-branch-button').each(function() {
                var $button = $(this);
                var branchId = $button.data('branch-id');

                var branchData = stockData.find(function(item) {
                    return item.branch_id == branchId;
                });

                if (branchData) {
                    var qty = branchData.quantity;
                    var $stockContainer = $button.find('.wbim-branch-button-stock');

                    // Remove existing stock classes
                    $button.removeClass('wbim-branch-in-stock wbim-branch-low-stock wbim-branch-out-of-stock wbim-branch-disabled');

                    // Update stock display and classes
                    if (qty <= 0) {
                        $button.addClass('wbim-branch-out-of-stock wbim-branch-disabled');
                        $stockContainer.html('<span class="wbim-stock-out">' + wbim_public.i18n.out_of_stock + '</span>');

                        // Deselect if was selected
                        if ($button.hasClass('wbim-branch-selected')) {
                            $button.removeClass('wbim-branch-selected');
                            $('#wbim_branch_id').val('');
                            $('#wbim_branch_name').val('');
                        }
                    } else if (qty !== null) {
                        if (qty <= 5) {
                            $button.addClass('wbim-branch-low-stock');
                        } else {
                            $button.addClass('wbim-branch-in-stock');
                        }
                        $stockContainer.html('<span class="wbim-stock-qty"><strong>' + qty + '</strong> ' + wbim_public.i18n.available + '</span>');
                    } else {
                        $button.addClass('wbim-branch-in-stock');
                        $stockContainer.html('<span class="wbim-stock-in">' + branchData.status_text + '</span>');
                    }

                    // Update data attribute
                    $button.data('stock', qty);
                }
            });
        },

        /**
         * Reset branch buttons to initial state
         */
        resetBranchButtons: function($buttons, $notice) {
            $buttons.find('.wbim-branch-button').each(function() {
                var $button = $(this);
                var $stockContainer = $button.find('.wbim-branch-button-stock');

                // Remove all stock classes and disabled state
                $button.removeClass('wbim-branch-in-stock wbim-branch-low-stock wbim-branch-out-of-stock wbim-branch-disabled wbim-branch-selected');

                // Reset stock display to "select variation" message
                $stockContainer.html('<span class="wbim-stock-variable">' + (wbim_public.i18n.select_variation || 'აირჩიეთ ვარიაცია') + '</span>');
            });

            // Clear hidden inputs
            $('#wbim_branch_id').val('');
            $('#wbim_branch_name').val('');

            // Show notice
            $notice.text(wbim_public.i18n.select_branch || 'აირჩიეთ ვარიაცია მარაგის სანახავად').show();
        },

        /**
         * Initialize variation stock handler
         */
        initVariationHandler: function() {
            var $container = $('.wbim-branch-stock-container.wbim-variable-product');

            if (!$container.length) {
                return;
            }

            var productId = $container.data('product-id');
            var $stockList = $container.find('.wbim-branch-stock-list');
            var $selectMessage = $container.find('.wbim-select-variation');

            // Listen for variation change
            $('form.variations_form').on('found_variation', function(event, variation) {
                WBIMPublic.loadVariationStock(productId, variation.variation_id, $stockList, $selectMessage);
            });

            // Listen for variation reset
            $('form.variations_form').on('reset_data', function() {
                $stockList.hide().empty();
                $selectMessage.show();
            });
        },

        /**
         * Load variation stock via AJAX
         */
        loadVariationStock: function(productId, variationId, $stockList, $selectMessage) {
            $selectMessage.text(wbim_public.i18n.loading);

            $.ajax({
                url: wbim_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_get_variation_stock',
                    nonce: wbim_public.nonce,
                    product_id: productId,
                    variation_id: variationId
                },
                success: function(response) {
                    if (response.success && response.data.stock) {
                        WBIMPublic.renderStockList(response.data.stock, $stockList);
                        $selectMessage.hide();
                        $stockList.show();
                    } else {
                        $selectMessage.text(wbim_public.i18n.error || 'Error loading stock');
                    }
                },
                error: function() {
                    $selectMessage.text(wbim_public.i18n.error || 'Error loading stock');
                }
            });
        },

        /**
         * Render stock list HTML
         */
        renderStockList: function(stockData, $container) {
            var html = '';

            $.each(stockData, function(index, item) {
                var statusClass = 'wbim-stock-out';
                if (item.status === 'in_stock') {
                    statusClass = 'wbim-stock-in';
                } else if (item.status === 'low_stock') {
                    statusClass = 'wbim-stock-low';
                }

                html += '<div class="wbim-branch-stock-item ' + statusClass + '">';
                html += '<span class="wbim-branch-name">' + item.branch_name + '</span>';
                html += '<span class="wbim-stock-status">';

                if (item.quantity !== null && item.quantity > 0) {
                    html += '<span class="wbim-stock-quantity">' + item.quantity + '</span>';
                    html += '<span class="wbim-stock-unit">' + wbim_public.i18n.available + '</span>';
                } else {
                    html += item.status_text;
                }

                html += '</span>';
                html += '<span class="wbim-stock-indicator"></span>';
                html += '</div>';
            });

            $container.html(html);
        },

        /**
         * Request geolocation for checkout
         */
        requestGeolocation: function() {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    WBIMPublic.setCustomerLocation(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    console.log('WBIM: Geolocation not available or denied');
                },
                {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000 // Cache for 5 minutes
                }
            );
        },

        /**
         * Set customer location via AJAX
         */
        setCustomerLocation: function(lat, lng) {
            if (typeof wbim_checkout === 'undefined') {
                return;
            }

            $.ajax({
                url: wbim_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbim_set_customer_location',
                    nonce: wbim_checkout.nonce,
                    lat: lat,
                    lng: lng
                },
                success: function(response) {
                    if (response.success) {
                        // Optionally refresh branch distances
                        WBIMPublic.updateBranchDistances(lat, lng);
                    }
                }
            });
        },

        /**
         * Update branch distances (client-side calculation)
         */
        updateBranchDistances: function(customerLat, customerLng) {
            // This is handled by the map selector if Google Maps is available
            // For dropdown/radio, distances come from server
        },

        /**
         * Calculate distance using Haversine formula (client-side)
         */
        calculateDistance: function(lat1, lng1, lat2, lng2) {
            var R = 6371; // Earth's radius in km
            var dLat = this.toRad(lat2 - lat1);
            var dLng = this.toRad(lng2 - lng1);

            var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                    Math.sin(dLng / 2) * Math.sin(dLng / 2);

            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            return R * c;
        },

        /**
         * Convert degrees to radians
         */
        toRad: function(deg) {
            return deg * (Math.PI / 180);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WBIMPublic.init();
    });

})(jQuery);
