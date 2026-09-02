<?php
/**
 * authorize_paypal_order.php
 * Called from PayPal JS onApprove()
 * - Authorizes the PayPal order (intent=AUTHORIZE)
 * - Stores authorization ID + pending order in DB
 * - Returns success/failure with meaningful messages
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/paypal_helpers.php';

header('Content-Type: application/json');
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// 1. Read & validate input
$input = json_decode(file_get_contents('php://input'), true);
$orderID      = $input['orderID'] ?? '';
$customer_id  = $input['customer_id'] ?? 0;
$expected_raw = $input['expected_total'] ?? 0;

if (empty($orderID)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing PayPal order ID']));
}
if (empty($customer_id) || !is_numeric($customer_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid customer ID']));
}

// 2. Get session data (fallback)
$pending = $_SESSION['pending_order_data'] ?? null;
if (!$pending) {
    error_log("No session data found, checking database");
    try {
        $stmt = $database->query(
            "SELECT * FROM pending_orders WHERE paypal_order_id = ? AND customer_id = ?",
            [$orderID, $customer_id]
        );
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pending) {
            error_log("No pending order found in database for OrderID: $orderID, CustomerID: $customer_id");
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Session expired or invalid order']));
        }
        $pending['cart_items'] = json_decode($pending['cart_data'], true);
        error_log("Found pending order in database: " . print_r($pending, true));
    } catch (Exception $e) {
        error_log("DB Error fetching pending order: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
}

$expected_amount = floatval($pending['total_amount'] ?? 0);
$cart_items      = $pending['cart_items'] ?? [];

error_log("Cart items: " . print_r($cart_items, true));
error_log("Expected amount: $expected_amount");

// Optional safety check
if ($expected_raw > 0 && abs($expected_raw - $expected_amount) > 0.01) {
    error_log("Amount mismatch: expected $expected_amount, got $expected_raw");
}

// 3. Authorize PayPal Order
error_log("Attempting to authorize PayPal order: $orderID");
$authResult = authorize_paypal_order($orderID);
if (!$authResult['success']) {
    error_log("PayPal Authorize Failed: " . $authResult['message']);
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => $authResult['message']]));
}
$authorization_id = $authResult['authorization_id'];
error_log("PayPal authorization successful. Authorization ID: $authorization_id");

// 4. Calculate total with PayPal fee (4.37%)
$paypal_fee = $expected_amount * 0.0437;
$total_with_fee = number_format($expected_amount + $paypal_fee, 2, '.', '');
error_log("Calculated total with fee: $total_with_fee (amount: $expected_amount, fee: $paypal_fee)");

// 5. Save to DB (atomic transaction)
error_log("Starting database transaction");
try {
    $database->beginTransaction();
    error_log("Transaction started");

    // Insert into pending_orders
    $cart_json = json_encode($cart_items, JSON_UNESCAPED_UNICODE);
    error_log("Cart JSON: $cart_json");
    
    $sql = "INSERT INTO pending_orders 
            (customer_id, paypal_order_id, authorization_id, total, cart_data, status, created_at, expires_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))";
    error_log("Executing pending_orders insert: $sql");
    error_log("Parameters: " . print_r([
        $customer_id,
        $orderID,
        $authorization_id,
        $total_with_fee,
        $cart_json
    ], true));
    
    $database->query($sql, [
        $customer_id,
        $orderID,
        $authorization_id,
        $total_with_fee,
        $cart_json
    ]);
    $pending_order_id = $database->lastInsertId();

    // Insert into payments
    $sql = "INSERT INTO payments 
            (payment_method, amount, authorization_id, status, payment_date, created_at)
            VALUES ('paypal', ?, ?, 'authorized', NOW(), NOW())";
    $stmt = $database->query($sql, [$total_with_fee, $authorization_id]);
    $payment_id = $database->lastInsertId();

    $database->commit();
    error_log("Transaction committed successfully");
} catch (Exception $e) {
    $database->rollBack();
    error_log("DB Error on authorize: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
}

// 6. Clean up session
unset($_SESSION['pending_order_data']);
error_log("Session data cleared");

// 7. Success response
ob_clean();
echo json_encode([
    'success' => true,
    'message' => 'Payment authorized successfully',
    'authorization_id' => $authorization_id,
    'order_id' => $orderID
]);
exit;