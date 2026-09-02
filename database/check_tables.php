<?php
/**
 * Check Database Tables
 * Fingerling Online Ordering Platform
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

try {
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Current Database Tables:\n";
    echo "========================\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $index => $table) {
        echo ($index + 1) . ". $table\n";
    }
    
    echo "\nTotal tables: " . count($tables) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>