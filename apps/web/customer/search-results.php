<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

// Get search query
$query = trim($_GET['q'] ?? '');

// Initialize classes
$product = new Product($database);
$supplier = new Supplier($database);

// Search for suppliers and products
$suppliers = [];
$products = [];

if (!empty($query)) {
    $suppliers = $supplier->searchSuppliers($query);
    $products = $product->searchProducts($query);
}

$total_results = count($suppliers) + count($products);

$page_title = 'Search Results';
include '../includes/customer_header.php';
?>

<!-- ===================== SEARCH RESULTS PAGE ===================== -->
<main class="dashboard-wrapper">
    <div class="container dashboard-container">
        <div class="row g-4">
            <div class="col-12">

                <!-- Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">
                            Search Results for 
                            <span class="text-primary">“<?php echo htmlspecialchars($query); ?>”</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <?php echo $total_results; ?> result<?php echo $total_results !== 1 ? 's' : ''; ?> found
                        </p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary modern-btn d-flex align-items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if (empty($query)): ?>
                    <!-- No Query -->
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted">What are you looking for?</h4>
                        <p class="text-muted">Enter a fish species, supplier name, or location to search.</p>
                    </div>

                <?php else: ?>
                    <!-- Suppliers Section -->
                    <?php if (!empty($suppliers)): ?>
                        <section class="mb-5">
                            <h4 class="fw-bold mb-4 d-flex align-items-center gap-2">
                                <i class="fas fa-store text-info"></i> Suppliers
                                <span class="badge bg-info ms-2"><?php echo count($suppliers); ?></span>
                            </h4>
                            <div class="row g-4">
                                <?php foreach ($suppliers as $s): ?>
                                    <div class="col-lg-4 col-md-6">
                                        <div class="card supplier-card h-100 border-0 shadow-sm">
                                            <div class="card-body d-flex flex-column p-4">
                                                <h5 class="card-title fw-bold mb-2">
                                                    <?php echo htmlspecialchars($s['business_name']); ?>
                                                </h5>
                                                <p class="text-muted small mb-3">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($s['barangay'] . ', ' . $s['city'] . ', ' . $s['province']); ?>
                                                </p>

                                                <?php if ($s['avg_rating'] > 0): ?>
                                                    <div class="d-flex align-items-center mb-3">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= round($s['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-2 small text-muted">
                                                            (<?php echo number_format($s['total_reviews']); ?> reviews)
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mt-auto">
                                                    <a href="supplier-profile.php?id=<?php echo $s['id']; ?>" 
                                                       class="btn btn-primary w-100 modern-btn d-flex align-items-center justify-content-center gap-2">
                                                        <i class="fas fa-eye"></i> View Profile
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Products Section -->
                    <section>
                        <h4 class="fw-bold mb-4 d-flex align-items-center gap-2">
                            <i class="fas fa-fish text-primary"></i> Products
                            <span class="badge bg-primary ms-2"><?php echo count($products); ?></span>
                        </h4>

                        <?php if (empty($products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No products found</h5>
                                <p class="text-muted">Try searching for "bangus", "tilapia", or a location.</p>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                                <?php foreach ($products as $p): 
                                    // Highlight search term in species name
                                    $highlighted = preg_replace(
                                        '/(' . preg_quote($query, '/') . ')/i',
                                        '<mark class="bg-warning text-dark">$1</mark>',
                                        htmlspecialchars($p['species_name']),
                                        1,
                                        $count
                                    );
                                    if ($count == 0) $highlighted = htmlspecialchars($p['species_name']);
                                ?>
                                    <div class="col">
                                        <div class="card product-card h-100 d-flex flex-column border-0 shadow-sm">
                                            <!-- Image -->
                                            <div class="product-image-wrap">
                                                <?php
                                                $image_src = !empty($p['image_path'])
                                                    ? base_url(str_replace('../', '', $p['image_path']))
                                                    : (!empty($p['image_url']) ? $p['image_url'] : asset_url('images/placeholder-fish.jpg'));
                                                ?>
                                                <img src="<?php echo $image_src; ?>"
                                                     alt="<?php echo htmlspecialchars($p['species_name']); ?>"
                                                     class="product-image">
                                            </div>

                                            <div class="card-body d-flex flex-column py-3 px-3">
                                                <h6 class="product-title mb-1">
                                                    <?php echo $highlighted; ?>
                                                </h6>
                                                <p class="text-muted small mb-1">
                                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($p['business_name']); ?>
                                                </p>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($p['barangay'] . ', ' . $p['city']); ?>
                                                </p>

                                                <div class="mt-auto">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div class="price-text">
                                                            <?php echo format_currency($p['price_per_piece']); ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <?php echo number_format($p['stock_quantity']); ?> left
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($p['size_category'])): ?>
                                                        <div class="mb-2">
                                                            <span class="badge bg-info"><?php echo ucfirst($p['size_category']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($p['minimum_order']) && $p['minimum_order'] > 1): ?>
                                                        <div class="mb-2 small text-warning">
                                                            <i class="fas fa-info-circle"></i> Min: <?php echo $p['minimum_order']; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="d-flex gap-2">
                                                        <a href="view.php?id=<?php echo $p['id']; ?>"
                                                           class="btn btn-outline-primary btn-sm flex-fill modern-btn">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <button class="btn btn-primary btn-sm flex-fill add-to-cart modern-btn"
                                                                data-product-id="<?php echo $p['id']; ?>"
                                                                data-min-order="<?php echo $p['minimum_order'] ?? 1; ?>">
                                                            <i class="fas fa-cart-plus"></i>
                                                            <?php echo ($p['minimum_order'] ?? 1) > 1 ? 'Add ' . $p['minimum_order'] : 'Add'; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<!-- ===================== STYLES ===================== -->
<style>
/* Reuse dashboard styles */
.dashboard-wrapper {
    background: linear-gradient(to bottom right, #e0f7ff, #f8fbff);
    padding: 3rem 0;
    min-height: 100vh;
}

.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Supplier Card */
.supplier-card {
    transition: all 0.3s ease;
    border-radius: 16px;
    overflow: hidden;
}
.supplier-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1) !important;
}

/* Product Card */
.product-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 40px rgba(16,24,40,0.12);
}
.product-image-wrap {
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: #f8f9fa;
}
.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.product-card:hover .product-image {
    transform: scale(1.06);
}
.product-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.price-text {
    font-size: 1.1rem;
    font-weight: 700;
    color: #dc2626;
}
.modern-btn {
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

/* Badges */
.badge {
    font-size: 0.75rem;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .row-cols-sm-2 > * { flex: 0 0 50%; max-width: 50%; }
    .product-title { font-size: 0.9rem; }
}
</style>

<!-- ===================== SCRIPTS ===================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const minOrder = parseInt(this.dataset.minOrder) || 1;
            const originalHTML = this.innerHTML;

            // Loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            this.disabled = true;

            fetch('add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${productId}&quantity=${minOrder}`
            })
            .then(r => r.json().catch(() => ({ success: false, message: 'Invalid response' })))
            .then(data => {
                if (data.success) {
                    showAlert('success', `${minOrder} item${minOrder > 1 ? 's' : ''} added to cart!`);
                    updateCartCount(data.cart_count);
                } else {
                    showAlert('danger', data.message || 'Failed to add to cart.');
                }
            })
            .catch(() => showAlert('danger', 'Network error. Please try again.'))
            .finally(() => {
                this.innerHTML = originalHTML;
                this.disabled = false;
            });
        });
    });

    function updateCartCount(count) {
        const el = document.querySelector('.cart-count');
        if (el) {
            el.textContent = count;
            el.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    function showAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 5000);
    }
});
</script>

<?php include '../includes/customer_footer.php'; ?>