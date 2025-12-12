=== WooCommerce Branch Inventory Manager ===
Contributors: yourname
Tags: woocommerce, inventory, branches, stock, multi-location
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
WC requires at least: 5.0
WC tested up to: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage inventory across multiple branches/locations for WooCommerce stores.

== Description ==

WooCommerce Branch Inventory Manager is a powerful plugin that allows you to manage inventory across multiple physical locations or branches. Perfect for businesses with multiple stores, warehouses, or distribution centers.

= Features =

**Branch Management**
* Create and manage unlimited branches
* Set branch details including address, phone, email
* Assign managers to branches
* Activate/deactivate branches

**Stock Management**
* Track inventory by product and branch
* Set stock levels per branch
* Bulk stock import via CSV
* Stock adjustment with logging
* Low stock threshold per product or global

**Transfer System**
* Create transfers between branches
* Multi-step workflow (Draft -> Pending -> In Transit -> Completed)
* Track transfer history
* PDF transfer documents
* Email notifications for transfers

**Reports**
* Stock report by branch/category
* Sales report with charts
* Transfer statistics
* Low stock alerts
* Stock movement history
* Export to CSV and PDF

**User Roles**
* Branch Manager role
* Custom capabilities
* Assign users to specific branches
* Role-based access control

**Checkout Integration**
* Customer branch selection at checkout
* Multiple selector types (dropdown, radio, map)
* Auto-select based on stock or location
* Branch info in order details

**REST API**
* Full CRUD for branches, stock, transfers
* Report endpoints
* WooCommerce REST API authentication

**Localization**
* Translation ready
* Georgian (ka_GE) included

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

= Compatibility =

* WooCommerce HPOS (High-Performance Order Storage)
* WooCommerce 8.x
* Popular WordPress themes

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woocommerce-branch-inventory-manager`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to "ფილიალები" (Branches) in the admin menu to configure

== Frequently Asked Questions ==

= Can I import existing stock data? =

Yes, go to Stock > Import and upload a CSV file with product_id, branch_id, and quantity columns.

= How do transfers work? =

1. Create a new transfer and select source/destination branches
2. Add products and quantities
3. Send the transfer (status: In Transit)
4. Receive at destination branch (status: Completed)

= Can customers choose which branch to order from? =

Yes, enable "Branch Selection at Checkout" in Settings > Checkout tab.

= Is the REST API secure? =

Yes, all API endpoints require WooCommerce REST API authentication.

== Screenshots ==

1. Dashboard with statistics and charts
2. Branch list management
3. Stock management interface
4. Transfer creation
5. Reports with export options
6. Settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Branch management
* Stock tracking per branch
* Transfer system
* Reports and analytics
* REST API
* User roles and permissions
* Checkout integration
* Georgian localization

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== API Documentation ==

The plugin provides a REST API at `/wp-json/wbim/v1/`

**Authentication:**
Use WooCommerce REST API keys (Consumer Key and Consumer Secret).

**Endpoints:**

= Branches =
* `GET /branches` - List all branches
* `GET /branches/{id}` - Get single branch
* `POST /branches` - Create branch
* `PUT /branches/{id}` - Update branch
* `DELETE /branches/{id}` - Delete branch

= Stock =
* `GET /stock` - List stock levels
* `GET /stock/product/{id}` - Stock by product
* `GET /stock/branch/{id}` - Stock by branch
* `POST /stock` - Set stock level
* `PUT /stock/adjust` - Adjust stock

= Transfers =
* `GET /transfers` - List transfers
* `GET /transfers/{id}` - Get single transfer
* `POST /transfers` - Create transfer
* `PUT /transfers/{id}/status` - Update status

= Reports =
* `GET /reports/stock` - Stock report
* `GET /reports/sales` - Sales report
* `GET /reports/low-stock` - Low stock items
