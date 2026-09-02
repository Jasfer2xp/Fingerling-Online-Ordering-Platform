<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../classes/Customer.php';
require_once '../classes/Order.php';  // Make sure Order class is loaded

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

// Handle registration completion
$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) unset($_SESSION['registration_just_completed']);

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Check if profile is complete
if (!$just_completed_registration && (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=customer&step=3'));
}

$customer = new Customer($database);
$order = new Order($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);

// Handle payment cancellation message
if (isset($_GET['payment']) && $_GET['payment'] === 'cancelled') {
    $_SESSION['info'] = 'Payment was cancelled. Your order has been marked as cancelled.';
}

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'cancel_order':
                $order_id = intval($_POST['order_id']);
                // Check if order is paid - prevent cancellation if paid
                $order_check = $database->fetch(
                    "SELECT o.status, o.id, 
                     (SELECT COUNT(*) FROM payments p WHERE p.order_id = o.id AND p.status IN ('paid', 'completed')) as payment_count,
                     (SELECT COUNT(*) FROM xendit_invoices xi WHERE xi.order_id = o.id AND xi.xendit_status = 'PAID') as invoice_count
                     FROM orders o WHERE o.id = ? AND o.customer_id = ?",
                    [$order_id, $customer_id]
                );
                
                if (!$order_check) {
                    throw new Exception('Order not found.');
                }
                
                // Check if order is paid
                $is_paid = in_array($order_check['status'], ['confirmed_and_paid', 'preparing', 'scheduled_for_delivery', 'out_for_delivery', 'delivered']) 
                          || $order_check['payment_count'] > 0 
                          || $order_check['invoice_count'] > 0;
                
                if ($is_paid) {
                    throw new Exception('Cannot cancel this order. Payment has already been received.');
                }
                
                $order->cancelOrder($order_id, $customer_id);
                $_SESSION['success'] = 'Order cancelled successfully';
                break;
            case 'confirm_received':
                $order_id = intval($_POST['order_id'] ?? 0);
                
                if (!$order_id) {
                    throw new Exception('Invalid order ID.');
                }
                
                // Verify order belongs to customer, is out for delivery, and not already confirmed
                $order_check = $database->fetch(
                    "SELECT o.id, o.status, o.order_number, o.customer_confirmed_delivery, s.user_id as supplier_user_id, s.business_name
                     FROM orders o
                     JOIN suppliers s ON o.supplier_id = s.id
                     WHERE o.id = ? AND o.customer_id = ?",
                    [$order_id, $customer_id]
                );
                
                if (!$order_check) {
                    throw new Exception('Order not found or access denied.');
                }
                
                if ($order_check['status'] !== 'out_for_delivery') {
                    throw new Exception('Order is not out for delivery. Current status: ' . $order_check['status']);
                }
                
                if (!empty($order_check['customer_confirmed_delivery'])) {
                    throw new Exception('Order has already been confirmed as received.');
                }
                
                // Update status to delivered - set customer_confirmed_delivery first
                try {
                    date_default_timezone_set('Asia/Manila');
                    $now = date('Y-m-d H:i:s');
                    
                    // Set customer_confirmed_delivery flag (required by Order class)
                    try {
                        $update_confirmed_sql = "UPDATE orders SET 
                                                customer_confirmed_delivery = TRUE,
                                                customer_confirmed_at = ?,
                                                updated_at = ?
                                                WHERE id = ?";
                        $database->query($update_confirmed_sql, [$now, $now, $order_id]);
                    } catch (Exception $e) {
                        // Column might not exist, try without it
                        if (strpos($e->getMessage(), "Unknown column") !== false) {
                            // Column doesn't exist, continue without it
                        } else {
                            throw $e;
                        }
                    }
                    
                    // Now update status to delivered using Order class
                    $order_obj = new Order($database);
                    $order_obj->updateOrderStatus($order_id, 'delivered');
                    
                    // Create notification for supplier
                    $notification_title = "Order Confirmed by Customer - Order #{$order_check['order_number']}";
                    $notification_message = "Customer has confirmed receipt of order #{$order_check['order_number']}. Order is now marked as delivered.";
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                        VALUES (?, ?, ?, 'order', ?)";
                    $database->query($notification_sql, [$order_check['supplier_user_id'], $notification_title, $notification_message, $now]);
                    
                    $_SESSION['success'] = 'Order received confirmed successfully! Thank you for your order.';
                } catch (Exception $e) {
                    throw new Exception('Failed to confirm order: ' . $e->getMessage());
                }
                break;
            case 'choose_delivery_date':
                $order_id = intval($_POST['order_id'] ?? 0);
                $chosen_date = trim($_POST['chosen_date'] ?? '');
                
                if (!$order_id || !$chosen_date) {
                    throw new Exception('Order ID and delivery date are required.');
                }
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $chosen_date)) {
                    throw new Exception('Invalid date format.');
                }
                
                // Verify order belongs to customer and get delivery window
                $order_check = $database->fetch(
                    "SELECT id, order_number, customer_id, supplier_id, supplier_delivery_from, supplier_delivery_to, customer_selected_delivery_date 
                     FROM orders 
                     WHERE id = ? AND customer_id = ?",
                    [$order_id, $customer_id]
                );
                
                if (!$order_check) {
                    throw new Exception('Order not found or access denied.');
                }
                
                if (!empty($order_check['customer_selected_delivery_date'])) {
                    throw new Exception('Delivery date has already been selected for this order.');
                }
                
                // Validate chosen date is within window
                if (empty($order_check['supplier_delivery_from']) || empty($order_check['supplier_delivery_to'])) {
                    throw new Exception('Delivery window has not been set by supplier yet.');
                }
                
                $chosen_timestamp = strtotime($chosen_date);
                $from_timestamp = strtotime($order_check['supplier_delivery_from']);
                $to_timestamp = strtotime($order_check['supplier_delivery_to']);
                
                if ($chosen_timestamp < $from_timestamp || $chosen_timestamp > $to_timestamp) {
                    throw new Exception('Selected date must be within the delivery window: ' . 
                        date('M d, Y', $from_timestamp) . ' to ' . date('M d, Y', $to_timestamp));
                }
                
                // Update order with customer's selected date
                $update_sql = "UPDATE orders SET 
                              customer_selected_delivery_date = ?,
                              delivery_date = ?,
                              updated_at = NOW()
                              WHERE id = ? AND customer_id = ?";
                $database->query($update_sql, [$chosen_date, $chosen_date, $order_id, $customer_id]);
                
                // Automatically update order status to 'preparing' when customer selects delivery date
                // This allows the order to proceed in the timeline
                // Only update if order is in a state that can transition to preparing
                try {
                    $current_status_check = $database->fetch(
                        "SELECT status FROM orders WHERE id = ?",
                        [$order_id]
                    );
                    
                    $current_status = $current_status_check['status'] ?? '';
                    error_log("Customer selected delivery date for order {$order_id}. Current status: '{$current_status}'");
                    
                    if ($current_status_check && in_array($current_status, ['scheduled_for_delivery', 'confirmed_and_paid'])) {
                        $order_obj = new Order($database);
                        $order_obj->updateOrderStatus($order_id, 'preparing');
                        
                        // Verify the status was updated - if not, force update directly
                        $verify_status = $database->fetch("SELECT status FROM orders WHERE id = ?", [$order_id]);
                        if ($verify_status && $verify_status['status'] === 'preparing') {
                            error_log("Successfully updated order {$order_id} status to 'preparing'");
                        } else {
                            error_log("WARNING: Order {$order_id} status update may have failed. Expected 'preparing', got: '" . ($verify_status['status'] ?? 'null') . "'. Forcing direct update.");
                            // Force update directly as fallback
                            $database->query("UPDATE orders SET status = 'preparing', updated_at = NOW() WHERE id = ?", [$order_id]);
                            error_log("Forced status update to 'preparing' for order {$order_id}");
                        }
                    } else {
                        error_log("Order {$order_id} status '{$current_status}' cannot transition to 'preparing' directly. Valid transitions are from: scheduled_for_delivery, confirmed_and_paid");
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the date selection
                    error_log("Failed to update order status to preparing for order {$order_id}: " . $e->getMessage());
                }
                
                // Get supplier user_id for notification
                $supplier_sql = "SELECT s.user_id, s.business_name 
                               FROM suppliers s 
                               JOIN orders o ON s.id = o.supplier_id 
                               WHERE o.id = ?";
                $supplier = $database->fetch($supplier_sql, [$order_id]);
                
                if ($supplier) {
                    date_default_timezone_set('Asia/Manila');
                    $now = date('Y-m-d H:i:s');
                    
                    // Create notification for supplier
                    $notification_title = "Customer Selected Delivery Date - Order #{$order_check['order_number']}";
                    $notification_message = "Customer has selected delivery date: " . date('M d, Y', strtotime($chosen_date)) . ". Order status has been updated to 'Preparing'.";
                    $notification_link = base_url("supplier/order-details.php?id={$order_id}");
                    
                    $notification_sql = "INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                                       VALUES (?, ?, ?, ?, 'order', ?)";
                    $database->query($notification_sql, [
                        $supplier['user_id'],
                        $notification_title,
                        $notification_message,
                        $notification_link,
                        $now
                    ]);
                }
                
                $_SESSION['success'] = 'Delivery date selected successfully! Order is now being prepared.';
                redirect(base_url('customer/order-details.php?id=' . $order_id));
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    redirect(base_url('customer/orders.php'));
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?";
$total_result = $database->fetch($count_sql, [$customer_id]);
$total_orders = $total_result['total'] ?? 0;
$total_pages = ceil($total_orders / $limit);

$orders = $order->getCustomerOrders($customer_id, null, $limit, $offset);
$recent_orders = array_filter($orders, function($order_item) {
    $order_time = strtotime($order_item['created_at']);
    // Use dynamic interval for "recent" check to match system pace
    $interval_seconds = strtotime(ORDER_TIMEOUT_INTERVAL, 0);
    return (time() - $order_time) < $interval_seconds;
});

// PERFORMANCE OPTIMIZATION: Pre-load payment and delivery window data for all orders
// This eliminates N+1 query problem (was running 3 queries per order)
$order_ids = array_column($orders, 'id');
$payment_status_map = [];
$invoice_status_map = [];
$delivery_window_map = [];
$rider_info_map = [];

if (!empty($order_ids)) {
    // Bulk fetch payment status
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $payment_sql = "SELECT DISTINCT order_id FROM payments WHERE order_id IN ($placeholders) AND status IN ('paid', 'completed')";
    $paid_orders = $database->fetchAll($payment_sql, $order_ids);
    foreach ($paid_orders as $paid) {
        $payment_status_map[$paid['order_id']] = true;
    }
    
    // Bulk fetch xendit invoice status
    $invoice_sql = "SELECT DISTINCT order_id FROM xendit_invoices WHERE order_id IN ($placeholders) AND xendit_status = 'PAID'";
    $paid_invoices = $database->fetchAll($invoice_sql, $order_ids);
    foreach ($paid_invoices as $invoice) {
        $invoice_status_map[$invoice['order_id']] = true;
    }
    
    // Bulk fetch delivery window data
    $delivery_sql = "SELECT id, supplier_delivery_from, supplier_delivery_to, customer_selected_delivery_date, 
                            delivery_selection_deadline 
                     FROM orders WHERE id IN ($placeholders)";
    $delivery_data = $database->fetchAll($delivery_sql, $order_ids);
    foreach ($delivery_data as $delivery) {
        $delivery_window_map[$delivery['id']] = $delivery;
    }
    
    // Bulk fetch rider info for out_for_delivery orders
    $rider_sql = "SELECT id, delivery_worker, delivery_phone as delivery_worker_contact, delivery_notes 
                  FROM orders WHERE id IN ($placeholders) AND status = 'out_for_delivery'";
    $rider_data = $database->fetchAll($rider_sql, $order_ids);
    foreach ($rider_data as $rider) {
        $rider_info_map[$rider['id']] = $rider;
    }
}

$page_title = 'My Orders';
include '../includes/customer_header.php';
?>
<script src="<?php echo base_url('assets/js/countdown.js'); ?>"></script>

<style>
/* Your existing beautiful styles remain unchanged */
body { background: #f9f9f9; font-family: 'Poppins', sans-serif; }
.orders-container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
.orders-header { background: linear-gradient(90deg, #ee4d2d, #ff7337); color: #fff; padding: 1rem 1.5rem; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
.orders-header h1 { font-size: 1.4rem; margin: 0; display: flex; align-items: center; gap: .5rem; font-weight: 600; }
.orders-header a { background: #fff; color: #ee4d2d; padding: .5rem 1rem; border-radius: 8px; font-weight: 500; text-decoration: none; font-size: .9rem; transition: 0.2s; }
.orders-header a:hover { background: #ffe9e3; }

.orders-list table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
.orders-list th { background: #fff5f2; padding: 1rem; text-align: left; font-weight: 600; color: #444; font-size: .95rem; }
.orders-list td { padding: 1rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.orders-list tr:hover { background: #fff9f7; }

.status-badge {
    padding: .4rem .85rem; 
    border-radius: 20px; 
    color: #fff; 
    font-size: .8rem; 
    font-weight: 500; 
    display: inline-block;
    white-space: nowrap;
    text-transform: none;
    letter-spacing: 0.3px;
}
.status-badge.pending { background: #ffc107; color: #000; }
.status-badge.confirmed { background: #17a2b8; color: #fff; }
.status-badge.paid, 
.status-badge.confirmed_and_paid { 
    background: #28a745; 
    color: #fff; 
}
.status-badge.scheduled_for_delivery { 
    background: #17a2b8; 
    color: #fff; 
}
.status-badge.preparing { 
    background: #6f42c1; 
    color: #fff; 
}
.status-badge.out_for_delivery { 
    background: #ffc107; 
    color: #000; 
}
.status-badge.delivered { 
    background: #198754; 
    color: #fff; 
}
.status-badge.cancelled,
.status-badge.archived { 
    background: #dc3545; 
    color: #fff; 
}

.order-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.order-actions a {
    background: #ee4d2d;
    color: #fff;
    padding: 0.4rem 0.9rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    display: inline-block;
    white-space: nowrap;
}

.order-actions a:hover {
    background: #ff8057;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(238, 77, 45, 0.3);
}

.order-actions a.btn-primary {
    background: #007bff;
}

.order-actions a.btn-primary:hover {
    background: #0056b3;
}

.order-actions button {
    background: #dc3545;
    color: #fff;
    padding: 0.4rem 0.9rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.order-actions button:hover {
    background: #c82333;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
}

.order-actions button[style*="background: #28a745"] {
    background: #28a745 !important;
}

.order-actions button[style*="background: #28a745"]:hover {
    background: #218838 !important;
    box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
}

.order-actions form {
    display: inline-block;
    margin: 0;
}

@media (max-width: 768px) {
    .orders-list table, .orders-list thead, .orders-list tbody, .orders-list th, .orders-list td, .orders-list tr { display: block; }
    .orders-list thead tr { position: absolute; top: -9999px; left: -9999px; }
    .orders-list tr { border: 1px solid #eee; border-radius: 12px; margin-bottom: 1rem; padding: 1rem; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .orders-list td { border: none; position: relative; padding-left: 50%; padding-top: .5rem; padding-bottom: .5rem; }
    .orders-list td::before { content: attr(data-label); position: absolute; left: 1rem; width: 45%; font-weight: 600; color: #555; text-align: left; }
    .order-actions { 
        margin-top: .5rem; 
        text-align: left;
        padding-left: 50%;
    }
    .order-actions a,
    .order-actions button {
        margin: 0.25rem 0.25rem 0.25rem 0;
    }
}
</style>

<div class="orders-container">
    <div class="orders-header">
        <h1><i class="fas fa-shopping-bag me-2"></i> My Orders</h1>
        <a href="dashboard.php">Place New Order</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>


    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= $_SESSION['info'] ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <?php if (!empty($recent_orders)): ?>
        <div class="alert alert-info alert-dismissible fade show">
            You have <?= count($recent_orders) ?> recent order<?= count($recent_orders) > 1 ? 's' : '' ?> placed within the last <?= ORDER_TIMEOUT_LABEL ?>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5 bg-white rounded-3 shadow-sm">
            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No orders yet</h4>
            <p class="text-muted">You haven't placed any orders yet.</p>
            <a href="browse.php" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order_item):
                        // Ensure status is never null or empty
                        $status = !empty($order_item['status']) ? $order_item['status'] : 'pending';
                        
                        // Use pre-loaded payment status (no queries needed)
                        $has_payment = isset($payment_status_map[$order_item['id']]) || isset($invoice_status_map[$order_item['id']]);
                        
                        // AUTO-FIX: If order has payment but status is still 'confirmed', automatically update it to 'confirmed_and_paid'
                        // This ensures status shows "Paid" immediately when payment is received (TRIGGER)
                        if ($has_payment && $status === 'confirmed') {
                            try {
                                require_once __DIR__ . '/../classes/Order.php';
                                $order_obj = new Order($database);
                                $order_obj->updateOrderStatus($order_item['id'], 'confirmed_and_paid');
                                $status = 'confirmed_and_paid';
                                // Refresh the order item data
                                $order_item['status'] = 'confirmed_and_paid';
                            } catch (Exception $e) {
                                error_log("Auto-fix order status failed: " . $e->getMessage());
                            }
                        }
                        
                        // AUTO-FIX: Also check xendit_invoices for paid invoices and update status automatically
                        if (!$has_payment && $status === 'confirmed') {
                            $xendit_check = $database->fetch(
                                "SELECT id, xendit_status FROM xendit_invoices WHERE order_id = ? AND xendit_status = 'PAID' LIMIT 1",
                                [$order_item['id']]
                            );
                            if ($xendit_check) {
                                try {
                                    require_once __DIR__ . '/../classes/Order.php';
                                    $order_obj = new Order($database);
                                    $order_obj->updateOrderStatus($order_item['id'], 'confirmed_and_paid');
                                    $status = 'confirmed_and_paid';
                                    $order_item['status'] = 'confirmed_and_paid';
                                    $has_payment = true; // Update flag
                                } catch (Exception $e) {
                                    error_log("Auto-fix order status from Xendit failed: " . $e->getMessage());
                                }
                            }
                        }
                        
                        // Order is considered paid if status is confirmed_and_paid OR has payment record
                        $is_paid = in_array($status, ['paid', 'confirmed_and_paid', 'preparing', 'scheduled_for_delivery', 'out_for_delivery', 'delivered']) || $has_payment;
                        
                        // Order needs payment if status is 'confirmed' AND no payment record exists
                        $needs_payment = ($status === 'confirmed' && !$has_payment);
                        
                        // Determine status display text - ALWAYS show something in Status column
                        // Format status text properly with proper capitalization
                        $status_display = '';
                        switch ($status) {
                            case 'pending':
                                $status_display = 'Pending';
                                break;
                            case 'confirmed':
                                $status_display = 'Awaiting Payment';
                                break;
                            case 'confirmed_and_paid':
                                $status_display = 'Paid';
                                break;
                            case 'scheduled_for_delivery':
                                $status_display = 'Scheduled for Delivery';
                                break;
                            case 'preparing':
                                $status_display = 'Preparing';
                                break;
                            case 'out_for_delivery':
                                $status_display = 'Out for Delivery';
                                break;
                            case 'delivered':
                                $status_display = 'Delivered';
                                break;
                            case 'cancelled':
                                $status_display = 'Cancelled';
                                break;
                            case 'archived':
                                $status_display = 'Archived';
                                break;
                            case 'expired':
                                $status_display = 'Expired';
                                $status = 'cancelled'; // Treat as cancelled for badge style
                                break;
                            default:
                                $status_display = ucwords(str_replace('_', ' ', $status));
                                break;
                        }

                        // Check for expired payment deadline to override status display
                        $deadline_passed = false;
                        if ($status === 'confirmed' && !empty($order_item['payment_deadline'])) {
                             if (strtotime($order_item['payment_deadline']) < time()) {
                                 $deadline_passed = true;
                                 $status_display = 'Expired';
                                 $status = 'cancelled'; // Use red badge
                                 $needs_payment = false; // Hide pay button
                             }
                        }

                        ?>
                        <tr>
                            <td data-label="Order #">
                                <a href="<?= base_url('customer/order-details.php?id=' . (int)$order_item['id']) ?>" class="text-decoration-none text-dark fw-bold">
                                    <?= $order_item['order_number'] ?>
                                </a>
                            </td>
                            <td data-label="Order Date">
                                <?= date('M j, Y', strtotime($order_item['created_at'])) ?>
                            </td>
                            <td data-label="Delivery Date">
                                <?php if (!empty($order_item['delivery_date'])): ?>
                                    <span class="text-primary fw-bold"><?= date('M j, Y', strtotime($order_item['delivery_date'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Supplier">
                                <?= htmlspecialchars($order_item['business_name']) ?>
                            </td>
                            <td data-label="Items">
                                <?= $order_item['item_count'] ?>
                            </td>
                            <td data-label="Total">
                                <?= format_currency($order_item['total_amount']) ?>
                            </td>
                            <td data-label="Status">
                                <!-- Status column: Shows status badge with proper formatting -->
                                <span class="status-badge <?= $status ?>">
                                    <?= htmlspecialchars($status_display) ?>
                                </span>
                                <?php if ($status === 'pending' && !empty($order_item['supplier_accept_deadline'])): ?>
                                    <div class="mt-1">
                                        <small class="text-muted">Supplier accept by:</small>
                                        <span class="countdown-timer" data-deadline="<?= htmlspecialchars($order_item['supplier_accept_deadline']) ?>"></span>
                                    </div>
                                <?php elseif ($status === 'confirmed' && !empty($order_item['payment_deadline'])): ?>
                                    <div class="mt-1">
                                        <small class="text-muted">Payment due:</small>
                                        <?php if ($deadline_passed): ?>
                                            <span class="text-danger fw-bold">Expired</span>
                                        <?php else: ?>
                                            <span class="countdown-timer" data-deadline="<?= htmlspecialchars($order_item['payment_deadline']) ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($status === 'out_for_delivery'): ?>
                                    <?php 
                                    // Use pre-loaded rider info (no query needed)
                                    $rider_info = $rider_info_map[$order_item['id']] ?? [];
                                    $rider_name = !empty($rider_info['delivery_worker']) ? trim($rider_info['delivery_worker']) : '';
                                    $rider_phone = '';
                                    if (!empty($rider_info['delivery_worker_contact'])) {
                                        $rider_phone = trim($rider_info['delivery_worker_contact']);
                                    } elseif (!empty($rider_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $rider_info['delivery_notes'], $matches)) {
                                        $rider_phone = trim($matches[1]);
                                    }
                                    ?>
                                    <?php if (!empty($rider_name)): ?>
                                        <div class="mt-1">
                                            <small class="text-muted d-block">Rider: <strong><?= htmlspecialchars($rider_name) ?></strong></small>
                                            <?php if (!empty($rider_phone)): ?>
                                                <small class="text-muted d-block">Phone: <strong><?= htmlspecialchars($rider_phone) ?></strong></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_item['eta_time'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-info" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-clock me-1"></i>ETA: <?= htmlspecialchars($order_item['eta_time']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif (!empty($order_item['eta_time']) && in_array($status, ['shipped'])): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-info" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                            <i class="fas fa-clock me-1"></i>ETA: <?= htmlspecialchars($order_item['eta_time']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" class="order-actions">
                                <!-- Actions column: Only shows action buttons/links -->
                                <a href="<?= base_url('customer/order-details.php?id=' . (int)$order_item['id']) ?>" title="View Details">
                                    <i class="fas fa-eye me-1"></i>View
                                </a>

                                <?php if ($needs_payment): ?>
                                    <a href="checkout.php?order_id=<?= $order_item['id'] ?>" class="btn-primary" title="Pay Now">
                                        <i class="fas fa-credit-card me-1"></i>Pay Now
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                // Use pre-loaded delivery window data (no query needed)
                                $delivery_window_check = $delivery_window_map[$order_item['id']] ?? [];
                                
                                if (!empty($delivery_window_check['supplier_delivery_from']) && 
                                    !empty($delivery_window_check['supplier_delivery_to']) && 
                                    empty($delivery_window_check['customer_selected_delivery_date'])): 
                                    // Check if deadline has passed
                                    $deadline_passed = false;
                                    if (!empty($delivery_window_check['delivery_selection_deadline'])) {
                                        $deadline_passed = strtotime($delivery_window_check['delivery_selection_deadline']) < time();
                                    }
                                    
                                    if (!$deadline_passed): ?>
                                        <a href="<?= base_url('customer/order-details.php?id=' . (int)$order_item['id'] . '&action=choose_date') ?>" class="btn btn-outline-primary btn-sm" title="Choose Delivery Date">
                                            <i class="fas fa-calendar-check me-1"></i>Choose Delivery Date
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($status === 'pending' && !$is_paid): ?>
                                    <!-- Only allow cancellation if order is pending AND not paid -->
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?= $order_item['id'] ?>">
                                        <button type="submit" title="Cancel Order">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php 
                                $hours_since_update = (time() - strtotime($order_item['updated_at'])) / 3600;
                                // Strict Logic: Button ONLY appears if Rider has uploaded proof (pending_supplier_review)
                                // We removed the 24h timeout fallback as requested.
                                $can_confirm = ($status === 'pending_supplier_review');
                                ?>
                                
                                <?php if ($can_confirm && empty($order_item['customer_confirmed_delivery'])): ?>
                                    <!-- Order Received button: Visible if Proof Uploaded OR > 24h -->
                                    <a href="<?= base_url('customer/confirm-delivery.php?order_id=' . $order_item['id']) ?>" class="btn btn-success btn-sm" title="Confirm Order Received">
                                        <i class="fas fa-check-circle me-1"></i>Order Received
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination" class="mt-4">
                <ul class="pagination justify-content-center flex-wrap gap-1">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Next
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/customer_footer.php'; ?>