<?php
/**
 * Migration: Add expires_at column to licenses and update existing records
 */

require_once __DIR__ . '/db.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Add expires_at column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE licenses ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER validity_days");
        echo "Added expires_at column to licenses table.\n";
    } catch (Exception $e) {
        echo "expires_at column may already exist or error: " . $e->getMessage() . "\n";
    }
    
    // Update existing licenses without expires_at
    $stmt = $pdo->query("SELECT id, validity_days, created_at FROM licenses WHERE expires_at IS NULL");
    $licenses = $stmt->fetchAll();
    
    echo "Found " . count($licenses) . " licenses without expiration date.\n";
    
    foreach ($licenses as $lic) {
        $validityDays = $lic['validity_days'] ?? 30;
        $createdAt = $lic['created_at'] ?? date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime($createdAt . " + {$validityDays} days"));
        
        $updateStmt = $pdo->prepare("UPDATE licenses SET expires_at = ? WHERE id = ?");
        $updateStmt->execute([$expiresAt, $lic['id']]);
        echo "Updated license ID {$lic['id']} with expires_at: {$expiresAt}\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
