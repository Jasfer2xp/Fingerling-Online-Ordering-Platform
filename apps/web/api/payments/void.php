<?php
// api/payments/void_paypal_authorization.php  (or whatever you named it)

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/paypal_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
$authorization_id = trim($input['authorization_id'] ?? '');

if (empty($authorization_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing authorization ID']));
}

// Verify this authorization belongs to a pending order
$pending = $database->fetch(
    "SELECT po.*, p.payment_id FROM pending_orders po 
     LEFT JOIN payments p ON p.order_id = po.order_id 
     WHERE po.authorization_id = ? AND po.status = 'pending'",
    [$authorization_id]
);

if (!$pending) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'No pending order found or already processed']));
}

// Use our secure helper function (already tested and working)
$result = void_paypal_authorization($authorization_id);

if (!$result['success']) {
    error_log("PayPal Void Failed [{$authorization_id}]: " . $result['message']);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Failed to release funds: ' . $result['message']]));
}

// Success! PayPal hold is gone instantly → customer gets 100% back, no fee

try {
    $database->beginTransaction();

    // Update payments table
    $database->query(
        "UPDATE payments SET status = 'voided', voided_at = NOW() WHERE authorization_id = ?",
        [$authorization_id]
    );

    // Update pending orders
    $database->query(
        "UPDATE pending_orders SET status = 'cancelled', cancelled_at = NOW() WHERE authorization_id = ?",
        [$authorization_id]
    );

    // Optional: notify customer via email/SMS
    // send_cancellation_email($pending['customer_id'], $pending['order_id']);

    $database->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled and funds released instantly'
    ]);

} catch (Exception $e) {
    $database->rollback();
    error_log("DB Error on void [{$authorization_id}]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}