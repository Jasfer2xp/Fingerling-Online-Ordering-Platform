<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect(base_url('supplier/orders.php?status=delivered'));
}

// Verify order belongs to supplier and is in out_for_delivery or delivered status
$order_sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name
              FROM orders o
              JOIN customers c ON o.customer_id = c.id
              WHERE o.id = ? AND o.supplier_id = ? AND o.status IN ('out_for_delivery', 'delivered')";
$order = $database->fetch($order_sql, [$order_id, $supplier_id]);

if (!$order) {
    $_SESSION['error'] = 'Order not found or not eligible for proof of delivery.';
    redirect(base_url('supplier/orders.php?status=delivered'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proof'])) {
    try {
        $message = trim($_POST['message'] ?? '');
        $photo_uploaded = false;
        $photo_path = null;
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/proof_of_delivery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }
            
            $file_name = 'pod_' . $order_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                $photo_path = 'uploads/proof_of_delivery/' . $file_name;
                $photo_uploaded = true;
            } else {
                throw new Exception('Failed to upload photo.');
            }
        }
        
        if (!$photo_uploaded) {
            throw new Exception('Photo is required for proof of delivery.');
        }
        
        // Save proof of delivery
        date_default_timezone_set('Asia/Manila');
        $now = date('Y-m-d H:i:s');
        
        $pod_sql = "INSERT INTO proof_of_delivery (order_id, supplier_message, photo_path, submitted_at, customer_notified_at) 
                    VALUES (?, ?, ?, ?, ?)";
        $database->query($pod_sql, [$order_id, $message, $photo_path, $now, $now]);
        
        // Create notification for customer with link to confirm
        $customer_sql = "SELECT c.user_id, c.id as customer_id FROM customers c 
                        JOIN orders o ON c.id = o.customer_id 
                        WHERE o.id = ?";
        $customer = $database->fetch($customer_sql, [$order_id]);
        
        if ($customer) {
            $notification_title = "Proof of Delivery - Order #{$order['order_number']}";
            $notification_message = "Your order #{$order['order_number']} has been delivered. Please confirm receipt within 30 minutes or it will be automatically confirmed.";
            $notification_link = base_url("customer/confirm-delivery.php?order_id=" . $order_id);
            
            $notification_sql = "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'order', ?)";
            $database->query($notification_sql, [
                $customer['user_id'], 
                $customer['customer_id'],
                $notification_title, 
                $notification_message, 
                $notification_link,
                $now
            ]);
            
            // Schedule auto-confirmation after 30 minutes
            $auto_confirm_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $update_pod = "UPDATE proof_of_delivery SET auto_confirmed_at = ? WHERE order_id = ? AND submitted_at = ?";
            $database->query($update_pod, [$auto_confirm_time, $order_id, $now]);
        }
        
        $_SESSION['success'] = 'Proof of delivery submitted successfully! Customer has been notified.';
        redirect(base_url('supplier/orders.php?status=' . $order['status']));
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$page_title = 'Proof of Delivery - Order #' . $order['order_number'];
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-camera me-2"></i>Proof of Delivery</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h5>Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                            <p class="mb-0"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p class="mb-0 mt-2"><small>Upload a photo and add a message as proof of delivery. The customer will be notified and must confirm within 30 minutes.</small></p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="proofForm">
                            <div class="mb-3">
                                <label for="message" class="form-label">Message (Optional)</label>
                                <textarea class="form-control" 
                                          id="message" 
                                          name="message" 
                                          rows="3" 
                                          placeholder="Add a short message about the delivery..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="photo" class="form-label">Delivery Photo <span class="text-danger">*</span></label>
                                <input type="file" 
                                       class="form-control" 
                                       id="photo" 
                                       name="photo" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif"
                                       required>
                                <small class="form-text text-muted">Upload a photo showing the delivered items</small>
                                <div id="photoPreview" class="mt-2" style="display: none;">
                                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 300px;">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="submit_proof" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i>Submit Proof of Delivery
                                </button>
                                <a href="<?php echo base_url('supplier/orders.php?status=' . $order['status']); ?>" class="btn btn-outline-secondary">
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
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');
    const previewImg = document.getElementById('previewImg');
    
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                photoPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            photoPreview.style.display = 'none';
        }
    });
});
</script>


