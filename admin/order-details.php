<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$order = new Order($database);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_delivery') {
    $oid = intval($_POST['order_id']);
    // Update Order Status
    $database->query("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?", [$oid]);
    // Verify Proof
    $database->query("UPDATE delivery_proofs SET admin_verified = 1 WHERE order_id = ?", [$oid]);
    
    $_SESSION['success'] = 'Order marked as Delivered successfully.';
    redirect(base_url("admin/order-details.php?id=$oid"));
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect(base_url('admin/orders.php'));
}

// Get order details
$order_details = $order->getOrderById($order_id);
if (!$order_details) {
    $_SESSION['error'] = 'Order not found.';
    redirect(base_url('admin/orders.php'));
}

// Get order items
$order_items = $order->getOrderItems($order_id);

// Try to get customer's complete address from customer_addresses table
$customer_id = $order_details['customer_id'];
$complete_address = null;

// Get customer's addresses
$address_sql = "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1";
$customer_address = $database->fetch($address_sql, [$customer_id]);

if ($customer_address) {
    // Construct complete address
    $address_parts = [];
    $address_parts[] = $customer_address['address_line_1'];
    
    if (!empty($customer_address['address_line_2'])) {
        $address_parts[] = $customer_address['address_line_2'];
    }
    
    $address_parts[] = $customer_address['barangay'] . ', ' . $customer_address['city'] . ', ' . $customer_address['province'];
    
    if (!empty($customer_address['postal_code'])) {
        $address_parts[] = 'Postal Code: ' . $customer_address['postal_code'];
    }
    
    $complete_address = implode("\n", $address_parts);
}

$page_title = 'Order Details - #' . $order_details['order_number'];
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 text-dark">
                    <i class="fas fa-receipt me-2 text-primary"></i>Order Details - #<?php echo htmlspecialchars($order_details['order_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Orders
                    </a>
                </div>
            </div>

            <!-- Order Information -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="modern-card p-4 mb-4">
                        <h4 class="text-dark mb-3">
                            <i class="fas fa-info-circle me-2 text-primary"></i>Order Information
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Order Number:</strong> #<?php echo htmlspecialchars($order_details['order_number']); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order_details['customer_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order_details['customer_email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order_details['customer_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Address:</strong><br>
                                <?php 
                                // Display complete address if available, otherwise fallback to delivery_address
                                $display_address = $complete_address ?: $order_details['delivery_address'];
                                echo nl2br(htmlspecialchars($display_address ?? 'N/A')); 
                                ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order_details['created_at'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo getStatusColor($order_details['status']); ?> px-3 py-2">
                                        <?php echo ucfirst($order_details['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Total Amount:</strong> <span class="text-success fw-bold">₱<?php echo number_format($order_details['total_amount'], 2); ?></span></p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge bg-<?php echo $order_details['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order_details['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Proof of Delivery -->
                    <?php
                    // Check for Rider Proof (New System)
                    $proof = $database->fetch("SELECT * FROM delivery_proofs WHERE order_id = ?", [$order_id]);
                    
                    // Fallback to legacy proof table if needed
                    if (!$proof) {
                         $proof_legacy = $database->fetch("SELECT * FROM proof_of_delivery WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1", [$order_id]);
                         if ($proof_legacy) {
                             // Map legacy to structure if needed, or just display separately
                              // For now, let's just stick to the new system or show legacy if exists
                         }
                    }

                    if ($proof): ?>
                    <div class="modern-card p-4 mb-4">
                        <h4 class="text-dark mb-3">
                            <i class="fas fa-camera me-2 text-primary"></i>Proof of Delivery
                        </h4>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Rider Name:</strong> <?php echo htmlspecialchars($proof['rider_name']); ?></p>
                                <p><strong>Rider Phone:</strong> <?php echo htmlspecialchars($proof['rider_phone']); ?></p>
                                <p class="text-muted"><small>Submitted: <?php echo date('M d, Y h:i A', strtotime($proof['submitted_at'])); ?></small></p>
                            </div>
                        </div>
                        <div class="mb-3 text-center bg-light p-3 rounded">
                            <img src="<?php echo base_url($proof['image_path']); ?>" 
                                 alt="Proof of Delivery" 
                                 class="img-fluid rounded shadow-sm"
                                 style="max-height: 400px;">
                        </div>
                        
                        <?php if ($order_details['status'] === 'pending_admin_verification'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i> Supplier has verified this delivery. Please confirm to finalize.
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="finalize_delivery">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <button type="submit" class="btn btn-success btn-lg w-100" onclick="return confirm('Mark this order as Delivered?');">
                                    <i class="fas fa-check-double me-2"></i>Finalize & Mark Delivered
                                </button>
                            </form>
                        <?php elseif ($proof['admin_verified']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-1"></i> Verified by Admin
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Legacy Support (keep existing block if no new proof)
                    if (!$proof) {
                        $pod_sql = "SELECT * FROM proof_of_delivery WHERE order_id = ? ORDER BY submitted_at DESC LIMIT 1";
                        $legacy_proof = $database->fetch($pod_sql, [$order_id]);
                        if ($legacy_proof): ?>
                            <div class="modern-card p-4 mb-4">
                                <h4>Proof of Delivery (Legacy)</h4>
                                <img src="<?php echo base_url($legacy_proof['photo_path']); ?>" class="img-fluid">
                            </div>
                        <?php endif; 
                    }
                    ?>

                    <!-- Order Items -->
                    <div class="modern-card p-4 mb-4">
                        <h4 class="text-dark mb-3">
                            <i class="fas fa-shopping-cart me-2 text-primary"></i>Order Items
                        </h4>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['species_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                        <td><?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="fw-bold">₱<?php echo number_format($item['total_price'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="4" class="text-end">Total Amount:</th>
                                        <th class="text-success">₱<?php echo number_format($order_details['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Delivery Information -->
                <div class="col-lg-4">
                    <div class="modern-card p-4">
                        <h4 class="text-dark mb-3">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>Delivery Information
                        </h4>
                        
                        <p><strong>Address:</strong><br>
                        <?php 
                        // Display complete address if available, otherwise fallback to delivery_address
                        $display_address = $complete_address ?: $order_details['delivery_address'];
                        echo nl2br(htmlspecialchars($display_address ?? 'N/A')); 
                        ?></p>
                        
                        <?php if (!empty($order_details['delivery_notes'])): ?>
                        <p><strong>Delivery Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order_details['delivery_notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="modern-card p-4 mt-4">
                        <h4 class="text-dark mb-3">
                            <i class="fas fa-store me-2 text-primary"></i>Supplier Information
                        </h4>
                        <p><strong>Business Name:</strong><br>
                        <?php echo htmlspecialchars($order_details['business_name']); ?></p>
                        <p><strong>Contact:</strong><br>
                        <?php echo htmlspecialchars($order_details['supplier_contact']); ?></p>
                    </div>
                </div>
            </div>
    </main>

<style>
.modern-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 15px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.modern-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}
</style>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'confirmed': return 'info';
        case 'preparing': return 'primary';
        case 'ready': return 'success';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

?>