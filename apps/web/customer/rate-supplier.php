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
$orderObj = new Order($database);

// Get user profile and customer ID
$profile = $user->getUserProfile($user_id);
$customer_id = $customer->getCustomerIdByUserId($user_id);

// Get order ID from GET or POST
$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$supplier_id = intval($_GET['supplier_id'] ?? 0);

if (!$order_id) {
    $_SESSION['error'] = 'Order not specified';
    redirect(base_url('customer/orders.php'));
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $order_id = intval($_POST['order_id']);
        $rating = intval($_POST['rating']);
        $comment = sanitize_input($_POST['comment']);
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/feedback/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'feedback_' . $order_id . '_' . time() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                }
            }
        }
        
        if ($rating < 1 || $rating > 5) {
            throw new Exception('Please provide a valid rating (1-5 stars)');
        }
        
        // Verify order belongs to customer and is delivered
        $order_details = $orderObj->getOrderDetails($order_id, $customer_id);
        if (!$order_details) {
            throw new Exception('Order not found or access denied');
        }
        
        if ($order_details['status'] !== 'delivered') {
            throw new Exception('You can only rate delivered orders');
        }
        
        // Check if feedback already exists
        $sql = "SELECT id FROM feedback WHERE order_id = ?";
        $existing = $database->fetch($sql, [$order_id]);
        if ($existing) {
            throw new Exception('You have already rated this order');
        }
        
        // Insert feedback
        $sql = "INSERT INTO feedback (order_id, customer_id, supplier_id, rating, comment, image_path, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $database->query($sql, [$order_id, $customer_id, $order_details['supplier_id'], $rating, $comment, $image_path]);
        
        // Update supplier rating
        $avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(DISTINCT customer_id) as total_ratings 
                    FROM feedback 
                    WHERE supplier_id = ?";
        $rating_data = $database->fetch($avg_sql, [$order_details['supplier_id']]);
        
        $update_sql = "UPDATE suppliers 
                       SET rating = ?, total_ratings = ? 
                       WHERE id = ?";
        $database->query($update_sql, [
            $rating_data['avg_rating'], 
            $rating_data['total_ratings'], 
            $order_details['supplier_id']
        ]);
        
        $_SESSION['success'] = 'Thank you for your feedback!';
        redirect(base_url('customer/order-details.php?id=' . $order_id));
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get order details for display
$order_details = $orderObj->getOrderDetails($order_id, $customer_id);
if (!$order_details) {
    $_SESSION['error'] = 'Order not found or access denied';
    redirect(base_url('customer/orders.php'));
}

if ($order_details['status'] !== 'delivered') {
    $_SESSION['error'] = 'You can only rate delivered orders';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

// Check if feedback already exists
$sql = "SELECT id FROM feedback WHERE order_id = ?";
$existing = $database->fetch($sql, [$order_id]);
if ($existing) {
    $_SESSION['info'] = 'You have already rated this order';
    redirect(base_url('customer/order-details.php?id=' . $order_id));
}

$page_title = 'Rate Supplier - Order #' . ($order_details['order_number'] ?? 'N/A');
include '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h1 class="h3 mb-0">Rate Supplier</h1>
                <div>
                    <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Back to Order
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order #<?php echo htmlspecialchars($order_details['order_number'] ?? 'N/A'); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="feedback-form" enctype="multipart/form-data">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Supplier: <?php echo htmlspecialchars($order_details['business_name'] ?? 'Supplier'); ?></h6>
                            <p class="text-muted">Please rate your experience with this supplier</p>
                            
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <div class="rating-stars fs-3">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-rating="<?php echo $i; ?>">
                                            <i class="far fa-star"></i>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating-input" value="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comment" class="form-label">Review (Optional)</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Share your experience with this supplier..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="product_image" class="form-label">Upload Image (Optional)</label>
                                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                <div class="form-text">Upload an image of the product you received (JPG, PNG, GIF)</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>Submit Rating
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/customer_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-stars .star');
    const ratingInput = document.getElementById('rating-input');
    const submitBtn = document.getElementById('submit-btn');
    
    if (stars.length > 0 && ratingInput && submitBtn) {
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                ratingInput.value = rating;
                updateStars(rating);
                submitBtn.disabled = false;
            });
            
            star.addEventListener('mouseover', function() {
                const rating = this.getAttribute('data-rating');
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', function() {
                const rating = ratingInput.value;
                updateStars(rating);
            });
        });
    }
    
    function updateStars(rating) {
        stars.forEach((star, index) => {
            const starIcon = star.querySelector('i');
            if (index < rating) {
                starIcon.classList.remove('far');
                starIcon.classList.add('fas');
            } else {
                starIcon.classList.remove('fas');
                starIcon.classList.add('far');
            }
        });
    }
    
    function highlightStars(rating) {
        stars.forEach((star, index) => {
            const starIcon = star.querySelector('i');
            if (index < rating) {
                starIcon.classList.remove('far');
                starIcon.classList.add('fas');
            } else {
                starIcon.classList.remove('fas');
                starIcon.classList.add('far');
            }
        });
    }
});
</script>

<style>
.rating-stars .star {
    cursor: pointer;
    padding: 5px;
    transition: all 0.2s;
}

.rating-stars .star:hover {
    transform: scale(1.1);
}

.rating-stars i {
    color: #dee2e6;
    transition: all 0.2s;
}

.rating-stars i.fas {
    color: #ffc107;
}
</style>