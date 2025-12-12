/**
 * WooCommerce Branch Inventory Manager - Stock JavaScript
 *
 * Handles stock management functionality including inline editing,
 * bulk updates, and CSV import.
 *
 * @package WBIM
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WBIM Stock object
     */
    var WBIMStock = {
        /**
         * Track modified inputs
         */
        modifiedInputs: {},

        /**
         * Auto-save debounce timer
         */
        autoSaveTimer: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initImport();
            this.initBulkActions();
            this.initVariationExpand();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Stock input change - track modifications
            $(document).on('change', '.wbim-stock-input', this.handleStockInputChange.bind(this));

            // Auto-save on blur
            $(document).on('blur', '.wbim-stock-input', this.handleAutoSaveOnBlur.bind(this));

            // Save all button
            $(document).on('click', '#wbim-save-all-stock', this.handleSaveAll.bind(this));

            // WC Sync button
            $(document).on('click', '#wbim-sync-wc', this.handleWCSync.bind(this));

            // Input focus highlight
            $(document).on('focus', '.wbim-stock-input', function() {
                $(this).closest('td').addClass('wbim-cell-editing');
            });

            $(document).on('blur', '.wbim-stock-input', function() {
                $(this).closest('td').removeClass('wbim-cell-editing');
            });

            // Keyboard navigation
            $(document).on('keydown', '.wbim-stock-input', this.handleKeyNavigation);
        },

        /**
         * Handle stock input change
         *
         * @param {Event} e Change event
         */
        handleStockInputChange: function(e) {
            var $input = $(e.target);
            var originalValue = parseInt($input.data('original')) || 0;
            var currentValue = parseInt($input.val()) || 0;

            console.log('WBIM Stock Change:', {
                productId: $input.data('product-id'),
                variationId: $input.data('variation-id'),
                branchId: $input.data('branch-id'),
                originalValue: originalValue,
                currentValue: currentValue
            });

            // Check if value actually changed from original
            if (currentValue !== originalValue) {
                var inputData = {
                    product_id: $input.data('product-id'),
                    variation_id: $input.data('variation-id') || 0,
                    branch_id: $input.data('branch-id'),
                    quantity: currentValue
                };
                console.log('WBIM Input Data to save:', inputData);

                // Mark as modified
                $input.addClass('wbim-modified');

                // Store modification
                var key = inputData.product_id + '-' + inputData.variation_id + '-' + inputData.branch_id;
                this.modifiedInputs[key] = inputData;
            } else {
                // Value returned to original, remove from modified
                $input.removeClass('wbim-modified');
                var key = $input.data('product-id') + '-' + ($input.data('variation-id') || 0) + '-' + $input.data('branch-id');
                delete this.modifiedInputs[key];
            }

            // Enable/disable save button based on modifications
            var hasModifications = Object.keys(this.modifiedInputs).length > 0;
            $('#wbim-save-all-stock').prop('disabled', !hasModifications);

            // Update modified count display
            this.updateModifiedCount();

            // Update row total
            this.updateRowTotal($input.closest('tr'));
        },

        /**
         * Handle auto-save on blur
         *
         * @param {Event} e Blur event
         */
        handleAutoSaveOnBlur: function(e) {
            var self = this;
            var $input = $(e.target);

            // Clear existing timer
            if (this.autoSaveTimer) {
                clearTimeout(this.autoSaveTimer);
            }

            // Only auto-save if input was modified
            if (!$input.hasClass('wbim-modified')) {
                return;
            }

            // Debounce - wait a bit in case user is tabbing to next field
            this.autoSaveTimer = setTimeout(function() {
                // Check if there are still modifications and no input is focused
                if (Object.keys(self.modifiedInputs).length > 0 && !$('.wbim-stock-input:focus').length) {
                    // Auto-save single input
                    self.saveSingleInput($input);
                }
            }, 500);
        },

        /**
         * Save single input value
         *
         * @param {jQuery} $input Input element
         */
        saveSingleInput: function($input) {
            var self = this;
            var productId = $input.data('product-id');
            var variationId = $input.data('variation-id') || 0;
            var branchId = $input.data('branch-id');
            var quantity = parseInt($input.val()) || 0;
            var key = productId + '-' + variationId + '-' + branchId;

            console.log('WBIM saveSingleInput:', {
                productId: productId,
                variationId: variationId,
                branchId: branchId,
                quantity: quantity,
                ajaxUrl: wbimStock.ajaxUrl
            });

            // Show saving indicator
            $input.addClass('wbim-saving');

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_update_stock',
                    nonce: wbimStock.nonce,
                    product_id: productId,
                    variation_id: variationId,
                    branch_id: branchId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        // Update original value
                        $input.data('original', quantity);
                        $input.removeClass('wbim-modified');

                        // Remove from modified list
                        delete self.modifiedInputs[key];

                        // Show brief success state
                        $input.addClass('wbim-saved');
                        setTimeout(function() {
                            $input.removeClass('wbim-saved');
                        }, 1000);

                        // Update button state
                        var hasModifications = Object.keys(self.modifiedInputs).length > 0;
                        $('#wbim-save-all-stock').prop('disabled', !hasModifications);
                        self.updateModifiedCount();
                    } else {
                        WBIMAdmin.showToast(response.data.message || wbimStock.strings.error, 'error');
                    }
                },
                error: function() {
                    WBIMAdmin.showToast(wbimStock.strings.error, 'error');
                },
                complete: function() {
                    $input.removeClass('wbim-saving');
                }
            });
        },

        /**
         * Update modified count display
         */
        updateModifiedCount: function() {
            var count = Object.keys(this.modifiedInputs).length;
            var $countContainer = $('.wbim-modified-count');

            if (count > 0) {
                $countContainer.show().find('.count').text(count);
            } else {
                $countContainer.hide();
            }
        },

        /**
         * Update row total
         *
         * @param {jQuery} $row Table row
         */
        updateRowTotal: function($row) {
            var total = 0;
            $row.find('.wbim-stock-input').each(function() {
                total += parseInt($(this).val()) || 0;
            });
            $row.find('.wbim-row-total').text(total);

            // Update parent row total if this is a variation row
            if ($row.hasClass('wbim-variation-row')) {
                var productId = $row.data('product-id');
                var $parentRow = $('tr.wbim-product-row[data-product-id="' + productId + '"]');

                if ($parentRow.length) {
                    var parentTotal = 0;
                    $('tr.wbim-variation-row[data-product-id="' + productId + '"]').each(function() {
                        $(this).find('.wbim-stock-input').each(function() {
                            parentTotal += parseInt($(this).val()) || 0;
                        });
                    });
                    $parentRow.find('.wbim-row-total').text(parentTotal);

                    // Also update branch summaries
                    $parentRow.find('.wbim-branch-summary').each(function() {
                        var $cell = $(this).closest('td');
                        var columnClass = $cell.attr('class').match(/column-branch_(\d+)/);
                        if (columnClass) {
                            var branchId = columnClass[1];
                            var branchTotal = 0;
                            $('tr.wbim-variation-row[data-product-id="' + productId + '"]').each(function() {
                                var $branchInput = $(this).find('.wbim-stock-input[data-branch-id="' + branchId + '"]');
                                if ($branchInput.length) {
                                    branchTotal += parseInt($branchInput.val()) || 0;
                                }
                            });
                            $(this).text(branchTotal);
                        }
                    });
                }
            }
        },

        /**
         * Handle keyboard navigation
         *
         * @param {Event} e Keydown event
         */
        handleKeyNavigation: function(e) {
            var $input = $(this);
            var $td = $input.closest('td');
            var $tr = $td.closest('tr');
            var $targetInput = null;

            switch (e.which) {
                case 38: // Up arrow
                    var $prevRow = $tr.prev('tr:visible');
                    if ($prevRow.length) {
                        $targetInput = $prevRow.find('td').eq($td.index()).find('.wbim-stock-input');
                    }
                    break;
                case 40: // Down arrow
                    var $nextRow = $tr.next('tr:visible');
                    if ($nextRow.length) {
                        $targetInput = $nextRow.find('td').eq($td.index()).find('.wbim-stock-input');
                    }
                    break;
                case 9: // Tab (default behavior)
                    return;
                case 13: // Enter - save all
                    e.preventDefault();
                    if (!$('#wbim-save-all-stock').prop('disabled')) {
                        $('#wbim-save-all-stock').click();
                    }
                    return;
            }

            if ($targetInput && $targetInput.length) {
                e.preventDefault();
                $targetInput.focus().select();
            }
        },

        /**
         * Handle save all button
         *
         * @param {Event} e Click event
         */
        handleSaveAll: function(e) {
            e.preventDefault();

            var self = this;
            var $button = $(e.target);
            var $status = $('.wbim-save-status');
            var updates = [];

            // Collect all modifications
            $.each(this.modifiedInputs, function(key, data) {
                updates.push(data);
            });

            console.log('WBIM handleSaveAll:', {
                modifiedInputs: this.modifiedInputs,
                updates: updates,
                ajaxUrl: wbimStock.ajaxUrl
            });

            if (updates.length === 0) {
                console.log('WBIM: No updates to save');
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true).addClass('wbim-loading');
            $status.text(wbimStock.strings.saving);

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_bulk_update_stock',
                    nonce: wbimStock.nonce,
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        // Update original values for all saved inputs
                        $.each(self.modifiedInputs, function(key, data) {
                            var $input = $('.wbim-stock-input[data-product-id="' + data.product_id + '"][data-variation-id="' + data.variation_id + '"][data-branch-id="' + data.branch_id + '"]');
                            if ($input.length) {
                                $input.data('original', data.quantity);
                            }
                        });

                        // Clear modifications
                        self.modifiedInputs = {};
                        $('.wbim-stock-input').removeClass('wbim-modified');

                        // Show success
                        $status.text(response.data.message).addClass('wbim-success');
                        if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                            WBIMAdmin.showToast(response.data.message, 'success');
                        }

                        // Update count
                        self.updateModifiedCount();

                        // Clear status after delay
                        setTimeout(function() {
                            $status.text('').removeClass('wbim-success');
                        }, 3000);
                    } else {
                        $status.text(response.data.message || wbimStock.strings.error).addClass('wbim-error');
                        if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                            WBIMAdmin.showToast(response.data.message || wbimStock.strings.error, 'error');
                        }
                    }
                },
                error: function() {
                    $status.text(wbimStock.strings.error).addClass('wbim-error');
                    if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                        WBIMAdmin.showToast(wbimStock.strings.error, 'error');
                    }
                },
                complete: function() {
                    $button.removeClass('wbim-loading');
                    setTimeout(function() {
                        $status.removeClass('wbim-error');
                    }, 3000);
                }
            });
        },

        /**
         * Handle WC Sync button
         *
         * @param {Event} e Click event
         */
        handleWCSync: function(e) {
            e.preventDefault();

            var $button = $(e.target);
            var originalText = $button.text();

            // Confirm action
            if (!confirm(wbimStock.strings.confirmSync || 'სინქრონიზაცია განაახლებს WooCommerce-ის მარაგს ფილიალების მარაგის ჯამით. გავაგრძელოთ?')) {
                return;
            }

            $button.prop('disabled', true).text(wbimStock.strings.syncing || 'სინქრონიზაცია...');

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_sync_all_wc_stock',
                    nonce: wbimStock.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                            WBIMAdmin.showToast(response.data.message, 'success');
                        } else {
                            alert(response.data.message);
                        }
                    } else {
                        if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                            WBIMAdmin.showToast(response.data.message || wbimStock.strings.error, 'error');
                        } else {
                            alert(response.data.message || wbimStock.strings.error);
                        }
                    }
                },
                error: function() {
                    if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                        WBIMAdmin.showToast(wbimStock.strings.error, 'error');
                    } else {
                        alert(wbimStock.strings.error);
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            var $bulkActionSelect = $('select[name="action"], select[name="action2"]');
            var $bulkSetStock = $('.wbim-bulk-set-stock');

            // Show/hide bulk set stock fields based on selected action
            $bulkActionSelect.on('change', function() {
                if ($(this).val() === 'set_stock') {
                    $bulkSetStock.show();
                } else {
                    $bulkSetStock.hide();
                }
            });
        },

        /**
         * Initialize variation expand functionality
         */
        initVariationExpand: function() {
            $(document).on('click', '.wbim-expand-variations', function(e) {
                e.preventDefault();
                var $button = $(this);
                var productId = $button.data('product-id');
                var $variationRows = $('tr.wbim-variation-row[data-product-id="' + productId + '"]');

                if ($button.hasClass('expanded')) {
                    // Collapse
                    $variationRows.hide();
                    $button.removeClass('expanded');
                } else {
                    // Expand
                    $variationRows.show();
                    $button.addClass('expanded');
                }
            });
        },

        /**
         * Initialize import functionality
         */
        initImport: function() {
            var $uploadArea = $('#wbim-upload-area');
            var $fileInput = $('#import_file');
            var $form = $('#wbim-import-form');

            if (!$uploadArea.length) {
                return;
            }

            // Drag and drop
            $uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('wbim-drag-over');
            });

            $uploadArea.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('wbim-drag-over');
            });

            $uploadArea.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    $fileInput[0].files = files;
                    WBIMStock.handleFileSelect(files[0]);
                }
            });

            // File input change
            $fileInput.on('change', function() {
                if (this.files.length) {
                    WBIMStock.handleFileSelect(this.files[0]);
                }
            });

            // Remove file
            $uploadArea.on('click', '.wbim-remove-file', function(e) {
                e.preventDefault();
                WBIMStock.clearFileSelection();
            });

            // Form submit - show preview first
            $form.on('submit', function(e) {
                e.preventDefault();

                // Check if preview was already shown
                if ($('#wbim-import-preview').is(':visible')) {
                    WBIMStock.handleImportSubmit();
                } else {
                    WBIMStock.showImportPreview();
                }
            });

            // Confirm import from preview
            $(document).on('click', '#wbim-confirm-import', function(e) {
                e.preventDefault();
                WBIMStock.handleImportSubmit();
            });

            // Cancel preview
            $(document).on('click', '#wbim-cancel-preview', function(e) {
                e.preventDefault();
                $('#wbim-import-preview').hide();
                $form.show();
            });
        },

        /**
         * Handle file selection
         *
         * @param {File} file Selected file
         */
        handleFileSelect: function(file) {
            var $uploadArea = $('#wbim-upload-area');
            var $uploadContent = $uploadArea.find('.wbim-upload-content');
            var $fileInfo = $uploadArea.find('.wbim-file-info');
            var $submitButton = $('#wbim-import-form').find('button[type="submit"]');

            // Validate file
            if (!file.name.toLowerCase().endsWith('.csv')) {
                if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                    WBIMAdmin.showToast(wbimStock.strings.invalidFile, 'error');
                } else {
                    alert(wbimStock.strings.invalidFile);
                }
                return;
            }

            // Show file info
            $uploadContent.hide();
            $fileInfo.show().find('.wbim-file-name').text(file.name);
            $submitButton.prop('disabled', false).text(wbimStock.strings.preview || 'გადახედვა');
        },

        /**
         * Clear file selection
         */
        clearFileSelection: function() {
            var $uploadArea = $('#wbim-upload-area');
            var $uploadContent = $uploadArea.find('.wbim-upload-content');
            var $fileInfo = $uploadArea.find('.wbim-file-info');
            var $fileInput = $('#import_file');
            var $submitButton = $('#wbim-import-form').find('button[type="submit"]');

            $fileInput.val('');
            $uploadContent.show();
            $fileInfo.hide();
            $submitButton.prop('disabled', true).text(wbimStock.strings.startImport || 'იმპორტის დაწყება');

            // Hide preview if visible
            $('#wbim-import-preview').hide();
        },

        /**
         * Show import preview
         */
        showImportPreview: function() {
            var $form = $('#wbim-import-form');
            var $preview = $('#wbim-import-preview');
            var formData = new FormData($form[0]);

            formData.append('action', 'wbim_preview_import');
            formData.append('nonce', wbimStock.nonce);

            // Show loading
            $form.hide();
            if (!$preview.length) {
                $preview = $('<div id="wbim-import-preview" class="wbim-preview-container"><div class="wbim-preview-loading">' + (wbimStock.strings.loadingPreview || 'იტვირთება...') + '</div></div>');
                $form.after($preview);
            } else {
                $preview.html('<div class="wbim-preview-loading">' + (wbimStock.strings.loadingPreview || 'იტვირთება...') + '</div>').show();
            }

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<h3>' + (wbimStock.strings.previewTitle || 'იმპორტის გადახედვა') + '</h3>';

                        // Summary
                        html += '<div class="wbim-preview-summary">';
                        html += '<p><strong>' + (wbimStock.strings.totalRows || 'სულ სტრიქონები') + ':</strong> ' + data.total_rows + '</p>';
                        html += '<p><strong>' + (wbimStock.strings.validRows || 'ვალიდური') + ':</strong> ' + data.valid_rows + '</p>';
                        if (data.errors.length > 0) {
                            html += '<p class="wbim-preview-errors"><strong>' + (wbimStock.strings.errors || 'შეცდომები') + ':</strong> ' + data.errors.length + '</p>';
                        }
                        html += '</div>';

                        // Preview table
                        if (data.preview && data.preview.length > 0) {
                            html += '<div class="wbim-preview-table-wrapper">';
                            html += '<table class="widefat striped">';
                            html += '<thead><tr>';
                            html += '<th>SKU</th><th>' + (wbimStock.strings.branch || 'ფილიალი') + '</th><th>' + (wbimStock.strings.quantity || 'რაოდენობა') + '</th><th>' + (wbimStock.strings.status || 'სტატუსი') + '</th>';
                            html += '</tr></thead><tbody>';

                            $.each(data.preview, function(i, row) {
                                var statusClass = row.status === 'valid' ? 'wbim-status-valid' : 'wbim-status-invalid';
                                var statusText = row.status === 'valid' ? (wbimStock.strings.valid || 'ვალიდური') : row.error;
                                html += '<tr class="' + statusClass + '">';
                                html += '<td>' + (row.sku || '-') + '</td>';
                                html += '<td>' + (row.branch_name || row.branch_id || '-') + '</td>';
                                html += '<td>' + (row.quantity || '0') + '</td>';
                                html += '<td>' + statusText + '</td>';
                                html += '</tr>';
                            });

                            html += '</tbody></table>';
                            html += '</div>';

                            if (data.total_rows > data.preview.length) {
                                html += '<p class="description">' + (wbimStock.strings.moreRows || 'და კიდევ') + ' ' + (data.total_rows - data.preview.length) + ' ' + (wbimStock.strings.rows || 'სტრიქონი') + '...</p>';
                            }
                        }

                        // Errors list
                        if (data.errors.length > 0) {
                            html += '<div class="wbim-preview-errors-list">';
                            html += '<h4>' + (wbimStock.strings.errors || 'შეცდომები') + '</h4>';
                            html += '<ul>';
                            $.each(data.errors.slice(0, 10), function(i, error) {
                                html += '<li>' + error + '</li>';
                            });
                            if (data.errors.length > 10) {
                                html += '<li>... ' + (data.errors.length - 10) + ' ' + (wbimStock.strings.moreErrors || 'მეტი შეცდომა') + '</li>';
                            }
                            html += '</ul></div>';
                        }

                        // Action buttons
                        html += '<div class="wbim-preview-actions">';
                        if (data.valid_rows > 0) {
                            html += '<button type="button" id="wbim-confirm-import" class="button button-primary">' + (wbimStock.strings.confirmImport || 'იმპორტის დადასტურება') + ' (' + data.valid_rows + ')</button>';
                        }
                        html += '<button type="button" id="wbim-cancel-preview" class="button">' + (wbimStock.strings.cancel || 'გაუქმება') + '</button>';
                        html += '</div>';

                        $preview.html(html);
                    } else {
                        $preview.html('<div class="notice notice-error"><p>' + (response.data.message || wbimStock.strings.error) + '</p></div><p><button type="button" id="wbim-cancel-preview" class="button">' + (wbimStock.strings.back || 'უკან') + '</button></p>');
                    }
                },
                error: function() {
                    $preview.html('<div class="notice notice-error"><p>' + wbimStock.strings.error + '</p></div><p><button type="button" id="wbim-cancel-preview" class="button">' + (wbimStock.strings.back || 'უკან') + '</button></p>');
                }
            });
        },

        /**
         * Handle import form submit
         */
        handleImportSubmit: function() {
            var $form = $('#wbim-import-form');
            var $preview = $('#wbim-import-preview');
            var $progress = $('#wbim-import-progress');
            var $results = $('#wbim-import-results');
            var formData = new FormData($form[0]);

            formData.append('action', 'wbim_import_stock');
            formData.append('nonce', wbimStock.nonce);

            // Show progress
            $form.hide();
            $preview.hide();
            $progress.show();
            $results.hide();

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $progress.hide();
                    $results.show();

                    if (response.success) {
                        var results = response.data.results;
                        var summaryHtml = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';

                        // Show detailed results
                        summaryHtml += '<ul>';
                        summaryHtml += '<li>' + wbimStock.strings.imported + ': <strong>' + results.success + '</strong></li>';
                        summaryHtml += '<li>' + wbimStock.strings.skipped + ': <strong>' + results.skipped + '</strong></li>';
                        summaryHtml += '<li>' + wbimStock.strings.total + ': <strong>' + results.total_rows + '</strong></li>';
                        summaryHtml += '</ul>';

                        $results.find('.wbim-results-summary').html(summaryHtml);

                        // Show errors if any
                        if (results.errors && results.errors.length > 0) {
                            var errorsHtml = '<h4>' + wbimStock.strings.errors + '</h4><ul class="wbim-error-list">';
                            $.each(results.errors.slice(0, 20), function(i, error) {
                                errorsHtml += '<li>' + error + '</li>';
                            });
                            if (results.errors.length > 20) {
                                errorsHtml += '<li>... ' + (results.errors.length - 20) + ' ' + wbimStock.strings.moreErrors + '</li>';
                            }
                            errorsHtml += '</ul>';
                            $results.find('.wbim-results-errors').html(errorsHtml);
                        }
                    } else {
                        $results.find('.wbim-results-summary').html(
                            '<div class="notice notice-error inline"><p>' + (response.data.message || wbimStock.strings.error) + '</p></div>'
                        );
                    }
                },
                error: function() {
                    $progress.hide();
                    $results.show();
                    $results.find('.wbim-results-summary').html(
                        '<div class="notice notice-error inline"><p>' + wbimStock.strings.error + '</p></div>'
                    );
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WBIMStock.init();
    });

    /**
     * Make WBIMStock globally accessible
     */
    window.WBIMStock = WBIMStock;

})(jQuery);
