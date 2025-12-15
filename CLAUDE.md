# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

WooCommerce Branch Inventory Manager (WBIM) is a WordPress plugin for managing inventory across multiple branches/locations in WooCommerce stores. It tracks stock per branch, handles inter-branch transfers, and integrates with WooCommerce checkout and orders.

## Architecture

### Entry Point & Initialization
- `woo-branch-inventory-manager.php` - Main plugin file, defines constants (`WBIM_VERSION`, `WBIM_PLUGIN_DIR`, `WBIM_PLUGIN_URL`), initializes singleton `WBIM` class
- `includes/class-wbim-autoloader.php` - PSR-0 style autoloader for `WBIM_*` classes

### Database Tables (created in `class-wbim-activator.php`)
- `wbim_branches` - Store locations with coordinates, manager assignments, `is_default` flag
- `wbim_stock` - Per-product, per-branch inventory levels with low stock thresholds, `stock_status` field
- `wbim_transfers` - Inter-branch stock transfer records with status workflow
- `wbim_transfer_items` - Line items for transfers
- `wbim_stock_log` - Audit trail for all stock changes
- `wbim_order_allocation` - Maps order items to branches for fulfillment

### Core Model Classes (in `includes/models/`)
- `WBIM_Branch` - Branch CRUD operations
- `WBIM_Stock` - Stock management with `get()`, `set()`, `adjust()` methods; auto-syncs with WooCommerce stock
- `WBIM_Transfer` - Transfer workflow with status state machine (draft → pending → in_transit → completed)
- `WBIM_Stock_Log` - Stock change audit logging
- `WBIM_Order_Allocation` - Order-to-branch assignment tracking

### Order & Checkout Flow
- `includes/class-wbim-order-handler.php` - Handles stock deduction on order processing, returns on cancellation/refund
- `public/class-wbim-checkout.php` - Branch selector UI at checkout, validates stock availability
- Branch auto-selection methods: nearest (geolocation), most_stock, default, first_available

### Admin Classes (in `admin/`)
- `WBIM_Admin` - Main admin initialization, enqueues assets
- `WBIM_Admin_Menus` - WordPress admin menu registration
- `WBIM_Admin_Stock` - Stock management UI and AJAX handlers
- `WBIM_Admin_Transfers` - Transfer management interface
- `WBIM_Admin_Reports` - Reporting functionality
- `WBIM_Admin_Dashboard` - Admin dashboard widgets

### REST API (`api/class-wbim-rest-api.php`)
Namespace: `wbim/v1`
- `/branches` - Branch CRUD
- `/stock` - Stock queries and updates
- `/stock/product/{id}` - Get product stock across branches
- `/stock/branch/{id}` - Get branch stock
- `/stock/adjust` - Stock adjustments
- `/transfers` - Transfer CRUD with status updates
- `/transfers/{id}/status` - Update transfer status
- `/reports/stock`, `/reports/sales`, `/reports/low-stock` - Reporting endpoints

### Key Integration Points
- WooCommerce HPOS (High-Performance Order Storage) compatible via `declare_hpos_compatibility()`
- Stock sync: WBIM stock changes automatically update WooCommerce product stock via `WBIM_Stock::sync_wc_stock()`
- Order hooks: Stock deducted on processing/completed/paid (configurable via `deduct_stock_on` setting), returned on cancel/refund

## Development Patterns

### Stock Operations
Always use `WBIM_Stock::adjust()` or `WBIM_Stock::set()` for stock changes - they handle logging and WC sync:
```php
// Adjust (add/subtract) - returns true or WP_Error
WBIM_Stock::adjust($product_id, $variation_id, $branch_id, $quantity_change, $action_type, $reference_id, $note);

// Set absolute value with additional fields
WBIM_Stock::set($product_id, $variation_id, $branch_id, [
    'quantity'            => $qty,
    'stock_status'        => 'instock', // instock, low, outofstock, preorder
    'low_stock_threshold' => 5,
    'shelf_location'      => 'A1-B2',
    'note'                => 'Import update',
]);

// Get stock for product at branch
$stock = WBIM_Stock::get($product_id, $branch_id, $variation_id);

// Get total stock across all branches
$total = WBIM_Stock::get_total($product_id, $variation_id);

// Sync WBIM totals back to WooCommerce product stock
WBIM_Stock::sync_wc_stock($product_id, $variation_id);
```

### Transfer Status Workflow
Valid transitions defined in `WBIM_Transfer::get_valid_transitions()`:
- draft → pending, cancelled
- pending → in_transit, cancelled
- in_transit → completed, cancelled
- completed/cancelled → (terminal states)

Stock deducted from source on draft→pending, added to destination on in_transit→completed.

```php
// Create transfer
$transfer_id = WBIM_Transfer::create([
    'source_branch_id'      => 1,
    'destination_branch_id' => 2,
    'notes'                 => 'Monthly restock',
    'items'                 => [
        ['product_id' => 123, 'variation_id' => 0, 'quantity' => 10],
    ],
]);

// Update status (handles stock automatically)
WBIM_Transfer::update_status($transfer_id, 'pending');
```

### Settings Access
Use `WBIM_Utils::get_setting($key, $default)` for plugin settings stored in `wbim_settings` option.

Key settings: `default_branch`, `auto_select_method`, `low_stock_threshold`, `deduct_stock_on`, `return_stock_on`, `google_maps_api_key`

### CSV/JSON Import
The `WBIM_CSV_Handler` class handles stock imports:
```php
// CSV format requires: sku, quantity columns (branch_id if not using import_with_branch)
// JSON format supports Column2/Column5 (Excel export) or sku/quantity fields

// Import with specific branch
WBIM_CSV_Handler::import_with_branch($file_path, $branch_id, [
    'update_existing'          => true,
    'skip_empty'               => true,
    'distribute_to_variations' => false, // distributes parent SKU qty evenly to variations
]);
```

### Permissions
Custom capabilities: `wbim_manage_branches`, `wbim_manage_stock`, `wbim_manage_transfers`, `wbim_view_reports`, `wbim_manage_settings`, `wbim_view_branch_stock`

### Action Hooks
```php
do_action('wbim_stock_updated', $product_id, $variation_id, $branch_id, $new_quantity);
do_action('wbim_transfer_created', $transfer_id, $data);
do_action('wbim_transfer_status_changed', $id, $old_status, $new_status, $user_id);
do_action('wbim_transfer_deleted', $id);
```

## File Locations

- Admin views: `admin/views/`
- Public views: `public/views/`
- Email templates: `templates/emails/`
- CSS: `admin/css/`, `public/css/`
- JS: `admin/js/`, `public/js/`

## Text Domain

Use `wbim` for all translatable strings. Many UI strings are in Georgian (ქართული).

## Database Migrations

Database version tracked in `wbim_db_version` option. Migrations run via `WBIM_Activator::maybe_upgrade_database()` on `plugins_loaded`. Current version: 1.3.0.
