<?php
/**
 * Comprehensive Database Cleanup Script
 * Fingerling Online Ordering Platform
 * 
 * This script cleans up the database by:
 * 1. Removing unused/duplicate tables
 * 2. Cleaning orphaned records
 * 3. Optimizing tables
 * 
 * Usage: php comprehensive_cleanup.php
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

echo "Fingerling Marketplace Database Cleanup\n";
echo "=====================================\n\n";

try {
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n\n";
    
    // Get initial table count
    $stmt = $pdo->query("SHOW TABLES");
    $initial_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $initial_count = count($initial_tables);
    
    echo "Initial tables: $initial_count\n";
    
    // Tables to remove (unused or duplicates)
    $tables_to_remove = [
        'customer_notes'  // Not used in current system
    ];
    
    $removed_tables = 0;
    
    // Remove unused tables
    echo "\nRemoving unused tables...\n";
    foreach ($tables_to_remove as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "  - Removed table: $table\n";
            $removed_tables++;
        } catch (Exception $e) {
            echo "  - Error removing $table: " . $e->getMessage() . "\n";
        }
    }
    
    // Clean orphaned records
    echo "\nCleaning orphaned records...\n";
    $cleanup_queries = [
        "feedback" => "DELETE FROM feedback WHERE order_id NOT IN (SELECT id FROM orders)",
        "notifications" => "DELETE FROM notifications WHERE user_id NOT IN (SELECT id FROM users)",
        "audit_log" => "DELETE FROM audit_log WHERE user_id NOT IN (SELECT id FROM users)",
        "supplier_notes" => "DELETE FROM supplier_notes WHERE supplier_id NOT IN (SELECT id FROM suppliers)",
        "order_tracking" => "DELETE FROM order_tracking WHERE order_id NOT IN (SELECT id FROM orders)"
    ];
    
    $total_deleted = 0;
    foreach ($cleanup_queries as $table => $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                echo "  - Removed $deleted orphaned records from $table\n";
            } else {
                echo "  - No orphaned records found in $table\n";
            }
            $total_deleted += $deleted;
        } catch (Exception $e) {
            echo "  - Error cleaning $table: " . $e->getMessage() . "\n";
        }
    }
    
    // Optimize tables
    echo "\nOptimizing tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE `$table`");
            echo "  - Optimized $table\n";
        } catch (Exception $e) {
            echo "  - Error optimizing $table: " . $e->getMessage() . "\n";
        }
    }
    
    // Final status
    $stmt = $pdo->query("SHOW TABLES");
    $final_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $final_count = count($final_tables);
    
    echo "\nCleanup Summary:\n";
    echo "================\n";
    echo "Tables before cleanup: $initial_count\n";
    echo "Tables removed: $removed_tables\n";
    echo "Tables after cleanup: $final_count\n";
    echo "Orphaned records removed: $total_deleted\n";
    echo "\nDatabase cleanup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>