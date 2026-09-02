<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$customer = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);
if (!$customer_id) {
    $_SESSION['error'] = 'Customer account not found.';
    redirect(base_url('customer/cart.php'));
}

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    $_SESSION['error'] = 'Invalid order.';
    redirect(base_url('customer/orders.php'));
}

// === CRITICAL: REBUILD SESSION DATA IF MISSING ===
// This fixes the "No pending_order_payment" bug
if (!isset($_SESSION['pending_order_payment'])) {
    $order = new Order($database);
    $order_data = $order->getOrderById($order_id);
    
    if (!$order_data || $order_data['customer_id'] != $customer_id || $order_data['status'] !== 'confirmed') {
        $_SESSION['error'] = 'Order not found or not ready for payment.';
        redirect(base_url('customer/orders.php'));
    }

    $items = $order->getOrderItems($order_id);
    if (empty($items)) {
        $_SESSION['error'] = 'No items in order.';
        redirect(base_url('customer/orders.php'));
    }

    $subtotal = $order_data['total_amount'] - ($order_data['payment_fee'] ?? 0);
    $payment_fee = round($subtotal * 0.02576, 2);
    $total = round($subtotal + $payment_fee, 2);

    // RECREATE THE SESSION DATA
    $_SESSION['pending_order_payment'] = [
        'order_id'       => $order_id,
        'subtotal'       => $subtotal,
        'payment_fee'    => $payment_fee,
        'total_amount'   => $total,
        'order_number'   => $order_data['order_number'],
        'business_name'  => $order_data['business_name'] ?? 'Supplier'
    ];
}

$p = $_SESSION['pending_order_payment'];
$order_id      = $p['order_id'];
$subtotal      = $p['subtotal'];
$payment_fee   = $p['payment_fee'];
$total         = $p['total_amount'];
$order_number  = $p['order_number'];
$business_name = $p['business_name'];

// Load items for display
$order = new Order($database);
$cart_items = $order->getOrderItems($order_id);
$first_item = $cart_items[0] ?? null;

// === ALWAYS RECALCULATE FEES TO PREVENT STALE 0.00 VALUES ===
$calculated_subtotal = 0;
foreach ($cart_items as $item) {
    if (isset($item['subtotal'])) {
        $calculated_subtotal += (float)$item['subtotal'];
    } else {
        $price = isset($item['price_per_piece']) ? (float)$item['price_per_piece'] : 0;
        $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0;
        $calculated_subtotal += $price * $qty;
    }
}

if ($calculated_subtotal > 0) {
    $subtotal = round($calculated_subtotal, 2);
    $payment_fee = round($subtotal * 0.02576, 2);
    $total = round($subtotal + $payment_fee, 2);

    $_SESSION['pending_order_payment']['subtotal'] = $subtotal;
    $_SESSION['pending_order_payment']['payment_fee'] = $payment_fee;
    $_SESSION['pending_order_payment']['total_amount'] = $total;
}

$product_name = $first_item['species_name'] ?? 'Fingerling Product';
$image_url = asset_url('images/placeholder-fish.jpg');
if (!empty($first_item['image_path'])) {
    $image_url = base_url(ltrim(str_replace('../', '', $first_item['image_path']), '/'));
} elseif (!empty($first_item['image_url'])) {
    $image_url = $first_item['image_url'];
}

$description = "Payment for Order #$order_number - $business_name";

$subtotal_display = number_format($subtotal, 2);
$payment_fee_display = number_format($payment_fee, 2);
$total_display = number_format($total, 2);

$page_title = 'Pay Order #' . $order_number;
include '../includes/customer_header.php';
?>

<main class="container py-5">
    <div class="row g-5 justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="bg-white rounded-3 shadow-sm p-4 h-100 d-flex flex-column">
                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($product_name); ?>" 
                     class="img-fluid rounded-3 mb-4" style="height: 300px; object-fit: cover;"
                     onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>'">
                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($product_name); ?></h5>
                <p class="text-muted small mb-0">from <strong><?php echo htmlspecialchars($business_name); ?></strong></p>
                <div class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted d-block">Order Number</small>
                    <strong class="text-primary">#<?php echo htmlspecialchars($order_number); ?></strong>
                </div>
            </div>
        </div>

        <div class="col-md-7 col-lg-5">
            <div class="bg-white rounded-3 shadow-sm p-4">
                <h6 class="text-muted mb-3">Order Summary</h6>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Subtotal</span>
                        <span>₱<?php echo $subtotal_display; ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Xendit Fee (2.576%)</span>
                        <span>₱<?php echo $payment_fee_display; ?></span>
                    </div>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between fw-bold fs-5">
                    <span>Total Amount Due</span>
                    <span class="text-primary">₱<?php echo $total_display; ?></span>
                </div>

                <div class="alert alert-info small mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Paying via <strong>Xendit</strong> — GCash, Maya, Cards, OTC
                </div>

                <form method="POST" action="<?php echo base_url('customer/process-xendit.php'); ?>" id="xenditForm">
                    <input type="hidden" name="action" value="process_xendit">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <button type="submit" class="btn btn-success w-100 py-3 fw-bold mt-4">
                        <i class="fas fa-lock me-2"></i> Pay ₱<?php echo $total_display; ?> Now
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="<?php echo base_url('customer/orders.php'); ?>" class="text-muted small">
                        ← Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Ensure form exists before adding listener
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('xenditForm');
    if (!form) {
        console.error('ERROR: xenditForm not found!');
        return;
    }
    
    console.log('Form found, action:', form.action);
    console.log('Form method:', form.method);
    
    form.addEventListener('submit', function(e) {
        console.log('=== FORM SUBMISSION STARTED ===');
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Redirecting...';
        }
        
        // Log form data
        const formData = new FormData(this);
        console.log('Form action:', this.action);
        console.log('Form method:', this.method);
        console.log('Form fields:');
        for (let [key, value] of formData.entries()) {
            console.log('  -', key, '=', value);
        }
        
        // CRITICAL: Don't prevent default - let form submit normally
        // If we preventDefault(), the form won't submit!
    });
    
    // Also add click handler to button as backup
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            console.log('=== BUTTON CLICKED ===');
            // Don't prevent default - let form submit
        });
    }
});
</script>

<?php 
// DO NOT UNSET SESSION HERE! Only process-xendit.php should do it
// unset($_SESSION['pending_order_payment']);  ← REMOVE THIS LINE IF EXISTS

include '../includes/customer_footer.php'; 
?>  