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
$paypal_order_id = $input['orderID'] ?? '';
$csrf = $input['csrf_token'] ?? '';

if (!isset($_SESSION['paypal_csrf_token']) || !hash_equals($_SESSION['paypal_csrf_token'], $csrf)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF']));
}

if (empty($_SESSION['pending_cart_data'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'No cart data']));
}

$cart_data = $_SESSION['pending_cart_data'];
$total_with_fee = $cart_data['total_with_fee'];
$customer_id = $cart_data['customer_id'];
$cart_json = json_encode($cart_data['cart_items']);

$token = get_paypal_access_token();
if (!$token) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'PayPal auth failed']));
}

$endpoint = PAYPAL_MODE === 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';
$url = $endpoint . '/v2/checkout/orders/' . urlencode($paypal_order_id) . '/authorize';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 201) {
    $err = json_decode($response, true);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => $err['message'] ?? 'Authorization failed']));
}

$result = json_decode($response, true);
$auth_id = $result['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
if (!$auth_id) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'No authorization ID']));
}

try {
    $database->beginTransaction();

    // Insert payment record (authorized)
    $database->query(
        "INSERT INTO payments (payment_method, amount, authorization_id, status, payment_date, created_at) 
         VALUES ('paypal', ?, ?, 'authorized', NOW(), NOW())",
        [$total_with_fee, $auth_id]
    );
    $payment_id = $database->lastInsertId();

    // Insert pending order
    $database->query(
        "INSERT INTO pending_orders 
         (customer_id, paypal_order_id, authorization_id, total, cart_data, status, expires_at) 
         VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY))",
        [$customer_id, $paypal_order_id, $auth_id, $total_with_fee, $cart_json]
    );
    $pending_id = $database->lastInsertId();

    $database->commit();

    unset($_SESSION['pending_cart_data'], $_SESSION['paypal_csrf_token']);

    echo json_encode(['success' => true, 'pending_id' => $pending_id]);
} catch (Exception $e) {
    $database->rollback();
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}