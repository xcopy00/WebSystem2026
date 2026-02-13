<?php
/**
 * Create settings table for system configuration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = Database::getInstance()->getConnection();

echo "Creating settings table...\n";

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "Settings table created!\n";

// Set default values
echo "Setting default values...\n";
setSetting('registration_enabled', '1');
echo "Default values set!\n";
