<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$product_filter = $_GET['product'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$where_conditions = ["o.supplier_id = ?"];
$params = [$supplier_id];

if ($date_from) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($product_filter) {
    $where_conditions[] = "s.id = ?";
    $params[] = $product_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count and totals
$count_sql = "SELECT COUNT(DISTINCT o.id) as total, 
                     SUM(CASE WHEN o.status IN ('confirmed_and_paid', 'preparing', 'scheduled_for_delivery', 'out_for_delivery', 'delivered') THEN o.total_amount ELSE 0 END) as total_revenue,
                     SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as completed_revenue
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN inventory inv ON oi.inventory_id = inv.id
              LEFT JOIN species s ON inv.species_id = s.id
              WHERE {$where_clause}";
$totals = $database->fetch($count_sql, $params);

$total_orders = $totals['total'] ?? 0;
$total_revenue = $totals['total_revenue'] ?? 0;
$completed_revenue = $totals['completed_revenue'] ?? 0;
$total_pages = ceil($total_orders / $per_page);

// Get sales list
$sales_sql = "SELECT o.id, o.order_number, o.created_at, o.status, o.total_amount,
                     CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                     s.name as product_name,
                     oi.quantity, oi.unit_price, oi.subtotal
              FROM orders o
              JOIN customers c ON o.customer_id = c.id
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN inventory inv ON oi.inventory_id = inv.id
              LEFT JOIN species s ON inv.species_id = s.id
              WHERE {$where_clause}
              ORDER BY o.created_at DESC
              LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$sales = $database->fetchAll($sales_sql, $params);

// Get products for filter dropdown
$products_sql = "SELECT DISTINCT s.id, s.name
                 FROM species s
                 JOIN inventory inv ON s.id = inv.species_id
                 JOIN order_items oi ON inv.id = oi.inventory_id
                 JOIN orders o ON oi.order_id = o.id
                 WHERE o.supplier_id = ?
                 ORDER BY s.name";
$products = $database->fetchAll($products_sql, [$supplier_id]);

$page_title = 'Sales Report';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <section class="sales-header mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h3 class="fw-bold mb-0"><i class="fas fa-chart-line me-2"></i>Order Report</h3>
                    </div>
                </section>

                <!-- Summary Cards -->
                <section class="sales-summary mb-4">
                    <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1">₱<?php echo number_format($total_revenue, 2); ?></h4>
                                <p class="card-text text-muted">Total Revenue</p>
                            </div>
                            <i class="fas fa-peso-sign fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1">₱<?php echo number_format($completed_revenue, 2); ?></h4>
                                <p class="card-text text-muted">Completed Sales</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1"><?php echo number_format($total_orders); ?></h4>
                                <p class="card-text text-muted">Total Orders</p>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

                <!-- Filters -->
                <section class="sales-filters mb-4">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <form method="GET" action="sales.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select form-select-sm" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="confirmed_and_paid" <?php echo $status_filter === 'confirmed_and_paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="out_for_delivery" <?php echo $status_filter === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="product" class="form-label">Product</label>
                            <select class="form-select form-select-sm" id="product" name="product">
                                <option value="">All Products</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-filter me-1"></i>Apply Filters
                            </button>
                            <a href="sales.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </section>

                <!-- Sales Table -->
                <section class="sales-table">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless align-middle mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th class="py-3">Order #</th>
                                    <th class="py-3">Date</th>
                                    <th class="py-3">Customer</th>
                                    <th class="py-3">Product</th>
                                    <th class="py-3">Quantity</th>
                                    <th class="py-3">Unit Price</th>
                                    <th class="py-3">Subtotal</th>
                                    <th class="py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No sales found for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $sale['id']; ?>" class="text-primary text-decoration-none">
                                                    <?php echo htmlspecialchars($sale['order_number']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($sale['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['product_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo number_format($sale['quantity'] ?? 0); ?></td>
                                            <td>₱<?php echo number_format($sale['unit_price'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($sale['subtotal'] ?? $sale['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($sale['status']) {
                                                        'pending' => 'secondary',
                                                        'confirmed' => 'warning',
                                                        'confirmed_and_paid' => 'primary',
                                                        'preparing' => 'info',
                                                        'out_for_delivery' => 'warning',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $sale['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-3 px-3 pb-3">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>



