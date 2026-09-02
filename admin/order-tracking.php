<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$order = new Order($database);

// Get all orders with tracking info
$orders = $admin->getOrdersWithTracking();

$page_title = 'Order Tracking';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Order Tracking</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshTracking()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tracking Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'confirmed')); ?></h4>
                                    <p class="mb-0">Ready to Ship</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'processing')); ?></h4>
                                    <p class="mb-0">Processing</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-cogs fa-2x"></i>
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
                                    <h4><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'shipped')); ?></h4>
                                    <p class="mb-0">In Transit</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-truck fa-2x"></i>
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
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Orders Tracking -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Active Orders Tracking</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                            <h5>No Active Orders</h5>
                            <p class="text-muted">All orders have been delivered or there are no orders to track.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($orders as $order_item): ?>
                                <?php if (in_array($order_item['status'], ['confirmed', 'processing', 'shipped'])): ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card border-left-<?php echo $order_item['status'] === 'shipped' ? 'primary' : ($order_item['status'] === 'processing' ? 'warning' : 'info'); ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="mb-1">Order #<?php echo str_pad($order_item['id'], 6, '0', STR_PAD_LEFT); ?></h6>
                                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($order_item['customer_name']); ?></p>
                                                    </div>
                                                    <span class="badge bg-<?php echo $order_item['status'] === 'shipped' ? 'primary' : ($order_item['status'] === 'processing' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($order_item['status']); ?>
                                                    </span>
                                                </div>

                                                <!-- Progress Timeline -->
                                                <div class="tracking-timeline">
                                                    <div class="timeline-item <?php echo in_array($order_item['status'], ['confirmed', 'processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                                                        <div class="timeline-marker"></div>
                                                        <div class="timeline-content">
                                                            <h6>Order Confirmed</h6>
                                                            <small class="text-muted">Order has been confirmed by supplier</small>
                                                        </div>
                                                    </div>
                                                    <div class="timeline-item <?php echo in_array($order_item['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                                                        <div class="timeline-marker"></div>
                                                        <div class="timeline-content">
                                                            <h6>Processing</h6>
                                                            <small class="text-muted">Order is being prepared</small>
                                                        </div>
                                                    </div>
                                                    <div class="timeline-item <?php echo in_array($order_item['status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                                                        <div class="timeline-marker"></div>
                                                        <div class="timeline-content">
                                                            <h6>Shipped</h6>
                                                            <small class="text-muted">Order is on the way</small>
                                                        </div>
                                                    </div>
                                                    <div class="timeline-item <?php echo $order_item['status'] === 'delivered' ? 'completed' : ''; ?>">
                                                        <div class="timeline-marker"></div>
                                                        <div class="timeline-content">
                                                            <h6>Delivered</h6>
                                                            <small class="text-muted">Order has been delivered</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <small class="text-muted">Total</small>
                                                            <div class="fw-bold"><?php echo format_currency($order_item['total_amount']); ?></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Items</small>
                                                            <div class="fw-bold"><?php echo $order_item['item_count']; ?></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Date</small>
                                                            <div class="fw-bold"><?php echo date('M j', strtotime($order_item['created_at'])); ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-3 d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewOrderDetails(<?php echo $order_item['id']; ?>)">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </main>

<style>
.border-left-primary { border-left: 4px solid #007bff !important; }
.border-left-warning { border-left: 4px solid #ffc107 !important; }
.border-left-info { border-left: 4px solid #17a2b8 !important; }

.tracking-timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -22px;
    top: 20px;
    width: 2px;
    height: calc(100% - 10px);
    background-color: #dee2e6;
}

.timeline-item.completed:not(:last-child)::before {
    background-color: #28a745;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #dee2e6;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-item.completed .timeline-marker {
    background-color: #28a745;
    box-shadow: 0 0 0 2px #28a745;
}

.timeline-content h6 {
    margin-bottom: 2px;
    font-size: 14px;
}

.timeline-content small {
    font-size: 12px;
}
</style>

<script>
function refreshTracking() {
    window.location.reload();
}

function viewOrderDetails(orderId) {
    window.location.href = `order-details.php?id=${orderId}`;
}

function updateStatus(orderId) {
    window.location.href = `update-order-status.php?id=${orderId}`;
}
</script>


