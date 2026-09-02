<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

// Validate required fields
$pending_id = isset($_GET['pending_id']) ? intval($_GET['pending_id']) : 0;

if (empty($pending_id)) {
    $_SESSION['error'] = 'Invalid order reference.';
    redirect(base_url('customer/cart.php'));
}

$user_id = get_user_id();
$user = new User($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Get pending order details
$pending_order = null;
try {
    $sql = "SELECT * FROM pending_orders WHERE id = ?";
    $pending_order = $database->fetch($sql, [$pending_id]);
    
    if (!$pending_order) {
        $_SESSION['error'] = 'Order not found.';
        redirect(base_url('customer/orders.php'));
    }
} catch (Exception $e) {
    error_log("Error fetching pending order: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading order details.';
    redirect(base_url('customer/orders.php'));
}

$page_title = 'Order Pending Confirmation';
include '../includes/customer_header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <div class="pending-animation mx-auto mb-3">
                            <i class="fas fa-clock fa-3x text-warning"></i>
                        </div>
                        <h2 class="card-title mb-3">Order Pending Confirmation</h2>
                        <p class="card-text text-muted">
                            Your payment has been authorized and we're waiting for the supplier to confirm your order.
                        </p>
                    </div>
                    
                    <div class="pending-info mb-4">
                        <div class="info-item mb-2">
                            <strong>Pending Order ID:</strong> 
                            <span class="badge bg-secondary">#<?php echo $pending_id; ?></span>
                        </div>
                        <div class="info-item mb-2">
                            <strong>Amount:</strong> 
                            <span class="fw-bold">₱<?php echo number_format($pending_order['total'], 2); ?></span>
                        </div>
                        <div class="info-item mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-warning text-dark">Waiting for Supplier</span>
                        </div>
                        <div class="info-item">
                            <small class="text-muted">
                                Your order will be processed once the supplier confirms availability.
                            </small>
                        </div>
                    </div>
                    
                    <div class="progress-container mb-4">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 25%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Authorized</span>
                            <span class="fw-bold">Pending</span>
                            <span>Confirmed</span>
                            <span>Delivered</span>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <a href="<?php echo base_url('customer/orders.php'); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>View All Orders
                        </a>
                        <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="btn btn-link">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-4 mb-0">
                <h6 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>What happens next?
                </h6>
                <ul class="mb-0 small">
                    <li>The supplier will review your order within <?php echo ORDER_TIMEOUT_LABEL; ?></li>
                    <li>If confirmed, your payment will be captured and order finalized</li>
                    <li>If cancelled, your authorization will be voided and funds released</li>
                    <li>You'll receive email notifications for any status changes</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<script>
// Periodically check order status
const pendingId = <?php echo json_encode($pending_id); ?>;
let statusCheckInterval;

function checkOrderStatus() {
    fetch(`../api/payments/status.php?pending_id=${pendingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If order is confirmed or cancelled, redirect to appropriate page
                if (data.status === 'confirmed') {
                    window.location.href = `order-details.php?id=${data.order_id}`;
                } else if (data.status === 'cancelled' || data.status === 'expired') {
                    window.location.href = 'orders.php';
                }
            }
        })
        .catch(error => {
            console.error('Error checking order status:', error);
        });
}

// Check status every 30 seconds
statusCheckInterval = setInterval(checkOrderStatus, 30000);

// Clean up interval when page is unloaded
window.addEventListener('beforeunload', () => {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
    }
});
</script>

<?php include '../includes/customer_footer.php'; ?>