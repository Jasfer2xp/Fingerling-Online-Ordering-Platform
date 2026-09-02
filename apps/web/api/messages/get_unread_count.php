<?php
require_once '../../config/config.php';
require_once '../../includes/session_guard.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

$user_id = get_user_id();
$user_type = get_user_type();

try {
    $unread_count = 0;
    
    if ($user_type === 'customer') {
        $sql = "SELECT id FROM customers WHERE user_id = ?";
        $customer = $database->fetch($sql, [$user_id]);
        
        if ($customer) {
            $sql = "SELECT COUNT(*) as unread_count 
                    FROM messages m
                    JOIN conversations c ON m.conversation_id = c.id
                    WHERE c.customer_id = ? AND m.receiver_id = ? AND m.is_read = 0";
            $result = $database->fetch($sql, [$customer['id'], $user_id]);
            $unread_count = $result ? (int)$result['unread_count'] : 0;
        }
    } elseif ($user_type === 'supplier') {
        $sql = "SELECT id FROM suppliers WHERE user_id = ?";
        $supplier = $database->fetch($sql, [$user_id]);
        
        if ($supplier) {
            $sql = "SELECT COUNT(*) as unread_count 
                    FROM messages m
                    JOIN conversations c ON m.conversation_id = c.id
                    WHERE c.supplier_id = ? AND m.receiver_id = ? AND m.is_read = 0";
            $result = $database->fetch($sql, [$supplier['id'], $user_id]);
            $unread_count = $result ? (int)$result['unread_count'] : 0;
        }
    }
    
    echo json_encode(['success' => true, 'count' => $unread_count]);
    
} catch (Exception $e) {
    error_log("Get unread count error: " . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0]);
}

