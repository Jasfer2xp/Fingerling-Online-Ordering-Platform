<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/xendit.php';
require_once '../../includes/xendit_helper.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/auto_payment_message.php';

// Get the raw POST data
$input = file_get_contents('php://input');

// Get the callback token from headers (Xendit sends it as X-Callback-Token)
$headers = getallheaders();
$callback_token = $headers['X-Callback-Token'] ?? $headers['x-callback-token'] ?? $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';

// Verify the webhook signature using the webhook token from config
$expected_token = XENDIT_WEBHOOK_TOKEN;
if (empty($callback_token) || $callback_token !== $expected_token) {
    error_log("Xendit webhook: Invalid callback token. Expected: " . substr($expected_token, 0, 10) . "... Got: " . substr($callback_token, 0, 10) . "...");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Decode the JSON data
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Xendit webhook: Invalid JSON data");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Log the webhook data for debugging
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'token_received' => !empty($callback_token),
    'data' => $data
];

file_put_contents(__DIR__ . '/xendit-webhook.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);

// Process the payment notification
// Xendit sends status as 'PAID' when payment is completed
if (isset($data['status']) && $data['status'] === 'PAID') {
    try {
        $invoice_id = $data['id'] ?? null;
        $external_id = $data['external_id'] ?? null;
        $amount = isset($data['amount']) ? (float)$data['amount'] : null;
        
        // Extract payment channel from Xendit webhook
        // Xendit may send payment_method, payment_channel, or payment_method_id
        // Also check nested structures like data.payment_method or data.payment_channel
        $rawPaymentMethod = $data['payment_method'] 
            ?? $data['payment_channel'] 
            ?? $data['payment_method_id']
            ?? ($data['payment'] ?? [])['method'] ?? null
            ?? ($data['payment'] ?? [])['channel'] ?? null
            ?? 'xendit';
        
        // Normalize the payment method label
        $normalizedPaymentMethod = Order::formatPaymentMethodLabel('xendit', $rawPaymentMethod);
        
        if (!$invoice_id) {
            throw new Exception('Invoice ID not found in webhook data');
        }
        
        // Find the invoice in our database
        $sql = "SELECT * FROM xendit_invoices WHERE invoice_id = ?";
        $invoice = $database->fetch($sql, [$invoice_id]);
        
        if (!$invoice) {
            error_log("Xendit webhook: Invoice not found in database: " . $invoice_id);
            throw new Exception('Invoice not found in database: ' . $invoice_id);
        }
        
        // Prevent double processing
        if ($invoice['xendit_status'] === 'PAID') {
            error_log("Xendit webhook: Invoice already processed: " . $invoice_id);
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Already processed']);
            exit;
        }
        
        // Update invoice status
        $update_sql = "UPDATE xendit_invoices SET xendit_status = 'PAID', updated_at = NOW() WHERE invoice_id = ?";
        $database->query($update_sql, [$invoice_id]);
        
        // If order_id exists, update order and payment status
        if (!empty($invoice['order_id'])) {
            $order_id = $invoice['order_id'];
            
            // Use Order class to properly process payment
            $order = new Order($database);
            $order_data = $order->getOrderById($order_id);
            
            if (!$order_data) {
                throw new Exception('Order not found: ' . $order_id);
            }

            try {
                $database->query(
                    "UPDATE orders SET payment_method = ? WHERE id = ?",
                    [$normalizedPaymentMethod, $order_id]
                );
            } catch (Exception $e) {
                error_log("Xendit webhook: failed to update payment method for order {$order_id} - " . $e->getMessage());
            }
            
            // Process payment if order is in 'confirmed' status (not yet paid)
            // Also handle case where order might already be 'preparing' (idempotency)
            if ($order_data['status'] === 'confirmed') {
                $payment_data = [
                    'amount' => $amount ?? $invoice['amount'],
                    'transaction_id' => $invoice_id,
                    'payment_method' => $normalizedPaymentMethod,
                    'payer_email' => $data['payer_email'] ?? null,
                    'currency' => 'PHP'
                ];

                // This will update order status to 'confirmed_and_paid' (customer sees as "Paid & Processing", supplier sees "Set Date" button)
                // and create payment record
                $order->processPaymentSuccess($order_id, $payment_data);
                send_auto_payment_message($database, $order_id);
                
                // Send payment success SMS
                require_once __DIR__ . '/../../config/sms.php';
                $customer_sql = "SELECT c.contact_number, o.order_number FROM customers c JOIN orders o ON c.id = o.customer_id WHERE o.id = ?";
                $customer_info = $database->fetch($customer_sql, [$order_id]);
                if ($customer_info && !empty($customer_info['contact_number'])) {
                    $phone = $customer_info['contact_number'];
                    $phone = preg_replace('/\D/', '', $phone);
                    if (strlen($phone) === 11 && strpos($phone, '0') === 0) {
                        $phone = '63' . substr($phone, 1);
                    } elseif (strlen($phone) === 10) {
                        $phone = '63' . $phone;
                    }
                    $sms_message = "Your payment for order {$customer_info['order_number']} has been received. Your order is now Paid.";
                    $sms_result = sendSemaphoreSMS($order_id, $phone, $sms_message);
                    if (!$sms_result['success']) {
                        $logDir = __DIR__ . '/../../logs';
                        if (!is_dir($logDir)) {
                            mkdir($logDir, 0755, true);
                        }
                        $errorLogFile = $logDir . '/sms_semaphore_errors.log';
                        $errorMsg = date('c') . " | Order #{$order_id} | {$phone} | Payment Success SMS | Error: " . ($sms_result['message'] ?? 'Unknown error') . "\n";
                        file_put_contents($errorLogFile, $errorMsg, FILE_APPEND);
                    }
                }
                
                error_log("Xendit webhook: Successfully processed payment for order #" . $order_id . " - Status updated to confirmed_and_paid");
            } elseif ($order_data['status'] === 'confirmed_and_paid' || $order_data['status'] === 'preparing') {
                // Order already paid - just update payment record if needed (idempotency)
                error_log("Xendit webhook: Order #" . $order_id . " is already in 'preparing' or 'confirmed_and_paid' status - payment already processed");
                
                // Ensure payment record exists and is marked as paid
                $payment_check = "SELECT id, status FROM payments WHERE order_id = ?";
                $existing_payment = $database->fetch($payment_check, [$order_id]);
                
                if ($existing_payment) {
                    if ($existing_payment['status'] !== 'paid') {
                        $payment_sql = "UPDATE payments SET status = 'paid', payment_date = NOW(), transaction_id = ? WHERE order_id = ?";
                        $database->query($payment_sql, [$invoice_id, $order_id]);
                        error_log("Xendit webhook: Updated payment record for order #" . $order_id . " to paid status");
                    }
                } else {
                    // Create payment record if it doesn't exist
                    $payment_sql = "INSERT INTO payments (order_id, payment_method, amount, transaction_id, status, payment_date, created_at) 
                                   VALUES (?, ?, ?, ?, 'paid', NOW(), NOW())";
                    $database->query($payment_sql, [$order_id, $normalizedPaymentMethod, $invoice['amount'], $invoice_id]);
                    error_log("Xendit webhook: Created payment record for order #" . $order_id);
                }
                send_auto_payment_message($database, $order_id);
            } else {
                error_log("Xendit webhook: Order #" . $order_id . " is not in 'confirmed' or 'confirmed_and_paid' status (current: " . $order_data['status'] . ") - cannot process payment");
            }
        } else {
            // Order not created yet - log for debugging
            $log_data['message'] = 'Payment successful but order not created yet';
            file_put_contents(__DIR__ . '/xendit-webhook.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);
        }
        
        $log_data['message'] = 'Payment processed successfully';
        file_put_contents(__DIR__ . '/xendit-webhook.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);
        
        // Send a 200 response
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed']);
        exit;
        
    } catch (Exception $e) {
        error_log("Xendit webhook error: " . $e->getMessage());
        $log_data['error'] = $e->getMessage();
        file_put_contents(__DIR__ . '/xendit-webhook.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);
        
        // Still return 200 to prevent Xendit from retrying
        http_response_code(200);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// Send a 200 response for all other cases
http_response_code(200);
echo json_encode(['status' => 'received']);
exit;