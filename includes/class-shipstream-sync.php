<?php

class ShipStream_Sync {

    public static function init() {
        // Add custom order statuses.
        add_filter('wc_order_statuses', array(__CLASS__, 'add_custom_order_statuses'));
        add_action('init', array(__CLASS__, 'register_custom_order_statuses'));

        // Add WooCommerce settings tab.
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_shipstream_sync', array(__CLASS__, 'settings_tab'));
        add_action('woocommerce_update_options_shipstream_sync', array(__CLASS__, 'update_settings'));
    }

    public static function add_custom_order_statuses($order_statuses) {
        $order_statuses['wc-ss-ready-to-ship'] = 'Ready to Ship';
        $order_statuses['wc-ss-failed-to-submit'] = 'Failed to Submit';
        $order_statuses['wc-ss-submitted'] = 'Submitted';
        return $order_statuses;
    }

    public static function register_custom_order_statuses() {
        register_post_status('wc-ss-ready-to-ship', array(
            'label' => __('Ready to Ship',  'woocommerce-shipstream-sync'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('Ready to Ship <span class="count">(%s)</span>', 'Ready to Ship <span class="count">(%s)</span>')
        ));

        register_post_status('wc-ss-failed-to-submit', array(
            'label' => __('Failed to Submit',  'woocommerce-shipstream-sync'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('Failed to Submit <span class="count">(%s)</span>', 'Failed to Submit <span class="count">(%s)</span>')
        ));

        register_post_status('wc-ss-submitted', array(
            'label' => __('Submitted',  'woocommerce-shipstream-sync'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop('Submitted <span class="count">(%s)</span>', 'Submitted <span class="count">(%s)</span>')
        ));
    }

    // Add WooCommerce settings tab
    public static function add_settings_tab($settings_tabs) {
        $settings_tabs['shipstream_sync'] = __('ShipStream Sync', 'woocommerce-shipstream-sync');
        return $settings_tabs;
    }

    public static function settings_tab() {
        woocommerce_admin_fields(self::get_settings());
    }

    public static function update_settings() {
        woocommerce_update_options(self::get_settings());
    }

    public static function get_settings() {
        $settings = array(
            'section_title' => array(
                'name'     => __('ShipStream Sync Settings', 'woocommerce-shipstream-sync'),
                'type'     => 'title',
                'desc'     => __('The ShipStream Sync plugin provides additional functionality needed for ShipStream to sync orders, tracking and inventory data to and from your WooCommerce store.', 'woocommerce-shipstream-sync'),
                'id'       => 'shipstream_sync'
            ),
            'enable_real_time_order_sync' => array(
                'name' => __('Real-Time Order Sync', 'woocommerce-shipstream-sync'),
                'type' => 'checkbox',
                'desc' => __('Immediately notify ShipStream when an order changes to Ready to Ship.', 'woocommerce-shipstream-sync'),
                'id'   => 'enable_real_time_order_sync'
            ),
            'enable_auto_fulfill_orders' => array(
                'name' => __('Auto-Fulfill Orders', 'woocommerce-shipstream-sync'),
                'type' => 'checkbox',
                'desc' => __('Automatically advance orders to Ready to Ship status when they are ready for Processing.', 'woocommerce-shipstream-sync'),
                'id'   => 'enable_auto_fulfill_orders'
            ),
            'send_new_shipment_email' => array(
                'name' => __('Send New Shipment Email', 'woocommerce-shipstream-sync'),
                'type' => 'checkbox',
                'desc' => __('Send an email to the customer when a new shipment is created by ShipStream.', 'woocommerce-shipstream-sync'),
                'id'   => 'send_new_shipment_email'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id'   => 'shipstream_sync_section_end'
            )
        );
        return $settings;
    }
}