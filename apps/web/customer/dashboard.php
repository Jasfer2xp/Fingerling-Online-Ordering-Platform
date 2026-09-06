<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

/**
 * Geocode address to get coordinates
 */
function geocodeAddress($address) {
    if (empty($address) || strlen($address) < 6) {
        return ['success' => false, 'error' => 'Address too short for geocoding'];
    }

    try {
        $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address) . '&limit=1&countrycodes=ph';
        $context = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'Fingerling Online Ordering Platform/1.0']
        ]);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to geocoding service'];
        }
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
            return [
                'success' => true,
                'latitude' => (float)$data[0]['lat'],
                'longitude' => (float)$data[0]['lon']
            ];
        }
        return ['success' => false, 'error' => 'No coordinates found'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Geocoding error: ' . $e->getMessage()];
    }
}

if (!function_exists('resolve_supplier_logo_url')) {
    function resolve_supplier_logo_url($logo_url)
    {
        if (empty($logo_url)) {
            return '';
        }

        if (filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return $logo_url;
        }

        $clean_path = ltrim(str_replace(['../', './'], '', $logo_url), '/');
        $filename = basename($clean_path);
        $app_root = realpath(__DIR__ . '/..');

        if (!$app_root) {
            return '';
        }

        $candidates = array_unique([
            $clean_path,
            "uploads/logos/$filename",
            "uploads/$clean_path",
            "supplier/$clean_path"
        ]);

        foreach ($candidates as $candidate) {
            $absolute = $app_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (file_exists($absolute)) {
                return base_url(ltrim($candidate, '/'));
            }
        }

        return '';
    }
}

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) {
    unset($_SESSION['registration_just_completed']);
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);
$order = new Order($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

// Ensure registration is complete
if (
    !$just_completed_registration &&
    (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))
) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=customer&step=3'));
}

// Get all approved suppliers with location data
$sql = "SELECT s.*, 
               AVG(f.rating) as avg_rating,
               COUNT(f.id) as total_reviews,
               COUNT(DISTINCT i.id) as product_count
        FROM suppliers s
        LEFT JOIN feedback f ON s.id = f.supplier_id
        LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
        WHERE s.status = 'approved' AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
        GROUP BY s.id
        ORDER BY s.business_name";
$suppliers = $database->fetchAll($sql);

// Get customer location (use default if not available)
$customer_lat = $profile['latitude'] ?? null;
$customer_lng = $profile['longitude'] ?? null;

// If customer doesn't have coordinates, try to geocode their address
if (empty($customer_lat) || empty($customer_lng)) {
    // Build address from available location data
    $address_parts = [];
    if (!empty($profile['barangay'])) {
        $address_parts[] = $profile['barangay'];
    }
    if (!empty($profile['city'])) {
        $address_parts[] = $profile['city'];
    } else {
        $address_parts[] = 'Tangub City'; // Default city
    }
    if (!empty($profile['province'])) {
        $address_parts[] = $profile['province'];
    } else {
        $address_parts[] = 'Misamis Occidental'; // Default province
    }
    
    $address = implode(', ', $address_parts);
    
    // Try to geocode the address
    $geocode_result = geocodeAddress($address);
    if ($geocode_result['success']) {
        $customer_lat = $geocode_result['latitude'];
        $customer_lng = $geocode_result['longitude'];
    } else {
        // Fallback to default coordinates if geocoding fails
        $customer_lat = 8.1586;  // Default: Tangub City
        $customer_lng = 123.7478;
    }
} else {
    // Ensure we have float values
    $customer_lat = (float)$customer_lat;
    $customer_lng = (float)$customer_lng;
}

// Get featured products: Most sold fingerlings (1000+ sold)
$featured_products = $product->getTopPerformingProducts(8, ['min_sold' => 1000]);

// Get nearest suppliers (3 closest)
$sql = "
    SELECT * FROM (
        SELECT 
            s.*,
            (6371 * acos(
                cos(radians(?)) * cos(radians(s.latitude)) * 
                cos(radians(s.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(s.latitude))
            )) AS distance_km,
            COUNT(DISTINCT i.id) as product_count,
            COALESCE(AVG(f.rating), 0) as avg_rating,
            COUNT(DISTINCT f.customer_id) as total_reviews
        FROM suppliers s
        LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
        LEFT JOIN feedback f ON s.id = f.supplier_id
        WHERE s.status = 'approved'
          AND s.latitude IS NOT NULL 
          AND s.longitude IS NOT NULL
        GROUP BY s.id
    ) sub
    WHERE distance_km <= 50
    ORDER BY distance_km ASC
    LIMIT 3
";

$nearest_suppliers = $database->fetchAll($sql, [$customer_lat, $customer_lng, $customer_lat]);

// Get nearby suppliers' products
$nearby_products = $product->getNearbyProducts($customer_lat, $customer_lng, 50, 12);

$order_stats = $order->getOrderStats($customer_id);
$recent_orders = $order->getCustomerOrders($customer_id, null, 5);

$page_title = 'Customer Dashboard';
include '../includes/customer_header.php';
?>

<!-- ===================== DASHBOARD CONTENT ===================== -->
<main class="dashboard-wrapper">
    <div class="container dashboard-container">
        <div class="row g-4 justify-content-center">
            <div class="col-12">

                <!-- Most sold Fingerlings -->
                <section class="featured-products mb-5">
                    <div class="section-header d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold text-dark"><i class="fas fa-fire text-danger me-2"></i> Most Sold Fingerlings</h4>
                        <a href="products.php" class="see-all fw-semibold text-primary">See All</a>
                    </div>

                    <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3" id="productsGrid">
                        <?php if (empty($featured_products)): ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No fingerlings sold over 1,000 yet</h5>
                                    <p class="text-muted">Check back later for trending fingerlings!</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($featured_products as $product_item): ?>
                                <div class="col">
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
                                                 style="height: 180px; object-fit: cover; transition: transform 0.3s ease;">
                                        </div>

                                        <div class="card-body d-flex flex-column p-3">
                                            <h6 class="card-title mb-1 fw-semibold text-dark" style="font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($product_item['species_name']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted small"><i class="fas fa-store text-primary"></i> <?php echo htmlspecialchars($product_item['business_name']); ?></p>

                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="price-text fw-bold text-dark" style="font-size: 1.1rem;">
                                                        <?php echo format_currency($product_item['price_per_piece']); ?>
                                                    </div>
                                                    <div class="text-muted small"><?php echo number_format($product_item['stock_quantity']); ?> pcs</div>
                                                </div>
                                                
                                                <?php if (!empty($product_item['size_category'])): ?>
                                                    <div class="mb-2 small text-muted">size: <?php echo htmlspecialchars($product_item['size_category']); ?> inches</div>
                                                <?php endif; ?>

                                                <?php if (isset($product_item['minimum_order']) && $product_item['minimum_order'] > 1): ?>
                                                    <div class="mb-2 small text-warning"><i class="fas fa-info-circle"></i> Min: <?php echo $product_item['minimum_order']; ?> pcs</div>
                                                <?php endif; ?>

                                                <?php if ($product_item['rating'] > 0): ?>
                                                    <div class="d-flex align-items-center small mb-2">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= round($product_item['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="text-muted ms-1">(<?php echo number_format($product_item['total_ratings']); ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="card-footer bg-white border-0 p-3 pt-0">
                                            <div class="d-grid gap-2 d-md-flex">
                                                <a href="view.php?id=<?php echo $product_item['id']; ?>"
                                                   class="btn btn-outline-primary btn-sm rounded-pill flex-fill modern-btn-view">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-primary btn-sm rounded-pill flex-fill modern-btn-add add-to-cart"
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

                <!-- Nearest Suppliers in Your Area -->
                <section class="nearby-suppliers mb-5">
                    <div class="section-header d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold text-dark"><i class="fas fa-location-dot text-success me-2"></i> Nearest Suppliers in Your Area</h4>
                    </div>

                    <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 g-3">
                        <?php if (empty($nearest_suppliers)): ?>
                            <div class="col-12">
                                <div class="text-center py-5 bg-white rounded-3 shadow-sm">
                                    <i class="fas fa-store-slash fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No nearby suppliers found</h5>
                                    <p class="text-muted">Try adjusting your location or check back later.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($nearest_suppliers as $supplier): ?>
                                <div class="col">
                                    <div class="card supplier-card h-100 border-0 shadow-sm rounded-3 overflow-hidden d-flex flex-column">
                                        <!-- Profile Image on Top -->
                                        <div class="supplier-image-wrap position-relative bg-light d-flex align-items-center justify-content-center p-3">
                                            <?php 
                                            $profile_image = resolve_supplier_logo_url($supplier['logo_url'] ?? '') ?: asset_url('images/placeholder-fish.jpg');
                                            ?>
                                            <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                                 alt="<?php echo htmlspecialchars($supplier['business_name']); ?>"
                                                 class="img-fluid rounded-circle shadow"
                                                 style="width: 80px; height: 80px; object-fit: cover; border: 4px solid #fff;"
                                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>'; this.onerror=null;">
                                        </div>

                                        <!-- Details Below -->
                                        <div class="card-body d-flex flex-column p-3 flex-grow-1">
                                            <h6 class="card-title mb-2 fw-bold text-dark text-center" style="font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($supplier['business_name']); ?>
                                            </h6>

                                            <!-- Rating -->
                                            <div class="d-flex justify-content-center align-items-center mb-2 small">
                                                <span class="fw-bold me-1"><?php echo number_format($supplier['avg_rating'], 1); ?></span>
                                                <div class="me-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= round($supplier['avg_rating']) ? 'text-warning' : 'text-muted'; ?>" style="font-size: 0.8rem;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="text-muted">(<?php echo $supplier['total_reviews']; ?>)</span>
                                            </div>

                                            <!-- Delivery Options -->
                                            <div class="d-flex justify-content-center gap-1 mb-2 flex-wrap">
                                                <?php if ($supplier['supports_truck']): ?>
                                                    <span class="badge bg-success small px-2 py-1">Truck</span>
                                                <?php endif; ?>
                                                <?php if ($supplier['supports_boat']): ?>
                                                    <span class="badge bg-info small px-2 py-1">Boat</span>
                                                <?php endif; ?>
                                                <?php if (!$supplier['supports_truck'] && !$supplier['supports_boat']): ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Products & Distance -->
                                            <div class="text-center small text-muted mb-2">
                                                <i class="fas fa-box me-1"></i>
                                                <?php echo $supplier['product_count']; ?> Product<?php echo $supplier['product_count'] != 1 ? 's' : ''; ?>
                                            </div>

                                            <div class="text-center text-success small mb-3">
                                                <i class="fas fa-location-arrow me-1"></i>
                                                ~<?php echo round($supplier['distance_km'], 1); ?> km away
                                            </div>

                                            <!-- CTA Button -->
                                            <div class="mt-auto">
                                                <a href="supplier-profile.php?id=<?php echo $supplier['id']; ?>" 
                                                   class="btn btn-primary btn-sm w-100 rounded-pill py-2">
                                                    <i class="fas fa-store me-1"></i> View Shop
                                                </a>
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
// Add to cart functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const minOrder = this.dataset.minOrder ? parseInt(this.dataset.minOrder) : 1;
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
            showAlert('success', `${quantity} item${quantity > 1 ? 's' : ''} added to cart!`);
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
    if (el) {
        el.textContent = count > 0 ? count : '';
        el.style.display = count > 0 ? 'flex' : 'none';
    }
}

function showAlert(type, msg) {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show position-fixed shadow-lg`;
    div.style.cssText = 'top:20px; right:20px; z-index:9999; min-width:300px; border-radius:12px;';
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}
</script>

<!-- MODERN STYLES -->
<style>
/* Core Layout */
.dashboard-wrapper {
    background: #ffffff !important;
    padding: 2.5rem 0;
    min-height: 100vh;
}
.dashboard-container {
    max-width: 900px !important;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Section Headers */
.section-header h4 {
    font-weight: 700;
    color: #1f2937;
    font-size: 1.25rem;
}
.see-all {
    font-size: 0.95rem;
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.see-all:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

/* Product Cards */
.product-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f3f4f6 !important;
    background: #fff;
    border-radius: 16px !important;
    overflow: hidden;
}
.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08) !important;
    border-color: #dbeafe !important;
}
.product-card:hover .product-image {
    transform: scale(1.06);
}
.product-image {
    transition: transform 0.4s ease;
}

/* Supplier Card - Same as Product Card */
.supplier-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #f3f4f6 !important;
    background: #fff;
    border-radius: 16px !important;
    overflow: hidden;
}
.supplier-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08) !important;
    border-color: #dbeafe !important;
}
.supplier-image-wrap {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    min-height: 120px;
}
.supplier-card .badge {
    font-size: 0.7rem;
    font-weight: 500;
}

/* Buttons */
.modern-btn-view,
.modern-btn-add {
    font-size: 0.85rem !important;
    font-weight: 600;
    padding: 0.5rem 0.75rem !important;
    border-radius: 9999px !important;
    transition: all 0.2s ease;
}
.modern-btn-view {
    border: 1.5px solid #3b82f6 !important;
    color: #3b82f6;
    background: transparent;
}
.modern-btn-view:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}
.modern-btn-add {
    background: #2563eb !important;
    border: 1.5px solid #2563eb !important;
    color: white;
}
.modern-btn-add:hover {
    background: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
    .product-image, .supplier-image-wrap img { height: 70px !important; width: 70px !important; }
    .supplier-image-wrap { min-height: 100px; }
    .card-body { padding: 0.75rem !important; }
}
@media (max-width: 576px) {
    .row-cols-sm-2 > * { flex: 0 0 50%; max-width: 50%; }
}
</style>

<?php include '../includes/customer_footer.php'; ?>