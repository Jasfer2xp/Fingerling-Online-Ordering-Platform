<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/session_guard.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $user_id = get_user_id();
    $user_type = get_user_type();
    
    // Get customer_id if user is a customer
    $customer_id = null;
    if ($user_type === 'customer') {
        $customer_sql = "SELECT id FROM customers WHERE user_id = ?";
        $customer = $database->fetch($customer_sql, [$user_id]);
        $customer_id = $customer['id'] ?? null;
    }
    
    if (!$customer_id) {
        throw new Exception('Customer profile not found');
    }
    
    // Mark all notifications as read for this customer (using customer_id)
    $sql = "UPDATE notifications SET is_read = 1 WHERE customer_id = ? AND is_read = 0";
    $database->query($sql, [$customer_id]);
    
    // Get updated count (should be 0)
    $count_sql = "SELECT COUNT(*) as count FROM notifications WHERE customer_id = ? AND is_read = 0";
    $result = $database->fetch($count_sql, [$customer_id]);
    $unread_count = $result['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'All notifications marked as read',
        'unread_count' => (int)$unread_count
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

