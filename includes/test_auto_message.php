<?php
/**
 * Test Auto Payment Message Script
 * Run this in Hostinger to test if auto messages are working
 * 
 * Usage: 
 * 1. Upload this file to your Hostinger root (same level as capstone folder)
 * 2. Access via browser: https://fingerling.shop/test_auto_message.php?order_id=123
 * 3. Replace 123 with an actual paid order ID
 */

require_once __DIR__ . '/capstone/config/config.php';
require_once __DIR__ . '/capstone/config/database.php';
require_once __DIR__ . '/capstone/includes/auto_payment_message.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Test Auto Message</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;color:#155724;margin:10px 0;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:5px;color:#721c24;margin:10px 0;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;padding:15px;border-radius:5px;color:#0c5460;margin:10px 0;}";
echo "pre{background:#fff;padding:10px;border:1px solid #ddd;border-radius:5px;overflow:auto;}";
echo "</style></head><body>";
echo "<h1>🧪 Test Auto Payment Message</h1>";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo "<div class='error'><strong>Error:</strong> Please provide a valid order_id in the URL.<br>";
    echo "Example: <code>test_auto_message.php?order_id=123</code></div>";
    
    // Show recent paid orders
    try {
        $recent_orders = $database->fetchAll(
            "SELECT o.id, o.order_number, o.status, o.payment_status, 
                    c.first_name, c.last_name, s.business_name,
                    o.created_at
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             JOIN suppliers s ON o.supplier_id = s.id
             WHERE o.payment_status = 'paid' OR o.status IN ('confirmed_and_paid', 'preparing')
             ORDER BY o.created_at DESC
             LIMIT 10"
        );
        
        if (!empty($recent_orders)) {
            echo "<div class='info'><strong>Recent Paid Orders:</strong><ul>";
            foreach ($recent_orders as $order) {
                echo "<li><a href='?order_id={$order['id']}'>Order #{$order['id']}</a> - {$order['order_number']} - {$order['business_name']} - {$order['first_name']} {$order['last_name']} - Status: {$order['status']}</li>";
            }
            echo "</ul></div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>Error fetching orders: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "</body></html>";
    exit;
}

echo "<div class='info'><strong>Testing Order ID:</strong> {$order_id}</div>";

// Step 1: Check if order exists and get details
try {
    $order_info = $database->fetch(
        "SELECT o.id, o.order_number, o.status, o.payment_status, o.customer_id, o.supplier_id,
                c.user_id AS customer_user_id, c.first_name, c.last_name,
                s.user_id AS supplier_user_id, s.business_name
         FROM orders o
         JOIN customers c ON o.customer_id = c.id
         JOIN suppliers s ON o.supplier_id = s.id
         WHERE o.id = ?",
        [$order_id]
    );
    
    if (!$order_info) {
        echo "<div class='error'><strong>Error:</strong> Order #{$order_id} not found in database.</div>";
        echo "</body></html>";
        exit;
    }
    
    echo "<div class='info'>";
    echo "<strong>Order Details:</strong><br>";
    echo "Order Number: {$order_info['order_number']}<br>";
    echo "Status: {$order_info['status']}<br>";
    echo "Payment Status: {$order_info['payment_status']}<br>";
    echo "Customer: {$order_info['first_name']} {$order_info['last_name']} (User ID: {$order_info['customer_user_id']})<br>";
    echo "Supplier: {$order_info['business_name']} (User ID: {$order_info['supplier_user_id']})<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>Error fetching order:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</body></html>";
    exit;
}

// Step 2: Check if conversation exists
try {
    $conversation = $database->fetch(
        "SELECT id, customer_id, supplier_id, order_id, created_at 
         FROM conversations 
         WHERE customer_id = ? AND supplier_id = ?",
        [$order_info['customer_id'], $order_info['supplier_id']]
    );
    
    if ($conversation) {
        echo "<div class='info'><strong>Existing Conversation Found:</strong> ID #{$conversation['id']}</div>";
    } else {
        echo "<div class='info'><strong>No Conversation Found:</strong> Will be created automatically</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><strong>Error checking conversation:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Step 3: Check if auto message already exists
try {
    $existing_message = $database->fetch(
        "SELECT id, message, created_at 
         FROM messages 
         WHERE order_id = ? AND is_auto = 1 
         LIMIT 1",
        [$order_id]
    );
    
    if ($existing_message) {
        echo "<div class='info'><strong>Auto Message Already Exists:</strong><br>";
        echo "Message ID: {$existing_message['id']}<br>";
        echo "Created: {$existing_message['created_at']}<br>";
        echo "Message: " . htmlspecialchars(substr($existing_message['message'], 0, 100)) . "...</div>";
    } else {
        echo "<div class='info'><strong>No Auto Message Found:</strong> Will be created now</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><strong>Error checking existing message:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Step 4: Check if messages table has required columns
try {
    $columns = $database->fetchAll(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'"
    );
    $column_names = array_column($columns, 'COLUMN_NAME');
    
    $has_is_auto = in_array('is_auto', $column_names);
    $has_order_id = in_array('order_id', $column_names);
    
    echo "<div class='info'><strong>Messages Table Columns:</strong><br>";
    echo "is_auto column: " . ($has_is_auto ? "✅ Exists" : "❌ Missing") . "<br>";
    echo "order_id column: " . ($has_order_id ? "✅ Exists" : "❌ Missing") . "<br>";
    echo "</div>";
    
    if (!$has_is_auto || !$has_order_id) {
        echo "<div class='error'><strong>Warning:</strong> Required columns are missing. The function will try to add them automatically.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'><strong>Error checking columns:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Step 5: Call the auto message function
echo "<div class='info'><strong>Attempting to send auto message...</strong></div>";

$result = send_auto_payment_message($database, $order_id);

if ($result) {
    echo "<div class='success'><strong>✅ SUCCESS!</strong> Auto message sent successfully.</div>";
    
    // Get the newly created message
    try {
        $new_message = $database->fetch(
            "SELECT m.*, c.business_name 
             FROM messages m
             LEFT JOIN suppliers s ON m.sender_id = s.user_id
             LEFT JOIN customers c ON m.sender_id = c.user_id
             WHERE m.order_id = ? AND m.is_auto = 1 
             ORDER BY m.created_at DESC 
             LIMIT 1",
            [$order_id]
        );
        
        if ($new_message) {
            echo "<div class='success'>";
            echo "<strong>Message Details:</strong><br>";
            echo "Message ID: {$new_message['id']}<br>";
            echo "Conversation ID: {$new_message['conversation_id']}<br>";
            echo "Sender ID: {$new_message['sender_id']}<br>";
            echo "Receiver ID: {$new_message['receiver_id']}<br>";
            echo "Is Auto: " . ($new_message['is_auto'] ? 'Yes' : 'No') . "<br>";
            echo "Is Read: " . ($new_message['is_read'] ? 'Yes' : 'No') . "<br>";
            echo "Created: {$new_message['created_at']}<br>";
            echo "<strong>Message Text:</strong><br>";
            echo "<pre>" . htmlspecialchars($new_message['message']) . "</pre>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'><strong>Error fetching new message:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='error'><strong>❌ FAILED!</strong> Auto message was not sent. Check the error log for details.</div>";
    echo "<div class='info'><strong>Common Issues:</strong><ul>";
    echo "<li>Check PHP error log: <code>logs/php_errors.log</code></li>";
    echo "<li>Verify order has customer_id and supplier_id</li>";
    echo "<li>Check if conversations table exists</li>";
    echo "<li>Verify messages table has is_auto and order_id columns</li>";
    echo "</ul></div>";
}

// Step 6: Show recent error log entries
echo "<div class='info'><strong>Recent Error Log Entries (AUTO MESSAGE):</strong><br>";
$log_file = __DIR__ . '/capstone/logs/php_errors.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $auto_lines = array_filter($lines, function($line) {
        return stripos($line, 'AUTO MESSAGE') !== false;
    });
    $recent = array_slice($auto_lines, -10);
    if (!empty($recent)) {
        echo "<pre>" . htmlspecialchars(implode('', $recent)) . "</pre>";
    } else {
        echo "No AUTO MESSAGE entries found in log.";
    }
} else {
    echo "Log file not found: {$log_file}";
}
echo "</div>";

echo "<hr>";
echo "<p><a href='?order_id={$order_id}'>🔄 Test Again</a> | <a href='?'>📋 View Other Orders</a></p>";
echo "</body></html>";

