<?php
/**
 * Cron job to handle automatic delivery confirmations
 * 
 * This script should be run every 5-10 minutes to:
 * 1. Auto-confirm deliveries after 30 minutes if proof of delivery exists and customer hasn't confirmed
 * 2. Send reminders to customers (48 hours, 72 hours) if they haven't confirmed
 * 3. Auto-confirm after 72 hours if proof of delivery exists
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Order.php';

try {
    $database = new Database();
    $order = new Order($database);
    
    date_default_timezone_set('Asia/Manila');
    $now = date('Y-m-d H:i:s');
    
    // 1. Auto-confirm deliveries after 30 minutes if proof exists and customer hasn't confirmed
    $sql = "SELECT pod.order_id, pod.submitted_at, pod.auto_confirmed_at, o.customer_id, o.order_number, o.total_amount, o.supplier_id
            FROM proof_of_delivery pod
            JOIN orders o ON pod.order_id = o.id
            WHERE o.status = 'out_for_delivery'
            AND o.customer_confirmed_delivery = FALSE
            AND pod.auto_confirmed_at IS NOT NULL
            AND pod.auto_confirmed_at <= ?
            AND NOT EXISTS (
                SELECT 1 FROM orders o2 
                WHERE o2.id = pod.order_id 
                AND o2.status = 'delivered'
            )";
    $auto_confirm_orders = $database->fetchAll($sql, [$now]);
    
    foreach ($auto_confirm_orders as $order_data) {
        try {
            // Mark as auto-confirmed first
            $update_confirmed_sql = "UPDATE orders SET 
                                    auto_confirmed_delivery = TRUE,
                                    auto_confirmed_at = ?,
                                    updated_at = ?
                                    WHERE id = ?";
            $database->query($update_confirmed_sql, [$now, $now, $order_data['order_id']]);
            
            // Now update status to delivered (Order class will allow this since auto_confirmed_delivery is set)
            $order_obj = new Order($database);
            $order_obj->updateOrderStatus($order_data['order_id'], 'delivered');
            
            // Earnings are automatically calculated from delivered orders
            // No need to manually update earnings - the system calculates from order_items
            
            // Create notification for customer
            $customer_sql = "SELECT user_id FROM customers WHERE id = ?";
            $customer = $database->fetch($customer_sql, [$order_data['customer_id']]);
            
            if ($customer) {
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                    VALUES (?, ?, ?, 'order', ?)";
                $notification_title = "Order Auto-Confirmed - Order #{$order_data['order_number']}";
                $notification_message = "Your order #{$order_data['order_number']} has been automatically confirmed after 30 minutes. Thank you for your order!";
                $database->query($notification_sql, [$customer['user_id'], $notification_title, $notification_message, $now]);
            }
            
            echo "Order #{$order_data['order_id']} auto-confirmed after 30 minutes.\n";
        } catch (Exception $e) {
            echo "Failed to auto-confirm order #{$order_data['order_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Send reminders to customers who haven't confirmed (48 and 72 hours)
    $reminder_sql = "SELECT o.id, o.order_number, o.customer_id, pod.submitted_at,
                     COUNT(dcr.id) as reminder_count
                     FROM orders o
                     JOIN proof_of_delivery pod ON o.id = pod.order_id
                     LEFT JOIN delivery_confirmation_reminders dcr ON o.id = dcr.order_id
                     WHERE o.status = 'out_for_delivery'
                     AND o.customer_confirmed_delivery = FALSE
                     AND o.auto_confirmed_delivery = FALSE
                     AND pod.submitted_at IS NOT NULL
                     GROUP BY o.id
                     HAVING (
                         (reminder_count = 0 AND pod.submitted_at <= DATE_SUB(?, INTERVAL 48 HOUR))
                         OR (reminder_count = 1 AND pod.submitted_at <= DATE_SUB(?, INTERVAL 72 HOUR))
                     )";
    $reminder_orders = $database->fetchAll($reminder_sql, [$now, $now]);
    
    foreach ($reminder_orders as $order_data) {
        try {
            $hours_since_submission = (strtotime($now) - strtotime($order_data['submitted_at'])) / 3600;
            $reminder_type = $order_data['reminder_count'] == 0 ? 'first' : 'second';
            
            // Get customer info
            $customer_sql = "SELECT user_id FROM customers WHERE id = ?";
            $customer = $database->fetch($customer_sql, [$order_data['customer_id']]);
            
            if ($customer) {
                // Create reminder notification
                $notification_title = "Reminder: Confirm Order Receipt - Order #{$order_data['order_number']}";
                $notification_message = "Please confirm receipt of your order #{$order_data['order_number']}. " . 
                    ($reminder_type === 'first' ? 
                        "If you don't confirm within " . ORDER_TIMEOUT_LABEL . ", the order will be automatically confirmed." :
                        "This is your final reminder. The order will be automatically confirmed soon.");
                $notification_link = base_url("customer/confirm-delivery.php?order_id=" . $order_data['id']);
                
                $notification_sql = "INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                                    VALUES (?, ?, ?, ?, 'order', ?)";
                $database->query($notification_sql, [
                    $customer['user_id'], 
                    $notification_title, 
                    $notification_message, 
                    $notification_link,
                    $now
                ]);
                
                // Record reminder
                $reminder_sql = "INSERT INTO delivery_confirmation_reminders (order_id, reminder_sent_at, reminder_type) 
                               VALUES (?, ?, ?)";
                $database->query($reminder_sql, [$order_data['id'], $now, $reminder_type]);
                
                echo "Reminder sent for order #{$order_data['id']} ({$reminder_type} reminder).\n";
            }
        } catch (Exception $e) {
            echo "Failed to send reminder for order #{$order_data['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Auto-confirm after 72 hours if proof exists and customer still hasn't confirmed
    $final_auto_confirm_sql = "SELECT pod.order_id, o.customer_id, o.order_number, o.total_amount, o.supplier_id
                              FROM proof_of_delivery pod
                              JOIN orders o ON pod.order_id = o.id
                              WHERE o.status = 'out_for_delivery'
                              AND o.customer_confirmed_delivery = FALSE
                              AND o.auto_confirmed_delivery = FALSE
                              AND pod.submitted_at <= DATE_SUB(?, INTERVAL 72 HOUR)
                              AND EXISTS (
                                  SELECT 1 FROM delivery_confirmation_reminders dcr 
                                  WHERE dcr.order_id = o.id 
                                  AND dcr.reminder_type = 'second'
                              )";
    $final_auto_confirm = $database->fetchAll($final_auto_confirm_sql, [$now]);
    
    foreach ($final_auto_confirm as $order_data) {
        try {
            // Mark as auto-confirmed first
            $update_confirmed_sql = "UPDATE orders SET 
                                    auto_confirmed_delivery = TRUE,
                                    auto_confirmed_at = ?,
                                    updated_at = ?
                                    WHERE id = ?";
            $database->query($update_confirmed_sql, [$now, $now, $order_data['order_id']]);
            
            // Now update status to delivered (Order class will allow this since auto_confirmed_delivery is set)
            $order_obj = new Order($database);
            $order_obj->updateOrderStatus($order_data['order_id'], 'delivered');
            
            // Earnings are automatically calculated from delivered orders
            // No need to manually update earnings - the system calculates from order_items
            
            // Create notification for customer
            $customer_sql = "SELECT user_id FROM customers WHERE id = ?";
            $customer = $database->fetch($customer_sql, [$order_data['customer_id']]);
            
            if ($customer) {
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                    VALUES (?, ?, ?, 'order', ?)";
                $notification_title = "Order Auto-Confirmed - Order #{$order_data['order_number']}";
                $notification_message = "Your order #{$order_data['order_number']} has been automatically confirmed after 72 hours with proof of delivery. Thank you for your order!";
                $database->query($notification_sql, [$customer['user_id'], $notification_title, $notification_message, $now]);
            }
            
            echo "Order #{$order_data['order_id']} auto-confirmed after 72 hours.\n";
        } catch (Exception $e) {
            echo "Failed to auto-confirm order #{$order_data['order_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Auto-confirmation cron job completed successfully.\n";
    
} catch (Exception $e) {
    echo "Cron job failed: " . $e->getMessage() . "\n";
}

