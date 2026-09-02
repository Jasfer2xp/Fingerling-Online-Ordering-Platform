<?php
/**
 * Test endpoint to manually trigger auto payment message
 * Usage: GET api/messages/test_auto_message.php?order_id=115
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auto_payment_message.php';

header('Content-Type: application/json');

// Only allow from trusted hosts
$allowed_hosts = ['localhost', '127.0.0.1', '::1', 'fingerling.shop', 'www.fingerling.shop'];
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, $allowed_hosts) && strpos($host, 'localhost') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed in production']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order_id']);
    exit;
}

try {
    $result = send_auto_payment_message($database, $order_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Auto payment message sent successfully for order #{$order_id}"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "Failed to send auto payment message for order #{$order_id}. Check error logs."
        ]);
    }
} catch (Exception $e) {
    error_log("Test auto message error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

