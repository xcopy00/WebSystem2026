<?php
/**
 * API Router - Handles all API endpoints
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/binance-api.php';

// Set JSON response headers
header('Content-Type: application/json');

// Security: Limit CORS to specific origin in production
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    APP_URL
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $errstr]);
    exit;
});

// Exception handler
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    exit;
});

// Get request URI
// Try different methods to get the route
$uri = '';

// Check for route query parameter first (from .htaccess rewrite)
if (isset($_GET['route'])) {
    $uri = $_GET['route'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
    // Map action to route
    $actionMap = [
        'login' => 'auth/login',
        'register' => 'auth/register',
        'me' => 'auth/me',
        'bots' => 'bots',
        'user/balance' => 'user/balance',
        'worker/status' => 'worker/status',
        'keys' => 'keys',
        'keys/status' => 'keys/status',
        'trades' => 'trades',
        'bots/logs' => 'bots/logs',
        // VIP Subscription routes
        'vip/plans' => 'vip/plans',
        'vip/subscribe' => 'vip/subscribe',
        'vip/my-subscription' => 'vip/my-subscription',
        // Bot feature routes
        'bots/create' => 'bots/create',
        'bots/validate' => 'bots/validate'
    ];
    $uri = $actionMap[$action] ?? $action;
} elseif (isset($_SERVER['PATH_INFO'])) {
    $uri = trim($_SERVER['PATH_INFO'], '/');
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $uri = parse_url($requestUri, PHP_URL_PATH);
    $uri = str_replace('/api/', '', $uri);
    $uri = trim($uri, '/');
}

// Remove 'api' prefix if present (only remove 'api/' at the start, not the word 'api' anywhere)
$uri = preg_replace('#^api/?#', '', $uri);
$uri = trim($uri, '/');

$method = $_SERVER['REQUEST_METHOD'];

// ==================== AUTH MIDDLEWARE ====================
function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $token = str_replace('Bearer ', '', $authHeader);

    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        $payload = json_decode(base64_decode($parts[1]), true);

        if (!$payload || !isset($payload['id']) || !isset($payload['exp'])) {
            throw new Exception('Invalid token payload');
        }

        if ($payload['exp'] < time()) {
            throw new Exception('Token expired');
        }

        $db = new DBOperations();
        $user = $db->getUserById($payload['id']);

        if (!$user) {
            throw new Exception('User not found');
        }

        return $user;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

function generateToken($user) {
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $now = time();
    $payload = base64_encode(json_encode([
        'id' => $user['id'],
        'email' => $user['email'],
        'exp' => $now + JWT_EXPIRY,
        'iat' => $now
    ]));
    $signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
    return "$header.$payload." . base64_encode($signature);
}

// Helper function to get client IP address
function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check for forwarded IP (behind proxy)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    return $ip;
}
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Table doesn't exist, use defaults
        }
    }
    
    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==================== ROUTES ====================

// ==================== AUTH ROUTES ====================

// ==================== USER ROUTES ====================

// elseif ($uri === 'user/balance' && $method === 'GET') {
//     $user = authenticate();
//     
//     // Return user balance info (stored in database or from API)
//     $balance = $user['balance'] ?? 10000; // Default demo balance
//     
//     echo json_encode([
//         'balance' => $balance,
//         'trading_mode' => $user['trading_mode'] ?? 'test'
//     ]);
// }

// ==================== WORKER ROUTES ====================

if ($uri === 'worker/status' && $method === 'GET') {
    // Check if worker processes are running
    $user = authenticate();
    
    // Only admins can check worker status
    if (($user['role'] ?? 'User') !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    // Check for running worker processes
    $workers = [];
    
    // Check for PHP worker processes (cross-platform)
    $workers = [];
    $workerCount = 0;
    $pids = [];
    
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: use tasklist
        $cmd = 'tasklist /FI "IMAGENAME eq php.exe" /FO CSV';
        exec($cmd, $output, $returnCode);
        
        if (!empty($output)) {
            // Skip header line and parse CSV
            array_shift($output);
            foreach ($output as $line) {
                // Parse CSV format: "php.exe","1234","Console","1","5,412 K","Running",...""
                $parts = str_getcsv($line);
                if (count($parts) >= 2) {
                    $pid = (int)$parts[1];
                    if ($pid > 0 && !in_array($pid, $pids)) {
                        $pids[] = $pid;
                        $workerCount++;
                        
                        $workers[] = [
                            'pid' => $pid,
                            'cpu' => 0,
                            'memory' => 0,
                            'status' => 'running'
                        ];
                    }
                }
            }
        }
    } else {
        // Unix/Linux: use ps aux
        $cmd = 'ps aux | grep worker.php | grep -v grep';
        exec($cmd, $output, $returnCode);
        
        if (!empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/\s+([0-9]+)\s+/', $line, $matches)) {
                    $pid = (int)$matches[1];
                    if ($pid > 0 && !in_array($pid, $pids)) {
                        $pids[] = $pid;
                        $workerCount++;
                        
                        preg_match('/([0-9.]+)%/', $line, $cpuMatch);
                        preg_match('/([0-9.]+)%/', $line, $memMatch);
                        
                        $workers[] = [
                            'pid' => $pid,
                            'cpu' => $cpuMatch[1] ?? 0,
                            'memory' => $memMatch[1] ?? 0,
                            'status' => 'running'
                        ];
                    }
                }
            }
        }
    }
    
    // Check running bots in database
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM bots WHERE status = 'running'");
        $runningBots = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $runningBots = 0;
    }
    
    $status = $workerCount > 0 ? 'running' : 'stopped';
    $statusText = $workerCount > 0 ? "Running ($workerCount worker(s))" : 'Stopped';
    
    echo json_encode([
        'status' => $status,
        'statusText' => $statusText,
        'workerCount' => $workerCount,
        'workers' => $workers,
        'runningBots' => $runningBots,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ==================== AUTH ROUTES ====================

if ($uri === 'auth/register' && ($method === 'POST' || $method === 'GET')) {
    // Check if registration is enabled
    $registrationEnabled = getSetting('registration_enabled', true);
    if (!$registrationEnabled) {
        http_response_code(403);
        echo json_encode(['error' => 'Registration is disabled. Please contact administrator.']);
        exit;
    }
    
    // Accept data from POST body, GET params, or form data
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($input, true) ?? [];
        } else {
            // Also check URL params as fallback
            $data = array_merge($_GET, $_POST);
        }
    } else {
        $data = $_GET;
    }
    
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    $db = new DBOperations();

    if ($db->getUserByEmail($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User already exists']);
        exit;
    }

    $user = $db->createUser($data['email'], $data['password'], $data['name'] ?? '');
    $token = generateToken($user);

    echo json_encode([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ]
    ]);
}

elseif (($uri === 'auth/login') && ($method === 'POST' || $method === 'GET')) {
    // Accept data from POST body, GET params, or form data
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($input, true) ?? [];
        } else {
            // Also check URL params as fallback
            $data = array_merge($_GET, $_POST);
        }
    } else {
        $data = $_GET;
    }
    
    // Ensure email and password are set
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    $db = new DBOperations();
    $user = $db->getUserByEmail($data['email']);

    if (!$user || !$db->validatePassword($user, $data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    
    // Check if user is active
    if (isset($user['is_active']) && !$user['is_active']) {
        http_response_code(403);
        echo json_encode(['error' => 'Account has been deactivated. Please contact administrator.']);
        exit;
    }

    // Update user's IP address on login
    $clientIp = getClientIp();
    if ($clientIp) {
        $db->updateUserIp($user['id'], $clientIp);
    }

    $token = generateToken($user);

    echo json_encode([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'hasApiKeys' => !empty($user['api_key']),
            'role' => $user['role'] ?? 'User'
        ]
    ]);
}

elseif ($uri === 'auth/me' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    // Update user's IP address on each request
    $clientIp = getClientIp();
    if ($clientIp) {
        $db->updateUserIp($user['id'], $clientIp);
    }
    
    $limits = $db->getRoleLimits($user['role'] ?? 'User');
    $features = $db->getUserFeatures($user['role'] ?? 'User');
    
    echo json_encode([
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'hasApiKeys' => !empty($user['api_key']),
        'role' => $user['role'] ?? 'User',
        'licenseExpires' => $user['license_expires'] ?? null,
        'limits' => $limits,
        'features' => array_column($features, 'code'),
        'tradingMode' => $user['trading_mode'] ?? 'testnet'
    ]);
}

// ==================== API KEYS ROUTES ====================

elseif ($uri === 'keys' && $method === 'POST') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['apiKey']) || !isset($data['apiSecret'])) {
        http_response_code(400);
        echo json_encode(['error' => 'API key and secret are required']);
        exit;
    }

    // Validate keys
    $binanceApi = new BinanceAPI($data['apiKey'], $data['apiSecret']);
    $validation = $binanceApi->validateApiKeys();

    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid API keys: ' . $validation['error']]);
        exit;
    }

    $db = new DBOperations();
    $db->saveApiKeys($user['id'], $data['apiKey'], $data['apiSecret']);
    $db->updateUserApiKeys($user['id'], $data['apiKey'], $data['apiSecret']);

    echo json_encode(['success' => true, 'message' => 'API keys saved successfully']);
}

elseif ($uri === 'keys/status' && $method === 'GET') {
    $user = authenticate();
    echo json_encode(['hasKeys' => !empty($user['api_key'])]);
}

// ==================== BOT ROUTES ====================

elseif ($uri === 'bots' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    $bots = $db->getUserBots($user['id']);
    echo json_encode($bots);
}

elseif ($uri === 'bots' && $method === 'POST') {
    $user = authenticate();
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Debug logging
    error_log("bots endpoint: input length = " . strlen($input) . ", data = " . ($data ? 'array' : 'null'));
    
    if (empty($data) || !isset($data['name']) || !isset($data['type']) || !isset($data['symbol'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name, type, and symbol are required', 'debug' => ['input_len' => strlen($input), 'data_null' => is_null($data)]]);
        exit;
    }

    if (!in_array($data['type'], ['spot', 'future'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type must be spot or future']);
        exit;
    }

    $db = new DBOperations();
    $bot = $db->createBot($user['id'], $data);

    echo json_encode(['success' => true, 'message' => 'Bot created successfully', 'bot' => $bot]);
}

elseif (preg_match('#^bots/(\d+)$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    $bot = $db->getBotById($matches[1]);

    if (!$bot || $bot['user_id'] != $user['id']) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found']);
        exit;
    }

    echo json_encode($bot);
}

elseif (preg_match('#^bots/(\d+)/start$#', $uri, $matches) && $method === 'POST') {
    $user = authenticate();
    $botId = $matches[1];
    $db = new DBOperations();
    $bot = $db->getBotById($botId);

    if (!$bot || $bot['user_id'] != $user['id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bot not found']);
        exit;
    }

    if (empty($user['api_key']) || empty($user['api_secret'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'API keys required to start bot']);
        exit;
    }

    // Update bot status
    $db->updateBotStatus($botId, 'running');

    // Start bot in background (cross-platform)
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --start', __DIR__, $botId);
        pclose(popen("start /B " . $cmd, "r"));
    } else {
        $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --start > /dev/null 2>&1 &', __DIR__, $botId);
        exec($cmd);
    }

    echo json_encode(['success' => true, 'message' => "Bot $botId started"]);
}

elseif (preg_match('#^bots/(\d+)/stop$#', $uri, $matches) && $method === 'POST') {
    $user = authenticate();
    $botId = $matches[1];
    $db = new DBOperations();
    $bot = $db->getBotById($botId);

    if (!$bot || $bot['user_id'] != $user['id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bot not found']);
        exit;
    }

    // Update bot status
    $db->updateBotStatus($botId, 'stopped');

    // Stop bot process (cross-platform)
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --stop', __DIR__, $botId);
        pclose(popen("start /B " . $cmd, "r"));
    } else {
        $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --stop > /dev/null 2>&1 &', __DIR__, $botId);
        exec($cmd);
    }

    echo json_encode(['success' => true, 'message' => "Bot $botId stopped"]);
}

elseif (preg_match('#^bots/(\d+)$#', $uri, $matches) && $method === 'DELETE') {
    $user = authenticate();
    $botId = $matches[1];
    $db = new DBOperations();
    $bot = $db->getBotById($botId);

    if (!$bot || $bot['user_id'] != $user['id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bot not found']);
        exit;
    }

    // Stop bot if running (cross-platform)
    if ($bot['status'] === 'running') {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --stop', __DIR__, $botId);
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            $cmd = sprintf('php %s/grid-bot.php --bot-id=%d --stop > /dev/null 2>&1 &', __DIR__, $botId);
            exec($cmd);
        }
    }

    $db->deleteBot($botId, $user['id']);
    echo json_encode(['success' => true, 'message' => 'Bot deleted']);
}

elseif (preg_match('#^bots/(\d+)/logs$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();
    $botId = $matches[1];
    $limit = (int)($_GET['limit'] ?? 100);
    
    $db = new DBOperations();
    $logs = $db->getLogsByBotId($botId, $limit);
    echo json_encode($logs);
}

elseif ($uri === 'bots/logs' && $method === 'GET') {
    // Get all logs for user's bots
    $user = authenticate();
    $limit = (int)($_GET['limit'] ?? 100);
    
    $db = new DBOperations();
    $logs = $db->getLogsByUserId($user['id'], $limit);
    echo json_encode($logs);
}

elseif (preg_match('#^bots/(\d+)/status$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();
    $botId = $matches[1];
    
    // Get status from database
    $db = new DBOperations();
    $bot = $db->getBotById($botId);
    
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found']);
        exit;
    }

    echo json_encode([
        'botId' => $botId,
        'status' => $bot['status'],
        'symbol' => $bot['symbol'],
        'type' => $bot['type']
    ]);
}

// ==================== TRADE ROUTES ====================

elseif ($uri === 'trades' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    $trades = $db->getTradesByUserId($user['id']);
    echo json_encode($trades);
}

elseif (preg_match('#^bots/(\d+)/trades$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();
    $botId = $matches[1];
    
    $db = new DBOperations();
    $trades = $db->getTradesByBotId($botId);
    echo json_encode($trades);
}

// ==================== MARKET DATA ROUTES ====================

elseif (preg_match('#^market/([A-Z]+USDT)$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();

    if (empty($user['api_key']) || empty($user['api_secret'])) {
        http_response_code(400);
        echo json_encode(['error' => 'API keys required']);
        exit;
    }

    $symbol = $matches[1];
    $binanceApi = new BinanceAPI($user['api_key'], $user['api_secret']);
    
    try {
        $price = $binanceApi->getSpotPrice($symbol);
        $ticker = $binanceApi->get24hrTicker($symbol);
        echo json_encode(['price' => $price, 'ticker' => $ticker]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

elseif (preg_match('#^market/([A-Z]+USDT)/klines#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();

    if (empty($user['api_key']) || empty($user['api_secret'])) {
        http_response_code(400);
        echo json_encode(['error' => 'API keys required']);
        exit;
    }

    $symbol = $matches[1];
    $interval = $_GET['interval'] ?? '1h';
    $limit = (int)($_GET['limit'] ?? 100);
    
    $binanceApi = new BinanceAPI($user['api_key'], $user['api_secret']);
    
    try {
        $klines = $binanceApi->getKlines($symbol, $interval, $limit);
        echo json_encode($binanceApi->formatKlines($klines));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ==================== BALANCE ROUTES ====================

elseif ($uri === 'balance' && $method === 'GET') {
    $user = authenticate();

    if (empty($user['api_key']) || empty($user['api_secret'])) {
        http_response_code(400);
        echo json_encode(['error' => 'API keys required']);
        exit;
    }

    $type = $_GET['type'] ?? 'spot';
    $binanceApi = new BinanceAPI($user['api_key'], $user['api_secret'], $type === 'future');
    
    try {
        if ($type === 'future') {
            $balance = $binanceApi->getFuturesBalance();
        } else {
            $balance = $binanceApi->getSpotBalance();
        }
        echo json_encode($balance);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ==================== STATS ROUTES ====================

elseif ($uri === 'stats' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    $bots = $db->getUserBots($user['id']);
    $trades = $db->getTradesByUserId($user['id']);

    $runningBots = count(array_filter($bots, function($b) { return $b['status'] === 'running'; }));
    $closedTrades = array_filter($trades, function($t) { return $t['status'] === 'closed'; });
    $openTrades = array_filter($trades, function($t) { return $t['status'] === 'open'; });

    $totalProfitLoss = array_sum(array_column($closedTrades, 'profit_loss'));
    $winningTrades = count(array_filter($closedTrades, function($t) { return $t['profit_loss'] > 0; }));
    $losingTrades = count(array_filter($closedTrades, function($t) { return $t['profit_loss'] < 0; }));

    $winRate = !empty($closedTrades) ? round(($winningTrades / count($closedTrades)) * 100, 2) : 0;

    echo json_encode([
        'totalBots' => count($bots),
        'runningBots' => $runningBots,
        'totalTrades' => count($trades),
        'openTrades' => count($openTrades),
        'closedTrades' => count($closedTrades),
        'totalProfitLoss' => round($totalProfitLoss, 2),
        'winningTrades' => $winningTrades,
        'losingTrades' => $losingTrades,
        'winRate' => $winRate
    ]);
}

// ==================== PROFILE ROUTES ====================

elseif ($uri === 'auth/profile' && $method === 'PUT') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $db = new DBOperations();
    
    $updateData = [];
    if (isset($data['name'])) {
        $updateData['name'] = $data['name'];
    }
    if (isset($data['password']) && !empty($data['password'])) {
        $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (!empty($updateData)) {
        $db->updateUser($user['id'], $updateData);
    }
    
    $updatedUser = $db->getUserById($user['id']);
    echo json_encode([
        'user' => [
            'id' => $updatedUser['id'],
            'email' => $updatedUser['email'],
            'name' => $updatedUser['name']
        ]
    ]);
}

// ==================== LICENSE ROUTES ====================

elseif ($uri === 'auth/activate' && $method === 'POST') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['code']) || empty($data['code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'License code is required']);
        exit;
    }
    
    $db = new DBOperations();
    
    // Validate license code
    $license = $db->validateLicense($data['code']);
    
    if (!$license) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid license code']);
        exit;
    }
    
    if ($license['is_used']) {
        http_response_code(400);
        echo json_encode(['error' => 'License code has already been used']);
        exit;
    }
    
    // Activate license
    $db->activateLicense($license['id'], $user['id']);
    
    // Log to license history
    $db->logLicenseHistory($user['id'], $data['code'], 'activate', $user['role'], "Activated {$license['type']} license");
    
    // Get updated user
    $updatedUser = $db->getUserById($user['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'License activated successfully',
        'user' => [
            'id' => $updatedUser['id'],
            'email' => $updatedUser['email'],
            'name' => $updatedUser['name'],
            'role' => $updatedUser['role'],
            'licenseExpires' => $updatedUser['license_expires']
        ]
    ]);
}

// ==================== USER ROUTES ====================

elseif ($uri === 'user/features' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    $features = $db->getUserFeatures($user['role'] ?? 'User');
    echo json_encode($features);
}

elseif ($uri === 'user/subscription' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    $limits = $db->getRoleLimits($user['role'] ?? 'User');
    $features = $db->getUserFeatures($user['role'] ?? 'User');
    
    echo json_encode([
        'role' => $user['role'] ?? 'User',
        'licenseExpires' => $user['license_expires'] ?? null,
        'limits' => $limits,
        'features' => array_column($features, 'code')
    ]);
}

elseif ($uri === 'user/license-history' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    $history = $db->getLicenseHistory($user['id']);
    echo json_encode($history);
}

// ==================== LICENSE ACTIVATION ROUTE ====================

elseif ($uri === 'licenses/activate' && $method === 'POST') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['license_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'License code is required']);
        exit;
    }
    
    $db = new DBOperations();
    
    // Validate license code
    $license = $db->validateLicense($data['license_code']);
    
    if (!$license) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or already used license code']);
        exit;
    }
    
    // Activate license
    $db->activateLicense($license['id'], $user['id']);
    
    // Log to license history
    $db->logLicenseHistory($user['id'], $data['license_code'], 'activate', $license['type'], 
        "Activated {$license['type']} license");
    
    // Get updated user
    $updatedUser = $db->getUserById($user['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'License activated successfully',
        'user' => [
            'id' => $updatedUser['id'],
            'email' => $updatedUser['email'],
            'name' => $updatedUser['name'],
            'vip_level' => $updatedUser['vip_level'],
            'licenseExpires' => $updatedUser['license_expires']
        ]
    ]);
}

// ==================== ADMIN ROUTES ====================

elseif ($uri === 'admin/dashboard' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $db = new DBOperations();
    
    $userStats = $db->getUserStats();
    $licenseStats = $db->getLicenseStats();
    
    // Calculate VIP users count
    $vipUsers = 0;
    foreach ($licenseStats as $lic) {
        $vipUsers += (int)($lic['activated'] ?? 0);
    }
    
    // Get bot and user counts per VIP tier
    $tierBotCounts = [];
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("
            SELECT u.role, COUNT(DISTINCT u.id) as user_count, COUNT(b.id) as bot_count, 
                   SUM(CASE WHEN b.status = 'running' THEN 1 ELSE 0 END) as running_count
            FROM users u
            LEFT JOIN bots b ON u.id = b.user_id
            GROUP BY u.role
        ");
        $tierStats = $stmt->fetchAll();
        foreach ($tierStats as $stat) {
            $tierBotCounts[$stat['role']] = [
                'users' => (int)($stat['user_count'] ?? 0),
                'bots' => (int)($stat['bot_count'] ?? 0),
                'running' => (int)($stat['running_count'] ?? 0)
            ];
        }
        
        // Also count orphaned bots (user_id is null or doesn't match any user)
        $stmt = $pdo->query("SELECT COUNT(*) as orphaned_bots FROM bots b WHERE b.user_id IS NULL OR b.user_id NOT IN (SELECT id FROM users)");
        $orphaned = $stmt->fetch();
        if ($orphaned && (int)$orphaned['orphaned_bots'] > 0) {
            $tierBotCounts['Orphaned'] = [
                'users' => 0,
                'bots' => (int)($orphaned['orphaned_bots'] ?? 0),
                'running' => 0
            ];
        }
        
        // Log all roles for debugging
        error_log('All user roles found: ' . print_r(array_keys($tierBotCounts), true));
    } catch (Exception $e) {
        // Table may not exist
    }
    
    // Get total trades
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM trades");
        $totalTrades = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totalTrades = 0;
    }
    
    echo json_encode([
        'totalUsers' => $userStats['total_users'] ?? 0,
        'activeUsers' => $userStats['active_users'] ?? 0,
        'vipUsers' => $vipUsers,
        'totalBots' => $userStats['total_bots'] ?? 0,
        'runningBots' => $userStats['running_bots'] ?? 0,
        'totalTrades' => $totalTrades,
        'tierStats' => $tierBotCounts
    ]);
}

elseif ($uri === 'admin/users' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $db = new DBOperations();
    $users = $db->getAllUsers();
    
    // Add IP address display
    foreach ($users as &$u) {
        $u['ip_display'] = $u['ip_address'] ?: '-';
    }
    
    echo json_encode($users);
}

elseif (preg_match('#^admin/users/(\d+)$#', $uri, $matches) && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $targetUserId = $matches[1];
    $db = new DBOperations();
    
    $targetUser = $db->getUserById($targetUserId);
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    echo json_encode($targetUser);
}

elseif (preg_match('#^admin/users/(\d+)$#', $uri, $matches) && $method === 'PUT') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $targetUserId = $matches[1];
    $data = json_decode(file_get_contents('php://input'), true);
    $db = new DBOperations();
    
    $targetUser = $db->getUserById($targetUserId);
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $oldRole = $targetUser['role'];
    $updateData = [];
    
    if (isset($data['role']) && in_array($data['role'], ['User', 'VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6', 'Admin'])) {
        $updateData['role'] = $data['role'];
    }
    if (isset($data['is_active'])) {
        $updateData['is_active'] = $data['is_active'] ? 1 : 0;
    }
    
    if (!empty($updateData)) {
        $db->updateUser($targetUserId, $updateData);
        
        // Log to license history
        $db->logLicenseHistory($targetUserId, null, 'admin_update', $oldRole, 
            "Admin updated user role");
    }
    
    echo json_encode(['success' => true, 'message' => 'User updated']);
}

// ==================== USER ACTIVATION/DEACTIVATION ROUTES ====================

elseif (preg_match('#^admin/users/(\d+)/(activate|deactivate)$#', $uri, $matches) && $method === 'PUT') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $targetUserId = $matches[1];
    $action = $matches[2]; // 'activate' or 'deactivate'
    
    $db = new DBOperations();
    
    $targetUser = $db->getUserById($targetUserId);
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $isActive = ($action === 'activate') ? 1 : 0;
    $db->updateUser($targetUserId, ['is_active' => $isActive]);
    
    // Log to license history
    $db->logLicenseHistory($targetUserId, null, 'admin_update', $targetUser['role'], 
        "Admin " . $action . "d user");
    
    echo json_encode(['success' => true, 'message' => 'User ' . $action . 'd successfully']);
}

// ==================== RESET SUBSCRIPTION ROUTE ====================

elseif (preg_match('#^admin/users/(\d+)/reset$#', $uri, $matches) && $method === 'POST') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $targetUserId = $matches[1];
    $db = new DBOperations();
    
    $targetUser = $db->getUserById($targetUserId);
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $oldRole = $targetUser['role'];
    $db->resetUserSubscription($targetUserId);
    
    // Log to license history
    $db->logLicenseHistory($targetUserId, null, 'admin_reset', $oldRole, 
        "Admin reset user subscription from $oldRole to User");
    
    echo json_encode(['success' => true, 'message' => 'Subscription reset successfully']);
}

elseif ($uri === 'admin/licenses' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $db = new DBOperations();
    $licenses = $db->getAllLicenses();
    
    // Get user emails for used_by field
    $pdo = Database::getInstance()->getConnection();
    foreach ($licenses as &$lic) {
        if (!empty($lic['used_by'])) {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$lic['used_by']]);
            $userData = $stmt->fetch();
            $lic['used_by_email'] = $userData ? $userData['email'] : 'User #' . $lic['used_by'];
        } else {
            $lic['used_by_email'] = '-';
        }
    }
    
    echo json_encode($licenses);
}

elseif ($uri === 'admin/licenses/history' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $limit = (int)($_GET['limit'] ?? 100);
    $db = new DBOperations();
    $history = $db->getLicenseHistory(null, null, $limit); // Admin license history retrieval
    echo json_encode($history);
}

elseif ($uri === 'admin/licenses/generate' && $method === 'POST') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $type = $data['type'] ?? 'VIP1';
    $validityDays = (int)($data['validity_days'] ?? 30);
    $notes = $data['notes'] ?? '';
    $count = (int)($data['count'] ?? 1);
    
    if (!in_array($type, ['User', 'VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6', 'Admin'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid license type']);
        exit;
    }
    
    $db = new DBOperations();
    $generatedCodes = [];
    
    for ($i = 0; $i < $count; $i++) {
        $code = $db->generateLicenseCode($type, $validityDays, $notes);
        $generatedCodes[] = $code;
    }
    
    echo json_encode([
        'success' => true,
        'codes' => $generatedCodes,
        'count' => count($generatedCodes)
    ]);
}

elseif ($uri === 'admin/licenses/delete' && $method === 'POST') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $licenseId = $data['id'] ?? 0;
    
    if (!$licenseId) {
        http_response_code(400);
        echo json_encode(['error' => 'License ID required']);
        exit;
    }
    
    $db = new DBOperations();
    $success = $db->deleteLicense($licenseId);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'License deleted successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete license (may be already used)']);
    }
}

elseif ($uri === 'admin/licenses/stats' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $db = new DBOperations();
    $stats = $db->getLicenseStats();
    echo json_encode($stats);
}

// ==================== VIP SUBSCRIPTION ROUTES ====================

elseif ($uri === 'vip/plans' && $method === 'GET') {
    // Get all VIP subscription plans (public endpoint)
    $db = new DBOperations();
    $plans = $db->getVipPlans();
    echo json_encode($plans);
}

elseif ($uri === 'vip/my-subscription' && $method === 'GET') {
    $user = authenticate();
    $db = new DBOperations();
    
    $limits = $db->getRoleLimits($user['role'] ?? 'User');
    $features = $db->getUserFeatures($user['role'] ?? 'User');
    
    // Get current bot count
    $bots = $db->getUserBots($user['id']);
    $runningBots = count(array_filter($bots, function($b) { return $b['status'] === 'running'; }));
    
    echo json_encode([
        'role' => $user['role'] ?? 'User',
        'licenseExpires' => $user['license_expires'] ?? null,
        'limits' => $limits,
        'features' => array_column($features, 'code'),
        'currentBots' => count($bots),
        'runningBots' => $runningBots,
        'canCreateBot' => count($bots) < ($limits['max_bots'] ?? 1)
    ]);
}

elseif ($uri === 'vip/subscribe' && $method === 'POST') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['license_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'License code is required']);
        exit;
    }
    
    $db = new DBOperations();
    
    // Validate and activate license
    $license = $db->validateLicense($data['license_code']);
    
    if (!$license) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid license code']);
        exit;
    }
    
    if ($license['is_used']) {
        http_response_code(400);
        echo json_encode(['error' => 'License code has already been used']);
        exit;
    }
    
    // Activate license and update user role
    $db->activateLicense($license['id'], $user['id']);
    
    // Log to license history
    $db->logLicenseHistory($user['id'], $data['license_code'], 'activate', $license['type'], 
        "Activated {$license['type']} license");
    
    // Get updated user
    $updatedUser = $db->getUserById($user['id']);
    $newLimits = $db->getRoleLimits($updatedUser['role']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription activated successfully',
        'subscription' => [
            'role' => $updatedUser['role'],
            'licenseExpires' => $updatedUser['license_expires'],
            'limits' => $newLimits
        ]
    ]);
}

// ==================== BOT VALIDATION ROUTES ====================

elseif ($uri === 'bots/validate' && $method === 'POST') {
    $user = authenticate();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Get user limits
    $db = new DBOperations();
    $limits = $db->getRoleLimits($user['role'] ?? 'User');
    
    // Get current bot count
    $bots = $db->getUserBots($user['id']);
    $currentBotCount = count($bots);
    $maxBots = $limits['max_bots'] ?? 1;
    
    // Check if user can create more bots
    if ($maxBots != -1 && $currentBotCount >= $maxBots) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'error' => "You have reached the maximum limit of {$maxBots} bots for your subscription level"
        ]);
        exit;
    }
    
    // Validate strategy is allowed
    $strategy = $data['strategy'] ?? 'grid';
    $features = $db->getUserFeatures($user['role'] ?? 'User');
    $featureCodes = array_column($features, 'code');
    
    $strategyMap = [
        'grid' => 'grid',
        'dca' => 'dca',
        'arbitrage' => 'arbitrage',
        'smart' => 'smart'
    ];
    
    $requiredFeature = $strategyMap[$strategy] ?? null;
    if ($requiredFeature && !in_array($requiredFeature, $featureCodes)) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'error' => "Your subscription level does not include the {$strategy} strategy. Upgrade to access this feature."
        ]);
        exit;
    }
    
    echo json_encode([
        'valid' => true,
        'message' => 'Bot configuration is valid',
        'remainingBots' => $maxBots - $currentBotCount
    ]);
}

// ==================== SETTINGS ROUTES ====================

elseif ($uri === 'admin/settings' && $method === 'GET') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    echo json_encode([
        'registration_enabled' => getSetting('registration_enabled', true)
    ]);
}

elseif ($uri === 'admin/settings' && $method === 'POST') {
    $user = authenticate();
    
    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['registration_enabled'])) {
        setSetting('registration_enabled', $data['registration_enabled'] ? '1' : '0');
    }
    
    echo json_encode(['success' => true]);
}

// ==================== DEFAULT / 404 ====================

else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found', 'uri' => $uri, 'method' => $method]);
}
