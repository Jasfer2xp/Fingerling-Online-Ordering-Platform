<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect(base_url('customer/orders.php'));
}

$user_id = get_user_id();
$customer = new Customer($database);
$customer_id = $customer->getCustomerIdByUserId($user_id);
$order = new Order($database);
$order_details = $order->getOrderById($order_id, $customer_id);

if (!$order_details || $order_details['customer_id'] != $customer_id) {
    $_SESSION['error'] = 'Order not found or access denied.';
    redirect(base_url('customer/orders.php'));
}

// Check if order is paid and has delivery window
if (!in_array($order_details['status'], ['confirmed_and_paid', 'scheduled_for_delivery'])) {
    $_SESSION['error'] = 'Order must be paid before selecting delivery date.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

if (empty($order_details['delivery_date_start']) || empty($order_details['delivery_date_end'])) {
    $_SESSION['error'] = 'Delivery window not available. Please contact support.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Check if already selected
if (!empty($order_details['delivery_time_selected'])) {
    $_SESSION['info'] = 'Delivery date has already been selected.';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_datetime'])) {
    try {
        $delivery_datetime = $_POST['delivery_datetime'];
        $delivery_date = date('Y-m-d', strtotime($delivery_datetime));
        $delivery_time = date('H:i:s', strtotime($delivery_datetime));
        
        // Validate date is within window
        $start_date = new DateTime($order_details['delivery_date_start']);
        $end_date = new DateTime($order_details['delivery_date_end']);
        $end_date->modify('+1 day'); // Include end date
        $selected_date = new DateTime($delivery_date);
        
        if ($selected_date < $start_date || $selected_date >= $end_date) {
            throw new Exception('Selected date must be within the delivery window: ' . 
                date('M d, Y', strtotime($order_details['delivery_date_start'])) . ' - ' . 
                date('M d, Y', strtotime($order_details['delivery_date_end'])));
        }
        
        // Update order with selected delivery date/time and status
        $sql = "UPDATE orders SET 
                delivery_time_selected = ?,
                delivery_date = ?,
                status = 'scheduled_for_delivery',
                updated_at = NOW()
                WHERE id = ? AND customer_id = ?";
        $database->query($sql, [$delivery_datetime, $delivery_date, $order_id, $customer_id]);
        
        // Create notification for supplier
        $supplier_sql = "SELECT s.user_id, s.business_name FROM suppliers s 
                        JOIN orders o ON s.id = o.supplier_id 
                        WHERE o.id = ?";
        $supplier = $database->fetch($supplier_sql, [$order_id]);
        
        if ($supplier) {
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                VALUES (?, ?, ?, 'order', ?)";
            $notification_title = "Delivery Date Selected - Order #{$order_details['order_number']}";
            $notification_message = "Customer has selected delivery date: " . date('M d, Y h:i A', strtotime($delivery_datetime)) . ". You can now proceed to 'Preparing' status.";
            $database->query($notification_sql, [$supplier['user_id'], $notification_title, $notification_message, $manila_time]);
        }
        
        $_SESSION['success'] = 'Delivery date selected successfully!';
        redirect(base_url('customer/order-details.php?id=' . $order_id));
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$page_title = 'Select Delivery Date';
include '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Select Delivery Date & Time</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h5>Order #<?php echo htmlspecialchars($order_details['order_number']); ?></h5>
                            <p class="mb-0"><strong>Available Delivery Window:</strong><br>
                            <?php 
                            echo date('M d, Y', strtotime($order_details['delivery_date_start'])) . ' - ' . 
                                 date('M d, Y', strtotime($order_details['delivery_date_end']));
                            ?>
                            </p>
                            <p class="mt-2 mb-0"><small>Please select a specific date and time within this window.</small></p>
                        </div>
                        
                        <form method="POST" id="deliveryForm">
                            <div class="mb-3">
                                <label for="delivery_datetime" class="form-label">Select Date & Time</label>
                                <input type="datetime-local" 
                                       class="form-control" 
                                       id="delivery_datetime" 
                                       name="delivery_datetime" 
                                       required
                                       min="<?php echo $order_details['delivery_date_start'] . 'T08:00'; ?>"
                                       max="<?php echo $order_details['delivery_date_end'] . 'T18:00'; ?>">
                                <small class="form-text text-muted">Select a date and time between 8:00 AM and 6:00 PM</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i>Confirm Delivery Date
                                </button>
                                <a href="<?php echo base_url('customer/order-details.php?id=' . $order_id); ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const datetimeInput = document.getElementById('delivery_datetime');
    const form = document.getElementById('deliveryForm');
    
    // Set default to start date at 8 AM
    const startDate = '<?php echo $order_details['delivery_date_start']; ?>';
    datetimeInput.value = startDate + 'T08:00';
    
    form.addEventListener('submit', function(e) {
        const selected = new Date(datetimeInput.value);
        const hour = selected.getHours();
        
        if (hour < 8 || hour >= 18) {
            e.preventDefault();
            alert('Please select a time between 8:00 AM and 6:00 PM');
            return false;
        }
    });
});
</script>

<?php include '../includes/customer_footer.php'; ?>

