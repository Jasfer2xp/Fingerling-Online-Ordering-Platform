<?php
/**
 * FORCE FIX ORDERS - Emergency Fix Script
 * This will directly update ALL confirmed orders that have ANY payment evidence
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/session_guard.php';
require_once '../classes/Supplier.php';
require_once '../classes/Order.php';

if (!is_logged_in() || get_user_type() !== 'supplier') {
    die('Access denied.');
}

$user_id = get_user_id();
$supplier = new Supplier($database);
$supplier_id = $supplier->getSupplierIdByUserId($user_id);

if (!$supplier_id) {
    die('Supplier not found.');
}

// Get ALL confirmed orders with full details
$orders = $database->fetchAll(
    "SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
            xi.id as invoice_db_id, xi.invoice_id, xi.xendit_status, xi.amount as invoice_amount,
            p.id as payment_id, p.status as payment_status, p.amount as payment_amount
     FROM orders o
     LEFT JOIN xendit_invoices xi ON o.id = xi.order_id
     LEFT JOIN payments p ON o.id = p.order_id
     WHERE o.supplier_id = ? 
     AND o.status = 'confirmed'
     ORDER BY o.created_at DESC",
    [$supplier_id]
);

$fixed = [];
$errors = [];

foreach ($orders as $order) {
    $order_id = $order['id'];
    $has_payment = !empty($order['payment_id']) && in_array($order['payment_status'], ['paid', 'completed']);
    $has_paid_invoice = !empty($order['invoice_id']) && $order['xendit_status'] === 'PAID';
    
    if ($has_payment || $has_paid_invoice) {
        try {
            $order_obj = new Order($database);
            $payment_data = [
                'amount' => $order['payment_amount'] ?? $order['invoice_amount'] ?? $order['total_amount'],
                'transaction_id' => $order['invoice_id'] ?? 'force_fix_' . time() . '_' . $order_id,
                'payment_method' => 'xendit',
                'currency' => 'PHP'
            ];
            $order_obj->processPaymentSuccess($order_id, $payment_data);
            $fixed[] = $order['order_number'];
        } catch (Exception $e) {
            $errors[] = "{$order['order_number']}: {$e->getMessage()}";
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Fix Orders</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; font-weight: bold; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Force Fix Orders - Results</h1>
        
        <?php if (count($fixed) > 0): ?>
            <div class="success">
                <strong>✓ SUCCESS!</strong><br>
                Fixed <?= count($fixed) ?> order(s):<br>
                <?php foreach ($fixed as $order_num): ?>
                    • <?= htmlspecialchars($order_num) ?><br>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="info">
                No orders were fixed. This could mean:<br>
                • Orders don't have payment records<br>
                • Invoices aren't marked as PAID<br>
                • Payments haven't been completed yet
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Errors:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>Debug Info:</strong><br>
            Total confirmed orders checked: <?= count($orders) ?><br>
            <?php foreach ($orders as $o): ?>
                Order <?= $o['order_number'] ?>: 
                Invoice=<?= $o['invoice_id'] ? substr($o['invoice_id'], 0, 15) . '...' : 'NONE' ?>, 
                Invoice Status=<?= $o['xendit_status'] ?? 'NONE' ?>, 
                Payment=<?= $o['payment_id'] ? 'YES (' . $o['payment_status'] . ')' : 'NO' ?><br>
            <?php endforeach; ?>
        </div>
        
        <a href="orders.php?status=confirmed" class="btn">← Back to Orders</a>
    </div>
</body>
</html>

