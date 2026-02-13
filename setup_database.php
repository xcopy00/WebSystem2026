<?php
/**
 * Database Setup Script - Creates all required tables
 * Run this file once to set up the database
 */

require_once 'config.php';
require_once 'db.php';

echo "Setting up database: " . DB_NAME . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Disable foreign key checks for clean drop
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // ==================== USERS TABLE ====================
    echo "Creating users table...\n";
    $db->exec("DROP TABLE IF EXISTS users");
    $db->exec("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            api_key TEXT,
            api_secret TEXT,
            role ENUM('User', 'VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6', 'Admin') NOT NULL DEFAULT 'User',
            license_expires DATETIME,
            is_active TINYINT(1) DEFAULT 1,
            max_bots INT DEFAULT 1,
            max_trades_per_day INT DEFAULT 10,
            api_calls_limit INT DEFAULT 100,
            trading_mode VARCHAR(10) DEFAULT 'testnet',
            ip_address VARCHAR(45),
            last_ip VARCHAR(45),
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Users table created\n";

    // ==================== LICENSES TABLE ====================
    echo "Creating licenses table...\n";
    $db->exec("DROP TABLE IF EXISTS licenses");
    $db->exec("
        CREATE TABLE licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) NOT NULL UNIQUE,
            type VARCHAR(20) NOT NULL,
            is_used TINYINT(1) DEFAULT 0,
            used_by INT,
            used_at DATETIME,
            expires_at DATETIME,
            validity_days INT DEFAULT 30,
            notes TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_code (code),
            INDEX idx_type (type),
            INDEX idx_used (is_used),
            FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Licenses table created\n";

    // Insert sample license codes (VIP 1-6)
    echo "Inserting sample license codes...\n";
    $db->exec("INSERT INTO licenses (code, type, expires_at, validity_days, created_at) VALUES 
        ('V1-VIP1-ABC123-XY78Z9P1', 'VIP1', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
        ('V1-VIP1-DEF456-GH12AB34', 'VIP1', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
        ('V1-VIP2-ABC123-XY78Z9P2', 'VIP2', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
        ('V1-VIP2-DEF456-GH12AB35', 'VIP2', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
        ('V1-VIP3-ABC123-XY78Z9P3', 'VIP3', DATE_ADD(NOW(), INTERVAL 60 DAY), 60, NOW()),
        ('V1-VIP3-DEF456-GH12AB36', 'VIP3', DATE_ADD(NOW(), INTERVAL 60 DAY), 60, NOW()),
        ('V1-VIP4-ABC123-XY78Z9P4', 'VIP4', DATE_ADD(NOW(), INTERVAL 90 DAY), 90, NOW()),
        ('V1-VIP4-DEF456-GH12AB37', 'VIP4', DATE_ADD(NOW(), INTERVAL 90 DAY), 90, NOW()),
        ('V1-VIP5-ABC123-XY78Z9P5', 'VIP5', DATE_ADD(NOW(), INTERVAL 180 DAY), 180, NOW()),
        ('V1-VIP5-DEF456-GH12AB38', 'VIP5', DATE_ADD(NOW(), INTERVAL 180 DAY), 180, NOW()),
        ('V1-VIP6-UNLIMITED-2024', 'VIP6', NULL, 365, NOW()),
        ('V1-ADMIN-MASTER-KEY', 'Admin', NULL, 365, NOW())");
    echo "✓ Sample license codes inserted\n";

    // ==================== FEATURES TABLE ====================
    echo "Creating features table...\n";
    $db->exec("DROP TABLE IF EXISTS features");
    $db->exec("
        CREATE TABLE features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            min_role VARCHAR(20) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_code (code),
            INDEX idx_min_role (min_role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Features table created\n";

    // Insert all features
    echo "Inserting features...\n";
    $db->exec("INSERT INTO features (name, code, min_role, description) VALUES
        ('Demo Trading', 'feature_demo', 'User', 'Trade with testnet/demo account'),
        ('Real Trading', 'feature_real_trading', 'VIP1', 'Trade with real Binance account'),
        ('Grid Trading Basic', 'feature_grid', 'VIP1', 'Basic grid trading strategy'),
        ('AI Trading RSI', 'feature_ai_rsi', 'VIP1', 'AI-based RSI trading strategy'),
        ('AI Trading MACD', 'feature_ai_macd', 'VIP1', 'AI-based MACD trading strategy'),
        ('AI Trading EMA', 'feature_ai_ema', 'VIP1', 'AI-based EMA trading strategy'),
        ('Trend Following', 'feature_trend', 'VIP2', 'Trend following strategies'),
        ('Advanced Indicators', 'feature_advanced_indicators', 'VIP2', 'Advanced technical indicators'),
        ('Custom Grid Strategies', 'feature_custom_grids', 'VIP3', 'Create custom grid strategies'),
        ('Multi-Grid Bot', 'feature_multi_grid', 'VIP3', 'Run multiple grid bots simultaneously'),
        ('Hedging Mode', 'feature_hedging', 'VIP3', 'Hedging strategies'),
        ('Martingale Strategy', 'feature_martingale', 'VIP4', 'Martingale strategy'),
        ('Scalping Strategy', 'feature_scalping', 'VIP4', 'Scalping trading strategy'),
        ('API Trading', 'feature_api', 'VIP5', 'External API access for trading'),
        ('Copy Trading', 'feature_copy', 'VIP5', 'Copy other traders'),
        ('Telegram Alerts', 'feature_telegram', 'VIP5', 'Telegram notifications'),
        ('Strategy Marketplace', 'feature_marketplace', 'VIP6', 'Buy/sell trading strategies'),
        ('Sell Strategies', 'feature_sell_strategies', 'VIP6', 'Sell your own strategies'),
        ('External API', 'feature_external_api', 'VIP6', 'Full external API access'),
        ('White Label', 'feature_whitelabel', 'VIP6', 'White label the platform'),
        ('Dedicated Support', 'feature_support', 'VIP6', 'Priority customer support'),
        ('Custom Development', 'feature_custom_dev', 'VIP6', 'Custom bot development')");
    echo "✓ Features inserted\n";

    // ==================== LICENSE HISTORY TABLE ====================
    echo "Creating license_history table...\n";
    $db->exec("DROP TABLE IF EXISTS license_history");
    $db->exec("
        CREATE TABLE license_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            license_code VARCHAR(50),
            action VARCHAR(20) NOT NULL,
            old_role VARCHAR(20),
            new_role VARCHAR(20),
            details TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ License history table created\n";

    // ==================== BOTS TABLE ====================
    echo "Creating bots table...\n";
    $db->exec("DROP TABLE IF EXISTS bots");
    $db->exec("
        CREATE TABLE bots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type ENUM('spot', 'future') NOT NULL DEFAULT 'spot',
            symbol VARCHAR(20) NOT NULL,
            strategy ENUM('grid', 'ai', 'trend', 'martingale', 'scalping') NOT NULL DEFAULT 'grid',
            config JSON,
            status ENUM('running', 'stopped', 'error') NOT NULL DEFAULT 'stopped',
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_symbol (symbol),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Bots table created\n";

    // ==================== TRADES TABLE ====================
    echo "Creating trades table...\n";
    $db->exec("DROP TABLE IF EXISTS trades");
    $db->exec("
        CREATE TABLE trades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT NOT NULL,
            user_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            side ENUM('buy', 'sell') NOT NULL,
            type ENUM('market', 'limit') NOT NULL,
            quantity DECIMAL(20, 10) NOT NULL,
            price DECIMAL(20, 10) NOT NULL,
            leverage INT DEFAULT 1,
            entry_price DECIMAL(20, 10),
            exit_price DECIMAL(20, 10),
            profit_loss DECIMAL(20, 10),
            status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            closed_at DATETIME,
            INDEX idx_bot_id (bot_id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Trades table created\n";

    // ==================== BOT LOGS TABLE ====================
    echo "Creating bot_logs table...\n";
    $db->exec("DROP TABLE IF EXISTS bot_logs");
    $db->exec("
        CREATE TABLE bot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT,
            user_id INT,
            message TEXT NOT NULL,
            type ENUM('info', 'trade', 'warning', 'error') NOT NULL DEFAULT 'info',
            data JSON,
            created_at DATETIME NOT NULL,
            INDEX idx_bot_id (bot_id),
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Bot logs table created\n";

    // ==================== GRID ORDERS TABLE ====================
    echo "Creating grid_orders table...\n";
    $db->exec("DROP TABLE IF EXISTS grid_orders");
    $db->exec("
        CREATE TABLE grid_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bot_id INT NOT NULL,
            level INT DEFAULT 0,
            price DECIMAL(20, 10) NOT NULL,
            buy_price DECIMAL(20, 10) DEFAULT 0,
            sell_price DECIMAL(20, 10) DEFAULT 0,
            quantity DECIMAL(20, 10) NOT NULL,
            type ENUM('buy', 'sell') NOT NULL,
            order_id VARCHAR(100),
            status ENUM('pending', 'filled', 'cancelled') NOT NULL DEFAULT 'pending',
            filled_price DECIMAL(20, 10),
            created_at DATETIME NOT NULL,
            INDEX idx_bot_id (bot_id),
            INDEX idx_status (status),
            INDEX idx_order_id (order_id),
            FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Grid orders table created\n";

    // ==================== GRID CONFIGS TABLE ====================
    echo "Creating grid_configs table...\n";
    $db->exec("DROP TABLE IF EXISTS grid_configs");
    $db->exec("
        CREATE TABLE grid_configs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            grid_count INT DEFAULT 10,
            grid_interval DECIMAL(20, 10) DEFAULT 30,
            investment DECIMAL(20, 10) DEFAULT 100,
            stop_loss DECIMAL(20, 10) DEFAULT 5,
            take_profit DECIMAL(20, 10) DEFAULT 10,
            created_at DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_symbol (symbol),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Grid configs table created\n";

    // ==================== API CALLS LOG TABLE ====================
    echo "Creating api_calls_log table...\n";
    $db->exec("DROP TABLE IF EXISTS api_calls_log");
    $db->exec("
        CREATE TABLE api_calls_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45),
            response_time INT,
            created_at DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_endpoint (endpoint),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ API calls log table created\n";

    // ==================== API KEYS TABLE ====================
    echo "Creating api_keys table...\n";
    $db->exec("DROP TABLE IF EXISTS api_keys");
    $db->exec("
        CREATE TABLE api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            api_key TEXT NOT NULL,
            api_secret TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ API keys table created\n";

    // ==================== VIP SUBSCRIPTIONS TABLE ====================
    echo "Creating vip_subscriptions table...\n";
    $db->exec("DROP TABLE IF EXISTS vip_subscriptions");
    $db->exec("
        CREATE TABLE vip_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(50) NOT NULL,
            type VARCHAR(20) NOT NULL,
            price DECIMAL(10, 2) DEFAULT 0,
            duration_days INT DEFAULT 30,
            max_bots INT DEFAULT 1,
            max_trades_per_day INT DEFAULT 10,
            api_calls_limit INT DEFAULT 100,
            features JSON,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_code (code),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ VIP subscriptions table created\n";

    // Insert VIP subscription plans
    echo "Inserting VIP subscription plans...\n";
    $db->exec("INSERT INTO vip_subscriptions (code, name, type, price, duration_days, max_bots, max_trades_per_day, api_calls_limit, features, is_active) VALUES
        ('User', 'Free User', 'User', 0, 0, 1, 10, 100, '[\"grid\"]', 1),
        ('VIP1', 'Silver VIP', 'VIP1', 29.99, 30, 3, 100, 1000, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\"]', 1),
        ('VIP2', 'Gold VIP', 'VIP2', 49.99, 30, 5, 200, 5000, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\", \"trend\", \"advanced_indicators\"]', 1),
        ('VIP3', 'Platinum VIP', 'VIP3', 99.99, 30, 10, 500, 10000, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\", \"trend\", \"advanced_indicators\", \"custom_grids\", \"multi_grid\", \"hedging\"]', 1),
        ('VIP4', 'Diamond VIP', 'VIP4', 199.99, 30, 20, 1000, 25000, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\", \"trend\", \"advanced_indicators\", \"custom_grids\", \"multi_grid\", \"hedging\", \"martingale\", \"scalping\"]', 1),
        ('VIP5', 'Elite VIP', 'VIP5', 499.99, 30, 50, -1, 100000, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\", \"trend\", \"advanced_indicators\", \"custom_grids\", \"multi_grid\", \"hedging\", \"martingale\", \"scalping\", \"api\", \"copy\", \"telegram\"]', 1),
        ('VIP6', 'Ultimate VIP', 'VIP6', 999.99, 30, -1, -1, -1, '[\"grid\", \"ai_rsi\", \"ai_macd\", \"ai_ema\", \"trend\", \"advanced_indicators\", \"custom_grids\", \"multi_grid\", \"hedging\", \"martingale\", \"scalping\", \"api\", \"copy\", \"telegram\", \"marketplace\", \"sell_strategies\", \"external_api\", \"whitelabel\", \"support\", \"custom_dev\"]', 1)");
    echo "✓ VIP subscription plans inserted\n";

    echo "\n========================================\n";
    echo "✓ All tables created successfully!\n";
    echo "========================================\n\n";

    // Insert sample users
    echo "Creating sample users...\n";
    $db->exec("
        INSERT INTO users (email, password, name, role, max_bots, max_trades_per_day, api_calls_limit, trading_mode, created_at) VALUES 
        ('demo@example.com', '" . password_hash('demo123', PASSWORD_DEFAULT) . "', 'Demo User', 'User', 1, 10, 100, 'testnet', NOW()),
        ('admin@example.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Admin User', 'Admin', 999, 999999, 999999, 'real', NOW())
    ");
    echo "✓ Sample users created\n";
    echo "  - Demo user: demo@example.com / demo123 (Testnet mode)\n";
    echo "  - Admin user: admin@example.com / admin123 (Full access)\n\n";

    echo "========================================\n";
    echo "📋 Plan Summary:\n";
    echo "========================================\n";
    echo "User Roles & Limits:\n";
    echo "  User   : 1 bot, 10 trades/day, 100 API calls, testnet\n";
    echo "  VIP 1  : 3 bots, 100 trades/day, 1,000 API calls\n";
    echo "  VIP 2  : 5 bots, 200 trades/day, 5,000 API calls\n";
    echo "  VIP 3  : 10 bots, 500 trades/day, 10,000 API calls\n";
    echo "  VIP 4  : 20 bots, 1,000 trades/day, 25,000 API calls\n";
    echo "  VIP 5  : 50 bots, unlimited trades/day, 100,000 API calls\n";
    echo "  VIP 6  : Unlimited everything\n";
    echo "========================================\n\n";

    echo "Database setup complete!\n";
    echo "You can now access the application at: " . APP_URL . "\n";
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
