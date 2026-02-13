<?php
/**
 * Bot Worker - Runs all active trading bots in the background
 * 
 * This script should be run as a cron job or daemon to execute trading bots
 * Usage: php worker.php [--bot-id=N] [--daemon]
 * 
 * Options:
 *   --bot-id=N    Run specific bot only
 *   --daemon      Run as daemon (continuous loop)
 *   --once        Run once and exit
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/binance-api.php';

class BotWorker {
    private $pdo;
    private $running = true;
    private $loopInterval = 10; // seconds between iterations
    
    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all running bots
     */
    public function getRunningBots() {
        $stmt = $this->pdo->query("SELECT * FROM bots WHERE status = 'running'");
        return $stmt->fetchAll();
    }
    
    /**
     * Get bot by ID
     */
    public function getBotById($botId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bots WHERE id = ?");
        $stmt->execute([$botId]);
        return $stmt->fetch();
    }
    
    /**
     * Process a single bot
     */
    public function processBot($bot) {
        $botId = $bot['id'];
        $strategy = $bot['strategy'];
        $type = $bot['type'];
        
        // Log start
        $this->log($botId, "Processing {$strategy} bot for {$bot['symbol']}", 'info');
        
        try {
            // Get user API keys
            $user = $this->getUserById($bot['user_id']);
            if (empty($user['api_key']) || empty($user['api_secret'])) {
                $this->log($botId, "User has no API keys configured", 'error');
                $this->stopBot($botId);
                return false;
            }
            
            // Initialize Binance API
            $isLive = ($user['trading_mode'] === 'live');
            $binance = new BinanceAPI($user['api_key'], $user['api_secret'], $isLive);
            
            // Process based on strategy
            switch ($strategy) {
                case 'grid':
                    return $this->processGridBot($binance, $bot);
                case 'dca':
                    return $this->processDCABot($binance, $bot);
                case 'arbitrage':
                    return $this->processArbitrageBot($binance, $bot);
                case 'smart':
                    return $this->processSmartBot($binance, $bot);
                default:
                    $this->log($botId, "Unknown strategy: {$strategy}", 'error');
                    return false;
            }
        } catch (Exception $e) {
            $this->log($botId, "Error: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Process Grid Trading Bot
     */
    private function processGridBot($binance, $bot) {
        $botId = $bot['id'];
        $symbol = $bot['symbol'];
        $config = json_decode($bot['config'] ?? '{}', true);
        
        $gridCount = $config['grid_count'] ?? 10;
        $gridInterval = $config['grid_interval'] ?? 1; // percentage
        $investment = $config['investment'] ?? 100;
        
        // Get current price
        $price = $binance->getSpotPrice($symbol);
        if (!$price) {
            $this->log($botId, "Could not get price for {$symbol}", 'error');
            return false;
        }
        
        // Calculate grid levels
        $lowerPrice = $price * (1 - ($gridInterval * $gridCount / 100) / 2);
        $upperPrice = $price * (1 + ($gridInterval * $gridCount / 100) / 2);
        $gridSize = ($upperPrice - $lowerPrice) / $gridCount;
        
        // Check for buy orders at lower levels
        $this->checkAndPlaceGridOrders($binance, $bot, $symbol, $price, $lowerPrice, $gridSize, 'buy');
        
        // Check for sell orders at upper levels
        $this->checkAndPlaceGridOrders($binance, $bot, $symbol, $price, $lowerPrice, $gridSize, 'sell');
        
        // Update bot status
        $this->updateBotPrice($botId, $price);
        
        $this->log($botId, "Grid bot processed. Price: {$price}", 'debug');
        return true;
    }
    
    /**
     * Process DCA Bot
     */
    private function processDCABot($binance, $bot) {
        $botId = $bot['id'];
        $symbol = $bot['symbol'];
        $config = json_decode($bot['config'] ?? '{}', true);
        
        $dcaAmount = $config['dca_amount'] ?? 50;
        $dcaInterval = $config['dca_interval'] ?? 60; // minutes
        $maxDcaOrders = $config['max_dca_orders'] ?? 5;
        $dcaMultiplier = $config['dca_multiplier'] ?? 1.0;
        
        // Check if it's time for next DCA
        $lastDca = $bot['last_trade_at'] ?? null;
        if ($lastDca && strtotime($lastDca) > strtotime("-{$dcaInterval} minutes")) {
            $this->log($botId, "Not yet time for DCA", 'debug');
            return true;
        }
        
        // Get current price
        $price = $binance->getSpotPrice($symbol);
        if (!$price) {
            $this->log($botId, "Could not get price for {$symbol}", 'error');
            return false;
        }
        
        // Place DCA buy order
        $quantity = $dcaAmount / $price;
        $order = $binance->placeOrder($symbol, 'BUY', 'MARKET', $quantity, null);
        
        if ($order) {
            $this->log($botId, "DCA order placed: {$quantity} {$symbol} at {$price}", 'trade');
            $this->recordTrade($bot, $order, 'buy', $price, $quantity);
        }
        
        return true;
    }
    
    /**
     * Process Arbitrage Bot
     */
    private function processArbitrageBot($binance, $bot) {
        $botId = $bot['id'];
        $symbol = $bot['symbol'];
        $config = json_decode($bot['config'] ?? '{}', true);
        
        $minProfit = $config['min_profit'] ?? 0.1; // percentage
        
        // Get prices from different sources
        $spotPrice = $binance->getSpotPrice($symbol);
        // For futures, we'd check futures API
        
        $this->log($botId, "Arbitrage check - Spot: {$spotPrice}", 'debug');
        
        // Arbitrage logic would go here
        return true;
    }
    
    /**
     * Process Smart Bot (AI-powered)
     */
    private function processSmartBot($binance, $bot) {
        $botId = $bot['id'];
        $symbol = $bot['symbol'];
        $config = json_decode($bot['config'] ?? '{}', true);
        
        // Get market data
        $price = $binance->getSpotPrice($symbol);
        $ticker = $binance->get24hrTicker($symbol);
        
        // Simple AI decision based on RSI and price action
        $rsi = $this->calculateRSI($binance, $symbol, 14);
        
        $action = 'hold';
        if ($rsi < 30) {
            $action = 'buy';
        } elseif ($rsi > 70) {
            $action = 'sell';
        }
        
        $this->log($botId, "Smart bot - RSI: {$rsi}, Action: {$action}", 'info');
        
        if ($action !== 'hold') {
            $quantity = ($config['investment'] ?? 100) / $price;
            $order = $binance->placeOrder($symbol, strtoupper($action), 'MARKET', $quantity, null);
            if ($order) {
                $this->log($botId, "Smart order placed: {$action} {$quantity} {$symbol}", 'trade');
            }
        }
        
        return true;
    }
    
    /**
     * Check and place grid orders
     */
    private function checkAndPlaceGridOrders($binance, $bot, $symbol, $currentPrice, $lowerPrice, $gridSize, $side) {
        $botId = $bot['id'];
        $config = json_decode($bot['config'] ?? '{}', true);
        $investment = $config['investment'] ?? 100;
        
        // Calculate order quantity
        $quantity = $investment / $currentPrice / 10; // Divide investment across 10 levels
        
        // Find the appropriate grid level
        $level = floor(($currentPrice - $lowerPrice) / $gridSize);
        $targetPrice = $lowerPrice + ($level * $gridSize);
        
        if ($side === 'buy' && $currentPrice > $targetPrice) {
            // Place buy order at this level
            $order = $binance->placeOrder($symbol, 'BUY', 'LIMIT', $quantity, $targetPrice);
            if ($order) {
                $this->log($botId, "Grid buy order: {$quantity} @ {$targetPrice}", 'trade');
                $this->recordOrder($bot, $order, 'buy', $targetPrice, $quantity);
            }
        } elseif ($side === 'sell' && $currentPrice < $targetPrice) {
            // Place sell order at this level
            $order = $binance->placeOrder($symbol, 'SELL', 'LIMIT', $quantity, $targetPrice);
            if ($order) {
                $this->log($botId, "Grid sell order: {$quantity} @ {$targetPrice}", 'trade');
                $this->recordOrder($bot, $order, 'sell', $targetPrice, $quantity);
            }
        }
    }
    
    /**
     * Calculate RSI (simplified)
     */
    private function calculateRSI($binance, $symbol, $period) {
        $klines = $binance->getKlines($symbol, '1h', $period + 1);
        if (!$klines || count($klines) < $period + 1) {
            return 50; // Default neutral RSI
        }
        
        $gains = 0;
        $losses = 0;
        
        for ($i = 1; $i < count($klines); $i++) {
            $change = $klines[$i][4] - $klines[$i-1][4]; // close price change
            if ($change > 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }
        
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        
        if ($avgLoss == 0) return 100;
        
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));
        
        return $rsi;
    }
    
    /**
     * Record order in database
     */
    private function recordOrder($bot, $order, $side, $price, $quantity) {
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_orders (bot_id, order_id, side, type, status, price, quantity, created_at)
            VALUES (?, ?, ?, 'limit', 'open', ?, ?, NOW())
        ");
        $stmt->execute([$bot['id'], $order['orderId'] ?? uniqid(), $side, $price, $quantity]);
    }
    
    /**
     * Record trade in database
     */
    private function recordTrade($bot, $order, $side, $price, $quantity) {
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_trades (bot_id, side, price, quantity, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$bot['id'], $side, $price, $quantity]);
        
        // Update last trade time
        $stmt = $this->pdo->prepare("UPDATE bots SET last_trade_at = NOW(), total_invested = total_invested + ? WHERE id = ?");
        $stmt->execute([$quantity * $price, $bot['id']]);
    }
    
    /**
     * Update bot current price
     */
    private function updateBotPrice($botId, $price) {
        $stmt = $this->pdo->prepare("UPDATE bots SET current_price = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$price, $botId]);
    }
    
    /**
     * Stop a bot
     */
    public function stopBot($botId) {
        $stmt = $this->pdo->prepare("UPDATE bots SET status = 'stopped', stopped_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$botId]);
        $this->log($botId, "Bot stopped", 'info');
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Log message
     */
    private function log($botId, $message, $type = 'info') {
        $stmt = $this->pdo->prepare("INSERT INTO bot_logs (bot_id, type, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$botId, $type, $message]);
        
        // Also output to console if running interactively
        echo "[" . date('Y-m-d H:i:s') . "] [{$type}] Bot {$botId}: {$message}\n";
    }
    
    /**
     * Run the worker
     */
    public function run($options = []) {
        $botId = $options['botId'] ?? null;
        $daemon = $options['daemon'] ?? false;
        $once = $options['once'] ?? false;
        
        echo "Bot Worker started at " . date('Y-m-d H:i:s') . "\n";
        echo "Mode: " . ($daemon ? "Daemon" : "Single run") . "\n";
        
        if ($botId) {
            echo "Processing bot ID: {$botId}\n";
        }
        
        if ($daemon) {
            $this->runDaemon($botId);
        } else {
            $this->runOnce($botId);
        }
    }
    
    /**
     * Run once and exit
     */
    private function runOnce($botId = null) {
        if ($botId) {
            $bot = $this->getBotById($botId);
            if ($bot && $bot['status'] === 'running') {
                $this->processBot($bot);
            }
        } else {
            $bots = $this->getRunningBots();
            foreach ($bots as $bot) {
                $this->processBot($bot);
            }
        }
    }
    
    /**
     * Run as daemon (continuous loop)
     */
    private function runDaemon($botId = null) {
        while ($this->running) {
            $startTime = time();
            
            try {
                $this->runOnce($botId);
            } catch (Exception $e) {
                echo "Error in main loop: " . $e->getMessage() . "\n";
            }
            
            // Sleep until next iteration
            $elapsed = time() - $startTime;
            $sleepTime = max(0, $this->loopInterval - $elapsed);
            sleep($sleepTime);
        }
    }
}

// Parse command line options
$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--bot-id=') === 0) {
        $options['botId'] = (int)str_replace('--bot-id=', '', $arg);
    } elseif ($arg === '--daemon') {
        $options['daemon'] = true;
    } elseif ($arg === '--once' || $arg === '--run-once') {
        $options['once'] = true;
    }
}

// Run worker
$worker = new BotWorker();
$worker->run($options);
