<?php
require_once '../config/config.php';

try {
    // Add location columns to customers table
    $sql = "ALTER TABLE customers 
            ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
            ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL";
    
    $database->execute($sql);
    
    echo "Location fields added successfully to customers table.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>