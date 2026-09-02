<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$pending_id = intval($_GET['pending_id'] ?? 0);
if (!$pending_id) {
    redirect(base_url('customer/cart.php'));
}

$pending = $database->fetch("SELECT * FROM pending_orders WHERE id = ? AND customer_id = ?", [$pending_id, $_SESSION['customer_id']]);
if (!$pending || $pending['status'] !== 'pending') {
    redirect(base_url('customer/orders.php'));
}

$page_title = 'Payment Authorized';
include '../includes/customer_header.php';
?>

<main class="container py-5 text-center">
    <div class="mb-4">
        <i class="fas fa-clock text-warning" style="font-size: 4rem;"></i>
    </div>
    <h2>Payment Authorized</h2>
    <p>Your payment is on hold. Waiting for supplier to confirm.</p>
    <p><strong>Total:</strong> ₱<?= number_format($pending['total'], 2) ?></p>
    <a href="<?= base_url('customer/orders.php') ?>" class="btn btn-primary">View Orders</a>
</main>

<script>
setInterval(() => {
    fetch('<?= base_url('api/payments/status.php?pending_id=') ?><?= $pending_id ?>')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'confirmed') {
                window.location.href = '<?= base_url('customer/order-details.php?id=') ?>' + d.order_id;
            } else if (d.status === 'voided') {
                window.location.href = '<?= base_url('customer/orders.php') ?>';
            }
        });
}, 15000);
</script>

<?php include '../includes/customer_footer.php'; ?>