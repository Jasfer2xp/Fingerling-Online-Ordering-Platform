<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get the input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $order_id = intval($input['order_id'] ?? 0);
    
    if (!$order_id) {
        throw new Exception('Missing order ID');
    }
    
    // Create Order instance and cancel the order
    $order = new Order($database);
    $order->updateOrderStatus($order_id, 'cancelled');
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}