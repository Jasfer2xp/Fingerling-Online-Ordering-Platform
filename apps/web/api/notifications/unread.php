<?php
require_once '../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $user_id = get_user_id();
    $user_type = get_user_type();
    
    // For now, return empty notifications array
    // This can be expanded later with actual notification functionality
    $notifications = [];
    
    // You can add actual notification logic here later
    // For example:
    /*
    $stmt = $database->prepare("
        SELECT id, title, message, type, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    */
    
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
