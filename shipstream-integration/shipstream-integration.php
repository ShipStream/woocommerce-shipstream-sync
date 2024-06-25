<?php
/**
 * Plugin Name: ShipStream Integration
 * Description: Integrates ShipStream with WooCommerce for order management.
 * Version: 1.0.0
 * Author: Praveen Kumar
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the main class.
require_once plugin_dir_path(__FILE__) . 'includes/class-shipstream-integration.php';

// Initialize the plugin.
function shipstream_integration_init() {
    new ShipStream_Integration();
}
add_action('plugins_loaded', 'shipstream_integration_init');
