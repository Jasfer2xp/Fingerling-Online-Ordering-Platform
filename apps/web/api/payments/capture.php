<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/paypal_helpers.php';
require_once '../../includes/auto_payment_message.php';

header('Content-Type: application/json');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
$authorization_id = $input['authorization_id'] ?? '';

if (empty($authorization_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing authorization ID']));
}

$pending = $database->fetch(
    "SELECT po.*, p.id AS payment_id 
     FROM pending_orders po 
     JOIN payments p ON po.authorization_id = p.authorization_id 
     WHERE po.authorization_id = ? AND po.status = 'pending'",
    [$authorization_id]
);

if (!$pending) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'No pending order']));
}

$token = get_paypal_access_token();
if (!$token) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'PayPal auth failed']));
}

$endpoint = PAYPAL_MODE === 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';
$url = $endpoint . '/v2/payments/authorizations/' . urlencode($authorization_id) . '/capture';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'PayPal-Request-Id: capture_' . uniqid()
    ]
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 201) {
    $err = json_decode($response, true);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => $err['message'] ?? 'Capture failed']));
}

$result = json_decode($response, true);
$capture_id = $result['id'] ?? null;
if (!$capture_id || ($result['status'] ?? '') !== 'COMPLETED') {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Capture not completed']));
}

try {
    $database->beginTransaction();

    $cart_items = json_decode($pending['cart_data'], true);
    $order_data = [
        'customer_id' => $pending['customer_id'],
        'total_amount' => $pending['total'],
        'payment_method' => 'paypal',
        'subtotal' => $pending['total'],
        'delivery_fee' => 0
    ];

    $order = new Order($database);
    $order_ids = $order->createOrderFromCart($order_data, $cart_items);
    $first_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;

    // Update payment
    $database->query(
        "UPDATE payments SET 
            order_id = ?, 
            transaction_id = ?, 
            status = 'captured', 
            captured_at = NOW() 
         WHERE authorization_id = ?",
        [$first_order_id, $capture_id, $authorization_id]
    );

    // Update pending order
    $database->query(
        "UPDATE pending_orders SET status = 'confirmed', order_id = ? WHERE id = ?",
        [$first_order_id, $pending['id']]
    );

    // Process payment success (this will update status to confirmed_and_paid and send auto message)
    $payment_data = [
        'amount' => $pending['total'],
        'transaction_id' => $capture_id,
        'payment_method' => 'PayPal',
        'payer_email' => null,
        'currency' => 'PHP'
    ];
    
    $order->processPaymentSuccess($first_order_id, $payment_data);

    // Clear cart
    $cart = new Cart($database, $pending['customer_id']);
    $cart->clearCart();

    $database->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $first_order_id,
        'message' => 'Payment captured and order confirmed'
    ]);
} catch (Exception $e) {
    $database->rollback();
    error_log("Capture Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}