<?php
/**
 * Cron job to handle automatic order cancellations
 * 
 * This script should be run every hour to check for:
 * 1. Orders pending supplier confirmation for more than " . ORDER_TIMEOUT_LABEL . "
 * 2. Orders awaiting customer payment for more than " . ORDER_TIMEOUT_LABEL . "
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Order.php';

function restore_stock_for_order($database, $order_id) {
    $items = $database->fetchAll("SELECT inventory_id, quantity FROM order_items WHERE order_id = ?", [$order_id]);
    foreach ($items as $item) {
        $database->query(
            "UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE id = ?",
            [$item['quantity'], $item['inventory_id']]
        );
    }
}

try {
    $database = new Database();
    $order = new Order($database);
    
    // Handle orders pending supplier confirmation - expired deadline
    $sql = "SELECT o.id, o.order_number, o.customer_id, o.supplier_id, c.user_id as customer_user_id, s.user_id as supplier_user_id
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN suppliers s ON o.supplier_id = s.id
            WHERE o.status = 'pending' 
            AND o.supplier_accept_deadline IS NOT NULL
            AND o.supplier_accept_deadline < NOW()
            AND o.supplier_accepted_at IS NULL";
    $orders_to_cancel = $database->fetchAll($sql);
    
    foreach ($orders_to_cancel as $order_row) {
        try {
            // ensured stock is restored on cancellation
            restore_stock_for_order($database, $order_row['id']);
            $database->query(
                "UPDATE orders SET status = 'archived', cancelled_at = NOW(), archived_at = NOW(), updated_at = NOW(), cancellation_reason = 'Auto-cancelled: supplier did not accept in time' WHERE id = ?",
                [$order_row['id']]
            );
            $notification_title = "Order Cancelled - Supplier Did Not Respond";
            $notification_message = "Your order #{$order_row['order_number']} has been automatically cancelled because the supplier did not respond within " . ORDER_TIMEOUT_LABEL . ".";
            $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, type, created_at) 
                               VALUES (?, ?, ?, ?, 'order', NOW())";
            $database->query($notification_sql, [$order_row['customer_user_id'], $order_row['customer_id'], $notification_title, $notification_message]);
            $supplier_note_title = "Order #{$order_row['order_number']} Cancelled (Timeout)";
            $supplier_note_msg = "Order auto-cancelled because supplier acceptance window expired.";
            $database->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'order', NOW())", [$order_row['supplier_user_id'], $supplier_note_title, $supplier_note_msg]);
            echo "Order #{$order_row['id']} cancelled and archived due to supplier non-response.\n";
        } catch (Exception $e) {
            echo "Failed to cancel order #{$order_row['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    // Handle orders awaiting customer payment - expired deadline
    $sql = "SELECT o.id, o.order_number, o.customer_id, o.supplier_id, c.user_id as customer_user_id, s.user_id as supplier_user_id
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN suppliers s ON o.supplier_id = s.id
            WHERE o.status IN ('confirmed','awaiting_payment') 
            AND o.payment_deadline IS NOT NULL
            AND o.payment_deadline < NOW()
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.order_id = o.id 
                AND p.status IN ('paid', 'completed')
            )
            AND NOT EXISTS (
                SELECT 1 FROM xendit_invoices xi 
                WHERE xi.order_id = o.id 
                AND xi.xendit_status = 'PAID'
            )";
    $orders_to_archive = $database->fetchAll($sql);
    
    foreach ($orders_to_archive as $order_row) {
        try {
            // ensured stock is restored on cancellation
            restore_stock_for_order($database, $order_row['id']);
            $database->query(
                "UPDATE orders SET status = 'archived', cancelled_at = NOW(), archived_at = NOW(), updated_at = NOW(), cancellation_reason = 'Auto-cancelled: payment deadline expired' WHERE id = ?",
                [$order_row['id']]
            );
            $notification_title = "Order Cancelled - Payment Not Received";
            $notification_message = "Your order #{$order_row['order_number']} has been automatically cancelled because payment was not received within " . ORDER_TIMEOUT_LABEL . ".";
            $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, type, created_at) 
                               VALUES (?, ?, ?, ?, 'order', NOW())";
            $database->query($notification_sql, [$order_row['customer_user_id'], $order_row['customer_id'], $notification_title, $notification_message]);
            
            $supplier_title = "Order Cancelled - Customer Payment Not Received";
            $supplier_message = "Order #{$order_row['order_number']} has been automatically cancelled because the customer did not pay within " . ORDER_TIMEOUT_LABEL . ".";
            $database->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'order', NOW())", [$order_row['supplier_user_id'], $supplier_title, $supplier_message]);
            
            echo "Order #{$order_row['id']} archived and stock restored due to customer non-payment.\n";
        } catch (Exception $e) {
            echo "Failed to archive order #{$order_row['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Cron job completed successfully.\n";
    
} catch (Exception $e) {
    echo "Cron job failed: " . $e->getMessage() . "\n";
}