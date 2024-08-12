<?php
class ShipStream_Sync_Helper_Api {
    const LOG_FILE = 'wp-content/plugins/woocommerce-shipstream-sync/includes/shipstream_reqs.log';

    /**
     * Log the start of the inventory sync process.
     */
    public static function logStart() {
        self::logMessage("Beginning inventory sync");
    }

    /**
     * Check if the warehouse API is configured.
     *
     * @return bool True if the API URL is configured, false otherwise.
     */
    public static function isConfigured() {
        return !empty(get_option('shipstream_warehouse_api_url'));
    }

    /**
     * Perform a request to the warehouse API.
     *
     * @param string $method The API method to call.
     * @param array $data The data to send with the request.
     * @return mixed The response from the API.
     * @throws Exception If the API URL is not configured or the request fails.
     */
    public static function callback($method, $data = []) {
        $apiUrl = get_option('shipstream_warehouse_api_url');
        if (empty($apiUrl)) {
            throw new Exception('The warehouse API URL is required.');
        }

        $apiUrl = urldecode($apiUrl);
        if (strpos($apiUrl, '{{method}}') === false) {
            throw new Exception('The warehouse API URL format is not valid.');
        }

        $apiUrl = str_replace('{{method}}', $method, $apiUrl);
        $ch = self::initCurl($apiUrl, 'GET', $data);

        // Log request details
        self::logRequest($ch);

        $response = self::executeCurl($ch);

        // Log response details
        self::logResponse((is_array($response) ? json_encode($response):$response));

        return $response;
    }

    /**
     * Execute a cURL session.
     *
     * @param resource $ch The cURL handle.
     * @return array The decoded response data.
     * @throws Exception If the request fails or the response is invalid.
     */
    protected static function executeCurl($ch) {
        $response = curl_exec($ch);
        if ($response === false) {
            $errorMsg = 'Response error code: "' . curl_errno($ch) . '". Error description: "' . curl_error($ch) . '".';
            curl_close($ch);
            throw new Exception($errorMsg);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201])) {
            $exceptionMessage = "Warehouse API Error ($httpCode).";
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($data['errors'])) {
                $exceptionMessage .= ': "' . implode('"; "', (array) $data['errors']) . '"';
            } else {
                $exceptionMessage .= ' Response: "' . $response . '"';
            }
            throw new Exception($exceptionMessage, $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('An error occurred while decoding JSON encoded string.');
        }

        return $data;
    }

    /**
     * Initialize a cURL session.
     *
     * @param string $url The URL to request.
     * @param string $type The HTTP method (GET, POST, PUT, DELETE).
     * @param array $data The data to send with the request.
     * @return resource The cURL handle.
     * @throws Exception If cURL is not installed or the request type is invalid.
     */
    protected static function initCurl($url, $type, array $data = []) {
        if (!function_exists('curl_version')) {
            throw new Exception('cURL is not installed.');
        }

        $type = strtoupper($type);
        if (!in_array($type, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new Exception('Invalid HTTP request type.');
        }

        if (in_array($type, ['GET', 'DELETE']) && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (in_array($type, ['PUT', 'POST'])) {
            $json = empty($data) ? '{}' : json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Length: ' . strlen($json);
        }

        if (in_array($type, ['PUT', 'DELETE'])) {
            $headers[] = 'X-HTTP-Method-Override: ' . $type;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true); // Enable request header output

        return $ch;
    }

    /**
     * Log a message to the log file.
     *
     * @param string $message The message to log.
     */
    protected static function logMessage($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, self::LOG_FILE);
    }

    /**
     * Log the request details.
     *
     * @param resource $ch The cURL handle.
     */
    protected static function logRequest($ch) {
        $requestHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        self::logMessage("Request Headers:\n" . $requestHeaders);
    }

    /**
     * Log the response details.
     *
     * @param string $response The response from the cURL request.
     */
    protected static function logResponse($response) {
        self::logMessage("Response:\n" . $response);
    }
}
