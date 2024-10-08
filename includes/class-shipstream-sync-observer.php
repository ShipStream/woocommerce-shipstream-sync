<?php

class ShipStream_Sync_Observer {
    
    protected $_deduplicate = [];

    public function __construct() {
        add_action('woocommerce_order_status_changed', [$this, 'salesOrderSaveAfter'], 10, 4);
        add_action('shipstream_ready_to_ship', [$this, 'updateReadyToShip'], 10, 2);
    }

    /**
     * Handle order status change events
     *
     * @param int $order_id Order ID
     * @param string $old_status Previous order status
     * @param string $new_status New order status
     * @param WC_Order $order Order object
     */
    public function salesOrderSaveAfter($order_id, $old_status, $new_status, $order) {
        ShipStream_Sync_Helper::logMessage("Order status changed for $order_id: $old_status -> $new_status");

        if ($new_status === 'ss-ready-to-ship') {
            // Do not sync if the order contains only virtual or downloadable products
            if ($this->isOrderVirtual($order)) {
                $order->update_status('wc-completed', __('Changed order status to "Completed" as the order is virtual.'));
            } else if ($this->isRealtimeSyncEnabled()) {
                // If real-time sync is enabled and the new status is "Ready to Ship", sync the order with ShipStream
                try {
                    ShipStream_Sync_Helper::callback('syncOrder', ['order_number' => $order->get_order_number()]);
                } catch (Throwable $e) {
                    // Handle potential errors gracefully
                    ShipStream_Sync_Helper::logError($e->getMessage());
                }
            }
        }

        if ($new_status === 'processing' && $old_status !== 'ss-ready-to-ship' && $this->isAutoFulfillOrders()) {
            // Update the order status to 'wc-ss-ready-to-ship'
            try {
                do_action('shipstream_ready_to_ship', $order, __('Auto-updated to Ready to Ship.', 'woocommerce-shipstream-sync'));
            } catch (Throwable $e) {
                // Handle potential errors gracefully
                ShipStream_Sync_Helper::logError($e->getMessage());
            }
        }
    }

    public function updateReadyToShip(WC_Order $order, ?string $comment) {
        if (!$this->isOrderVirtual($order)) {
            $order->update_status('wc-ss-ready-to-ship', $comment);
            $orderNumber = $order->get_order_number();
            if ($this->isRealtimeSyncEnabled() && ! isset($this->_deduplicate[(string)$orderNumber])) {
                ShipStream_Sync_Helper::callback('syncOrder', ['order_number' => $orderNumber]);
                $this->_deduplicate[(string)$orderNumber];
            }
        }
    }

    /**
     * Check if an order contains only virtual or downloadable products
     *
     * @param WC_Order $order Order object
     * @return bool True if the order contains only virtual or downloadable products, otherwise false
     */
    protected function isOrderVirtual($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product->is_virtual() && !$product->is_downloadable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if real-time sync is enabled
     *
     * @return bool True if real-time sync is enabled, otherwise false
     */
    protected function isRealtimeSyncEnabled() {
        return get_option('enable_real_time_order_sync', 'no') === 'yes';
    }

    /**
     * Check if auto-fulfill orders is enabled
     *
     * @return bool True if auto-fulfill orders is enabled, otherwise false
     */
    protected function isAutoFulfillOrders() {
        return get_option('enable_auto_fulfill_orders', 'no') === 'yes';
    }

}