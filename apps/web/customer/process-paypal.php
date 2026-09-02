<?php
/**
 * customer/process-paypal.php
 * PayPal Payment Processing - Mirrors process-xendit.php flow
 * Creates PayPal order and redirects to PayPal payment page
 */

ob_start();

require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';
require_once '../includes/paypal_helpers.php';

// Verify PayPal credentials are loaded
if (!defined('PAYPAL_CLIENT_ID') || !defined('PAYPAL_CLIENT_SECRET')) {
    error_log("PAYPAL ERROR: PayPal credentials not defined in config.php");
    $_SESSION['error'] = 'PayPal configuration error. Please contact support.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

if (empty(PAYPAL_CLIENT_ID) || empty(PAYPAL_CLIENT_SECRET)) {
    error_log("PAYPAL ERROR: PayPal credentials are empty. CLIENT_ID: " . (defined('PAYPAL_CLIENT_ID') ? 'SET' : 'NOT SET') . ", CLIENT_SECRET: " . (defined('PAYPAL_CLIENT_SECRET') ? 'SET' : 'NOT SET'));
    $_SESSION['error'] = 'PayPal credentials not configured. Please contact support.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

// Strict error logging
$log = function($msg) {
    error_log("[PAYPAL PROCESS] " . date('Y-m-d H:i:s') . " | UID:" . (get_user_id() ?? '?') . " | " . $msg);
};

$log("=== PROCESS-PAYPAL STARTED ===");

// Log initial request details
$log("Request Method: " . $_SERVER['REQUEST_METHOD']);
$log("POST action: " . ($_POST['action'] ?? 'NOT SET'));
$log("POST order_id: " . ($_POST['order_id'] ?? 'NOT SET'));
$log("User ID: " . (get_user_id() ?? 'NOT SET'));
$log("User Type: " . (get_user_type() ?? 'NOT SET'));

// === 1. Security & Request Validation ===
if (!is_logged_in() || get_user_type() !== 'customer') {
    $log("AUTH FAILED: Not logged in or not customer");
    $_SESSION['error'] = 'Please log in as a customer to continue.';
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'process_paypal') {
    $log("VALIDATION FAILED: Method=" . $_SERVER['REQUEST_METHOD'] . ", Action=" . ($_POST['action'] ?? 'NOT SET'));
    $_SESSION['error'] = 'Invalid payment request.';
    header('Location: ' . base_url('customer/cart.php'));
    exit;
}

$log("Validation passed - proceeding with payment processing");

// === 2. Critical: Rebuild session if missing ===
if (!isset($_SESSION['pending_order_payment'])) {
    $log("SESSION MISSING — attempting recovery from order_id in POST");
    
    $order_id = intval($_POST['order_id'] ?? 0);
    $log("Recovery: order_id from POST = " . $order_id);
    
    if ($order_id <= 0) {
        $log("Recovery FAILED: Invalid order_id");
        $_SESSION['error'] = 'Payment session expired. Please try again.';
        header('Location: ' . base_url('customer/orders.php'));
        exit;
    }

    $order = new Order($database);
    $order_data = $order->getOrderById($order_id);
    
    // Get customer_id from user_id
    $customer = new Customer($database);
    $current_customer_id = $customer->getCustomerIdByUserId(get_user_id());
    
    $log("Recovery: Order data found = " . ($order_data ? 'YES' : 'NO'));
    if ($order_data) {
        $log("Recovery: Order status = " . ($order_data['status'] ?? 'NOT SET'));
        $log("Recovery: Order customer_id = " . ($order_data['customer_id'] ?? 'NOT SET'));
        $log("Recovery: Current customer_id = " . ($current_customer_id ?? 'NOT FOUND'));
    }
    
    if (!$order_data || !in_array($order_data['status'], ['confirmed', 'awaiting_customer_payment']) || $order_data['customer_id'] != $current_customer_id) {
        $log("Recovery FAILED: Order #$order_id | Status: " . ($order_data['status'] ?? 'NOT FOUND') . " | Order Customer ID: " . ($order_data['customer_id'] ?? 'NOT FOUND') . " | Current Customer ID: " . ($current_customer_id ?? 'NOT FOUND'));
        $_SESSION['error'] = 'Order not ready for payment or access denied.';
        header('Location: ' . base_url('customer/orders.php'));
        exit;
    }

    // Recalculate total with PayPal fee (4.37% + ₱15)
    $subtotal = $order_data['total_amount'] - ($order_data['payment_fee'] ?? 0);
    $paypal_fee = round(($subtotal * 0.0437) + 15.00, 2);
    $total = round($subtotal + $paypal_fee, 2);

    $_SESSION['pending_order_payment'] = [
        'order_id'       => $order_id,
        'subtotal'       => $subtotal,
        'payment_fee'    => $paypal_fee,
        'total_amount'   => $total,
        'order_number'   => $order_data['order_number'],
        'business_name'  => $order_data['business_name'] ?? 'Supplier'
    ];

    $log("Session successfully recovered for Order #$order_id | Total: ₱" . number_format($total, 2));
}

$p = $_SESSION['pending_order_payment'];

$order_id      = (int)($p['order_id'] ?? 0);
$total_amount  = round((float)($p['total_amount'] ?? 0), 2);
$order_number  = $p['order_number'] ?? 'Unknown';
$business_name = $p['business_name'] ?? 'Supplier';

$log("Processing payment → Order #$order_id | Amount: ₱" . number_format($total_amount, 2));

// === FINAL VALIDATION ===
if ($order_id <= 0 || $total_amount <= 0) {
    $log("BLOCKED: Invalid or zero amount (₱" . number_format($total_amount, 2) . ")");
    unset($_SESSION['pending_order_payment']);
    $_SESSION['error'] = 'Invalid payment amount. Please contact support.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

// PayPal minimum amount validation (PHP 1.00 minimum)
$paypal_minimum = 1.00;
if ($total_amount < $paypal_minimum) {
    $log("BLOCKED: Amount below PayPal minimum (₱" . number_format($total_amount, 2) . " < ₱" . number_format($paypal_minimum, 2) . ")");
    unset($_SESSION['pending_order_payment']);
    $_SESSION['error'] = 'Payment amount (₱' . number_format($total_amount, 2) . ') is below PayPal\'s minimum of ₱' . number_format($paypal_minimum, 2) . '. Please add more items to your order.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

// === 3. Customer Info ===
$user_id = get_user_id();
$profile = (new User($database))->getUserProfile($user_id);

$email      = $profile['email'] ?? '';
$first_name = trim($profile['first_name'] ?? '');
$last_name  = trim($profile['last_name'] ?? '');

if (empty($email) || empty($first_name)) {
    $log("Profile incomplete: missing email or name");
    $_SESSION['error'] = 'Please complete your profile before making a payment.';
    header('Location: ' . base_url('customer/profile.php'));
    exit;
}

// === 4. Create PayPal Order ===
$description = "Payment for Order #$order_number - $business_name";
$amount_pesos = number_format($total_amount, 2, '.', '');

$log("Creating PayPal order → ₱" . number_format($amount_pesos, 2) . " | Customer: $first_name $last_name ($email)");

// Get PayPal access token
$token = get_paypal_access_token();
if (!$token) {
    $log("PAYPAL TOKEN FAILED - Check PayPal credentials and API endpoint");
    error_log("PAYPAL ERROR: Failed to get access token. Check PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, and PAYPAL_MODE in config.php");
    $_SESSION['error'] = 'Payment gateway is temporarily unavailable. Please check PayPal configuration or try again in a few minutes.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

// Create PayPal order
$endpoint = (PAYPAL_MODE === 'live')
    ? 'https://api.paypal.com/v2/checkout/orders'
    : 'https://api.sandbox.paypal.com/v2/checkout/orders';

// Ensure amount meets PayPal minimum (PHP 1.00)
$amount_pesos = max($amount_pesos, '1.00');

$payload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'amount' => [
            'currency_code' => 'PHP',
            'value' => $amount_pesos
        ],
        'description' => $description,
        'invoice_id' => $order_number,
        'custom_id' => $order_id . '|' . $user_id
    ]],
    'application_context' => [
        'brand_name' => APP_NAME,
        'landing_page' => 'NO_PREFERENCE',
        'user_action' => 'PAY_NOW',
        'return_url' => base_url('customer/paypal-return.php?order_id=' . $order_id),
        'cancel_url' => base_url('customer/checkout.php?cancel=1')
    ]
];

$log("PayPal Order Payload: Amount=₱{$amount_pesos}, Currency=PHP, Order#={$order_number}");

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'PayPal-Request-Id: ' . uniqid('paypal_', true)
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 201) {
    $error_details = $curl_error ?: substr($response, 0, 500);
    $log("PAYPAL ORDER CREATION FAILED (HTTP $http_code): " . $error_details);
    error_log("PAYPAL ORDER ERROR: HTTP $http_code | Error: " . $error_details);
    
    // Parse PayPal error response
    $error_message = 'Payment gateway is temporarily unavailable. Please try again in a few minutes.';
    $error_issue = '';
    $error_description = '';
    
    if ($response) {
        $error_data = json_decode($response, true);
        
        // Get main error message
        if (isset($error_data['message'])) {
            $error_message = $error_data['message'];
        }
        
        // Get specific issue and description from details array
        if (isset($error_data['details']) && is_array($error_data['details']) && !empty($error_data['details'])) {
            $first_detail = $error_data['details'][0];
            $error_issue = $first_detail['issue'] ?? '';
            $error_description = $first_detail['description'] ?? '';
            
            // Handle specific PayPal errors
            if ($error_issue === 'PAYEE_ACCOUNT_RESTRICTED') {
                $error_message = 'PayPal Account Restricted: Your PayPal merchant account is restricted and cannot receive payments.';
                $error_message .= ' Please contact PayPal support to resolve this issue or use a different payment method.';
                $error_message .= ' Error: ' . $error_description;
            } elseif ($error_issue === 'INSTRUMENT_DECLINED') {
                $error_message = 'Payment Declined: The payment method was declined by PayPal.';
                $error_message .= ' Please try a different payment method or contact your bank.';
            } elseif ($error_issue === 'PAYER_ACCOUNT_RESTRICTED') {
                $error_message = 'Customer Account Restricted: The customer\'s PayPal account is restricted.';
                $error_message .= ' Please use a different payment method.';
            } elseif ($error_issue === 'INSUFFICIENT_FUNDS') {
                $error_message = 'Insufficient Funds: The payment method does not have sufficient funds.';
            } elseif ($error_description) {
                $error_message = 'PayPal Error: ' . $error_description;
            }
        }
        
        // Log debug ID for support
        if (isset($error_data['debug_id'])) {
            $log("PayPal Debug ID: " . $error_data['debug_id']);
            error_log("PayPal Debug ID: " . $error_data['debug_id']);
        }
    }
    
    $_SESSION['error'] = $error_message;
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

$paypal_order = json_decode($response, true);
if (empty($paypal_order['id']) || empty($paypal_order['links'])) {
    $log("PAYPAL ORDER CREATION FAILED: Invalid response - " . substr($response, 0, 500));
    $_SESSION['error'] = 'Payment gateway error. Please try again.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

$paypal_order_id = $paypal_order['id'];
$approve_url = null;

foreach ($paypal_order['links'] as $link) {
    if ($link['rel'] === 'approve') {
        $approve_url = $link['href'];
        break;
    }
}

if (!$approve_url) {
    $log("PAYPAL ORDER CREATION FAILED: No approve URL found");
    $_SESSION['error'] = 'Payment gateway error. Please try again.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

$log("PAYPAL ORDER CREATED SUCCESSFULLY → ID: {$paypal_order_id} | Approve URL: {$approve_url}");

// === 5. Save PayPal Order Record ===
try {
    $customer_id = (new Customer($database))->getCustomerIdByUserId($user_id);

    // Store PayPal order info (use session, similar to Xendit)
    // Optionally save to database if paypal_orders table exists
    try {
        // Try to insert into paypal_orders if table exists
        $database->query("
            INSERT INTO paypal_orders 
            (paypal_order_id, order_id, customer_id, amount, status, created_at)
            VALUES (?, ?, ?, ?, 'CREATED', NOW())
            ON DUPLICATE KEY UPDATE 
                amount = VALUES(amount),
                status = VALUES(status),
                updated_at = NOW()
        ", [
            $paypal_order_id,
            $order_id,
            $customer_id,
            $amount_pesos
        ]);
    } catch (Exception $e) {
        // Table might not exist - that's OK, we use session instead
        $log("paypal_orders table insert failed (non-critical): " . $e->getMessage());
        // Continue - session will be used as fallback
    }

    // Store for return URL use (CRITICAL - must be set even if DB insert fails)
    $_SESSION['last_paypal_order'] = [
        'paypal_order_id' => $paypal_order_id,
        'order_id'   => $order_id,
        'amount'     => $amount_pesos,
        'order_number' => $order_number
    ];

    $log("PayPal order record saved (DB or session)");

} catch (Exception $e) {
    $log("DB save failed (non-critical): " . $e->getMessage());
    // Still store in session as fallback
    if (isset($paypal_order_id)) {
        $_SESSION['last_paypal_order'] = [
            'paypal_order_id' => $paypal_order_id,
            'order_id'   => $order_id,
            'amount'     => $amount_pesos,
            'order_number' => $order_number
        ];
    }
    // Do NOT block payment — PayPal order was already created successfully
}

// === 6. CLEAN UP & REDIRECT TO PAYPAL ===
unset($_SESSION['pending_order_payment']);

$log("Redirecting user to PayPal payment page: {$approve_url}");

// Clean output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Redirect to PayPal
header('Location: ' . $approve_url);
exit;

