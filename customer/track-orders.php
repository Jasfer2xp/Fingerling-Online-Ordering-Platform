<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$order = new Order($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Get all active customer orders (not delivered or cancelled)
$sql = "SELECT o.*, s.business_name, s.barangay, s.city, s.supplier_contact,
               COUNT(oi.id) as item_count,
               GROUP_CONCAT(CONCAT(i.fish_type, ' (', oi.quantity, ')') SEPARATOR ', ') as items_summary
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN inventory i ON oi.inventory_id = i.id
        WHERE o.customer_id = ? AND o.status NOT IN ('delivered', 'cancelled')
        GROUP BY o.id 
        ORDER BY o.created_at DESC";
$orders = $database->fetchAll($sql, [$user_id]);

$page_title = 'Track Orders';
include '../includes/customer_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/customer_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Track Orders</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list"></i> All Orders
                        </a>
                        <a href="order-history.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-history"></i> Order History
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No active orders to track</h4>
                    <p class="text-muted">You don't have any orders that are currently being delivered.</p>
                    <a href="dashboard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search me-1"></i> Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($orders as $order_item): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card order-tracking-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Order #<?php echo htmlspecialchars($order_item['order_number']); ?></h6>
                                        <small class="text-muted"><?php echo format_date($order_item['created_at']); ?></small>
                                    </div>
                                    <span class="badge status-<?php echo $order_item['status']; ?> fs-6">
                                        <?php echo ucfirst(str_replace('_', ' ', $order_item['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Supplier Info -->
                                    <div class="supplier-info mb-3">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($order_item['business_name']); ?></h6>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($order_item['barangay'] . ', ' . $order_item['city']); ?>
                                        </p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-phone"></i> 
                                            <?php echo htmlspecialchars($order_item['supplier_contact']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Order Summary -->
                                    <div class="order-summary mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <strong><?php echo $order_item['item_count']; ?></strong> items
                                            </div>
                                            <div class="col-6 text-end">
                                                <strong><?php echo format_currency($order_item['total_amount']); ?></strong>
                                            </div>
                                        </div>
                                        <?php if ($order_item['items_summary']): ?>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($order_item['items_summary']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Order Progress -->
                                    <div class="order-progress mb-3">
                                        <div class="progress-steps">
                                            <?php
                                            $steps = [
                                                'pending' => ['icon' => 'clock', 'label' => 'Order Placed'],
                                                'confirmed' => ['icon' => 'check-circle', 'label' => 'Confirmed'],
                                                'preparing' => ['icon' => 'fish', 'label' => 'Preparing'],
                                                'ready' => ['icon' => 'box', 'label' => 'Ready'],
                                                'out_for_delivery' => ['icon' => 'truck', 'label' => 'Out for Delivery']
                                            ];
                                            
                                            $current_status = $order_item['status'];
                                            $status_order = array_keys($steps);
                                            $current_index = array_search($current_status, $status_order);
                                            ?>
                                            
                                            <?php foreach ($steps as $status => $step): ?>
                                                <?php
                                                $step_index = array_search($status, $status_order);
                                                $is_completed = $step_index <= $current_index;
                                                $is_current = $status === $current_status;
                                                ?>
                                                <div class="progress-step <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
                                                    <div class="step-icon">
                                                        <i class="fas fa-<?php echo $step['icon']; ?>"></i>
                                                    </div>
                                                    <div class="step-label"><?php echo $step['label']; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Estimated Delivery -->
                                    <?php if ($order_item['estimated_delivery']): ?>
                                        <div class="estimated-delivery mb-3">
                                            <div class="alert alert-info">
                                                <i class="fas fa-calendar-alt"></i>
                                                <strong>Estimated Delivery:</strong> 
                                                <?php echo format_date($order_item['estimated_delivery']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Order Notes -->
                                    <?php if ($order_item['notes']): ?>
                                        <div class="order-notes mb-3">
                                            <h6>Order Notes:</h6>
                                            <p class="text-muted"><?php echo htmlspecialchars($order_item['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <a href="order-details.php?id=<?php echo $order_item['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($order_item['status'] === 'pending'): ?>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="cancelOrder(<?php echo $order_item['id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-outline-info" 
                                                    onclick="contactSupplier('<?php echo htmlspecialchars($order_item['supplier_contact']); ?>', '<?php echo htmlspecialchars($order_item['business_name']); ?>')">
                                                <i class="fas fa-phone"></i> Contact
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<style>
.order-tracking-card {
    border-left: 4px solid #007bff;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    margin: 20px 0;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    color: #6c757d;
    border: 2px solid #e9ecef;
}

.step-label {
    font-size: 12px;
    text-align: center;
    color: #6c757d;
    font-weight: 500;
}

.progress-step.completed .step-icon {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.progress-step.completed .step-label {
    color: #28a745;
}

.progress-step.current .step-icon {
    background: #007bff;
    color: white;
    border-color: #007bff;
    animation: pulse 2s infinite;
}

.progress-step.current .step-label {
    color: #007bff;
    font-weight: 600;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
}

.supplier-info {
    border-left: 3px solid #007bff;
    padding-left: 15px;
}
</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        fetch('<?php echo base_url("api/orders/cancel.php"); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel order');
            }
        })
        .catch(error => {
            console.error('Cancel order error:', error);
            alert('Failed to cancel order');
        });
    }
}

function contactSupplier(phone, businessName) {
    const message = `Hello ${businessName}, I would like to inquire about my order. Thank you!`;
    const whatsappUrl = `https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
}

// Auto-refresh every 30 seconds for real-time updates
setInterval(() => {
    // Only refresh if there are active orders
    if (document.querySelectorAll('.order-tracking-card').length > 0) {
        location.reload();
    }
}, 30000);
</script>

<?php include '../includes/customer_footer.php'; ?>
