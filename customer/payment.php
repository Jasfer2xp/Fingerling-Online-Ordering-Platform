<?php
require_once '../config/config.php';
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
$customer = new Customer($database);
$order = new Order($database);

// Get customer ID
$customer_id = $customer->getCustomerIdByUserId($user_id);

// Get order details
$order_id = intval($_GET['order_id'] ?? 0);
$payment_method = $_GET['method'] ?? '';

if (!$order_id || !in_array($payment_method, ['gcash', 'paypal'])) {
    $_SESSION['error'] = 'Invalid payment request.';
    redirect(base_url('customer/orders.php'));
}

// Handle Xendit payments (redirect to Xendit checkout)
if ($payment_method === 'gcash') {
    // Redirect to Xendit checkout with order details
    redirect(base_url("customer/xendit-checkout.php?order_id={$order_id}&method=gcash"));
}

// Instead of calling undefined getOrderDetails method, we'll query the database directly
$sql = "SELECT o.*, c.first_name, c.last_name 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = ? AND o.customer_id = ?";
$order_details = $database->fetch($sql, [$order_id, $user_id]);

if (!$order_details) {
    $_SESSION['error'] = 'Order not found or access denied';
    redirect(base_url('customer/orders.php'));
}

if ($order_details['payment_status'] === 'paid') {
    $_SESSION['success'] = 'This order has already been paid';
    redirect(base_url('customer/orders.php'));
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'process_payment') {
            // Simulate payment processing
            $payment_reference = 'PAY_' . time() . '_' . $order_id;
            
            // Update order payment status
            $sql = "UPDATE orders SET 
                    payment_status = 'paid', 
                    payment_reference = ?, 
                    payment_date = NOW(),
                    status = 'confirmed'
                    WHERE id = ? AND customer_id = ?";
            $database->query($sql, [$payment_reference, $order_id, $customer_id]);
            
            // Create payment record
            $sql = "INSERT INTO payments (order_id, customer_id, amount, payment_method, payment_reference, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'completed', NOW())";
            $database->query($sql, [$order_id, $customer_id, $order_details['total_amount'], $payment_method, $payment_reference]);
            
            $_SESSION['success'] = 'Payment successful! Your order has been confirmed.';
            redirect(base_url('customer/orders.php'));
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$page_title = 'Payment Processing';
include '../includes/customer_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/customer_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Payment Processing</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Order Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt"></i> Order Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Order Number:</strong></p>
                                    <p class="text-muted">#<?php echo htmlspecialchars($order_details['order_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Order Date:</strong></p>
                                    <p class="text-muted"><?php echo format_date($order_details['created_at']); ?></p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Supplier:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($order_details['business_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Total Amount:</strong></p>
                                    <h4 class="text-primary"><?php echo format_currency($order_details['total_amount']); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card"></i> 
                                <?php echo $payment_method === 'gcash' ? 'GCash Payment' : 'PayPal Payment'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($payment_method === 'gcash'): ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-mobile-alt fa-4x text-primary mb-3"></i>
                                    <h4>Pay with GCash</h4>
                                    <p class="text-muted">You will be redirected to GCash to complete your payment</p>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Payment Instructions:</strong>
                                    <ol class="mb-0 mt-2">
                                        <li>Click "Pay with GCash" button below</li>
                                        <li>You will be redirected to GCash mobile app or website</li>
                                        <li>Enter your GCash PIN to authorize payment</li>
                                        <li>You will receive a confirmation SMS</li>
                                        <li>Return to this page to see payment confirmation</li>
                                    </ol>
                                </div>
                                
                            <?php else: ?>
                                <div class="text-center mb-4">
                                    <i class="fab fa-paypal fa-4x text-info mb-3"></i>
                                    <h4>Pay with PayPal</h4>
                                    <p class="text-muted">You will be redirected to PayPal to complete your payment</p>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Payment Instructions:</strong>
                                    <ol class="mb-0 mt-2">
                                        <li>Click "Pay with PayPal" button below</li>
                                        <li>You will be redirected to PayPal website</li>
                                        <li>Log in to your PayPal account</li>
                                        <li>Review and confirm the payment</li>
                                        <li>Return to this page to see payment confirmation</li>
                                    </ol>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Validate order isn't already cash payment before showing digital payment form -->
                            <?php 
                            $currentPaymentLabel = strtolower($order_details['payment_method'] ?? '');
                            $isCashOrder = in_array($currentPaymentLabel, ['cash', 'cash on delivery'], true);
                            ?>
                            <?php if (!$isCashOrder): ?>
                            <form method="POST" id="paymentForm" class="text-center">
                                <input type="hidden" name="action" value="process_payment">
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <?php if ($payment_method === 'gcash'): ?>
                                        <i class="fas fa-mobile-alt"></i> Pay with GCash
                                    <?php else: ?>
                                        <i class="fab fa-paypal"></i> Pay with PayPal
                                    <?php endif; ?>
                                    <br>
                                    <small><?php echo format_currency($order_details['total_amount']); ?></small>
                                </button>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-lock"></i> Your payment is secured with SSL encryption
                                    </small>
                                </div>
                            </form>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Cash Payment Selected</strong>
                                <p class="mb-0 mt-2">This order is set up for cash payment on delivery. 
                                Please contact customer support if you want to change the payment method.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Security Notice -->
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <i class="fas fa-shield-alt fa-3x text-success"></i>
                                </div>
                                <div class="col-md-10">
                                    <h6>Secure Digital Payments</h6>
                                    <p class="mb-0 text-muted">
                                        We only accept digital payments through trusted payment gateways. 
                                        All transactions are protected with industry-standard encryption.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    // Check if this is a PayPal payment
    <?php if ($payment_method === 'paypal'): ?>
    // For PayPal, we should redirect to the PayPal checkout page instead
    e.preventDefault();
    window.location.href = '<?php echo base_url("customer/paypal-checkout.php?order_id={$order_id}"); ?>';
    return;
    <?php else: ?>
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
    
    // Simulate payment gateway redirect delay
    setTimeout(() => {
        // In a real implementation, this would redirect to the payment gateway
        // For demo purposes, we'll just process the payment
        this.submit();
    }, 2000);
    <?php endif; ?>
});

// Simulate payment gateway callback
function handlePaymentCallback(success, reference) {
    if (success) {
        // Payment successful
        window.location.href = '<?php echo base_url("customer/orders.php"); ?>?payment=success';
    } else {
        // Payment failed
        alert('Payment failed. Please try again.');
        location.reload();
    }
}
</script>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

.alert {
    border-radius: 0.5rem;
}

.text-center .btn {
    min-width: 250px;
}
</style>

<?php include '../includes/customer_footer.php'; ?>
