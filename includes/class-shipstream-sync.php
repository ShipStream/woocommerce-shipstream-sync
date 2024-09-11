<?php

class ShipStream_Sync {

    public static function init() {
        // Add custom order statuses.
        add_filter('wc_order_statuses', array(__CLASS__, 'add_custom_order_statuses'));
        add_action('init', array(__CLASS__, 'register_custom_order_statuses'));
        add_filter('woocommerce_get_order_status_labels', array(__CLASS__, 'get_order_status_labels'), 11, 2);
        add_filter('bulk_actions-woocommerce_page_wc-orders', array(__CLASS__, 'define_bulk_actions') );
        add_action('admin_head', array(__CLASS__, 'custom_head'), 11);

        // Add WooCommerce settings tab.
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_shipstream_sync', array(__CLASS__, 'settings_tab'));
        add_action('woocommerce_update_options_shipstream_sync', array(__CLASS__, 'update_settings'));
    }

    public static function add_custom_order_statuses($order_statuses) {
        $order_statuses['wc-ss-ready-to-ship'] = 'Ready to Ship';
        $order_statuses['wc-ss-failed'] = 'Failed to Submit';
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

        register_post_status('wc-ss-failed', array(
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

    public static function get_order_status_labels($status_names, WC_Order $order) {
        $status_names['ss-ready-to-ship'] = __( 'Order is ready to be imported automatically by ShipStream.', 'woocommerce-shipstream-sync');
        $status_names['ss-failed'] = __( 'An error occurred importing the order into ShipStream and requires manual intervention.', 'woocommerce-shipstream-sync');
        $status_names['ss-submitted'] = __( 'The order was successfully imported into ShipStream.', 'woocommerce-shipstream-sync');

        return $status_names;
    }

    public static function define_bulk_actions(array $actions) {
        // WooCommerce automatically handles the action for mark_{status} actions
        $actions['mark_ss-ready-to-ship'] = __('Change status to Ready to Ship', 'woocommerce-shipstream-sync');
       
        return $actions;
    }

    public static function custom_head() {
        echo <<<HTML
<style>
.order-status.status-ss-ready-to-ship {
    background: #293b35;
    color: #9ff2af;
}
.order-status.status-ss-failed {
    background: #e1c8c8;
    color: #2e4453;
}
.order-status.status-ss-submitted {
    background: #3c4144;
    color: #9fd0f2;
}
</style>
HTML;
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
            'section_end' => array(
                'type' => 'sectionend',
                'id'   => 'shipstream_sync_section_end'
            )
        );
        return $settings;
    }
}