<?php
require_once '../config/config.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

// Get a sample product for testing
$sample_products = $product->getFeaturedProducts(1);
$sample_product = $sample_products[0] ?? null;

$page_title = 'Test Add to Cart';
include '../includes/customer_header.php';
?>

<div class="container mt-5">
    <h2>Test Add to Cart Functionality</h2>
    
    <?php if ($sample_product): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($sample_product['species_name'] ?? 'Sample Product') ?></h5>
                <p class="card-text">Price: ₱<?= number_format($sample_product['price_per_piece'] ?? 0, 2) ?></p>
                <p class="card-text">Stock: <?= $sample_product['stock_quantity'] ?? 0 ?> items available</p>
                <button class="btn btn-primary add-to-cart" data-product-id="<?= $sample_product['id'] ?>" data-min-order="<?= $sample_product['minimum_order'] ?? 1 ?>">
                    Add to Cart
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            No products available for testing.
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Add to Cart Test</h5>
            <p class="card-text">This page tests the add to cart functionality.</p>
            
            <?php if (isset($result)): ?>
                <div class="alert alert-info">
                    <h6>Test Result:</h6>
                    <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="product_id" class="form-label">Product ID</label>
                    <input type="number" class="form-control" id="product_id" name="product_id" required>
                </div>
                <div class="mb-3">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" required>
                </div>
                <button type="submit" class="btn btn-primary">Add to Cart</button>
                <a href="dashboard.php" class="btn btn-secondary">Browse Products</a>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const minOrder = this.dataset.minOrder ? parseInt(this.dataset.minOrder) : 1;
            addToCart(productId, minOrder);
        });
    });
});
</script>

<?php include '../includes/customer_footer.php'; ?>