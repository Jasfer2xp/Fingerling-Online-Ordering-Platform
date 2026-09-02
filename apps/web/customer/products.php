<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);
$order = new Order($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

// Ensure registration is complete
if (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province'])) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=customer&step=3'));
}

// Get customer location (use default if not available)
$customer_lat = $profile['latitude'] ?? 8.1586;  // Default: Tangub City
$customer_lng = $profile['longitude'] ?? 123.7478;

// Get all available products
$all_products = $product->getAvailableProducts([], 50); // Get first 50 products

$page_title = 'All Products';
include '../includes/customer_header.php';
?>

<!-- ===================== PRODUCTS PAGE ===================== -->
<main class="dashboard-wrapper">
    <div class="container dashboard-container" style="max-width: 1000px; margin: 0 auto; padding: 0 1rem;">
        <div class="row g-4">

            <!-- MOBILE FILTER (shown only on < md) -->
            <div class="col-12 d-md-none mb-3">
                <div class="card border-0 shadow-sm rounded-3 p-3">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-filter me-2"></i> Narrow Results</h5>

                    <div class="row g-2">
                        <div class="col-6">
                            <label for="filter-size-mobile" class="form-label small fw-semibold text-muted">Size (inches)</label>
                            <input type="text" id="filter-size-mobile" class="form-control form-control-sm" placeholder="e.g. 5" inputmode="numeric">
                        </div>
                        <div class="col-6">
                            <label for="filter-price-mobile" class="form-label small fw-semibold text-muted">Price (PHP)</label>
                            <input type="text" id="filter-price-mobile" class="form-control form-control-sm" placeholder="e.g. 150" inputmode="numeric">
                        </div>
                    </div>

                    <button id="clear-filters-mobile" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>

            <!-- DESKTOP SIDEBAR (shown on >= md) -->
            <div class="col-lg-3 col-md-4 d-none d-md-block">
                <div class="sidebar-filter sticky-top" style="top: 80px;">
                    <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-filter me-2"></i> Narrow Results</h5>

                        <div class="mb-3">
                            <label for="filter-size" class="form-label small fw-semibold text-muted">Size (inches)</label>
                            <input type="text" id="filter-size" class="form-control form-control-sm" placeholder="e.g. 5" inputmode="numeric">
                        </div>

                        <div class="mb-3">
                            <label for="filter-price" class="form-label small fw-semibold text-muted">Price (PHP)</label>
                            <input type="text" id="filter-price" class="form-control form-control-sm" placeholder="e.g. 150" inputmode="numeric">
                        </div>

                        <button id="clear-filters" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-lg-9 col-md-8 col-12">
                <section class="all-products">
                    <div class="section-header d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold text-dark"><i class="fas fa-fish text-primary me-2"></i> All Products</h4>
                        <small class="text-muted" id="result-count"><?php echo count($all_products); ?> items</small>
                    </div>

                    <!-- 2-column on mobile, 3-column on lg -->
                    <div class="row row-cols-2 row-cols-md-2 row-cols-lg-3 g-3" id="product-grid">
                        <?php if (empty($all_products)): ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-fish fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No products available</h5>
                                    <p class="text-muted">Check back later for new products!</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($all_products as $product_item): ?>
                                <div class="col product-item"
                                     data-size="<?php echo htmlspecialchars($product_item['size_category'] ?? ''); ?>"
                                     data-price="<?php echo $product_item['price_per_piece']; ?>">
                                    <div class="card product-card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                                        <div class="product-image-wrap position-relative">
                                            <?php
                                            $image_path = '';
                                            if (!empty($product_item['image_path'])) {
                                                $image_path = str_replace('../', '', $product_item['image_path']);
                                                $image_src = base_url($image_path);
                                            } elseif (!empty($product_item['image_url'])) {
                                                $image_src = $product_item['image_url'];
                                            } else {
                                                $image_src = asset_url('images/placeholder-fish.jpg');
                                            }
                                            ?>
                                            <img src="<?php echo $image_src; ?>"
                                                 alt="<?php echo htmlspecialchars($product_item['species_name'] ?? 'Product'); ?>"
                                                 class="product-image w-100"
                                                 style="height: 160px; object-fit: cover;"
                                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                        </div>

                                        <div class="card-body d-flex flex-column p-3">
                                            <h6 class="card-title mb-1 product-title fw-semibold"><?php echo htmlspecialchars($product_item['species_name']); ?></h6>
                                            <p class="mb-1 text-muted small"><i class="fas fa-store"></i> <?php echo htmlspecialchars($product_item['business_name']); ?></p>

                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="price-area">
                                                        <div class="price-text fw-bold text-dark"><?php echo format_currency($product_item['price_per_piece']); ?></div>
                                                    </div>
                                                    <div class="stock small text-muted"><?php echo number_format($product_item['stock_quantity']); ?> pcs</div>
                                                </div>

                                                <?php if ($product_item['size_category']): ?>
                                                    <div class="mb-2 small text-secondary">
                                                        <span class="text-muted">Size:</span>
                                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($product_item['size_category']); ?></span>
                                                        <span class="text-muted">inches</span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($product_item['description'])): ?>
                                                    <div class="product-description small text-muted mb-2 line-clamp-2">
                                                        <?php echo htmlspecialchars($product_item['description']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (isset($product_item['minimum_order']) && $product_item['minimum_order'] > 1): ?>
                                                    <div class="mb-2 small text-warning"><i class="fas fa-info-circle"></i> Min: <?php echo $product_item['minimum_order']; ?></div>
                                                <?php endif; ?>

                                                <?php if ($product_item['rating'] > 0): ?>
                                                    <div class="rating mb-2 small">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= round($product_item['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <small class="text-muted ms-1">(<?php echo number_format($product_item['total_ratings']); ?>)</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="card-footer bg-transparent border-0 p-3 pt-0">
                                            <div class="d-flex gap-2">
                                                <a href="view.php?id=<?php echo $product_item['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill modern-btn">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-primary btn-sm flex-fill add-to-cart modern-btn"
                                                        data-product-id="<?php echo $product_item['id']; ?>"
                                                        data-min-order="<?php echo $product_item['minimum_order'] ?? 1; ?>">
                                                    <i class="fas fa-cart-plus"></i>
                                                    <?php echo $product_item['minimum_order'] > 1 ? "Add {$product_item['minimum_order']}" : 'Add'; ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---------- FILTER ELEMENTS ----------
    const desktopSize   = document.getElementById('filter-size');
    const desktopPrice  = document.getElementById('filter-price');
    const desktopClear  = document.getElementById('clear-filters');

    const mobileSize    = document.getElementById('filter-size-mobile');
    const mobilePrice   = document.getElementById('filter-price-mobile');
    const mobileClear   = document.getElementById('clear-filters-mobile');

    const productGrid   = document.getElementById('product-grid');
    const resultCount   = document.getElementById('result-count');
    const productItems  = document.querySelectorAll('.product-item');

    // ---------- SYNC INPUTS ----------
    function syncInputs(srcSize, srcPrice, dstSize, dstPrice) {
        dstSize.value  = srcSize.value;
        dstPrice.value = srcPrice.value;
    }

    // desktop to mobile
    if (desktopSize)   desktopSize.addEventListener('input',   () => syncInputs(desktopSize, desktopPrice, mobileSize, mobilePrice));
    if (desktopPrice)  desktopPrice.addEventListener('input',  () => syncInputs(desktopSize, desktopPrice, mobileSize, mobilePrice));
    if (desktopClear)  desktopClear.addEventListener('click',  () => { mobileSize.value = ''; mobilePrice.value = ''; filterProducts(); });

    // mobile to desktop
    if (mobileSize)    mobileSize.addEventListener('input',    () => syncInputs(mobileSize, mobilePrice, desktopSize, desktopPrice));
    if (mobilePrice)   mobilePrice.addEventListener('input',   () => syncInputs(mobileSize, mobilePrice, desktopSize, desktopPrice));
    if (mobileClear)   mobileClear.addEventListener('click',   () => { desktopSize.value = ''; desktopPrice.value = ''; filterProducts(); });

    // ---------- FILTER LOGIC ----------
    function filterProducts() {
        const sizeVal  = (desktopSize ? desktopSize.value.trim() : mobileSize.value.trim());
        const priceVal = (desktopPrice ? desktopPrice.value.trim() : mobilePrice.value.trim());

        let visible = 0;

        productItems.forEach(item => {
            const size  = item.dataset.size;
            const price = parseFloat(item.dataset.price);

            let show = true;

            if (sizeVal && size !== sizeVal) show = false;
            if (priceVal) {
                const target = parseFloat(priceVal);
                if (isNaN(target) || price !== target) show = false;
            }

            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        resultCount.textContent = visible + ' item' + (visible !== 1 ? 's' : '');
    }

    // attach listeners to both sets
    [desktopSize, desktopPrice, mobileSize, mobilePrice].forEach(el => {
        if (el) el.addEventListener('input', filterProducts);
    });

    // initial run
    filterProducts();

    // ---------- ADD TO CART (unchanged) ----------
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function () {
            const productId = this.dataset.productId;
            const minOrder  = this.dataset.minOrder ? parseInt(this.dataset.minOrder) : 1;
            addToCart(productId, minOrder);
        });
    });
});

function addToCart(productId, quantity) {
    const button = document.querySelector(`[data-product-id="${productId}"]`);
    if (!button) return;

    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;

    fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${productId}&quantity=${quantity}`
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => {
        if (data.success) {
            showAlert('success', `${quantity} item${quantity > 1 ? 's' : ''} added!`);
            updateCartCount(data.cart_count);
        } else {
            showAlert('danger', data.message || 'Failed to add.');
        }
    })
    .catch(() => showAlert('danger', 'Network error.'))
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function updateCartCount(count) {
    const el = document.querySelector('.cart-count');
    if (el) el.textContent = count > 0 ? count : '';
    if (el) el.style.display = count > 0 ? 'flex' : 'none';
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    div.style.cssText = 'top:20px;right:20px;z-index:9999;min-width:300px;';
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}
</script>

<!-- STYLES -->
<style>
/* Core Layout & Background */
.dashboard-wrapper { 
    background: #ffffff; 
    padding: 2rem 0; 
    min-height: 100vh;
}
.dashboard-container { 
    max-width: 1000px !important; 
    margin: 0 auto; 
    padding: 0 1rem;
}

/* Section Header */
.section-header h4 { 
    font-weight: 700; 
    color: #1e293b; 
}

/* Product Card */
.product-card { 
    transition: all 0.3s ease; 
    border: 1px solid #e2e8f0 !important; 
    overflow: hidden; 
    background: #fff;
}
.product-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important; 
    border-color: #93c5fd !important; 
}
.product-image { 
    transition: transform 0.4s ease; 
}
.product-card:hover .product-image { 
    transform: scale(1.06); 
}

/* Size Text */
.product-card .text-secondary .fw-bold {
    font-weight: 600 !important;
    letter-spacing: 0.5px;
}

/* Description Clamp */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

/* Sidebar */
.sidebar-filter .card {
    background: #f8fafc;
}
.sidebar-filter input {
    border: 1px solid #cbd5e1;
    font-size: 0.875rem;
}
.sidebar-filter input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

/* Buttons */
.modern-btn {
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.5rem;
    padding: 0.375rem 0.75rem;
}
.btn-primary.modern-btn {
    background: #2563eb;
    border: none;
}
.btn-primary.modern-btn:hover {
    background: #1d4ed8;
}

/* Responsive */
@media (max-width: 767.98px) {
    .product-image { height: 140px !important; }
}
@media (max-width: 575.98px) {
    .d-flex.gap-2 { flex-direction: column; }
    .d-flex.gap-2 .btn { width: 100%; }
}
</style>

<?php include '../includes/customer_footer.php'; ?>