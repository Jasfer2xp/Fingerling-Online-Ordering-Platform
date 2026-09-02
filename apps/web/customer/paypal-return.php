<?php
/**
 * customer/paypal-return.php
 * PayPal Return URL Handler - Processes payment when PayPal redirects back
 * Mirrors Xendit webhook/return flow
 */

require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';
require_once '../includes/paypal_helpers.php';
require_once '../includes/auto_payment_message.php';

// Strict error logging
$log = function($msg) {
    error_log("[PAYPAL RETURN] " . date('Y-m-d H:i:s') . " | UID:" . (get_user_id() ?? '?') . " | " . $msg);
};

$log("=== PAYPAL RETURN HANDLER STARTED ===");

if (!is_logged_in() || get_user_type() !== 'customer') {
    $log("AUTH FAILED: Not logged in or not customer");
    $_SESSION['error'] = 'Please log in to continue.';
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$customer = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);

if (!$customer_id) {
    $log("CUSTOMER NOT FOUND: User ID: {$user_id}");
    $_SESSION['error'] = 'Customer account not found.';
    redirect(base_url('customer/orders.php'));
}

// Get order_id and PayPal token from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$token = $_GET['token'] ?? '';
$payer_id = $_GET['PayerID'] ?? '';

$log("Return parameters - Order ID: {$order_id}, Token: " . substr($token, 0, 20) . "...");

if ($order_id <= 0) {
    $log("INVALID ORDER ID");
    $_SESSION['error'] = 'Invalid order.';
    redirect(base_url('customer/orders.php'));
}

// Verify order belongs to customer
$order_check = $database->fetch(
    "SELECT id, status, total_amount FROM orders WHERE id = ? AND customer_id = ?",
    [$order_id, $customer_id]
);

if (!$order_check) {
    $log("ORDER NOT FOUND OR ACCESS DENIED - Order: {$order_id}, Customer: {$customer_id}");
    $_SESSION['error'] = 'Order not found or access denied.';
    redirect(base_url('customer/orders.php'));
}

// Get PayPal order ID from session or database
$paypal_order_id = $_SESSION['last_paypal_order']['paypal_order_id'] ?? null;

if (!$paypal_order_id) {
    // Try to find it in database (if table exists)
    try {
        $paypal_order_record = $database->fetch(
            "SELECT paypal_order_id FROM paypal_orders WHERE order_id = ? ORDER BY created_at DESC LIMIT 1",
            [$order_id]
        );
        $paypal_order_id = $paypal_order_record['paypal_order_id'] ?? null;
    } catch (Exception $e) {
        // Table might not exist - that's OK
        $log("paypal_orders table lookup failed (non-critical): " . $e->getMessage());
    }
}

if (!$paypal_order_id) {
    $log("PAYPAL ORDER ID NOT FOUND for order {$order_id}");
    $_SESSION['error'] = 'Payment session expired. Please try again.';
    redirect(base_url('customer/orders.php'));
}

$log("Processing PayPal order: {$paypal_order_id} for order: {$order_id}");

// === Capture PayPal Payment ===
try {
    $token = get_paypal_access_token();
    if (!$token) {
        throw new Exception('Failed to get PayPal access token');
    }

    // Get order details from PayPal
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
        error_log("PayPal order fetch failed (HTTP $http_code): " . substr($response, 0, 500));
        throw new Exception('Failed to verify payment with PayPal');
    }

    $paypal_data = json_decode($response, true);
    $paypal_status = $paypal_data['status'] ?? 'unknown';
    
    $log("PayPal order status: {$paypal_status}");

    $capture_data = null;
    $capture_performed = false;

    // If order is not COMPLETED, try to capture it
    if ($paypal_status === 'APPROVED') {
        // Capture the payment
        $capture_url = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/checkout/orders/{$paypal_order_id}/capture"
            : "https://api.sandbox.paypal.com/v2/checkout/orders/{$paypal_order_id}/capture";

        $ch = curl_init($capture_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $capture_response = curl_exec($ch);
        $capture_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($capture_http_code !== 201) {
            error_log("PayPal capture failed (HTTP $capture_http_code): " . substr($capture_response, 0, 500));
            throw new Exception('Failed to capture payment');
        }

        $capture_data = json_decode($capture_response, true);
        $paypal_status = $capture_data['status'] ?? 'unknown';
        $capture_performed = true;
        $log("PayPal capture response status: {$paypal_status}");
    }

    if ($paypal_status !== 'COMPLETED') {
        throw new Exception('PayPal order not completed. Status: ' . $paypal_status);
    }

    // Get capture details - use capture response if we just captured, otherwise use original order data
    $capture = null;
    if ($capture_performed && isset($capture_data['purchase_units'][0]['payments']['captures'][0])) {
        // Use capture response data
        $capture = $capture_data['purchase_units'][0]['payments']['captures'][0];
        $log("Using capture details from capture response");
    } elseif (isset($paypal_data['purchase_units'][0]['payments']['captures'][0])) {
        // Use original order data (order was already completed)
        $capture = $paypal_data['purchase_units'][0]['payments']['captures'][0];
        $log("Using capture details from original order data");
    } else {
        throw new Exception('No capture found in PayPal response');
    }

    $capture_id = $capture['id'];
    $paid_amount = floatval($capture['amount']['value']);
    $paypal_fee_amount = floatval($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0);

    $log("Payment captured - Capture ID: {$capture_id}, Amount: ₱{$paid_amount}, Fee: ₱{$paypal_fee_amount}");

    // === Process Payment (Mirror Xendit flow) ===
    $order = new Order($database);
    $order_data = $order->getOrderById($order_id);

    if (!$order_data) {
        throw new Exception('Order not found');
    }
    
    // Check if already paid (idempotency - same as Xendit)
    if ($order_data['status'] === 'confirmed_and_paid' || $order_data['payment_status'] === 'paid') {
        $log("Order {$order_id} already paid - redirecting to success");
        redirect(base_url('customer/payment-successful.php?order_id=' . $order_id));
    }
    
    // Allow processing if order is confirmed or awaiting payment (same as Xendit)
    if (!in_array($order_data['status'], ['confirmed', 'awaiting_customer_payment'])) {
        throw new Exception('Order not in valid status for payment. Current status: ' . $order_data['status']);
    }

    // Calculate expected total with PayPal fee
    // PayPal fee: 4.37% of subtotal + ₱15.00
    $subtotal = $order_data['total_amount'] - ($order_data['payment_fee'] ?? 0);
    $paypal_fee = round(($subtotal * 0.0437) + 15.00, 2);
    $expected_total_with_fee = round($subtotal + $paypal_fee, 2);

    $log("Amount validation - Order Total: ₱{$order_data['total_amount']}, Subtotal: ₱{$subtotal}, PayPal Fee: ₱{$paypal_fee}, Expected Total (with fee): ₱{$expected_total_with_fee}, Received: ₱{$paid_amount}");

    // Validate amount matches expected total (with fee) - allow small tolerance for rounding
    $difference = abs($expected_total_with_fee - $paid_amount);
    $tolerance = 0.10; // Allow 10 cent difference for rounding differences
    
    if ($difference > $tolerance) {
        $log("PAYMENT AMOUNT MISMATCH - Expected: ₱{$expected_total_with_fee}, Received: ₱{$paid_amount}, Difference: ₱{$difference}");
        throw new Exception("Payment amount mismatch. Expected: " . number_format($expected_total_with_fee, 2) . ", Received: " . number_format($paid_amount, 2) . ". Please contact support if payment was deducted.");
    }

    // Update order total_amount to include PayPal fee (this is what customer actually paid)
    // This ensures validatePaymentAmount() passes and the order reflects the actual payment
    $original_total = $order_data['total_amount'];
    try {
        $database->query(
            "UPDATE orders SET total_amount = ?, payment_fee = ? WHERE id = ?",
            [$expected_total_with_fee, $paypal_fee, $order_id]
        );
        $log("Updated order total_amount to include PayPal fee: ₱{$expected_total_with_fee} (fee: ₱{$paypal_fee})");
    } catch (Exception $e) {
        error_log("Failed to update order total_amount: " . $e->getMessage());
        // Continue anyway - we'll handle validation manually
    }

    // Use processPaymentSuccess (same as Xendit)
    // Pass the expected total with fee so validation passes
    $payment_data = [
        'amount' => $expected_total_with_fee, // Total with fee (matches what PayPal charged and order total_amount now)
        'transaction_id' => $capture_id,
        'payment_method' => 'paypal', // Use lowercase to match database enum
        'payer_email' => $paypal_data['payer']['email_address'] ?? null,
        'currency' => 'PHP'
    ];

    try {
        $order->processPaymentSuccess($order_id, $payment_data);
    } catch (Exception $e) {
        // Revert order total_amount if payment processing fails
        try {
            $database->query("UPDATE orders SET total_amount = ? WHERE id = ?", [$original_total, $order_id]);
            $log("Reverted order total_amount due to payment processing failure");
        } catch (Exception $revert_error) {
            error_log("Failed to revert order total_amount: " . $revert_error->getMessage());
        }
        throw $e;
    }
    
    // Payment record is created by processPaymentSuccess with the correct amount
    $log("Payment processed successfully - Order total updated to ₱{$expected_total_with_fee} (includes ₱{$paypal_fee} PayPal fee)");

    // Update order with PayPal-specific fields
    try {
        $database->query(
            "UPDATE orders SET 
                payment_method = 'paypal',
                payment_reference = ?,
                updated_at = NOW()
            WHERE id = ?",
            [$capture_id, $order_id]
        );
    } catch (Exception $e) {
        // Ignore if columns don't exist
        error_log("Failed to update PayPal fields (non-critical): " . $e->getMessage());
    }

    // Update payment record with fees
    try {
        $database->query(
            "UPDATE payments SET 
                transaction_id = ?,
                payer_email = ?,
                updated_at = NOW()
            WHERE order_id = ?",
            [$capture_id, $payment_data['payer_email'], $order_id]
        );
    } catch (Exception $e) {
        error_log("Failed to update payment record (non-critical): " . $e->getMessage());
    }

    // Update PayPal order status (if table exists)
    try {
        $database->query(
            "UPDATE paypal_orders SET 
                status = 'COMPLETED',
                updated_at = NOW()
            WHERE paypal_order_id = ?",
            [$paypal_order_id]
        );
    } catch (Exception $e) {
        // Table might not exist - non-critical
        $log("Failed to update paypal_orders (non-critical): " . $e->getMessage());
    }

    // Send auto payment message (same as Xendit)
    send_auto_payment_message($database, $order_id);

    // Clear cart
    try {
        require_once '../classes/Cart.php';
        $cart = new Cart($database, $customer_id);
        $cart->clearCart();
    } catch (Exception $e) {
        error_log("Cart clear failed (non-critical): " . $e->getMessage());
    }

    // Clear session
    unset($_SESSION['last_paypal_order']);

    $log("PAYPAL PAYMENT SUCCESS → Order #{$order_id} | Amount: ₱{$paid_amount} | Capture ID: {$capture_id}");

    // Redirect to success page (same as Xendit)
    redirect(base_url('customer/payment-successful.php?order_id=' . $order_id));

} catch (Exception $e) {
    $log("PAYPAL RETURN ERROR: " . $e->getMessage());
    error_log("PAYPAL RETURN ERROR: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
    
    $_SESSION['error'] = 'Payment processing failed: ' . $e->getMessage() . '. Please contact support if payment was deducted.';
    redirect(base_url('customer/orders.php'));
}

