<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Product.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

// Get product ID
$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    $_SESSION['error'] = 'Product not found.';
    redirect(base_url('customer/dashboard.php'));
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Get product details
$product_details = $product->getProductById($product_id);

if (!$product_details) {
    $_SESSION['error'] = 'Product not found.';
    redirect(base_url('customer/dashboard.php'));
}

// Check if product is available
if ($product_details['availability_status'] !== 'available' || ($product_details['stock_quantity'] ?? 0) <= 0) {
    $_SESSION['error'] = 'This product is currently unavailable.';
    redirect(base_url('customer/dashboard.php'));
}

// Get supplier details using database query
$sql = "SELECT s.*, u.email FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.id = ?";
$supplier_details = $database->fetch($sql, [$product_details['supplier_id']]);

// Get related products from same supplier
$related_products = $product->getProductsBySupplier($product_details['supplier_id'], 4);

$page_title = ($product_details['species_name'] ?? 'Product') . ' - Product Details';
include '../includes/customer_header.php';
?>

<!-- Shopee-inspired Product View -->
<main class="product-page shopee-theme">
    <div class="container-xl py-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row gx-4 gy-4">
            <div class="col-lg-8">
                <div class="card product-card modern-card p-3">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-5">
                            <div class="product-gallery">
                                <?php if (!empty($product_details['image_path'])): ?>
                                    <img src="<?php echo base_url($product_details['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($product_details['species_name'] ?? 'Product'); ?>"
                                         class="img-fluid main-image rounded">
                                <?php elseif (!empty($product_details['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($product_details['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product_details['species_name'] ?? 'Product'); ?>"
                                         class="img-fluid main-image rounded">
                                <?php else: ?>
                                    <div class="placeholder-image d-flex align-items-center justify-content-center rounded">
                                        <i class="fas fa-fish fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h2 class="product-title mb-1">
                                        <?php echo htmlspecialchars($product_details['species_name'] ?? 'Product'); ?>
                                    </h2>
                                    <div class="small text-muted">by <?php echo htmlspecialchars($supplier_details['business_name'] ?? 'Unknown'); ?></div>
                                </div>

                                <div class="badge-availability text-end">
                                    <?php if (($product_details['availability_status'] ?? '') === 'available'): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="price-box mt-3">
                                <div class="price">₱<?php echo number_format($product_details['price_per_piece'] ?? 0, 2); ?> <small class="text-muted"> / <?php echo htmlspecialchars($product_details['size_category'] ?? 'pc'); ?></small></div>
                                <div class="stock mt-1">Stock: <strong><?php echo number_format($product_details['stock_quantity'] ?? 0); ?></strong></div>
                            </div>

                            <div class="mt-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <label class="form-label">Quantity</label>
                                        <div class="input-group qty-input">
                                            <button class="btn btn-outline-secondary" id="qty-decrease" type="button"><i class="fas fa-minus"></i></button>
                                            <input type="number" id="quantity" class="form-control text-center" value="1" min="1" max="<?php echo intval($product_details['stock_quantity']); ?>">
                                            <button class="btn btn-outline-secondary" id="qty-increase" type="button"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                                        <div class="total-label small text-muted">Total</div>
                                        <div id="totalPrice" class="total-price">₱<?php echo number_format(($product_details['price_per_piece'] ?? 0), 2); ?></div>
                                    </div>
                                </div>

                                <div class="d-grid mt-3">
                                    <button class="btn btn-add-to-cart btn-lg" id="addToCartBtn" data-product-id="<?php echo $product_details['id']; ?>">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>

                                <div class="mt-3">
                                    <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="text-decoration-none small"><i class="fas fa-chevron-left"></i> Back to Browse</a>
                                </div>
                            </div>

                            <?php if (!empty($product_details['description'])): ?>
                                <div class="mt-4 product-description small text-muted">
                                    <h6 class="mb-2">Product Description</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($product_details['description'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($related_products)): ?>
                    <div class="card modern-card mt-4 p-3">
                        <h5 class="mb-3">More from this Supplier</h5>
                        <div class="row g-3">
                            <?php foreach ($related_products as $related): ?>
                                <div class="col-6 col-md-3">
                                    <div class="related-card p-2 text-center">
                                        <a href="view.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                            <div class="related-thumb mb-2">
                                                <?php if (!empty($related['image_path'])): ?>
                                                    <img src="<?php echo base_url($related['image_path']); ?>" alt="<?php echo htmlspecialchars($related['species_name'] ?? ''); ?>" class="img-fluid">
                                                <?php else: ?>
                                                    <div class="related-placeholder"><i class="fas fa-fish"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="related-name small mb-1"><?php echo htmlspecialchars($related['species_name'] ?? $related['name'] ?? ''); ?></div>
                                            <div class="related-price small text-muted">₱<?php echo number_format($related['price_per_piece'] ?? 0, 2); ?></div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <aside class="card supplier-card modern-card p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="supplier-avatar rounded-circle d-flex align-items-center justify-content-center me-3">
                            <i class="fas fa-store"></i>
                        </div>
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($supplier_details['business_name'] ?? ''); ?></h6>
                            <div class="small text-muted"><?php echo htmlspecialchars($supplier_details['owner_name'] ?? ''); ?></div>
                        </div>
                    </div>

                    <div class="small text-muted mb-3">
                        <div><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($supplier_details['email'] ?? ''); ?></div>
                        <div><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($supplier_details['contact_number'] ?? ''); ?></div>
                        <div><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars(($supplier_details['barangay'] ?? '') . ', ' . ($supplier_details['city'] ?? '') . ', ' . ($supplier_details['province'] ?? '')); ?></div>
                    </div>

                    <div class="d-grid gap-2 mb-2">
                        <a href="products.php?supplier=<?php echo $supplier_details['id']; ?>" class="btn btn-outline-primary">View All Products</a>
                        <?php if (!empty($supplier_details['latitude']) && !empty($supplier_details['longitude'])): ?>
                            <a href="supplier-profile.php?id=<?php echo $supplier_details['id']; ?>" class="btn btn-outline-primary">View Profile</a>
                        <?php else: ?>
                            <a href="supplier-profile.php?id=<?php echo $supplier_details['id']; ?>" class="btn btn-outline-primary">View Profile</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($related_products)): ?>
                        <div class="mt-3">
                            <h6 class="mb-2">Quick Picks</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($related_products as $r): ?>
                                    <a href="view.php?id=<?php echo $r['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div class="small"><?php echo htmlspecialchars($r['species_name'] ?? $r['name'] ?? ''); ?></div>
                                        <div class="small text-muted">₱<?php echo number_format($r['price_per_piece'] ?? 0, 2); ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>
</main>

<style>
/* Shopee-inspired theme colors */
.shopee-theme .modern-card { background: #fff; }
.shopee-theme { --accent: #ee4d2d; --accent-dark: #d43f20; }

.product-card { border-radius: 12px; }
.product-gallery .main-image { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.placeholder-image { height: 320px; background: #f5f5f5; display:flex; align-items:center; justify-content:center; }
.product-title { font-size: 1.5rem; font-weight:700; color:#222; }
.price { font-size: 1.35rem; color: var(--accent); font-weight:700; }
.stock { color: #6c757d; }
.qty-input .form-control { width: 70px; }
.btn-add-to-cart { background: linear-gradient(90deg, var(--accent), var(--accent-dark)); color: #fff; border: none; border-radius: 8px; padding: 12px 18px; }
.btn-add-to-cart:disabled { opacity: 0.7; }
.related-card { background: #fafafa; border-radius: 8px; padding: 10px; }
.related-thumb img { max-height: 80px; object-fit: cover; }
.supplier-card .supplier-avatar { width:48px; height:48px; background:#fff1ea; color:var(--accent); }
.total-price { font-size: 1.25rem; font-weight:700; color: #1b6b2f; }

/* responsive tweaks */
@media (max-width: 767px) {
    .product-title { font-size: 1.25rem; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const productPrice = parseFloat(<?php echo json_encode(floatval($product_details['price_per_piece'] ?? 0)); ?>) || 0;
    const maxStock = parseInt(<?php echo json_encode(intval($product_details['stock_quantity'] ?? 0)); ?>, 10) || 0;

    const quantityInput = document.getElementById('quantity');
    const totalPriceEl = document.getElementById('totalPrice');
    const btnIncrease = document.getElementById('qty-increase');
    const btnDecrease = document.getElementById('qty-decrease');
    const addToCartBtn = document.getElementById('addToCartBtn');

    function updateTotalPrice() {
        let qty = parseInt(quantityInput.value, 10) || 1;
        if (qty < 1) qty = 1;
        if (qty > maxStock) qty = maxStock;
        quantityInput.value = qty;
        const total = productPrice * qty;
        totalPriceEl.textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
    }

    btnIncrease && btnIncrease.addEventListener('click', function () {
        let current = parseInt(quantityInput.value, 10) || 1;
        if (current < maxStock) {
            quantityInput.value = current + 1;
            updateTotalPrice();
        }
    });

    btnDecrease && btnDecrease.addEventListener('click', function () {
        let current = parseInt(quantityInput.value, 10) || 1;
        if (current > 1) {
            quantityInput.value = current - 1;
            updateTotalPrice();
        }
    });

    quantityInput && quantityInput.addEventListener('input', updateTotalPrice);

    // Add to cart
    addToCartBtn && addToCartBtn.addEventListener('click', function () {
        const productId = this.dataset.productId;
        const qty = parseInt(quantityInput.value, 10) || 1;
        addToCart(productId, qty);
    });

    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 100px; right: 20px; z-index: 9999; max-width: 360px;';
        alertDiv.innerHTML = `
            <strong>${type === 'success' ? 'Success' : 'Notice'}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alertDiv);
        setTimeout(() => { if (alertDiv.parentNode) alertDiv.remove(); }, 5000);
    }

    // Add to cart function
    function addToCart(productId, quantity) {
        // Disable button and show loading state
        const btn = document.getElementById('addToCartBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        
        // Send request to add to cart
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                // Update cart count in header if exists
                const cartCountElement = document.querySelector('.cart-count');
                if (cartCountElement && data.cart_count !== undefined) {
                    cartCountElement.textContent = data.cart_count;
                }
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while adding to cart. Please try again.');
        })
        .finally(() => {
            // Restore button state
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    // maps
    window.openGoogleMaps = function(lat, lng, name) {
        const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
        window.open(url, '_blank');
    };

    // initial price update
    updateTotalPrice();
});
</script>

<?php include '../includes/customer_footer.php'; ?>
