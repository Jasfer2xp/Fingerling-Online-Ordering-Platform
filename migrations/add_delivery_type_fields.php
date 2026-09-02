<?php
require_once '../config/config.php';

try {
    // Add delivery type columns to suppliers table
    $sql = "ALTER TABLE suppliers 
            ADD COLUMN IF NOT EXISTS supports_truck BOOLEAN DEFAULT TRUE,
            ADD COLUMN IF NOT EXISTS supports_boat BOOLEAN DEFAULT FALSE";
    
    $database->query($sql);
    echo "Added delivery type columns to suppliers table\n";
    
    // Add delivery_type column to orders table
    $sql = "ALTER TABLE orders 
            ADD COLUMN IF NOT EXISTS delivery_type ENUM('truck', 'boat') NULL";
    
    $database->query($sql);
    echo "Added delivery_type column to orders table\n";
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>