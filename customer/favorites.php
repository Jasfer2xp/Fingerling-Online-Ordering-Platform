<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$customer = new Customer($database);

// Get customer profile
$profile = $customer->getCustomerProfile($user_id);

// Get customer ID
$sql = "SELECT id FROM customers WHERE user_id = ?";
$customer_data = $database->fetch($sql, [$user_id]);
$customer_id = $customer_data['id'];

// Handle remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_favorite') {
    $product_id = intval($_POST['product_id']);
    
    $sql = "DELETE FROM favorites WHERE customer_id = ? AND product_id = ?";
    if ($database->query($sql, [$customer_id, $product_id])) {
        $success_message = "Product removed from favorites successfully!";
    } else {
        $error_message = "Failed to remove product from favorites.";
    }
}

// Get favorite products
$sql = "SELECT f.*, i.*, sp.name as species_name, sp.scientific_name, s.business_name, s.id as supplier_id
        FROM favorites f
        JOIN inventory i ON f.product_id = i.id
        JOIN species sp ON i.species_id = sp.id
        JOIN suppliers s ON i.supplier_id = s.id
        WHERE f.customer_id = ?
        ORDER BY f.created_at DESC";
$favorites = $database->fetchAll($sql, [$customer_id]);

$page_title = 'My Favorites';
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
                        <i class="fas fa-heart"></i>
                    </div>
                    My Favorites
                </h1>
            </div>
            <div class="modern-dashboard-actions">
                <a href="dashboard.php" class="modern-btn modern-btn-primary modern-btn-sm">
                    <i class="fas fa-search"></i> Browse Products
                </a>
            </div>
        </div>

        <div class="container-fluid px-4">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($favorites)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-heart text-muted" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="text-muted">No Favorites Yet</h3>
                    <p class="text-muted mb-4">You haven't added any products to your favorites list.</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($favorites as $favorite): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <?php if ($favorite['image_url']): ?>
                                    <img src="<?php echo base_url($favorite['image_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($favorite['species_name']); ?>"
                                         style="height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px;">
                                        <i class="fas fa-fish text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($favorite['species_name']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars($favorite['scientific_name']); ?></small>
                                    </p>
                                    <p class="card-text">
                                        <strong>Supplier:</strong> <?php echo htmlspecialchars($favorite['business_name']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="h5 mb-0 text-primary">₱<?php echo number_format($favorite['price_per_piece'], 2); ?></span>
                                        <span class="badge bg-success">
                                            <?php echo number_format($favorite['stock_quantity']); ?> available
                                        </span>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Size: <?php echo htmlspecialchars($favorite['size_category']); ?> | 
                                            Min Order: <?php echo number_format($favorite['minimum_order']); ?>
                                        </small>
                                    </p>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-2">
                                        <a href="view.php?id=<?php echo $favorite['id']; ?>" 
                                           class="btn btn-primary btn-sm flex-fill">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <button type="button" class="btn btn-outline-success btn-sm" 
                                                onclick="addToCart(<?php echo $favorite['id']; ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Remove this product from favorites?')">
                                            <input type="hidden" name="action" value="remove_favorite">
                                            <input type="hidden" name="product_id" value="<?php echo $favorite['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-heart-broken"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        </main>

<script>
function addToCart(productId) {
    fetch('add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            alertDiv.innerHTML = `
                <i class="fas fa-check-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding to cart.');
    });
}
</script>

<?php include '../includes/customer_footer.php'; ?>
