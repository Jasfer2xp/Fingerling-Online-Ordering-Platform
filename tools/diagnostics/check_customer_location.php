<?php
require_once 'config/config.php';

$sql = "SELECT id, first_name, last_name, latitude, longitude FROM customers LIMIT 5";
$customers = $database->fetchAll($sql);

echo "<h2>Customer Location Data Check</h2>";
echo "<pre>";
print_r($customers);
echo "</pre>";

// Check a specific customer
$customer_id = 1;
$sql = "SELECT id, first_name, last_name, latitude, longitude FROM customers WHERE id = ?";
$customer = $database->fetch($sql, [$customer_id]);

echo "<h2>Specific Customer (ID: $customer_id)</h2>";
echo "<pre>";
print_r($customer);
echo "</pre>";
?>