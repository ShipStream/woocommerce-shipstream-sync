=== ShipStream Sync ===
Contributors: colinshipstream
Tags: wms, oms, fulfillment, woocommerce, tracking, inventory, shipping
Requires at least: 6.3
Tested up to: 6.6
Stable tag: 1.0.4
Requires PHP: 7.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for fast and efficient sync with the ShipStream WMS/OMS native Merchant Integration.

== Description ==

The WooCommerce ShipStream Sync plugin is a powerful companion tool designed to seamlessly integrate WooCommerce with
the ShipStream Warehouse Management System (WMS) and Order Management System (OMS). This plugin ensures fast and
efficient synchronization of orders, inventory, and fulfillment processes between your WooCommerce store and ShipStream,
providing a hassle-free experience and streamlined workflow for merchants.

### Key Features

- **Real-Time Order Sync:** Automatically sync orders from WooCommerce to ShipStream in real-time, ensuring that your warehouse operations are always up-to-date.
- **Automatic Fulfillment:** Optionally enable automatic order fulfillment so no manual intervention is required to reduce your workload and speed up the order processing time.
- **Order Status Updates:** Keep your WooCommerce store updated with the latest order statuses from ShipStream, providing accurate information to your customers.
- **Custom Order Notes:** Add custom notes to orders witch advanced data like lot numbers, serial numbers, etc.
- **Error Handling:** Provides detailed error messages and logging to help you troubleshoot any issues that arise during the synchronization process.

### Installation

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-shipstream-sync` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress and update the default configuration in the Woocommerce settings at the new ShipStream Sync tab.
3. Complete the connection via ShipStream by adding a Merchant Integration for Woocommerce. Authentication is handled securely so there are no API keys to manage.

### Usage

Once installed and the ShipStream integration has been activated, the plugin will automatically start syncing orders and
inventory between WooCommerce and ShipStream.

Just set the order status to Ready to Ship or let the automatic fulfillment feature handle it for you.
You can manage the plugin settings and view synchronization logs from the WooCommerce settings page under the ShipStream Sync tab.

### Support

For support and troubleshooting, please contact your 3PL or [ShipStream Support](mailto:help@shipstream.io), or open
an issue on the [GitHub project page](https://github.com/ShipStream/woocommerce-shipstream-sync).

### Changelog

#### 1.0.4

- Fix authentication to piggy-back WooCommerce REST API authentication properly.

#### 1.0.3

- Added billing address information to the order sync, in particular so that the billing phone number can be used as a fallback for the shipping phone number.

#### 1.0.2

- Added support for recording tracking number information to the Advanced Shipment Tracking extension by Zorem.

#### 1.0.1
- Initial release.
