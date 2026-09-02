<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/paypal_helpers.php';

header('Content-Type: application/json');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? '';
$supplier_id = $input['supplier_id'] ?? '';

if (empty($order_id) || empty($supplier_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing order ID or supplier ID']));
}

// Check if order belongs to this supplier and has authorized PayPal payment
$sql = "SELECT o.*, p.authorization_id, p.payment_method 
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ? AND o.supplier_id = ? AND p.payment_method = 'paypal' AND p.status = 'authorized'";
$order = $database->fetch($sql, [$order_id, $supplier_id]);

if (!$order) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Order not found or not eligible for cancellation']));
}

// Void the PayPal authorization
$void_result = void_paypal_authorization($order['authorization_id']);

if (!$void_result['success']) {
    error_log("PayPal Void Failed for order $order_id: " . $void_result['message']);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Failed to void PayPal payment: ' . $void_result['message']]));
}

try {
    $database->beginTransaction();
    
    // Update payment status
    $database->query(
        "UPDATE payments SET status = 'voided', voided_at = NOW() WHERE authorization_id = ?",
        [$order['authorization_id']]
    );
    
    // Update order status
    $database->query(
        "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
        [$order_id]
    );
    
    $database->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled and payment voided successfully'
    ]);
} catch (Exception $e) {
    $database->rollback();
    error_log("Database error during order cancellation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order. Please try again.']);
}