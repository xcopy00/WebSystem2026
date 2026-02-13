<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = Database::getInstance()->getConnection();

echo "Licenses table:\n";
$stmt = $pdo->query("DESCRIBE licenses");
print_r($stmt->fetchAll());

echo "\n\nVIP Subscriptions table:\n";
$stmt = $pdo->query("DESCRIBE vip_subscriptions");
print_r($stmt->fetchAll());
