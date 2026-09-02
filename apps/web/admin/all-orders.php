<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$order = new Order($database);

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'supplier' => $_GET['supplier'] ?? '',
    'customer' => $_GET['customer'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get orders
$orders = $admin->getAllOrders($filters);

$page_title = 'All Orders';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">All Orders</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filtersModal">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportOrders()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count($orders); ?></h4>
                                    <p class="mb-0">Total Orders</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'pending')); ?></h4>
                                    <p class="mb-0">Pending</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'confirmed')); ?></h4>
                                    <p class="mb-0">Confirmed</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'delivered')); ?></h4>
                                    <p class="mb-0">Delivered</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-truck fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $filters['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="processing" <?php echo $filters['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $filters['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $filters['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $filters['date_from']; ?>" placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" value="<?php echo $filters['date_to']; ?>" placeholder="To Date">
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="customer" value="<?php echo htmlspecialchars($filters['customer']); ?>" placeholder="Customer name or email">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Orders List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Admin Com.</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order_item): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo str_pad($order_item['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order_item['customer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($order_item['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order_item['supplier_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($order_item['supplier_location']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $order_item['item_count']; ?> items
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo format_currency($order_item['total_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'confirmed' => 'info',
                                                'processing' => 'primary',
                                                'shipped' => 'secondary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $color = $status_colors[$order_item['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($order_item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Show Admin Commission if available
                                            if (isset($order_item['admin_commission']) && $order_item['admin_commission'] !== null) {
                                                echo '<span class="text-success fw-bold">' . format_currency($order_item['admin_commission']) . '</span>';
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($order_item['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($order_item['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewOrder(<?php echo $order_item['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="trackOrder(<?php echo $order_item['id']; ?>)">
                                                    <i class="fas fa-truck"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </main>

<script>
function viewOrder(orderId) {
    window.location.href = `order-details.php?id=${orderId}`;
}

function trackOrder(orderId) {
    window.location.href = `order-tracking.php?id=${orderId}`;
}

function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = `export-orders.php?${params.toString()}`;
}
</script>

