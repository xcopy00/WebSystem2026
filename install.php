<?php
/**
 * Installation Script - One-click database setup
 * Access this file in browser to set up the database
 */

require_once 'db.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
$logs = [];
$success = false;

function addLog($message, $type = 'info') {
    global $logs;
    $logs[] = ['message' => $message, 'type' => $type];
}

if ($step === 1) {
    // Run database setup
    ob_start();
    try {
        addLog('Starting database setup...', 'info');
        
        $db = Database::getInstance()->getConnection();
        addLog('Database connection successful', 'success');
        
        // Create users table
        addLog('Creating users table...', 'info');
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
                created_at DATETIME NOT NULL,
                updated_at DATETIME,
                INDEX idx_email (email),
                INDEX idx_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('Users table created', 'success');
        
        // Create licenses table
        addLog('Creating licenses table...', 'info');
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
                FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('Licenses table created', 'success');
        
        // Insert sample licenses
        addLog('Inserting sample license codes...', 'info');
        $db->exec("INSERT INTO licenses (code, type, expires_at, validity_days, created_at) VALUES 
            ('V1-VIP1-ABC123-XY78Z9P1', 'VIP1', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
            ('V1-VIP2-ABC123-XY78Z9P2', 'VIP2', DATE_ADD(NOW(), INTERVAL 30 DAY), 30, NOW()),
            ('V1-VIP6-UNLIMITED-2024', 'VIP6', NULL, 365, NOW())");
        addLog('Sample licenses inserted', 'success');
        
        // Create features table
        addLog('Creating features table...', 'info');
        $db->exec("DROP TABLE IF EXISTS features");
        $db->exec("
            CREATE TABLE features (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                code VARCHAR(50) NOT NULL UNIQUE,
                min_role VARCHAR(20) NOT NULL,
                description TEXT,
                is_active TINYINT(1) DEFAULT 1,
                INDEX idx_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("INSERT INTO features (name, code, min_role, description) VALUES
            ('Demo Trading', 'feature_demo', 'User', 'Trade with testnet/demo account'),
            ('Real Trading', 'feature_real_trading', 'VIP1', 'Trade with real Binance account'),
            ('Grid Trading Basic', 'feature_grid', 'VIP1', 'Basic grid trading strategy')");
        addLog('Features table created', 'success');
        
        // Create bots table
        addLog('Creating bots table...', 'info');
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
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('Bots table created', 'success');
        
        // Create trades table
        addLog('Creating trades table...', 'info');
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
                profit_loss DECIMAL(20, 10),
                status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL,
                INDEX idx_bot_id (bot_id),
                INDEX idx_user_id (user_id),
                FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('Trades table created', 'success');
        
        // Create bot_logs table
        addLog('Creating bot_logs table...', 'info');
        $db->exec("DROP TABLE IF EXISTS bot_logs");
        $db->exec("
            CREATE TABLE bot_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bot_id INT,
                user_id INT,
                message TEXT NOT NULL,
                type ENUM('info', 'trade', 'warning', 'error') NOT NULL DEFAULT 'info',
                created_at DATETIME NOT NULL,
                INDEX idx_bot_id (bot_id),
                FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('Bot logs table created', 'success');
        
        // Create api_keys table
        addLog('Creating api_keys table...', 'info');
        $db->exec("DROP TABLE IF EXISTS api_keys");
        $db->exec("
            CREATE TABLE api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                api_key TEXT NOT NULL,
                api_secret TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        addLog('API keys table created', 'success');
        
        // Insert sample users
        addLog('Creating sample users...', 'info');
        $db->exec("INSERT INTO users (email, password, name, role, max_bots, max_trades_per_day, api_calls_limit, trading_mode, created_at) VALUES 
            ('demo@example.com', '" . password_hash('demo123', PASSWORD_DEFAULT) . "', 'Demo User', 'User', 1, 10, 100, 'testnet', NOW()),
            ('admin@example.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Admin User', 'Admin', 999, 999999, 999999, 'real', NOW())");
        addLog('Sample users created', 'success');
        
        $success = true;
        addLog('Installation completed successfully!', 'success');
        
    } catch (PDOException $e) {
        addLog('Database error: ' . $e->getMessage(), 'error');
    }
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Installation - Binance AI Trading Bot</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #0a0a0f; color: #fff; min-height: 100vh; }
        .install-card { background: #16161e; border: 1px solid #2a2a36; border-radius: 16px; max-width: 700px; margin: 50px auto; }
        .card-header { background: transparent; border-bottom: 1px solid #2a2a36; padding: 20px 25px; }
        .card-body { padding: 25px; }
        h1 { background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .step { display: flex; align-items: center; margin: 10px 0; padding: 12px; background: #1a1a24; border-radius: 10px; }
        .step-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 14px; }
        .step.pending .step-icon { background: #2a2a36; color: #666; }
        .step.success .step-icon { background: #10b981; color: #fff; }
        .step.error .step-icon { background: #ef4444; color: #fff; }
        .step.info .step-icon { background: #3b82f6; color: #fff; }
        .btn-primary-custom { background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: #fff; padding: 12px 24px; border-radius: 10px; font-weight: 600; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); color: #fff; }
        .log-output { max-height: 300px; overflow-y: auto; background: #1a1a24; border-radius: 10px; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 13px; }
        .log-success { color: #10b981; }
        .log-error { color: #ef4444; }
        .log-info { color: #3b82f6; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card">
        <div class="card-header">
            <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Binance AI Trading Bot - Installation</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                    <h4 class="mt-3">Installation Complete!</h4>
                    <p class="text-muted">Your database has been set up successfully.</p>
                    <a href="/" class="btn btn-primary-custom"><i class="bi bi-house me-2"></i>Go to Dashboard</a>
                </div>
            <?php elseif ($step === 1): ?>
                <h5>Installation Log</h5>
                <div class="log-output">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-<?php echo $log['type']; ?>">
                            [<?php echo strtoupper($log['type']); ?>] <?php echo htmlspecialchars($log['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$success): ?>
                    <div class="alert alert-danger">Installation failed. Please check the error messages above.</div>
                <?php endif; ?>
            <?php else: ?>
                <h5>Welcome to Binance AI Trading Bot!</h5>
                <p class="text-muted">This installer will set up the database and create all required tables.</p>
                
                <h6 class="mt-4">What will be installed:</h6>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-database"></i></div>
                    <div>Database: <?php echo DB_NAME; ?></div>
                </div>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-table"></i></div>
                    <div>Tables: users, licenses, features, bots, trades, bot_logs, api_keys</div>
                </div>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-person"></i></div>
                    <div>Sample users: demo@example.com (demo123), admin@example.com (admin123)</div>
                </div>
                
                <h6 class="mt-4">Features:</h6>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-robot"></i></div>
                    <div>Grid Trading Bot - Automated buy/sell at price levels</div>
                </div>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-cpu"></i></div>
                    <div>AI Trading - RSI, MACD, EMA based strategies</div>
                </div>
                <div class="step info">
                    <div class="step-icon"><i class="bi bi-shield-lock"></i></div>
                    <div>VIP Licensing - Multiple tiers with different limits</div>
                </div>
                
                <a href="?step=1" class="btn btn-primary-custom mt-4"><i class="bi bi-play me-2"></i>Start Installation</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
