<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ShipStream_Cron {

    public static function clean_log() {
        if (file_exists(ShipStream_Sync_Helper::$logFile)
            && is_writable(ShipStream_Sync_Helper::$logFile)
        && '/dev/stderr' !== ShipStream_Sync_Helper::$logFile
        ) {
            file_put_contents(ShipStream_Sync_Helper::$logFile, '');
            ShipStream_Sync_Helper::logMessage('Cleaned log file.');
        }
    }

    /**
     * Perform a full inventory synchronization.
     *
     * @param bool $sleep Whether to sleep for a random time to avoid server stampede.
     */
    public static function full_inventory_sync($sleep = true) {
        if (!ShipStream_Sync_Helper::isConfigured()) {
            ShipStream_Sync_Helper::logError('Cannot sync inventory while not configured.');
            throw new Exception('ShipStream Sync is not properly registered.');
        }

        if ($sleep) {
            sleep(rand(0, 300)); // Avoid stampeding the server
        }

        ShipStream_Sync_Helper::logMessage("Starting inventory sync...");

        global $wpdb;

        $source = ShipStream_Sync_Helper::callback('inventoryWithLock');
        try {
            if (!empty($source['skus']) && is_array($source['skus'])) {
                foreach (array_chunk($source['skus'], 500, true) as $source_chunk) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $target = self::get_target_inventory(array_keys($source_chunk));
                        ShipStream_Sync_Helper::logMessage('Current: '.json_encode($target));
                        $processing_qty = self::get_processing_order_items_qty(array_keys($target));
                        ShipStream_Sync_Helper::logMessage('Unsubmitted: '.json_encode($processing_qty));
    
                        foreach ($source_chunk as $sku => $qty) {
                            if (!isset($target[$sku])) {
                                continue;
                            }
    
                            $qty = floor(floatval($qty));
                            $sync_qty = $qty;
                            if (isset($processing_qty[$sku])) {
                                $sync_qty = floor($qty - floatval($processing_qty[$sku]));
                            }
    
                            $target_qty = floatval($target[$sku]['qty']);
                            if ($sync_qty == $target_qty) {
                                continue;
                            }
    
                            ShipStream_Sync_Helper::logMessage("SKU: $sku remote qty is $qty, local is $target_qty and should be $sync_qty");
                            $product_id = $target[$sku]['product_id'];
                            wc_update_product_stock($product_id, $sync_qty);
                        }
                        $wpdb->query('COMMIT');
                    } catch (Exception $e) {
                        $wpdb->query('ROLLBACK');
                        ShipStream_Sync_Helper::logError("Aborted inventory sync: $e");
                        throw $e;
                    }
                }
            }
        } finally {
            ShipStream_Sync_Helper::callback('unlockOrderImport');
        }
    }

    /**
     * Get the target inventory from the local database.
     *
     * @param array $skus The SKUs to get the inventory for.
     * @return array The target inventory data.
     */
    protected static function get_target_inventory($skus) {
        $target_inventory = [];
        foreach ($skus as $sku) {
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product && $product->managing_stock()) {
                    $target_inventory[$sku] = [
                        'product_id' => $product_id,
                        'qty' => $product->get_stock_quantity()
                    ];
                }
            }
        }
        return $target_inventory;
    }

    /**
     * Get the quantity of items in processing orders from the local database.
     *
     * @param array $skus The SKUs to get the processing quantities for.
     * @return array The processing quantities data.
     */
    public static function get_processing_order_items_qty($skus) {
        $excluded_statuses = ['wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-ss-submitted'];
        $included_statuses = array_diff(array_keys(wc_get_order_statuses()), $excluded_statuses);
        
        $orders = wc_get_orders([
            'status' => array_values($included_statuses),
            'limit' => -1,
        ]);
        
        $processing_qty = [];
        $skuMap = array_flip($skus);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && array_key_exists($product->get_sku(), $skuMap)) {
                    $sku = $product->get_sku();
                    if (!isset($processing_qty[$sku])) {
                        $processing_qty[$sku] = 0;
                    }
                    $processing_qty[$sku] += $item->get_quantity();
                }
            }
        }

        return $processing_qty;
    }
}
