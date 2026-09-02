<?php
/**
 * Customer Product Details Page - Redesigned
 * Modern, responsive layout with balanced margins and clear hierarchy.
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Product.php';
require_once '../classes/Supplier.php';
require_once '../helpers/assets.php'; // Add asset helper for proper URL handling

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect('/auth/login.php');
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);
$supplier = new Supplier($database);

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) {
    redirect('/customer/dashboard.php');
}

try {
    $product_details = $product->getProductById($product_id);
    if (!$product_details) {
        $error = "Product not found.";
    } else {
        $sql = "SELECT s.*, u.email FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.id = ?";
        $supplier_info = $database->fetch($sql, [$product_details['supplier_id']]);

        // Image display with fallbacks: inventory image > species image > placeholder
        $image_src = asset_url('images/placeholder-fish.jpg'); // default placeholder
        
        if (!empty($product_details['image_path'])) {
            // Fix any incorrect paths that start with ../
            $corrected_path = str_replace('../', '', $product_details['image_path']);
            // Use inventory-specific image
            $image_src = base_url($corrected_path);
        } elseif (!empty($product_details['image_url'])) {
            // Fallback to species image
            $image_src = $product_details['image_url'];
        }

        $related_products = $product->getProductsBySupplier($product_details['supplier_id'], 4);
        $customer_profile = $user->getUserProfile($user_id);
    }
} catch (Exception $e) {
    $error = "Error loading product details: " . $e->getMessage();
}

$page_title = isset($product_details) ? ($product_details['species_name'] ?? 'Product Details') : 'Product Details';
include '../includes/customer_header.php';
?>

<!-- =========================
     PRODUCT DETAILS CONTENT
     ========================= -->
<style>
/* Page container spacing so main doesn't touch edges */
.responsive-container {
  max-width: 1200px;
  margin: 28px auto;
  padding: 0 16px;
}

/* Card / surface styles */
.product-surface {
  background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
  border-radius: 12px;
  box-shadow: 0 6px 22px rgba(20, 40, 60, 0.06);
  overflow: hidden;
  border: 1px solid rgba(15,23,42,0.04);
}

/* Grid layout */
.product-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 28px;
  align-items: start;
  padding: 26px;
}

/* Left column (images) */
.gallery {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.gallery-main {
  border-radius: 10px;
  overflow: hidden;
  background: #f8fafc;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
}
.gallery-main img {
  display: block;
  width: 100%;
  height: 480px;
  object-fit: cover;
}
.gallery-thumbs {
  display: flex;
  gap: 10px;
  margin-top: 8px;
}
.gallery-thumb {
  flex: 1 0 20%;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform .15s ease, border-color .15s ease;
}
.gallery-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.gallery-thumb:hover { transform: translateY(-3px); }
.gallery-thumb.active { border-color: rgba(37,99,235,0.9); }

/* Right column (info) */
.product-info {
  padding: 4px 6px;
}
.product-title {
  font-size: 1.6rem;
  margin-bottom: 8px;
  color: #0f172a;
  font-weight: 700;
}
.price-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 12px;
}
.price {
  font-size: 1.5rem;
  color: #0b5ed7;
  font-weight: 800;
}
.per-piece { color: #64748b; font-size: 0.95rem; }

/* Stock badge */
.badge-stock {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.9rem;
}
.badge-in { background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.12); }
.badge-out { background: rgba(239,68,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.08); }

/* Description & specs */
.section-heading {
  font-size: 1.05rem;
  font-weight: 700;
  margin-bottom: 8px;
  color: #0f172a;
}
.text-muted-1 { color: #475569; line-height: 1.6; }

/* Add to cart area */
.add-to-cart {
  margin-top: 18px;
  display: flex;
  gap: 12px;
  align-items: center;
}
.input-qty {
  width: 120px;
  min-width: 90px;
}
.btn-cta {
  background: linear-gradient(90deg,#0b5ed7,#1e88ff);
  border: none;
  color: white;
  padding: 11px 18px;
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(14,75,161,0.18);
  transition: transform .12s ease, box-shadow .12s ease;
}
.btn-cta:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(14,75,161,0.18); }

/* Seller card */
.seller-card {
  margin-top: 18px;
  padding: 12px;
  border-radius: 10px;
  background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
  border: 1px solid rgba(15,23,42,0.04);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.seller-info { display:flex; gap:12px; align-items:center; }
.seller-logo {
  width:56px; height:56px; border-radius:8px; background:#eef2ff; display:flex; align-items:center; justify-content:center; color:#0b5ed7; font-weight:700;
}

/* Related products grid */
.related-grid {
  margin-top: 26px;
  display:grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}
.related-card {
  background: white;
  border-radius: 10px;
  padding: 12px;
  text-align:center;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
}
.related-card img { width:100%; height:120px; object-fit:cover; border-radius:8px; margin-bottom:10px; }

/* Breadcrumb */
.breadcrumb {
  background: transparent;
  padding: 0;
  margin-bottom: 14px;
}

/* Responsive adjustments */
@media (max-width: 991px) {
  .product-grid { grid-template-columns: 1fr; padding: 18px; }
  .gallery-main img { height: 360px; }
  .related-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 575px) {
  .gallery-main img { height: 260px; }
  .related-grid { grid-template-columns: 1fr; }
  .price { font-size: 1.25rem; }
  .product-title { font-size: 1.25rem; }
}
</style>

<main class="responsive-container">
    <!-- header row -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="history.back()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="mb-0" style="font-weight:700;"><?php echo htmlspecialchars($product_details['species_name'] ?? 'Product'); ?></h2>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-list"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>

    <div class="product-surface">

        <!-- breadcrumb -->
        <div style="padding:18px 26px;">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product_details['species_name'] ?? 'Product'); ?></li>
                </ol>
            </nav>
        </div>

        <!-- main grid -->
        <div class="product-grid">
            <!-- images -->
            <div class="gallery">
                <div class="gallery-main">
                    <?php if (!empty($product_images)): ?>
                        <img id="mainImage" src="<?php echo '../' . htmlspecialchars($product_images[0]['image_path']); ?>" alt="<?php echo htmlspecialchars($product_details['species_name'] ?? 'Product Image'); ?>">
                    <?php else: ?>
                        <div style="padding:40px; text-align:center; color:#64748b;">
                            <i class="fas fa-image fa-4x mb-3"></i>
                            <div>No images available</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gallery-thumbs">
                    <div class="gallery-thumb active">
                        <img src="<?php echo htmlspecialchars($image_src); ?>" 
                             alt="<?php echo htmlspecialchars($product_details['species_name']); ?>" 
                             onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                    </div>
                </div>
            </div>

            <!-- info -->
            <div class="product-info">
                <div class="price-row">
                    <div class="price"><?php echo format_currency($product_details['price_per_piece'] ?? 0); ?></div>
                    <div class="per-piece">per piece</div>
                </div>

                <div class="mb-3">
                    <?php if (!empty($product_details['stock_quantity']) && $product_details['stock_quantity'] > 0): ?>
                        <div class="badge-stock badge-in"><i class="fas fa-check"></i> In stock (<?php echo number_format($product_details['stock_quantity']); ?>)</div>
                    <?php else: ?>
                        <div class="badge-stock badge-out"><i class="fas fa-times"></i> Out of stock</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <div class="section-heading">Description</div>
                    <div class="text-muted-1"><?php echo nl2br(htmlspecialchars($product_details['species_description'] ?? 'No description available')); ?></div>
                </div>

                <?php if (!empty($product_details['specifications'])): ?>
                    <div class="mb-3">
                        <div class="section-heading">Specifications</div>
                        <div class="text-muted-1"><?php echo nl2br(htmlspecialchars($product_details['specifications'])); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($product_details['stock_quantity']) && $product_details['stock_quantity'] > 0): ?>
                    <div class="add-to-cart-section">
                        <div class="quantity-control">
                            <button class="quantity-btn" id="decreaseQty">-</button>
                            <input type="number" class="quantity-input" id="quantity" 
                                   value="<?php echo $product_details['minimum_order'] ?? 1; ?>" 
                                   min="1" 
                                   max="<?php echo $product_details['stock_quantity'] ?? 0; ?>">
                            <button class="quantity-btn" id="increaseQty">+</button>
                            <span class="ms-2">pieces</span>
                        </div>
                        <button class="add-to-cart-btn" id="addToCartBtn" 
                                data-product-id="<?php echo $product_details['id'] ?? 0; ?>" 
                                <?php echo (!$product_details['stock_quantity'] ? 'disabled' : ''); ?>>
                            <?php echo ($product_details['stock_quantity'] ? 'Add to Cart' : 'Out of Stock'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="seller-card">
                    <div class="seller-info">
                        <div class="seller-logo">
                            <?php echo strtoupper(substr($supplier_info['business_name'] ?? 'S', 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight:700;"><?php echo htmlspecialchars($supplier_info['business_name'] ?? 'Supplier'); ?></div>
                            <div class="text-muted" style="font-size:0.9rem;">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($supplier_info['city'] . ', ' . $supplier_info['province']); ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="supplier-profile.php?id=<?php echo intval($supplier_info['id'] ?? 0); ?>" class="btn btn-outline-primary btn-sm">View Store</a>
                    </div>
                </div>
            </div>
        </div> <!-- /product-grid -->

        <!-- related -->
        <?php if (!empty($related_products)): ?>
            <div style="padding: 22px;">
                <h4 style="margin:0 0 12px 0; color:#0f172a;">More from this supplier</h4>
                <div class="related-grid">
                    <?php foreach ($related_products as $related): ?>
                        <?php 
                        // Image display with fallbacks for related products
                        $related_image_src = asset_url('images/placeholder-fish.jpg'); // default placeholder
                        
                        if (!empty($related['image_path'])) {
                            // Use inventory-specific image
                            $related_image_src = base_url($related['image_path']);
                        } elseif (!empty($related['image_url'])) {
                            // Fallback to species image
                            $related_image_src = $related['image_url'];
                        }
                        ?>
                        <div class="related-card">
                            <img src="<?php echo htmlspecialchars($related_image_src); ?>" 
                                 alt="<?php echo htmlspecialchars($related['species_name']); ?>" 
                                 class="related-card-img"
                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                        <?php endif; ?>
                    <?php foreach ($related_products as $related): ?>
                        <?php 
                        // Image display with fallbacks for related products
                        $related_image_src = asset_url('images/placeholder-fish.jpg'); // default placeholder
                        
                        if (!empty($related['image_path'])) {
                            // Use inventory-specific image
                            $related_image_src = base_url($related['image_path']);
                        } elseif (!empty($related['image_url'])) {
                            // Fallback to species image
                            $related_image_src = $related['image_url'];
                        }
                        ?>
                        <div class="related-card">
                            <img src="<?php echo htmlspecialchars($related_image_src); ?>" 
                                 alt="<?php echo htmlspecialchars($related['species_name']); ?>" 
                                 class="related-card-img"
                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                    <?php endforeach; ?>
                            <div style="font-weight:700;"><?php echo htmlspecialchars($related['species_name'] ?? 'Product'); ?></div>
                            <div style="color:#059669;font-weight:700;margin:6px 0;"><?php echo format_currency($related['price_per_piece'] ?? 0); ?></div>
                            <a href="product-details.php?id=<?php echo intval($related['id']); ?>" class="btn btn-outline-secondary btn-sm">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div> <!-- /product-surface -->

    <?php endif; ?>
</main>

<script>
/* Gallery thumbnails -> main image */
document.querySelectorAll('.gallery-thumb').forEach(function(thumb){
    thumb.addEventListener('click', function(){
        document.querySelectorAll('.gallery-thumb').forEach(t=>t.classList.remove('active'));
        this.classList.add('active');
        var src = this.getAttribute('data-src');
        var main = document.getElementById('mainImage');
        if(main && src) main.src = src;
    });
});

/* Add to cart via AJAX */
document.getElementById('addBtn')?.addEventListener('click', function(e){
    e.preventDefault();
    const qty = parseInt(document.getElementById('quantity').value || 1, 10);
    const productId = <?php echo intval($product_id); ?>;

    // Basic validation
    if (isNaN(qty) || qty < 1) {
        showAlert('Please enter a valid quantity', 'danger');
        return;
    }

    fetch('add-to-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.success) {
            showAlert('Product added to cart successfully!', 'success');
            if (typeof updateCartDisplay === 'function') updateCartDisplay();
        } else {
            showAlert((data && data.message) ? data.message : 'Failed to add product to cart', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('An error occurred while adding to cart', 'danger');
    });
});

function showAlert(message, type) {
    const wrapper = document.createElement('div');
    wrapper.className = `alert alert-${type} alert-dismissible fade show`;
    wrapper.style.margin = '16px';
    wrapper.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    const container = document.querySelector('.responsive-container') || document.body;
    container.insertBefore(wrapper, container.firstChild);
    setTimeout(() => wrapper.remove(), 5000);
}
</script>

<?php include '../includes/customer_footer.php'; ?>
