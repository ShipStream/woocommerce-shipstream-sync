# ShipStream Sync

## Description

This plugin syncs data from the ShipStream WMS server to WooCommerce.

## Features

- Syncs inventory for matching SKUs from ShipStream to WooCommerce.
- Adds a cron job to perform a full inventory pull at 02:00 every day (with a random sleep time).
- Keeps inventory in sync in real-time using plugin events.
- Updates order status when ShipStream shipments are completed.
- Adds tracking numbers to WooCommerce using the Shipment Tracking extension.
- Updates WooCommerce order with order_item_id, SKU, quantity, package_data, lot_data.
- Marks WooCommerce order as "completed" if all ShipStream order items are shipped.
- Allows resubmission of canceled ShipStream orders.

## Installation

1. Download the plugin and extract the contents to a folder named `shipstream-sync`.
2. Upload the `shipstream-sync` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Configuration

1. Set up your ShipStream API credentials in the plugin settings.
2. Ensure that the Shipment Tracking extension is installed and activated in WooCommerce.

## Usage

1. The plugin will automatically sync inventory at 02:00 every day.
2. Real-time inventory sync is handled through plugin events.
3. Orders will be updated with tracking numbers and marked as completed when ShipStream shipments are done.

## License

This plugin is licensed under the GPLv2 or later.

## Author

Praveen Kumar
