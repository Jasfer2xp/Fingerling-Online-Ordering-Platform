<?php
/**
 * Migration to update size_category column in inventory table
 * Changes size_category from VARCHAR(50) to VARCHAR(20) to accommodate numeric values with units
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';

try {
    // Check if the column exists and its current type
    $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = 'fingerling_marketplace' 
            AND TABLE_NAME = 'inventory' 
            AND COLUMN_NAME = 'size_category'";
    
    $result = $database->fetch($sql);
    
    if ($result) {
        echo "Current column definition: " . json_encode($result) . "\n";
        
        // Update the column to be more appropriate for numeric values with units
        $sql = "ALTER TABLE inventory MODIFY size_category VARCHAR(20)";
        $database->query($sql);
        
        echo "Successfully updated size_category column to VARCHAR(20)\n";
    } else {
        echo "Column size_category not found in inventory table\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}