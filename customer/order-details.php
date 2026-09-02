<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../classes/User.php';
require_once '../classes/Order.php';
require_once '../classes/Customer.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$order = new Order($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Get customer ID
$customer = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);

// Validate customer_id
if (!$customer_id) {
    $_SESSION['error'] = 'Customer profile not found. Please complete your registration.';
    redirect(base_url('customer/orders.php'));
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'choose_delivery_date') {
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
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect(base_url('customer/order-details.php?id=' . ($_POST['order_id'] ?? 0)));
    }
}

// Get order ID
$order_id = intval($_GET['id'] ?? 0);
if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect(base_url('customer/orders.php'));
}

// Get order details - don't redirect if action=choose_date (allow error display)
$order_details = null;
$order_exists = false;

// First check if order exists at all (without customer filter)
try {
    $order_check = $database->fetch("SELECT id, customer_id FROM orders WHERE id = ?", [$order_id]);
    $order_exists = !empty($order_check);
    
    if ($order_exists) {
        // Now check if it belongs to this customer
        $order_details = $order->getOrderDetails($order_id, $customer_id);
    }
} catch (Exception $e) {
    // Log error but don't redirect if action=choose_date
    error_log("Error fetching order details: " . $e->getMessage());
}

// Only redirect if action is NOT choose_date
if (!$order_details && !isset($_GET['action'])) {
    $_SESSION['error'] = 'Order not found or access denied.';
    redirect(base_url('customer/orders.php'));
}

// Get order items - only if order_details exists
$order_items = [];
if ($order_details) {
    $order_items = $order->getOrderItems($order_id);
}

// Recalculate subtotal and total from order items (in case DB values are stale)
$subtotal = 0;
if ($order_details) {
    foreach ($order_items as $item) {
        $subtotal += $item['price_per_piece'] * $item['quantity'];
    }
    $delivery_fee = $order_details['delivery_fee'] ?? 0;
    $total_amount = $subtotal + $delivery_fee;
    
    // Use actual payment method from DB
    $payment_method = $order_details['payment_method'] ?? 'Pending';
} else {
    $delivery_fee = 0;
    $total_amount = 0;
    $payment_method = 'Pending';
}

// Check for expiration
$is_expired = false;
if ($order_details && $order_details['status'] === 'confirmed' && !empty($order_details['payment_deadline'])) {
    if (strtotime($order_details['payment_deadline']) < time()) {
        $is_expired = true;
    }
}

// Helper function to get badge class based on status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning';
        case 'confirmed':
            return 'bg-primary';
        case 'preparing':
            return 'bg-info';
        case 'out_for_delivery':
            return 'bg-secondary';
        case 'delivered':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

$page_title = 'Order Details';
include '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h1 class="h3 mb-0"><?= isset($_GET['action']) && $_GET['action'] === 'choose_date' ? 'Choose Delivery Date' : 'Order Details' ?></h1>
                <div>
                    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                        Back to Orders
                    </a>
                    
                    <?php 
                    // Strict Visibility Logic for "Order Received"
                    $hours_since_update = (time() - strtotime($order_details['updated_at'])) / 3600;
                    $is_overdue = ($order_details['status'] === 'out_for_delivery' && $hours_since_update >= DELIVERY_CONFIRMATION_TIMEOUT_HOURS);
                    $has_proof_status = ($order_details['status'] === 'pending_supplier_review');
                    
                    // Button VISIBILITY: ONLY if proof is uploaded. (Removed timeout fallback)
                    $can_confirm = $has_proof_status && empty($order_details['customer_confirmed_delivery']);
                    ?>

                    <?php if ($can_confirm): ?>
                        <a href="confirm-delivery.php?order_id=<?= $order_id ?>" class="btn btn-success btn-sm ms-2">
                            <i class="fas fa-check-circle me-1"></i>Order Received
                        </a>
                    <?php endif; ?>

                    <?php if ($is_overdue && empty($order_details['customer_confirmed_delivery'])): ?>
                         <a href="report-issue.php?order_id=<?= $order_id ?>&type=not_received" class="btn btn-outline-danger btn-sm ms-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>Report Issue
                        </a>
                    <?php endif; ?>

                    <?php if ($order_details && $order_details['status'] === 'delivered'): ?>
                        <?php 
                        $feedback_sql = "SELECT id FROM feedback WHERE order_id = ?";
                        $existing_feedback = $database->fetch($feedback_sql, [$order_id]);
                        if (!$existing_feedback):
                        ?>
                            <a href="feedback.php?order_id=<?= $order_id ?>" class="btn btn-success btn-sm ms-2">
                                Rate Supplier
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-success btn-sm ms-2" disabled>
                                Rated
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Delivery Date Selection (if action=choose_date) -->
            <?php if (isset($_GET['action']) && $_GET['action'] === 'choose_date'): ?>
                <?php
                // First check if order exists
                if (!$order_exists): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> Order #<?= htmlspecialchars($order_id) ?> not found.
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary ms-2">Back to Orders</a>
                    </div>
                <?php else:
                    // Verify order ownership directly (don't rely on $order_details)
                    // Ensure customer_id is valid
                    if (empty($customer_id) || !is_numeric($customer_id)) {
                        error_log("Order Details Error: Invalid customer_id for user_id $user_id");
                        ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> Unable to verify your customer account. Please try logging out and logging back in.
                            <a href="orders.php" class="btn btn-sm btn-outline-secondary ms-2">Back to Orders</a>
                        </div>
                        <?php
                    } else {
                        // Use explicit type casting to ensure proper comparison
                        $order_ownership_check = $database->fetch(
                            "SELECT id, customer_id FROM orders WHERE id = ? AND customer_id = ?",
                            [intval($order_id), intval($customer_id)]
                        );
                        
                        if (!$order_ownership_check) {
                            // Log for debugging (remove in production)
                            error_log("Order Details Error: Order $order_id does not belong to customer $customer_id (user_id: $user_id)");
                            ?>
                            <div class="alert alert-danger mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error:</strong> You don't have permission to view this order. This order belongs to a different customer.
                                <a href="orders.php" class="btn btn-sm btn-outline-secondary ms-2">Back to Orders</a>
                            </div>
                            <?php
                        } else {
                        // Get delivery window - handle case where columns might not exist yet
                        $delivery_window = null;
                        try {
                            $delivery_window = $database->fetch(
                                "SELECT supplier_delivery_from, supplier_delivery_to, customer_selected_delivery_date, delivery_selection_deadline 
                                 FROM orders 
                                 WHERE id = ? AND customer_id = ?",
                                [$order_id, $customer_id]
                            );
                        } catch (Exception $e) {
                            // Columns might not exist - show helpful error
                            $error_msg = $e->getMessage();
                            if (strpos($error_msg, 'Unknown column') !== false || strpos($error_msg, 'supplier_delivery_from') !== false) {
                                $delivery_window = false;
                                ?>
                                <div class="alert alert-danger mb-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Database Migration Required:</strong> Please run the migration file:<br>
                                    <code>database/migrations/add_supplier_delivery_window_columns.sql</code>
                                    <br><br>
                                    <strong>SQL to run:</strong><br>
                                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">ALTER TABLE orders
  ADD COLUMN supplier_delivery_from DATE NULL AFTER payment_deadline,
  ADD COLUMN supplier_delivery_to DATE NULL AFTER supplier_delivery_from,
  ADD COLUMN customer_selected_delivery_date DATE NULL AFTER supplier_delivery_to,
  ADD COLUMN delivery_selection_deadline DATETIME NULL AFTER customer_selected_delivery_date;</pre>
                                </div>
                                <?php
                            } else {
                                // Re-throw if it's a different error
                                throw $e;
                            }
                        }
                    
                    if ($delivery_window && !empty($delivery_window['supplier_delivery_from']) && empty($delivery_window['customer_selected_delivery_date'])) {
                        // Check if deadline passed
                        $deadline_passed = false;
                        if (!empty($delivery_window['delivery_selection_deadline'])) {
                            $deadline_passed = strtotime($delivery_window['delivery_selection_deadline']) < time();
                        }
                        
                        if (!$deadline_passed) {
                            // Generate three date options
                            $date1 = $delivery_window['supplier_delivery_from'];
                            $date2 = date('Y-m-d', strtotime($date1 . ' +1 day'));
                            $date3 = date('Y-m-d', strtotime($date1 . ' +2 days'));
                    ?>
                    <div class="card border-0 shadow-sm bg-white mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="card-title mb-0">Choose Your Delivery Date</h5>
                                    <small class="text-muted">Pick one date within the supplier’s 3-day window.</small>
                                </div>
                            </div>
                            <form method="POST" action="<?= base_url('customer/order-details.php?id=' . $order_id) ?>" class="row g-3">
                                <input type="hidden" name="action" value="choose_delivery_date">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <div class="col-12 col-md-8">
                                    <label for="chosen_date" class="form-label fw-semibold">Select Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                        <select class="form-select" id="chosen_date" name="chosen_date" required>
                                            <option value="">-- Choose a date --</option>
                                            <option value="<?= $date1 ?>"><?= date('l, F j, Y', strtotime($date1)) ?></option>
                                            <option value="<?= $date2 ?>"><?= date('l, F j, Y', strtotime($date2)) ?></option>
                                            <option value="<?= $date3 ?>"><?= date('l, F j, Y', strtotime($date3)) ?></option>
                                        </select>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <i class="fas fa-clock me-1"></i>
                                        Delivery window: <?= date('M d, Y', strtotime($delivery_window['supplier_delivery_from'])) ?> – 
                                        <?= date('M d, Y', strtotime($delivery_window['supplier_delivery_to'])) ?>
                                    </div>
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check me-1"></i>Confirm Delivery Date
                                    </button>
                                    <a href="<?= base_url('customer/order-details.php?id=' . $order_id) ?>" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php 
                        } // End if (!$deadline_passed)
                        } elseif ($deadline_passed) {
                    ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        The delivery date selection deadline has passed. Please contact the supplier to arrange delivery.
                    </div>
                    <?php 
                        } elseif (empty($delivery_window['supplier_delivery_from'])) {
                    ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        The supplier has not set a delivery window yet. Please wait for the supplier to set the delivery dates.
                    </div>
                    <?php 
                        } elseif (!empty($delivery_window['customer_selected_delivery_date'])) {
                    ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-check-circle me-2"></i>
                        You have selected delivery date: <strong><?= date('l, F j, Y', strtotime($delivery_window['customer_selected_delivery_date'])) ?></strong>
                    </div>
                    <?php } // End if ($delivery_window && ...) ?>
                        <?php } // End else (ownership verified - delivery window fetch) ?>
                    <?php } // End else (customer_id valid) ?>
                <?php endif; // End else (order exists - uses alternative syntax) ?>
            <?php endif; // End if (action=choose_date) ?>

            <!-- Order Header -->
            <?php if ($order_details): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order #<?= htmlspecialchars($order_details['order_number']) ?></h5>
                    <span class="badge fs-6 px-3 py-2 status-<?= $is_expired ? 'cancelled' : $order_details['status'] ?>">
                        <?= $is_expired ? 'Expired' : ucfirst(str_replace('_', ' ', $order_details['status'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Information</h6>
                            <p class="mb-1"><strong>Order Date:</strong> <?= format_date($order_details['created_at']) ?></p>
                            <p class="mb-1"><strong>Payment Method:</strong> 
                                <?php
                                $payment_method_display = $order_details['payment_method'] ?? 'Pending';
                                if (str_starts_with($payment_method_display, 'Xendit - ')) {
                                    echo htmlspecialchars($payment_method_display);
                                } elseif ($payment_method_display === 'PayPal') {
                                    echo 'PayPal';
                                } elseif (strtolower($payment_method_display) === 'pending') {
                                    echo 'Pending';
                                } else {
                                    echo htmlspecialchars($payment_method_display);
                                }
                                ?>
                            </p>
                            <?php if (!empty($order_details['delivery_type'])): ?>
                                <p class="mb-1"><strong>Delivery Type:</strong> 
                                    <?php if ($order_details['delivery_type'] === 'truck'): ?>
                                        Truck Delivery
                                    <?php elseif ($order_details['delivery_type'] === 'boat'): ?>
                                        Boat Delivery
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-1"><strong>Order Status:</strong> 
                                <span class="badge <?= getStatusBadgeClass($order_details['status']) ?>">
                                    <?= $is_expired ? 'Expired' : ucfirst(str_replace('_', ' ', $order_details['status'])) ?>
                                </span>
                            </p>
                            <?php if (!empty($order_details['payment_reference'])): ?>
                                <p class="mb-1"><strong>Payment Reference:</strong> <?= htmlspecialchars($order_details['payment_reference']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Supplier Information</h6>
                            <p class="mb-1"><strong>Business:</strong> <?= htmlspecialchars($order_details['business_name'] ?? '') ?></p>
                            <p class="mb-1"><strong>Contact:</strong> <?= htmlspecialchars($order_details['supplier_contact'] ?? '') ?></p>
                            <?php if (!empty($order_details['delivery_date'])): ?>
                                <p class="mb-1"><strong>Expected Delivery:</strong> <?= format_date($order_details['delivery_date']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order_details['delivery_worker']) && trim($order_details['delivery_worker']) !== ''): ?>
                                <?php 
                                // Get rider phone from delivery_notes or delivery_worker_contact
                                $rider_phone = '';
                                if (!empty($order_details['delivery_worker_contact'])) {
                                    $rider_phone = trim($order_details['delivery_worker_contact']);
                                } elseif (!empty($order_details['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $order_details['delivery_notes'], $matches)) {
                                    $rider_phone = trim($matches[1]);
                                }
                                ?>
                                <p class="mb-1"><strong>Delivery Rider:</strong> <?= htmlspecialchars(trim($order_details['delivery_worker'])) ?></p>
                                <?php if (!empty($rider_phone)): ?>
                                    <p class="mb-1"><strong>Rider Phone:</strong> <a href="tel:<?= htmlspecialchars($rider_phone) ?>"><?= htmlspecialchars($rider_phone) ?></a></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($order_details['eta_time'])): ?>
                                <p class="mb-1"><strong>Estimated Arrival:</strong> <?= htmlspecialchars($order_details['eta_time']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary & Delivery Address -->
            <div class="row g-4 mb-4">
                <!-- Summary -->
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?= format_currency($subtotal) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Delivery Fee:</span>
                                <span><?= format_currency($delivery_fee) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-primary h5 mb-0"><?= format_currency($total_amount) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Delivery Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="mb-1"><strong>Delivery Address:</strong></p>
                                    <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($order_details['delivery_address'])) ?></p>
                                    <?php if ($order_details['delivery_notes']): ?>
                                        <p class="mb-1"><strong>Delivery Notes:</strong></p>
                                        <p class="text-muted"><?= nl2br(htmlspecialchars($order_details['delivery_notes'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Customer:</strong></p>
                                    <p class="text-muted mb-0">
                                        <?= htmlspecialchars(($order_details['first_name'] ?? '') . ' ' . ($order_details['last_name'] ?? '')) ?><br>
                                        <?= htmlspecialchars($order_details['contact_number'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Order Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product</th>
                                    <th>Species</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                // Check for uploaded product image first
                                                $image_src = '';
                                                if (!empty($item['image_path'])) {
                                                    // Handle image_path (uploaded images)
                                                    if (filter_var($item['image_path'], FILTER_VALIDATE_URL)) {
                                                        $image_src = $item['image_path'];
                                                    } else {
                                                        $image_src = base_url($item['image_path']);
                                                    }
                                                } elseif (!empty($item['image_url'])) {
                                                    // Fallback to species image_url
                                                    if (filter_var($item['image_url'], FILTER_VALIDATE_URL)) {
                                                        $image_src = $item['image_url'];
                                                    } else {
                                                        $image_src = base_url($item['image_url']);
                                                    }
                                                } else {
                                                    // Fallback to placeholder
                                                    $image_src = asset_url('images/placeholder-fish.jpg');
                                                }
                                                ?>
                                                <img src="<?= $image_src ?>" 
                                                     class="rounded me-3" style="width:50px;height:50px;object-fit:cover;" 
                                                     onerror="this.src='<?= asset_url('images/placeholder-fish.jpg') ?>';this.onerror=null;">
                                                <div>
                                                    <div class="fw-semibold"><?= !empty($item['species_name']) ? htmlspecialchars($item['species_name']) : 'Unknown Species' ?></div>
                                                    <?php if (!empty($item['scientific_name'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($item['scientific_name']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= !empty($item['species_name']) ? htmlspecialchars($item['species_name']) : 'Unknown Species' ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= number_format($item['quantity']) ?> pcs</span>
                                        </td>
                                        <td><?= format_currency($item['price_per_piece'] ?? 0) ?></td>
                                        <td class="fw-bold"><?= format_currency($item['total_price'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>



            <!-- Payment Section - Show only when order is confirmed AND not expired -->
            <?php if ($order_details && $order_details['status'] === 'confirmed' && !$is_expired): ?>
                <div class="card shadow-sm border-warning mb-4">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Required</h6>
                    </div>
                    <div class="card-body text-center">
                        <p class="lead mb-3">Your order has been confirmed by the supplier. Please proceed with payment within <?= ORDER_TIMEOUT_LABEL ?>.</p>
                        <p class="mb-3"><strong>Total Amount:</strong> <span class="h4 text-primary"><?= format_currency($total_amount) ?></span></p>
                        <a href="checkout.php?order_id=<?= $order_id ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-credit-card me-2"></i>Pay Now
                        </a>
                        <p class="text-muted mt-3 small">
                            <i class="fas fa-clock me-1"></i>Payment must be completed within <?= ORDER_TIMEOUT_LABEL ?>
                        </p>
                    </div>
                </div>
                </div>
            <?php elseif ($is_expired): ?>
                <div class="alert alert-danger mb-4">
                     <h4 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Order Expired</h4>
                     <p>The payment deadline for this order has passed. The order has been automatically cancelled/archived.</p>
                </div>
            <?php endif; ?>

            <!-- Order Timeline -->
            <?php if ($order_details): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Order Timeline</h6>
                </div>
                <div class="card-body">
                    <div class="horizontal-timeline">
                        <?php 
                        $statuses = [
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'preparing' => 'Preparing',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered'
                        ];
                        
                        $current_status = $order_details['status'];
                        $current_index = array_search($current_status, array_keys($statuses));
                        if ($current_index === false) $current_index = -1;
                        ?>
                        
                        <div class="timeline-steps">
                            <?php foreach ($statuses as $status_key => $status_label): ?>
                                <?php 
                                $status_index = array_search($status_key, array_keys($statuses));
                                $is_completed = $status_index < $current_index;
                                $is_active = $status_index === $current_index;
                                $is_future = $status_index > $current_index;
                                ?>
                                <div class="timeline-step <?= $is_completed ? 'completed' : '' ?> <?= $is_active ? 'active' : '' ?>">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-label"><?= $status_label ?></div>
                                </div>
                                <?php if ($status_index < count($statuses) - 1): ?>
                                    <div class="timeline-connector <?= $is_completed ? 'completed' : '' ?>"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($current_status === 'cancelled'): ?>
                        <div class="timeline-step cancelled mt-4">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6>Order Cancelled</h6>
                                <p class="text-muted mb-0">
                                    <?= !empty($order_details['cancelled_at']) ? format_date($order_details['cancelled_at']) : 'Order was cancelled' ?>
                                </p>
                                <?php if (!empty($order_details['cancellation_reason'])): ?>
                                    <small class="text-danger">Reason: <?= htmlspecialchars($order_details['cancellation_reason']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; // End if ($order_details) for timeline ?>
            <?php endif; // End if ($order_details) for main content ?>

        </div>
    </div>
</div>

<style>
/* Balanced Container */
.container { max-width: 1200px; }

/* Horizontal Timeline */
.horizontal-timeline {
    overflow-x: auto;
    padding: 20px 0;
}

.timeline-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    min-width: 600px;
}

.timeline-steps::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 4px;
    background-color: #e9ecef;
    z-index: 1;
}

.timeline-connector {
    flex: 1;
    height: 4px;
    background-color: #e9ecef;
    position: relative;
    z-index: 1;
}

.timeline-connector.completed {
    background-color: #007bff;
}

.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    min-width: 80px;
}

.timeline-marker {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #e9ecef;
    border: 4px solid #fff;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.timeline-step.completed .timeline-marker {
    background-color: #007bff;
    border-color: #007bff;
}

.timeline-step.active .timeline-marker {
    background-color: #007bff;
    border-color: #007bff;
    transform: scale(1.2);
    box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.3);
}

.timeline-label {
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    color: #6c757d;
    transition: all 0.3s ease;
}

.timeline-step.completed .timeline-label,
.timeline-step.active .timeline-label {
    color: #007bff;
    font-weight: 600;
}

.timeline-step.cancelled .timeline-marker {
    background-color: #dc3545;
    border-color: #dc3545;
}

.timeline-step.cancelled .timeline-content h6 {
    color: #dc3545;
}

/* Responsive */
@media (max-width: 768px) {
    .horizontal-timeline {
        padding: 15px 0;
    }
    
    .timeline-steps {
        min-width: 500px;
    }
    
    .timeline-label {
        font-size: 12px;
    }
    
    .timeline-marker {
        width: 25px;
        height: 25px;
    }
}
</style>

<?php include '../includes/customer_footer.php'; ?>