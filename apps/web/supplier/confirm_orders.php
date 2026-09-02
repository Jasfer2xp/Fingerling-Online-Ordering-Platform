<?php
require_once '../config/config.php';
require_once '../includes/check_session.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Supplier.php';
require_once '../classes/Order.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$supplier_id = $profile['id'];

if (!$supplier_id) {
    $_SESSION['error'] = 'Supplier profile not found.';
    redirect(base_url('auth/login.php'));
}

$order = new Order($database);

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = $_POST['order_id'] ?? 0;
    
    try {
        if ($action === 'confirm') {
            if ($order->confirmOrderBySupplier($order_id, $supplier_id)) {
                $_SESSION['success'] = 'Order confirmed successfully. The customer has been notified to proceed with payment within ' . ORDER_TIMEOUT_LABEL . '.';
            } else {
                $_SESSION['error'] = 'Failed to confirm order.';
            }
        } elseif ($action === 'reject') {
            if ($order->rejectOrderBySupplier($order_id, $supplier_id)) {
                $_SESSION['success'] = 'Order rejected successfully.';
            } else {
                $_SESSION['error'] = 'Failed to reject order.';
            }
        } elseif ($action === 'cancel') {
            // Cancel order that is awaiting customer payment
            if ($order->cancelOrderBySupplierBeforePayment($order_id, $supplier_id)) {
                $_SESSION['success'] = 'Order cancelled successfully.';
            } else {
                $_SESSION['error'] = 'Failed to cancel order. Order may not be in the correct status.';
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('supplier/orders.php'));
}

// Get orders awaiting supplier confirmation
$sql = "SELECT o.*, c.first_name, c.last_name, c.contact_number, c.address, c.barangay, c.city, c.province
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.supplier_id = ? AND o.status = 'pending'
        ORDER BY o.created_at DESC";
$pending_orders = $database->fetchAll($sql, [$supplier_id]);

$page_title = 'Confirm Orders';
include '../includes/supplier_header.php';
?>

<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Orders Awaiting Confirmation</h1>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (empty($pending_orders)): ?>
            <div class="alert alert-info">No orders awaiting your confirmation.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pending_orders as $order_item): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Order #<?php echo htmlspecialchars($order_item['order_number']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order_item['first_name'] . ' ' . $order_item['last_name']); ?></p>
                                <p><strong>Total Amount:</strong> ₱<?php echo number_format($order_item['total_amount'], 2); ?></p>
                                <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order_item['delivery_address']); ?></p>
                                <p><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order_item['created_at'])); ?></p>
                                
                                <div class="mt-3">
                                    <form method="POST" class="d-inline me-2">
                                        <input type="hidden" name="order_id" value="<?php echo $order_item['id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn btn-success" 
                                                onclick="return confirm('Are you sure you want to confirm this order? The customer will be notified to proceed with payment.')">Accept Order</button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?php echo $order_item['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to reject this order?')">Reject Order</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

