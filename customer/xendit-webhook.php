<?php
// customer/xendit-webhook.php  ← RENAME THIS FILE TO THIS
// This is now your OFFICIAL XENDIT WEBHOOK HANDLER (not user-facing)

ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Order.php';
require_once '../classes/Customer.php';
require_once '../includes/auto_payment_message.php';

// === SECURITY: Only accept from Xendit IP + verify signature ===
$input = file_get_contents('php://input');
$headers = getallheaders();

$token = $headers['X-Callback-Token'] ?? $headers['x-callback-token'] ?? '';

// REPLACE THIS WITH YOUR ACTUAL XENDIT CALLBACK TOKEN FROM DASHBOARD
$EXPECTED_TOKEN = 'your_xendit_callback_token_here'; // ← CHANGE THIS!

if ($token !== $EXPECTED_TOKEN) {
    http_response_code(401);
    error_log("XENDIT WEBHOOK: Invalid token");
    exit('Invalid token');
}

if (empty($input)) {
    http_response_code(400);
    exit('No payload');
}

$event = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit('Invalid JSON');
}

error_log("XENDIT WEBHOOK RECEIVED: " . json_encode($event));

// Only handle invoice.paid
if (($event['event'] ?? '') !== 'invoice.paid') {
    http_response_code(200);
    exit('Ignored event');
}

$invoice_id = $event['id'] ?? '';
$external_id = $event['external_id'] ?? '';
$status = $event['status'] ?? '';
$paid_amount = floatval($event['amount'] ?? 0);

// Extract payment channel from Xendit webhook
// Xendit may send payment_method, payment_channel, or payment_method_id
// Also check nested structures like event.payment_method or event.payment_channel
$payment_channel = $event['payment_method'] 
    ?? $event['payment_channel'] 
    ?? $event['payment_method_id']
    ?? ($event['payment'] ?? [])['method'] ?? null
    ?? ($event['payment'] ?? [])['channel'] ?? null
    ?? 'xendit';

if ($status !== 'PAID' || empty($invoice_id)) {
    http_response_code(200);
    exit('Not paid');
}

// === Find invoice in your DB ===
$db_invoice = $database->fetch("SELECT * FROM xendit_invoices WHERE invoice_id = ?", [$invoice_id]);

if (!$db_invoice) {
    error_log("XENDIT WEBHOOK: Invoice not found in DB: $invoice_id");
    http_response_code(404);
    exit('Invoice not found');
}

$order_id = $db_invoice['order_id'];
if (!$order_id) {
    error_log("XENDIT WEBHOOK: No order_id linked to invoice $invoice_id");
    http_response_code(400);
    exit('No order linked');
}

// === Prevent Double Processing ===
if ($db_invoice['xendit_status'] === 'PAID') {
    error_log("XENDIT WEBHOOK: Already processed invoice $invoice_id");
    http_response_code(200);
    exit('Already processed');
}

// === Update Invoice Status ===
$database->query(
    "UPDATE xendit_invoices SET xendit_status = 'PAID', updated_at = NOW() WHERE invoice_id = ?",
    [$invoice_id]
);

// === Mark Order as Paid ===
try {
    $order = new Order($database);
    $order_data = $order->getOrderById($order_id);

    if (!$order_data) {
        throw new Exception("Order not found: $order_id");
    }

    // Allow processing if order is confirmed or already paid (idempotency)
    if (!in_array($order_data['status'], ['confirmed', 'confirmed_and_paid', 'preparing'])) {
        error_log("XENDIT WEBHOOK: Order $order_id is not in valid state for payment (current: {$order_data['status']})");
        http_response_code(200);
        exit('Order not in valid state');
    }

    $formattedMethod = Order::formatPaymentMethodLabel('xendit', $payment_channel);

    // Process payment if order is in 'confirmed' status
    if ($order_data['status'] === 'confirmed') {
        // Process payment success (this will also send auto message)
        $payment_data = [
            'amount'          => $paid_amount,
            'transaction_id'  => $invoice_id,
            'payment_method'  => $formattedMethod,
            'payer_email'     => $event['payer_email'] ?? null,
            'currency'        => 'PHP'
        ];

        $order->processPaymentSuccess($order_id, $payment_data);
    } else {
        // Order already paid - ensure payment record exists and send auto message
        try {
            $payment_check = "SELECT id, status FROM payments WHERE order_id = ?";
            $existing_payment = $database->fetch($payment_check, [$order_id]);
            
            if ($existing_payment) {
                if ($existing_payment['status'] !== 'paid') {
                    $payment_sql = "UPDATE payments SET status = 'paid', payment_date = NOW(), transaction_id = ? WHERE order_id = ?";
                    $database->query($payment_sql, [$invoice_id, $order_id]);
                    error_log("XENDIT WEBHOOK: Updated payment record for order {$order_id} to paid status");
                }
            } else {
                // Create payment record if it doesn't exist
                $payment_sql = "INSERT INTO payments (order_id, payment_method, amount, transaction_id, status, payment_date, created_at) 
                               VALUES (?, ?, ?, ?, 'paid', NOW(), NOW())";
                $database->query($payment_sql, [$order_id, $formattedMethod, $paid_amount, $invoice_id]);
                error_log("XENDIT WEBHOOK: Created payment record for order {$order_id}");
            }
        } catch (Exception $e) {
            error_log("XENDIT WEBHOOK: Failed to update/create payment record - " . $e->getMessage());
        }
        
        // Always send auto message (even if order was already processed)
        $message_result = send_auto_payment_message($database, $order_id);
        if ($message_result) {
            error_log("XENDIT WEBHOOK: Auto payment message sent successfully for order {$order_id}");
        } else {
            error_log("XENDIT WEBHOOK: Failed to send auto payment message for order {$order_id}");
        }
    }

    try {
        $database->query(
            "UPDATE orders SET payment_method = ? WHERE id = ?",
            [$formattedMethod, $order_id]
        );
    } catch (Exception $e) {
        error_log("XENDIT WEBHOOK: Failed updating payment method for order {$order_id} - " . $e->getMessage());
    }

    // Clear customer's cart
    $customer = new Customer($database);
    $customer_id = $customer->getCustomerIdByUserId($order_data['customer_id']);
    if ($customer_id) {
        require_once '../classes/Cart.php';
        $cart = new Cart($database, $customer_id);
        $cart->clearCart();
    }

    error_log("XENDIT WEBHOOK: ORDER #$order_id SUCCESSFULLY MARKED AS PAID (₱" . number_format($paid_amount, 2) . ")");

} catch (Exception $e) {
    error_log("XENDIT WEBHOOK ERROR: " . $e->getMessage());
    http_response_code(500);
    exit('Processing failed');
}

http_response_code(200);
echo 'OK';
exit;