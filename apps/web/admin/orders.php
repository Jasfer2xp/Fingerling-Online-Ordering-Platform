<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_status':
                $order_id = intval($_POST['order_id']);
                $status = $_POST['status'];
                
                $order = new Order($database);
                $order->updateOrderStatus($order_id, $status);
                $_SESSION['success'] = 'Order status updated successfully';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('admin/orders.php' . ($_GET['status'] ? '?status=' . $_GET['status'] : '')));
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'supplier_id' => $_GET['supplier_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get orders
$orders = $admin->getAllOrders($filters, $limit, $offset);
$total_orders = count($admin->getAllOrders($filters)); // For pagination
$total_pages = ceil($total_orders / $limit);

// Get suppliers for filter
$sql = "SELECT id, business_name FROM suppliers WHERE status = 'approved' ORDER BY business_name";
$suppliers = $database->fetchAll($sql);

$page_title = 'Order Management';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- ====================== RESPONSIVE STYLES ====================== -->
<style>
    .admin-main-content { padding: 1rem; }
    .card { border-radius: 12px; overflow: hidden; }
    .table th { font-weight: 600; color: #f6f6f6ff; font-size: 0.875rem; }
    .table td { vertical-align: middle; font-size: 0.92rem; }

    /* Header */
    .modern-dashboard-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
        .modern-dashboard-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }

    /* Tabs */
    .nav-tabs {
        flex-wrap: wrap;
        gap: 0.5rem;
        border-bottom: none;
    }
    .nav-tabs .nav-link {
        white-space: nowrap;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    /* Active Filters */
    .active-filters .badge {
        font-size: 0.85rem;
    }

    /* Mobile: Hide desktop table, show cards */
    @media (max-width: 991.98px) {
        .desktop-table { display: none !important; }
        .mobile-card { display: block !important; }
    }
    .mobile-card { display: none; }

    /* No horizontal scroll */
    body { overflow-x: hidden; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    /* Buttons */
    .btn-group-sm .btn { padding: 0.35rem 0.5rem; }
</style>

<main class="admin-main-content">
    <div class="container-fluid px-0 px-md-4">

        <!-- Dashboard Header -->
        <div class="modern-dashboard-header">
            <div>
                <h1 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-shopping-cart text-primary"></i>
                    Order Management
                </h1>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="refreshOrders()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="exportOrders()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <div class="nav nav-tabs mb-4 flex-wrap gap-2">
            <a class="nav-link flex-fill text-center <?php echo empty($filters['status']) ? 'active' : ''; ?>" href="orders.php">
                All Orders
                <span class="badge bg-secondary ms-1"><?php echo count($admin->getAllOrders()); ?></span>
            </a>
            <a class="nav-link flex-fill text-center <?php echo $filters['status'] === 'pending' ? 'active' : ''; ?>" href="orders.php?status=pending">
                Pending
                <span class="badge bg-warning ms-1"><?php echo count($admin->getAllOrders(['status' => 'pending'])); ?></span>
            </a>
            <a class="nav-link flex-fill text-center <?php echo $filters['status'] === 'confirmed' ? 'active' : ''; ?>" href="orders.php?status=confirmed">
                Confirmed
                <span class="badge bg-info ms-1"><?php echo count($admin->getAllOrders(['status' => 'confirmed'])); ?></span>
            </a>
            <a class="nav-link flex-fill text-center <?php echo $filters['status'] === 'preparing' ? 'active' : ''; ?>" href="orders.php?status=preparing">
                Preparing
                <span class="badge bg-primary ms-1"><?php echo count($admin->getAllOrders(['status' => 'preparing'])); ?></span>
            </a>
            <a class="nav-link flex-fill text-center <?php echo $filters['status'] === 'delivered' ? 'active' : ''; ?>" href="orders.php?status=delivered">
                Delivered
                <span class="badge bg-success ms-1"><?php echo count($admin->getAllOrders(['status' => 'delivered'])); ?></span>
            </a>
        </div>

        <!-- Active Filters -->
        <?php if (array_filter($filters)): ?>
            <div class="active-filters mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small">Active filters:</span>
                    <?php foreach ($filters as $key => $value): ?>
                        <?php if ($value): ?>
                            <span class="badge bg-primary">
                                <?php echo ucfirst(str_replace('_', ' ', $key)); ?>: <?php echo htmlspecialchars($value); ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, [$key => ''])); ?>" 
                                   class="text-white ms-1 text-decoration-none">×</a>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Orders Table / Mobile Cards -->
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h3 class="text-muted">No orders found</h3>
                <p class="text-muted">Orders will appear here as customers place them.</p>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">

                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['contact_number']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['business_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['barangay'] . ', ' . $order['city']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info"><?php echo $order['item_count']; ?> items</span></td>
                                        <td><strong><?php echo format_currency($order['total_amount']); ?></strong></td>
                                        <td>
                                            <span style="border: 1px solid green; color: black;" class="badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo format_date($order['created_at']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-card">
                        <?php foreach ($orders as $order): ?>
                        <div class="border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong class="text-primary"><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    <span class="badge status-<?php echo $order['status']; ?> ms-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>

                            <div class="small text-muted mb-2">
                                <div><strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></div>
                                <div><strong>Supplier:</strong> <?php echo htmlspecialchars($order['business_name']); ?></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($order['barangay'] . ', ' . $order['city']); ?></div>
                                <div><strong>Items:</strong> <?php echo $order['item_count']; ?> · <strong>Amount:</strong> <?php echo format_currency($order['total_amount']); ?></div>
                                <div><strong>Date:</strong> <?php echo format_date($order['created_at']); ?> at <?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Orders pagination" class="mt-4">
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
</main>

<script>
function refreshOrders() {
    location.reload();
}

function clearFilters() {
    window.location.href = 'orders.php';
}

function updateOrderStatus(orderId, status) {
    let confirmMessage = '';
    switch (status) {
        case 'confirmed':
            confirmMessage = 'Confirm this order?';
            break;
        case 'cancelled':
            confirmMessage = 'Cancel this order? This action cannot be undone.';
            break;
        case 'preparing':
            confirmMessage = 'Mark this order as preparing?';
            break;
        case 'out_for_delivery':
            confirmMessage = 'Mark this order as out for delivery?';
            break;
        case 'delivered':
            confirmMessage = 'Mark this order as delivered?';
            break;
        default:
            confirmMessage = 'Update order status?';
    }
    
    if (confirm(confirmMessage)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportOrders() {
    const orders = <?php echo json_encode($orders); ?>;
    let csv = 'Order Number,Customer,Supplier,Amount,Status,Date\n';
    
    orders.forEach(order => {
        csv += `"${order.order_number}","${order.first_name} ${order.last_name}","${order.business_name}","${order.total_amount}","${order.status}","${order.created_at}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `orders_export_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Auto-refresh every 2 minutes
setInterval(function() {
    if (!document.querySelector('.modal.show')) {
        refreshOrders();
    }
}, 120000);
</script>

</body>
</html>