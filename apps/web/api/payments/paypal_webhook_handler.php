<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auto_payment_message.php';
require_once '../../includes/auto_payment_message.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the request headers
$headers = getallheaders();
$body = file_get_contents('php://input');

// Get the PayPal webhook verification URL based on mode
$paypal_webhook_verify_url = (PAYPAL_MODE === 'live')
    ? 'https://api.paypal.com/v1/notifications/verify-webhook-signature'
    : 'https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature';

// Get the webhook event
$event = json_decode($body, true);

// Log the event for debugging
error_log("PayPal Webhook Event: " . print_r($event, true));

// Process different event types
if (isset($event['event_type'])) {
    switch ($event['event_type']) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            // Payment completed
            handlePaymentCompleted($event);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
            // Payment denied
            handlePaymentDenied($event);
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            // Payment refunded
            handlePaymentRefunded($event);
            break;
            
        case 'CHECKOUT.ORDER.APPROVED':
            // Order approved but not yet captured
            handleOrderApproved($event);
            break;
            
        default:
            // Other events
            error_log("Unhandled PayPal webhook event: " . $event['event_type']);
            break;
    }
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success']);

/**
 * Handle payment completed event
 */
function handlePaymentCompleted($event) {
    global $database;
    $capture = $event['resource'] ?? null;
    if (!$capture) {
        error_log("Invalid payment capture data");
        return;
    }
    
    $transaction_id = $capture['id'] ?? null;
    $amount = $capture['amount']['value'] ?? null;
    $currency = $capture['amount']['currency_code'] ?? null;
    $custom_id = $capture['custom_id'] ?? null; // This would be your order ID
    
    error_log("Payment completed - Transaction ID: $transaction_id, Amount: $amount $currency, Custom ID: $custom_id");
    
    if (!empty($custom_id) && strpos($custom_id, '|') !== false) {
        $parts = explode('|', $custom_id);
        $order_id = (int) array_pop($parts);
        if ($order_id > 0) {
            try {
                $database->query(
                    "UPDATE orders SET payment_method = ? WHERE id = ?",
                    ['PayPal', $order_id]
                );
            } catch (Exception $e) {
                error_log("PayPal webhook: failed to update payment method for order {$order_id} - " . $e->getMessage());
            }
            send_auto_payment_message($database, $order_id);
        }
    }
}

/**
 * Handle payment denied event
 */
function handlePaymentDenied($event) {
    $capture = $event['resource'] ?? null;
    if (!$capture) {
        error_log("Invalid payment capture data for denied payment");
        return;
    }
    
    $transaction_id = $capture['id'] ?? null;
    $custom_id = $capture['custom_id'] ?? null;
    
    error_log("Payment denied - Transaction ID: $transaction_id, Custom ID: $custom_id");
    
    // Here you would update your database to mark the order as payment failed
}

/**
 * Handle payment refunded event
 */
function handlePaymentRefunded($event) {
    $refund = $event['resource'] ?? null;
    if (!$refund) {
        error_log("Invalid refund data");
        return;
    }
    
    $refund_id = $refund['id'] ?? null;
    $refund_amount = $refund['amount']['value'] ?? null;
    $refund_currency = $refund['amount']['currency_code'] ?? null;
    $capture_id = $refund['capture_id'] ?? null;
    
    error_log("Payment refunded - Refund ID: $refund_id, Amount: $refund_amount $refund_currency, Capture ID: $capture_id");
    
    // Here you would update your database to mark the payment as refunded
}

/**
 * Handle order approved event
 */
function handleOrderApproved($event) {
    $order = $event['resource'] ?? null;
    if (!$order) {
        error_log("Invalid order data");
        return;
    }
    
    $order_id = $order['id'] ?? null;
    $intent = $order['intent'] ?? null;
    $status = $order['status'] ?? null;
    
    error_log("Order approved - Order ID: $order_id, Intent: $intent, Status: $status");
    
    // Here you could update your database to mark the order as approved
}
?>