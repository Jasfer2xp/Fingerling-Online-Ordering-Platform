<?php
// api/payments/capture_authorization.php  ← Use this name

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/paypal_helpers.php';
require_once '../../includes/auto_payment_message.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['authorization_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing authorization_id']));
}

$authorization_id = trim($input['authorization_id']);

// === 1. Verify pending order exists ===
$pending = $database->fetch(
    "SELECT po.*, c.user_id as customer_user_id 
     FROM pending_orders po 
     JOIN customers c ON po.customer_id = c.id 
     WHERE po.authorization_id = ? AND po.status = 'pending'",
    [$authorization_id]
);

if (!$pending) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'No pending order found']));
}

$expected_amount = floatval($pending['total']);
$customer_id = $pending['customer_id'];
$cart_items = json_decode($pending['cart_data'], true);

// === 2. Capture the authorization using our secure helper ===
$result = capture_paypal_payment($authorization_id);

if (!$result['success']) {
    error_log("PayPal Capture Failed [{$authorization_id}]: " . $result['message']);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Payment capture failed: ' . $result['message']]));
}

$capture_id = $result['capture_id'];
$captured_amount = $result['data']['amount']['value'] ?? 0;

// === 3. Security: Verify captured amount matches ===
if (abs($captured_amount - $expected_amount) > 0.01) {
    error_log("Amount mismatch! Expected: $expected_amount, Got: $captured_amount");
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Payment amount mismatch']));
}

// === 4. Everything safe → create real order in DB transaction ===
try {
    $database->beginTransaction();

    // Create final order(s)
    $order = new Order($database);
    $order_data = [
        'customer_id' => $customer_id,
        'total_amount' => $captured_amount
    ];
    $order_ids = $order->createOrderFromCart($order_data, $cart_items);

    if (!$order_ids || (is_array($order_ids) && empty($order_ids))) {
        throw new Exception('Failed to create order');
    }

    $first_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;

    // Update main order
    $database->query(
        "UPDATE orders SET 
            payment_status = 'paid',
            payment_reference = ?,
            payment_date = NOW(),
            status = 'confirmed'
         WHERE id = ?",
        [$capture_id, $first_order_id]
    );

    // Update payment record
    $database->query(
        "UPDATE payments SET 
            order_id = ?,
            transaction_id = ?,
            status = 'completed',
            updated_at = NOW()
         WHERE authorization_id = ?",
        [$first_order_id, $capture_id, $authorization_id]
    );

    // Mark pending as completed
    $database->query(
        "UPDATE pending_orders SET 
            status = 'completed',
            updated_at = NOW()
         WHERE authorization_id = ?",
        [$authorization_id]
    );

    // Clear customer's cart
    $cart = new Cart($database, $customer_id);
    $cart->clearCart();

    $database->commit();

    // Send auto payment message after successful payment
    send_auto_payment_message($database, $first_order_id);

    echo json_encode([
        'success' => true,
        'message' => 'Payment captured and order confirmed!',
        'order_id' => $first_order_id,
        'capture_id' => $capture_id
    ]);

} catch (Exception $e) {
    $database->rollback();
    error_log("Capture DB Error [{$authorization_id}]: " . $e->getMessage());

    // Optional: attempt to refund if something went wrong after capture
    // (very rare, but safe)
    // void_paypal_authorization($authorization_id);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Order creation failed. Please contact support.']);
}