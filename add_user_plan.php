<?php
/**
 * Add User (Free) plan to vip_subscriptions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = Database::getInstance()->getConnection();

echo "Adding User plan to vip_subscriptions...\n";

$stmt = $pdo->prepare("
    INSERT INTO vip_subscriptions (code, name, type, price, duration_days, max_bots, max_trades_per_day, api_calls_limit, features, is_active)
    VALUES ('USER', 'Free User', 'User', 0, 0, 1, 10, 100, '[\"grid\"]', 1)
    ON DUPLICATE KEY UPDATE name = VALUES(name), max_bots = VALUES(max_bots), features = VALUES(features)
");

$stmt->execute();

echo "User plan added/updated successfully!\n";

// Verify
$stmt = $pdo->query("SELECT * FROM vip_subscriptions WHERE type = 'User'");
$plan = $stmt->fetch();
print_r($plan);
