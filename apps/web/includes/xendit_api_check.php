<?php
/**
 * Xendit API Direct Check
 * Checks invoice status directly from Xendit API (bypasses webhook)
 */

require_once __DIR__ . '/../config/xendit.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Order.php';

/**
 * Check Xendit invoice status via API
 */
function check_xendit_invoice_status($invoice_id) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.xendit.co/v2/invoices/{$invoice_id}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(XENDIT_API_KEY . ':'),
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        error_log("Xendit API check failed for invoice {$invoice_id}: HTTP {$http_code} - {$curl_error}");
        return false;
    }
    
    $result = json_decode($response, true);
    return $result;
}

/**
 * Sync order status from Xendit for a specific order
 */
function sync_order_from_xendit($order_id) {
    global $database;
    
    try {
        // Find Xendit invoice for this order
        $invoice = $database->fetch(
            "SELECT * FROM xendit_invoices WHERE order_id = ? ORDER BY created_at DESC LIMIT 1",
            [$order_id]
        );
        
        if (!$invoice || empty($invoice['invoice_id'])) {
            return ['success' => false, 'message' => 'No Xendit invoice found for this order'];
        }
        
        // Check if already processed
        if ($invoice['xendit_status'] === 'PAID') {
            // Double-check order status
            $order = $database->fetch("SELECT status FROM orders WHERE id = ?", [$order_id]);
            if ($order && $order['status'] === 'confirmed') {
                // Status mismatch - fix it
                require_once __DIR__ . '/../classes/Order.php';
                $order_obj = new Order($database);
                $payment_data = [
                    'amount' => $invoice['amount'],
                    'transaction_id' => $invoice['invoice_id'],
                    'payment_method' => 'xendit',
                    'currency' => 'PHP'
                ];
                $order_obj->processPaymentSuccess($order_id, $payment_data);
                return ['success' => true, 'message' => 'Order status updated to confirmed_and_paid'];
            }
            return ['success' => true, 'message' => 'Order already processed'];
        }
        
        // Check Xendit API for current status
        $invoice_data = check_xendit_invoice_status($invoice['invoice_id']);
        
        if (!$invoice_data) {
            return ['success' => false, 'message' => 'Failed to check invoice status from Xendit'];
        }
        
        $xendit_status = strtoupper($invoice_data['status'] ?? '');
        
        // Update invoice status in database
        $database->query(
            "UPDATE xendit_invoices SET xendit_status = ?, updated_at = NOW() WHERE invoice_id = ?",
            [$xendit_status, $invoice['invoice_id']]
        );
        
        // If paid, process the payment
        if ($xendit_status === 'PAID') {
            $order = $database->fetch("SELECT status FROM orders WHERE id = ?", [$order_id]);
            
            if ($order && $order['status'] === 'confirmed') {
                require_once __DIR__ . '/../classes/Order.php';
                $order_obj = new Order($database);
                $payment_data = [
                    'amount' => $invoice_data['amount'] / 100, // Convert from cents to PHP
                    'transaction_id' => $invoice['invoice_id'],
                    'payment_method' => 'xendit',
                    'payer_email' => $invoice_data['payer_email'] ?? null,
                    'currency' => 'PHP'
                ];
                $order_obj->processPaymentSuccess($order_id, $payment_data);
                return ['success' => true, 'message' => 'Payment confirmed and order status updated'];
            }
        }
        
        return ['success' => true, 'message' => "Invoice status: {$xendit_status}"];
        
    } catch (Exception $e) {
        error_log("Sync order from Xendit error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Sync all pending orders for a supplier
 */
function sync_supplier_orders_from_xendit($supplier_id) {
    global $database;
    
    try {
        // Get ALL confirmed orders (including those with PAID invoices but wrong order status)
        $orders = $database->fetchAll(
            "SELECT o.id, o.order_number, o.status as order_status, xi.invoice_id, xi.xendit_status, xi.amount
             FROM orders o
             LEFT JOIN xendit_invoices xi ON o.id = xi.order_id
             WHERE o.supplier_id = ? 
             AND o.status = 'confirmed'
             ORDER BY o.created_at DESC",
            [$supplier_id]
        );
        
        $results = ['updated' => 0, 'checked' => 0, 'errors' => [], 'details' => []];
        
        foreach ($orders as $order) {
            $results['checked']++;
            
            // If invoice is already marked PAID but order is still confirmed, fix it immediately
            if (!empty($order['invoice_id']) && $order['xendit_status'] === 'PAID' && $order['order_status'] === 'confirmed') {
                try {
                    require_once __DIR__ . '/../classes/Order.php';
                    $order_obj = new Order($database);
                    $payment_data = [
                        'amount' => $order['amount'] ?? 0,
                        'transaction_id' => $order['invoice_id'],
                        'payment_method' => 'xendit',
                        'currency' => 'PHP'
                    ];
                    $order_obj->processPaymentSuccess($order['id'], $payment_data);
                    $results['updated']++;
                    $results['details'][] = "Order {$order['order_number']}: Fixed status mismatch (invoice PAID, order confirmed)";
                    continue;
                } catch (Exception $e) {
                    $results['errors'][] = "Order {$order['order_number']}: {$e->getMessage()}";
                    continue;
                }
            }
            
            // If no invoice, skip
            if (empty($order['invoice_id'])) {
                $results['details'][] = "Order {$order['order_number']}: No Xendit invoice found";
                continue;
            }
            
            // Check Xendit API for current status
            $result = sync_order_from_xendit($order['id']);
            if ($result['success'] && (strpos($result['message'], 'updated') !== false || strpos($result['message'], 'confirmed') !== false)) {
                $results['updated']++;
                $results['details'][] = "Order {$order['order_number']}: {$result['message']}";
            } elseif (!$result['success']) {
                $results['errors'][] = "Order {$order['order_number']}: {$result['message']}";
            } else {
                $results['details'][] = "Order {$order['order_number']}: {$result['message']}";
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Sync supplier orders error: " . $e->getMessage());
        return ['updated' => 0, 'checked' => 0, 'errors' => [$e->getMessage()], 'details' => []];
    }
}

