<?php
require_once 'config/config.php';

$order_id = 200;
$supplier_id = 76; // Hardcoded from debug logs

echo "<h1>Debug Sync Query</h1>";

// 1. Simulate the Select part of the update
$sql = "SELECT o.id, o.status, o.supplier_id, dp.id as proof_id 
        FROM orders o 
        INNER JOIN delivery_proofs dp ON o.id = dp.order_id
        WHERE o.supplier_id = ? 
        AND o.status NOT IN ('delivered', 'cancelled', 'pending_supplier_review')";

echo "<p>Running Query: <code>$sql</code> with supplier_id=$supplier_id</p>";

try {
    $rows = $database->fetchAll($sql, [$supplier_id]);
    echo "<h3>Matches found: " . count($rows) . "</h3>";
    
    if (count($rows) > 0) {
        echo "<table border='1'><tr><th>Order ID</th><th>Status</th><th>Supplier ID</th><th>Proof ID</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['supplier_id'] . "</td>";
            echo "<td>" . $row['proof_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Attempt Update
        echo "<h3>Attempting Update...</h3>";
        $update_sql = "UPDATE orders o 
                       INNER JOIN delivery_proofs dp ON o.id = dp.order_id
                       SET o.status = 'pending_supplier_review', o.updated_at = NOW()
                       WHERE o.supplier_id = ? 
                       AND o.status NOT IN ('delivered', 'cancelled', 'pending_supplier_review')";
        $stmt = $database->query($update_sql, [$supplier_id]);
        echo "<p>Rows Updated: " . $stmt->rowCount() . "</p>";
        echo "<p style='color:green'>UPDATE EXECUTED.</p>";
    } else {
        echo "<p style='color:red'>NO MATCHES FOUND. The Join or Where clause is failing.</p>";
        
        // Debug individual parts
        echo "<h4>Debugging Why:</h4>";
        // Check Order existence
        $o = $database->fetch("SELECT id, status, supplier_id FROM orders WHERE id=?", [$order_id]);
        echo "Order $order_id: Status=" . $o['status'] . ", Supplier=" . $o['supplier_id'] . "<br>";
        
        // Check Proof existence
        $p = $database->fetchAll("SELECT id, order_id FROM delivery_proofs WHERE order_id=?", [$order_id]);
        echo "Proofs for $order_id: " . count($p) . "<br>";
        
        // Check filtering
        $status_check = !in_array($o['status'], ['delivered', 'cancelled', 'pending_supplier_review']);
        echo "Status " . $o['status'] . " allowed? " . ($status_check ? 'YES' : 'NO') . "<br>";
        
        echo "Supplier ID match? " . ($o['supplier_id'] == $supplier_id ? 'YES' : 'NO') . "<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
