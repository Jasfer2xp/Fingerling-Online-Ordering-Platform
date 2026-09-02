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

$user_id     = get_user_id();
$customer    = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

$order_data    = null;
$total_amount  = 0;
$order_number  = '';
$business_name = 'Fingerling Supplier';

// Log PayPal checkout page visit (similar to Xendit)
$logFile = __DIR__ . '/interactions.log';
$logEntry = date('Y-m-d H:i:s') . " | CUSTOMER | PAYPAL_CHECKOUT_PAGE | Order: {$order_id} | Customer: {$customer_id} | User: {$user_id}\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// === CRITICAL: REBUILD SESSION DATA IF MISSING (Same as Xendit) ===
if ($order_id > 0 && !isset($_SESSION['pending_order_payment'])) {
    $order_obj = new Order($database);
    $order_data_check = $order_obj->getOrderById($order_id);
    
    if ($order_data_check && $order_data_check['customer_id'] == $customer_id && in_array($order_data_check['status'], ['confirmed', 'awaiting_customer_payment'])) {
        $order_items = $order_obj->getOrderItems($order_id);
        if (!empty($order_items)) {
            $subtotal = $order_data_check['total_amount'] - ($order_data_check['payment_fee'] ?? 0);
            $paypal_fee = round(($subtotal * 0.0437) + 15.00, 2);
            $total = round($subtotal + $paypal_fee, 2);
            
            // Get supplier business name
            $supplier_info = $database->fetch("SELECT business_name FROM suppliers WHERE id = ?", [$order_data_check['supplier_id']]);
            
            $_SESSION['pending_order_payment'] = [
                'order_id'       => $order_id,
                'subtotal'       => $subtotal,
                'payment_fee'    => $paypal_fee,
                'total_amount'   => $total,
                'order_number'   => $order_data_check['order_number'],
                'business_name'  => $supplier_info['business_name'] ?? 'Supplier',
                'payment_method' => 'paypal'
            ];
            error_log("PAYPAL CHECKOUT: Rebuilt session data for order {$order_id}");
        }
    }
}

if ($order_id > 0) {
    $order_obj = new Order($database);
    $valid_statuses = ['confirmed', 'awaiting_customer_payment', 'pending_supplier_confirmation'];
    $sql = "SELECT o.*, s.business_name FROM orders o JOIN suppliers s ON o.supplier_id = s.id WHERE o.id = ? AND o.customer_id = ? AND o.status IN ('" . implode("','", $valid_statuses) . "')";
    $order_data = $database->fetch($sql, [$order_id, $customer_id]);

    if (!$order_data) {
        error_log("PAYPAL CHECKOUT: Order not found or not ready - Order ID: {$order_id} | Customer ID: {$customer_id} | User ID: {$user_id}");
        $_SESSION['error'] = 'Order not found or not ready for payment.';
        redirect(base_url('customer/orders.php'));
    }

    $order_items = $order_obj->getOrderItems($order_id);
    $total_amount = 0;
    $product_name = 'Fingerling Product';
    $product_image = asset_url('images/fingerling.jpg');
    if (!empty($order_items)) {
        $product_name = $order_items[0]['species_name'] ?? $product_name;
        if (!empty($order_items[0]['image_path'])) {
            $path = str_replace('../', '', $order_items[0]['image_path']);
            $product_image = base_url(ltrim($path, '/'));
        } elseif (!empty($order_items[0]['image_url'])) {
            $product_image = $order_items[0]['image_url'];
        }
        foreach ($order_items as $item) {
            if (isset($item['subtotal'])) {
                $total_amount += (float)$item['subtotal'];
            } else {
                $price = isset($item['price_per_piece']) ? (float)$item['price_per_piece'] : 0;
                $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0;
                $total_amount += $price * $qty;
            }
        }
    } else {
        $total_amount = floatval($order_data['total_amount']);
    }

    $order_number  = $order_data['order_number'];
    $business_name = $order_data['business_name'] ?? 'Fingerling Supplier';
} else {
    $pending = $_SESSION['pending_order_payment'] ?? null;
    if (!$pending) {
        $_SESSION['error'] = 'No pending order found.';
        redirect(base_url('customer/checkout.php'));
    }
    $total_amount  = floatval($pending['total_amount'] ?? 0);
    $order_number  = $pending['order_number'] ?? 'ORD-' . time();
    $business_name = $pending['business_name'] ?? 'Fingerling Supplier';
    $product_name = $pending['product_name'] ?? 'Fingerling Product';
    $product_image = $pending['product_image'] ?? asset_url('images/fingerling.jpg');
}

$fee         = round(($total_amount * 0.0437) + 15.00, 2);
$grand_total = $total_amount + $fee;
$grand_total_paypal = number_format($grand_total, 2, '.', '');

$description = "Fingerling Order #{$order_number}";

$page_title = 'Pay with PayPal';
include '../includes/customer_header.php';
?>

<style>
    body { 
        background: #f5f7fa; 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .checkout-wrapper {
        max-width: 1100px;
        margin: 40px auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
    }
    .product-section {
        flex: 1 1 400px;
        padding: 40px;
        background: #fff;
        border-right: 1px solid #e5e7eb;
    }
    .product-image {
        width: 100%;
        max-width: 380px;
        height: 380px;
        object-fit: cover;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        margin-bottom: 24px;
    }
    .product-title {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 12px 0;
        color: #1f2937;
    }
    .product-subtitle {
        font-size: 15px;
        color: #6b7280;
        line-height: 1.5;
    }
    .payment-section {
        flex: 1 1 420px;
        padding: 40px;
        background: #fafbfc;
    }
    .summary-box {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .summary-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 16px;
        color: #111827;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-top: 1px solid #e5e7eb;
        font-size: 20px;
        font-weight: 700;
    }
    .total-label { color: #374151; }
    .total-amount { color: #0070ba; font-size: 28px; }
    .paypal-container { margin: 24px 0; }
    .footer-text {
        font-size: 13px;
        color: #6b7280;
        text-align: center;
        margin-top: 32px;
        line-height: 1.5;
    }
    .footer-text a { color: #0070ba; text-decoration: none; }
    .footer-text a:hover { text-decoration: underline; }

    @media (max-width: 768px) {
        .checkout-wrapper { flex-direction: column; }
        .product-section { border-right: none; border-bottom: 1px solid #e5e7eb; }
        .product-image { height: 300px; }
    }
</style>

<main>
    <div class="checkout-wrapper">
        <!-- LEFT: Product Image + Details -->
        <div class="product-section">
            <img src="<?php echo htmlspecialchars($product_image ?? asset_url('images/fingerling.jpg')); ?>" alt="Fingerling Order" class="product-image" onerror="this.src='<?php echo asset_url('images/fingerling.jpg'); ?>';this.onerror=null;">
            <h1 class="product-title"><?php echo htmlspecialchars($product_name ?? 'Fingerling Order'); ?></h1>
            <p class="product-subtitle">
                Order #<?php echo htmlspecialchars($order_number); ?><br>
                Supplier: <strong><?php echo htmlspecialchars($business_name); ?></strong>
            </p>
        </div>

        <!-- RIGHT: Payment & Summary -->
        <div class="payment-section">
            <div class="summary-box">
                <h3 class="summary-title">Order summary</h3>
                
                <div style="margin: 20px 0; padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="color:#6b7280;">Order Total</span>
                        <span><?php echo format_currency($total_amount); ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#6b7280; font-size:14px;">
                        <span>PayPal Fee (4.37% + ₱15)</span>
                        <span><?php echo format_currency($fee); ?></span>
                    </div>
                </div>

                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-amount"><?php echo format_currency($grand_total); ?></span>
                </div>
            </div>

            <!-- PayPal Payment Form - Mirrors Xendit flow (Form POST instead of JavaScript SDK) -->
            <div class="paypal-container">
                <form method="POST" action="<?php echo base_url('customer/process-paypal.php'); ?>" id="paypalForm">
                    <input type="hidden" name="action" value="process_paypal">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <button type="submit" class="btn w-100 py-3 fw-bold" style="background: #FFC439; color: #003087; border: none; border-radius: 6px; font-size: 16px; font-weight: 700;">
                        <i class="fab fa-paypal me-2"></i> Pay ₱<?php echo number_format($grand_total, 2); ?> with PayPal
                    </button>
                </form>
            </div>

            <div class="footer-text">
                This product is offered and sold by the seller and is subject to their policies. Item descriptions, pictures and info are provided by the seller and not verified or guaranteed by PayPal.<br><br>
                <a href="#">Report this link</a> • <a href="#">Privacy</a><br><br>
                <strong>Powered by PayPal</strong>
            </div>
        </div>
    </div>
</main>

<script>
// Ensure form exists and handle submission (mirrors Xendit flow)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('paypalForm');
    if (!form) {
        console.error('ERROR: paypalForm not found!');
        return;
    }
    
    console.log('PayPal form found, action:', form.action);
    console.log('PayPal form method:', form.method);
    
    form.addEventListener('submit', function(e) {
        console.log('=== PAYPAL FORM SUBMISSION STARTED ===');
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Redirecting to PayPal...';
        }
        
        // Log form data
        const formData = new FormData(this);
        console.log('Form action:', this.action);
        console.log('Form method:', this.method);
        console.log('Form fields:');
        for (let [key, value] of formData.entries()) {
            console.log('  -', key, '=', value);
        }
        
        // CRITICAL: Don't prevent default - let form submit normally (like Xendit)
        // If we preventDefault(), the form won't submit!
    });
});
</script>

<?php include '../includes/customer_footer.php'; ?>
