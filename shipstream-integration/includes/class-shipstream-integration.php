<?php

class ShipStream_Integration {

    // Constructor: Initializes the plugin by defining constants and initializing hooks
    public function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    // Define constants used in the plugin
    private function define_constants() {
        define('SHIPSTREAM_INTEGRATION_VERSION', '1.0.0');
    }

    // Initialize hooks for various actions and filters
    private function init_hooks() {
        // Add the settings page to the admin menu
        add_action('admin_menu', array($this, 'admin_menu'));

        // Register plugin settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    // Add a settings page to the WordPress admin menu
    public function admin_menu() {
        add_menu_page(
            'ShipStream Settings',  // Page title
            'Woocommerce Extension', // Menu title
            'manage_options',       // Capability required to access the page
            'shipstream-settings',  // Menu slug
            array($this, 'settings_page') // Callback function to render the settings page
        );
    }

    // Register settings for the plugin
    public function register_settings() {
        register_setting('woocommerce_settings_group', 'woocommerce_api_url');
        register_setting('woocommerce_settings_group', 'woocommerce_consumer_key');
        register_setting('woocommerce_settings_group', 'woocommerce_consumer_secret');
    }

    // Render the settings page
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce Plugin Connection</h1>
            <form method="post" action="options.php">
                <?php
                // Display settings fields and sections
                settings_fields('woocommerce_settings_group'); 
                do_settings_sections('shipstream-settings'); 
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">WooCommerce API URL</th>
                        <td><input type="text" name="woocommerce_api_url" value="<?php echo esc_attr(get_option('woocommerce_api_url')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WooCommerce Consumer Key</th>
                        <td><input type="text" name="woocommerce_consumer_key" value="<?php echo esc_attr(get_option('woocommerce_consumer_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WooCommerce Consumer Secret</th>
                        <td><input type="text" name="woocommerce_consumer_secret" value="<?php echo esc_attr(get_option('woocommerce_consumer_secret')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); // Display the submit button ?>
            </form>
        </div>
        <?php
    }

}

?>
