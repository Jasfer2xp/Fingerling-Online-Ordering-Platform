<?php
/**
 * PayPal v2 Order Capture Verification API
 * Called after PayPal onApprove → capture
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/auto_payment_message.php';

// ---------------------------------------------------------------------
// 1. Prevent any output before JSON
// ---------------------------------------------------------------------
ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ---------------------------------------------------------------------
// 2. Only allow POST
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// ---------------------------------------------------------------------
// 3. Read & validate JSON input
// ---------------------------------------------------------------------
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid JSON payload']));
}

$paypal_order_id = $input['orderID'] ?? '';
if (empty($paypal_order_id)) {
    ob_clean();
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing PayPal order ID']));
}

// ---------------------------------------------------------------------
// 4. Start try-catch for clean error handling
// ---------------------------------------------------------------------
try {
    // 1. Capture payment on PayPal
    $capture_result = capturePayPalOrder($paypal_order_id);
    if (!$capture_result['success']) {
        throw new Exception($capture_result['message']);
    }

    $captured_amount = $capture_result['amount'];
    $transaction_id  = $capture_result['transaction_id'];

    // 2. Retrieve pending order from session
    if (!isset($_SESSION['pending_order_data'])) {
        throw new Exception('No pending order found in session');
    }

    $pending = $_SESSION['pending_order_data'];
    $expected_amount = $pending['total_amount'];
    $customer_id     = $pending['customer_id'];
    $cart_items      = $pending['cart_items'] ?? [];
    $pending_for_order = $pending;
    unset($pending_for_order['cart_items']); // clean for createOrderFromCart

    // 3. Verify amount (allow small float variance)
    if (abs($captured_amount - $expected_amount) > 0.01) {
        throw new Exception('Payment amount mismatch');
    }

    // 4. Create order in database
    $order = new Order($database);
    $order_ids = $order->createOrderFromCart($pending_for_order, $cart_items);

    if (!$order_ids || (is_array($order_ids) && empty($order_ids))) {
        throw new Exception('Failed to create order in database');
    }

    $first_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;

    // 5. Update order status
    $sql = "UPDATE orders SET 
                payment_status = 'paid',
                payment_reference = ?,
                payment_date = NOW(),
                status = 'confirmed'
            WHERE id = ?";
    $database->query($sql, [$transaction_id, $first_order_id]);

    // 6. Insert payment record
    $sql = "INSERT INTO payments 
                (order_id, amount, payment_method, transaction_id, status, payment_date, created_at)
            VALUES 
                (?, ?, 'paypal', ?, 'completed', NOW(), NOW())";
    $database->query($sql, [$first_order_id, $expected_amount, $transaction_id]);

    // 7. Clear cart & session
    $cart = new Cart($database, $customer_id);
    $cart->clearCart();
    unset($_SESSION['pending_order_data']);

    // 8. Send auto payment message after successful payment
    send_auto_payment_message($database, $first_order_id);

    // 9. Success
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified and order confirmed',
        'order_id' => $first_order_id
    ]);

} catch (Exception $e) {
    error_log("PayPal Capture Error: " . $e->getMessage());
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit; // Ensure no further output

// ---------------------------------------------------------------------
// 9. Capture PayPal Order
// ---------------------------------------------------------------------
function capturePayPalOrder(string $orderID): array
{
    $endpoint = (PAYPAL_MODE === 'live')
        ? 'https://api.paypal.com'
        : 'https://api.sandbox.paypal.com';

    $url = $endpoint . '/v2/checkout/orders/' . urlencode($orderID) . '/capture';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . get_paypal_access_token(),
            'PayPal-Request-Id: ' . uniqid('capture_', true)
        ],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log cURL errors
    if ($curlError) {
        error_log("PayPal cURL Error (capture): $curlError");
        return ['success' => false, 'message' => 'Connection failed'];
    }

    // PayPal returns HTML on auth failure
    if (strpos($response, '<html') !== false || strpos($response, '<!DOCTYPE') !== false) {
        error_log("PayPal returned HTML (likely auth error): $response");
        return ['success' => false, 'message' => 'PayPal authentication failed'];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("PayPal invalid JSON on capture: $response");
        return ['success' => false, 'message' => 'Invalid response from PayPal'];
    }

    if ($httpCode !== 201) {
        $msg = $data['message'] ?? 'HTTP ' . $httpCode;
        return ['success' => false, 'message' => 'PayPal error: ' . $msg];
    }

    if (($data['status'] ?? '') !== 'COMPLETED') {
        return ['success' => false, 'message' => 'Order not completed: ' . ($data['status'] ?? 'unknown')];
    }

    $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture) {
        return ['success' => false, 'message' => 'No capture data found'];
    }

    return [
        'success'        => true,
        'amount'         => floatval($capture['amount']['value'] ?? 0),
        'transaction_id' => $capture['id'] ?? $orderID
    ];
}

// ---------------------------------------------------------------------
// 10. Get PayPal Access Token (safe & cached)
// ---------------------------------------------------------------------
if (!function_exists('get_paypal_access_token')) {
    function get_paypal_access_token(): string
    {
        // Optional: Cache token in session for ~1 hour
        if (isset($_SESSION['paypal_access_token']) && $_SESSION['paypal_token_expires'] > time()) {
            return $_SESSION['paypal_access_token'];
        }

        $endpoint = (PAYPAL_MODE === 'live')
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';

        $url = $endpoint . '/v1/oauth2/token';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: en_US'
            ],
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            error_log("PayPal Token Error: HTTP $httpCode | cURL: $curlError | Response: $response");
            return '';
        }

        $data = json_decode($response, true);
        $token = $data['access_token'] ?? '';
        $expires_in = $data['expires_in'] ?? 3600;

        // Cache in session
        $_SESSION['paypal_access_token'] = $token;
        $_SESSION['paypal_token_expires'] = time() + $expires_in - 60; // 1 min safety

        return $token;
    }
}