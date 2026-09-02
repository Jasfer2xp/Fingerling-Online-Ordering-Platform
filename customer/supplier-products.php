<?php
/**
 * Customer Browse Products Page
 * Displays products from a specific supplier or all products
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

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$product = new Product($database);
$supplier = new Supplier($database);

// Get supplier ID from URL parameter if provided
$supplier_id = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;

// Get supplier info if a specific supplier is requested
$supplier_info = null;
if ($supplier_id) {
    $sql = "SELECT s.*, 
                   AVG(f.rating) as avg_rating,
                   COUNT(f.id) as total_reviews,
                   COUNT(DISTINCT i.id) as product_count
            FROM suppliers s
            LEFT JOIN feedback f ON s.id = f.supplier_id
            LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
            WHERE s.id = ? AND s.status = 'approved'
            GROUP BY s.id";
    $supplier_info = $database->fetch($sql, [$supplier_id]);
    
    if (!$supplier_info) {
        $_SESSION['error'] = 'Supplier not found or not available';
        redirect(base_url('customer/suppliers.php'));
    }
}

// Get products based on supplier filter
if ($supplier_id) {
    // Get products from specific supplier
    $sql = "SELECT i.*, sp.name as species_name, sp.scientific_name, sp.image_url, sp.category
            FROM inventory i
            JOIN species sp ON i.species_id = sp.id
            WHERE i.supplier_id = ? AND i.availability_status = 'available'
            ORDER BY sp.name, i.size_category";
    $products = $database->fetchAll($sql, [$supplier_id]);
    $page_title = 'Products from ' . $supplier_info['business_name'];
} else {
    // Get all products from all suppliers
    $sql = "SELECT i.*, sp.name as species_name, sp.scientific_name, sp.image_url, sp.category,
                   s.business_name, s.city, s.province
            FROM inventory i
            JOIN species sp ON i.species_id = sp.id
            JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.availability_status = 'available' AND s.status = 'approved'
            ORDER BY sp.name, i.size_category";
    $products = $database->fetchAll($sql);
    $page_title = 'All Products';
}

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

/* Header */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.5rem;
}

/* Products Section */
.products-section {
    background: #ffffff;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.products-section h5 {
    font-size: 1.3rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 1.5rem;
}
.product-card {
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}
.product-card:hover {
    transform: translateY(-5px);
}
.product-img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.product-placeholder {
    width: 100%;
    height: 200px;
    background: #e0f2fe;
    display: flex;
    align-items: center;
    justify-content: center;
}
.product-card .card-body {
    padding: 1rem;
}
.product-card .card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
}
.product-card .text-muted {
    font-size: 0.85rem;
    color: #64748b;
}
.product-card .text-success {
    font-size: 1.1rem;
    font-weight: 600;
    color: #10b981;
}
.product-card .btn-primary {
    background-color: #0ea5e9;
    border-color: #0ea5e9;
    border-radius: 20px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
}
.product-card .btn-primary:hover {
    background-color: #0284c7;
    border-color: #0284c7;
}

/* Supplier Info */
.supplier-info {
    background: #f1f5f9;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}
.supplier-info h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
}
.supplier-info p {
    margin-bottom: 0.25rem;
}

/* Responsive */
@media (max-width: 768px) {
    main.role-main-content {
        padding: 1rem;
    }
    .product-img, .product-placeholder {
        height: 180px;
    }
}
</style>

<main class="role-main-content customer-main-content">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="suppliers.php">Suppliers</a></li>
            <?php if ($supplier_info): ?>
                <li class="breadcrumb-item"><a href="supplier-profile.php?id=<?php echo $supplier_id; ?>"><?php echo htmlspecialchars($supplier_info['business_name']); ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Products</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1>
            <?php if ($supplier_info): ?>
                Products from <?php echo htmlspecialchars($supplier_info['business_name']); ?>
            <?php else: ?>
                All Available Products
            <?php endif; ?>
        </h1>
        <p class="text-muted">
            <?php echo count($products); ?> product<?php echo count($products) != 1 ? 's' : ''; ?> found
        </p>
    </div>

    <?php if ($supplier_info): ?>
        <div class="supplier-info">
            <h3><?php echo htmlspecialchars($supplier_info['business_name']); ?></h3>
            <p><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($supplier_info['city']); ?>, <?php echo htmlspecialchars($supplier_info['province']); ?></p>
            <p><i class="fas fa-fish me-1"></i> <?php echo $supplier_info['product_count']; ?> Available Products</p>
            <p><i class="fas fa-star text-warning me-1"></i> <?php echo number_format($supplier_info['avg_rating'] ?? 0, 1); ?> Average Rating (<?php echo $supplier_info['total_reviews']; ?> reviews)</p>
        </div>
    <?php endif; ?>

    <section class="products-section">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="fas fa-fish fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">No products available</h3>
                <p class="text-muted">
                    <?php if ($supplier_info): ?>
                        This supplier doesn't have any products listed at the moment.
                    <?php else: ?>
                        No products are currently available.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="product-card h-100">
                            <?php
                            // Handle image path correctly
                            $image_src = '';
                            if (!empty($product['image_path'])) {
                                // Fix any incorrect paths that start with ../
                                $image_path = str_replace('../', '', $product['image_path']);
                                $image_src = base_url($image_path);
                            } elseif (!empty($product['image_url'])) {
                                $image_src = $product['image_url'];
                            } else {
                                $image_src = asset_url('images/placeholder-fish.jpg');
                            }
                            ?>
                            <?php if ($image_src): ?>
                                <img src="<?php echo $image_src; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['species_name']); ?>">
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
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include '../includes/customer_footer.php'; ?>