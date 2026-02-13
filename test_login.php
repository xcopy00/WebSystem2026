<?php
/**
 * Test Login API
 */
require_once 'db.php';
require_once 'db_operations.php';

$email = 'demo@example.com';
$password = 'demo123';

try {
    $db = new DBOperations();
    $user = $db->getUserByEmail($email);
    
    if (!$user) {
        echo "User not found\n";
        exit;
    }
    
    if (!$db->validatePassword($user, $password)) {
        echo "Invalid password\n";
        exit;
    }
    
    echo "Login successful!\n";
    echo "User ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
