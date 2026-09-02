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
    
    // Fetch recent notifications (limit to 5 for dropdown)
    $sql = "SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5";
    $notifications = $database->fetchAll($sql, [$user_id]);
    
    // Format the notifications for display
    date_default_timezone_set('Asia/Manila');
    foreach ($notifications as &$notification) {
        // Format the date in Manila timezone
        $dt = new DateTime($notification['created_at'], new DateTimeZone('Asia/Manila'));
        $notification['formatted_date'] = $dt->format('M j, Y \a\t g:i A');
        $notification['time_ago'] = time_ago($notification['created_at']);
        
        // Add order ID if this is an order notification
        if (preg_match('/order #(\d+)/i', $notification['message'], $matches)) {
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

/**
 * Calculate time ago (using Manila timezone)
 */
function time_ago($timestamp) {
    date_default_timezone_set('Asia/Manila');
    
    try {
        // Parse timestamp as Manila time
        $dt = new DateTime($timestamp, new DateTimeZone('Asia/Manila'));
        $current = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $diff = $current->getTimestamp() - $dt->getTimestamp();
    } catch (Exception $e) {
        // Fallback to strtotime
        $time_ago = strtotime($timestamp);
        $current_time = time();
        $diff = $current_time - $time_ago;
    }
    
    $seconds = $diff;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return "$minutes minute" . ($minutes > 1 ? 's' : '') . " ago";
    } else if ($hours <= 24) {
        return "$hours hour" . ($hours > 1 ? 's' : '') . " ago";
    } else if ($days <= 7) {
        return "$days day" . ($days > 1 ? 's' : '') . " ago";
    } else if ($weeks <= 4.3) {
        return "$weeks week" . ($weeks > 1 ? 's' : '') . " ago";
    } else if ($months <= 12) {
        return "$months month" . ($months > 1 ? 's' : '') . " ago";
    } else {
        return "$years year" . ($years > 1 ? 's' : '') . " ago";
    }
}
?>