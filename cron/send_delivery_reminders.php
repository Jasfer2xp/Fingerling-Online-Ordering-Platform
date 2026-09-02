<?php
/**
 * Cron Job: Send Delivery Confirmation Reminders
 * 
 * This script should run every hour to check for orders that:
 * 1. Are in 'out_for_delivery' status
 * 2. Have been in that status for 24+ hours
 * 3. Haven't had a reminder sent yet
 * 
 * Setup: Add to crontab to run hourly:
 * 0 * * * * /usr/bin/php /path/to/capstone/cron/send_delivery_reminders.php
 */

require_once __DIR__ . '/../config/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

// Find orders that need reminders
$sql = "SELECT o.id, o.order_number, o.updated_at, o.customer_id,
               c.first_name, c.last_name, c.contact_number,
               u.id as user_id, u.email
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE o.status = 'out_for_delivery'
        AND TIMESTAMPDIFF(HOUR, o.updated_at, NOW()) >= 24
        AND (o.reminder_sent_at IS NULL OR o.reminder_sent_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))";

$orders = $database->fetchAll($sql, []);

$sent_count = 0;

foreach ($orders as $order) {
    try {
        // Create notification
        $title = "Confirm Order Receipt - Order #{$order['order_number']}";
        $message = "Your order has been delivered. The rider hasn't uploaded proof yet. Please confirm if you received your order.";
        $link = "/customer/confirm-delivery.php?order_id=" . $order['id'];
        
        $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'order', ?)";
        $database->query($notification_sql, [
            $order['user_id'],
            $order['customer_id'],
            $title,
            $message,
            $link,
            $now
        ]);
        
        // Send SMS if phone number exists
        if (!empty($order['contact_number']) && function_exists('sendSemaphoreSMS')) {
            require_once __DIR__ . '/../config/sms.php';
            $sms_message = "Your order #{$order['order_number']} has been delivered. Please confirm receipt: " . base_url($link);
            sendSemaphoreSMS($order['id'], $order['contact_number'], $sms_message);
        }
        
        // Send Email
        if (!empty($order['email']) && function_exists('send_app_email')) {
            require_once __DIR__ . '/../config/email.php';
            $email_subject = "Confirm Order Receipt - Order #{$order['order_number']}";
            $email_body = "
                <h2>Confirm Order Receipt</h2>
                <p>Dear {$order['first_name']},</p>
                <p>Your order <strong>#{$order['order_number']}</strong> has been delivered.</p>
                <p>The delivery rider has not uploaded proof of delivery yet. If you have received your order, please confirm:</p>
                <p><a href='" . base_url($link) . "' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Confirm Order Received</a></p>
                <p>If you have NOT received your order, please contact us immediately.</p>
                <p>Thank you!</p>
            ";
            send_app_email($order['email'], $email_subject, $email_body, true);
        }
        
        // Update reminder_sent_at timestamp
        $update_sql = "UPDATE orders SET reminder_sent_at = ? WHERE id = ?";
        $database->query($update_sql, [$now, $order['id']]);
        
        $sent_count++;
        error_log("Delivery reminder sent for order #{$order['order_number']} (ID: {$order['id']})");
        
    } catch (Exception $e) {
        error_log("Error sending delivery reminder for order {$order['id']}: " . $e->getMessage());
    }
}

if ($sent_count > 0) {
    error_log("Delivery reminders cron: Sent $sent_count reminder(s)");
} else {
    error_log("Delivery reminders cron: No reminders needed");
}
?>
