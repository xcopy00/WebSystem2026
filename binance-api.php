<?php
/**
 * Binance API Wrapper Class (PHP)
 * Handles all interactions with Binance API for Spot and Futures trading
 */

class BinanceAPI {
    private $apiKey;
    private $apiSecret;
    private $useFutures;
    private $baseUrl;
    private $baseUrlFutures;

    public function __construct($apiKey = '', $apiSecret = '', $useFutures = false) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->useFutures = $useFutures;
        $this->baseUrl = BINANCE_SPOT_BASE_URL;
        $this->baseUrlFutures = BINANCE_FUTURES_BASE_URL;
    }

    /**
     * Generate API signature for authenticated requests
     */
    private function generateSignature($params) {
        $queryString = http_build_query($params);
        return hash_hmac('sha256', $queryString, $this->apiSecret);
    }

    /**
     * Make API request
     */
    private function request($endpoint, $params = [], $method = 'GET', $futures = false) {
        $baseUrl = $futures ? $this->baseUrlFutures : $this->baseUrl;
        $url = $baseUrl . $endpoint;

        // Add timestamp and signature for authenticated endpoints
        if (!empty($this->apiKey)) {
            $params['timestamp'] = millitime();
            $params['signature'] = $this->generateSignature($params);
        }

        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-MBX-APIKEY: ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("API Error (HTTP $httpCode): " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Make public API request (no auth required)
     * @return array|null Returns array on success, null on failure
     */
    private function publicRequest($endpoint, $params = []): ?array {
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP " . $httpCode . " - " . $response);
        }

        if (empty($response)) {
            return null;
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }

        return $data;
    }

    // ==================== VALIDATION ====================

    /**
     * Validate API keys
     */
    public function validateApiKeys() {
        try {
            if ($this->useFutures) {
                $this->request('/fapi/v1/account', [], 'GET', true);
            } else {
                $this->request('/api/v3/account', [], 'GET', false);
            }
            return ['valid' => true];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== SPOT TRADING ====================

    /**
     * Get spot account balance
     */
    public function getSpotBalance() {
        $data = $this->request('/api/v3/account', [], 'GET', false);
        return $data['balances'];
    }

    /**
     * Get current spot price
     */
    public function getSpotPrice($symbol) {
        $data = $this->publicRequest('/api/v3/ticker/price', ['symbol' => $symbol]);
        return floatval($data['price']);
    }

    /**
     * Get 24hr ticker
     */
    public function get24hrTicker($symbol) {
        return $this->publicRequest('/api/v3/ticker/24hr', ['symbol' => $symbol]);
    }

    /**
     * Place spot limit buy order
     */
    public function spotLimitBuy($symbol, $quantity, $price) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'LIMIT',
            'timeInForce' => 'GTC',
            'quantity' => $quantity,
            'price' => $price
        ], 'POST', false);
    }

    /**
     * Place spot limit sell order
     */
    public function spotLimitSell($symbol, $quantity, $price) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'LIMIT',
            'timeInForce' => 'GTC',
            'quantity' => $quantity,
            'price' => $price
        ], 'POST', false);
    }

    /**
     * Place spot market buy order
     */
    public function spotMarketBuy($symbol, $quantity) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => $quantity
        ], 'POST', false);
    }

    /**
     * Place spot market sell order
     */
    public function spotMarketSell($symbol, $quantity) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => $quantity
        ], 'POST', false);
    }

    /**
     * Get open orders
     */
    public function getOpenOrders($symbol) {
        return $this->request('/api/v3/openOrders', ['symbol' => $symbol], 'GET', false);
    }

    /**
     * Get order status
     */
    public function getOrderStatus($symbol, $orderId) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'orderId' => $orderId
        ], 'GET', false);
    }

    /**
     * Cancel order
     */
    public function cancelOrder($symbol, $orderId) {
        return $this->request('/api/v3/order', [
            'symbol' => $symbol,
            'orderId' => $orderId
        ], 'DELETE', false);
    }

    /**
     * Get order book
     */
    public function getOrderBook($symbol, $limit = 20) {
        return $this->publicRequest('/api/v3/depth', [
            'symbol' => $symbol,
            'limit' => $limit
        ]);
    }

    /**
     * Get klines/candlesticks
     */
    public function getKlines($symbol, $interval = '1h', $limit = 100) {
        return $this->publicRequest('/api/v3/klines', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ]);
    }

    // ==================== FUTURES TRADING ====================

    /**
     * Get futures balance
     */
    public function getFuturesBalance() {
        $data = $this->request('/fapi/v1/balance', [], 'GET', true);
        return $data;
    }

    /**
     * Get futures position
     */
    public function getFuturesPosition($symbol) {
        return $this->request('/fapi/v2/positionRisk', ['symbol' => $symbol], 'GET', true);
    }

    /**
     * Set leverage
     */
    public function setFuturesLeverage($symbol, $leverage) {
        return $this->request('/fapi/v1/leverage', [
            'symbol' => $symbol,
            'leverage' => $leverage
        ], 'POST', true);
    }

    /**
     * Place futures limit order
     */
    public function futuresLimitOrder($symbol, $side, $quantity, $price, $leverage = 10) {
        $this->setFuturesLeverage($symbol, $leverage);
        return $this->request('/fapi/v1/order', [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'LIMIT',
            'timeInForce' => 'GTC',
            'quantity' => $quantity,
            'price' => $price,
            'leverage' => $leverage
        ], 'POST', true);
    }

    /**
     * Place futures market order
     */
    public function futuresMarketOrder($symbol, $side, $quantity, $leverage = 10) {
        $this->setFuturesLeverage($symbol, $leverage);
        return $this->request('/fapi/v1/order', [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'leverage' => $leverage
        ], 'POST', true);
    }

    /**
     * Get futures open orders
     */
    public function getFuturesOpenOrders($symbol) {
        return $this->request('/fapi/v1/openOrders', ['symbol' => $symbol], 'GET', true);
    }

    /**
     * Cancel futures order
     */
    public function cancelFuturesOrder($symbol, $orderId) {
        return $this->request('/fapi/v1/order', [
            'symbol' => $symbol,
            'orderId' => $orderId
        ], 'DELETE', true);
    }

    /**
     * Get futures account info
     */
    public function getFuturesAccountInfo() {
        return $this->request('/fapi/v2/account', [], 'GET', true);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Format Kline data
     */
    public function formatKlines($klines) {
        $formatted = [];
        foreach ($klines as $kline) {
            $formatted[] = [
                'time' => $kline[0] / 1000,
                'open' => floatval($kline[1]),
                'high' => floatval($kline[2]),
                'low' => floatval($kline[3]),
                'close' => floatval($kline[4]),
                'volume' => floatval($kline[5])
            ];
        }
        return $formatted;
    }

    /**
     * Calculate quantity from USDT amount
     */
    public function calculateQuantity($symbol, $usdtAmount) {
        $price = $this->getSpotPrice($symbol);
        $quantity = $usdtAmount / $price;
        
        // Get symbol info for precision
        $response = $this->publicRequest('/api/v3/exchangeInfo', ['symbol' => $symbol]);
        
        if ($response === null || !isset($response['symbols'][0])) {
            return $quantity; // Return unadjusted quantity if we can't get filters
        }
        
        foreach ($response['symbols'][0]['filters'] as $filter) {
            if ($filter['filterType'] === 'LOT_SIZE') {
                $stepSize = floatval($filter['stepSize']);
                $quantity = floor($quantity / $stepSize) * $stepSize;
                break;
            }
        }
        
        return $quantity;
    }
}

/**
 * Helper function to get current timestamp in milliseconds
 */
if (!function_exists('millitime')) {
    function millitime() {
        return round(microtime(true) * 1000);
    }
}
