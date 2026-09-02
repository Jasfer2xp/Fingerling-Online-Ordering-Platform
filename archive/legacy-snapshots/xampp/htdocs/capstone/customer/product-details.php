<?php
require_once '../config/config.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

// Get product ID from URL
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    redirect(base_url('customer/dashboard.php'));
}

// Initialize classes
$product = new Product($database);
$supplier = new Supplier($database);

// Fetch product details
$product_details = $product->getProductById($product_id);

if (!$product_details) {
    redirect(base_url('customer/dashboard.php'));
}

// Fetch supplier information
$supplier_id = $product_details['supplier_id'];
$supplier_info = $supplier->getSupplierById($supplier_id);

// Ensure the product has an image path
$image_path = !empty($product_details['image_path']) ? base_url($product_details['image_path']) : asset_url('../images/placeholder-fish.jpg');

$page_title = htmlspecialchars($product_details['species_name'] ?? 'Product Details');
include '../includes/customer_header.php';
?>

<main class="dashboard-wrapper">
    <div class="container dashboard-container">
        <div class="row g-4">
            <!-- Product Image -->
            <div class="col-md-6">
                <div class="product-image-container mb-4">
                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                         alt="<?php echo htmlspecialchars($product_details['species_name'] ?? ''); ?>"
                         class="img-fluid rounded-3 shadow-sm" 
                         style="max-height: 500px; object-fit: cover;">
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-md-6">
                <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($product_details['species_name'] ?? ''); ?></h1>
                
                <div class="price-info mb-3">
                    <span class="text-primary h3 fw-bold">₱<?php echo number_format($product_details['price_per_piece'] ?? 0, 2); ?></span>
                    <span class="text-muted ms-2">per piece</span>
                </div>

                <!-- Stock Status -->
                <?php if ($product_details['stock_quantity'] > 0): ?>
                    <div class="badge bg-success mb-3">
                        <i class="fas fa-check-circle me-1"></i> In stock (<?php echo $product_details['stock_quantity']; ?>)
                    </div>
                <?php else: ?>
                    <div class="badge bg-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> Out of stock
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <div class="mb-4">
                    <h5 class="fw-bold mb-2">Description</h5>
                    <p><?php echo htmlspecialchars($product_details['description'] ?? 'No description available'); ?></p>
                </div>

                <!-- Quantity and Add to Cart -->
                <form method="POST" action="add-to-cart.php">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   min="1" max="<?php echo $product_details['stock_quantity']; ?>" 
                                   value="1" required>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Supplier Information -->
                <div class="mt-4 border-top pt-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-3">
                            <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                  style="width: 40px; height: 40px; font-size: 1rem;">
                                <?php echo strtoupper(substr($supplier_info['business_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($supplier_info['business_name'] ?? ''); ?></h6>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($supplier_info['barangay'] ?? '') . ', ' . 
                                       htmlspecialchars($supplier_info['city'] ?? '') . ', ' . 
                                       htmlspecialchars($supplier_info['province'] ?? ''); ?>
                            </small>
                        </div>
                        <a href="supplier-profile.php?id=<?php echo $supplier_info['id']; ?>" class="btn btn-outline-primary btn-sm">
                            View Store
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.product-image-container {
    background-color: #f8f9fa;
    padding: 2rem;
    border-radius: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>

<?php include '../includes/customer_footer.php'; ?>