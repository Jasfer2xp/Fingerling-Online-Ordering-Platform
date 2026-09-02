<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

// Get customer ID from URL
$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    redirect(base_url('admin/customers.php'));
}

$admin = new Admin($database);
$user = new User($database);
$order = new Order($database);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = (SELECT user_id FROM customers WHERE id = ?)";
                $database->query($sql, [$status, $customer_id]);
                
                // Log status change
                if ($notes) {
                    $sql = "INSERT INTO customer_notes (customer_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())";
                    $database->query($sql, [$customer_id, get_user_id(), "Status changed to: $status. Notes: $notes"]);
                }
                
                $_SESSION['success'] = 'Customer status updated successfully';
                break;
                
            case 'add_note':
                $note = $_POST['note'];
                $sql = "INSERT INTO customer_notes (customer_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())";
                $database->query($sql, [$customer_id, get_user_id(), $note]);
                
                $_SESSION['success'] = 'Note added successfully';
                break;
        }
        
        // Redirect to prevent form resubmission
        redirect(base_url("admin/customer-details.php?id=$customer_id"));
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Get customer details
$sql = "SELECT c.*, u.email, u.status as user_status, u.created_at as registered_at
        FROM customers c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?";
$customer = $database->fetch($sql, [$customer_id]);

if (!$customer) {
    $_SESSION['error'] = 'Customer not found';
    redirect(base_url('admin/customers.php'));
}

// Get customer orders
$customer_orders = $order->getCustomerOrders($customer_id);

// Get customer statistics
$sql = "SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
            COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount END), 0) as total_spent,
            COALESCE(AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount END), 0) as avg_order_value,
            COUNT(DISTINCT o.supplier_id) as unique_suppliers
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.id = ?";
$stats = $database->fetch($sql, [$customer_id]);

// Get customer notes
$sql = "SELECT cn.*, a.full_name as admin_name
        FROM customer_notes cn
        LEFT JOIN admins a ON cn.admin_id = a.user_id
        WHERE cn.customer_id = ?
        ORDER BY cn.created_at DESC
        LIMIT 10";
$notes = $database->fetchAll($sql, [$customer_id]);

// Get recent feedback
$sql = "SELECT f.*, o.order_number, s.business_name
        FROM feedback f
        JOIN orders o ON f.order_id = o.id
        JOIN suppliers s ON f.supplier_id = s.id
        WHERE o.customer_id = ?
        ORDER BY f.created_at DESC
        LIMIT 5";
$recent_feedback = $database->fetchAll($sql, [$customer_id]);

$page_title = 'Customer Details - ' . $customer['first_name'] . ' ' . $customer['last_name'];
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Customers
                        </a>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-sticky-note"></i> Add Note
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Customer Overview -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Customer Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Full Name:</strong><br><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
                                    <p><strong>Email:</strong><br><?php echo htmlspecialchars($customer['email']); ?></p>
                                    <p><strong>Contact Number:</strong><br><?php echo htmlspecialchars($customer['contact_number'] ?? 'Not provided'); ?></p>
                                    <p><strong>Status:</strong><br>
                                        <span class="badge bg-<?php 
                                            echo $customer['user_status'] === 'active' ? 'success' : 
                                                ($customer['user_status'] === 'inactive' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($customer['user_status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Address:</strong><br><?php echo htmlspecialchars($customer['address'] ?? 'Not provided'); ?></p>
                                    <p><strong>Location:</strong><br>
                                        <?php 
                                        $location_parts = array_filter([
                                            $customer['barangay'],
                                            $customer['city'],
                                            $customer['province']
                                        ]);
                                        echo htmlspecialchars(implode(', ', $location_parts) ?: 'Not provided');
                                        ?>
                                    </p>
                                    <p><strong>Registered:</strong><br><?php echo date('M j, Y g:i A', strtotime($customer['registered_at'])); ?></p>
                                    <p><strong>Last Updated:</strong><br><?php echo date('M j, Y g:i A', strtotime($customer['updated_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="h4 text-primary"><?php echo number_format($stats['total_orders']); ?></div>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h4 text-success"><?php echo number_format($stats['completed_orders']); ?></div>
                                    <small class="text-muted">Completed</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h4 text-warning">₱<?php echo number_format($stats['total_spent'], 2); ?></div>
                                    <small class="text-muted">Total Spent</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h4 text-info">₱<?php echo number_format($stats['avg_order_value'], 2); ?></div>
                                    <small class="text-muted">Avg Order</small>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <div class="h5 text-secondary"><?php echo number_format($stats['unique_suppliers']); ?></div>
                                <small class="text-muted">Unique Suppliers</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Management -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cog"></i> Status Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" name="status" id="status" required>
                                        <option value="active" <?php echo $customer['user_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $customer['user_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $customer['user_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" name="notes" id="notes" rows="3" placeholder="Add notes about this status change..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments"></i> Admin Notes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notes)): ?>
                                <p class="text-muted text-center py-3">No notes yet</p>
                            <?php else: ?>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($notes as $note): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                                                <?php if ($note['admin_name']): ?>
                                                    by <?php echo htmlspecialchars($note['admin_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-success mt-2" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                <i class="fas fa-plus"></i> Add Note
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart"></i> Recent Orders
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($customer_orders)): ?>
                                <p class="text-muted text-center py-3">No orders yet</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Supplier</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($customer_orders, 0, 10) as $order): ?>
                                                <tr>
                                                    <td><small>#<?php echo $order['order_number']; ?></small></td>
                                                    <td><small><?php echo htmlspecialchars($order['business_name']); ?></small></td>
                                                    <td><small>₱<?php echo number_format($order['total_amount'], 2); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?php
                                                            echo $order['status'] === 'delivered' ? 'success' :
                                                                ($order['status'] === 'pending' ? 'warning' :
                                                                ($order['status'] === 'cancelled' ? 'danger' : 'info'));
                                                        ?> badge-sm">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small></td>
                                                    <td>
                                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($customer_orders) > 10): ?>
                                    <div class="text-center mt-3">
                                        <a href="orders.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary">
                                            View All Orders (<?php echo count($customer_orders); ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-star"></i> Recent Feedback
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_feedback)): ?>
                                <p class="text-muted text-center py-3">No feedback yet</p>
                            <?php else: ?>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($recent_feedback as $feedback): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <small class="text-muted">Order #<?php echo $feedback['order_number']; ?></small>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-muted'; ?>" style="font-size: 0.8rem;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($feedback['business_name']); ?></small>
                                            <?php if ($feedback['comment']): ?>
                                                <p class="mb-1 mt-1" style="font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($feedback['comment'])); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($feedback['created_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    </main>
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sticky-note"></i> Add Admin Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="note" class="form-label">Note</label>
                        <textarea class="form-control" name="note" id="note" rows="4" placeholder="Add a note about this customer..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.rating .fa-star {
    font-size: 0.9rem;
}
</style>

