<?php
/**
 * VIP Subscriptions and Bots Database Setup
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "Setting up VIP Subscriptions and Bots tables...\n";

try {
    $pdo = Database::getInstance()->getConnection();

    // Create VIP Subscriptions Table
    echo "Creating vip_subscriptions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vip_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            type ENUM('User', 'VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6', 'Admin') NOT NULL DEFAULT 'User',
            price DECIMAL(10, 2) DEFAULT 0,
            duration_days INT DEFAULT 30,
            max_bots INT DEFAULT 1,
            max_trades_per_day INT DEFAULT 10,
            api_calls_limit INT DEFAULT 100,
            features JSON,
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert VIP Subscription Plans
    echo "Inserting VIP subscription plans...\n";
    $plans = [
        ['VIP1', 'VIP 1 - Starter', 'VIP1', 9.99, 30, 2, 50, 500, '["grid", "dca"]'],
        ['VIP2', 'VIP 2 - Trader', 'VIP2', 24.99, 30, 5, 100, 1000, '["grid", "dca", "arbitrage"]'],
        ['VIP3', 'VIP 3 - Pro', 'VIP3', 49.99, 30, 10, 500, 2500, '["grid", "dca", "arbitrage", "smart"]'],
        ['VIP4', 'VIP 4 - Elite', 'VIP4', 99.99, 30, 20, 1000, 5000, '["grid", "dca", "arbitrage", "smart", "api_access"]'],
        ['VIP5', 'VIP 5 - Master', 'VIP5', 199.99, 30, 50, 5000, 10000, '["grid", "dca", "arbitrage", "smart", "api_access", "priority"]'],
        ['VIP6', 'VIP 6 - Legend', 'VIP6', 499.99, 30, 999, 99999, 99999, '["grid", "dca", "arbitrage", "smart", "api_access", "priority", "custom", "whitelabel"]']
    ];

    foreach ($plans as $plan) {
        $stmt = $pdo->prepare("
            INSERT INTO vip_subscriptions (code, name, type, price, duration_days, max_bots, max_trades_per_day, api_calls_limit, features)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                price = VALUES(price),
                features = VALUES(features)
        ");
        $stmt->execute($plan);
    }

    // Create Bots Table
    echo "Creating bots table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type ENUM('spot', 'future') NOT NULL DEFAULT 'spot',
            strategy ENUM('grid', 'dca', 'arbitrage', 'smart') NOT NULL DEFAULT 'grid',
            symbol VARCHAR(20) NOT NULL,
            status ENUM('running', 'stopped', 'paused', 'error') NOT NULL DEFAULT 'stopped',
            
            -- Investment Settings
            investment DECIMAL(20, 8) DEFAULT 0,
            leverage INT DEFAULT 1,
            
            -- Grid Settings
            grid_count INT DEFAULT 10,
            grid_interval DECIMAL(20, 8) DEFAULT 0,
            lower_price DECIMAL(20, 8),
            upper_price DECIMAL(20, 8),
            
            -- DCA Settings
            dca_amount DECIMAL(20, 8),
            dca_interval INT,
            dca_multiplier DECIMAL(10, 4) DEFAULT 1.0,
            max_dca_orders INT,
            
            -- Take Profit / Stop Loss
            take_profit DECIMAL(10, 4),
            stop_loss DECIMAL(10, 4),
            
            -- Filters
            min_profit DECIMAL(10, 4) DEFAULT 0.1,
            
            -- Status
            current_price DECIMAL(20, 8),
            total_invested DECIMAL(20, 8) DEFAULT 0,
            total_profit DECIMAL(20, 8) DEFAULT 0,
            open_orders INT DEFAULT 0,
            last_trade_at DATETIME,
            
            -- Timestamps
            started_at DATETIME,
            stopped_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_symbol (symbol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create Bot Orders Table
    echo "Creating bot_orders table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT NOT NULL,
            order_id VARCHAR(100),
            side ENUM('buy', 'sell') NOT NULL,
            type ENUM('limit', 'market', 'stop_limit') NOT NULL,
            status ENUM('open', 'filled', 'cancelled', 'rejected') NOT NULL DEFAULT 'open',
            price DECIMAL(20, 8),
            quantity DECIMAL(20, 8),
            filled_price DECIMAL(20, 8),
            filled_quantity DECIMAL(20, 8),
            profit DECIMAL(20, 8) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            filled_at DATETIME,
            
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
            INDEX idx_bot_id (bot_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create Bot Trades Table
    echo "Creating bot_trades table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT NOT NULL,
            order_id INT,
            side ENUM('buy', 'sell') NOT NULL,
            price DECIMAL(20, 8) NOT NULL,
            quantity DECIMAL(20, 8) NOT NULL,
            fee DECIMAL(20, 8) DEFAULT 0,
            profit DECIMAL(20, 8) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
            INDEX idx_bot_id (bot_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create Bot Logs Table
    echo "Creating bot_logs table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT NOT NULL,
            type ENUM('info', 'warning', 'error', 'trade', 'debug') NOT NULL DEFAULT 'info',
            message TEXT,
            data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
            INDEX idx_bot_id (bot_id),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "VIP Subscriptions and Bots tables created successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
