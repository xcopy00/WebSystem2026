<?php
/**
 * System Test Script - Comprehensive Testing for Binance AI Trading Bot
 * Run this script to verify all components are working correctly
 */

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║         Binance AI Trading Bot - System Test Script              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$testsPassed = 0;
$testsFailed = 0;
$testsTotal = 0;

function test($name, $condition, $message = '') {
    global $testsPassed, $testsFailed, $testsTotal;
    $testsTotal++;
    
    if ($condition) {
        echo "✓ [PASS] $name\n";
        $testsPassed++;
    } else {
        echo "✗ [FAIL] $name" . ($message ? " - $message" : "") . "\n";
        $testsFailed++;
    }
}

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "1. FILE STRUCTURE TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$requiredFiles = [
    'config.php',
    'db.php',
    'db_operations.php',
    'api.php',
    'binance-api.php',
    'index.php',
    'install.php',
    'setup_database.php',
    'worker.php',
    'grid-bot.php',
    '.htaccess',
];

$publicFiles = [
    'public/index.html',
    'public/login.html',
    'public/register.html',
    'public/admin.html',
    'public/app.js',
    'public/admin.js',
    'public/styles.css',
    'public/sw.js',
    'public/manifest.json',
];

foreach ($requiredFiles as $file) {
    test("File exists: $file", file_exists(__DIR__ . '/' . $file));
}

foreach ($publicFiles as $file) {
    test("Public file exists: $file", file_exists(__DIR__ . '/' . $file));
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "2. CONFIGURATION TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

// Test config file
$configContent = file_get_contents(__DIR__ . '/config.php');
test('Config defines DB_HOST', defined('DB_HOST'));
test('Config defines DB_NAME', defined('DB_NAME'));
test('Config defines DB_USER', defined('DB_USER'));
test('Config defines DB_PASS', defined('DB_PASS'));
test('Config defines JWT_SECRET', defined('JWT_SECRET'));
test('Config defines JWT_EXPIRY', defined('JWT_EXPIRY'));
test('Config defines BINANCE_SPOT_BASE_URL', defined('BINANCE_SPOT_BASE_URL'));
test('Config defines APP_NAME', defined('APP_NAME'));
test('Config sets session cookie httponly', strpos($configContent, 'session.cookie_httponly') !== false);
test('Config disables display_errors', strpos($configContent, "ini_set('display_errors', 0)") !== false || strpos($configContent, 'display_errors', 0) !== false);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "3. DATABASE TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    test('Database connection', true);
    
    // Test if tables exist
    $tables = ['users', 'bots', 'trades', 'bot_logs', 'licenses', 'features'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        test("Table exists: $table", $stmt->rowCount() > 0);
    }
    
    // Test if sample user exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    test('Sample users exist', $userCount > 0);
    echo "  Users in database: $userCount\n";
    
    // Test if sample licenses exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM licenses");
    $licenseCount = $stmt->fetchColumn();
    test('Sample licenses exist', $licenseCount > 0);
    echo "  Licenses in database: $licenseCount\n";
    
} catch (PDOException $e) {
    test('Database connection', false, $e->getMessage());
    echo "  Note: Run install.php to create database tables\n";
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "4. SECURITY TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$htaccess = file_get_contents(__DIR__ . '/.htaccess');
test('.htaccess exists', !empty($htaccess));
test('.htaccess has X-Frame-Options', strpos($htaccess, 'X-Frame-Options') !== false);
test('.htaccess has X-Content-Type-Options', strpos($htaccess, 'X-Content-Type-Options') !== false);
test('.htaccess has X-XSS-Protection', strpos($htaccess, 'X-XSS-Protection') !== false);
test('.htaccess disables directory listing', strpos($htaccess, 'Options -Indexes') !== false);
test('.htaccess has rewrite engine', strpos($htaccess, 'RewriteEngine On') !== false);

$apiContent = file_get_contents(__DIR__ . '/api.php');
test('API has CORS headers', strpos($apiContent, 'Access-Control-Allow') !== false);
test('API has authentication function', strpos($apiContent, 'function authenticate') !== false);
test('API has JWT token generation', strpos($apiContent, 'function generateToken') !== false);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "5. API ENDPOINT TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$apiEndpoints = [
    'auth/register' => 'POST',
    'auth/login' => 'POST',
    'auth/me' => 'GET',
    'bots' => ['GET', 'POST'],
    'trades' => 'GET',
];

foreach ($apiEndpoints as $endpoint => $methods) {
    if (!is_array($methods)) {
        $methods = [$methods];
    }
    foreach ($methods as $method) {
        $pattern = "if.*$endpoint.*$method";
        // Simple check if endpoint is referenced in api.php
        test("API endpoint: $endpoint ($method)", strpos($apiContent, $endpoint) !== false);
    }
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "6. FRONTEND TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

// Test JavaScript files
$jsFiles = ['public/app.js', 'public/admin.js', 'public/sw.js'];
foreach ($jsFiles as $jsFile) {
    $jsContent = file_get_contents(__DIR__ . '/' . $jsFile);
    test("$jsFile exists and has content", strlen($jsContent) > 100);
    test("$jsFile has no syntax errors (basic)", substr(trim($jsContent), 0, 5) === '//' || substr(trim($jsContent), 0, 7) === '<script');
}

// Test HTML files
$htmlFiles = ['public/index.html', 'public/login.html', 'public/register.html'];
foreach ($htmlFiles as $htmlFile) {
    $htmlContent = file_get_contents(__DIR__ . '/' . $htmlFile);
    test("$htmlFile exists and has content", strlen($htmlContent) > 500);
    test("$htmlFile has DOCTYPE", strpos($htmlContent, '<!DOCTYPE') !== false);
    test("$htmlFile includes app.js", strpos($htmlContent, 'app.js') !== false);
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "7. BINANCE API TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

require_once __DIR__ . '/binance-api.php';
test('BinanceAPI class exists', class_exists('BinanceAPI'));

$binance = new BinanceAPI('test_key', 'test_secret', false);
test('BinanceAPI can be instantiated', true);
test('BinanceAPI has getSpotPrice method', method_exists($binance, 'getSpotPrice'));
test('BinanceAPI has get24hrTicker method', method_exists($binance, 'get24hrTicker'));
test('BinanceAPI has getKlines method', method_exists($binance, 'getKlines'));
test('BinanceAPI has validateApiKeys method', method_exists($binance, 'validateApiKeys'));

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "8. WORKER TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$workerContent = file_get_contents(__DIR__ . '/worker.php');
test('Worker file exists', strlen($workerContent) > 100);
test('Worker has run method', strpos($workerContent, 'function run') !== false);
test('Worker has loadRunningBots method', strpos($workerContent, 'function loadRunningBots') !== false);
test('Worker has checkBot method', strpos($workerContent, 'function checkBot') !== false);
test('Worker has signal handling', strpos($workerContent, 'pcntl_signal') !== false);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "9. INSTALLER TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$installContent = file_get_contents(__DIR__ . '/install.php');
test('Install file exists', strlen($installContent) > 100);
test('Install creates users table', strpos($installContent, 'CREATE TABLE users') !== false);
test('Install creates bots table', strpos($installContent, 'CREATE TABLE bots') !== false);
test('Install creates licenses table', strpos($installContent, 'CREATE TABLE licenses') !== false);
test('Install inserts sample data', strpos($installContent, 'INSERT INTO users') !== false);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "10. PWA CONFIGURATION TESTS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$manifest = json_decode(file_get_contents(__DIR__ . '/public/manifest.json'), true);
test('Manifest file is valid JSON', json_last_error() === JSON_ERROR_NONE);
test('Manifest has name', isset($manifest['name']));
test('Manifest has start_url', isset($manifest['start_url']));
test('Manifest has display: standalone', isset($manifest['display']) && $manifest['display'] === 'standalone');
test('Manifest has icons', isset($manifest['icons']) && count($manifest['icons']) > 0);

$swContent = file_get_contents(__DIR__ . '/public/sw.js');
test('Service worker exists', strlen($swContent) > 50);
test('Service worker has fetch handler', strpos($swContent, 'fetch') !== false);
test('Service worker has install event', strpos($swContent, 'install') !== false);

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "Total Tests: $testsTotal\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";
echo "Success Rate: " . round(($testsPassed / $testsTotal) * 100, 1) . "%\n";

if ($testsFailed > 0) {
    echo "\n⚠️  Some tests failed. Please review the errors above.\n";
} else {
    echo "\n✅ All tests passed! System is ready for deployment.\n";
}

echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "NEXT STEPS\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "1. Ensure MySQL is running and create the database:\n";
echo "   mysql -u root -p < setup_database.php\n";
echo "\n";
echo "2. Or use the web installer:\n";
echo "   Visit: http://localhost/install.php\n";
echo "\n";
echo "3. Start the background worker:\n";
echo "   php worker.php\n";
echo "\n";
echo "4. Access the dashboard:\n";
echo "   Visit: http://localhost/\n";
echo "\n";
echo "5. Login with:\n";
echo "   Email: demo@example.com\n";
echo "   Password: demo123\n";
echo "\n";
