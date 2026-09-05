<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/session_guard.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$notification_id || !$user_id) {
        throw new Exception('Invalid request');
    }
    
    // Get customer_id if user is a customer
    $customer_id = null;
    if (get_user_type() === 'customer') {
        $customer_sql = "SELECT id FROM customers WHERE user_id = ?";
        $customer = $database->fetch($customer_sql, [$user_id]);
        $customer_id = $customer['id'] ?? null;
    }
    
    if (!$customer_id) {
        throw new Exception('Customer profile not found');
    }
    
    // Verify the notification belongs to the customer (using customer_id)
    $sql = "SELECT id FROM notifications WHERE id = ? AND customer_id = ?";
    $notification = $database->fetch($sql, [$notification_id, $customer_id]);
    
    if (!$notification) {
        throw new Exception('Notification not found');
    }
    
    // Mark as read
    $sql = "UPDATE notifications SET is_read = true WHERE id = ?";
    $database->query($sql, [$notification_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
