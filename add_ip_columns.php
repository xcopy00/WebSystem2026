<?php
/**
 * Migration: Add IP address columns to users table
 */

require_once __DIR__ . '/db.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Add ip_address column (current IP)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER api_secret");
    
    // Add last_ip column (previous IP)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) NULL AFTER ip_address");
    
    echo "Successfully added IP address columns to users table.\n";
    echo "Columns added: ip_address, last_ip\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
