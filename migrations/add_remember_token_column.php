<?php
/**
 * Migration: Add remember_token column to users table
 * 
 * This migration adds the missing remember_token column to the users table
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';

try {
    // Add remember_token column to users table
    $sql = "ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL DEFAULT NULL AFTER updated_at";
    $database->query($sql);
    
    echo "Migration completed successfully!\n";
    echo "Added remember_token column to users table.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}