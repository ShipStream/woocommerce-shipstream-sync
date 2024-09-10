<?php

class ShipStream_Sync_Observer {
    
    public function __construct() {
        add_action('woocommerce_order_status_changed', [$this, 'salesOrderSaveAfter'], 10, 4);
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
        
        if ($new_status === 'ss-ready-to-ship') {
            // Do not sync if the order contains only virtual or downloadable products
            if ($this->isOrderVirtual($order)) {
                $order->update_status('wc-completed', __('Changed order status to "Completed" as the order is virtual.'));
            } else if ($this->isRealtimeSyncEnabled()) {
                // If real-time sync is enabled and the new status is "Ready to Ship", sync the order with ShipStream
                try {
                    ShipStream_Sync_Helper_Api::callback('syncOrders', ['order_number' => $order->get_order_number()]);
                } catch (Throwable $e) {
                    // Handle potential errors gracefully
                    error_log($e->getMessage());
                }
            }
        }

        if ($new_status === 'wc-processing' && $this->isAutoFulfillOrders()) {
            // Check if the order is not virtual
            if (!$this->isOrderVirtual($order)) {
                // Update the order status to 'ss-ready-to-ship'
                $order->update_status('ss-ready-to-ship', __('Auto-updated to Ready to Ship.'));
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