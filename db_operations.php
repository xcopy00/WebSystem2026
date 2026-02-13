<?php
/**
 * Database Operations Class
 * Handles all database queries for the AI Trading Bot
 */

require_once __DIR__ . '/db.php';

class DBOperations {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // ==================== USER OPERATIONS ====================
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }
    
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: false;
    }
    
    public function createUser($email, $password, $name = '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, password, name, role, created_at, updated_at)
            VALUES (?, ?, ?, 'User', NOW(), NOW())
        ");
        $stmt->execute([$email, $hashedPassword, $name]);
        
        return $this->getUserById($this->pdo->lastInsertId());
    }
    
    public function validatePassword($user, $password) {
        return password_verify($password, $user['password']);
    }
    
    public function updateUser($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'email', 'api_key', 'api_secret', 'trading_mode', 'is_active', 'role', 'license_expires'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function saveApiKeys($userId, $apiKey, $apiSecret) {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_keys (user_id, api_key, api_secret, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), api_secret = VALUES(api_secret)
        ");
        return $stmt->execute([$userId, $apiKey, $apiSecret]);
    }
    
    public function updateUserApiKeys($userId, $apiKey, $apiSecret) {
        return $this->updateUser($userId, [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret
        ]);
    }
    
    public function getUserApiKeys($userId) {
        $stmt = $this->pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: false;
    }
    
    // ==================== LICENSE OPERATIONS ====================
    
    public function getLicenseByCode($code) {
        $stmt = $this->pdo->prepare("SELECT * FROM licenses WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: false;
    }
    
    public function validateLicense($code) {
        $license = $this->getLicenseByCode($code);
        
        if (!$license) {
            return false;
        }
        
        // Check if already used
        if ($license['is_used']) {
            return false;
        }
        
        // Check if expired
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            return false;
        }
        
        return $license;
    }
    
    public function activateLicense($licenseId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET is_used = 1, used_by = ?, used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId, $licenseId]);
        
        // Get the license to update user role
        $license = $this->getLicenseById($licenseId);
        if ($license) {
            $validityDays = $license['validity_days'] ?? 30;
            $expiresAt = $license['expires_at'] ? $license['expires_at'] : date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
            $stmt = $this->pdo->prepare("UPDATE users SET role = ?, license_expires = ? WHERE id = ?");
            $stmt->execute([$license['type'], $expiresAt, $userId]);
        }
        
        return true;
    }
    
    public function getLicenseById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM licenses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }
    
    public function logLicenseHistory($userId, $licenseCode, $action, $newRole, $notes = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO license_history (user_id, license_code, action, new_role, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $licenseCode, $action, $newRole, $notes]);
    }
    
    public function getLicenseHistory($userId = null, $licenseCode = null, $limit = 100) {
        $sql = "
            SELECT lh.*
            FROM license_history lh
            WHERE 1=1
        ";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND lh.user_id = ?";
            $params[] = $userId;
        }
        
        if ($licenseCode !== null) {
            $sql .= " AND lh.license_code = ?";
            $params[] = $licenseCode;
        }
        
        $sql .= " ORDER BY lh.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ==================== VIP & ROLE LIMITS ====================
    
    public function getVipPlans() {
        $stmt = $this->pdo->query("SELECT id, code, name, type, price, duration_days, max_bots, max_trades_per_day, api_calls_limit, features, is_active FROM vip_subscriptions WHERE is_active = 1 ORDER BY id");
        $plans = $stmt->fetchAll();
        
        foreach ($plans as &$plan) {
            $plan['features'] = json_decode($plan['features'] ?? '[]', true);
        }
        
        return $plans;
    }
    
    public function getRoleLimits($role) {
        // First try vip_subscriptions table
        $stmt = $this->pdo->prepare("SELECT type as role, name, price, duration_days, max_bots, max_trades_per_day as max_trades_per_day, api_calls_limit, features FROM vip_subscriptions WHERE type = ? AND is_active = 1");
        $stmt->execute([$role]);
        $limits = $stmt->fetch();
        
        if ($limits) {
            $limits['features'] = json_decode($limits['features'] ?? '[]', true);
            return $limits;
        }
        
        // Fallback to role_limits table or defaults
        $stmt = $this->pdo->prepare("SELECT * FROM role_limits WHERE role = ?");
        $stmt->execute([$role]);
        $limits = $stmt->fetch();
        
        if (!$limits) {
            // Default limits for User role
            return [
                'role' => 'User',
                'name' => 'Free User',
                'max_bots' => 1,
                'max_trades_per_day' => 10,
                'api_calls_limit' => 100,
                'features' => ['grid']
            ];
        }
        
        $limits['features'] = json_decode($limits['features'] ?? '[]', true);
        return $limits;
    }
    
    public function getUserFeatures($role) {
        $limits = $this->getRoleLimits($role);
        $featureCodes = $limits['features'] ?? [];
        
        if (empty($featureCodes)) {
            return [];
        }
        
        // Return feature codes as a simple array
        return array_map(function($code) {
            return ['code' => $code];
        }, $featureCodes);
    }
    
    public function getUserLimits($userId) {
        $user = $this->getUserById($userId);
        if (!$user) return null;
        
        $role = $user['role'] ?? 'User';
        return $this->getRoleLimits($role);
    }
    
    // ==================== BOT OPERATIONS ====================
    
    public function getUserBots($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bots WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getBotById($botId) {
        $stmt = $this->pdo->prepare("SELECT * FROM bots WHERE id = ?");
        $stmt->execute([$botId]);
        return $stmt->fetch() ?: false;
    }
    
    public function createBot($userId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO bots (user_id, name, type, symbol, strategy, status, config, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'stopped', ?, NOW(), NOW())
        ");
        
        $config = json_encode([
            'grid_count' => $data['grid_count'] ?? 10,
            'grid_interval' => $data['grid_interval'] ?? 30,
            'investment' => $data['investment'] ?? 100,
            'stop_loss' => $data['stop_loss'] ?? 5,
            'take_profit' => $data['take_profit'] ?? 10,
            'leverage' => $data['leverage'] ?? 1,
            'side' => $data['side'] ?? 'both'
        ]);
        
        $stmt->execute([$userId, $data['name'], $data['type'], $data['symbol'], $data['strategy'], $config]);
        
        return $this->getBotById($this->pdo->lastInsertId());
    }
    
    public function updateBot($botId, $data) {
        $fields = [];
        $values = [];
        
        foreach (['name', 'symbol', 'strategy', 'status', 'config'] as $field) {
            if (isset($data[$field])) {
                if ($field === 'config') {
                    $fields[] = "config = ?";
                    $values[] = json_encode($data[$field]);
                } else {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $botId;
        $sql = "UPDATE bots SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    public function updateBotStatus($botId, $status) {
        $stmt = $this->pdo->prepare("UPDATE bots SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $botId]);
    }
    
    public function deleteBot($botId, $userId = null) {
        if ($userId) {
            $stmt = $this->pdo->prepare("DELETE FROM bots WHERE id = ? AND user_id = ?");
            return $stmt->execute([$botId, $userId]);
        }
        $stmt = $this->pdo->prepare("DELETE FROM bots WHERE id = ?");
        return $stmt->execute([$botId]);
    }
    
    public function countUserBots($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bots WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    // ==================== BOT LOG OPERATIONS ====================
    
    public function getLogsByBotId($botId, $limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bot_logs 
            WHERE bot_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$botId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getLogsByUserId($userId, $limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT bl.*, b.name as bot_name 
            FROM bot_logs bl
            LEFT JOIN bots b ON bl.bot_id = b.id
            WHERE b.user_id = ? 
            ORDER BY bl.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function addBotLog($botId, $message, $type = 'info') {
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_logs (bot_id, message, type, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$botId, $message, $type]);
    }
    
    // ==================== TRADE OPERATIONS ====================
    
    public function getUserTrades($userId, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, b.name as bot_name 
            FROM trades t
            LEFT JOIN bots b ON t.bot_id = b.id
            WHERE t.user_id = ? 
            ORDER BY t.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getTradesByUserId($userId, $limit = 50) {
        return $this->getUserTrades($userId, $limit);
    }
    
    public function getTradesByBotId($botId, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM trades 
            WHERE bot_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$botId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function createTrade($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO trades (user_id, bot_id, symbol, side, price, quantity, profit, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['user_id'],
            $data['bot_id'],
            $data['symbol'],
            $data['side'],
            $data['price'],
            $data['quantity'],
            $data['profit'] ?? 0,
            $data['status'] ?? 'open'
        ]);
    }
    
    public function updateTrade($tradeId, $data) {
        $fields = [];
        $values = [];
        
        foreach (['profit', 'status', 'closed_at'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $tradeId;
        $sql = "UPDATE trades SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    // ==================== GRID CONFIG OPERATIONS ====================
    
    public function getUserGridConfigs($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM grid_configs WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function createGridConfig($userId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO grid_configs (user_id, name, symbol, grid_count, grid_interval, investment, stop_loss, take_profit, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $data['name'],
            $data['symbol'],
            $data['grid_count'] ?? 10,
            $data['grid_interval'] ?? 30,
            $data['investment'] ?? 100,
            $data['stop_loss'] ?? 5,
            $data['take_profit'] ?? 10
        ]);
        
        return $this->getGridConfigById($this->pdo->lastInsertId());
    }
    
    public function getGridConfigById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM grid_configs WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }
    
    public function deleteGridConfig($id) {
        $stmt = $this->pdo->prepare("DELETE FROM grid_configs WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ==================== RUNNING BOTS OPERATIONS ====================
    
    public function getRunningBots() {
        $stmt = $this->pdo->query("SELECT * FROM bots WHERE status = 'running'");
        return $stmt->fetchAll();
    }
    
    // ==================== GRID ORDER OPERATIONS ====================
    
    public function createGridOrder($botId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO grid_orders (bot_id, level, price, buy_price, sell_price, quantity, type, order_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        return $stmt->execute([
            $botId,
            $data['level'] ?? 0,
            $data['price'] ?? 0,
            $data['buyPrice'] ?? 0,
            $data['sellPrice'] ?? 0,
            $data['quantity'] ?? 0,
            $data['type'] ?? 'buy',
            $data['order_id'] ?? ''
        ]);
    }
    
    public function getPendingGridOrders($botId) {
        $stmt = $this->pdo->prepare("SELECT * FROM grid_orders WHERE bot_id = ? AND status = 'pending'");
        $stmt->execute([$botId]);
        return $stmt->fetchAll();
    }
    
    public function getGridOrdersByBotId($botId) {
        $stmt = $this->pdo->prepare("SELECT * FROM grid_orders WHERE bot_id = ? ORDER BY created_at DESC");
        $stmt->execute([$botId]);
        return $stmt->fetchAll();
    }
    
    public function markGridOrderFilled($orderId, $fillPrice) {
        $stmt = $this->pdo->prepare("UPDATE grid_orders SET status = 'filled', filled_price = ? WHERE id = ?");
        return $stmt->execute([$fillPrice, $orderId]);
    }
    
    public function updateGridOrderStatus($orderId, $status) {
        $stmt = $this->pdo->prepare("UPDATE grid_orders SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    }
    
    // ==================== BOT LOG OPERATIONS ====================
    
    public function createLog($botId, $userId, $message, $type = 'info') {
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_logs (bot_id, user_id, message, type, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$botId, $userId, $message, $type]);
    }
    
    // ==================== ADMIN OPERATIONS ====================
    
    public function getAllUsers() {
        $stmt = $this->pdo->query("
            SELECT u.*, 
                (SELECT COUNT(*) FROM bots WHERE user_id = u.id) as bots_count
            FROM users u 
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function updateUserIp($userId, $ipAddress) {
        $stmt = $this->pdo->prepare("UPDATE users SET ip_address = ?, last_ip = IF(ip_address != ?, ip_address, last_ip), updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$ipAddress, $ipAddress, $userId]);
    }
    
    public function updateUserRole($userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$role, $userId]);
    }
    
    public function resetUserSubscription($userId) {
        // Reset user to Free tier and clear license expiration
        $stmt = $this->pdo->prepare("UPDATE users SET role = 'User', license_expires = NULL, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    public function createLicense($data) {
        $validityDays = $data['duration_days'] ?? ($data['validity_days'] ?? 30);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO licenses (code, type, validity_days, expires_at, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['license_code'] ?? $data['code'],
            $data['vip_tier'] ?? $data['type'],
            $validityDays,
            $expiresAt,
            $data['notes'] ?? ''
        ]);
    }
    
    public function getAllLicenses() {
        $stmt = $this->pdo->query("SELECT * FROM licenses ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function deleteLicense($licenseId) {
        $stmt = $this->pdo->prepare("DELETE FROM licenses WHERE id = ? AND is_used = 0");
        return $stmt->execute([$licenseId]);
    }
    
    public function generateLicenseCode($type = 'VIP1', $validityDays = 30, $notes = '') {
        switch ($type) {
            case 'VIP1': $prefix = 'V1'; break;
            case 'VIP2': $prefix = 'V2'; break;
            case 'VIP3': $prefix = 'V3'; break;
            case 'VIP4': $prefix = 'V4'; break;
            case 'VIP5': $prefix = 'V5'; break;
            case 'VIP6': $prefix = 'V6'; break;
            default: $prefix = 'LIC'; break;
        }
        
        $random = strtoupper(substr(md5(uniqid() . time()), 0, 12));
        $code = "$prefix-" . $random;
        
        $this->createLicense([
            'code' => $code,
            'vip_tier' => $type,
            'duration_days' => $validityDays,
            'notes' => $notes
        ]);
        
        return $code;
    }
    
    public function getLicenseStats() {
        $stmt = $this->pdo->query("
            SELECT 
                type as vip_tier,
                COUNT(*) as total,
                SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as activated
            FROM licenses
            GROUP BY type
        ");
        return $stmt->fetchAll();
    }
    
    public function getUserStats() {
        $stats = [];
        
        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = (int)$stmt->fetchColumn();
        
        // Active users (logged in last 24h) - handle missing last_login column
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['active_users'] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $stats['active_users'] = 0;
        }
        
        // Total bots
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM bots");
        $stats['total_bots'] = (int)$stmt->fetchColumn();
        
        // Running bots
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM bots WHERE status = 'running'");
        $stats['running_bots'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
    
    // ==================== API CALL LOGGING ====================
    
    public function logApiCall($userId, $endpoint, $responseTime) {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_calls_log (user_id, endpoint, response_time, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $endpoint, $responseTime]);
    }
    
    public function countUserApiCalls($userId, $hourAgo = 1) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM api_calls_log
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$userId, $hourAgo]);
        return (int)$stmt->fetchColumn();
    }
}
