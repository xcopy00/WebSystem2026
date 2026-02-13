<?php
/**
 * Configuration File - Binance AI Grid Trading System (PHP)
 * Edit this file directly or use environment variables
 */

// ============================================
// DATABASE CONFIGURATION (MySQL)
// ============================================
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ai_trading_bot');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// ============================================
// APPLICATION SETTINGS
// ============================================
define('APP_NAME', 'Binance AI Trade');
define('APP_URL', 'http://localhost');

// ============================================
// JWT SECURITY
// ============================================
// IMPORTANT: Change this secret key in production!
define('JWT_SECRET', 'binance-ai-trade-secret-key-2024-please-change-this');
define('JWT_EXPIRY', 86400); // 24 hours

// ============================================
// BINANCE API BASE URLs
// ============================================
define('BINANCE_SPOT_BASE_URL', 'https://api.binance.com');
define('BINANCE_FUTURES_BASE_URL', 'https://fapi.binance.com');

// ============================================
// GRID TRADING DEFAULTS
// ============================================
define('DEFAULT_GRID_COUNT', 10);
define('DEFAULT_INTERVAL', 30); // seconds
define('DEFAULT_STOP_LOSS_PERCENT', 5);

// ============================================
// SECURITY: SESSION SETTINGS
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    // Uncomment below for HTTPS in production
    // ini_set('session.cookie_secure', 1);
    session_start();
}

// ============================================
// ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

// Create logs directory if not exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('UTC');
