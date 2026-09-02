<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

$order = new Order($database);

// Get tracking number from URL
$tracking_number = $_GET['tracking'] ?? '';
$order_data = null;
$tracking_history = [];

if ($tracking_number) {
    try {
        // Get order by tracking number and verify it belongs to this customer
        $order_data = $order->getOrderByTrackingNumber($tracking_number, $customer_id);
        if ($order_data) {
            $tracking_history = $order->getTrackingHistory($order_data['id']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Order not found or access denied';
    }
}

$page_title = 'Track Order';
include '../includes/customer_header.php';
include '../includes/customer_sidebar.php';
?>

<!-- Main Content -->
<main class="role-main-content customer-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                Track Order
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <a href="orders.php" class="modern-btn modern-btn-outline-secondary modern-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

            <!-- Tracking Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <label for="tracking" class="form-label">Enter Tracking Number</label>
                            <input type="text" class="form-control" id="tracking" name="tracking" 
                                   value="<?php echo htmlspecialchars($tracking_number); ?>" 
                                   placeholder="e.g., FP-2024-001234">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Track Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($tracking_number && !$order_data): ?>
                <!-- Order Not Found -->
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Order not found!</strong> Please check your tracking number and try again.
                </div>
            <?php elseif ($order_data): ?>
                <!-- Order Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Order Number:</strong></td>
                                        <td><?php echo htmlspecialchars($order_data['order_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tracking Number:</strong></td>
                                        <td><?php echo htmlspecialchars($order_data['tracking_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order Date:</strong></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($order_data['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusColor($order_data['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order_data['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Total Amount:</strong></td>
                                        <td><?php echo format_currency($order_data['total_amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo getPaymentStatusColor($order_data['payment_status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order_data['payment_status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Delivery Method:</strong></td>
                                        <td><?php echo ucfirst($order_data['delivery_method']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Expected Delivery:</strong></td>
                                        <td>
                                            <?php if ($order_data['expected_delivery_date']): ?>
                                                <?php echo date('M j, Y', strtotime($order_data['expected_delivery_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">TBD</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tracking Timeline -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Tracking History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tracking_history)): ?>
                            <p class="text-muted">No tracking updates available yet.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($tracking_history as $index => $event): ?>
                                    <div class="timeline-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <div class="timeline-marker">
                                            <i class="fas fa-<?php echo getTrackingIcon($event['status']); ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title">
                                                <?php echo htmlspecialchars($event['title']); ?>
                                            </h6>
                                            <p class="timeline-description">
                                                <?php echo htmlspecialchars($event['description']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
                                                <?php if ($event['location']): ?>
                                                    <i class="fas fa-map-marker-alt ms-3"></i>
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Delivery Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Delivery Address</h6>
                                <address>
                                    <?php echo htmlspecialchars($order_data['delivery_address']); ?>
                                </address>
                            </div>
                            <div class="col-md-6">
                                <h6>Contact Information</h6>
                                <p>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($order_data['delivery_phone']); ?><br>
                                    <?php if ($order_data['delivery_notes']): ?>
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($order_data['delivery_notes']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $order_items = $order->getOrderItems($order_data['id']);
                        foreach ($order_items as $item): 
                        ?>
                            <div class="row align-items-center mb-3 pb-3 border-bottom">
                                <div class="col-md-2">
                                    <img src="<?php echo $item['image_url'] ?: asset_url('images/placeholder-fish.jpg'); ?>" 
                                         class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['species_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['species_name']); ?></h6>
                                    <p class="text-muted small mb-1"><?php echo htmlspecialchars($item['scientific_name']); ?></p>
                                    <span class="badge bg-info"><?php echo ucfirst($item['size_category'] ?? 'Standard'); ?></span>
                                </div>
                                <div class="col-md-2 text-center">
                                    <div class="fw-bold"><?php echo format_currency($item['price_per_piece']); ?></div>
                                    <small class="text-muted">per piece</small>
                                </div>
                                <div class="col-md-1 text-center">
                                    <span class="fw-bold"><?php echo $item['quantity']; ?></span>
                                </div>
                                <div class="col-md-1 text-end">
                                    <div class="fw-bold"><?php echo format_currency($item['subtotal']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.timeline-item.active .timeline-marker {
    background: #0d6efd;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.timeline-item.active .timeline-content {
    border-left-color: #0d6efd;
}

.timeline-title {
    margin-bottom: 5px;
    color: #495057;
}

.timeline-description {
    margin-bottom: 10px;
    color: #6c757d;
}
</style>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'confirmed': return 'info';
        case 'processing': return 'primary';
        case 'shipped': return 'success';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function getPaymentStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'paid': return 'success';
        case 'failed': return 'danger';
        case 'refunded': return 'info';
        default: return 'secondary';
    }
}

function getTrackingIcon($status) {
    switch ($status) {
        case 'order_placed': return 'shopping-cart';
        case 'confirmed': return 'check';
        case 'processing': return 'cog';
        case 'shipped': return 'truck';
        case 'out_for_delivery': return 'route';
        case 'delivered': return 'home';
        case 'cancelled': return 'times';
        default: return 'circle';
    }
}

include '../includes/customer_footer.php';
?>
