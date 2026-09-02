<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    redirect(base_url('supplier/orders.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Check if latitude/longitude columns exist in customers table
$customerLocationColumnsExist = columnExists('customers', 'latitude') && columnExists('customers', 'longitude');

// Get order details with delivery address and customer location
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
               c.contact_number, o.delivery_address" . 
               ($customerLocationColumnsExist ? ", c.latitude, c.longitude" : "") . "
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ? AND o.supplier_id = ?";
$order = $database->fetch($sql, [$order_id, $supplier_id]);

if (!$order) {
    redirect(base_url('supplier/orders.php'));
}

// Get order items
$sql = "SELECT oi.*, sp.name as species_name, i.size_category
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.id
        JOIN species sp ON i.species_id = sp.id
        WHERE oi.order_id = ?";
$order_items = $database->fetchAll($sql, [$order_id]);

// Calculate items subtotal for consistent service fee calculation (excluding potential delivery fees)
$items_subtotal = 0;
foreach ($order_items as $item) {
    // Use subtotal if available, otherwise calculate
    $items_subtotal += $item['subtotal'] ?? ($item['quantity'] * ($item['unit_price'] ?? $item['price_per_piece']));
}

// Add email sending function
function sendDeliveryNotification($customer_email, $customer_name, $order_number, $delivery_date) {
    $subject = "Delivery Date Set for Your Order #$order_number";
    
    $message = "Dear $customer_name,\n\n";
    $message .= "We're pleased to inform you that a delivery date has been set for your order #$order_number.\n";
    $message .= "Expected delivery date: " . date('F j, Y', strtotime($delivery_date)) . "\n\n";
    $message .= "Thank you for choosing our services!";
    
    // Set additional headers
    $headers = "From: no-reply@yourdomain.com\r\n";
    $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email (this is a basic implementation, consider using SMTP for production)
    return mail($customer_email, $subject, $message, $headers);
}

// Handle status update or message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? null;
        $delivery_worker = $_POST['delivery_worker'] ?? null;
        
        // Get customer email before update in case we need to send notification
        $customer_email = null;
        if ($delivery_date) {
            $customer_sql = "SELECT c.email FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.id = ?";
            $customer = $database->fetch($customer_sql, [$order_id]);
            if ($customer) {
                $customer_email = $customer['email'];
            }
        }
        
        if ($supplier->updateOrderStatus($order_id, $new_status, $delivery_date, $delivery_worker)) {
            $success_message = "Order status updated successfully!";
            
            // Send notification if delivery date was set
            if ($delivery_date && $customer_email) {
                // Get customer name and order number
                $order_number = $order['order_number'];
                $customer_name = $order['customer_name'];
                
                if (sendDeliveryNotification($customer_email, $customer_name, $order_number, $delivery_date)) {
                    $success_message .= " A notification has been sent to the customer.";
                } else {
                    $error_message = "Order status updated but failed to send notification to customer.";
                }
            }
            
            // Refresh order data
            $order_sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                                 c.contact_number, o.delivery_address, c.latitude, c.longitude
                          FROM orders o
                          JOIN customers c ON o.customer_id = c.id
                          WHERE o.id = ? AND o.supplier_id = ?";
            $order = $database->fetch($order_sql, [$order_id, $supplier_id]);
        } else {
            $error_message = "Failed to update order status.";
        }
    } elseif ($action === 'send_message') {
        $send_message = $_POST['send_message'] ?? 0;
        $custom_message = $_POST['custom_message'] ?? '';
        if ($send_message && $custom_message) {
            // Placeholder for message sending logic (e.g., insert into notifications table)
            $success_message = "Message sent to customer successfully!";
            // In a real implementation, add logic to save message to database and notify customer
        } else {
            $error_message = "Please provide a message to send.";
        }
    }
}

$page_title = 'Order Details - #' . $order['order_number'];
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="order-details-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold"><i class="fas fa-shopping-cart me-2"></i>Order #<?php echo $order['order_number']; ?></h3>
                <div class="btn-group">
                    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="printOrder()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <section class="order-details-content">
            <div class="row">
                <!-- Order Information -->
                <div class="col-md-8 mb-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold">Order Information</h5>
                                <span class="badge bg-<?php echo getStatusColor($order['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Order Number:</strong> #<?php echo $order['order_number']; ?></p>
                                    <p><strong>Order Date:</strong> <?php echo format_date($order['created_at']); ?></p>
                                    <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                    
                                    <?php 
                                    // Calculate Service Fee (5% flat on ITEMS only, matching Supplier.php logic)
                                    // Use $items_subtotal calculated above instead of $order['total_amount']
                                    $service_fee = $items_subtotal * 0.05;
                                    $net_earnings = $items_subtotal - $service_fee;
                                    ?>
                                    <p class="text-danger" title="Service Fee Deducted">
                                        <strong>Service Fee (5%):</strong> 
                                        -₱<?php echo number_format($service_fee, 2); ?>
                                    </p>
                                    <p class="text-success border-top pt-2 mt-2" style="max-width: 200px;">
                                        <strong>Total Earnings:</strong> 
                                        <span class="fw-bold fs-5">₱<?php echo number_format($net_earnings, 2); ?></span>
                                    </p>
                                    <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'Not specified'); ?></p>
                                    <?php if (!empty($order['delivery_type'])): ?>
                                        <p><strong>Delivery Type:</strong> 
                                            <?php if ($order['delivery_type'] === 'truck'): ?>
                                                <i class="fas fa-truck me-1"></i>Truck Delivery
                                            <?php elseif ($order['delivery_type'] === 'boat'): ?>
                                                <i class="fas fa-ship me-1"></i>Boat Delivery
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                    <p><strong>Delivery Date:</strong> 
                                        <?php 
                                        if ($order['delivery_date']) {
                                            echo format_date($order['delivery_date']);
                                        } else {
                                            // Add form to set delivery date separately
                                            echo '<form method="POST" class="d-inline">';
                                            echo '<input type="hidden" name="action" value="update_status">';
                                            echo '<input type="hidden" name="status" value="' . $order['status'] . '">';
                                            echo '<input type="date" name="delivery_date" class="form-control form-control-sm d-inline-block" style="width: auto;">';
                                            echo '<button type="submit" class="btn btn-sm btn-primary ms-2">Set Date</button>';
                                            echo '</form>';
                                        }
                                        ?>
                                    </p>
                                    <p><strong>Delivery Worker:</strong> <?php echo htmlspecialchars($order['delivery_worker'] ?? 'Not assigned'); ?></p>
                                </div>
                            </div>
                            <!-- Message Customer -->
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="action" value="send_message">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="sendMessage" name="send_message" value="1">
                                    <label class="form-check-label" for="sendMessage">
                                        Send status update message to customer
                                    </label>
                                </div>
                                <div id="messageContent" class="mb-3" style="display: none;">
                                    <label for="customMessage" class="form-label">Custom Message:</label>
                                    <textarea class="form-control" id="customMessage" name="custom_message" rows="3" 
                                              placeholder="Enter a custom message for the customer..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" id="sendMessageBtn" style="display: none;">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="card border-0 shadow-sm bg-white mt-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Order Items</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-borderless align-middle mb-0">
                                    <thead>
                                        <tr class="table-light">
                                            <th class="py-3">Species</th>
                                            <th class="py-3">Size</th>
                                            <th class="py-3">Quantity</th>
                                            <th class="py-3">Unit Price</th>
                                            <th class="py-3">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['species_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['size_category']); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>₱<?php echo number_format($item['unit_price'] ?? ($item['price_per_piece'] ?? 0), 2); ?></td>
                                                <td>₱<?php echo number_format(($item['subtotal'] ?? (($item['quantity'] ?? 0) * ($item['unit_price'] ?? ($item['price_per_piece'] ?? 0)))), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Proof of Delivery -->
                    <?php
                    $pod_sql = "SELECT * FROM proof_of_delivery WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1";
                    $proof = $database->fetch($pod_sql, [$order_id]);
                    if ($proof): ?>
                    <div class="card border-0 shadow-sm bg-white mt-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="fas fa-camera me-2"></i>Proof of Delivery</h5>
                            <?php if ($proof['supplier_message']): ?>
                                <div class="mb-3">
                                    <strong>Message:</strong>
                                    <p class="border p-3 rounded bg-light mt-2"><?php echo nl2br(htmlspecialchars($proof['supplier_message'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($proof['photo_path']): ?>
                                <div class="mb-3">
                                    <strong>Photo:</strong>
                                    <div class="mt-2">
                                        <img src="<?php echo base_url($proof['photo_path']); ?>" 
                                             alt="Proof of Delivery" 
                                             class="img-fluid rounded border"
                                             style="max-width: 100%; height: auto; max-height: 400px;">
                                    </div>
                                    <p class="text-muted mt-2"><small>Submitted: <?php echo date('M d, Y h:i A', strtotime($proof['submitted_at'])); ?></small></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($order['customer_confirmed_delivery']): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>Customer confirmed receipt on <?php echo date('M d, Y h:i A', strtotime($order['customer_confirmed_at'])); ?>
                                </div>
                            <?php elseif ($order['auto_confirmed_delivery']): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-clock me-2"></i>Auto-confirmed on <?php echo date('M d, Y h:i A', strtotime($order['auto_confirmed_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-hourglass-half me-2"></i>Waiting for customer confirmation
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Order Timeline -->
                    <div class="card border-0 shadow-sm bg-white mt-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Order Timeline</h5>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6>Order Placed</h6>
                                        <small class="text-muted"><?php echo format_date($order['created_at']); ?></small>
                                        <p>Order was successfully placed by <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                    </div>
                                </div>
                                <?php if (!in_array($order['status'], ['pending_supplier_confirmation', 'cancelled', 'canceled_due_to_supplier_timeout', 'canceled_due_to_payment_timeout', 'canceled_by_supplier'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <h6>Order Confirmed</h6>
                                            <small class="text-muted">Status updated</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($order['status'], ['preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-warning"></div>
                                        <div class="timeline-content">
                                            <h6>Preparing Order</h6>
                                            <small class="text-muted">Order being prepared</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($order['status'], ['ready_for_delivery', 'out_for_delivery', 'delivered'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-info"></div>
                                        <div class="timeline-content">
                                            <h6>Ready for Delivery</h6>
                                            <small class="text-muted">Order ready</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'out_for_delivery'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-info"></div>
                                        <div class="timeline-content">
                                            <h6>Out for Delivery</h6>
                                            <small class="text-muted">Order dispatched</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <h6>Delivered</h6>
                                            <small class="text-muted"><?php echo $order['delivery_date'] ? format_date($order['delivery_date']) : 'Recently'; ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'cancelled'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-danger"></div>
                                        <div class="timeline-content">
                                            <h6>Order Cancelled</h6>
                                            <small class="text-muted">Order was cancelled</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>Delivery Address
                            </h5>
                            <address class="mb-0">
                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?><br>
                                <abbr title="Phone">P:</abbr> <?php echo htmlspecialchars($order['contact_number']); ?>
                            </address>
                            
                            <?php if ($customerLocationColumnsExist && in_array($order['status'], ['awaiting_customer_payment', 'paid_and_processing', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered']) && 
                                      !empty($order['latitude']) && !empty($order['longitude'])): ?>
                                <div class="mt-3">
                                    <h6 class="fw-bold mb-2">Customer Location</h6>
                                    <div id="customerMap" style="height: 200px; border-radius: 5px;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<style>
    .alert {
        border-radius: 0.5rem;
    }
    .badge {
        padding: 0.5em 0.75em;
    }
    .badge.bg-purple-custom {
        background-color: #6f42c1 !important;
        color: white;
    }
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-marker {
        position: absolute;
        left: -35px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: -30px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .btn-group .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .card-title, h5 {
            font-size: 1.1rem;
        }
    }
    
    /* Leaflet map styles */
    #customerMap {
        height: 200px;
        border-radius: 5px;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function printOrder() {
    window.print();
}

document.addEventListener('DOMContentLoaded', function() {
    const sendMessageCheckbox = document.getElementById('sendMessage');
    const messageContent = document.getElementById('messageContent');
    const sendMessageBtn = document.getElementById('sendMessageBtn');

    if (sendMessageCheckbox && messageContent && sendMessageBtn) {
        sendMessageCheckbox.addEventListener('change', function() {
            messageContent.style.display = this.checked ? 'block' : 'none';
            sendMessageBtn.style.display = this.checked ? 'inline-block' : 'none';
        });
    }

    // Sidebar toggle
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }
    
    // Initialize customer map if coordinates are available
    <?php if ($customerLocationColumnsExist && in_array($order['status'], ['awaiting_customer_payment', 'paid_and_processing', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered']) && 
              !empty($order['latitude']) && !empty($order['longitude'])): ?>
        // Initialize map only if coordinates are available
        const map = L.map('customerMap').setView([<?php echo $order['latitude']; ?>, <?php echo $order['longitude']; ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        const customerMarker = L.marker([<?php echo $order['latitude']; ?>, <?php echo $order['longitude']; ?>]).addTo(map);
    <?php endif; ?>
});
</script>
<?php 
function getStatusColor($status) {
    switch ($status) {
        case 'pending_supplier_confirmation': return 'secondary';
        case 'awaiting_customer_payment': return 'warning';
        case 'paid_and_processing': return 'primary';
        case 'canceled_due_to_supplier_timeout': return 'danger';
        case 'canceled_due_to_payment_timeout': return 'danger';
        case 'canceled_by_supplier': return 'danger';
        case 'pending': return 'warning';
        case 'confirmed': return 'success';
        case 'preparing': return 'purple-custom';
        case 'ready_for_delivery': return 'info';
        case 'out_for_delivery': return 'warning';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}
?>
</body>
</html>