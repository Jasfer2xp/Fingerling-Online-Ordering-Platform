<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "Checking database tables...\n";

try {
    global $database;
    
    // Check if user_2fa_otps table exists
    $tables = $database->fetchAll("SHOW TABLES LIKE 'user_2fa_otps'");
    if (count($tables) > 0) {
        echo "user_2fa_otps table exists\n";
        
        // Show table structure
        $columns = $database->fetchAll("DESCRIBE user_2fa_otps");
        echo "Table structure:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "user_2fa_otps table does not exist\n";
        
        // Check if user_otps table exists as fallback
        $tables = $database->fetchAll("SHOW TABLES LIKE 'user_otps'");
        if (count($tables) > 0) {
            echo "user_otps table exists (fallback)\n";
            
            // Show table structure
            $columns = $database->fetchAll("DESCRIBE user_otps");
            echo "Table structure:\n";
            foreach ($columns as $column) {
                echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
            }
        } else {
            echo "user_otps table does not exist either\n";
        }
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

// Clean up
unlink(__FILE__);
?>