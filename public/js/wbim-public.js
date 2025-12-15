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
            this.autoSelectDefaultBranch();
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

                // If clicking on contact toggle, don't select the branch
                if ($(e.target).closest('.wbim-branch-contact-toggle').length) {
                    return;
                }

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

            // Contact toggle in branch cards
            $(document).on('click', '.wbim-contact-trigger', function(e) {
                e.stopPropagation();
                var $toggle = $(this).closest('.wbim-branch-contact-toggle');
                $toggle.toggleClass('wbim-contact-open');
            });

            // Validate branch selection before add to cart
            $(document).on('submit', 'form.cart', function(e) {
                var $wrapper = $('.wbim-branch-selector-wrapper');

                // If branch selector exists on page
                if ($wrapper.length) {
                    var $branchInput = $('#wbim_branch_id');
                    var branchId = $branchInput.val();
                    var isRequired = $branchInput.attr('required') !== undefined;
                    var hasButtons = $wrapper.find('.wbim-branch-button:not(.wbim-branch-disabled)').length > 0;

                    // If required or has available branches, validate selection
                    if ((isRequired || hasButtons) && !branchId) {
                        e.preventDefault();

                        // Show error message
                        if ($('.wbim-branch-error').length === 0) {
                            $wrapper.prepend('<div class="wbim-branch-error woocommerce-error" style="color: #e53935; margin-bottom: 10px; padding: 10px; background: #ffebee; border-radius: 4px;">' +
                                (wbim_public.i18n.select_branch || 'გთხოვთ აირჩიოთ ფილიალი') +
                            '</div>');
                        }

                        // Scroll to branch selector
                        $('html, body').animate({
                            scrollTop: $wrapper.offset().top - 100
                        }, 300);

                        // Highlight the branch buttons
                        $wrapper.find('.wbim-branch-buttons').addClass('wbim-shake');
                        setTimeout(function() {
                            $wrapper.find('.wbim-branch-buttons').removeClass('wbim-shake');
                        }, 500);

                        return false;
                    }
                }
            });

            // Remove error when branch is selected
            $(document).on('click', '.wbim-branch-button', function() {
                $('.wbim-branch-error').remove();
            });
        },

        /**
         * Auto-select default branch on page load
         */
        autoSelectDefaultBranch: function() {
            var $wrapper = $('.wbim-branch-selector-wrapper');

            if (!$wrapper.length) {
                return;
            }

            // Skip if it's a variable product (wait for variation selection)
            if ($wrapper.data('is-variable') === 1) {
                return;
            }

            // Skip if already selected
            if ($('#wbim_branch_id').val()) {
                return;
            }

            var $branchToSelect = null;

            // Try to find the default branch from settings
            if (typeof wbim_public !== 'undefined' && wbim_public.default_branch_id) {
                $branchToSelect = $wrapper.find('.wbim-branch-button[data-branch-id="' + wbim_public.default_branch_id + '"]:not(.wbim-branch-disabled)').first();
            }

            // Fallback to first available branch if default not found or not available
            if (!$branchToSelect || !$branchToSelect.length) {
                $branchToSelect = $wrapper.find('.wbim-branch-button:not(.wbim-branch-disabled)').first();
            }

            if ($branchToSelect && $branchToSelect.length) {
                $branchToSelect.addClass('wbim-branch-selected');
                $('#wbim_branch_id').val($branchToSelect.data('branch-id'));
                $('#wbim_branch_name').val($branchToSelect.data('branch-name'));
            }
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
            var firstAvailable = null;

            $buttons.find('.wbim-branch-button').each(function() {
                var $button = $(this);
                var branchId = $button.data('branch-id');

                var branchData = stockData.find(function(item) {
                    return item.branch_id == branchId;
                });

                if (branchData) {
                    var qty = branchData.quantity;
                    var stockStatus = branchData.stock_status || 'instock';
                    var statusClass = branchData.status_class || 'wbim-stock-in';
                    var statusText = branchData.status_text || wbim_public.i18n.in_stock;
                    var $stockContainer = $button.find('.wbim-branch-button-stock');

                    // Remove existing stock classes
                    $button.removeClass('wbim-branch-in-stock wbim-branch-low-stock wbim-branch-out-of-stock wbim-branch-preorder wbim-branch-disabled');

                    // Determine availability based on stock_status (not just quantity)
                    var isAvailable = stockStatus !== 'outofstock';

                    if (!isAvailable) {
                        // Out of stock - disable
                        $button.addClass('wbim-branch-out-of-stock wbim-branch-disabled');
                        $stockContainer.html('<span class="' + statusClass + '">' + statusText + '</span>');

                        // Deselect if was selected
                        if ($button.hasClass('wbim-branch-selected')) {
                            $button.removeClass('wbim-branch-selected');
                            $('#wbim_branch_id').val('');
                            $('#wbim_branch_name').val('');
                        }
                    } else {
                        // Available - apply appropriate class
                        if (stockStatus === 'low') {
                            $button.addClass('wbim-branch-low-stock');
                        } else if (stockStatus === 'preorder') {
                            $button.addClass('wbim-branch-preorder');
                        } else {
                            $button.addClass('wbim-branch-in-stock');
                        }

                        // Show quantity if > 0, otherwise just status
                        if (qty > 0) {
                            $stockContainer.html('<span class="' + statusClass + '"><strong>' + qty + '</strong> ' + wbim_public.i18n.available + '</span>');
                        } else {
                            $stockContainer.html('<span class="' + statusClass + '">' + statusText + '</span>');
                        }

                        // Track first available for auto-select
                        if (!firstAvailable) {
                            firstAvailable = $button;
                        }
                    }

                    // Update data attributes
                    $button.data('stock', qty);
                    $button.data('stock-status', stockStatus);
                }
            });

            // Auto-select first available branch if none selected
            if (firstAvailable && !$('#wbim_branch_id').val()) {
                firstAvailable.addClass('wbim-branch-selected');
                $('#wbim_branch_id').val(firstAvailable.data('branch-id'));
                $('#wbim_branch_name').val(firstAvailable.data('branch-name'));
            }
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
                // Use status_class from response if available
                var statusClass = item.status_class || 'wbim-stock-out';
                var stockStatus = item.stock_status || 'outofstock';

                html += '<div class="wbim-branch-stock-item ' + statusClass + '" data-stock-status="' + stockStatus + '">';
                html += '<span class="wbim-branch-name">' + item.branch_name + '</span>';
                html += '<span class="wbim-stock-status">';

                // Show quantity only if status allows and quantity > 0
                if (stockStatus !== 'outofstock' && item.quantity > 0) {
                    html += item.status_text;
                    html += ' <span class="wbim-stock-quantity">(' + item.quantity + ' ' + wbim_public.i18n.available + ')</span>';
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
