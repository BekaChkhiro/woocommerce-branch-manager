/**
 * WooCommerce Branch Inventory Manager - Admin JavaScript
 *
 * @package WBIM
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WBIM Admin object
     */
    var WBIMAdmin = {
        /**
         * Google Map instance
         */
        map: null,

        /**
         * Map marker instance
         */
        marker: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initGoogleMaps();
            this.initSortable();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle branch status
            $(document).on('click', '.wbim-toggle-status', this.handleToggleStatus);

            // Delete branch
            $(document).on('click', '.wbim-delete-branch', this.handleDeleteBranch);

            // Set default branch
            $(document).on('click', '.wbim-set-default', this.handleSetDefault);

            // Clear coordinates
            $(document).on('click', '#clear-coordinates', this.handleClearCoordinates);

            // Form validation
            $(document).on('submit', '#wbim-branch-form', this.handleFormSubmit);

            // Variation stock input change - update total
            $(document).on('input change', '.wbim-variation-stock-input', this.handleVariationStockChange);
        },

        /**
         * Handle variation stock input change
         */
        handleVariationStockChange: function() {
            var $input = $(this);
            var loop = $input.data('loop');
            var $wrapper = $input.closest('.wbim-variation-inventory-wrapper');

            // Calculate total for this variation
            var total = 0;
            $wrapper.find('.wbim-variation-stock-input').each(function() {
                var val = parseInt($(this).val()) || 0;
                total += val;
            });

            // Update the total display
            $wrapper.find('.wbim-total-qty').text(total);
        },

        /**
         * Initialize Google Maps
         */
        initGoogleMaps: function() {
            var mapContainer = document.getElementById('wbim-map');

            if (!mapContainer || !wbimAdmin.hasGoogleMaps) {
                // Show placeholder if no API key
                if (mapContainer) {
                    $(mapContainer).html(
                        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#646970;">' +
                        '<span class="dashicons dashicons-location" style="font-size:48px;margin-right:10px;"></span>' +
                        '<span>' + wbimAdmin.strings.selectLocation + '</span>' +
                        '</div>'
                    );
                }
                return;
            }

            // Get existing coordinates or use defaults
            var lat = parseFloat($('#lat').val()) || wbimAdmin.defaultLat;
            var lng = parseFloat($('#lng').val()) || wbimAdmin.defaultLng;
            var hasExistingCoords = $('#lat').val() && $('#lng').val();

            // Initialize map
            this.map = new google.maps.Map(mapContainer, {
                center: { lat: lat, lng: lng },
                zoom: hasExistingCoords ? 15 : 12,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true
            });

            // Add marker if coordinates exist
            if (hasExistingCoords) {
                this.addMarker(lat, lng);
            }

            // Handle map click
            var self = this;
            this.map.addListener('click', function(e) {
                self.setCoordinates(e.latLng.lat(), e.latLng.lng());
            });

            // Add search box if Places library is available
            this.initPlacesSearch();
        },

        /**
         * Initialize Places Search
         */
        initPlacesSearch: function() {
            if (typeof google === 'undefined' || typeof google.maps.places === 'undefined') {
                return;
            }

            var input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'Search location...';
            input.className = 'wbim-map-search';
            input.style.cssText = 'margin:10px;padding:10px;width:300px;border:1px solid #ccc;border-radius:4px;font-size:14px;';

            this.map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

            var searchBox = new google.maps.places.SearchBox(input);
            var self = this;

            searchBox.addListener('places_changed', function() {
                var places = searchBox.getPlaces();
                if (places.length === 0) return;

                var place = places[0];
                if (!place.geometry) return;

                self.map.setCenter(place.geometry.location);
                self.map.setZoom(15);
                self.setCoordinates(
                    place.geometry.location.lat(),
                    place.geometry.location.lng()
                );
            });
        },

        /**
         * Set coordinates and update form fields
         *
         * @param {number} lat Latitude
         * @param {number} lng Longitude
         */
        setCoordinates: function(lat, lng) {
            // Update form fields
            $('#lat').val(lat.toFixed(8));
            $('#lng').val(lng.toFixed(8));

            // Update marker
            this.addMarker(lat, lng);
        },

        /**
         * Add or move marker on map
         *
         * @param {number} lat Latitude
         * @param {number} lng Longitude
         */
        addMarker: function(lat, lng) {
            var position = { lat: lat, lng: lng };

            if (this.marker) {
                this.marker.setPosition(position);
            } else {
                this.marker = new google.maps.Marker({
                    position: position,
                    map: this.map,
                    draggable: true,
                    animation: google.maps.Animation.DROP
                });

                // Handle marker drag
                var self = this;
                this.marker.addListener('dragend', function(e) {
                    self.setCoordinates(e.latLng.lat(), e.latLng.lng());
                });
            }
        },

        /**
         * Handle clear coordinates button
         *
         * @param {Event} e Click event
         */
        handleClearCoordinates: function(e) {
            e.preventDefault();

            $('#lat').val('');
            $('#lng').val('');

            if (WBIMAdmin.marker) {
                WBIMAdmin.marker.setMap(null);
                WBIMAdmin.marker = null;
            }
        },

        /**
         * Handle toggle branch status
         *
         * @param {Event} e Click event
         */
        handleToggleStatus: function(e) {
            e.preventDefault();

            var $link = $(this);
            var branchId = $link.data('branch-id');
            var action = $link.data('action');

            if (action === 'deactivate' && !confirm(wbimAdmin.strings.confirmDeactivate)) {
                return;
            }

            $link.addClass('wbim-loading');

            $.ajax({
                url: wbimAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_toggle_branch_status',
                    nonce: wbimAdmin.nonce,
                    branch_id: branchId
                },
                success: function(response) {
                    if (response.success) {
                        WBIMAdmin.showToast(response.data.message, 'success');
                        // Reload page to reflect changes
                        location.reload();
                    } else {
                        WBIMAdmin.showToast(response.data.message || wbimAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    WBIMAdmin.showToast(wbimAdmin.strings.error, 'error');
                },
                complete: function() {
                    $link.removeClass('wbim-loading');
                }
            });
        },

        /**
         * Handle delete branch
         *
         * @param {Event} e Click event
         */
        handleDeleteBranch: function(e) {
            e.preventDefault();

            if (!confirm(wbimAdmin.strings.confirmDelete)) {
                return;
            }

            var $link = $(this);
            var branchId = $link.data('branch-id');

            $link.addClass('wbim-loading');

            $.ajax({
                url: wbimAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_delete_branch',
                    nonce: wbimAdmin.nonce,
                    branch_id: branchId,
                    force: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        WBIMAdmin.showToast(response.data.message, 'success');
                        // Remove row or reload
                        $link.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        WBIMAdmin.showToast(response.data.message || wbimAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    WBIMAdmin.showToast(wbimAdmin.strings.error, 'error');
                },
                complete: function() {
                    $link.removeClass('wbim-loading');
                }
            });
        },

        /**
         * Handle set default branch
         *
         * @param {Event} e Click event
         */
        handleSetDefault: function(e) {
            e.preventDefault();

            var $link = $(this);
            var branchId = $link.data('branch-id');

            $link.addClass('wbim-loading');

            $.ajax({
                url: wbimAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_set_default_branch',
                    nonce: wbimAdmin.nonce,
                    branch_id: branchId
                },
                success: function(response) {
                    if (response.success) {
                        WBIMAdmin.showToast(response.data.message, 'success');
                        // Reload page to reflect changes
                        location.reload();
                    } else {
                        WBIMAdmin.showToast(response.data.message || wbimAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    WBIMAdmin.showToast(wbimAdmin.strings.error, 'error');
                },
                complete: function() {
                    $link.removeClass('wbim-loading');
                }
            });
        },

        /**
         * Handle form submission
         *
         * @param {Event} e Submit event
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $name = $form.find('#name');

            // Clear previous errors
            $form.find('.wbim-field-error').removeClass('wbim-field-error');

            // Validate name
            if (!$name.val().trim()) {
                e.preventDefault();
                $name.addClass('wbim-field-error').focus();
                WBIMAdmin.showToast('Branch name is required.', 'error');
                return false;
            }

            // Validate email if provided
            var $email = $form.find('#email');
            if ($email.val() && !WBIMAdmin.isValidEmail($email.val())) {
                e.preventDefault();
                $email.addClass('wbim-field-error').focus();
                WBIMAdmin.showToast('Please enter a valid email address.', 'error');
                return false;
            }

            return true;
        },

        /**
         * Initialize sortable branches
         */
        initSortable: function() {
            // Check if we're on branches list page
            var $branchesList = $('.wbim-branches-list');
            if (!$branchesList.length) {
                return;
            }

            var $tbody = $branchesList.find('.wp-list-table tbody');
            if (!$tbody.length) {
                return;
            }

            // Make tbody rows sortable
            $tbody.sortable({
                handle: '.wbim-drag-handle',
                placeholder: 'ui-sortable-placeholder',
                axis: 'y',
                helper: function(e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).outerWidth());
                    });
                    return $helper;
                },
                update: function(event, ui) {
                    var order = [];
                    $tbody.find('tr').each(function() {
                        var branchId = $(this).data('branch-id');
                        if (branchId) {
                            order.push(branchId);
                        }
                    });
                    WBIMAdmin.saveBranchOrder(order);
                }
            });
        },

        /**
         * Save branch order via AJAX
         *
         * @param {Array} order Array of branch IDs
         */
        saveBranchOrder: function(order) {
            $.ajax({
                url: wbimAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_reorder_branches',
                    nonce: wbimAdmin.nonce,
                    order: order
                },
                success: function(response) {
                    if (response.success) {
                        WBIMAdmin.showToast(wbimAdmin.strings.saved, 'success');
                    } else {
                        WBIMAdmin.showToast(response.data.message || wbimAdmin.strings.error, 'error');
                    }
                },
                error: function() {
                    WBIMAdmin.showToast(wbimAdmin.strings.error, 'error');
                }
            });
        },

        /**
         * Show toast notification
         *
         * @param {string} message Message to display
         * @param {string} type    Notification type (success, error)
         */
        showToast: function(message, type) {
            // Remove existing toasts
            $('.wbim-toast').remove();

            var $toast = $('<div class="wbim-toast"></div>')
                .addClass(type)
                .text(message)
                .appendTo('body');

            // Auto hide after 3 seconds
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Validate email address
         *
         * @param {string} email Email address
         * @returns {boolean}
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WBIMAdmin.init();
    });

    /**
     * Make WBIMAdmin globally accessible
     */
    window.WBIMAdmin = WBIMAdmin;

})(jQuery);
