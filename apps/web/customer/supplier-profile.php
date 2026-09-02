<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';

if (!function_exists('resolve_feedback_image_url')) {
    /**
     * Shared helper to resolve stored review images to a publicly accessible URL.
     */
    function resolve_feedback_image_url($image_path)
    {
        if (empty($image_path)) {
            return '';
        }

        if (filter_var($image_path, FILTER_VALIDATE_URL)) {
            return $image_path;
        }

        $clean_path = ltrim(str_replace(['../', './'], '', $image_path), '/');
        $filename = basename($clean_path);
        $app_root = realpath(__DIR__ . '/..');

        if (!$app_root) {
            return '';
        }

        $candidates = array_unique([
            $clean_path,
            "uploads/reviews/$filename",
            "customer/$clean_path",
            "customer/uploads/reviews/$filename",
            "customer/uploads/feedback/$filename",
            "uploads/feedback/$filename",
            "uploads/$clean_path"
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

if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$supplier_id = intval($_GET['id'] ?? 0);
if (!$supplier_id) {
    redirect(base_url('customer/suppliers.php'));
}

$user_id = get_user_id();
$customer = new Customer($database);

// Supplier details
$sql = "SELECT s.*, u.email, u.created_at as user_created_at,
               AVG(f.rating) as avg_rating,
               COUNT(DISTINCT f.customer_id) as total_reviews,
               COUNT(DISTINCT i.id) as product_count
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN feedback f ON s.id = f.supplier_id
        LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
        WHERE s.id = ? AND s.status = 'approved'
        GROUP BY s.id";
$supplier = $database->fetch($sql, [$supplier_id]);

if (!$supplier) {
    $_SESSION['error'] = 'Supplier not found or not available';
    redirect(base_url('customer/suppliers.php'));
}

$barangay = trim($supplier['barangay'] ?? '');
$purok = trim($supplier['purok'] ?? '');
$city = trim($supplier['city'] ?? '');
$province = trim($supplier['province'] ?? '');

$location_prefix = trim(implode(' ', array_filter([$barangay, $purok])));
$location_main = $location_prefix;
if ($city !== '') {
    $location_main = trim(($location_main ? $location_main . ' ' : '') . $city);
}
$supplier_location = $location_main;
if ($province !== '') {
    $supplier_location = $supplier_location ? $supplier_location . ', ' . $province : $province;
}
if (!$supplier_location) {
    $supplier_location = 'Not specified';
}

// Products
$sql = "SELECT i.*, sp.name as species_name, sp.scientific_name, sp.image_url, sp.category
        FROM inventory i
        JOIN species sp ON i.species_id = sp.id
        WHERE i.supplier_id = ? AND i.availability_status = 'available'
        ORDER BY sp.name, i.size_category";
$products = $database->fetchAll($sql, [$supplier_id]);

// Reviews — Now includes supplier_reply
$sql = "SELECT f.*, c.first_name, c.last_name, f.image_path, f.supplier_reply
        FROM feedback f
        JOIN customers c ON f.customer_id = c.id
        WHERE f.supplier_id = ?
        ORDER BY f.created_at DESC
        LIMIT 5";
$reviews = $database->fetchAll($sql, [$supplier_id]);

$page_title = $supplier['business_name'] . ' - Supplier Profile';
include '../includes/customer_header.php';
?>

<style>
/* General Layout */
body {
    background-color: #ffffff;
    color: #1e293b;
    font-family: "Poppins", sans-serif;
}

main.role-main-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Breadcrumb */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1.5rem;
}
.breadcrumb a {
    color: #0ea5e9;
    text-decoration: none;
    font-weight: 500;
}
.breadcrumb-item.active {
    color: #475569;
}

/* Supplier Header */
.supplier-header {
    background: #ffffff;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1.5rem;
}
.supplier-logo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
}
.supplier-info h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #0f172a;
}
.supplier-description {
    color: #64748b;
    line-height: 1.6;
}

/* Stats Section */
.stats-section .row {
    margin-left: -0.75rem;
    margin-right: -0.75rem;
}
.stats-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e2e8f0;
}
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.stats-card h3 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #0ea5e9;
}
.stats-card p {
    color: #64748b;
    margin-bottom: 0;
    font-size: 0.9rem;
}

/* Product Cards */
.products-section h5 {
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #0f172a;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e2e8f0;
}
.product-card {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e2e8f0;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.product-img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}
.product-placeholder {
    width: 100%;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f1f5f9;
}
.card-body {
    padding: 1.25rem;
}
.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #0f172a;
}
.view-all-btn {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    border: none;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    border-radius: 50px;
    transition: all 0.2s ease;
}
.view-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(2, 132, 199, 0.3);
}

/* Reviews Section */
.reviews-section h5 {
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #0f172a;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e2e8f0;
}
.review-item {
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
.review-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
.review-image {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 10px;
}

/* Supplier Reply Styling */
.supplier-reply {
    background-color: #f0f9ff;
    border-left: 4px solid #0ea5e9;
    padding: 12px 16px;
    border-radius: 0 8px 8px 8px;
    margin-top: 12px;
    font-size: 0.95rem;
}
.supplier-reply strong {
    color: #0c4a6e;
}

/* Rating Stars */
.rating-stars {
    color: #f59e0b;
}
.rating-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

/* Badges */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Responsive */
@media (max-width: 768px) {
    .supplier-header {
        flex-direction: column;
        text-align: center;
    }
    .supplier-logo {
        margin-bottom: 1rem;
    }
    .stats-section .col-6 {
        margin-bottom: 1rem;
    }
}

/* Map Section */
.map-section {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
}
.map-section h5 {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #0f172a;
}
#map {
    height: 300px;
    border-radius: 8px;
    z-index: 1;
}
</style>

<main role="main" class="role-main-content">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($supplier['business_name']); ?></li>
        </ol>
    </nav>

    <!-- Supplier Info -->
    <section class="supplier-header">
        <?php
        $logo_src = resolve_supplier_logo_url($supplier['logo_url'] ?? '') ?: asset_url('images/placeholder-fish.jpg');
        ?>
        <img src="<?php echo htmlspecialchars($logo_src); ?>" class="supplier-logo" alt="Supplier Logo"
             onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';this.onerror=null;">
        <div class="supplier-info">
            <h1><?php echo htmlspecialchars($supplier['business_name']); ?></h1>
            <div class="rating-display">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= round($supplier['avg_rating'] ?? 0)): ?>
                        <i class="fas fa-star rating-stars"></i>
                    <?php else: ?>
                        <i class="far fa-star rating-stars"></i>
                    <?php endif; ?>
                <?php endfor; ?>
                <span class="fw-bold"><?php echo number_format($supplier['avg_rating'] ?? 0, 1); ?></span>
                <span class="text-muted">(<?php echo $supplier['total_reviews'] ?? 0; ?> reviews)</span>
            </div>

            <!-- Delivery Options Display (Single Instance) -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">Delivery Options:</h6>
                <div class="d-flex justify-content-center gap-2">
                    <?php if (!empty($supplier['supports_truck'])): ?>
                        <span class="badge bg-primary">
                            Truck
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($supplier['supports_boat'])): ?>
                        <span class="badge bg-info">
                            Boat
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mb-2">
                <span class="badge bg-primary"><?php echo $supplier['product_count'] ?? 0; ?> Products</span>
                <span class="badge bg-success"><?php echo htmlspecialchars($supplier_location); ?></span>
            </div>
            
            <p class="supplier-description"><?php echo htmlspecialchars($supplier['description'] ?? ''); ?></p>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section mb-4">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3><?php echo number_format($supplier['avg_rating'] ?? 0, 1); ?></h3>
                    <p>Average Rating</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3><?php echo $supplier['total_reviews'] ?? 0; ?></h3>
                    <p>Total Reviews</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3><?php echo $supplier['product_count']; ?></h3>
                    <p>Products</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3><?php echo date('Y', strtotime($supplier['user_created_at'])); ?></h3>
                    <p>Member Since</p>
                </div>
            </div>
            
            <!-- Delivery Options Stats -->
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <h3>
                        <?php 
                            $delivery_options = 0;
                            if (!empty($supplier['supports_truck'])) $delivery_options++;
                            if (!empty($supplier['supports_boat'])) $delivery_options++;
                            echo $delivery_options;
                        ?>
                    </h3>
                    <p>Delivery Options</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Cards -->
    <section class="products-section">
        <h5>Available Products (<?php echo count($products); ?>)</h5>
        <?php if (empty($products)): ?>
            <div class="text-center py-4">
                <i class="fas fa-fish fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No products available</h6>
                <p class="text-muted">This supplier doesn't have any products listed at the moment.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="product-card h-100">
                        <?php
                        $image_src = asset_url('images/placeholder-fish.jpg');
                        if (!empty($product['image_path'])) {
                            $corrected_path = str_replace('../', '', $product['image_path']);
                            $image_src = base_url($corrected_path);
                        } elseif (!empty($product['image_url'])) {
                            $image_src = $product['image_url'];
                        }
                        ?>
                        <?php if ($image_src): ?>
                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="product-img" alt="<?php echo htmlspecialchars($product['species_name']); ?>">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="fas fa-fish fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($product['species_name'] ?? ''); ?></h6>
                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($product['scientific_name'] ?? ''); ?></p>
                            <p class="text-muted small mb-1">Size: <?php echo htmlspecialchars($product['size_category'] ?? ''); ?></p>
                            <p class="text-success fw-bold mb-2">₱<?php echo number_format($product['price_per_piece'], 2); ?> per piece</p>
                            <p class="text-muted small mb-3">Stock: <?php echo number_format($product['stock_quantity']); ?></p>

                            <a href="view.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm w-100">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-3">
                <a href="dashboard.php" class="btn btn-primary view-all-btn">
                    Browse All Products
                </a>
            </div>
        <?php endif; ?>
    </section>

    <!-- Reviews -->
    <?php if (!empty($reviews)): ?>
    <section class="reviews-section">
        <h5>Recent Reviews</h5>
        <?php foreach ($reviews as $review): ?>
            <div class="review-item">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-1"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h6>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['rating']): ?>
                                <i class="fas fa-star text-warning"></i>
                            <?php else: ?>
                                <i class="far fa-star text-warning"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <small class="text-muted"><?php echo time_ago($review['created_at']); ?></small>
                <?php if (!empty($review['comment'])): ?>
                    <p class="mt-2 mb-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($review['image_path'])): ?>
                    <?php
                    $review_image_src = resolve_feedback_image_url($review['image_path']);
                    if (!$review_image_src) {
                        $review_image_src = asset_url('images/placeholder-fish.jpg');
                    }
                    ?>
                    <div class="mt-2">
                        <img src="<?php echo htmlspecialchars($review_image_src); ?>" alt="Review Image" class="img-fluid review-image" 
                             onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';this.onerror=null;">
                    </div>
                <?php endif; ?>

                <!-- Supplier Reply (NEW) -->
                <?php if (!empty($review['supplier_reply'])): ?>
                    <div class="supplier-reply mt-3">
                        <strong>Reply from <?php echo htmlspecialchars($supplier['business_name']); ?>:</strong><br>
                        <?php echo nl2br(htmlspecialchars($review['supplier_reply'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>

<script>
function openGoogleMaps(lat, lng) {
    window.open(`https://www.google.com/maps/search/?api=1&query=${lat},${lng}', '_blank');
    
}
</script>

<?php include '../includes/customer_footer.php'; ?>