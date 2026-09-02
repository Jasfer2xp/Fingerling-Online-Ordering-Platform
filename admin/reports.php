<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);

// Get filter parameters
$report_type = $_GET['type'] ?? 'sales';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$supplier_id = $_GET['supplier_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;

if (!function_exists('fetch_products_report_data')) {
    function fetch_products_report_data($database, $date_from, $date_to, $supplier_id = '')
    {
        $params = [
            $date_from,
            $date_to,
            $date_from,
            $date_to
        ];

        $supplier_filter = '';
        if (!empty($supplier_id)) {
            $supplier_filter = "AND i.supplier_id = ?";
        }

        if (!empty($supplier_id)) {
            $params[] = $supplier_id;
        }

        $sql = "
            SELECT 
                sp.name AS name,
                s.business_name AS supplier_name,
                sp.category AS category_name,
                i.price_per_piece AS price,
                i.stock_quantity,
                i.availability_status AS status,
                COUNT(CASE WHEN o.id IS NOT NULL AND DATE(o.created_at) BETWEEN ? AND ? THEN 1 END) AS total_orders,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ? THEN oi.subtotal ELSE 0 END), 0) AS total_revenue
            FROM inventory i
            JOIN species sp ON i.species_id = sp.id
            JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN order_items oi ON oi.inventory_id = i.id
            LEFT JOIN orders o ON o.id = oi.order_id
            WHERE 1 = 1 {$supplier_filter}
            GROUP BY i.id
            ORDER BY total_revenue DESC
        ";

        return $database->fetchAll($sql, $params);
    }
}

if (!function_exists('report_build_query')) {
    function report_build_query(array $overrides = [])
    {
        $params = [
            'type' => $GLOBALS['report_type'],
            'date_from' => $GLOBALS['date_from'],
            'date_to' => $GLOBALS['date_to'],
        ];

        if (!empty($GLOBALS['supplier_id'])) {
            $params['supplier_id'] = $GLOBALS['supplier_id'];
        }

        return '?' . http_build_query(array_merge($params, $overrides));
    }
}

// Get data based on report type
$report_data = [];
switch ($report_type) {
    case 'sales':
        $report_data = $admin->getSalesReport($date_from, $date_to, $supplier_id);
        break;
    case 'orders':
        $report_data = $admin->getOrdersReport($date_from, $date_to, $supplier_id);
        break;
    case 'suppliers':
        $report_data = $admin->getSuppliersReport($date_from, $date_to);
        break;
    case 'customers':
        $report_data = $admin->getCustomersReport($date_from, $date_to);
        break;
    case 'products':
        $report_data = fetch_products_report_data($database, $date_from, $date_to, $supplier_id);
        break;
}

$total_records = count($report_data);
$total_pages = $total_records > 0 ? (int) ceil($total_records / $per_page) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$report_page_data = array_slice($report_data, $offset, $per_page);

// Get suppliers for filter
$suppliers = $admin->getAllSuppliers();

$page_title = 'Sales Reports';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- ====================== RESPONSIVE STYLES ====================== -->
<style>
    .admin-main-content { padding: 1rem; }
    .card { border-radius: 12px; overflow: hidden; }
    .table th { font-weight: 600; color: #374151; font-size: 0.875rem; }
    .table td { vertical-align: middle; font-size: 0.92rem; }

    /* Header */
    .modern-dashboard-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
        .modern-dashboard-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }

    /* Filters */
    .filter-form .col-md-3,
    .filter-form .col-md-2 {
        flex: 1 1 100%;
        max-width: 100%;
    }
    @media (min-width: 768px) {
        .filter-form .col-md-3 { flex: 0 0 25%; max-width: 25%; }
        .filter-form .col-md-2 { flex: 0 0 16.666%; max-width: 16.666%; }
    }

    /* Mobile: Hide desktop table, show cards */
    @media (max-width: 991.98px) {
        .desktop-table { display: none !important; }
        .mobile-card { display: block !important; }
        .dataTables_wrapper { display: none !important; }
    }
    .mobile-card { display: none; }

    /* No horizontal scroll */
    body { overflow-x: hidden; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    /* Export button */
    .export-btn-mobile { width: 100%; }
</style>

<main class="admin-main-content">
    <div class="container-fluid px-0 px-md-4">

        <!-- Dashboard Header -->
        <div class="modern-dashboard-header">
            <div class="d-flex align-items-center gap-2">
                <button class="modern-sidebar-toggle d-lg-none btn btn-outline-secondary btn-sm" type="button">
                    Menu
                </button>
                <h1 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-file-alt text-primary"></i>
                    Reports & Analytics
                </h1>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 export-btn-mobile" onclick="exportReport('csv')">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 filter-form">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="type" id="type">
                            <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="orders" <?php echo $report_type === 'orders' ? 'selected' : ''; ?>>Orders Report</option>
                            <option value="suppliers" <?php echo $report_type === 'suppliers' ? 'selected' : ''; ?>>Suppliers Report</option>
                            <option value="customers" <?php echo $report_type === 'customers' ? 'selected' : ''; ?>>Customers Report</option>
                            <option value="products" <?php echo $report_type === 'products' ? 'selected' : ''; ?>>Products Report</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select class="form-select" name="supplier_id">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_id == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['business_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Content -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-none d-lg-block">
                <h5 class="mb-0">
                    <?php 
                    $titles = [
                        'sales' => 'Sales Report',
                        'orders' => 'Orders Report',
                        'suppliers' => 'Suppliers Report',
                        'customers' => 'Customers Report',
                        'products' => 'Products Report'
                    ];
                    echo $titles[$report_type] ?? 'Report';
                    ?>
                    <small class="text-muted">(<?php echo date('M j, Y', strtotime($date_from)); ?> – <?php echo date('M j, Y', strtotime($date_to)); ?>)</small>
                </h5>
            </div>
            <div class="card-body p-0">

                <?php if (empty($report_data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No data available</h5>
                        <p class="text-muted">Try adjusting your filters.</p>
                    </div>
                <?php else: ?>

                    <!-- Desktop Table with DataTables -->
                    <div class="desktop-table">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0" id="reportTable">
                                <thead class="table-light">
                                    <?php if ($report_type === 'sales'): ?>
                                    <tr>
                                        <th>Date</th>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Supplier</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                    </tr>
                                    <?php elseif ($report_type === 'orders'): ?>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Supplier</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                    </tr>
                                    <?php elseif ($report_type === 'suppliers'): ?>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Location</th>
                                        <th>Total Orders</th>
                                        <th>Completed</th>
                                        <th>Revenue</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                    </tr>
                                    <?php elseif ($report_type === 'customers'): ?>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Orders</th>
                                        <th>Spent</th>
                                        <th>Last Order</th>
                                        <th>Status</th>
                                    </tr>
                                    <?php elseif ($report_type === 'products'): ?>
                                    <tr>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                        <th>Status</th>
                                    </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_page_data as $row): ?>
                                    <tr>
                                        <?php if ($report_type === 'sales'): ?>
                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td>#<?php echo $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td><?php echo $row['total_items']; ?></td>
                                            <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td><span class="badge bg-<?php echo getStatusColor($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <?php elseif ($report_type === 'orders'): ?>
                                            <td>#<?php echo $row['id']; ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td><span class="badge bg-<?php echo getStatusColor($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                            <td><span class="badge bg-<?php echo getPaymentStatusColor($row['payment_status']); ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
                                            <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <?php elseif ($report_type === 'suppliers'): ?>
                                            <td><?php echo htmlspecialchars($row['business_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['city'] . ', ' . $row['province']); ?></td>
                                            <td><?php echo number_format($row['total_orders']); ?></td>
                                            <td><?php echo number_format($row['completed_orders']); ?></td>
                                            <td>₱<?php echo number_format($row['total_revenue'], 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-1"><?php echo number_format($row['rating'], 1); ?></span>
                                                    <div class="text-warning small">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $row['rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $row['status'] === 'approved' ? 'success' : 'warning'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <?php elseif ($report_type === 'customers'): ?>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                            <td><?php echo number_format($row['total_orders']); ?></td>
                                            <td>₱<?php echo number_format($row['total_spent'], 2); ?></td>
                                            <td><?php echo $row['last_order'] ? date('M d, Y', strtotime($row['last_order'])) : 'Never'; ?></td>
                                            <td><span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <?php elseif ($report_type === 'products'): ?>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                            <td>₱<?php echo number_format($row['price'], 2); ?></td>
                                            <td><?php echo number_format($row['stock_quantity']); ?></td>
                                            <td><?php echo number_format($row['total_orders']); ?></td>
                                            <td>₱<?php echo number_format($row['total_revenue'], 2); ?></td>
                                            <td><span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-card">
                        <?php foreach ($report_page_data as $row): ?>
                        <div class="border-bottom p-3">
                            <?php if ($report_type === 'sales'): ?>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>#<?php echo $row['id']; ?></strong>
                                        <span class="badge bg-<?php echo getStatusColor($row['status']); ?> ms-2"><?php echo ucfirst($row['status']); ?></span>
                                    </div>
                                    <div class="text-end small text-muted">
                                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    <div><strong>Customer:</strong> <?php echo htmlspecialchars($row['customer_name']); ?></div>
                                    <div><strong>Supplier:</strong> <?php echo htmlspecialchars($row['supplier_name']); ?></div>
                                    <div><strong>Items:</strong> <?php echo $row['total_items']; ?> · <strong>Total:</strong> ₱<?php echo number_format($row['total_amount'], 2); ?></div>
                                </div>

                            <?php elseif ($report_type === 'orders'): ?>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>#<?php echo $row['id']; ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo getStatusColor($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span>
                                        <span class="badge bg-<?php echo getPaymentStatusColor($row['payment_status']); ?> ms-1"><?php echo ucfirst($row['payment_status']); ?></span>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    <div><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></div>
                                    <div><strong>Customer:</strong> <?php echo htmlspecialchars($row['customer_name']); ?></div>
                                    <div><strong>Supplier:</strong> <?php echo htmlspecialchars($row['supplier_name']); ?></div>
                                    <div><strong>Total:</strong> ₱<?php echo number_format($row['total_amount'], 2); ?></div>
                                </div>

                            <?php elseif ($report_type === 'suppliers'): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($row['business_name']); ?></strong>
                                    <span class="badge bg-<?php echo $row['status'] === 'approved' ? 'success' : 'warning'; ?> ms-2"><?php echo ucfirst($row['status']); ?></span>
                                </div>
                                <div class="small text-muted">
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars($row['city'] . ', ' . $row['province']); ?></div>
                                    <div><strong>Orders:</strong> <?php echo number_format($row['total_orders']); ?> (<?php echo number_format($row['completed_orders']); ?> completed)</div>
                                    <div><strong>Revenue:</strong> ₱<?php echo number_format($row['total_revenue'], 2); ?></div>
                                    <div><strong>Rating:</strong> <?php echo number_format($row['rating'], 1); ?> Stars</div>
                                </div>

                            <?php elseif ($report_type === 'customers'): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?> ms-2"><?php echo ucfirst($row['status']); ?></span>
                                </div>
                                <div class="small text-muted">
                                    <div><strong>Email:</strong> <?php echo htmlspecialchars($row['email']); ?></div>
                                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($row['phone']); ?></div>
                                    <div><strong>Orders:</strong> <?php echo number_format($row['total_orders']); ?> · <strong>Spent:</strong> ₱<?php echo number_format($row['total_spent'], 2); ?></div>
                                    <div><strong>Last Order:</strong> <?php echo $row['last_order'] ? date('M d, Y', strtotime($row['last_order'])) : 'Never'; ?></div>
                                </div>

                            <?php elseif ($report_type === 'products'): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'secondary'; ?> ms-2"><?php echo ucfirst($row['status']); ?></span>
                                </div>
                                <div class="small text-muted">
                                    <div><strong>Supplier:</strong> <?php echo htmlspecialchars($row['supplier_name']); ?></div>
                                    <div><strong>Category:</strong> <?php echo htmlspecialchars($row['category_name']); ?></div>
                                    <div><strong>Price:</strong> ₱<?php echo number_format($row['price'], 2); ?> · <strong>Stock:</strong> <?php echo number_format($row['stock_quantity']); ?></div>
                                    <div><strong>Orders:</strong> <?php echo number_format($row['total_orders']); ?> · <strong>Revenue:</strong> ₱<?php echo number_format($row['total_revenue'], 2); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($report_data)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center px-4 py-3 border-top">
                <span class="text-muted small mb-2 mb-md-0">
                    Showing
                    <?php
                        $from_record = $total_records ? ($offset + 1) : 0;
                        $to_record = $total_records ? min($offset + count($report_page_data), $total_records) : 0;
                    ?>
                    <?php echo $from_record; ?>–<?php echo $to_record; ?> of <?php echo $total_records; ?> records
                </span>
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 flex-wrap">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : report_build_query(['page' => $page - 1]); ?>">
                                    Previous
                                </a>
                            </li>
                            <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo report_build_query(['page' => $i]); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : report_build_query(['page' => $page + 1]); ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function exportReport(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('format', format);
        window.open('export-report.php?' + params.toString(), '_blank');
    }
</script>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'completed':
        case 'delivered':
            return 'success';
        case 'pending':
        case 'processing':
            return 'warning';
        case 'cancelled':
        case 'failed':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getPaymentStatusColor($status) {
    switch ($status) {
        case 'paid':
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
        case 'refunded':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

</body>
</html>