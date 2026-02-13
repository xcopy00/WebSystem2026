<?php
/**
 * Generate test license codes for testing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_operations.php';

echo "Generating test license codes...\n\n";

$db = new DBOperations();

$testCodes = [
    ['type' => 'VIP1', 'notes' => 'Test - Starter'],
    ['type' => 'VIP2', 'notes' => 'Test - Trader'],
    ['type' => 'VIP3', 'notes' => 'Test - Pro (Recommended)'],
    ['type' => 'VIP4', 'notes' => 'Test - Elite'],
    ['type' => 'VIP5', 'notes' => 'Test - Master'],
    ['type' => 'VIP6', 'notes' => 'Test - Legend'],
];

foreach ($testCodes as $code) {
    $licenseCode = $db->generateLicenseCode($code['type'], 30, $code['notes']);
    echo "Generated {$code['type']} code: {$licenseCode}\n";
}

echo "\nDone! Use these codes to test VIP subscription activation.\n";
