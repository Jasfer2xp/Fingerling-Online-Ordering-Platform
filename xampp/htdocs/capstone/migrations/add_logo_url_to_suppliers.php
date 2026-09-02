<?php
require_once '../config/config.php';

try {
    // Add logo_url column to suppliers table
    $sql = "ALTER TABLE suppliers ADD COLUMN logo_url VARCHAR(500) NULL AFTER pond_photo";
    
    $database->query($sql);
    echo "Added logo_url column to suppliers table\n";
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>