<?php
/**
 * Customer Order Cancellation Page
 * Handles order cancellation requests
 */
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect('/auth/login.php');
}

$user_id = get_user_id();
$customer = new Customer($database);
$order = new Order($database);

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect('/customer/orders.php');
}

// Get order details and verify ownership
try {
    $order_details = $order->getOrderById($order_id);
    
    if (!$order_details || $order_details['customer_id'] !== $customer->getCustomerIdByUserId($user_id)) {
        $_SESSION['error'] = 'Order not found or access denied.';
        redirect('/customer/orders.php');
    }
    
    // Check if order can be cancelled
    if (!in_array($order_details['status'], ['pending', 'confirmed'])) {
        $_SESSION['error'] = 'This order cannot be cancelled. Current status: ' . ucfirst($order_details['status']);
        redirect('/customer/order-details.php?id=' . $order_id);
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading order details: ' . $e->getMessage();
    redirect('/customer/orders.php');
}

// Handle cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancellation_reason = sanitize_input($_POST['cancellation_reason'] ?? '');
    
    if (empty($cancellation_reason)) {
        $error = 'Please provide a reason for cancellation.';
    } else {
        try {
            $result = $order->cancelOrder($order_id, $cancellation_reason, $user_id);
            
            if ($result) {
                $_SESSION['success'] = 'Order cancelled successfully. Any payments will be refunded within 3-5 business days.';
                redirect('/customer/orders.php');
            } else {
                $error = 'Failed to cancel order. Please try again or contact support.';
            }
        } catch (Exception $e) {
            $error = 'Error cancelling order: ' . $e->getMessage();
        }
    }
}

$page_title = 'Cancel Order #' . $order_details['order_number'];
include '../includes/customer_header.php';
include '../includes/customer_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content customer-main-content">
        <!-- Dashboard Header -->
        <div class="modern-dashboard-header">
            <div>
                <button class="modern-sidebar-toggle d-lg-none" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="modern-dashboard-title">
                    <div class="modern-dashboard-title-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    Cancel Order #<?php echo $order_details['order_number']; ?>
                </h1>
            </div>
            <div class="modern-dashboard-actions">
                <a href="orders.php" class="modern-btn modern-btn-outline-secondary modern-btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                <li class="breadcrumb-item"><a href="order-details.php?id=<?php echo $order_id; ?>">Order #<?php echo $order_details['order_number']; ?></a></li>
                <li class="breadcrumb-item active">Cancel Order</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 text-danger">
                        <i class="fas fa-times-circle me-2"></i>Cancel Order
                    </h1>
                    <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Order
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Order Summary -->
            <div class="col-lg-6 mb-4">
                <div class="customer-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>Order Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Order Number:</strong></div>
                            <div class="col-sm-8">#<?php echo htmlspecialchars($order_details['order_number']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Order Date:</strong></div>
                            <div class="col-sm-8"><?php echo format_date($order_details['created_at']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-<?php echo $order_details['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst($order_details['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Total Amount:</strong></div>
                            <div class="col-sm-8 text-success fw-bold"><?php echo format_currency($order_details['total_amount']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Payment Method:</strong></div>
                            <div class="col-sm-8"><?php echo ucfirst($order_details['payment_method']); ?></div>
                        </div>
                        <?php if ($order_details['payment_status'] === 'paid'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Payment Refund:</strong> Since this order has been paid, the refund will be processed within 3-5 business days after cancellation.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cancellation Form -->
            <div class="col-lg-6 mb-4">
                <div class="customer-card">
                    <div class="card-header">
                        <h5 class="mb-0 text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>Cancellation Request
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-warning me-2"></i>
                            <strong>Important:</strong> Once cancelled, this order cannot be restored. Please make sure you want to proceed.
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="cancellation_reason" class="form-label">
                                    <strong>Reason for Cancellation <span class="text-danger">*</span></strong>
                                </label>
                                <select class="form-select mb-2" id="reason_select" onchange="updateReasonText()">
                                    <option value="">Select a reason...</option>
                                    <option value="Changed my mind">Changed my mind</option>
                                    <option value="Found better price elsewhere">Found better price elsewhere</option>
                                    <option value="Ordered by mistake">Ordered by mistake</option>
                                    <option value="Delivery taking too long">Delivery taking too long</option>
                                    <option value="Product no longer needed">Product no longer needed</option>
                                    <option value="Financial reasons">Financial reasons</option>
                                    <option value="Other">Other (please specify)</option>
                                </select>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" 
                                          rows="4" required placeholder="Please provide a detailed reason for cancelling this order..."><?php echo htmlspecialchars($_POST['cancellation_reason'] ?? ''); ?></textarea>
                                <small class="text-muted">This information helps us improve our service.</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_cancellation" required>
                                    <label class="form-check-label" for="confirm_cancellation">
                                        I understand that this order will be cancelled and cannot be restored.
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-2"></i>Keep Order
                                </a>
                                <button type="submit" class="btn btn-danger" onclick="return confirmCancellation()">
                                    <i class="fas fa-times-circle me-2"></i>Cancel Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancellation Policy -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="customer-card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Cancellation Policy
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">✓ What happens when you cancel:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Order status changes to "Cancelled"</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Inventory is restored for other customers</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Supplier is notified automatically</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Refund processed if payment was made</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-info">ℹ Refund Information:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-clock text-info me-2"></i>Cash on Delivery: No refund needed</li>
                                    <li><i class="fas fa-clock text-info me-2"></i>Online Payment: 3-5 business days</li>
                                    <li><i class="fas fa-clock text-info me-2"></i>Bank Transfer: 5-7 business days</li>
                                    <li><i class="fas fa-envelope text-info me-2"></i>Email confirmation sent</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </main>

<script>
function updateReasonText() {
    const select = document.getElementById('reason_select');
    const textarea = document.getElementById('cancellation_reason');
    
    if (select.value && select.value !== 'Other') {
        textarea.value = select.value;
    } else if (select.value === 'Other') {
        textarea.value = '';
        textarea.focus();
    }
}

function confirmCancellation() {
    return confirm('Are you sure you want to cancel this order? This action cannot be undone.');
}
</script>

<?php include '../includes/customer_footer.php'; ?>
