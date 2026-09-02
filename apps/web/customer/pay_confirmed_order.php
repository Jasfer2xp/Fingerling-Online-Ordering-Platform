<?php
require_once '../config/config.php';
require_once '../includes/check_session.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

if (!$customer_id) {
    $_SESSION['error'] = 'Customer profile not found.';
    redirect(base_url('auth/login.php'));
}

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    $_SESSION['error'] = 'No order specified.';
    redirect(base_url('customer/orders.php'));
}

// Get order details
$sql = "SELECT o.*, s.business_name 
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        WHERE o.id = ? AND o.customer_id = ? AND o.status = 'awaiting_customer_payment'";
$order = $database->fetch($sql, [$order_id, $customer_id]);

if (!$order) {
    $_SESSION['error'] = 'Order not found or not ready for payment.';
    redirect(base_url('customer/orders.php'));
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    if (!in_array($payment_method, ['gcash', 'paypal', 'xendit'])) {
        $_SESSION['error'] = 'Please select a valid payment method';
        redirect(base_url("customer/pay_confirmed_order.php?order_id={$order_id}"));
    }
    
    // Store order data for payment processing - CRITICAL: order_id must be included
    $_SESSION['pending_order_payment'] = [
        'order_id' => $order_id,
        'total_amount' => $order['total_amount'],
        'business_name' => $order['business_name'],
        'order_number' => $order['order_number'],
        'payment_method' => $payment_method
    ];
    
    if ($payment_method === 'paypal') {
        redirect(base_url("customer/paypal-checkout.php?order_id={$order_id}&total={$order['total_amount']}&desc=" . urlencode("Payment for Order #{$order['order_number']}")));
    }
    
    if ($payment_method === 'gcash' || $payment_method === 'xendit') {
        redirect(base_url("customer/xendit-checkout.php?order_id={$order_id}&total={$order['total_amount']}&desc=" . urlencode("Payment for Order #{$order['order_number']}")));
    }
}

$page_title = 'Pay for Confirmed Order';
include '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row">
        <?php include '../includes/customer_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Pay for Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5>Order Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Supplier:</strong> <?php echo htmlspecialchars($order['business_name']); ?></p>
                    <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                    <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Select Payment Method</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal" required>
                                    <label class="form-check-label" for="paypal">
                                        PayPal
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="gcash" value="gcash" required>
                                    <label class="form-check-label" for="gcash">
                                        GCash
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="xendit" value="xendit" required>
                                    <label class="form-check-label" for="xendit">
                                        Xendit Payments
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                        <a href="<?php echo base_url('customer/orders.php'); ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/customer_footer.php'; ?>