<?php
ob_start();
session_write_close(); // Prevent session lock issues during redirect

require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';
require_once '../includes/xendit_helper.php';

// Strict error logging
$log = function($msg) {
    error_log("[XENDIT PROCESS] " . date('Y-m-d H:i:s') . " | UID:" . (get_user_id() ?? '?') . " | " . $msg);
};

$log("=== PROCESS-XENDIT STARTED ===");

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'process_xendit') {
    $log("VALIDATION FAILED: Method=" . $_SERVER['REQUEST_METHOD'] . ", Action=" . ($_POST['action'] ?? 'NOT SET'));
    $_SESSION['error'] = 'Invalid payment request.';
    header('Location: ' . base_url('customer/cart.php'));
    exit;
}

$log("Validation passed - proceeding with payment processing");

// === 2. Critical: Rebuild session if missing (recovery from xendit-checkout.php) ===
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
    
    // Get customer_id from user_id (orders.customer_id is the customers.id, not users.id)
    $customer = new Customer($database);
    $current_customer_id = $customer->getCustomerIdByUserId(get_user_id());
    
    $log("Recovery: Order data found = " . ($order_data ? 'YES' : 'NO'));
    if ($order_data) {
        $log("Recovery: Order status = " . ($order_data['status'] ?? 'NOT SET'));
        $log("Recovery: Order customer_id = " . ($order_data['customer_id'] ?? 'NOT SET'));
        $log("Recovery: Current user_id = " . get_user_id());
        $log("Recovery: Current customer_id = " . ($current_customer_id ?? 'NOT FOUND'));
        $log("Recovery: Status check = " . ($order_data['status'] === 'confirmed' ? 'PASS' : 'FAIL'));
        $log("Recovery: Customer check = " . ($order_data['customer_id'] == $current_customer_id ? 'PASS' : 'FAIL'));
    }
    
    if (!$order_data || $order_data['status'] !== 'confirmed' || $order_data['customer_id'] != $current_customer_id) {
        $log("Recovery FAILED: Order #$order_id | Status: " . ($order_data['status'] ?? 'NOT FOUND') . " | Order Customer ID: " . ($order_data['customer_id'] ?? 'NOT FOUND') . " | Current Customer ID: " . ($current_customer_id ?? 'NOT FOUND') . " | User ID: " . get_user_id());
        $_SESSION['error'] = 'Order not ready for payment or access denied.';
        header('Location: ' . base_url('customer/orders.php'));
        exit;
    }

    // Recalculate total with Xendit fee (2.576%)
    $subtotal = $order_data['total_amount'] - ($order_data['payment_fee'] ?? 0);
    $payment_fee = round($subtotal * 0.02576, 2);
    $total = round($subtotal + $payment_fee, 2);

    $_SESSION['pending_order_payment'] = [
        'order_id'       => $order_id,
        'subtotal'       => $subtotal,
        'payment_fee'    => $payment_fee,
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
$log("Session data: order_id={$order_id}, total_amount={$total_amount}, order_number={$order_number}");

// === FINAL VALIDATION: Only block if amount is zero or negative ===
if ($order_id <= 0 || $total_amount <= 0) {
    $log("BLOCKED: Invalid or zero amount (₱" . number_format($total_amount, 2) . ")");
    unset($_SESSION['pending_order_payment']);
    $_SESSION['error'] = 'Invalid payment amount. Please contact support.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

// Optional: Log low-value transactions (useful for monitoring)
if ($total_amount < 100) {
    $log("LOW-VALUE PAYMENT ALLOWED: ₱" . number_format($total_amount, 2) . " (this is now permitted)");
}

// === 3. Customer Info ===
$user_id = get_user_id();
$profile = (new User($database))->getUserProfile($user_id);

$email      = $profile['email'] ?? '';
$first_name = trim($profile['first_name'] ?? '');
$last_name  = trim($profile['last_name'] ?? '');
$mobile     = trim($profile['contact_number'] ?? $profile['phone'] ?? '');

if (empty($email) || empty($first_name)) {
    $log("Profile incomplete: missing email or name");
    $_SESSION['error'] = 'Please complete your profile before making a payment.';
    header('Location: ' . base_url('customer/profile.php'));
    exit;
}

// === 4. Create Xendit Invoice (AMOUNT IN PHP PESOS) ===
$description = "Payment for Order #$order_number - $business_name";

// Amount is already in PHP pesos (e.g., 125.50)
$amount_in_php = $total_amount;

$log("Creating Xendit invoice → ₱" . number_format($amount_in_php, 2) . " | Customer: $first_name $last_name ($email)");

$invoice = create_xendit_invoice($amount_in_php, $description, [
    'payer_email' => $email,
    'customer' => [
        'given_names'   => $first_name,
        'surname'       => $last_name,
        'email'         => $email,
        'mobile_number' => !empty($mobile) ? $mobile : null
    ]
]);

if (!$invoice || empty($invoice['invoice_url'])) {
    $log("XENDIT INVOICE CREATION FAILED: " . json_encode($invoice ?? 'null'));
    $_SESSION['error'] = 'Payment gateway is temporarily unavailable. Please try again in a few minutes.';
    header('Location: ' . base_url('customer/orders.php'));
    exit;
}

$log("XENDIT INVOICE CREATED SUCCESSFULLY → ID: {$invoice['id']} | URL: {$invoice['invoice_url']}");

// === 5. Save Invoice Record (non-blocking, safe) ===
try {
    $customer_id = (new Customer($database))->getCustomerIdByUserId($user_id);

    try { $database->query("DELETE FROM xendit_invoices WHERE invoice_id = ?", [$invoice['id']]); } catch (Exception $e) {}
    $database->query("
        INSERT INTO xendit_invoices 
        (invoice_id, external_id, order_id, customer_id, amount, invoice_url, xendit_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ", [
        $invoice['id'],
        $invoice['external_id'] ?? $invoice['id'],
        $order_id,
        $customer_id,
        $amount_in_php,
        $invoice['invoice_url'],
        $invoice['status'] ?? 'PENDING'
    ]);

    // Store for potential success page use
    $_SESSION['last_xendit_invoice'] = [
        'invoice_id' => $invoice['id'],
        'order_id'   => $order_id,
        'amount'     => $amount_in_php,
        'order_number' => $order_number
    ];

    $log("Invoice record saved to database");

} catch (Exception $e) {
    $log("DB save failed (non-critical): " . $e->getMessage());
    // Do NOT block payment — invoice was already created successfully
}

// === 6. CLEAN UP & REDIRECT TO XENDIT ===
unset($_SESSION['pending_order_payment']);
ob_end_clean();

$log("Redirecting user to Xendit payment page...");
header('Location: ' . $invoice['invoice_url']);
exit;