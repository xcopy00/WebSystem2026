<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = Database::getInstance()->getConnection();

echo "License History table:\n";
$stmt = $pdo->query("DESCRIBE license_history");
print_r($stmt->fetchAll());
