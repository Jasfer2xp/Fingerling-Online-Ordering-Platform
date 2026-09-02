<?php
/**
 * api/payments/capture_paypal.php
 * PayPal Immediate Capture (intent=capture) — FINAL & BULLETPROOF VERSION
 */

if (!defined('ACCESS_ALLOWED')) define('ACCESS_ALLOWED', true);

// Start output buffering early to prevent any output
ob_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../classes/Customer.php';
require_once '../../includes/session_guard.php';
require_once '../../includes/auto_payment_message.php';

// Include PayPal helper (for get_paypal_access_token)
require_once '../../includes/paypal_helpers.php';

// Clean any output and set headers
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log all requests for debugging
error_log("PAYPAL CAPTURE: Request received - Method: " . $_SERVER['REQUEST_METHOD'] . " | URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " | User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("PAYPAL CAPTURE: Invalid method - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

// Get input data
$raw_input = file_get_contents('php://input');
error_log("PAYPAL CAPTURE: Raw input received: " . substr($raw_input, 0, 500));

$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("PAYPAL CAPTURE: JSON decode error: " . json_last_error_msg() . " | Raw: " . substr($raw_input, 0, 200));
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    ob_end_flush();
    exit;
}

$paypal_order_id   = $input['orderID'] ?? $input['orderID'] ?? '';
$existing_order_id = !empty($input['order_id']) ? intval($input['order_id']) : 0;

error_log("PAYPAL CAPTURE: Parsed input - PayPal Order ID: {$paypal_order_id}, Existing Order ID: {$existing_order_id}");

// FIX: Get customer_id from user_id (same bug fix as Xendit)
// orders.customer_id stores customers.id, NOT users.id
$user_id = get_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$customer = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);

if (empty($paypal_order_id) || !$customer_id) {
    error_log("PAYPAL CAPTURE: Invalid request - PayPal Order ID: " . ($paypal_order_id ?: 'empty') . " | User ID: {$user_id} | Customer ID: " . ($customer_id ?: 'NOT FOUND'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request: Missing data or customer not found']);
    exit;
}

try {
    $database->beginTransaction();

    // Step 1: Verify PayPal payment is actually COMPLETED
    $token = get_paypal_access_token();
    if (!$token) {
        throw new Exception('Failed to get PayPal access token');
    }

    $url = (PAYPAL_MODE === 'live')
        ? "https://api.paypal.com/v2/checkout/orders/{$paypal_order_id}"
        : "https://api.sandbox.paypal.com/v2/checkout/orders/{$paypal_order_id}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("PayPal verify failed (HTTP $http_code): " . substr($response, 0, 500));
        throw new Exception('Failed to verify payment with PayPal');
    }

    $paypal_data = json_decode($response, true);
    $paypal_status = $paypal_data['status'] ?? 'unknown';
    
    // Check if order is already processed (idempotency check)
    if ($paypal_status === 'COMPLETED') {
        $existing_payment = $database->fetch(
            "SELECT id, order_id FROM payments WHERE transaction_id = ? OR payment_reference = ? LIMIT 1",
            [$paypal_order_id, $paypal_order_id]
        );
        
        if ($existing_payment && !empty($existing_payment['order_id'])) {
            // Payment already processed - return success with existing order
            $database->rollBack();
            error_log("PAYPAL CAPTURE: Payment already processed for PayPal Order: {$paypal_order_id} | Existing Order: {$existing_payment['order_id']}");
            ob_clean();
            echo json_encode([
                'success'    => true,
                'order_id'   => $existing_payment['order_id'],
                'message'    => 'Payment already processed',
                'already_processed' => true
            ]);
            ob_end_flush();
            exit;
        }
    }
    
    if ($paypal_status !== 'COMPLETED') {
        error_log("PAYPAL CAPTURE: Order status is '{$paypal_status}', expected 'COMPLETED'. Full response: " . substr(json_encode($paypal_data), 0, 1000));
        throw new Exception('PayPal order not completed. Status: ' . $paypal_status);
    }

    $capture = $paypal_data['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture) {
        error_log("PAYPAL CAPTURE: No capture found. Full response: " . substr(json_encode($paypal_data), 0, 1000));
        throw new Exception('No capture found in PayPal response');
    }

    $capture_id = $capture['id'];
    $paid_amount = floatval($capture['amount']['value']);

    // Step 2: Determine or create order
    if ($existing_order_id > 0) {
        // Existing confirmed order (user is paying now)
        // FIX: Use correct customer_id (customers.id, not users.id)
        $order_check = $database->fetch(
            "SELECT id, total_amount, status, payment_status FROM orders WHERE id = ? AND customer_id = ?",
            [$existing_order_id, $customer_id]
        );

        if (!$order_check) {
            error_log("PAYPAL CAPTURE: Order not found or access denied - Order ID: {$existing_order_id} | Customer ID: {$customer_id} | User ID: {$user_id}");
            throw new Exception('Order not found or access denied');
        }
        
        // Check if already paid (idempotency)
        if ($order_check['payment_status'] === 'paid' || $order_check['status'] === 'confirmed_and_paid') {
            // Check if payment record exists
            $existing_payment = $database->fetch(
                "SELECT id FROM payments WHERE order_id = ? AND transaction_id = ? LIMIT 1",
                [$existing_order_id, $capture_id]
            );
            
            if ($existing_payment) {
                // Already processed - return success
                $database->rollBack();
                error_log("PAYPAL CAPTURE: Order {$existing_order_id} already paid with capture {$capture_id}");
                ob_clean();
                echo json_encode([
                    'success'    => true,
                    'order_id'   => $existing_order_id,
                    'message'    => 'Payment already processed',
                    'already_processed' => true
                ]);
                ob_end_flush();
                exit;
            }
        }
        
        // Validate order is in a status that allows payment
        $allowed_statuses = ['confirmed', 'awaiting_customer_payment'];
        if (!in_array($order_check['status'], $allowed_statuses)) {
            error_log("PAYPAL CAPTURE: Order {$existing_order_id} is in status '{$order_check['status']}', which does not allow payment. Allowed: " . implode(', ', $allowed_statuses));
            throw new Exception('Order is not in a valid status for payment. Current status: ' . $order_check['status']);
        }
        
        // Log order validation success
        error_log("PAYPAL CAPTURE: Order validation passed - Order #{$existing_order_id} | Status: {$order_check['status']} | Payment Status: " . ($order_check['payment_status'] ?? 'NULL') . " | Customer ID: {$customer_id}");

        $final_order_id = $existing_order_id;
        $order_total    = floatval($order_check['total_amount']);

        // Update order status (match Xendit flow) - handle both payment_status column existing or not
        try {
            // Try with payment_status column first
            $database->query(
                "UPDATE orders SET 
                    status = 'confirmed_and_paid', 
                    payment_method = 'PayPal', 
                    payment_status = 'paid',
                    payment_date = NOW(),
                    payment_reference = ?,
                    updated_at = NOW() 
                WHERE id = ?",
                [$capture_id, $final_order_id]
            );
        } catch (Exception $e) {
            // If payment_status column doesn't exist, update without it
            error_log("PAYPAL CAPTURE: payment_status column may not exist, trying without it: " . $e->getMessage());
            $database->query(
                "UPDATE orders SET 
                    status = 'confirmed_and_paid', 
                    payment_method = 'PayPal', 
                    payment_date = NOW(),
                    payment_reference = ?,
                    updated_at = NOW() 
                WHERE id = ?",
                [$capture_id, $final_order_id]
            );
        }

    } else {
        // New order from cart
        $pending = $_SESSION['pending_order_payment'] ?? null;
        if (!$pending) {
            throw new Exception('No pending order in session');
        }

        $order = new Order($database);
        $order_data = [
            'customer_id'     => $customer_id,
            'total_amount'    => $pending['total_amount'],
            'subtotal'        => $pending['total_amount'] - ($pending['delivery_fee'] ?? 0),
            'delivery_fee'    => $pending['delivery_fee'] ?? 0,
            'payment_method'  => 'paypal',
            'delivery_address'=> $pending['delivery_address'] ?? '',
            'delivery_type'   => $pending['delivery_type'] ?? 'truck',
            'delivery_notes'  => $pending['delivery_notes'] ?? ''
        ];

        $order_ids = $order->createOrderFromCart($order_data, $pending['cart_items'] ?? []);
        if (!$order_ids || empty($order_ids)) {
            throw new Exception('Failed to create order from cart');
        }

        $final_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;
        $order_total    = $pending['total_amount'];

        // Update order status to confirmed_and_paid (match Xendit flow)
        $database->query(
            "UPDATE orders SET 
                status = 'confirmed_and_paid', 
                payment_status = 'paid',
                payment_date = NOW(),
                payment_reference = ?,
                updated_at = NOW() 
            WHERE id = ?",
            [$capture_id, $final_order_id]
        );

        unset($_SESSION['pending_order_payment']);
    }

    // Step 3: Record payment in `payments` table
    // Check if captured_at column exists (may not exist in all database schemas)
    try {
        $check_column = $database->fetch("SELECT COUNT(*) as col_exists FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'payments' 
            AND COLUMN_NAME = 'captured_at'");
        $has_captured_at = ($check_column && $check_column['col_exists'] > 0);
    } catch (Exception $e) {
        $has_captured_at = false;
    }
    
    if ($has_captured_at) {
        $database->query("
            INSERT INTO payments 
            (order_id, payment_method, amount, transaction_id, status, payment_date, captured_at, created_at) 
            VALUES 
            (?, 'PayPal', ?, ?, 'completed', NOW(), NOW(), NOW())
        ", [$final_order_id, $paid_amount, $capture_id]);
    } else {
        // Schema doesn't have captured_at column
        $database->query("
            INSERT INTO payments 
            (order_id, payment_method, amount, transaction_id, status, payment_date, created_at) 
            VALUES 
            (?, 'PayPal', ?, ?, 'completed', NOW(), NOW())
        ", [$final_order_id, $paid_amount, $capture_id]);
    }

    // Step 4: Clear customer's cart
    try {
        (new Cart($database, $customer_id))->clearCart();
    } catch (Exception $e) {
        error_log("Cart clear failed (non-critical): " . $e->getMessage());
    }

    // Step 5: COMMIT — THIS IS WHAT SAVES EVERYTHING!
    $database->commit();

    // Log success with detailed information
    error_log("PAYPAL SUCCESS → Order #$final_order_id | Amount: ₱$paid_amount | Capture ID: $capture_id | Customer ID: $customer_id | User ID: $user_id | PayPal Order: $paypal_order_id");
    
    // Log to customer interactions log (similar to Xendit)
    $logFile = __DIR__ . '/../../customer/interactions.log';
    if (is_writable(dirname($logFile))) {
        $logEntry = date('Y-m-d H:i:s') . " | CUSTOMER | PAYPAL_PAYMENT_SUCCESS | Order: {$final_order_id} | Amount: ₱{$paid_amount} | Customer: {$customer_id} | User: {$user_id}\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    // Step 6: Create notifications (match Xendit flow)
    try {
        $order_info = $database->fetch(
            "SELECT o.order_number, o.customer_id, o.supplier_id,
                    c.user_id AS customer_user_id,
                    s.user_id AS supplier_user_id,
                    s.business_name
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             JOIN suppliers s ON o.supplier_id = s.id
             WHERE o.id = ?",
            [$final_order_id]
        );
        
        if ($order_info) {
            // Use Manila timezone for timestamp
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            
            // Create notification for customer
            $notification_title = "Payment Successful";
            $notification_message = "Your payment for order #{$order_info['order_number']} from {$order_info['business_name']} has been processed successfully. Your order is now being prepared.";
            $notification_link = base_url("customer/order-details.php?id=" . $final_order_id);
            $database->query(
                "INSERT INTO notifications (user_id, customer_id, title, message, link, type, is_read, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'order', 0, ?)",
                [$order_info['customer_user_id'], $order_info['customer_id'], $notification_title, $notification_message, $notification_link, $manila_time]
            );
            
            // Create notification for supplier
            $notification_title_supplier = "Payment Received";
            $notification_message_supplier = "Payment has been received for order #{$order_info['order_number']}. Please proceed with order preparation.";
            $database->query(
                "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                 VALUES (?, ?, ?, 'order', 0, ?)",
                [$order_info['supplier_user_id'], $notification_title_supplier, $notification_message_supplier, $manila_time]
            );
        }
    } catch (Exception $e) {
        error_log("PayPal notification creation failed (non-critical): " . $e->getMessage());
    }

    // Step 7: Send auto payment message (match Xendit flow)
    send_auto_payment_message($database, $final_order_id);
    $_SESSION['paypal_payment_order_id'] = $final_order_id;

    // Ensure clean output
    ob_clean();
    echo json_encode([
        'success'    => true,
        'order_id'   => $final_order_id,
        'message'    => 'Payment successful and recorded!'
    ]);
    ob_end_flush();
    exit;

} catch (Exception $e) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    // Enhanced error logging (similar to Xendit)
    $error_details = [
        'Customer ID' => $customer_id ?? 'NOT SET',
        'User ID' => $user_id ?? 'NOT SET',
        'PayPal Order ID' => $paypal_order_id ?? 'NOT SET',
        'Existing Order ID' => $existing_order_id ?? 'NOT SET',
        'Error' => $e->getMessage(),
        'File' => $e->getFile(),
        'Line' => $e->getLine()
    ];
    error_log("PAYPAL CAPTURE FAILED → " . json_encode($error_details));
    
    // Log to customer interactions log
    $logFile = __DIR__ . '/../../customer/interactions.log';
    if (is_writable(dirname($logFile))) {
        $logEntry = date('Y-m-d H:i:s') . " | CUSTOMER | PAYPAL_PAYMENT_FAILED | Order: " . ($existing_order_id ?: 'NEW') . " | Error: " . $e->getMessage() . " | Customer: " . ($customer_id ?? 'NOT FOUND') . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    // Return more detailed error for debugging (but don't expose sensitive info)
    $error_message = 'Payment processing failed. Please try again or contact support.';
    // Only show detailed errors in development (check error_reporting level)
    if (error_reporting() & E_ALL && ini_get('display_errors')) {
        $error_message .= ' Error: ' . $e->getMessage();
    }
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'error_code' => 'CAPTURE_FAILED'
    ]);
    ob_end_flush();
}

exit;