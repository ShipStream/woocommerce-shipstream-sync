# ShipStream Integration

## Description
The ShipStream Integration plugin seamlessly connects ShipStream with WooCommerce, enhancing order management by automating order processing and status updates.

## Installation

### Manual Installation

1. **Upload Plugin Files**: Upload the plugin files to the `/wp-content/plugins/shipstream-integration` directory of your WordPress installation.

### WordPress Dashboard Installation

1. **Navigate to Plugins**: In your WordPress admin panel, go to **Plugins > Add New**.
2. **Search for Plugin**: Search for "ShipStream Integration".
3. **Install Plugin**: Click on the "Install Now" button to install the plugin.
4. **Activate Plugin**: After installation, click on the "Activate" button to activate the ShipStream Integration plugin.

## Configuration

1. **Navigate to Settings**: Go to **Settings > ShipStream** in the WordPress admin panel.
2. **Configure Settings**:
   - **WooCommerce API URL**: Enter the API URL for your WooCommerce store (e.g., `https://yourstore.com/wp-json/wc/v3`).
   - **WooCommerce API Key**: Enter the API key generated from your WooCommerce store settings under the API section.

## Features

The ShipStream Integration plugin offers the following features:

- **Automated Order Processing**: Automatically processes orders received from WooCommerce and updates their status in ShipStream.
- **Error Handling**: Logs errors and retries failed order updates to ensure data consistency and reliability.
