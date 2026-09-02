<?php

/**
 * Notification Handler
 * 
 * Handles customer notifications for order confirmations
 */

function getCustomerNotifications($customer_id, $database) {
    try {
        // Get unread notifications for the customer (using customer_id)
        $sql = "SELECT * FROM notifications 
                WHERE customer_id = ? AND is_read = 0 
                ORDER BY created_at DESC";
        return $database->fetchAll($sql, [$customer_id]);
    } catch (Exception $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsRead($notification_id, $database) {
    try {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        return $database->query($sql, [$notification_id]);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

function getNotificationRedirectUrl($notification, $database) {
    try {
        // Check if notification is about an order confirmation
        if ($notification['type'] === 'order' && strpos($notification['message'], 'confirmed') !== false) {
            // Extract order number from message
            if (preg_match('/Order #([A-Z0-9\-]+)/', $notification['message'], $matches)) {
                $order_number = $matches[1];
                
                // Get order ID by order number
                $sql = "SELECT id FROM orders WHERE order_number = ?";
                $order = $database->fetch($sql, [$order_number]);
                
                if ($order && $order['id']) {
                    // Check if order is awaiting customer payment
                    $sql = "SELECT status FROM orders WHERE id = ?";
                    $order_status = $database->fetch($sql, [$order['id']]);
                    
                    if ($order_status && $order_status['status'] === 'awaiting_customer_payment') {
                        return base_url("customer/pay_confirmed_order.php?order_id=" . $order['id']);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error processing notification redirect: " . $e->getMessage());
    }
    
    // Default to orders page
    return base_url("customer/orders.php");
}