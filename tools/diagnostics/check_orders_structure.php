<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $sql = "DESCRIBE orders";
    $result = $database->fetchAll($sql);
    
    echo "Orders table structure:\n";
    foreach ($result as $row) {
        echo $row['Field'] . " " . $row['Type'] . " " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}