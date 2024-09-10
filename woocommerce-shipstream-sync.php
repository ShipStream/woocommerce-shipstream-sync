<?php
/*
Plugin Name: WooCommerce ShipStream Sync
Description: Companion plugin to sync WooCommerce with the ShipStream WMS/OMS.
Version: 1.0.0
Author: ShipStream, LLC
Author URI: https://shipstream.io
Text Domain: woocommerce-shipstream-sync
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include required files.
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shipstream-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shipstream-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shipstream-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shipstream-sync-observer.php';

// Initialize the helper for logging
ShipStream_Sync_Helper::init();

// Initialize the observer.
new ShipStream_Sync_Observer();

// Initialize the plugin.
add_action('plugins_loaded', 'ShipStream_Sync::init');

// Initialize the ShipStream API
add_action('rest_api_init', 'ShipStream_API::init');

// Schedule activation and deactivation hooks.
register_activation_hook(__FILE__, 'shipstream_sync_activate');
register_deactivation_hook(__FILE__, 'shipstream_sync_deactivate');

/**
 * Activation hook function.
 */
function shipstream_sync_activate() {
    update_option('enable_real_time_order_sync', 'yes');
    update_option('enable_auto_fulfill_orders', 'yes');
    update_option('send_new_shipment_email', 'yes');
    ShipStream_Sync_Helper::logMessage('Activated plugin');
}

/**
 * Deactivation hook function.
 */
function shipstream_sync_deactivate() {
    if (ShipStream_Sync_Helper::isConfigured()) {
        try {
            ShipStream_Sync_Helper::callback('deactivatePlugin');
        } catch (Exception $e) {
            error_log('Error notifying ShipStream of plugin deactivation: ' . $e->getMessage());
        }
    }
    ShipStream_Sync_Helper::logMessage('Deactivated plugin');
}