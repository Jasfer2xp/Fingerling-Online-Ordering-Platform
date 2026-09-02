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

// Get user profile and customer ID
$profile = $user->getUserProfile($user_id);
$customer_id = $customer->getCustomerIdByUserId($user_id);

// Get order ID from GET or POST
$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$issue_type = $_GET['type'] ?? $_POST['type'] ?? 'general';

if (!$order_id) {
    $_SESSION['error'] = 'Order not specified';
    redirect(base_url('customer/orders.php'));
}

// Get order details
$order_details = $order->getOrderDetails($order_id, $customer_id);

if (!$order_details) {
    $_SESSION['error'] = 'Order not found or access denied';
    redirect(base_url('customer/orders.php'));
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'submit_report') {
            $issue_description = sanitize_input($_POST['issue_description']);
            $issue_category = sanitize_input($_POST['issue_category']);
            
            if (empty($issue_description)) {
                throw new Exception('Please provide a description of the issue.');
            }
            
            // Notification logic
            date_default_timezone_set('Asia/Manila');
            $now = date('Y-m-d H:i:s');
            
            // 1. Notify Supplier
            $supplier_user_sql = "SELECT s.user_id, s.business_name FROM suppliers s 
                                 JOIN orders o ON s.id = o.supplier_id 
                                 WHERE o.id = ?";
            $supplier = $database->fetch($supplier_user_sql, [$order_id]);
            
            if ($supplier) {
                // Determine title based on category
                $title_map = [
                    'not_received' => 'URGENT: Customer Reported Order Not Received',
                    'damaged' => 'Issue Reported: Damaged Item',
                    'wrong_item' => 'Issue Reported: Wrong Item',
                    'other' => 'Issue Reported by Customer'
                ];
                $title = $title_map[$issue_category] ?? 'Issue Reported';
                $title .= " - Order #{$order_details['order_number']}";
                
                $message = "Customer has reported an issue: " . strtoupper(str_replace('_', ' ', $issue_category)) . ".\n\nDescription: " . $issue_description;
                $link = "/supplier/order-details.php?id=" . $order_id; // Assuming supplier has this page, or uses orders.php
                
                $notification_sql = "INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                                    VALUES (?, ?, ?, ?, 'order_issue', ?)";
                $database->query($notification_sql, [$supplier['user_id'], $title, $message, $link, $now]);
            }
            
            // 2. Notify Admin (Optional - Find admin user)
            // Assuming admin has role 'admin'
            $admin_user = $database->fetch("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            if ($admin_user) {
                 $admin_title = "Support Ticket: Order #{$order_details['order_number']}";
                 $database->query(
                    "INSERT INTO notifications (user_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, 'support', ?)",
                    [$admin_user['id'], $admin_title, "Customer reported issue: $issue_category. Check supplier interactions.", "/admin/orders.php", $now]
                );
            }

            // Could also update order status to something if needed, e.g., 'disputed'
            // For now, we rely on the notification.

            $_SESSION['success'] = 'Report submitted successfully. The supplier has been notified and will contact you shortly.';
            redirect(base_url('customer/order-details.php?id=' . $order_id));
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$page_title = 'Report Issue - Order #' . $order_details['order_number'];
include '../includes/customer_header.php';
// include '../includes/customer_sidebar.php'; // Optional based on layout
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-danger text-white py-3">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Report an Issue</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-light border shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Order #<?php echo htmlspecialchars($order_details['order_number']); ?></h5>
                                <p class="mb-0 text-muted small">
                                    Status: <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $order_details['status'])); ?></span> | 
                                    Updated: <?php echo date('M d, Y', strtotime($order_details['updated_at'])); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="d-block fw-bold text-primary"><?php echo number_format($order_details['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="submit_report">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        
                        <div class="mb-3">
                            <label for="issue_category" class="form-label fw-bold">What is the issue?</label>
                            <select class="form-select" id="issue_category" name="issue_category" required>
                                <option value="not_received" <?php echo ($issue_type === 'not_received') ? 'selected' : ''; ?>>I have not received my order</option>
                                <option value="damaged">Items are damaged</option>
                                <option value="wrong_item">I received the wrong item(s)</option>
                                <option value="other">Other issue</option>
                            </select>
                            <?php if ($issue_type === 'not_received'): ?>
                            <div class="form-text text-danger">
                                <i class="fas fa-info-circle"></i> Use this if the order is marked as 'Delivered' or 'Out for Delivery' for >24h but you haven't received it.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label for="issue_description" class="form-label fw-bold">Description</label>
                            <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required placeholder="Please provide more details about the issue..."></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Submit Report
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

<?php include '../includes/customer_footer.php'; ?>
