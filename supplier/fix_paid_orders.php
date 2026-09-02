<?php
/**
 * Manual Fix Script for Paid Orders
 * This script manually updates orders that have been paid but status is still 'confirmed'
 * 
 * Usage: Access via browser: https://fingerling.shop/supplier/fix_paid_orders.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/session_guard.php';
require_once '../classes/Supplier.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    die('Access denied. Supplier login required.');
}

$user_id = get_user_id();
$supplier = new Supplier($database);
$supplier_id = $supplier->getSupplierIdByUserId($user_id);

if (!$supplier_id) {
    die('Supplier not found.');
}

$results = [
    'fixed_from_payments' => 0,
    'fixed_from_xendit' => 0,
    'errors' => []
];

try {
    // Fix 1: Check payments table
    // Fix 1: Check payments table
    $fix_sql1 = "UPDATE orders o 
                 INNER JOIN payments p ON o.id = p.order_id 
                 SET o.status = 'confirmed_and_paid', o.updated_at = NOW()
                 WHERE o.supplier_id = ? 
                 AND o.status = 'confirmed' 
                 AND p.status IN ('paid', 'completed')
                 AND p.order_id = o.id";
    $stmt1 = $database->query($fix_sql1, [$supplier_id]);
    $results['fixed_from_payments'] = $stmt1->rowCount();
    
    // Fix 2: Check xendit_invoices table
    $fix_sql2 = "UPDATE orders o 
                 INNER JOIN xendit_invoices xi ON o.id = xi.order_id 
                 SET o.status = 'confirmed_and_paid', o.updated_at = NOW()
                 WHERE o.supplier_id = ? 
                 AND o.status = 'confirmed' 
                 AND xi.xendit_status = 'PAID'
                 AND xi.order_id = o.id";
    $stmt2 = $database->query($fix_sql2, [$supplier_id]);
    $results['fixed_from_xendit'] = $stmt2->rowCount();
    
} catch (Exception $e) {
    $results['errors'][] = $e->getMessage();
}

// Show results
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Paid Orders</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; }
        .info { background: #e7f3ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Fix Paid Orders - Results</h1>
        
        <?php if ($results['fixed_from_payments'] > 0 || $results['fixed_from_xendit'] > 0): ?>
            <div class="info success">
                <strong>✓ Success!</strong><br>
                Fixed <?= $results['fixed_from_payments'] ?> order(s) from payments table<br>
                Fixed <?= $results['fixed_from_xendit'] ?> order(s) from xendit_invoices table<br>
                <strong>Total: <?= $results['fixed_from_payments'] + $results['fixed_from_xendit'] ?> order(s) updated</strong>
            </div>
        <?php else: ?>
            <div class="info">
                No orders needed fixing. All paid orders are already in the correct status.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results['errors'])): ?>
            <div class="error">
                <strong>Errors:</strong><br>
                <?php foreach ($results['errors'] as $error): ?>
                    <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <p>
            <a href="orders.php?status=confirmed">← Back to Orders</a> | 
            <a href="orders.php?status=confirmed_and_paid">View Paid & Processing Orders</a>
        </p>
    </div>
</body>
</html>

