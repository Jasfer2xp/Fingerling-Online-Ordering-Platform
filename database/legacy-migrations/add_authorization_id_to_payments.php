<?php
// Migration to add authorization_id column to payments table

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';

try {
    // Add authorization_id column to payments table
    $sql = "ALTER TABLE payments ADD COLUMN authorization_id VARCHAR(255) NULL AFTER transaction_id";
    $database->query($sql);
    
    echo "Migration successful: Added authorization_id column to payments table\n";
} catch (Exception $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration skipped: authorization_id column already exists\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}