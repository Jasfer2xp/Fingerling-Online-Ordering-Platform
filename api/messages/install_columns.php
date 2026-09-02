<?php
/**
 * One-time script to add required columns for auto payment messages
 * Run this once: https://fingerling.shop/capstone/api/messages/install_columns.php
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Only allow from trusted hosts
$allowed_hosts = ['localhost', '127.0.0.1', '::1', 'fingerling.shop', 'www.fingerling.shop'];
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, $allowed_hosts) && strpos($host, 'localhost') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed in production']);
    exit;
}

$results = [];
$errors = [];

try {
    // Check current columns
    $columns = $database->fetchAll(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'"
    );
    $names = array_map(static fn($row) => $row['COLUMN_NAME'], $columns ?? []);
    
    // Add is_auto column if it doesn't exist
    if (!in_array('is_auto', $names, true)) {
        try {
            $database->query("ALTER TABLE messages ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER message");
            $results[] = "Added is_auto column";
        } catch (Exception $e) {
            $errors[] = "Failed to add is_auto: " . $e->getMessage();
        }
    } else {
        $results[] = "is_auto column already exists";
    }
    
    // Add order_id column if it doesn't exist
    if (!in_array('order_id', $names, true)) {
        try {
            $database->query("ALTER TABLE messages ADD COLUMN order_id INT NULL AFTER is_auto");
            $results[] = "Added order_id column";
        } catch (Exception $e) {
            $errors[] = "Failed to add order_id: " . $e->getMessage();
        }
    } else {
        $results[] = "order_id column already exists";
    }
    
    // Add index if it doesn't exist
    $indexes = $database->fetchAll(
        "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_messages_order_auto'"
    );
    if (empty($indexes)) {
        try {
            $database->query("CREATE INDEX idx_messages_order_auto ON messages (order_id, is_auto)");
            $results[] = "Added index idx_messages_order_auto";
        } catch (Exception $e) {
            $errors[] = "Failed to add index: " . $e->getMessage();
        }
    } else {
        $results[] = "Index idx_messages_order_auto already exists";
    }
    
    echo json_encode([
        'success' => empty($errors),
        'results' => $results,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

