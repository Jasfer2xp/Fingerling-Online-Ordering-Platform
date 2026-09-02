<?php
require_once 'config/config.php';
require_once 'classes/Supplier.php';

$supplier_id = 76; // From previous debug

echo "<h1>Debug: Pending Supplier Review Orders</h1>";

// Direct SQL check
echo "<h2>1. Direct Database Check</h2>";
$sql = "SELECT o.id, o.order_number, o.status, o.supplier_id, 
               dp.id as proof_id, dp.image_path, dp.submitted_at
        FROM orders o
        LEFT JOIN delivery_proofs dp ON o.id = dp.order_id
        WHERE o.supplier_id = ? 
        AND o.status = 'pending_supplier_review'
        ORDER BY o.created_at DESC";

$orders = $database->fetchAll($sql, [$supplier_id]);

echo "<p><strong>Found " . count($orders) . " orders with status 'pending_supplier_review'</strong></p>";

if (count($orders) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Order #</th><th>Status</th><th>Proof ID</th><th>Image Path</th><th>Submitted At</th></tr>";
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
        echo "<td>" . htmlspecialchars($order['status']) . "</td>";
        echo "<td>" . ($order['proof_id'] ?: '<span style="color:red">NO PROOF</span>') . "</td>";
        echo "<td>" . ($order['image_path'] ? htmlspecialchars($order['image_path']) : '<span style="color:red">N/A</span>') . "</td>";
        echo "<td>" . ($order['submitted_at'] ?: 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>NO ORDERS FOUND!</strong> This means either:</p>";
    echo "<ul>";
    echo "<li>No rider has submitted a proof yet, OR</li>";
    echo "<li>The order status didn't update to 'pending_supplier_review' when proof was submitted</li>";
    echo "</ul>";
}

// Check via Supplier class
echo "<h2>2. Supplier Class Check</h2>";
try {
    $supplier = new Supplier($database);
    $supplier->supplier_id = $supplier_id;
    
    $class_orders = $supplier->getOrders(['status' => 'pending_supplier_review']);
    
    echo "<p><strong>Supplier::getOrders() returned " . count($class_orders) . " orders</strong></p>";
    
    if (count($class_orders) != count($orders)) {
        echo "<p style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;'>";
        echo "<strong>⚠ MISMATCH!</strong> Direct SQL found " . count($orders) . " but Supplier class returned " . count($class_orders);
        echo "</p>";
    } else {
        echo "<p style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb;'>";
        echo "<strong>✓ MATCH!</strong> Both methods returned the same count.";
        echo "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check recent order status changes
echo "<h2>3. Recent Status Changes (Last 24 hours)</h2>";
$recent_sql = "SELECT id, order_number, status, updated_at 
               FROM orders 
               WHERE supplier_id = ? 
               AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               ORDER BY updated_at DESC";

$recent = $database->fetchAll($recent_sql, [$supplier_id]);

echo "<p>Found " . count($recent) . " orders updated in last 24 hours:</p>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Order #</th><th>Current Status</th><th>Updated At</th></tr>";
foreach ($recent as $r) {
    $highlight = $r['status'] === 'pending_supplier_review' ? 'background: #fff3cd;' : '';
    echo "<tr style='$highlight'>";
    echo "<td>" . htmlspecialchars($r['order_number']) . "</td>";
    echo "<td><strong>" . htmlspecialchars($r['status']) . "</strong></td>";
    echo "<td>" . $r['updated_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
