# ShipStream Sync for WooCommerce

## Overview

The WooCommece ShipStream Sync plugin is a lightweight companion plugin for Wordpress and WooCommerce designed to 
facilitate real-time and efficient synchronization of inventory, orders, and tracking information between ShipStream and your WooCommerce store.

Please note that this extension does not provide any functionality alone; the ShipStream Merchant Integration subscription must
be properly configured for this plugin to do anything. It **is safe** to install this plugin **before** the Merchant Integration is configured.

## Features

This WooCommerce plugin adds the following functionalities to your WooCommerce store:

- **Settings Integration:**
  - Adds a new settings tab under `WooCommerce > Settings > ShipStream Sync` with options for:
    - **Real-Time Order Sync:** Activate real-time synchronization of orders between WooCommerce and ShipStream (in addition to the every 10 minutes).
    - **Auto-Fulfill Orders:** Automatically advance orders from Processing to Ready to Ship so they are ready to be imported by ShipStream.

- **New Order Statuses:**
  - Introduces three new order statuses to better track the lifecycle of orders:
    - **Ready to Ship:** Indicates that the order is paid for and ready for fulfillment.
    - **Failed to Submit:** Indicates an error occurred while trying to submit the order to ShipStream.
    - **Submitted:** Indicates that the order has been successfully submitted to ShipStream and is awaiting tracking information.

- **API Endpoints:**
  - Adds several API endpoints for interacting with ShipStream. These use the same authentication mechanism as other authenticated Read/Write WooCommerce API endpoints.
    - `shipstream/v1/info`
    - `shipstream/v1/set_config`
    - `shipstream/v1/inventory/sync`
    - `shipstream/v1/inventory/adjust`
    - `shipstream/v1/order/info`
    - `shipstream/v1/order/complete_with_tracking`
    - `shipstream/v1/order/list`
    - `shipstream/v1/order/addComment`
    - `shipstream/v1/order/status_update`

- **Event Observer:**
  - Adds an action observer for `woocommerce_order_status_changed` to trigger synchronization of orders when the status is updated if Real-Time Order Sync is enabled and advancement to Ready to Ship status if Auto-Fulfill Orders is enabled.

- **Configuration Storage:**
  - Stores the ShipStream remote callback URL in the `options` table to facilitate two-way communication.

## Purpose of New Statuses

The addition of new order statuses provides better visibility into the order processing workflow, ensuring that orders are correctly managed and tracked. The new statuses help distinguish between different stages of order processing and give hte shop owner greater control over when orders
are submitted to ShipStream:

- **Ready to Ship:** This status can be set manually or programmatically (through custom code) to indicate that an order is ready for shipment.
  
- **Failed to Submit:** This status signals that there was an issue when attempting to submit the order to the warehouse.

- **Submitted:** This status indicates that the order has been successfully submitted to the warehouse, but not yet fully shipped. The order status will automatically advance to "Complete" once the shipment is finalized.

If configured to sync orders with the "Ready to Ship" status, the order status progression will follow this workflow:

![Status State Diagram](https://raw.githubusercontent.com/ShipStream/openmage-sync/master/shipstream-sync.png)

Custom workflows can be implemented by disabling **Auto-Fulfill Orders** and implementing your own procedure for updating the status to **Ready to Ship**, whether it be manual or automated.

## Installation

1. Download the **woocommerce-shipstream-sync.zip** file from the [Releases](https://github.com/ShipStream/woocommerce-shipstream-sync/releases) page.
2. Upload it to your Wordpress site by clicking Plugins > Add New Plugin > Upload Plugin or use your preferred method of extracting the plugin
files to your `wp-content/plugins` directory.

## Setup

After installation, complete the following steps to configure the extension:

1. **Activate the Plugin:**
   - Log in to your WordPress admin panel.
   - Navigate to `Plugins` and activate the ShipStream Sync Extension by clicking "Activate".

2. **Configure the plugin:**
   - Both Real-Time Order Sync and Auto-Fulfill Orders are enabled by default. Change if needed by navigating to
     WooCommerce > Settings > ShipStream Sync.
   
## ShipStream Setup

The ShipStream plugin requires the following information:

- **WooCommerce Store URL:** The base URL of your WooCommerce site.

Click **Save** and then click **Connect WooCommerce Store** to begin the authentication. Your password will not be shared with ShipStream in this process.

## Customization

Feel free to modify the source code to tailor the extension to your specific needs. For example, if you have multiple fulfillment providers, you may want to add metadata to orders to control automatic import behavior.

## Support

For assistance, please contact us at [help@shipstream.io](mailto:help@shipstream.io). We're here to help with any questions or issues you may have.