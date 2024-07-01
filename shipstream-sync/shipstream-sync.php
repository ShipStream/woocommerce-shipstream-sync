<?php

/*
Plugin Name: ShipStream sync
Description: Integrates WooCommerce with ShipStream for order management and inventory synchronization.
Version: 1.0.0
Author: Praveen Kumar
*/

class WooCommerce_ShipStream_Sync {
 // Constructor: Initializes the plugin by defining constants, registering REST routes, and initializing hooks
    public function __construct() {
        $this->define_constants();
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        $this->init_hooks();
    }

 // Define constants used in the plugin
    private function define_constants() {
        define('WC_SHIPSTREAM_INTEGRATION_VERSION', '1.0.0');
    }


  public function register_rest_routes() {
        // Register a route for setting configuration
        // Add webhook route
        register_rest_route('shipstream/v1', '/inventory-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_inventory_webhook'),
          
        ));

        register_rest_route('shipstream/v1', '/shipstream-set-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_sample_post_request'),
        ));

    }



    function get_shipstream_api_credentials() {
        $options = get_option('woocommerce_extension_settings');
        $credentials = array(
            'url' => isset($options['woocommerce_extension_url']) ? $options['woocommerce_extension_url'] : '',
            'username' => isset($options['woocommerce_extension_username']) ? $options['woocommerce_extension_username'] : '',
            'password' => isset($options['woocommerce_extension_password']) ? $options['woocommerce_extension_password'] : '',
        );
        return $credentials;
    }




// Initialize hooks for various actions and filters
private function init_hooks() {
    add_action('ssi_inventory_pull_event', [$this, 'ssi_inventory_pull']);

    // Schedule cron job for daily inventory pull
    if (!wp_next_scheduled('ssi_inventory_pull_event')) {
        wp_schedule_event(strtotime('02:00:00'), 'daily', 'ssi_inventory_pull_event');
    }

    // Add a hook to run before the action, to introduce a random sleep time
    add_action('ssi_inventory_pull_event', [$this, 'add_random_sleep']);
}

// Function to add random sleep time before inventory pull
public function add_random_sleep() {
    // Generate a random sleep time between 0 and 300 seconds (5 minutes)
    $sleep_time = rand(0, 300);
    sleep($sleep_time); // Sleep for the randomly generated time
}




  // Inventory pull function triggered by a cron event
  public function ssi_inventory_pull() {
    // Insert a post for debugging
    $post_data = [
        'post_title'   => 'ssi_inventory_pull',
        'post_content' => 'ssi_inventory_pull',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post',
    ];

    $post_id = wp_insert_post($post_data);

    // Fetch API credentials
    $credentials = get_shipstream_api_credentials(); // Replace with your function to fetch credentials
    if (!$credentials || !isset($credentials['username']) || !isset($credentials['password'])) {
        error_log('ShipStream API credentials are missing or invalid.');
        return;
    }

    $api_url = 'https://fiverr-sandbox.shipstream.app/api/jsonrpc/';
    $username = $credentials['username'];
    $password = $credentials['password'];

    $login_data = [
        'jsonrpc' => '2.0',
        'id' => 1234,
        'method' => 'login',
        'params' => [$username, $password]
    ];

    // Make login API call
    $session_token = $this->ssi_make_json_rpc_call($api_url, $login_data);

    if (!$session_token) {
        error_log('Failed to authenticate with ShipStream API.');
        return;
    }

    // Prepare inventory data request
    $inventory_data = [
        'jsonrpc' => '2.0',
        'id' => 1234,
        'method' => 'call',
        'params' => [
            $session_token,
            "inventory.list",
            []
        ]
    ];

    // Make inventory data API call
    $response = wp_remote_post($api_url, [
        'method' => 'POST',
        'body' => json_encode($inventory_data),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Error fetching inventory from ShipStream API: ' . $response->get_error_message());
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);

    if (isset($decoded_response['result']) && is_array($decoded_response['result'])) {
        foreach ($decoded_response['result'] as $inventory_item) {
            // Assuming $inventory_item contains SKU and quantity
            $sku = isset($inventory_item['sku']) ? $inventory_item['sku'] : '';
            $quantity = isset($inventory_item['qty']) ? $inventory_item['qty'] : '';

            // Update WooCommerce product inventory
            if (!empty($sku) && !empty($quantity)) {
                $product_id = wc_get_product_id_by_sku($sku);
                if ($product_id) {
                    update_post_meta($product_id, '_stock', $quantity);
                    wc_update_product_stock_status($product_id, $quantity > 0 ? 'instock' : 'outofstock');
                } else {
                    error_log('Product not found for SKU: ' . $sku);
                }
            } else {
                error_log('Invalid inventory item format: ' . print_r($inventory_item, true));
            }
        }
    } else {
        error_log('No valid inventory data found in API response.');
    }
}






public function handle_sample_post_request(WP_REST_Request $request) {
    try {
        $body = $request->get_body();
        $data = json_decode($body, true);
        error_log('Received data: ' . print_r($data, true));


        $order_id = isset($data['message']['order_ref']) ? $data['message']['order_ref'] : null;
        $shipment_id = isset($data['message']['unique_id']) ? $data['message']['unique_id'] : null;
        $new_status = isset($data['message']['status']) ? $data['message']['status'] : null;

    
        if (empty($order_id) || empty($new_status)) {
            throw new Exception('Missing order reference or status.');
        }

        // Map the incoming status to WooCommerce statuses
        switch (strtolower($new_status)) {
            case 'holded':
                $new_status = 'on-hold';
                break;
            case 'backordered':
                $new_status = 'pending';
                break;
            case 'canceled':
                $new_status = 'cancelled';
                break;
            case 'complete':
                $new_status = 'completed';
                break;
            default:
                throw new Exception('Invalid status provided.');
        }

        // Debugging: Log the mapped status
        error_log('Mapped status: ' . $new_status);

        // Load the order
        $order = wc_get_order($order_id);

        if (!$order) {
            throw new Exception('Invalid order ID.');
        }

        // Update the order status
        $order->update_status($new_status, 'Order status updated via custom REST endpoint', true);

        if ($new_status == 'cancelled') {
            update_post_meta($order_id, '_shipstream_order_ids', 'cancelled');
        }

        // Debugging: Log the success
        error_log('Order status updated successfully');
       


        // Fetch shipment information if shipment_id is available
        if (!empty($shipment_id)) {

                $api_url = 'https://fiverr-sandbox.shipstream.app/api/jsonrpc/';
                $username = $credentials['username'];
                $password = $credentials['password'];

                $login_data = [
                    'jsonrpc' => '2.0',
                    'id' => 1234,
                    'method' => 'login',
                    'params' => [$username, $password]
                ];

                // Make login API call
                $session_token = $this->ssi_make_json_rpc_call($api_url, $login_data);


            $rpc_params = [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    $session_token,
                    'shipment.info',
                    $shipment_id
                ],
                'id' => 1234
            ];

            $api_url = 'https://fiverr-sandbox.shipstream.app/api/jsonrpc/';
            $response = wp_remote_post('https://fiverr-sandbox.shipstream.app/api/jsonrpc/', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($rpc_params),
            ]);

            if (is_wp_error($response)) {
                $error_message = 'RPC Error: ' . $response->get_error_message();
                error_log($error_message);
                $this->log_error_in_table($error_message); // Log error in a table
                throw new Exception('Failed to fetch shipment information.');
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['result'])) {
                // Insert a post for debugging
                $post_data = [
                    'post_title'   => 'shipment',
                    'post_content' => json_encode($data['result'], JSON_PRETTY_PRINT),
                    'post_status'  => 'publish',
                    'post_author'  => get_current_user_id(),
                    'post_type'    => 'post',
                ];

                wp_insert_post($post_data);

                // Add tracking number directly to order meta data
                if (isset($data['result']['tracking_numbers'])) {
                    $tracking_numbers = $data['result']['tracking_numbers'];

                    if (is_array($tracking_numbers)) {
                            foreach ($tracking_numbers as $tracking_info) {
                                if ($tracking_info['number']) {
                                    update_post_meta($order_id, '_tracking_numbers',$tracking_info['number']);
                                    break; 
                                }
                            }
                        }
                }

                // Update WooCommerce order line items metadata
                if (isset($data['result']['items']) && is_array($data['result']['items'])) {
                    foreach ($data['result']['items'] as $item) {
                        $order_item_id = $item['order_item_id'];
                        wc_update_order_item_meta($order_item_id, 'sku', $item['sku']);
                        wc_update_order_item_meta($order_item_id, 'quantity', $item['qty']);
                        wc_update_order_item_meta($order_item_id, 'package_data', $item['package_data']);
                        wc_update_order_item_meta($order_item_id, 'lot_data', $item['lot_data']);
                    }
                }

                return new WP_REST_Response(['success' => true, 'message' => 'Order status and shipment info updated successfully'], 200);
            } else {
                $error_message = 'No valid result found in API response.';
                error_log($error_message);
                $this->log_error_in_table($error_message); // Log error in a table
                throw new Exception($error_message);
            }
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Order status updated successfully'], 200);
    } catch (Exception $e) {
        error_log('Error in handle_sample_post_request: ' . $e->getMessage());
        $this->log_error_in_table($e->getMessage()); // Log error in a table
        return new WP_Error('processing_error', $e->getMessage(), ['status' => 400]);
    }
}




private function log_error_in_table($message) {
    $post_data = [
        'post_title'   => 'Error Log',
        'post_content' => $message,
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
        'post_type'    => 'error_log',
    ];

    wp_insert_post($post_data);
}

















   // Define the inventory data to send to the WMS API

 public function handle_inventory_webhook(WP_REST_Request $request) {
 
            $api_url = get_option('shipstream_api_url');
            $username = get_option('shipstream_username');
            $password = get_option('shipstream_password');

               if (empty($api_url) || empty($username) || empty($password)) {
                throw new Exception('API credentials are not set.');
            }

            $login_data = [
                'jsonrpc' => '2.0',
                'id' => 1234,
                'method' => 'login',
                'params' => [$username, $password]
            ];
         $session_token = $this->ssi_make_json_rpc_call($api_url, $login_data);
    
    $inventory_data = [
        'jsonrpc' => '2.0',
        'id' => 1234,
        'method' => 'call',
        'params' => [
            $session_token,
            "inventory.list",
        ]
    ];
    // Make the API request to fetch inventory data
    $response = wp_remote_post($api_url, [
        'method' => 'POST',
        'body' => json_encode($inventory_data),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);
    // Check for errors in the API request
    if (is_wp_error($response)) {
        error_log('Error fetching inventory from WMS: ' . $response->get_error_message());
        return;
    }
    // Retrieve and decode the response body
    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);

    // Create a new post to store the API response (for debugging purposes)
    $post_data = [
        'post_title'   => 'Synchronize WMS Products',
        'post_content' => json_encode($decoded_response, JSON_PRETTY_PRINT), 
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post',
    ];

    // Insert the post into WordPress
    $post_id = wp_insert_post($post_data);

    // Check if there's an error reported in the API response
    if (isset($decoded_response['error'])) {
        error_log('Error from WMS: ' . print_r($decoded_response['error'], true));
        return;
    }
    // Process inventory items if no error reported
    if (isset($decoded_response['result']) || is_array($decoded_response['result'])) {
        foreach ($decoded_response['result'] as $item) {
            // Extract SKU and quantity from each inventory item
            $sku = isset($item['sku']) ? $item['sku'] : '';
            $quantity = isset($item['qty']) ? $item['qty'] : '';

            // Update WooCommerce product stock based on SKU
            if (!empty($sku) && !empty($quantity)) {
                $product_id = wc_get_product_id_by_sku($sku);

                if ($product_id) {
                    update_post_meta($product_id, '_stock', $quantity);
                    wc_update_product_stock_status($product_id);
                } else {
                    error_log('Product not found for SKU: ' . $sku);
                }
            } else {
                error_log('Invalid inventory item format: ' . print_r($item, true));
            }
        }
    } else {
        error_log('No valid inventory data found in API response.');
    }
}







  // Helper function to make a JSON-RPC call

    private function ssi_make_json_rpc_call($api_url, $data) {
        $response = wp_remote_post($api_url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);
            if (isset($decoded_response['error'])) {
                return false;
            } else {
                return $decoded_response['result'];
            }
        }
    }



}
// Initialize the plugin
$woocommerce_shipstream_integration = new WooCommerce_ShipStream_Sync();

?>
