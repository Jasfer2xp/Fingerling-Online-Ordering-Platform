<?php
/**
 * Debug Sync Script
 * Shows detailed information about why sync might not be working
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/session_guard.php';
require_once '../classes/Supplier.php';
require_once '../includes/xendit_api_check.php';

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

// Get all confirmed orders
$orders = $database->fetchAll(
    "SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
            xi.invoice_id, xi.xendit_status, xi.amount as invoice_amount,
            p.id as payment_id, p.status as payment_status
     FROM orders o
     LEFT JOIN xendit_invoices xi ON o.id = xi.order_id
     LEFT JOIN payments p ON o.id = p.order_id
     WHERE o.supplier_id = ? 
     AND o.status = 'confirmed'
     ORDER BY o.created_at DESC",
    [$supplier_id]
);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Sync - Supplier Orders</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .status-confirmed { background: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; }
        .status-paid { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; }
        .status-pending { background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; }
        .error { color: #dc3545; }
        .success { color: #28a745; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Sync - Supplier Orders</h1>
        <p><a href="orders.php?status=confirmed" class="btn">← Back to Orders</a></p>
        
        <div class="info">
            <strong>Found <?= count($orders) ?> confirmed order(s)</strong>
        </div>
        
        <?php if (empty($orders)): ?>
            <p>No confirmed orders found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Order Status</th>
                        <th>Amount</th>
                        <th>Xendit Invoice ID</th>
                        <th>Xendit Status</th>
                        <th>Payment Record</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                            <td><span class="status-confirmed"><?= htmlspecialchars($order['status']) ?></span></td>
                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($order['invoice_id']): ?>
                                    <?= htmlspecialchars(substr($order['invoice_id'], 0, 20)) ?>...
                                <?php else: ?>
                                    <span class="error">No invoice</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['xendit_status']): ?>
                                    <span class="status-<?= strtolower($order['xendit_status']) === 'paid' ? 'paid' : 'pending' ?>">
                                        <?= htmlspecialchars($order['xendit_status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="error">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['payment_id']): ?>
                                    <span class="success">Exists</span>
                                <?php else: ?>
                                    <span class="error">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['payment_status']): ?>
                                    <?= htmlspecialchars($order['payment_status']) ?>
                                <?php else: ?>
                                    <span class="error">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['invoice_id']): ?>
                                    <form method="POST" action="orders.php" style="display:inline;">
                                        <input type="hidden" name="action" value="sync_single">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn" style="background: #28a745;">Sync This Order</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

