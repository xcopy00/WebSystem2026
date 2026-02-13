<?php
/**
 * Grid Trading Bot - PHP CLI Script
 * Run with: php grid-bot.php --bot-id=<id>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/binance-api.php';

class GridTradingBot {
    private $botId;
    private $bot;
    private $config;
    private $db;
    private $client;
    private $isRunning = false;
    private $currentPrice = null;
    private $intervalId = null;

    public function __construct($botId) {
        $this->botId = $botId;
        $this->db = new DBOperations();
    }

    /**
     * Initialize the bot
     */
    public function initialize() {
        echo "[" . date('Y-m-d H:i:s') . "] Initializing Grid Bot #{$this->botId}...\n";
        
        $this->bot = $this->db->getBotById($this->botId);
        if (!$this->bot) {
            throw new Exception("Bot #{$this->botId} not found");
        }

        $this->config = json_decode($this->bot['config'], true) ?: [];
        
        // Set defaults
        $this->config['gridType'] = $this->config['gridType'] ?? 'arithmetic';
        $this->config['gridCount'] = $this->config['gridCount'] ?? DEFAULT_GRID_COUNT;
        $this->config['stopLossPercent'] = $this->config['stopLossPercent'] ?? DEFAULT_STOP_LOSS_PERCENT;
        $this->config['investmentAmount'] = $this->config['investmentAmount'] ?? 1000;
        $this->config['interval'] = $this->config['interval'] ?? DEFAULT_INTERVAL;

        // Get API keys
        $apiKeys = $this->db->getApiKeys($this->bot['user_id']);
        if (!$apiKeys) {
            throw new Exception("API keys not found for user");
        }

        // Initialize Binance client
        $this->client = new BinanceAPI($apiKeys['api_key'], $apiKeys['api_secret'], $this->bot['type'] === 'future');

        // Validate API keys
        $validation = $this->client->validateApiKeys();
        if (!$validation['valid']) {
            throw new Exception("API validation failed: " . $validation['error']);
        }

        // Get current price
        $this->currentPrice = $this->client->getSpotPrice($this->bot['symbol']);

        // Auto-set grid prices if not configured
        if (empty($this->config['upperPrice']) || empty($this->config['lowerPrice'])) {
            $this->autoSetGridPrices();
        }

        echo "[" . date('Y-m-d H:i:s') . "] Grid Bot initialized: {$this->bot['symbol']} | Range: {$this->config['lowerPrice']} - {$this->config['upperPrice']} | Levels: {$this->config['gridCount']}\n";
        
        $this->db->createLog($this->botId, $this->bot['user_id'], 
            "Grid Bot initialized for {$this->bot['symbol']}. Range: {$this->config['lowerPrice']} - {$this->config['upperPrice']}", 
            'info');
    }

    /**
     * Auto-set grid prices based on current price
     */
    private function autoSetGridPrices() {
        $rangePercent = 0.05; // 5% range
        $this->config['lowerPrice'] = round($this->currentPrice * (1 - $rangePercent), 2);
        $this->config['upperPrice'] = round($this->currentPrice * (1 + $rangePercent), 2);
        
        echo "[" . date('Y-m-d H:i:s') . "] Auto-set grid: {$this->config['lowerPrice']} - {$this->config['upperPrice']}\n";
    }

    /**
     * Calculate grid levels
     */
    public function calculateGridLevels() {
        $grids = [];
        $upperPrice = $this->config['upperPrice'];
        $lowerPrice = $this->config['lowerPrice'];
        $gridCount = $this->config['gridCount'];
        $gridType = $this->config['gridType'];

        if ($gridType === 'geometric') {
            // Geometric progression (equal percentage)
            $ratio = pow($upperPrice / $lowerPrice, 1 / ($gridCount - 1));
            for ($i = 0; $i < $gridCount; $i++) {
                $price = $lowerPrice * pow($ratio, $i);
                $grids[] = [
                    'level' => $i,
                    'price' => round($price, 2),
                    'buyPrice' => round($price * 0.9995, 2),
                    'sellPrice' => round($price * 1.0005, 2)
                ];
            }
        } else {
            // Arithmetic progression (equal price intervals)
            $interval = ($upperPrice - $lowerPrice) / ($gridCount - 1);
            for ($i = 0; $i < $gridCount; $i++) {
                $price = $lowerPrice + ($interval * $i);
                $grids[] = [
                    'level' => $i,
                    'price' => round($price, 2),
                    'buyPrice' => round($price * 0.9995, 2),
                    'sellPrice' => round($price * 1.0005, 2)
                ];
            }
        }

        return $grids;
    }

    /**
     * Calculate order quantity per grid
     */
    public function calculateOrderQuantity() {
        return $this->config['investmentAmount'] / $this->config['gridCount'];
    }

    /**
     * Start the bot
     */
    public function start() {
        if ($this->isRunning) {
            echo "[" . date('Y-m-d H:i:s') . "] Bot is already running\n";
            return;
        }

        $this->initialize();
        $this->isRunning = true;
        $this->db->updateBotStatus($this->botId, 'running');

        // Place initial grid orders
        $this->placeGridOrders();

        echo "[" . date('Y-m-d H:i:s') . "] Grid Bot #{$this->botId} started\n";

        // Start monitoring loop
        while ($this->isRunning) {
            try {
                $this->monitoringLoop();
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
                $this->db->createLog($this->botId, $this->bot['user_id'], "Error: " . $e->getMessage(), 'error');
            }

            sleep($this->config['interval']);
        }
    }

    /**
     * Stop the bot
     */
    public function stop() {
        $this->isRunning = false;
        
        // Cancel all pending orders
        $this->cancelAllPendingOrders();
        
        $this->db->updateBotStatus($this->botId, 'stopped');
        $this->db->createLog($this->botId, $this->bot['user_id'], 'Grid Bot stopped', 'info');
        
        echo "[" . date('Y-m-d H:i:s') . "] Grid Bot #{$this->botId} stopped\n";
    }

    /**
     * Place initial grid orders
     */
    public function placeGridOrders() {
        $grids = $this->calculateGridLevels();
        $quantity = $this->calculateOrderQuantity();

        foreach ($grids as $grid) {
            // Only place buy orders below current price
            if ($grid['price'] < $this->currentPrice) {
                $this->placeBuyOrder($grid['buyPrice'], $quantity);
            }
        }

        echo "[" . date('Y-m-d H:i:s') . "] Initial grid orders placed\n";
    }

    /**
     * Place a buy order
     */
    private function placeBuyOrder($price, $quantity) {
        try {
            $order = $this->client->spotLimitBuy($this->bot['symbol'], $quantity, $price);
            
            $this->db->createGridOrder($this->botId, [
                'level' => 0,
                'price' => $price,
                'buyPrice' => $price,
                'sellPrice' => 0,
                'quantity' => $quantity,
                'type' => 'buy',
                'order_id' => $order['orderId']
            ]);

            echo "[" . date('Y-m-d H:i:s') . "] Placed BUY order: {$quantity} @ {$price} (OrderID: {$order['orderId']})\n";
            
            return $order;
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to place buy order: " . $e->getMessage() . "\n";
            $this->db->createLog($this->botId, $this->bot['user_id'], "Failed to place buy order: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Place a sell order
     */
    private function placeSellOrder($price, $quantity) {
        try {
            $order = $this->client->spotLimitSell($this->bot['symbol'], $quantity, $price);
            
            $this->db->createGridOrder($this->botId, [
                'level' => 0,
                'price' => $price,
                'buyPrice' => 0,
                'sellPrice' => $price,
                'quantity' => $quantity,
                'type' => 'sell',
                'order_id' => $order['orderId']
            ]);

            echo "[" . date('Y-m-d H:i:s') . "] Placed SELL order: {$quantity} @ {$price} (OrderID: {$order['orderId']})\n";
            
            return $order;
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to place sell order: " . $e->getMessage() . "\n";
            $this->db->createLog($this->botId, $this->bot['user_id'], "Failed to place sell order: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Check and replace filled orders
     */
    private function checkAndReplaceOrders() {
        $pendingOrders = $this->db->getPendingGridOrders($this->botId);
        $grids = $this->calculateGridLevels();

        foreach ($pendingOrders as $order) {
            try {
                $status = $this->client->getOrderStatus($this->bot['symbol'], $order['order_id']);

                if ($status['status'] === 'FILLED') {
                    $this->db->markGridOrderFilled($order['id'], $status['price']);
                    
                    echo "[" . date('Y-m-d H:i:s') . "] Order #{$order['order_id']} FILLED @ {$status['price']}\n";
                    
                    $this->db->createLog($this->botId, $this->bot['user_id'], 
                        "Order filled: {$order['type']} {$order['quantity']} @ {$status['price']}", 'trade');

                    // Place opposite order
                    if ($order['type'] === 'buy') {
                        // Find next grid level up
                        foreach ($grids as $grid) {
                            if ($grid['price'] > $order['price']) {
                                $this->placeSellOrder($grid['sellPrice'], $order['quantity']);
                                break;
                            }
                        }
                    } else {
                        // Find next grid level down
                        foreach (array_reverse($grids) as $grid) {
                            if ($grid['price'] < $order['price']) {
                                $this->placeBuyOrder($grid['buyPrice'], $order['quantity']);
                                break;
                            }
                        }
                    }
                } elseif (in_array($status['status'], ['CANCELED', 'EXPIRED'])) {
                    $this->db->updateGridOrderStatus($order['id'], 'cancelled');
                }
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Error checking order {$order['order_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Monitoring loop
     */
    private function monitoringLoop() {
        // Get current price
        $this->currentPrice = $this->client->getSpotPrice($this->bot['symbol']);
        
        echo "[" . date('Y-m-d H:i:s') . "] Price: {$this->bot['symbol']} = {$this->currentPrice}\n";

        // Check filled orders and replace
        $this->checkAndReplaceOrders();

        // Calculate total profit from completed trades
        $totalProfit = $this->calculateTotalProfit();
        
        // Check stop loss
        if ($totalProfit < -$this->config['stopLossPercent']) {
            echo "[" . date('Y-m-d H:i:s') . "] Stop loss triggered! Profit: {$totalProfit}%\n";
            $this->db->createLog($this->botId, $this->bot['user_id'], "Stop loss triggered! Total P/L: {$totalProfit}%", 'error');
            $this->emergencyClose();
            return;
        }

        // Check if price moved out of grid range
        if ($this->currentPrice < $this->config['lowerPrice'] || $this->currentPrice > $this->config['upperPrice']) {
            echo "[" . date('Y-m-d H:i:s') . "] Warning: Price moved out of grid range!\n";
            $this->db->createLog($this->botId, $this->bot['user_id'], 
                "Price moved out of grid range: {$this->currentPrice}", 'warning');
        }
    }

    /**
     * Calculate total profit from completed trades
     */
    private function calculateTotalProfit() {
        $trades = $this->db->getTradesByBotId($this->botId);
        $closedTrades = array_filter($trades, function($t) {
            return $t['status'] === 'closed';
        });

        if (empty($closedTrades)) {
            return 0;
        }

        $totalProfit = 0;
        foreach ($closedTrades as $trade) {
            $totalProfit += floatval($trade['profit_loss']);
        }

        return round($totalProfit, 2);
    }

    /**
     * Cancel all pending orders
     */
    private function cancelAllPendingOrders() {
        $pendingOrders = $this->db->getPendingGridOrders($this->botId);

        foreach ($pendingOrders as $order) {
            try {
                $this->client->cancelOrder($this->bot['symbol'], $order['order_id']);
                $this->db->updateGridOrderStatus($order['id'], 'cancelled');
                echo "[" . date('Y-m-d H:i:s') . "] Cancelled order #{$order['order_id']}\n";
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to cancel order {$order['order_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Emergency close all positions
     */
    private function emergencyClose() {
        $this->cancelAllPendingOrders();
        $this->stop();
    }

    /**
     * Get bot status
     */
    public function getStatus() {
        $gridOrders = $this->db->getGridOrdersByBotId($this->botId);
        $pendingCount = count(array_filter($gridOrders, function($o) { return $o['status'] === 'pending'; }));
        $filledCount = count(array_filter($gridOrders, function($o) { return $o['status'] === 'filled'; }));

        return [
            'botId' => $this->botId,
            'symbol' => $this->bot['symbol'],
            'type' => $this->bot['type'],
            'status' => $this->isRunning ? 'running' : 'stopped',
            'currentPrice' => $this->currentPrice,
            'gridConfig' => [
                'gridType' => $this->config['gridType'],
                'upperPrice' => $this->config['upperPrice'],
                'lowerPrice' => $this->config['lowerPrice'],
                'gridCount' => $this->config['gridCount'],
                'investmentAmount' => $this->config['investmentAmount'],
                'stopLossPercent' => $this->config['stopLossPercent']
            ],
            'orders' => [
                'pending' => $pendingCount,
                'filled' => $filledCount
            ],
            'totalProfit' => $this->calculateTotalProfit()
        ];
    }
}

// ==================== CLI HANDLER ====================
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Parse command line arguments
    $options = getopt('', ['bot-id:', 'start', 'stop', 'status']);
    
    if (!isset($options['bot-id'])) {
        echo "Usage: php grid-bot.php --bot-id=<id> [--start|--stop|--status]\n";
        exit(1);
    }

    $botId = (int)$options['bot-id'];

    try {
        $bot = new GridTradingBot($botId);

        if (isset($options['stop']) || isset($options['status'])) {
            // For stop/status, just initialize to get connection
            $bot->initialize();
        }

        if (isset($options['start'])) {
            echo "Starting Grid Bot...\n";
            $bot->start();
        } elseif (isset($options['stop'])) {
            echo "Stopping Grid Bot...\n";
            $bot->stop();
        } elseif (isset($options['status'])) {
            $status = $bot->getStatus();
            echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Usage: php grid-bot.php --bot-id=<id> --start\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
