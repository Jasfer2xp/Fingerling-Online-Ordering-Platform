<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// GLOBAL DEBUG LOG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logDir = __DIR__ . '/../logs'; // c:\xampp\htdocs\capstone\logs
    if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
    $debugFile = $logDir . '/global_post_debug.log';
    $data = "POST " . date('c') . " | Action: " . ($_POST['action'] ?? 'NONE') . " | OrderID: " . ($_POST['order_id'] ?? 'N/A') . "\n";
    file_put_contents($debugFile, $data, FILE_APPEND);
}

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

// Check if this is a user who just completed registration
$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) {
    unset($_SESSION['registration_just_completed']);
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if (!$just_completed_registration && (empty($profile['business_name']) || empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['business_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=supplier&step=3'));
}

// if ($profile['status'] !== 'approved') {
//     redirect(base_url('supplier/dashboard.php'));
// }

// Ensure we're using the Supplier class correctly
require_once '../classes/Supplier.php';
$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Proof Verification (Supplier Review)
    // Legacy 'verify_proof' block removed (moved/handled elsewhere)
    // NEW: Handle Proof Verification
    if ($action === 'verify_proof') {
        try {
            $order_id = intval($_POST['order_id']);
            $status = $_POST['status']; // 'approved' or 'rejected'
            $rejection_reason = $_POST['rejection_reason'] ?? '';

            // Verify ownership
            $order_check = $database->fetch("SELECT supplier_id FROM orders WHERE id = ?", [$order_id]);
            if (!$order_check || $order_check['supplier_id'] != $supplier_id) {
                throw new Exception("Unauthorized or Order not found.");
            }

            require_once '../classes/Order.php';
            $order_obj = new Order($database);

            if ($status === 'approved') {
                // Mark as Delivered
                // This transition (pending_supplier_review -> delivered) is allowed in Order.php
                $order_obj->updateOrderStatus($order_id, 'delivered');
                $_SESSION['success'] = "Proof verified successfully. Order marked as Delivered.";
            } elseif ($status === 'rejected') {
                // Reject -> Revert to Out for Delivery
                // This transition (pending_supplier_review -> out_for_delivery) is allowed in Order.php
                $order_obj->updateOrderStatus($order_id, 'out_for_delivery');
                
                // Log rejection logic/notification here if needed
                // For now, it just goes back to the rider/delivery list
                $_SESSION['success'] = "Proof rejected. Order reverted to 'Out for Delivery'.";
            }
            redirect(base_url('supplier/orders.php?status=pending_supplier_review'));

        } catch (Exception $e) {
            $_SESSION['error'] = "Error verifying proof: " . $e->getMessage();
            redirect(base_url('supplier/orders.php?status=pending_supplier_review'));
        }
    }
    
    try {
        if ($action === 'sync_xendit') {
            // AGGRESSIVE FIX: Get ALL confirmed orders and check EVERYTHING
            $confirmed_orders = $database->fetchAll(
                "SELECT o.id, o.order_number, o.total_amount,
                        xi.id as invoice_db_id, xi.invoice_id, xi.xendit_status, xi.amount as invoice_amount,
                        p.id as payment_id, p.status as payment_status
                 FROM orders o
                 LEFT JOIN xendit_invoices xi ON o.id = xi.order_id
                 LEFT JOIN payments p ON o.id = p.order_id AND p.status IN ('paid', 'completed')
                 WHERE o.supplier_id = ? 
                 AND o.status = 'confirmed'
                 ORDER BY o.created_at DESC",
                [$supplier_id]
            );
            
            $updated_count = 0;
            $checked_count = count($confirmed_orders);
            $errors = [];
            $details = [];
            
            require_once __DIR__ . '/../classes/Order.php';
            require_once __DIR__ . '/../includes/xendit_api_check.php';
            
            foreach ($confirmed_orders as $order) {
                $order_id = $order['id'];
                $should_update = false;
                $reason = '';
                
                // Check 1: Does it have a payment record?
                if (!empty($order['payment_id']) && in_array($order['payment_status'], ['paid', 'completed'])) {
                    $should_update = true;
                    $reason = 'has payment record';
                }
                // Check 2: Does it have a PAID invoice in database?
                elseif (!empty($order['invoice_id']) && $order['xendit_status'] === 'PAID') {
                    $should_update = true;
                    $reason = 'invoice marked PAID in database';
                }
                // Check 3: Check Xendit API directly
                elseif (!empty($order['invoice_id'])) {
                    $invoice_data = check_xendit_invoice_status($order['invoice_id']);
                    if ($invoice_data && strtoupper($invoice_data['status'] ?? '') === 'PAID') {
                        // Update invoice status in database
                        $database->query(
                            "UPDATE xendit_invoices SET xendit_status = 'PAID', updated_at = NOW() WHERE invoice_id = ?",
                            [$order['invoice_id']]
                        );
                        $should_update = true;
                        $reason = 'confirmed PAID via Xendit API';
                    }
                }
                
                // Update order if any check passed
                if ($should_update) {
                    try {
                        $order_obj = new Order($database);
                        $payment_data = [
                            'amount' => $order['invoice_amount'] ?? $order['total_amount'],
                            'transaction_id' => $order['invoice_id'] ?? 'sync_' . time(),
                            'payment_method' => 'xendit',
                            'currency' => 'PHP'
                        ];
                        $order_obj->processPaymentSuccess($order_id, $payment_data);
                        $updated_count++;
                        $details[] = "Order {$order['order_number']}: {$reason}";
                        error_log("SYNC FIX: Updated order {$order_id} ({$order['order_number']}) - {$reason}");
                    } catch (Exception $e) {
                        $errors[] = "Order {$order['order_number']}: {$e->getMessage()}";
                        error_log("SYNC ERROR for order {$order_id}: " . $e->getMessage());
                    }
                } else {
                    $details[] = "Order {$order['order_number']}: No payment found (invoice: " . ($order['invoice_id'] ? substr($order['invoice_id'], 0, 15) . '...' : 'none') . ", status: " . ($order['xendit_status'] ?? 'none') . ")";
                }
            }
            
            
            // Build message
            if ($updated_count > 0) {
                $_SESSION['success'] = "✓ Successfully updated {$updated_count} order(s) to 'Paid & Processing'!";
                foreach ($details as $detail) {
                    error_log("SYNC DETAIL: {$detail}");
                }
            } else {
                $_SESSION['info'] = "Checked {$checked_count} order(s). No payments found. " . 
                                   ($checked_count > 0 ? "Orders may not have invoices linked or payments not completed yet." : "");
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = "Errors: " . implode('; ', array_slice($errors, 0, 3));
            }
            
            redirect(base_url('supplier/orders.php?status=confirmed'));
        } elseif ($action === 'set_delivery_start_date') {
  $order_id = intval($_POST['order_id'] ?? 0);
  $start_date = $_POST['start_date'] ?? null; // YYYY-MM-DD
  if (!$order_id || !$start_date) { $_SESSION['error']='Invalid'; redirect(base_url('supplier/orders.php')); }
  $from = $start_date;
  $to = date('Y-m-d', strtotime($start_date . ' +2 days'));
  $deadline = $to . ' 23:59:59';
  $now = date('Y-m-d H:i:s');
  $database->query("UPDATE orders SET supplier_delivery_from = ?, supplier_delivery_to = ?, delivery_selection_deadline = ?, updated_at = ? WHERE id = ?", [$from, $to, $deadline, $now, $order_id]);
  $order = $database->fetch("SELECT order_number, customer_id FROM orders WHERE id = ?", [$order_id]);
  $customer_user = $database->fetch("SELECT user_id FROM customers WHERE id = ?", [$order['customer_id']]);
  $customer_user_id = $customer_user['user_id'] ?? null;
  $title = "Choose delivery date for order #{$order['order_number']}";
  $msg = "Supplier set a delivery window: {$from} to {$to}. Click here to choose your preferred delivery date.";
  if ($customer_user_id) {
    $database->query("INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, ?, 'order', ?)", [$customer_user_id, $order['customer_id'], $title, $msg, "/customer/order-details.php?id={$order_id}&action=choose_date", $now]);
  }
  $_SESSION['success']='Delivery window set.'; redirect(base_url('supplier/orders.php?status=scheduled_for_delivery'));
        } elseif ($action === 'choose_delivery_date') {
  $order_id = intval($_POST['order_id'] ?? 0);
  $chosen = $_POST['chosen_date'] ?? null;
  $customer_id = $_SESSION['customer_id'] ?? null;
  if (!$order_id || !$chosen) { $_SESSION['error']='Invalid'; redirect(base_url('customer/orders.php')); }
  $row = $database->fetch("SELECT supplier_delivery_from, supplier_delivery_to, customer_id, order_number, supplier_id FROM orders WHERE id = ?", [$order_id]);
  if (!$row || (int)$row['customer_id'] !== (int)$customer_id) { $_SESSION['error']='Access denied'; redirect(base_url('customer/orders.php')); }
  if ($chosen < $row['supplier_delivery_from'] || $chosen > $row['supplier_delivery_to']) { $_SESSION['error']='Date out of window'; redirect(base_url('customer/orders.php')); }
  $now = date('Y-m-d H:i:s');
  $database->query("UPDATE orders SET customer_selected_delivery_date = ?, delivery_date = ?, updated_at = ? WHERE id = ?", [$chosen, $chosen, $now, $order_id]);
  $title = "Customer selected delivery date - Order #{$row['order_number']}";
  $msg = "Customer selected delivery date: {$chosen}.";
  // notify supplier
  $supplier_user = $database->fetch("SELECT user_id FROM suppliers WHERE id = ?", [$row['supplier_id']]);
  $supplier_user_id = $supplier_user['user_id'] ?? null;
  if ($supplier_user_id) {
    $database->query("INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, ?, 'order', ?)", [$supplier_user_id, $row['customer_id'], $title, $msg, "/supplier/orders.php?status=scheduled_for_delivery", $now]);
  }
  $_SESSION['success']='Delivery date selected.'; redirect(base_url('customer/orders.php'));
        } elseif ($action === 'assign_rider') {
    try {
        $order_id = intval($_POST['order_id'] ?? 0);
        $rider_name = trim($_POST['rider_name'] ?? '');
        $rider_phone = trim($_POST['rider_phone'] ?? '');
        
        if (!$order_id || $rider_name === '' || $rider_phone === '') {
            throw new Exception('Invalid input: Order ID, Rider Name, and Rider Phone are required.');
        }

        // 1. Fetch Order
        $order_row = $database->fetch(
            "SELECT id, order_number, status, supplier_id, customer_id FROM orders WHERE id = ?",
            [$order_id]
        );
        if (!$order_row) throw new Exception('Order not found.');

        // 2. Verify Ownership
        $session_supplier_id = $_SESSION['supplier_id'] ?? null;
        if ($session_supplier_id && (int)$order_row['supplier_id'] !== (int)$session_supplier_id) {
            throw new Exception('Access denied.');
        }

        $now = date('Y-m-d H:i:s');
        
        // 3. Generate Token
        $token = bin2hex(random_bytes(16));
        $token_expires = date('Y-m-d H:i:s', strtotime('+12 hours'));
        
        // 4. Update Database
        try {
            $database->query(
                "UPDATE orders SET 
                    delivery_worker = ?, 
                    delivery_phone = ?, 
                    status = 'out_for_delivery', 
                    delivery_token = ?,
                    token_expires_at = ?,
                    token_used = 0,
                    updated_at = ? 
                WHERE id = ?",
                [$rider_name, $rider_phone, $token, $token_expires, $now, $order_id]
            );
        } catch (Exception $dbEx) {
            // Check if column error (migration not run)
            if (strpos($dbEx->getMessage(), 'Unknown column') !== false) {
                 throw new Exception('Database error: Please run the migration scripts to add delivery_token columns.');
            }
            throw $dbEx;
        }

        // 5. Send SMS to Rider (PRIORITY)
        $sms_sent = false;
        $sms_error = '';
        if (file_exists(__DIR__ . '/../config/sms.php')) {
            require_once __DIR__ . '/../config/sms.php';
            if (function_exists('sendSemaphoreSMS')) {
                // FORCE Production URL to ensure SMS contains a valid public link
                // Optimized: Remove https:// to reduce spam score
                $production_base = "fingerling.shop/"; 
                $proof_link = $production_base . "rider/delivery-proof.php?token={$token}";
                
                // Short, professional message to avoid spam filters
                $rider_msg = "Order {$order_row['order_number']}: You are assigned.\nUpload: {$proof_link}\nCode: " . substr($token, 0, 6);
                
                $sms_sent = sendSemaphoreSMS($order_id, $rider_phone, $rider_msg);
                if (!$sms_sent) {
                    $sms_error = "Failed to send SMS to Rider ($rider_phone). Check logs.";
                }
            }
        }

        // 6. Notifications (Customer) - BEST EFFORT / NON-BLOCKING FEEL
        try {
            // Insert notification into DB
            $title = "Delivery Rider Assigned - Order #{$order_row['order_number']}";
            $message = "Your delivery rider is {$rider_name}. Contact: {$rider_phone}.";
            
            $customer_user = $database->fetch("SELECT user_id FROM customers WHERE id = ?", [$order_row['customer_id']]);
            $customer_user_id = $customer_user['user_id'] ?? null;
            
            if ($customer_user_id) {
                // In-app notification
                $database->query(
                    "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, ?, 'order', ?)",
                    [$customer_user_id, $order_row['customer_id'], $title, $message, "/customer/order-details.php?id={$order_id}", $now]
                );
            }

            // Fetch Customer Contacts
            $cus = $database->fetch("SELECT u.email, c.contact_number FROM customers c JOIN users u ON c.user_id = u.id WHERE c.id = ?", [$order_row['customer_id']]);
            $customer_email = $cus['email'] ?? null;
            $customer_phone = $cus['contact_number'] ?? null;

            // Customer SMS
            if ($customer_phone && function_exists('sendSemaphoreSMS')) {
                 // Send simplified SMS to customer
                 sendSemaphoreSMS($order_id, $customer_phone, "Your rider for Order {$order_row['order_number']} is {$rider_name} ($rider_phone).");
            }

            // Customer Email (Often slow - wrap in try/catch and maybe skip if slow)
            if ($customer_email) {
                $mailer_path = __DIR__ . '/../includes/mailer.php';
                if (file_exists($mailer_path)) {
                    require_once $mailer_path;
                    if (function_exists('send_app_email')) {
                        // Suppress exceptions from mailer to avoid crashing/hanging too long
                        try {
                            send_app_email($customer_email, "Rider Assigned - Order #{$order_row['order_number']}", $message);
                        } catch (Exception $e) { /* ignore email error */ }
                    }
                }
            }
        } catch (Exception $ignored) {
            // these are secondary notifications, do not fail the request
        }

        $_SESSION['success'] = $sms_sent ? 'Rider assigned and SMS link sent!' : 'Rider assigned but SMS failed. Please check phone number.';
        redirect(base_url('supplier/orders.php?status=preparing'));

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        redirect(base_url('supplier/orders.php?status=preparing'));
    }
} elseif ($action === 'resend_rider_sms') {
    try {
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) throw new Exception('Invalid Order ID.');

        $order_row = $database->fetch(
            "SELECT id, order_number, status, delivery_worker, delivery_phone, supplier_id FROM orders WHERE id = ?",
            [$order_id]
        );
        
        if (!$order_row) throw new Exception('Order not found.');
        
        // Verify ownership
        $session_supplier_id = $_SESSION['supplier_id'] ?? null;
        if ($session_supplier_id && $session_supplier_id != $order_row['supplier_id']) {
             throw new Exception('Access Denied.');
        }

        if (empty($order_row['delivery_phone'])) throw new Exception('No rider phone number assigned.');

        // Debug logging
        $debugLog = __DIR__ . '/../logs/sms_debug_controller.log';
        $logEntry = date('c') . " | Resending SMS Order #{$order_id}\n";
        $logEntry .= " - Rider Phone: [{$order_row['delivery_phone']}]\n"; // Log phone to check format
        
        // Regenerate Token (Security: invalidate old one)
        $token = bin2hex(random_bytes(16));
        $token_expires = date('Y-m-d H:i:s', strtotime('+12 hours'));
        
        $database->query(
            "UPDATE orders SET delivery_token = ?, token_expires_at = ?, token_used = 0 WHERE id = ?",
            [$token, $token_expires, $order_id]
        );

        // Send SMS
        $error_detail = "";
        if (file_exists(__DIR__ . '/../config/sms.php')) {
            require_once __DIR__ . '/../config/sms.php';
            
            // Check API Key explicitly so we can tell the user
            $apiKeyVar = getenv('SEMAPHORE_API_KEY');
            if (!$apiKeyVar && isset($_ENV['SEMAPHORE_API_KEY'])) $apiKeyVar = $_ENV['SEMAPHORE_API_KEY'];
            
            if (empty($apiKeyVar)) {
                $error_detail = "SEMAPHORE_API_KEY is not set in environment or .env file.";
            } elseif (function_exists('sendSemaphoreSMS')) {
                // Remove https:// to reduce spam score
                // Revert: Domain points directly to app root
                $production_base = "fingerling.shop/"; 
                $proof_link = $production_base . "rider/delivery-proof.php?token={$token}";
                $rider_msg = "Order {$order_row['order_number']}: You are assigned.\nUpload: {$proof_link}\nCode: " . substr($token, 0, 6);
                
                $logEntry .= " - Msg: " . str_replace("\n", " ", $rider_msg) . "\n"; 

                // Try to send
                $sent = sendSemaphoreSMS($order_id, $order_row['delivery_phone'], $rider_msg);
                
                $logEntry .= " - Send Result: " . ($sent ? 'TRUE' : 'FALSE') . "\n";
                file_put_contents($debugLog, $logEntry . "-------------------------\n", FILE_APPEND);
                
                if ($sent) {
                    $_SESSION['success'] = "SMS link resent to Rider ({$order_row['delivery_phone']}) successfully.";
                } else {
                    $error_detail = "Semaphore API failure. Target: {$order_row['delivery_phone']}. See logs.";
                }
            } else {
                $error_detail = "Function sendSemaphoreSMS() not found.";
            }
        } else {
             $error_detail = "config/sms.php file not found.";
        }
        
        if (!empty($error_detail)) {
            $_SESSION['error'] = "SMS FAILED: " . $error_detail;
            // Also log it
            file_put_contents($debugLog, date('c') . " | UI Error Displayed: $error_detail\n", FILE_APPEND);
        }
        
        redirect(base_url('supplier/orders.php?status=out_for_delivery'));
        
        redirect(base_url('supplier/orders.php?status=out_for_delivery'));

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        redirect(base_url('supplier/orders.php?status=out_for_delivery'));
    }
} elseif ($action === 'verify_proof') {
    try {
        $order_id = intval($_POST['order_id'] ?? 0);
        $check = $database->fetch("SELECT id, order_number, customer_id FROM orders WHERE id = ? AND supplier_id = ?", [$order_id, $supplier_id]);
        
        if (!$check) throw new Exception('Access Denied or Order Not Found.');

        // Update to DELIVERED directly - DISABLED
        // $database->query("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?", [$order_id]);

        // Notifications - DISABLED
        /*
        $title = "Order Delivered - #{$check['order_number']}";
        $message = "Your order #{$check['order_number']} has been marked as delivered by the supplier.";
        $link = "/customer/order-details.php?id={$order_id}";
        
        $c_user = $database->fetch("SELECT user_id FROM customers WHERE id = ?", [$check['customer_id']]);
        if ($c_user) {
             $database->query(
                "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, ?, 'order', NOW())",
                [$c_user['user_id'], $check['customer_id'], $title, $message, $link]
            );
        }
        */

        $_SESSION['error'] = 'Only customers can confirm delivery. Please wait for the customer to confirm.';
        redirect(base_url('supplier/orders.php?status=pending_supplier_review'));

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect(base_url('supplier/orders.php?status=pending_supplier_review'));
    }
} elseif ($action === 'update_status') {
            $order_id = intval($_POST['order_id']);
            $status = trim($_POST['status'] ?? '');
            $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
            $delivery_worker = !empty($_POST['delivery_worker']) ? $_POST['delivery_worker'] : null;
            $delivery_worker_contact = !empty($_POST['delivery_worker_contact']) ? $_POST['delivery_worker_contact'] : null;
            
            // Check current order status before proceeding
            $current_order_sql = "SELECT status, delivery_date FROM orders WHERE id = ? AND supplier_id = ?";
            $current_order = $database->fetch($current_order_sql, [$order_id, $supplier_id]);
            
            if (!$current_order) {
                throw new Exception('Order not found.');
            }
            
            // CRITICAL: If delivery_date is provided, calculate 3-day range and set status to scheduled_for_delivery
            // This replaces any current status (including empty string or confirmed_and_paid)
            if ($delivery_date) {
                $status = 'scheduled_for_delivery';
                
                // Calculate 3-day delivery range from selected date
                // If supplier selects December 4, range will be December 4-7 (4 days total: 4, 5, 6, 7)
                date_default_timezone_set('Asia/Manila');
                $start_date = new DateTime($delivery_date);
                $end_date = clone $start_date;
                $end_date->modify('+3 days'); // Selected date + 3 days = 4-day window (e.g., Dec 4-7)
                
                // Update order with delivery date range
                $range_sql = "UPDATE orders SET 
                             delivery_date_start = ?,
                             delivery_date_end = ?,
                             delivery_date = ?,
                             updated_at = NOW()
                             WHERE id = ? AND supplier_id = ?";
                $database->query($range_sql, [
                    $start_date->format('Y-m-d'),
                    $end_date->format('Y-m-d'),
                    $delivery_date, // Keep original date for backward compatibility
                    $order_id,
                    $supplier_id
                ]);
            }
            
            // Ensure status is never empty
            if (empty($status)) {
                // If no status provided but delivery_date exists, use scheduled_for_delivery
                if ($delivery_date) {
                    $status = 'scheduled_for_delivery';
                } else {
                    // Keep current status if no delivery_date
                    $status = !empty($current_order['status']) ? $current_order['status'] : 'pending';
                }
            }
            
            // Prevent updating if order is already in the target status (unless we're setting delivery_date)
            if ($current_order['status'] === $status && !$delivery_date) {
                $_SESSION['success'] = 'Order status is already ' . ucfirst(str_replace('_', ' ', $status)) . '.';
                redirect(base_url('supplier/orders.php' . (!empty($_GET['status']) ? '?status=' . $_GET['status'] : '')));
            }

            // CRITICAL: Prevent suppliers from marking orders as delivered directly
            // Only customers can confirm delivery after proof is uploaded
            if ($status === 'delivered') {
                throw new Exception('Orders can only be marked as delivered when the customer confirms receipt. Please upload proof of delivery and wait for customer confirmation.');
            }
            
            // CRITICAL: Check if order has been paid (PayPal or Xendit) — BLOCK CANCELLATION
            $payment_check = $database->fetch("SELECT status FROM payments WHERE order_id = ? AND status = 'completed' LIMIT 1", [$order_id]);
            $is_paid = $payment_check !== false;

            if ($is_paid && in_array($status, ['cancelled', 'pending'])) {
                throw new Exception('Cannot cancel or revert this order. Payment has already been completed.');
            }

            // Handle PayPal order confirmation/cancellation through API
            if ($status === 'confirmed' || $status === 'cancelled') {
                // Check if this is a PayPal order that needs to be captured or voided
                $payment_sql = "SELECT p.authorization_id, p.payment_method, p.status as payment_status
                               FROM payments p 
                               JOIN orders o ON p.order_id = o.id 
                               WHERE o.id = ? AND o.supplier_id = ? AND p.payment_method = 'paypal'";
                $payment = $database->fetch($payment_sql, [$order_id, $supplier_id]);
                
                if ($payment && !empty($payment['authorization_id']) && $payment['payment_status'] === 'authorized') {
                    if ($status === 'confirmed') {
                        // Use our new API endpoint to capture the PayPal payment
                        $capture_data = [
                            'order_id' => $order_id,
                            'supplier_id' => $supplier_id
                        ];
                        
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => base_url('api/orders/confirm_order.php'),
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($capture_data),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => [
                                'Content-Type: application/json'
                            ]
                        ]);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        $result = json_decode($response, true);
                        if (!$result['success']) {
                            throw new Exception('Failed to capture PayPal payment: ' . $result['message']);
                        }
                    } else if ($status === 'cancelled') {
                        // Use our new API endpoint to void the PayPal payment
                        $void_data = [
                            'order_id' => $order_id,
                            'supplier_id' => $supplier_id
                        ];
                        
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => base_url('api/orders/cancel_order.php'),
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($void_data),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => [
                                'Content-Type: application/json'
                            ]
                        ]);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        $result = json_decode($response, true);
                        if (!$result['success']) {
                            throw new Exception('Failed to void PayPal payment: ' . $result['message']);
                        }
                    }
                }
            }
            
            // Check if customer has selected delivery date/time before allowing status change to preparing
            // Only check this when trying to move to 'preparing' status, not when setting delivery date
            if ($status === 'preparing') {
                $delivery_check = $database->fetch(
                    "SELECT customer_selected_delivery_date, delivery_time_selected, delivery_date FROM orders WHERE id = ?", 
                    [$order_id]
                );
                // Check both columns for backward compatibility OR if supplier set manual delivery_date
                $has_selected_date = !empty($delivery_check['customer_selected_delivery_date']) || 
                                     !empty($delivery_check['delivery_time_selected']) || 
                                     !empty($delivery_check['delivery_date']);
                
                if (!$has_selected_date) {
                    throw new Exception('Delivery date must be set before you can proceed to preparing status. Customer must select a date or you must schedule it manually.');
                }
            }
            
            // Use Order class to update status
            try {
                $order_obj = new Order($database);
                
                // CRITICAL: If delivery_date is provided, force status to scheduled_for_delivery
                // This must happen BEFORE calling updateOrderStatus
                if ($delivery_date) {
                    $status = 'scheduled_for_delivery';
                }
                
                // Update order status (this will also save delivery_date and send SMS)
                $order_obj->updateOrderStatus($order_id, $status, $delivery_date);
                
                // Double-check: Verify status and delivery_date were saved correctly
                $verify_sql = "SELECT status, delivery_date FROM orders WHERE id = ? AND supplier_id = ?";
                $verify_result = $database->fetch($verify_sql, [$order_id, $supplier_id]);
                
                if ($verify_result) {
                    // If status didn't update, force it (CRITICAL for preparing, out_for_delivery, delivered)
                    if (empty($verify_result['status']) || $verify_result['status'] !== $status) {
                        error_log("Status mismatch for order {$order_id}: expected '{$status}', got '{$verify_result['status']}'. Forcing update.");
                        $force_update = "UPDATE orders SET status = ?, updated_at = NOW()";
                        $force_params = [$status];
                        if ($delivery_date) {
                            $force_update .= ", delivery_date = ?";
                            $force_params[] = $delivery_date;
                        }
                        $force_update .= " WHERE id = ? AND supplier_id = ?";
                        $force_params[] = $order_id;
                        $force_params[] = $supplier_id;
                        $database->query($force_update, $force_params);
                        
                        // Verify again after force update
                        $verify_after = $database->fetch($verify_sql, [$order_id, $supplier_id]);
                        if ($verify_after && $verify_after['status'] !== $status) {
                            error_log("CRITICAL: Status still not updated after force update for order {$order_id}. Expected: '{$status}', Got: '{$verify_after['status']}'");
                        }
                    }
                    
                    // If delivery_date didn't save, force it
                    if ($delivery_date && $verify_result['delivery_date'] !== $delivery_date) {
                        error_log("Delivery date mismatch for order {$order_id}: expected '{$delivery_date}', got '{$verify_result['delivery_date']}'. Forcing update.");
                        $database->query("UPDATE orders SET delivery_date = ?, updated_at = NOW() WHERE id = ? AND supplier_id = ?", 
                            [$delivery_date, $order_id, $supplier_id]);
                    }
                }
            } catch (Exception $e) {
                error_log("Supplier order status update failed: " . $e->getMessage());
                // If Order class update fails, try direct database update as fallback
                try {
                    error_log("Attempting direct database update as fallback for order {$order_id} to status '{$status}'");
                    $direct_update = "UPDATE orders SET status = ?, updated_at = NOW()";
                    $direct_params = [$status];
                    if ($delivery_date) {
                        $direct_update .= ", delivery_date = ?";
                        $direct_params[] = $delivery_date;
                    }
                    $direct_update .= " WHERE id = ? AND supplier_id = ?";
                    $direct_params[] = $order_id;
                    $direct_params[] = $supplier_id;
                    $database->query($direct_update, $direct_params);
                    error_log("Direct database update succeeded for order {$order_id}");
                    $_SESSION['success'] = 'Order status updated successfully.';
                } catch (Exception $e2) {
                    error_log("Direct database update also failed: " . $e2->getMessage());
                    $_SESSION['error'] = 'Failed to update order status: ' . $e->getMessage();
                }
            }
            
            // If delivery worker is provided, update it separately and send notification
            if ($delivery_worker) {
                $sql = "UPDATE orders SET delivery_worker = ? WHERE id = ? AND supplier_id = ?";
                $database->query($sql, [$delivery_worker, $order_id, $supplier_id]);
                
                // Get order and customer info for notification
                $order_info_sql = "SELECT o.*, c.user_id as customer_user_id, c.id as customer_id, c.first_name, c.last_name, c.contact_number
                                  FROM orders o
                                  JOIN customers c ON o.customer_id = c.id
                                  WHERE o.id = ?";
                $order_info = $database->fetch($order_info_sql, [$order_id]);
                
                if ($order_info) {
                    // Get delivery worker phone from delivery_notes or delivery_worker_contact
                    $worker_phone = '';
                    if (!empty($order_info['delivery_worker_contact'])) {
                        $worker_phone = $order_info['delivery_worker_contact'];
                    } elseif (!empty($order_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $order_info['delivery_notes'], $matches)) {
                        $worker_phone = trim($matches[1]);
                    }
                    
                    date_default_timezone_set('Asia/Manila');
                    $manila_time = date('Y-m-d H:i:s');
                    
                    // Create notification with rider info
                    $notification_title = "Rider Assigned - Order #{$order_info['order_number']}";
                    $notification_message = "A delivery rider has been assigned to your order #{$order_info['order_number']}. Rider: {$delivery_worker}";
                    if ($worker_phone) {
                        $notification_message .= " (Phone: {$worker_phone})";
                    }
                    $notification_message .= ". Please be available to receive your order.";
                    $notification_link = base_url("customer/order-details.php?id=" . $order_id);
                    
                    $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) 
                                        VALUES (?, ?, ?, ?, ?, 'order', ?)";
                    $database->query($notification_sql, [
                        $order_info['customer_user_id'],
                        $order_info['customer_id'],
                        $notification_title,
                        $notification_message,
                        $notification_link,
                        $manila_time
                    ]);
                    
                    // Send email notification (if email helper exists)
                    if (function_exists('send_app_email')) {
                        require_once __DIR__ . '/../includes/mailer.php';
                        $customer_email_sql = "SELECT email FROM users WHERE id = ?";
                        $customer_user = $database->fetch($customer_email_sql, [$order_info['customer_user_id']]);
                        if ($customer_user && !empty($customer_user['email'])) {
                            $email_subject = "Rider Assigned for Your Order #{$order_info['order_number']}";
                            $email_body = "<h2>Delivery Rider Assigned</h2>";
                            $email_body .= "<p>Dear {$order_info['first_name']} {$order_info['last_name']},</p>";
                            $email_body .= "<p>A delivery rider has been assigned to your order #{$order_info['order_number']}.</p>";
                            $email_body .= "<p><strong>Rider Name:</strong> {$delivery_worker}</p>";
                            if ($worker_phone) {
                                $email_body .= "<p><strong>Rider Phone:</strong> {$worker_phone}</p>";
                            }
                            $email_body .= "<p>Please be available to receive your order. The rider will contact you when they are on the way.</p>";
                            $email_body .= "<p><a href=\"{$notification_link}\">View Order Details</a></p>";
                            
                            send_app_email($customer_user['email'], $email_subject, $email_body, true);
                        }
                    }
                    
                    // Send SMS notification
                    if (function_exists('sendSemaphoreSMS') && !empty($order_info['contact_number'])) {
                        require_once __DIR__ . '/../config/sms.php';
                        $sms_message = "Rider assigned for Order #{$order_info['order_number']}. Rider: {$delivery_worker}";
                        if ($worker_phone) {
                            $sms_message .= " (Phone: {$worker_phone})";
                        }
                        $sms_message .= ". Please be available to receive your order.";
                        sendSemaphoreSMS($order_id, $order_info['contact_number'], $sms_message);
                    }
                    
                    // Send message to customer in conversations
                    try {
                        if (class_exists('Message')) {
                            require_once __DIR__ . '/../classes/Message.php';
                            $message = new Message($database);
                            
                            // Get conversation ID for this order
                            $conv_sql = "SELECT id FROM conversations WHERE order_id = ? LIMIT 1";
                            $conversation = $database->fetch($conv_sql, [$order_id]);
                            
                            if ($conversation) {
                                // Get supplier user_id
                                $supplier_user_sql = "SELECT user_id FROM suppliers WHERE id = ?";
                                $supplier_user = $database->fetch($supplier_user_sql, [$supplier_id]);
                                
                                if ($supplier_user) {
                                    $message_text = "🚚 Delivery rider assigned for your order #{$order_info['order_number']}.\n\n";
                                    $message_text .= "Rider: {$delivery_worker}\n";
                                    if ($worker_phone) {
                                        $message_text .= "Phone: {$worker_phone}\n";
                                    }
                                    $message_text .= "\nPlease be available to receive your order.";
                                    
                                    $message->sendMessage(
                                        $conversation['id'],
                                        $supplier_user['user_id'],
                                        $order_info['customer_user_id'],
                                        $message_text
                                    );
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Message sending failed, but continue - notification and email are more important
                        error_log("Failed to send rider assignment message: " . $e->getMessage());
                    }
                }
            }
            
            // If delivery worker contact number is provided, update it
            if ($delivery_worker_contact) {
                // Store in a custom field or use notes field - check if column exists
                try {
                    $check_sql = "SELECT delivery_worker_contact FROM orders LIMIT 1";
                    $database->query($check_sql);
                    $sql = "UPDATE orders SET delivery_worker_contact = ? WHERE id = ? AND supplier_id = ?";
                    $database->query($sql, [$delivery_worker_contact, $order_id, $supplier_id]);
                } catch (Exception $e) {
                    // Column doesn't exist, store in notes or create migration
                    // For now, append to delivery_notes
                    $update_notes = "UPDATE orders SET delivery_notes = CONCAT(COALESCE(delivery_notes, ''), '\nDelivery Worker Contact: ', ?) WHERE id = ? AND supplier_id = ?";
                    $database->query($update_notes, [$delivery_worker_contact, $order_id, $supplier_id]);
                }
            }
            
            // If delivery date range was set, send notification to customer to select exact date/time
            if ($delivery_date) {
                // Get the updated order info with the calculated date range
                $order_info_sql = "SELECT o.*, c.user_id as customer_user_id, c.id as customer_id, 
                                  o.delivery_date_start, o.delivery_date_end, o.delivery_worker, o.delivery_worker_contact, o.delivery_notes
                                  FROM orders o
                                  JOIN customers c ON o.customer_id = c.id
                                  WHERE o.id = ?";
                $order_info = $database->fetch($order_info_sql, [$order_id]);
                
                if ($order_info && !empty($order_info['delivery_date_start']) && !empty($order_info['delivery_date_end'])) {
                    date_default_timezone_set('Asia/Manila');
                    $manila_time = date('Y-m-d H:i:s');
                    
                    // Format date range (e.g., "December 4, 2025 - December 7, 2025")
                    $date_range = date('F j, Y', strtotime($order_info['delivery_date_start'])) . ' - ' . 
                                  date('F j, Y', strtotime($order_info['delivery_date_end']));
                    
                    // Get delivery worker contact if available
                    $worker_contact = '';
                    if (!empty($order_info['delivery_worker_contact'])) {
                        $worker_contact = $order_info['delivery_worker_contact'];
                    } elseif (!empty($order_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $order_info['delivery_notes'], $matches)) {
                        $worker_contact = trim($matches[1]);
                    }
                    
                    // Create notification for customer with link to select delivery date
                    $notification_title = "Select Your Delivery Date - Order #{$order_info['order_number']}";
                    $notification_message = "Your order #{$order_info['order_number']} is ready for delivery. Please select your preferred date and time from the available window: {$date_range}";
                    if ($worker_contact) {
                        $notification_message .= " Delivery worker contact: {$worker_contact}.";
                    }
                    $notification_link = base_url("customer/select-delivery-date.php?order_id=" . $order_id);
                    
                    $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) 
                                        VALUES (?, ?, ?, ?, ?, 'order', ?)";
                    $database->query($notification_sql, [
                        $order_info['customer_user_id'],
                        $order_info['customer_id'],
                        $notification_title,
                        $notification_message,
                        $notification_link,
                        $manila_time
                    ]);
                }
            }
            
            // Create notification for customer
            $order_sql = "SELECT o.customer_id, o.order_number, u.id as user_id 
                         FROM orders o 
                         JOIN customers c ON o.customer_id = c.id 
                         JOIN users u ON c.user_id = u.id 
                         WHERE o.id = ? AND o.supplier_id = ?";
            $order = $database->fetch($order_sql, [$order_id, $supplier_id]);
            
            if ($order) {
                // Define status labels for notifications
                $status_labels = [
                    'pending' => 'is pending',
                    'confirmed' => 'has been confirmed',
                    'preparing' => 'is being prepared',
                    'out_for_delivery' => 'is out for delivery',
                    'delivered' => 'has been delivered',
                    'cancelled' => 'has been cancelled'
                ];
                
                $status_label = $status_labels[$status] ?? 'status has been updated';
                
                // Use Manila timezone for timestamp
                date_default_timezone_set('Asia/Manila');
                $manila_time = date('Y-m-d H:i:s');
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                    VALUES (?, ?, ?, 'order', ?)";
                $notification_title = "Order #{$order['order_number']} Status Update";
                $notification_message = "Your order #{$order['order_number']} {$status_label}.";
                $database->query($notification_sql, [$order['user_id'], $notification_title, $notification_message, $manila_time]);
            }
            
            $_SESSION['success'] = 'Order status updated successfully';
            
            // Redirect to the appropriate status tab based on new status
            $redirect_status = '';
            if ($status === 'scheduled_for_delivery') {
                $redirect_status = 'scheduled_for_delivery';
            } elseif ($status === 'out_for_delivery') {
                $redirect_status = 'out_for_delivery';
            } elseif ($status === 'delivered') {
                $redirect_status = 'delivered';
            } elseif ($status === 'confirmed_and_paid') {
                $redirect_status = 'confirmed_and_paid';
            } elseif ($status === 'preparing') {
                $redirect_status = 'preparing';
            }
            
            redirect(base_url('supplier/orders.php' . ($redirect_status ? '?status=' . $redirect_status : '')));
            exit;
        } elseif ($action === 'set_eta') {
            // This action is now deprecated but kept for backward compatibility
            $order_id = intval($_POST['order_id']);
            $eta_time = sanitize_input($_POST['eta_time']);
            
            // Update the order with ETA time
            $sql = "UPDATE orders SET eta_time = ? WHERE id = ? AND supplier_id = ?";
            $database->query($sql, [$eta_time, $order_id, $supplier_id]);
            
            // Create notification for customer
            $order_sql = "SELECT o.customer_id, o.order_number, u.id as user_id 
                         FROM orders o 
                         JOIN customers c ON o.customer_id = c.id 
                         JOIN users u ON c.user_id = u.id 
                         WHERE o.id = ?";
            $order = $database->fetch($order_sql, [$order_id]);
            
            if ($order) {
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES (?, ?, ?, 'order')";
                $notification_title = "Delivery ETA for Order #{$order['order_number']}";
                $notification_message = "Your order #{$order['order_number']} is expected to arrive between {$eta_time}.";
                $database->query($notification_sql, [$order['user_id'], $notification_title, $notification_message]);
                
                // Send ETA email notification
                $order_class = new Order($database);
                $order_details = $order_class->getOrderById($order_id);
                if ($order_details) {
                    $order_class->sendETAEmail($order_details, $eta_time);
                }
            }
            
            $_SESSION['success'] = 'ETA time set successfully and notification sent to customer';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    redirect(base_url('supplier/orders.php' . (!empty($_GET['status']) ? '?status=' . $_GET['status'] : '')));
}

$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Auto-fix start
// Auto-fix: Update orders that have been paid but status is still 'confirmed'
// This handles cases where webhook didn't fire or failed
try {
    // Fix 1: Check payments table
    $fix_sql1 = "UPDATE orders o 
                 INNER JOIN payments p ON o.id = p.order_id 
                 SET o.status = 'confirmed_and_paid', o.updated_at = NOW()
                 WHERE o.supplier_id = ? 
                 AND o.status = 'confirmed' 
                 AND p.status IN ('paid', 'completed')
                 AND p.order_id = o.id";
    $stmt1 = $database->query($fix_sql1, [$supplier_id]);
    $updated1 = $stmt1->rowCount();
    
    // Fix 2: Check xendit_invoices table (if payment was via Xendit and already marked PAID)
    $fix_sql2 = "UPDATE orders o 
                 INNER JOIN xendit_invoices xi ON o.id = xi.order_id 
                 SET o.status = 'confirmed_and_paid', o.updated_at = NOW()
                 WHERE o.supplier_id = ? 
                 AND o.status = 'confirmed' 
                 AND xi.xendit_status = 'PAID'
                 AND xi.order_id = o.id";
    $stmt2 = $database->query($fix_sql2, [$supplier_id]);
    $updated2 = $stmt2->rowCount();
    
    // Auto-fix logic removed as per user request to prevent interference with order workflow.
    // The Rider app and Supplier actions are now the sole source of truth for status updates.
    
    // Safety Sync REMOVED: Orders should follow natural workflow progression
    // Scheduled → Preparing → Out for Delivery → Review Proof (when rider submits) → Delivered
    // The rider/delivery-proof.php already updates status to pending_supplier_review correctly
    $updated_sync = 0; // Initialize for logging compatibility
    // Fix 4: Ensure orders with status preparing, out_for_delivery, delivered are not empty
    // This fixes any orders that might have been updated but status wasn't saved correctly
    $fix_sql4 = "UPDATE orders 
                 SET status = CASE 
                     WHEN status = '' AND delivery_date IS NOT NULL THEN 'scheduled_for_delivery'
                     WHEN status = '' THEN 'pending'
                     ELSE status
                 END, updated_at = NOW()
                 WHERE supplier_id = ? 
                 AND status = ''";
    $stmt4 = $database->query($fix_sql4, [$supplier_id]);
    $updated4 = $stmt4->rowCount();

    // Fix 5: Archive expired confirmed orders (payment deadline passed)
    // These should be moved to 'archived' status
    $fix_sql5 = "UPDATE orders 
                 SET status = 'archived', updated_at = NOW(), archived_at = NOW()
                 WHERE supplier_id = ? 
                 AND status = 'confirmed' 
                 AND payment_deadline IS NOT NULL 
                 AND payment_deadline < NOW()";
    $stmt5 = $database->query($fix_sql5, [$supplier_id]);
    $updated5 = $stmt5->rowCount();
    
    // Initialize variables for removed fixes to prevent warnings
    $updated1 = 0; $updated2 = 0; $updated3 = 0;
    
    if ($updated1 > 0 || $updated2 > 0 || $updated3 > 0 || $updated4 > 0 || $updated5 > 0 || $updated_sync > 0) {
        error_log("Auto-fix: Updated " . ($updated1 + $updated2 + $updated3 + $updated4 + $updated5 + $updated_sync) . " order(s) for supplier #{$supplier_id} (paid: {$updated1}+{$updated2}, delivery_date: {$updated3}, empty_status: {$updated4}, archived: {$updated5}, sync_proofs: {$updated_sync})");
    }
    
    // Fix 5: Check Xendit API directly for pending invoices (only if status filter is 'confirmed')
    // This bypasses webhook completely
    if ($status_filter === 'confirmed') {
        require_once __DIR__ . '/../includes/xendit_api_check.php';
        $sync_result = sync_supplier_orders_from_xendit($supplier_id);
        if ($sync_result['updated'] > 0) {
            error_log("Auto-sync from Xendit API: Updated {$sync_result['updated']} order(s) for supplier #{$supplier_id}");
        }
    }
} catch (Exception $e) {
    error_log("Auto-fix order status error: " . $e->getMessage());
}
// Auto-fix end

// Fix: Pass status filter as an array with 'status' key
$filters = [];
if ($status_filter) {
    $filters['status'] = $status_filter;
}

// Special handling for 'confirmed' status - exclude orders that have been paid
if ($status_filter === 'confirmed') {
    $filters['exclude_paid'] = true;
}

$orders = $supplier->getOrders($filters, $limit, $offset);
$total_orders = $supplier->getOrderCount($status_filter);
$total_pages = ceil($total_orders / $limit);

// PERFORMANCE OPTIMIZATION: Pre-load payment status and delivery window data for all orders
// This eliminates N+1 query problem (was running 3 queries per order, now just 2 total queries)
$order_ids = array_column($orders, 'id');
$payment_status_map = [];
$delivery_window_map = [];
$proofs_map = []; // Initialize to prevent undefined variable errors

if (!empty($order_ids)) {
    // Bulk fetch payment status for all orders at once
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $payment_sql = "SELECT DISTINCT order_id FROM payments WHERE order_id IN ($placeholders) AND status = 'completed'";
    $paid_orders = $database->fetchAll($payment_sql, $order_ids);
    foreach ($paid_orders as $paid) {
        $payment_status_map[$paid['order_id']] = true;
    }
    
    // Bulk fetch delivery window data for all orders at once
    $delivery_sql = "SELECT id, supplier_delivery_from, supplier_delivery_to, customer_selected_delivery_date, 
                            delivery_time_selected 
                     FROM orders WHERE id IN ($placeholders)";
    $delivery_data = $database->fetchAll($delivery_sql, $order_ids);
    foreach ($delivery_data as $delivery) {
        $delivery_window_map[$delivery['id']] = $delivery;
    }

    // Bulk fetch delivery proofs for relevant orders (get latest proof if multiple exist)
    $proofs_sql = "SELECT dp.order_id, dp.rider_name, dp.rider_phone, dp.image_path, dp.submitted_at 
                   FROM delivery_proofs dp
                   INNER JOIN (
                       SELECT order_id, MAX(submitted_at) as max_submitted
                       FROM delivery_proofs
                       WHERE order_id IN ($placeholders)
                       GROUP BY order_id
                   ) latest ON dp.order_id = latest.order_id AND dp.submitted_at = latest.max_submitted
                   WHERE dp.order_id IN ($placeholders)";
    $proofs_data = $database->fetchAll($proofs_sql, array_merge($order_ids, $order_ids)); // Pass order_ids twice for both subquery and main query
    foreach ($proofs_data as $proof) {
        $proofs_map[$proof['order_id']] = $proof;
    }
}

$page_title = 'Order Management';
include '../includes/supplier_header.php';
?>
<script src="<?php echo base_url('assets/js/countdown.js'); ?>"></script>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="orders-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold">Order Management</h3>
                <div>
                    <?php if ($status_filter === 'confirmed'): ?>
                        <form method="POST" action="orders.php?status=confirmed" style="display:inline;" onsubmit="return confirm('Sync payment status from Xendit API? This will check all pending orders.');">
                            <input type="hidden" name="action" value="sync_xendit">
                            <button type="submit" class="btn btn-warning btn-sm me-2">
                                <i class="fas fa-sync-alt me-1"></i> Sync Payment Status
                            </button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshOrders()">
                    <i class="fas fa-sync me-2"></i>Refresh
                </button>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tabs -->
        <section class="orders-tabs mb-4">
            <ul class="nav nav-tabs">
                <?php
                $tabs = [
                    '' => 'All Orders',
                    'pending' => 'Pending Confirmation',
                    'confirmed' => 'Awaiting Payment',
                    'confirmed_and_paid' => 'Paid & Processing',
                    'scheduled_for_delivery' => 'Scheduled',
                    'preparing' => 'Preparing',
                    'out_for_delivery' => 'Out for Delivery',
                    'pending_supplier_review' => 'Pending Review',
                    // 'pending_admin_verification' => 'Pending Admin', // Removed as per request
                    'delivered' => 'Delivered'
                ];
                foreach ($tabs as $status => $label):
                    $is_active = $status_filter === $status || ($status === '' && $status_filter === '');
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" href="orders.php<?php echo $status ? '?status=' . $status : ''; ?>">
                            <?php echo $label; ?>
                            <span class="badge bg-secondary ms-2"><?php echo $supplier->getOrderCount($status ?: null); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Orders Table -->
        <section class="orders-table">
            <?php if (empty($orders)): ?>
                <div class="card border-0 shadow-sm bg-white text-center p-5">
                    <h3>No <?php echo $status_filter ?: 'orders'; ?> found</h3>
                    <p class="text-muted">Orders from customers will appear here once received.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="table-light">
                                        <th class="py-3">Order #</th>
                                        <th class="py-3">Customer</th>
                                        <th class="py-3">Amount</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3">Order Date</th>
                                        <th class="py-3">Delivery Date</th>
                                        <th class="py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): 
                                        // Use pre-loaded payment status (no query needed)
                                        $is_paid = isset($payment_status_map[$order['id']]);
                                        
                                        // Normalize status - if empty but has delivery_date, treat as scheduled_for_delivery
                                        if (empty($order['status']) && !empty($order['delivery_date'])) {
                                            $order['status'] = 'scheduled_for_delivery';
                                        }
                                    ?>
                                        <?php 
                                        $display_amount = isset($order['net_amount']) && $order['net_amount'] > 0
                                            ? (float)$order['net_amount']
                                            : (float)$order['total_amount'];
                                        ?>
                                        <tr>
                                            <td><?php echo $order['order_number']; ?></td>
                                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                            <td>₱<?php echo number_format($display_amount, 2); ?></td>
                                            <td>
                                                <?php 
                                                // Determine status display - handle empty status or delivery_date
                                                $display_status = $order['status'];
                                                if (empty($display_status) && !empty($order['delivery_date'])) {
                                                    $display_status = 'scheduled_for_delivery';
                                                } elseif (empty($display_status)) {
                                                    $display_status = 'pending';
                                                }
                                                
                                                // Status badge color mapping
                                                $status_color = match($display_status) {
                                                    'pending' => 'secondary',
                                                    'confirmed' => 'warning',
                                                    'confirmed_and_paid' => 'primary',
                                                    'scheduled_for_delivery' => 'info',
                                                    'preparing' => 'purple-custom',
                                                    'out_for_delivery' => 'warning',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                                
                                                // Status display text
                                                $status_text = match($display_status) {
                                                    'scheduled_for_delivery' => 'Scheduled for Delivery',
                                                    'confirmed_and_paid' => 'Paid & Processing',
                                                    'out_for_delivery' => 'Out for Delivery',
                                                    default => ucfirst(str_replace('_', ' ', $display_status))
                                                };
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo htmlspecialchars($status_text); ?>
                                                </span>
                                                <?php 
                                                // Add Overdue badge if out_for_delivery > 24 hours
                                                if ($display_status === 'out_for_delivery') {
                                                    $hours_elapsed = (time() - strtotime($order['updated_at'])) / 3600;
                                                    if ($hours_elapsed > 24) {
                                                        echo '<span class="badge bg-danger ms-1">Overdue</span>';
                                                    }
                                                }
                                                ?>
                                                <?php if ($order['status'] === 'pending' && !empty($order['supplier_accept_deadline']) && empty($order['supplier_accepted_at'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">Accept by:</small>
                                                        <span class="countdown-timer" data-deadline="<?php echo htmlspecialchars($order['supplier_accept_deadline']); ?>"></span>
                                                    </div>
                                                <?php elseif ($order['status'] === 'confirmed' && !empty($order['payment_deadline'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">Payment due:</small>
                                                        <span class="countdown-timer" data-deadline="<?php echo htmlspecialchars($order['payment_deadline']); ?>"></span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_date($order['created_at']); ?></td>
                                            <td>
                                                <?php if (!empty($order['delivery_date'])): ?>
                                                    <span class="text-primary fw-bold"><?php echo format_date($order['delivery_date']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                    <?php if ($order['status'] === 'pending' && !$is_paid): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="acceptOrder(<?php echo $order['id']; ?>)">
                                                            Accept
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="rejectOrder(<?php echo $order['id']; ?>)">
                                                            Reject
                                                        </button>
                                                    <?php elseif ($order['status'] === 'confirmed' && !$is_paid): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="cancelOrderBeforePayment(<?php echo $order['id']; ?>)">
                                                            Cancel
                                                        </button>
                                                    <?php elseif ($order['status'] === 'confirmed_and_paid'): ?>
                                                        <?php 
                                                        // Use pre-loaded delivery window data (no query needed)
                                                        $delivery_window_check = $delivery_window_map[$order['id']] ?? [];
                                                        
                                                        if (empty($delivery_window_check['supplier_delivery_from'])): ?>
                                                            <!-- Supplier needs to set start date -->
                                                            <button class="btn btn-sm btn-outline-primary" onclick="showSetStartDateModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number'], ENT_QUOTES); ?>')">
                                                                <i class="fas fa-calendar-alt me-1"></i>Set Start Date
                                                            </button>
                                                        <?php elseif (empty($delivery_window_check['customer_selected_delivery_date'])): ?>
                                                            <!-- Waiting for customer to choose date -->
                                                            <span class="badge bg-info">Waiting for customer to choose date</span>
                                                            <small class="d-block text-muted mt-1">
                                                                Window: <?php echo date('M d', strtotime($delivery_window_check['supplier_delivery_from'])); ?> - 
                                                                <?php echo date('M d, Y', strtotime($delivery_window_check['supplier_delivery_to'])); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <!-- Customer has selected date, supplier can proceed -->
                                                            <button class="btn btn-sm btn-outline-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')">
                                                                <i class="fas fa-check me-1"></i>Mark as Preparing
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php elseif ($order['status'] === 'scheduled_for_delivery'): ?>
                                                        <?php 
                                                        // Use pre-loaded delivery window data (no query needed)
                                                        $delivery_check = $delivery_window_map[$order['id']] ?? [];
                                                        
                                                        // Check both columns for backward compatibility OR manual supplier schedule
                                                        $has_selected_date = !empty($delivery_check['customer_selected_delivery_date']) || 
                                                                           !empty($delivery_check['delivery_time_selected']) || 
                                                                           !empty($order['delivery_date']);
                                                        
                                                        if (!$has_selected_date): ?>
                                                            <span class="badge bg-info">Waiting for customer to set date</span>
                                                            <?php if (!empty($delivery_check['supplier_delivery_from']) && !empty($delivery_check['supplier_delivery_to'])): ?>
                                                                <small class="d-block text-muted mt-1">
                                                                    Window: <?php echo date('M d', strtotime($delivery_check['supplier_delivery_from'])); ?> - 
                                                                    <?php echo date('M d, Y', strtotime($delivery_check['supplier_delivery_to'])); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')">
                                                                <i class="fas fa-check me-1"></i>Mark as Preparing
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                                        <!-- "Out for Delivery" now triggers the Rider Assignment Modal directly -->
                                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#assignRiderModal-<?= (int)$order['id'] ?>">
                                                            <i class="fas fa-truck me-1"></i>Out for Delivery
                                                        </button>

                                                        <!-- Assign Rider Modal -->
                                                        <div class="modal fade" id="assignRiderModal-<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog modal-sm">
                                                                <div class="modal-content">
                                                                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Assign Rider — Order #<?= htmlspecialchars($order['order_number']); ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <input type="hidden" name="action" value="assign_rider">
                                                                            <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                                                                            <p class="small text-muted mb-3">
                                                                                Assigning a rider will send them an SMS with a proof-of-delivery link.
                                                                                The order status will automatically change to <strong>Out for Delivery</strong>.
                                                                            </p>
                                                                            <div class="mb-2">
                                                                                <label class="form-label">Rider name</label>
                                                                                <input name="rider_name" type="text" class="form-control" required maxlength="100" placeholder="e.g. Juan dela Cruz" />
                                                                            </div>
                                                                            <div class="mb-2">
                                                                                <label class="form-label">Rider phone</label>
                                                                                <input name="rider_phone" type="text" class="form-control" required maxlength="30" placeholder="e.g. 09123456789" />
                                                                                <div class="form-text">Must be a valid PH mobile number.</div>
                                                                            </div>
                                                                            <?php if (!empty($_SESSION['csrf_token'])): ?>
                                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-primary btn-sm">Assign & Send SMS</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>



                                                    <?php elseif ($order['status'] === 'out_for_delivery'): ?>
                                                        <span class="badge bg-warning ms-2">Rider Delivering...</span>
                                                        <button class="btn btn-sm btn-outline-secondary ms-1" onclick="resendRiderSMS(<?php echo $order['id']; ?>)">
                                                            <i class="fas fa-paper-plane me-1"></i>Resend Link
                                                        </button>
                                                    <?php elseif ($order['status'] === 'pending_supplier_review'): ?>
                                                        <button class="btn btn-sm btn-info text-white" onclick="verifyProof(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                                                            <i class="fas fa-eye me-1"></i>View Proof
                                                        </button>
                                                        <!-- Hidden Proof Details for JS -->
                                                        <?php $proof = $proofs_map[$order['id']] ?? null; ?>
                                                        <?php if ($proof): ?>
                                                            <div id="proof-data-<?php echo $order['id']; ?>" class="d-none"
                                                                 data-rider="<?php echo htmlspecialchars($proof['rider_name']); ?>"
                                                                 data-img="<?php echo base_url(htmlspecialchars($proof['image_path'])); ?>"
                                                                 data-time="<?php echo htmlspecialchars($proof['submitted_at']); ?>">
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php elseif ($order['status'] === 'pending_admin_verification'): ?>
                                                        <span class="badge bg-info">Waiting for Admin</span>
                                                    <?php elseif ($order['status'] === 'delivered'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </section>

        <!-- Delivery Modal -->
        <div class="modal fade" id="deliveryModal" tabindex="-1" aria-labelledby="deliveryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deliveryModalLabel">Set Delivery Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="deliveryForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" id="delivery_status" value="scheduled_for_delivery">
                        <input type="hidden" name="order_id" id="delivery_order_id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Order Information</label>
                                <div id="delivery_order_info" class="border p-3 rounded"></div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_date" class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" required>
                                <small class="form-text text-muted">Select the expected delivery date. A 3-day delivery window will be automatically calculated (e.g., if you select December 4, the customer will receive a range of December 4-7 to choose from)</small>
                            </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_worker" class="form-label">Delivery Worker Name</label>
                                <input type="text" class="form-control" id="delivery_worker" name="delivery_worker" placeholder="Enter delivery worker name" required>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_worker_contact" class="form-label">Delivery Worker Contact Number</label>
                                <input type="tel" class="form-control" id="delivery_worker_contact" name="delivery_worker_contact" placeholder="Enter contact number (e.g., 09123456789)" required>
                                <small class="form-text text-muted">This will be included in SMS and email notifications to the customer</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Set Delivery</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Verify Proof Modal -->
        <div class="modal fade" id="verifyProofModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">View Delivery Proof - Order #<span id="verify_order_number"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="verify_image" src="" class="img-fluid rounded mb-3 border shadow-sm" style="max-height: 300px;">
                        
                        <div class="text-start bg-light p-3 rounded mb-3">
                            <p class="mb-1"><strong>Rider:</strong> <span id="verify_rider_name"></span></p>
                            <p class="mb-0"><strong>Time:</strong> <span id="verify_timestamp"></span></p>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .alert {
        border-radius: 0.5rem;
    }
    .nav-tabs .nav-link {
        color: #555;
        border: none;
        border-bottom: 2px solid transparent;
    }
    .nav-tabs .nav-link.active {
        color: #000;
        font-weight: 600;
        border-bottom: 2px solid #444;
    }
    .badge {
        padding: 0.5em 0.75em;
    }
    .badge.bg-purple-custom {
        background-color: #6f42c1 !important;
        color: white;
    }
    /* Supplier page footer alignment */
    .main-content footer,
    .main-content .footer {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
    }
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .btn-group .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .nav-tabs .nav-link {
            font-size: 0.85rem;
            padding: 0.5rem;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function refreshOrders() {
    location.reload();
}

function resendRiderSMS(orderId) {
    if (confirm("Resend the SMS link to the assigned rider? This will generate a new link and expire the old one.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="resend_rider_sms">
            <input type="hidden" name="order_id" value="${orderId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateOrderStatus(orderId, status) {
    let messages = {
        preparing: "Mark order as Preparing?",
        out_for_delivery: "Mark as Out for Delivery?",
        delivered: "Mark as Delivered?"
    };
    if (status === 'preparing' || confirm(messages[status] || "Update order status?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateOrderStatusWithETA(orderId, status) {
    if (confirm("Mark this order as Delivered?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showSetStartDateModal(orderId, orderNumber) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Delivery Start Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="setStartDateForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="set_delivery_start_date">
                        <input type="hidden" name="order_id" value="${orderId}">
                        <p>Order: <strong>${orderNumber}</strong></p>
                        <p class="text-muted">Select a start date. The system will create a 3-day delivery window (start date to start date + 2 days).</p>
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Set Start Date</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('hidden.bs.modal', () => modal.remove());
}

// Alias function for consistency
function markAsDelivered(orderId) {
    updateOrderStatus(orderId, 'delivered');
}

function acceptOrder(orderId) {
    if (confirm("Are you sure you want to accept this order? The customer will be notified to proceed with payment.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'confirm_orders.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="confirm">
            <input type="hidden" name="order_id" value="${orderId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectOrder(orderId) {
    if (confirm("Are you sure you want to reject this order? This cannot be undone.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'confirm_orders.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="order_id" value="${orderId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cancelOrderBeforePayment(orderId) {
    if (confirm("Are you sure you want to cancel this order? The customer has not paid yet.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'confirm_orders.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="order_id" value="${orderId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function verifyProof(orderId, orderNumber) {
    const dataDiv = document.getElementById('proof-data-' + orderId);
    if (!dataDiv) {
        alert("Proof data not found. Please refresh.");
        return;
    }
    
    // Read-only modal population
    document.getElementById('verify_order_number').textContent = orderNumber;
    document.getElementById('verify_rider_name').textContent = dataDiv.dataset.rider;
    document.getElementById('verify_timestamp').textContent = dataDiv.dataset.time;
    document.getElementById('verify_image').src = dataDiv.dataset.img; 
    
    new bootstrap.Modal(document.getElementById('verifyProofModal')).show();
}

function showDeliveryModal(orderId, orderNumber, customerName, totalAmount) {
    document.getElementById('delivery_order_id').value = orderId;
    document.getElementById('delivery_order_info').innerHTML = `
        <strong>Order #${orderNumber}</strong><br>
        Customer: ${customerName}<br>
        Amount: ₱${parseFloat(totalAmount).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('delivery_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('delivery_worker_contact').value = '';
    // Status is already set to scheduled_for_delivery in the form - backend will handle the transition from confirmed_and_paid
    document.getElementById('delivery_status').value = 'scheduled_for_delivery';
    new bootstrap.Modal(document.getElementById('deliveryModal')).show();
}

if (window.location.search.includes('status=pending') || window.location.search === '') {
    setInterval(() => {
        if (!document.querySelector('.modal.show')) refreshOrders();
    }, 30000);
}

document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }
});
</script>
</body>
</html>
</html>