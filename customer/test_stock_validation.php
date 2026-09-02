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

$page_title = 'Test Stock Validation';
include '../includes/customer_header.php';
?>

<div class="container mt-5">
    <h2>Test Stock Validation</h2>
    
    <?php if ($sample_product): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($sample_product['species_name'] ?? 'Sample Product') ?></h5>
                <p class="card-text">Price: ₱<?= number_format($sample_product['price_per_piece'] ?? 0, 2) ?></p>
                <p class="card-text">Stock: <?= $sample_product['stock_quantity'] ?? 0 ?> items available</p>
                <p class="card-text">Minimum order: <?= $sample_product['minimum_order'] ?? 1 ?> items</p>
                
                <div class="mb-3">
                    <label for="quantity" class="form-label">Quantity to add:</label>
                    <input type="number" class="form-control" id="quantity" value="<?= $sample_product['minimum_order'] ?? 1 ?>" min="1">
                </div>
                
                <button class="btn btn-primary add-to-cart" data-product-id="<?= $sample_product['id'] ?>">
                    Add to Cart
                </button>
                
                <button class="btn btn-success add-large-quantity" data-product-id="<?= $sample_product['id'] ?>" data-stock="<?= $sample_product['stock_quantity'] ?>">
                    Try Adding Large Quantity (Test Stock Validation)
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
            <h5 class="card-title">Stock Validation Test</h5>
            <p class="card-text">This page tests the stock validation functionality.</p>
            
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
                <button type="submit" class="btn btn-primary">Test Stock Validation</button>
                <a href="dashboard.php" class="btn btn-secondary">Browse Products</a>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Normal add to cart button
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            addToCart(productId, quantity);
        });
    });
    
    // Test large quantity button
    document.querySelectorAll('.add-large-quantity').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const stock = parseInt(this.dataset.stock) || 0;
            // Try to add more than available stock
            const largeQuantity = stock + 10;
            addToCart(productId, largeQuantity);
        });
    });
});
</script>

<?php include '../includes/customer_footer.php'; ?>