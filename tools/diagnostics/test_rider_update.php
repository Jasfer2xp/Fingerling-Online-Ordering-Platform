<?php
require_once 'config/config.php';

echo "<h1>Test Rider Proof Submission Flow</h1>";

// Find an order that's 'out_for_delivery' to test with
$test_order = $database->fetch(
    "SELECT id, order_number, status, delivery_token, token_expires_at 
     FROM orders 
     WHERE supplier_id = 76 
     AND status = 'out_for_delivery' 
     LIMIT 1"
);

if (!$test_order) {
    echo "<p style='color: orange;'>No 'out_for_delivery' orders found. Let me check for ANY order we can test with...</p>";
    
    $test_order = $database->fetch(
        "SELECT id, order_number, status, delivery_token, token_expires_at 
         FROM orders 
         WHERE supplier_id = 76 
         ORDER BY updated_at DESC
         LIMIT 1"
    );
}

if ($test_order) {
    echo "<h2>Test Order Found</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Order ID</th><td>" . $test_order['id'] . "</td></tr>";
    echo "<tr><th>Order #</th><td>" . $test_order['order_number'] . "</td></tr>";
    echo "<tr><th>Current Status</th><td><strong>" . $test_order['status'] . "</strong></td></tr>";
    echo "<tr><th>Has Token?</th><td>" . ($test_order['delivery_token'] ? 'Yes' : 'No') . "</td></tr>";
    echo "</table>";
    
    echo "<h2>Manual Status Update Test</h2>";
    
    if (isset($_POST['test_update'])) {
        try {
            $now = date('Y-m-d H:i:s');
            
            // Try the EXACT same query the rider uses
            $result = $database->query(
                "UPDATE orders SET status = 'pending_supplier_review', updated_at = ? WHERE id = ?", 
                [$now, $test_order['id']]
            );
            
            $affected = $result->rowCount();
            
            echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0;'>";
            echo "<strong>✓ Update executed!</strong> Affected rows: $affected<br>";
            echo "Now checking if it actually changed...";
            echo "</div>";
            
            // Verify
            $verify = $database->fetch("SELECT status FROM orders WHERE id = ?", [$test_order['id']]);
            
            echo "<div style='background: " . ($verify['status'] === 'pending_supplier_review' ? '#d4edda' : '#f8d7da') . "; padding: 15px;'>";
            echo "<strong>Verification:</strong> Status is now '<strong>" . $verify['status'] . "</strong>'";
            
            if ($verify['status'] === 'pending_supplier_review') {
                echo " ✓ SUCCESS!";
            } else {
                echo " ✗ FAILED - Status didn't change!";
            }
            echo "</div>";
            
            // Check if there are any triggers or constraints
            echo "<h3>Checking for Database Triggers</h3>";
            $triggers = $database->fetchAll("SHOW TRIGGERS WHERE `Table` = 'orders'");
            if (count($triggers) > 0) {
                echo "<p style='color: red;'><strong>⚠ Found " . count($triggers) . " trigger(s) on orders table!</strong></p>";
                echo "<pre>" . print_r($triggers, true) . "</pre>";
            } else {
                echo "<p style='color: green;'>No triggers found on orders table.</p>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 15px;'>";
            echo "<strong>Error:</strong> " . $e->getMessage();
            echo "</div>";
        }
    } else {
        echo "<form method='POST'>";
        echo "<p>Click below to test updating Order #" . $test_order['order_number'] . " to 'pending_supplier_review':</p>";
        echo "<button type='submit' name='test_update' style='padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;'>";
        echo "Test Status Update";
        echo "</button>";
        echo "</form>";
    }
    
} else {
    echo "<p style='color: red;'>No orders found for supplier 76!</p>";
}

// Also check if delivery_proofs table has any entries
echo "<h2>Delivery Proofs Check</h2>";
$proofs = $database->fetchAll(
    "SELECT dp.*, o.order_number, o.status as order_status 
     FROM delivery_proofs dp 
     JOIN orders o ON dp.order_id = o.id 
     WHERE o.supplier_id = 76 
     ORDER BY dp.submitted_at DESC 
     LIMIT 10"
);

echo "<p>Found " . count($proofs) . " delivery proof(s) in database:</p>";

if (count($proofs) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Proof ID</th><th>Order #</th><th>Order Status</th><th>Rider</th><th>Submitted At</th></tr>";
    foreach ($proofs as $proof) {
        $highlight = $proof['order_status'] !== 'pending_supplier_review' ? 'background: #f8d7da;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>" . $proof['id'] . "</td>";
        echo "<td>" . htmlspecialchars($proof['order_number']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($proof['order_status']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($proof['rider_name']) . "</td>";
        echo "<td>" . $proof['submitted_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='background: #fff3cd; padding: 10px; margin: 10px 0;'>";
    echo "<strong>⚠ Important:</strong> If you see proofs but the order status is NOT 'pending_supplier_review', ";
    echo "it means the rider submitted the proof but the status update failed!";
    echo "</p>";
}
?>
