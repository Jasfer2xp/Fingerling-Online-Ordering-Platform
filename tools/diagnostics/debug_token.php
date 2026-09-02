<?php
require_once 'config/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Please provide a token URL parameter.");
}

echo "<h1>Debug Info for Token: " . htmlspecialchars($token) . "</h1>";

try {
    // 1. Check Order Table
    $order = $database->fetch("SELECT id, order_number, status, supplier_id, delivery_worker, token_used, updated_at FROM orders WHERE delivery_token = ?", [$token]);

    if (!$order) {
        echo "<p style='color:red'>Token NOT FOUND in orders table.</p>";
    } else {
        echo "<h3>Order Checks:</h3>";
        echo "<ul>";
        echo "<li><strong>Order ID:</strong> " . $order['id'] . "</li>";
        echo "<li><strong>Order Number:</strong> " . $order['order_number'] . "</li>";
        echo "<li><strong>Current Status:</strong> <span style='background:yellow'>" . $order['status'] . "</span> (Expected: pending_supplier_review)</li>";
        echo "<li><strong>Supplier ID:</strong> " . $order['supplier_id'] . "</li>";
        echo "<li><strong>Rider:</strong> " . $order['delivery_worker'] . "</li>";
        echo "<li><strong>Token Used:</strong> " . ($order['token_used'] ? 'YES' : 'NO') . "</li>";
        echo "<li><strong>Last Updated:</strong> " . $order['updated_at'] . "</li>";
        echo "</ul>";

        // 2. Check Delivery Proofs Table
        $proof = $database->fetch("SELECT * FROM delivery_proofs WHERE order_id = ?", [$order['id']]);
        
        echo "<h3>Proof Table Checks:</h3>";
        if ($proof) {
            echo "<ul>";
            echo "<li><strong>Proof Found:</strong> YES</li>";
            echo "<li><strong>Submitted At:</strong> " . $proof['submitted_at'] . "</li>";
            echo "<li><strong>Image Path:</strong> " . $proof['image_path'] . "</li>";
            echo "</ul>";
        } else {
             echo "<p style='color:red'><strong>Proof NOT FOUND in database!</strong> Rider submission may have failed to insert.</p>";
        }

        // 3. Diagnose
        echo "<h3>Diagnosis:</h3>";
        if ($order['status'] === 'pending_supplier_review') {
            echo "<p style='color:green'>Status IS pending_supplier_review. The order SHOULD appear in the Supplier 'Review Proof' tab.</p>";
            echo "<p>If it is not appearing, check if you are logged in as Supplier ID <strong>" . $order['supplier_id'] . "</strong>.</p>";
        } else {
             echo "<p style='color:red'>Status is <strong>" . $order['status'] . "</strong>. It Failed to update to 'pending_supplier_review'.</p>";
             if ($proof) {
                 echo "<p>Proof exists, but status didn't update. Running manual fix...</p>";
                 // Attempt Fix
                 $database->query("UPDATE orders SET status = 'pending_supplier_review' WHERE id = ?", [$order['id']]);
                 echo "<p style='color:blue'><strong>FIX ATTEMPTED:</strong> Force updated status to 'pending_supplier_review'. Please check Supplier dashboard now.</p>";
             }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
