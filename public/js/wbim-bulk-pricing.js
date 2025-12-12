/**
 * WBIM Bulk Pricing Frontend JavaScript
 *
 * Handles quantity button interactions and dynamic price updates.
 *
 * @package WBIM
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Bulk Pricing Module
     */
    var WBIMBulkPricing = {

        /**
         * Configuration from localized script
         */
        config: {},

        /**
         * Current variation data (for variable products)
         */
        currentVariation: null,

        /**
         * Original product price display HTML
         */
        originalPriceHtml: '',

        /**
         * Initialize the module
         */
        init: function() {
            var self = this;

            // Store config
            if (typeof wbim_bulk_pricing !== 'undefined') {
                this.config = wbim_bulk_pricing;
            } else {
                console.warn('WBIM Bulk Pricing: Config not found');
                return;
            }

            // Store original price HTML
            this.originalPriceHtml = $('p.price, .price').first().html();

            // Bind events
            this.bindEvents();

            // Initialize based on product type
            if (this.config.is_variable) {
                this.initVariableProduct();
            } else {
                this.initSimpleProduct();
            }

            console.log('WBIM Bulk Pricing initialized', this.config);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Quantity button click
            $(document).on('click', '.wbim-qty-btn', function(e) {
                e.preventDefault();
                self.handleButtonClick($(this));
            });

            // Quantity input change
            $(document).on('change input', 'input[name="quantity"]', function() {
                self.handleQuantityChange($(this));
            });

            // Variable product variation change
            $('.variations_form').on('found_variation', function(event, variation) {
                self.handleVariationFound(variation);
            });

            // Variable product reset
            $('.variations_form').on('reset_data', function() {
                self.handleVariationReset();
            });
        },

        /**
         * Initialize simple product
         */
        initSimpleProduct: function() {
            var $wrapper = $('.wbim-bulk-pricing-wrapper');

            if (!$wrapper.length) {
                return;
            }

            // Get initial quantity
            var initialQty = parseInt($('input[name="quantity"]').val()) || 1;

            // Update price for initial quantity
            this.updateSimpleProductPrice(initialQty);

            // Highlight matching button if any
            this.highlightMatchingButton(initialQty);
        },

        /**
         * Initialize variable product
         */
        initVariableProduct: function() {
            // Hide bulk pricing wrapper initially
            $('.wbim-bulk-variable').hide();
        },

        /**
         * Handle quantity button click
         *
         * @param {jQuery} $button Clicked button
         */
        handleButtonClick: function($button) {
            if ($button.hasClass('disabled')) {
                return;
            }

            var qty = parseInt($button.data('qty'));
            var price = parseFloat($button.data('price'));

            // Update quantity input
            $('input[name="quantity"]').val(qty).trigger('change');

            // Highlight this button
            $('.wbim-qty-btn').removeClass('active');
            $button.addClass('active');

            // Update displayed price
            if (this.config.is_variable && this.currentVariation) {
                this.updateVariableProductPrice(qty, price);
            } else if (!this.config.is_variable) {
                this.updateSimpleProductPrice(qty, price);
            }
        },

        /**
         * Handle quantity input change
         *
         * @param {jQuery} $input Quantity input
         */
        handleQuantityChange: function($input) {
            var qty = parseInt($input.val()) || 1;

            // Highlight matching button
            this.highlightMatchingButton(qty);

            // Update price
            if (this.config.is_variable && this.currentVariation) {
                this.updateVariableProductPrice(qty);
            } else if (!this.config.is_variable) {
                this.updateSimpleProductPrice(qty);
            }
        },

        /**
         * Highlight button matching current quantity
         *
         * @param {int} qty Current quantity
         */
        highlightMatchingButton: function(qty) {
            $('.wbim-qty-btn').removeClass('active');

            $('.wbim-qty-btn').each(function() {
                if (parseInt($(this).data('qty')) === qty) {
                    $(this).addClass('active');
                }
            });
        },

        /**
         * Handle variation found event
         *
         * @param {Object} variation Variation data
         */
        handleVariationFound: function(variation) {
            var self = this;
            var $wrapper = $('.wbim-bulk-variable');

            this.currentVariation = variation;

            // Check if this variation has bulk pricing
            if (!variation.wbim_bulk_pricing ||
                !variation.wbim_bulk_pricing.enabled ||
                !variation.wbim_bulk_pricing.buttons ||
                !variation.wbim_bulk_pricing.tiers ||
                variation.wbim_bulk_pricing.tiers.length === 0) {

                $wrapper.hide();
                return;
            }

            // Build buttons HTML
            var buttonsHtml = this.buildVariationButtons(variation.wbim_bulk_pricing);

            // Update wrapper content
            $wrapper.find('.wbim-qty-buttons').html(buttonsHtml);
            $wrapper.show();

            // Store original price for this variation
            this.originalPriceHtml = $('.woocommerce-variation-price .price').html() || this.originalPriceHtml;

            // Update price for current quantity
            var currentQty = parseInt($('input[name="quantity"]').val()) || 1;
            this.updateVariableProductPrice(currentQty);
            this.highlightMatchingButton(currentQty);
        },

        /**
         * Handle variation reset
         */
        handleVariationReset: function() {
            this.currentVariation = null;
            $('.wbim-bulk-variable').hide();

            // Restore original price display
            if (this.originalPriceHtml) {
                $('p.price, .price').first().html(this.originalPriceHtml);
            }
        },

        /**
         * Build buttons HTML for variation
         *
         * @param {Object} bulkData Bulk pricing data
         * @return {string} HTML
         */
        buildVariationButtons: function(bulkData) {
            var self = this;
            var tiers = bulkData.tiers;
            var regularPrice = bulkData.regular_price;
            var html = '';

            // Sort tiers by quantity ascending
            tiers.sort(function(a, b) {
                return a.qty - b.qty;
            });

            tiers.forEach(function(tier) {
                var total = tier.qty * tier.price;
                var savings = 0;

                if (regularPrice > 0 && tier.price < regularPrice) {
                    savings = Math.round(((regularPrice - tier.price) / regularPrice) * 100);
                }

                html += '<button type="button" class="wbim-qty-btn" ' +
                        'data-qty="' + tier.qty + '" ' +
                        'data-price="' + tier.price + '" ' +
                        'data-total="' + total + '">';
                html += '<span class="wbim-qty-btn-qty">' + tier.qty + ' <small>' + self.config.i18n.pcs + '</small></span>';
                html += '<span class="wbim-qty-btn-total">' + self.formatPrice(total) + '</span>';
                html += '<span class="wbim-qty-btn-unit">' + self.formatPrice(tier.price) + '/' + self.config.i18n.pcs + '</span>';

                if (savings > 0) {
                    html += '<span class="wbim-savings-badge">' + self.config.i18n.save + ' ' + savings + '%</span>';
                }

                html += '</button>';
            });

            return html;
        },

        /**
         * Update simple product price display
         *
         * @param {int}   qty   Quantity
         * @param {float} price Unit price (optional, will calculate if not provided)
         */
        updateSimpleProductPrice: function(qty, price) {
            var self = this;

            // If price not provided, calculate from tiers
            if (typeof price === 'undefined') {
                price = this.getPriceForQuantity(qty, this.getSimpleProductTiers());
            }

            if (!price || price <= 0) {
                return;
            }

            var total = price * qty;
            var regularPrice = this.getRegularPrice();
            var regularTotal = regularPrice * qty;

            this.updatePriceDisplay(total, regularTotal, regularPrice > price);
        },

        /**
         * Update variable product price display
         *
         * @param {int}   qty   Quantity
         * @param {float} price Unit price (optional)
         */
        updateVariableProductPrice: function(qty, price) {
            if (!this.currentVariation || !this.currentVariation.wbim_bulk_pricing) {
                return;
            }

            var bulkData = this.currentVariation.wbim_bulk_pricing;

            // If price not provided, calculate from tiers
            if (typeof price === 'undefined') {
                price = this.getPriceForQuantity(qty, bulkData.tiers);

                // If no tier matches, use variation's display price
                if (!price) {
                    price = parseFloat(this.currentVariation.display_price);
                }
            }

            if (!price || price <= 0) {
                return;
            }

            var total = price * qty;
            var regularPrice = bulkData.regular_price;
            var regularTotal = regularPrice * qty;

            this.updatePriceDisplay(total, regularTotal, regularPrice > price);
        },

        /**
         * Get price for quantity based on tiers
         *
         * @param {int}   qty   Quantity
         * @param {Array} tiers Pricing tiers
         * @return {float|null}
         */
        getPriceForQuantity: function(qty, tiers) {
            if (!tiers || tiers.length === 0) {
                return null;
            }

            // Sort tiers descending by quantity
            var sortedTiers = tiers.slice().sort(function(a, b) {
                return b.qty - a.qty;
            });

            // Find matching tier
            for (var i = 0; i < sortedTiers.length; i++) {
                if (qty >= sortedTiers[i].qty) {
                    return sortedTiers[i].price;
                }
            }

            return null;
        },

        /**
         * Get simple product tiers from DOM
         *
         * @return {Array}
         */
        getSimpleProductTiers: function() {
            var tiers = [];

            $('.wbim-qty-btn').each(function() {
                tiers.push({
                    qty: parseInt($(this).data('qty')),
                    price: parseFloat($(this).data('price'))
                });
            });

            return tiers;
        },

        /**
         * Get regular price from original price HTML
         *
         * @return {float}
         */
        getRegularPrice: function() {
            // Try to get from data attribute first
            var $wrapper = $('.wbim-bulk-pricing-wrapper');
            if ($wrapper.data('regular-price')) {
                return parseFloat($wrapper.data('regular-price'));
            }

            // Try to parse from original price HTML
            var $temp = $('<div>').html(this.originalPriceHtml);
            var priceText = $temp.find('del .woocommerce-Price-amount, .woocommerce-Price-amount').first().text();

            // Remove currency symbol and parse
            priceText = priceText.replace(/[^\d.,]/g, '').replace(',', '.');

            return parseFloat(priceText) || 0;
        },

        /**
         * Update price display in DOM
         *
         * @param {float}   total          Total price
         * @param {float}   regularTotal   Regular total
         * @param {boolean} showDiscount   Show as discounted
         */
        updatePriceDisplay: function(total, regularTotal, showDiscount) {
            var $priceContainer = $('p.price, .price').first();

            // For variable products, target variation price
            if (this.config.is_variable) {
                var $varPrice = $('.woocommerce-variation-price .price');
                if ($varPrice.length) {
                    $priceContainer = $varPrice;
                }
            }

            var priceHtml;

            if (showDiscount && regularTotal > total) {
                priceHtml = '<del>' + this.formatPriceHtml(regularTotal) + '</del> ';
                priceHtml += '<ins>' + this.formatPriceHtml(total) + '</ins>';
            } else {
                priceHtml = this.formatPriceHtml(total);
            }

            $priceContainer.html(priceHtml);
        },

        /**
         * Format price as HTML
         *
         * @param {float} price Price value
         * @return {string}
         */
        formatPriceHtml: function(price) {
            var formattedPrice = this.formatPrice(price);

            return '<span class="woocommerce-Price-amount amount">' +
                   '<bdi>' + formattedPrice + '</bdi>' +
                   '</span>';
        },

        /**
         * Format price with currency
         *
         * @param {float} price Price value
         * @return {string}
         */
        formatPrice: function(price) {
            var symbol = this.config.currency_symbol;
            var position = this.config.currency_pos;
            var decimals = this.config.decimals;
            var decimalSep = this.config.decimal_sep;
            var thousandSep = this.config.thousand_sep;

            // Format number
            var formatted = this.numberFormat(price, decimals, decimalSep, thousandSep);

            // Apply currency position
            switch (position) {
                case 'left':
                    return symbol + formatted;
                case 'right':
                    return formatted + symbol;
                case 'left_space':
                    return symbol + ' ' + formatted;
                case 'right_space':
                    return formatted + ' ' + symbol;
                default:
                    return symbol + formatted;
            }
        },

        /**
         * Number format helper
         *
         * @param {float}  number       Number to format
         * @param {int}    decimals     Decimal places
         * @param {string} decimalSep   Decimal separator
         * @param {string} thousandSep  Thousand separator
         * @return {string}
         */
        numberFormat: function(number, decimals, decimalSep, thousandSep) {
            decimals = decimals || 2;
            decimalSep = decimalSep || '.';
            thousandSep = thousandSep || ',';

            var parts = parseFloat(number).toFixed(decimals).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);

            return parts.join(decimalSep);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WBIMBulkPricing.init();
    });

})(jQuery);
