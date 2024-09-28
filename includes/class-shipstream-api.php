<?php

class ShipStream_API {

    // Initialize the REST API routes
    public static function init() {
        // Register the REST API routes
        register_rest_route('shipstream/v1', '/info', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'get_info'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'register'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/inventory/sync', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'sync_inventory'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/inventory/adjust', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'adjust_stock'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/order/info', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'get_order_info'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/order/complete_with_tracking', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'complete_order_with_tracking'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/order/list', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'order_list'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/order/addComment', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'add_order_comment'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
        register_rest_route('shipstream/v1', '/order/canceled', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'order_canceled'),
            'permission_callback' => array(__CLASS__, 'authenticate'),
        ));
    }

    public static function logRequest(WP_REST_Request $request) {
        ShipStream_Sync_Helper::logMessage('Received API request: '
            . $request->get_method() . ' '
            . $request->get_route() . ' '
            . $request->get_body()
        );
    }

    public static function authenticate(WP_REST_Request $request) {
        // Get headers from the request
        $headers = $request->get_headers();

        // Check if the authorization header is set
        if (!isset($headers['authorization'][0])) {
            return false;
        }

        // Get the authorization header value
        $auth_header = $headers['authorization'][0];

        // Check if the authorization method is Basic
        if (strpos($auth_header, 'Basic ') !== 0) {
            return false;
        }

        // Decode the Base64 encoded authorization value
        $auth_value = base64_decode(substr($auth_header, 6));

        // Split the decoded value into consumer key and consumer secret
        list($consumer_key, $consumer_secret) = explode(':', $auth_value);

        // Sanitize and hash the consumer key
        $consumer_key = wc_api_hash(sanitize_text_field($consumer_key));

        // Access the global $wpdb object for database operations
        global $wpdb;

        // Query the database to retrieve the user information based on the hashed consumer key
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
                $consumer_key
            )
        );

        // Check if the user exists and if the hashed consumer secret matches the stored value
        if (empty($user) || !hash_equals($user->consumer_secret, $consumer_secret)) {
            return false;
        }

        // Authentication successful
        return true;
    }


    // Get information about WordPress, WooCommerce, and plugin versions
    public static function get_info(WP_REST_Request $request) {
        self::logRequest($request);
        $wc_version = get_option('woocommerce_version', 'N/A');

        $plugin_file_path = WP_PLUGIN_DIR . '/woocommerce-shipstream-sync/woocommerce-shipstream-sync.php';
        if (file_exists($plugin_file_path)) {
            $plugin_data = get_file_data($plugin_file_path, array('Version' => 'Version'), false);
            $shipstream_sync_version = $plugin_data['Version'];
        } else {
            $shipstream_sync_version = 'N/A';
        }

        global $wp_version;

        $result = array(
            'wordpress_version' => $wp_version,
            'woocommerce_version' => $wc_version,
            'shipstream_sync_version' => $shipstream_sync_version,
            'tracking_plugins' => [],
        );

        // Detect common shipment tracking extensions and add their versions
        $tracking_extensions = array(
            'woocommerce-shipment-tracking/woocommerce-shipment-tracking.php' => 'WooCommerce Shipment Tracking',
            'woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php' => 'Advanced Shipment Tracking for WooCommerce',
            'aftership-woocommerce-tracking/aftership.php' => 'AfterShip WooCommerce Tracking',
            'woo-orders-tracking/woo-orders-tracking.php' => 'Orders Tracking for WooCommerce'
        );
        foreach ($tracking_extensions as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                $result['tracking_plugins'][$plugin_name] = $plugin_data['Version'];
            }
        }
        return new WP_REST_Response($result, 200);
    }

    // Set configuration values
    public static function register(WP_REST_Request $request) {
        self::logRequest($request);
        $updated = update_option('shipstream_callback_url', $request->get_param('callback_url'))
            && update_option('shipstream_app_title', $request->get_param('app_title'));
        
        if ($updated) {
            ShipStream_Sync_Helper::logMessage('Configuration updated');
            return new WP_REST_Response(array('success' => 'Configuration updated'), 200);
        } else {
            ShipStream_Sync_Helper::logError('Failed to update configuration');
            return new WP_REST_Response(array('error' => 'Failed to update configuration'), 500);
        }
    }

    // Sync inventory
    public static function sync_inventory(WP_REST_Request $request) {
        self::logRequest($request);
        try {
            ShipStream_Cron::full_inventory_sync(false);
            return new WP_REST_Response(array('success' => true,'message' => 'Inventory synced successfully.'), 200);
        } catch (Exception $e) {
            ShipStream_Sync_Helper::logError("Inventory sync failed: $e");
            return new WP_REST_Response(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    // Adjust stock item quantity
    public static function adjust_stock(WP_REST_Request $request) {
        self::logRequest($request);
        $changes = $request->get_param('changes');

        if (empty($changes) || !is_array($changes)) {
            return new WP_REST_Response(array('error' => 'Changes parameter is required and must be an array'), 400);
        }

        $results = array();

        foreach ($changes as $change) {
            $product_id = wc_get_product_id_by_sku($change['sku']);
            if (!$product_id) {
                $results[] = array(
                    'sku' => $change['sku'],
                    'status' => 'not-found',
                );
                continue;
            }
            $product_ids[$change['sku']] = $product_id;
        }

        global $wpdb; /** @var wpdb $wpdb */

        $wpdb->query('START TRANSACTION');

        try {
            if ($product_ids) {
                // Lock the product rows for update
                $product_ids_string = implode(',', array_map('intval', $product_ids));
                $wpdb->query("SELECT ID FROM {$wpdb->posts} WHERE ID IN ($product_ids_string) FOR UPDATE");

                // Gather unsubmitted amounts for each SKU
                $unsubmitted_amounts = ShipStream_Cron::get_processing_order_items_qty(array_keys($product_ids));

                // Calculate the final amount and apply the updates
                foreach ($changes as $change) {
                    $sku = $change['sku'];
                    if (empty($product_ids[$sku])) {
                        continue;
                    }
                    $product_id = $product_ids[$sku];
                    $product = wc_get_product($product_id);
    
                    $stock_quantity = $product->get_stock_quantity();
                    if (isset($change['delta'])) {
                        $new_stock_quantity = $stock_quantity + $change['delta'];
                    } else {
                        $new_stock_quantity = $change['quantity'];
                        $new_stock_quantity -= $unsubmitted_amounts[$sku] ?? 0;
                    }
                    if ($new_stock_quantity == $stock_quantity) {
                        $results[] = array(
                            'sku' => $sku,
                            'product_id' => $product_id,
                            'old_quantity' => $stock_quantity,
                            'new_quantity' => $new_stock_quantity,
                            'unsubmitted' => $unsubmitted_amounts[$sku] ?? 0,
                            'status' => 'no-change',
                        );
                        continue;
                    }
                    
                    $product->set_stock_quantity($new_stock_quantity);
                    if ($new_stock_quantity > 0 && $product->get_stock_status() === 'outofstock') {
                        $product->set_stock_status('instock');
                    } elseif ($new_stock_quantity <= 0 && $product->get_stock_status() === 'instock') {
                        $product->set_stock_status('outofstock');
                    }
                    $product->save();
    
                    $results[] = array(
                        'sku' => $sku,
                        'product_id' => $product_id,
                        'old_quantity' => $stock_quantity,
                        'new_quantity' => $new_stock_quantity,
                        'unsubmitted' => $unsubmitted_amounts[$sku],
                        'status' => 'success',
                    );
                }
            }

            $wpdb->query('COMMIT');

            return new WP_REST_Response(array('success' => true, 'results' => $results), 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log($e->getMessage());
            return new WP_REST_Response(array('error' => $e->getMessage()), 500);
        }
    }

    // Get order information
    public static function get_order_info(WP_REST_Request $request) {
        self::logRequest($request);
        $order_number = $request->get_param('order_number');
        if (empty($order_number)) {
            return new WP_REST_Response(array('error' => 'Order Number parameter is required'), 400);
        }

        $order = wc_get_order($order_number);
        if (!$order) {
            return new WP_REST_Response(array('error' => 'Order does not exist'), 404);
        }

        $result = array();
        $result['order_id'] = $order->get_id();
        $result['order_number'] = $order->get_order_number();
        $result['coupon_codes'] = $order->get_coupon_codes();
        $result['created_via'] = $order->get_created_via();
        $result['customer_id'] = $order->get_customer_id();
        $result['customer_note'] = $order->get_customer_note();
        $result['customer_order_notes'] = $order->get_customer_order_notes();
        $result['date_created'] = $order->get_date_created()->format('Y-m-d H:i:s');
        $result['payment_method'] = $order->get_payment_method();
        $result['shipping_address'] = $order->get_address('shipping');
        $result['shipping_method'] = $order->get_shipping_method();
        $result['status'] = $order->get_status();
        $result['subtotal'] = $order->get_subtotal();
        $result['total'] = $order->get_total();

        $result['items'] = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product(); /** @var WC_Product $product */
            if ($product->get_virtual() || $product->get_downloadable()) {
                continue;
            }
            $item_data = array(
                'product_id' => $product->get_id(),
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'product_type' => $product->get_type(),
                'price' => $product->get_price(),
                'shipping_class' => $product->get_shipping_class(),
                'stock_managed_by_id' => $product->get_stock_managed_by_id(),
                'tag_ids' => $product->get_tag_ids(),
                'tax_class' => $product->get_tax_class(),
                'tax_status' => $product->get_tax_status(),
                'category_ids' => $product->get_category_ids(),
            );
            $result['items'][] = $item_data;
        }

        $result['shipping_lines'] = array();
        foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {
            $shipping_data = array(
                'shipping_method' => $shipping_item->get_method_id(),
                'shipping_description' => $shipping_item->get_method_title(),
                'total' => $shipping_item->get_total(),
            );
            $result['shipping_lines'][] = $shipping_data;
        }

        return new WP_REST_Response($result, 200);
    }

    public static function complete_order_with_tracking(WP_REST_Request $request) {
        self::logRequest($request);
        $data = $request->get_json_params();
        $eventData = $data['event_data'];

        // Fetch the order using the increment ID
        $order = wc_get_order($data['order_number']);
        if (!$order) {
            return;
        }

        // Add tracking numbers to the order using the Shipment Tracking extension
        foreach ($eventData['packages'] as $package) {
            $i = 0;
            foreach ($package['tracking_numbers'] as $tracking_number) {
                self::add_tracking_number_to_order(
                    $order,
                    $tracking_number,
                    $eventData['carrier'] ?? $package['manifest_courier'] ?? $eventData['service_description'],
                    $eventData['service_description'] ?? $package['manifest_courier'] ?? $eventData['carrier'],
                    $package['tracking_urls'][$i] ?? null
                );
                $i++;
            }
        }

        // Update the order's line items with shipment info data
        self::update_order_line_items_with_shipment_info($order, $eventData);

        // Check if all items are shipped and mark order as completed
        if ($eventData['order_status'] === 'complete') {
            self::maybe_mark_order_as_completed($order, $eventData);
        }
    }

    public static function add_tracking_number_to_order(WC_Order $order, $tracking_number, $carrierCode, $serviceName, $url) {
        // Ensure that the function exists (confirm AST is active)
        if (function_exists('ast_insert_tracking_number')) {
            $actions = new WC_Advanced_Shipment_Tracking_Actions();
            $providers  = $actions->get_providers();
            if (isset($providers[$carrierCode])) {
                $tracking_provider = $providers[$carrierCode]['provider_name'];
            } else {
                foreach ($providers as $code => $providerData) {
                    if (strtolower($providerData['provider_name']) === strtolower($carrierCode)) {
                        $tracking_provider = $providerData['provider_name'];
                        break;
                    }
                }
                if (empty($tracking_provider)) {
                    foreach ($providers as $code => $providerData) {
                        if (strpos(strtolower($providerData['provider_name']), strtolower($carrierCode)) !== false) {
                            $tracking_provider = $providerData['provider_name'];
                            break;
                        }
                    }
                }
            }
            ast_insert_tracking_number($order->get_id(), $tracking_number, $tracking_provider, date('Y-m-d'), $url);
        }
        if (class_exists('WC_Shipment_Tracking')) {
            $tracking_provider = WC_Shipment_Tracking::get_providers()[$carrierCode] ?? $carrierCode;
    
            $tracking_data = array(
                'tracking_provider' => $tracking_provider,
                'tracking_number'   => $tracking_number,
                'date_shipped'      => current_time('timestamp'),
            );
    
            $wc_shipment_tracking = new WC_Shipment_Tracking();
            $wc_shipment_tracking->add_tracking_number($order->get_id(), $tracking_data);
        }
        else {
            $order->add_order_note(sprintf(__('Shipped via %s with tracking number %s.', 'woocommerce-shipstream-sync'), $serviceName, $tracking_number), 1);
        }
    }

    public static function update_order_line_items_with_shipment_info(WC_Order $order, $eventData) {
        foreach ($order->get_items() as $item_id => $item) {
            $item_sku = $item->get_product()->get_sku();

            foreach ($eventData['items'] as $shipment_item) {
                if ($shipment_item['sku'] === $item_sku) {
                    //wc_update_order_item_meta($item_id, 'order_item_id', $shipment_item['order_item_id']);
                    if ($shipment_item['sku'] != $item_sku) {
                        wc_update_order_item_meta($item_id, 'sku', $shipment_item['sku']);
                    }
                    if ($shipment_item['quantity'] != $item->get_quantity()) {
                        wc_update_order_item_meta($item_id, 'quantity', $shipment_item['quantity']);
                    }
                    if ($shipment_item['package_data']) {
                        wc_update_order_item_meta($item_id, 'package_data', json_encode($shipment_item['package_data']));
                    }
                    if ($shipment_item['lot_data']) {
                        wc_update_order_item_meta($item_id, 'lot_data', json_encode($shipment_item['lot_data']));
                    }
                }
            }
        }
    }

    public static function maybe_mark_order_as_completed(WC_Order $order, $eventData) {
        $all_items_shipped = true;

        foreach ($order->get_items() as $item) {
            $item_sku = $item->get_product()->get_sku();
            $order_quantity = $item->get_quantity();

            foreach ($eventData['items'] as $shipment_item) {
                if ($shipment_item['sku'] === $item_sku) {
                    if ((float)$shipment_item['quantity'] < (float)$order_quantity) {
                        $all_items_shipped = false;
                        break 2;
                    }
                }
            }
        }

        if ($all_items_shipped) {
            $order->update_status('completed');
        }
    }


    /**
     * List orders based on filters from the request
     * @param $request
     * @return WP_REST_Response
     */
    public static function order_list(WP_REST_Request $request)
    {
        self::logRequest($request);
        global $wpdb;

        $cols_select        = ['id', 'date_updated_gmt'];
        $cols               = ['id', 'date_modified'];
        $orders             = [];
        $date_updated_gmt   = [];
        $status             = [];

        if($request->get_param('date_updated_gmt'))
        {
            $date_updated_gmt = $request->get_param('date_updated_gmt');
        }
        if($request->get_param('status'))
        {
            $status = $request->get_param('status');
        }
        // Build the base query
        $query = $wpdb->prepare(
            "SELECT " . implode(", ", $cols_select) . " FROM {$wpdb->prefix}wc_orders WHERE type = %s",
            'shop_order'
        );

        // Add date_updated_gmt filter if provided
        if (isset($date_updated_gmt['from']) && isset($date_updated_gmt['to'])) {
            $query .= $wpdb->prepare(
                " AND date_updated_gmt BETWEEN %s AND %s",
                $date_updated_gmt['from'],
                $date_updated_gmt['to']
            );
        }

        // Add status filters if provided
        if (isset($status['in']) && !empty($status['in'])) {
            $status_in = implode(", ", array_map(function($item) use ($wpdb) { return $wpdb->prepare('%s', $item); }, $status['in']));
            $query .= " AND status IN ($status_in)";
        }
        // Execute the query
        $results = $wpdb->get_results($query, ARRAY_A);

        // Handle query errors
        if ($wpdb->last_error) {
            return new WP_REST_Response(['error' => $wpdb->last_error], 500);
        }

        // Prepare the orders data
        foreach ($results as $row) {
            $order = wc_get_order($row['id']);
            if ($order) {
                $eventData = $order->get_data();
                $filtered_data = array_intersect_key($eventData, array_flip($cols));
                $orders[] = $filtered_data;
            }
        }

        return new WP_REST_Response($orders, 200);
    }

    /**
     * Add a comment to an order.
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response object.
     */
    public static function add_order_comment(WP_REST_Request $request)
    {
        self::logRequest($request);
        $order_number = $request->get_param('order_number');
        $status = $request->get_param('status');
        $comment = $request->get_param('comment');

        if (empty($order_number) || empty($comment) || empty($status)) {
            return new WP_REST_Response(['error' => 'Order ID, Status, and Comment are required.'], 400);
        }

        $order = wc_get_order($order_number);

        if (!$order) {
            return new WP_REST_Response(['error' => 'Invalid order ID.'], 404);
        }

        // Update order status
        if ($status) {
            $order->update_status(sanitize_text_field($status));
        }

        // Add order note
        $comment_id = $order->add_order_note(
            sanitize_text_field($comment),
            false
        );

        if (!$comment_id) {
            return new WP_REST_Response(['error' => 'Failed to add comment to order.'], 500);
        }

        if ($status === 'wc-ss-submitted') {
            $order->update_meta_data('_apptitle', sanitize_text_field(ShipStream_Sync_Helper::getAppTitle()));
        }
        $order->save();

        return new WP_REST_Response(['success' => 'Comment added to order.', 'comment_id' => $comment_id], 200);
    }

    /**
     * Update the status of an order and add a comment.
     * 
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response object.
     */
    public static function order_canceled(WP_REST_Request $request)
    {
        self::logRequest($request);
        // Get parameters from the request
        $order_number = $request->get_param('order_number');

        // Check for missing required parameters
        if (empty($order_number)) {
            return new WP_REST_Response(['error' => 'Order Number is required.'], 400);
        }

        // Get the order by ID
        $order = wc_get_order($order_number);
        if (!$order) {
            return new WP_REST_Response(['error' => 'Invalid order number.'], 404);
        }

        // Update order status and add a comment
        if ($order->get_status() === 'ss-submitted') {
            $order->update_status('wc-on-hold', sprintf(__('Corresponding %s order was cancelled.', 'woocommerce-shipstream-sync'), ShipStream_Sync_Helper::getAppTitle()));
            return new WP_REST_Response(['success' => 'Status updated successfully for order #' . $order->get_order_number()], 200);
        } else {
            $order->add_order_note(sprintf(__('%s order was cancelled but no changes were applied.', 'woocommerce-shipstream-sync'), ShipStream_Sync_Helper::getAppTitle()));
            return new WP_REST_Response(['success' => 'No change was made for order #' . $order->get_order_number() . ' with status ' . $order->get_status()], 200);
        }
    }

}
