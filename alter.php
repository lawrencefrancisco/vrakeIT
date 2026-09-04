<?php
require 'includes/db.php';
$db = getDB();

try {
    // Your existing column addition
    $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
    
    // New columns required for the Forgot Password feature
    $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL");
    $db->exec("ALTER TABLE users ADD COLUMN reset_expires_at DATETIME NULL DEFAULT NULL");
    
    echo 'Database altered successfully! Added role, reset_token, and reset_expires_at columns.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>