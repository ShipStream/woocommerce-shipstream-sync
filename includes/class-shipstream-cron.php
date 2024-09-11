<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ShipStream_Cron {
    const LOG_FILE = 'wp-content/plugins/woocommerce-shipstream-sync/includes/shipstream_cron.log';

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
                foreach (array_chunk($source['skus'], 5000, true) as $source_chunk) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $target = self::get_target_inventory(array_keys($source_chunk));
                        $processing_qty = self::get_processing_order_items_qty(array_keys($source_chunk));
    
                        foreach ($source_chunk as $sku => $qty) {
                            if (!isset($target[$sku])) {
                                continue;
                            }
    
                            $qty = floor(floatval($qty));
                            $sync_qty = $qty;
                            if (isset($processing_qty[$sku])) {
                                $sync_qty = floor($qty - floatval($processing_qty[$sku]['qty']));
                            }
    
                            $target_qty = floatval($target[$sku]['qty']);
                            if ($sync_qty == $target_qty) {
                                continue;
                            }
    
                            ShipStream_Sync_Helper::logMessage("SKU: $sku remote qty is $qty and local is $target_qty");
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
        global $wpdb;
        $placeholders = implode(',', array_map(function($sku) { return "'" . $sku . "'"; }, $skus));
        $sql = "
            SELECT p.ID as product_id, p.post_title as name, pm.meta_value as sku, pm2.meta_value as qty
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_stock'
            WHERE p.post_type = 'product' AND pm.meta_value IN ($placeholders)
        ";
        $results = $wpdb->get_results($wpdb->prepare($sql, $skus), ARRAY_A);

        $target_inventory = [];
        foreach ($results as $result) {
            $target_inventory[$result['sku']] = [
                'product_id' => $result['product_id'],
                'qty' => $result['qty']
            ];
        }
        return $target_inventory;
    }

    /**
     * Get the quantity of items in processing orders from the local database.
     *
     * @param array $skus The SKUs to get the processing quantities for.
     * @return array The processing quantities data.
     */
    protected static function get_processing_order_items_qty($skus) {
        global $wpdb;
        $statuses = ['wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-ss-submitted'];
        $placeholders = implode(',', array_map(function($sku) { return "'" . $sku . "'"; }, $skus));
        $order_statuses = implode(',', array_map(function($status) { return "'" . $status . "'"; }, $statuses));

        $sql = "
            SELECT pm.meta_value as sku, SUM(oi.meta_value) as qty
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->postmeta} pm ON pm.post_id = oim.meta_value AND pm.meta_key = '_sku'
            JOIN {$wpdb->posts} p ON p.ID = oi.order_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status NOT IN ($order_statuses)
            AND pm.meta_value IN ($placeholders)
            GROUP BY pm.meta_value
        ";
        $results = $wpdb->get_results($wpdb->prepare($sql, $skus), ARRAY_A);

        $processing_qty = [];
        foreach ($results as $result) {
            $processing_qty[$result['sku']] = [
                'sku' => $result['sku'],
                'qty' => $result['qty']
            ];
        }
        return $processing_qty;
    }
}
