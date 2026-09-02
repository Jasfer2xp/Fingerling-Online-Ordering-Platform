<?php
require_once '../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $user_id = get_user_id();
    
    // Fetch recent notifications (limit to 5 for dropdown)
    $sql = "SELECT id, title, message, type, created_at, is_read 
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5";
    $notifications = $database->fetchAll($sql, [$user_id]);
    
    // Format notifications
    foreach ($notifications as &$notification) {
        // Add time ago format
        $notification['time_ago'] = time_ago($notification['created_at']);
        
        // Check if this is an order-related notification
        // Look for patterns like "order #123" or "order 123"
        if (preg_match('/order[^\d]*(\d+)/i', $notification['message'], $matches)) {
            $notification['order_id'] = $matches[1];
        } else if (preg_match('/order[^\d]*(\d+)/i', $notification['title'], $matches)) {
            $notification['order_id'] = $matches[1];
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>