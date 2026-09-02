<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Check order #108 status
$order_id = 108;

$sql = "SELECT id, status, customer_id FROM orders WHERE id = ?";
$order_data = $database->fetch($sql, [$order_id]);

if ($order_data) {
    echo "<h2>Order #{$order_id} Details:</h2>";
    echo "<p>Status: " . $order_data['status'] . "</p>";
    echo "<p>Customer ID: " . $order_data['customer_id'] . "</p>";
} else {
    echo "<h2>Order #{$order_id} not found in database</h2>";
    
    // Let's check what orders exist
    $sql = "SELECT id, status, customer_id FROM orders ORDER BY id DESC LIMIT 10";
    $orders = $database->fetchAll($sql);
    
    echo "<h3>Recent orders:</h3>";
    echo "<ul>";
    foreach ($orders as $order) {
        echo "<li>Order #{$order['id']} - Status: {$order['status']} - Customer: {$order['customer_id']}</li>";
    }
    echo "</ul>";
}

// Check if we can get the customer ID from session
session_start();
if (isset($_SESSION['customer_id'])) {
    echo "<p>Current session customer ID: " . $_SESSION['customer_id'] . "</p>";
} else {
    echo "<p>No customer ID in session</p>";
}
?>