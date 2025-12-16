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
            var $branchSelect = $('#import_branch_id');

            if (!$uploadArea.length) {
                return;
            }

            // Branch selection change
            $branchSelect.on('change', function() {
                WBIMStock.updateSubmitButtonState();
            });

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

                // Validate branch is selected
                var branchId = $branchSelect.val();
                if (!branchId) {
                    if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                        WBIMAdmin.showToast(wbimStock.strings.selectBranch || 'გთხოვთ აირჩიოთ ფილიალი.', 'error');
                    } else {
                        alert(wbimStock.strings.selectBranch || 'გთხოვთ აირჩიოთ ფილიალი.');
                    }
                    return;
                }

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
         * Update submit button state based on file and branch selection
         */
        updateSubmitButtonState: function() {
            var $fileInput = $('#import_file');
            var $branchSelect = $('#import_branch_id');
            var $submitButton = $('#wbim-import-form').find('button[type="submit"]');

            var hasFile = $fileInput[0] && $fileInput[0].files && $fileInput[0].files.length > 0;
            var hasBranch = $branchSelect.val() && $branchSelect.val() !== '';

            if (hasFile && hasBranch) {
                $submitButton.prop('disabled', false).text(wbimStock.strings.preview || 'გადახედვა');
            } else {
                $submitButton.prop('disabled', true).text(wbimStock.strings.startImport || 'იმპორტის დაწყება');
            }
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
            var fileName = file.name.toLowerCase();

            // Validate file - accept both CSV and JSON
            if (!fileName.endsWith('.csv') && !fileName.endsWith('.json')) {
                if (typeof WBIMAdmin !== 'undefined' && WBIMAdmin.showToast) {
                    WBIMAdmin.showToast(wbimStock.strings.invalidFile || 'გთხოვთ აირჩიოთ CSV ან JSON ფაილი.', 'error');
                } else {
                    alert(wbimStock.strings.invalidFile || 'გთხოვთ აირჩიოთ CSV ან JSON ფაილი.');
                }
                return;
            }

            // Show file info
            $uploadContent.hide();
            $fileInfo.show().find('.wbim-file-name').text(file.name);

            // Check if branch is selected and enable button
            WBIMStock.updateSubmitButtonState();
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

                        // Store total for progress
                        WBIMStock.importTotalRows = data.valid_rows;

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
                        // Show detailed error
                        var errorHtml = '<div class="notice notice-error"><p><strong>' + (response.data.message || wbimStock.strings.error) + '</strong></p>';
                        if (response.data.debug) {
                            errorHtml += '<p class="wbim-debug-info"><small>Debug: ' + response.data.debug + '</small></p>';
                        }
                        if (response.data.file_info) {
                            errorHtml += '<p class="wbim-debug-info"><small>File: ' + response.data.file_info.name + ' (' + response.data.file_info.type + ', ' + response.data.file_info.size + ' bytes)</small></p>';
                        }
                        errorHtml += '</div>';
                        errorHtml += '<p><button type="button" id="wbim-cancel-preview" class="button">' + (wbimStock.strings.back || 'უკან') + '</button></p>';
                        $preview.html(errorHtml);
                    }
                },
                error: function(xhr, status, error) {
                    var errorHtml = '<div class="notice notice-error">';
                    errorHtml += '<p><strong>' + wbimStock.strings.error + '</strong></p>';
                    errorHtml += '<p class="wbim-debug-info"><small>Status: ' + status + '</small></p>';
                    errorHtml += '<p class="wbim-debug-info"><small>Error: ' + error + '</small></p>';
                    if (xhr.responseText) {
                        errorHtml += '<p class="wbim-debug-info"><small>Response: ' + xhr.responseText.substring(0, 500) + '</small></p>';
                    }
                    errorHtml += '</div>';
                    errorHtml += '<p><button type="button" id="wbim-cancel-preview" class="button">' + (wbimStock.strings.back || 'უკან') + '</button></p>';
                    $preview.html(errorHtml);
                }
            });
        },

        /**
         * Build collapsible section HTML for import results
         *
         * @param {string} id Section ID
         * @param {string} title Section title
         * @param {Array} items Array of items with sku, name, reason
         * @param {string} className CSS class name
         * @return {string} HTML string
         */
        buildCollapsibleSection: function(id, title, items, className) {
            var perPage = 50;
            var totalPages = Math.ceil(items.length / perPage);

            var html = '<div class="wbim-collapsible-section ' + className + '" data-section-id="' + id + '">';
            html += '<div class="wbim-collapsible-header">';
            html += '<span class="wbim-collapsible-toggle dashicons dashicons-arrow-right-alt2"></span>';
            html += '<span class="wbim-collapsible-title">' + title + ' (' + items.length + ')</span>';
            html += '</div>';
            html += '<div class="wbim-collapsible-content" style="display: none;">';

            // Build table
            html += '<table class="widefat striped wbim-details-table">';
            html += '<thead><tr>';
            html += '<th style="width: 120px;">SKU</th>';
            html += '<th>პროდუქტის სახელი</th>';
            html += '<th style="width: 250px;">მიზეზი</th>';
            html += '</tr></thead>';
            html += '<tbody class="wbim-paginated-content" data-per-page="' + perPage + '" data-total-pages="' + totalPages + '" data-current-page="1">';

            // Store all items as data attribute for pagination
            $.each(items, function(i, item) {
                var displayClass = i < perPage ? '' : 'style="display: none;"';
                html += '<tr class="wbim-paginated-item" data-index="' + i + '" ' + displayClass + '>';
                html += '<td><code>' + (item.sku || '-') + '</code></td>';
                html += '<td>' + (item.name || '-') + '</td>';
                html += '<td>' + (item.reason || '-') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Pagination controls
            if (totalPages > 1) {
                html += '<div class="wbim-pagination" data-section="' + id + '">';
                html += '<button type="button" class="button wbim-page-prev" disabled>&laquo; წინა</button>';
                html += '<span class="wbim-page-info">გვერდი <span class="wbim-current-page">1</span> / ' + totalPages + '</span>';
                html += '<button type="button" class="button wbim-page-next"' + (totalPages <= 1 ? ' disabled' : '') + '>შემდეგი &raquo;</button>';
                html += '</div>';
            }

            html += '</div></div>';

            return html;
        },

        /**
         * Initialize collapsible sections and pagination
         */
        initCollapsibleSections: function() {
            // Toggle collapsible sections
            $(document).off('click.wbimCollapsible').on('click.wbimCollapsible', '.wbim-collapsible-header', function() {
                var $section = $(this).closest('.wbim-collapsible-section');
                var $content = $section.find('.wbim-collapsible-content');
                var $toggle = $(this).find('.wbim-collapsible-toggle');

                $content.slideToggle(200);
                $toggle.toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
            });

            // Pagination - Previous
            $(document).off('click.wbimPagPrev').on('click.wbimPagPrev', '.wbim-page-prev', function() {
                var $pagination = $(this).closest('.wbim-pagination');
                var $tbody = $pagination.siblings('table').find('.wbim-paginated-content');
                WBIMStock.changePage($tbody, $pagination, -1);
            });

            // Pagination - Next
            $(document).off('click.wbimPagNext').on('click.wbimPagNext', '.wbim-page-next', function() {
                var $pagination = $(this).closest('.wbim-pagination');
                var $tbody = $pagination.siblings('table').find('.wbim-paginated-content');
                WBIMStock.changePage($tbody, $pagination, 1);
            });
        },

        /**
         * Change pagination page
         *
         * @param {jQuery} $tbody Table body element
         * @param {jQuery} $pagination Pagination container
         * @param {number} direction -1 for previous, 1 for next
         */
        changePage: function($tbody, $pagination, direction) {
            var currentPage = parseInt($tbody.data('current-page')) || 1;
            var totalPages = parseInt($tbody.data('total-pages')) || 1;
            var perPage = parseInt($tbody.data('per-page')) || 50;
            var newPage = currentPage + direction;

            if (newPage < 1 || newPage > totalPages) {
                return;
            }

            // Update current page
            $tbody.data('current-page', newPage);

            // Calculate range
            var startIndex = (newPage - 1) * perPage;
            var endIndex = startIndex + perPage - 1;

            // Show/hide rows
            $tbody.find('.wbim-paginated-item').each(function() {
                var index = parseInt($(this).data('index'));
                if (index >= startIndex && index <= endIndex) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            // Update pagination UI
            $pagination.find('.wbim-current-page').text(newPage);
            $pagination.find('.wbim-page-prev').prop('disabled', newPage <= 1);
            $pagination.find('.wbim-page-next').prop('disabled', newPage >= totalPages);
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
            var totalRows = WBIMStock.importTotalRows || 0;

            formData.append('action', 'wbim_import_stock');
            formData.append('nonce', wbimStock.nonce);

            // Show progress
            $form.hide();
            $preview.hide();
            $results.hide();

            // Create enhanced progress UI
            var progressHtml = '<div class="wbim-import-progress-container">';
            progressHtml += '<h3>იმპორტი მიმდინარეობს...</h3>';
            progressHtml += '<div class="wbim-progress-bar"><div class="wbim-progress-fill" style="width: 0%"></div></div>';
            progressHtml += '<p class="wbim-progress-text"><span class="wbim-progress-percent">0%</span> - <span class="wbim-progress-status">მზადდება...</span></p>';
            progressHtml += '<p class="wbim-progress-details"><small>სულ: ' + totalRows + ' პროდუქტი</small></p>';
            progressHtml += '</div>';

            $progress.html(progressHtml).show();

            // Simulate progress animation
            var progressInterval;
            var currentProgress = 0;
            var $progressFill = $progress.find('.wbim-progress-fill');
            var $progressPercent = $progress.find('.wbim-progress-percent');
            var $progressStatus = $progress.find('.wbim-progress-status');

            progressInterval = setInterval(function() {
                if (currentProgress < 90) {
                    currentProgress += Math.random() * 10;
                    if (currentProgress > 90) currentProgress = 90;
                    $progressFill.css('width', currentProgress + '%');
                    $progressPercent.text(Math.round(currentProgress) + '%');
                    $progressStatus.text('იმპორტირდება პროდუქტები...');
                }
            }, 500);

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 600000, // 10 minutes timeout for large imports
                success: function(response) {
                    clearInterval(progressInterval);

                    // Complete progress animation
                    $progressFill.css('width', '100%');
                    $progressPercent.text('100%');
                    $progressStatus.text('დასრულდა!');

                    setTimeout(function() {
                        $progress.hide();
                        $results.show();

                        if (response.success) {
                            var results = response.data.results;
                            var successPercent = results.total_rows > 0 ? Math.round((results.success / results.total_rows) * 100) : 0;

                            var summaryHtml = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';

                            // Show detailed results with visual stats
                            summaryHtml += '<div class="wbim-import-stats">';
                            summaryHtml += '<div class="wbim-stat wbim-stat-success"><span class="wbim-stat-number">' + results.success + '</span><span class="wbim-stat-label">' + (wbimStock.strings.imported || 'იმპორტირებული') + '</span></div>';
                            summaryHtml += '<div class="wbim-stat wbim-stat-skipped"><span class="wbim-stat-number">' + results.skipped + '</span><span class="wbim-stat-label">' + (wbimStock.strings.skipped || 'გამოტოვებული') + '</span></div>';
                            summaryHtml += '<div class="wbim-stat wbim-stat-total"><span class="wbim-stat-number">' + results.total_rows + '</span><span class="wbim-stat-label">' + (wbimStock.strings.total || 'სულ') + '</span></div>';
                            summaryHtml += '</div>';

                            $results.find('.wbim-results-summary').html(summaryHtml);

                            // Show detailed results in collapsible sections
                            var allMessages = '';

                            // Error details (products not found, etc.)
                            if (results.error_details && results.error_details.length > 0) {
                                allMessages += WBIMStock.buildCollapsibleSection(
                                    'errors',
                                    'შეცდომები',
                                    results.error_details,
                                    'wbim-section-errors'
                                );
                            }

                            // Skipped details (variable products, etc.)
                            if (results.skipped_details && results.skipped_details.length > 0) {
                                allMessages += WBIMStock.buildCollapsibleSection(
                                    'skipped',
                                    'გამოტოვებული პროდუქტები',
                                    results.skipped_details,
                                    'wbim-section-skipped'
                                );
                            }

                            // Check for background sync task
                            var syncTaskId = response.data.sync_task_id;
                            if (syncTaskId) {
                                // Show background sync status section
                                allMessages += '<div class="wbim-background-sync-status" data-task-id="' + syncTaskId + '">';
                                allMessages += '<div class="wbim-sync-status-header">';
                                allMessages += '<span class="dashicons dashicons-update wbim-sync-spinner"></span>';
                                allMessages += '<span class="wbim-sync-status-title">სინქრონიზაცია მიმდინარეობს ფონურ რეჟიმში...</span>';
                                allMessages += '</div>';
                                allMessages += '<p class="wbim-sync-status-text">ფაილში არსებული პროდუქტების გარდა დანარჩენი პროდუქტები მოინიშნება "არ არის მარაგში".</p>';
                                allMessages += '<p class="wbim-sync-status-info"><small>სტატუსი ავტომატურად განახლდება</small></p>';
                                allMessages += '</div>';

                                // Start polling for sync status
                                setTimeout(function() {
                                    WBIMStock.pollSyncStatus(syncTaskId);
                                }, 3000);
                            }

                            if (allMessages) {
                                $results.find('.wbim-results-errors').html(allMessages);
                                // Initialize collapsible and pagination
                                WBIMStock.initCollapsibleSections();
                            } else {
                                $results.find('.wbim-results-errors').empty();
                            }
                        } else {
                            // Show detailed error
                            var errorHtml = '<div class="notice notice-error inline">';
                            errorHtml += '<p><strong>' + (response.data.message || wbimStock.strings.error) + '</strong></p>';
                            if (response.data.debug) {
                                errorHtml += '<p class="wbim-debug-info"><small><strong>Debug:</strong> ' + response.data.debug + '</small></p>';
                            }
                            if (response.data.errors && response.data.errors.length > 0) {
                                errorHtml += '<div class="wbim-error-details"><p><strong>დეტალები:</strong></p><ul>';
                                $.each(response.data.errors.slice(0, 10), function(i, err) {
                                    errorHtml += '<li>' + err + '</li>';
                                });
                                errorHtml += '</ul></div>';
                            }
                            if (response.data.trace) {
                                errorHtml += '<details><summary>Stack Trace</summary><pre style="font-size:10px;overflow:auto;max-height:200px;">' + response.data.trace + '</pre></details>';
                            }
                            errorHtml += '</div>';
                            $results.find('.wbim-results-summary').html(errorHtml);
                            $results.find('.wbim-results-errors').empty();
                        }
                    }, 500);
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    $progress.hide();
                    $results.show();

                    var errorHtml = '<div class="notice notice-error inline">';
                    errorHtml += '<p><strong>' + (wbimStock.strings.error || 'დაფიქსირდა შეცდომა') + '</strong></p>';
                    errorHtml += '<p class="wbim-debug-info"><small><strong>Status:</strong> ' + status + '</small></p>';
                    errorHtml += '<p class="wbim-debug-info"><small><strong>Error:</strong> ' + error + '</small></p>';
                    if (xhr.responseText) {
                        var responsePreview = xhr.responseText.substring(0, 1000);
                        errorHtml += '<details><summary>Server Response</summary><pre style="font-size:10px;overflow:auto;max-height:200px;">' + $('<div>').text(responsePreview).html() + '</pre></details>';
                    }
                    errorHtml += '</div>';

                    $results.find('.wbim-results-summary').html(errorHtml);
                    $results.find('.wbim-results-errors').empty();
                }
            });
        },

        /**
         * Poll for background sync status
         *
         * @param {string} taskId Task ID to check
         */
        pollSyncStatus: function(taskId) {
            var self = this;

            $.ajax({
                url: wbimStock.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbim_check_sync_status',
                    nonce: wbimStock.nonce,
                    task_id: taskId
                },
                success: function(response) {
                    var $statusContainer = $('.wbim-background-sync-status[data-task-id="' + taskId + '"]');

                    if (!$statusContainer.length) {
                        return;
                    }

                    if (response.success) {
                        var status = response.data;

                        if (status.status === 'completed') {
                            // Update UI to show completion
                            var completedHtml = '<div class="wbim-sync-status-header wbim-sync-completed">';
                            completedHtml += '<span class="dashicons dashicons-yes-alt"></span>';
                            completedHtml += '<span class="wbim-sync-status-title">სინქრონიზაცია დასრულდა!</span>';
                            completedHtml += '</div>';

                            if (status.results) {
                                completedHtml += '<p class="wbim-sync-status-text"><strong>' + status.results.marked_out_of_stock + '</strong> პროდუქტი მოინიშნა "არ არის მარაგში"</p>';
                                if (status.results.errors_count > 0) {
                                    completedHtml += '<p class="wbim-sync-status-text wbim-sync-errors">' + status.results.errors_count + ' შეცდომა</p>';
                                }
                            }

                            completedHtml += '<p class="wbim-sync-status-info"><small>დასრულდა: ' + status.completed_at + '</small></p>';

                            $statusContainer.html(completedHtml).addClass('wbim-sync-done');
                        } else if (status.status === 'processing') {
                            // Update status text and continue polling
                            $statusContainer.find('.wbim-sync-status-title').text('სინქრონიზაცია მიმდინარეობს...');

                            // Poll again after 5 seconds
                            setTimeout(function() {
                                self.pollSyncStatus(taskId);
                            }, 5000);
                        } else if (status.status === 'pending') {
                            // Still waiting to start, continue polling
                            $statusContainer.find('.wbim-sync-status-title').text('სინქრონიზაცია მალე დაიწყება...');

                            // Poll again after 3 seconds
                            setTimeout(function() {
                                self.pollSyncStatus(taskId);
                            }, 3000);
                        }
                    } else {
                        // Error - show message but stop polling
                        $statusContainer.find('.wbim-sync-status-title').text('სტატუსი ვერ მოიძებნა');
                        $statusContainer.find('.wbim-sync-spinner').removeClass('wbim-sync-spinner').addClass('dashicons-warning');
                    }
                },
                error: function() {
                    // Network error - try again after longer delay
                    setTimeout(function() {
                        self.pollSyncStatus(taskId);
                    }, 10000);
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
