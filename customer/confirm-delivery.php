<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

// ... existing code ...



$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect(base_url('customer/orders.php'));
}

$user_id = get_user_id();
$customer_id = get_customer_id();

// Fallback: If customer_id is not in session, try to fetch it from DB using user_id
if (!$customer_id && $user_id) {
    try {
        $cust_sql = "SELECT id FROM customers WHERE user_id = ?";
        $cust_data = $database->fetch($cust_sql, [$user_id]);
        if ($cust_data) {
            $customer_id = $cust_data['id'];
            $_SESSION['customer_id'] = $customer_id; // Update session
        }
    } catch (Exception $e) {
        // Silent fail
    }
}

$order = new Order($database);
$order_details = $order->getOrderById($order_id);

if (!$order_details || $order_details['customer_id'] != $customer_id) {
    // Debugging (remove in production) - normally we wouldn't see this if redirected
    // But if redirect happens, we know this block was verified.
    $_SESSION['error'] = 'Order not found or access denied. (Order Customer: ' . ($order_details['customer_id']??'N/A') . ' vs My ID: ' . ($customer_id??'N/A') . ')';
    redirect(base_url('customer/orders.php'));
}

// Check if order is in eligible status
if (!in_array($order_details['status'], ['out_for_delivery', 'pending_supplier_review', 'delivered'])) {
    $_SESSION['error'] = 'Order is not eligible for confirmation.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Get proof of delivery
$pod_sql = "SELECT * FROM delivery_proofs WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1";
$proof = $database->fetch($pod_sql, [$order_id]);

// Strict Logic: Proof is MANDATORY. No timeout bypass.
if (!$proof) {
    $_SESSION['error'] = 'You cannot confirm delivery until the rider uploads a proof of delivery.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Check if already confirmed
if ($order_details['customer_confirmed_delivery']) {
    $_SESSION['info'] = 'Delivery has already been confirmed.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delivery'])) {
        try {
            $database->beginTransaction();
            
            date_default_timezone_set('Asia/Manila');
            $now = date('Y-m-d H:i:s');
            
            // Update order to mark customer confirmed first
            $update_confirmed_sql = "UPDATE orders SET 
                                    customer_confirmed_delivery = TRUE,
                                    customer_confirmed_at = ?,
                                    updated_at = ?
                                    WHERE id = ?";
            $database->query($update_confirmed_sql, [$now, $now, $order_id]);
            
            // Now update status to delivered 
            // Only update if not already delivered to avoid unnecessary processing
            if ($order_details['status'] !== 'delivered') {
                require_once '../classes/Order.php';
                $order_obj = new Order($database);
                $order_obj->updateOrderStatus($order_id, 'delivered');
            } else {
                 // Even if already delivered, we might want to ensure 'customer_confirmed_delivery' is the only thing we needed.
                 // But wait, what if we need to release earnings? 
                 // Order.php handled earnings release in updateOrderStatus.
                 // If we skipped it, earnings might be missed if they weren't calculated on the first 'delivered' update.
                 // Let's call it anyway, trusting our new idempotency fix in Order.php.
                 require_once '../classes/Order.php';
                 $order_obj = new Order($database);
                 $order_obj->updateOrderStatus($order_id, 'delivered');
            }
            
            // Create notification for supplier
            $supplier_user_sql = "SELECT s.user_id, s.business_name FROM suppliers s 
                                 JOIN orders o ON s.id = o.supplier_id 
                                 WHERE o.id = ?";
            $supplier = $database->fetch($supplier_user_sql, [$order_id]);
            
            if ($supplier) {
                $notification_title = "Order Confirmed by Customer - Order #{$order_details['order_number']}";
                $notification_message = "Customer has confirmed receipt of order #{$order_details['order_number']}. Earnings have been released.";
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                    VALUES (?, ?, ?, 'order', ?)";
                $database->query($notification_sql, [$supplier['user_id'], $notification_title, $notification_message, $now]);
            }
            
            $database->commit();
            
            $_SESSION['success'] = 'Delivery confirmed successfully! Thank you for your order.';
            redirect(base_url('customer/order-details.php?id=' . $order_id));
            
        } catch (Throwable $e) {
            if (method_exists($database, 'inTransaction') && $database->inTransaction()) {
                $database->rollback();
            } elseif ($database->pdo->inTransaction()) { // Fallback if wrapper doesn't expose inTransaction
                 $database->rollback();
            }
            error_log("Delivery Confirmation Error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to confirm delivery: ' . $e->getMessage();
        }
    } elseif (isset($_POST['report_not_received'])) {
        redirect(base_url('customer/report-issue.php?order_id=' . $order_id . '&type=not_received'));
    }
}

$page_title = 'Confirm Delivery - Order #' . $order_details['order_number'];
include '../includes/customer_header.php';
// sidebar if needed
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0"><i class="fas fa-box-open me-2"></i>Confirm Order Received</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-4">
                        <h5>Order #<?php echo htmlspecialchars($order_details['order_number']); ?></h5>
                        <p class="text-muted">Review the details below.</p>
                    </div>
                    
                    <?php if ($proof): ?>
                        <div class="card mb-4 border-success">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-camera me-2"></i>Proof of Delivery Uploaded
                            </div>
                            <div class="card-body text-center">
                                <img src="<?php echo base_url($proof['image_path']); ?>" 
                                     alt="Proof of Delivery" 
                                     class="img-fluid rounded border shadow-sm mb-2"
                                     style="max-height: 400px;">
                                <p class="text-muted small mt-2">
                                    Uploaded by Rider: <?php echo htmlspecialchars($proof['rider_name']); ?><br>
                                    At: <?php echo date('M d, Y h:i A', strtotime($proof['submitted_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning border-warning">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>No Proof Uploaded</h5>
                            <p>The rider has not uploaded a proof of delivery, but 24 hours have passed since the order was marked 'Out for Delivery'.</p>
                            <hr>
                            <p class="mb-0">You can confirm receipt manually if you have received the order.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="confirmForm">
                        <div class="alert alert-danger bg-light border-danger text-danger">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Warning:</strong> Only confirm if you have physically received your items. This action cannot be undone and will release payment to the supplier.
                        </div>
                        
                        <div class="d-grid gap-3">
                            <button type="submit" name="confirm_delivery" class="btn btn-success btn-lg py-3 fw-bold shadow-sm" onclick="return confirm('Are you sure you have received this order?');">
                                <i class="fas fa-check-double me-2"></i>YES, I Have Received My Order
                            </button>
                            

                            
                            <a href="<?php echo base_url('customer/order-details.php?id=' . $order_id); ?>" class="btn btn-link text-secondary">
                                Cancel & Go Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/customer_footer.php'; ?>

