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

// Get customer insights data
try {
    $customer_insights = $supplier->getCustomerInsights();
    $geographic_insights = $supplier->getGeographicInsights();
    $customer_analytics = $supplier->getCustomerAnalytics();
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to load customer data: ' . $e->getMessage();
    $customer_insights = [];
    $geographic_insights = [];
    $customer_analytics = ['total_customers' => 0, 'active_customers' => 0, 'new_customers' => 0, 'retention_rate' => 0];
}

// Get top customers
$sql = "SELECT
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            u.email,
            c.contact_number,
            c.city,
            c.province,
            COUNT(o.id) as total_orders,
            SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent,
            AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END) as avg_order_value,
            MAX(o.created_at) as last_order_date,
            MIN(o.created_at) as first_order_date
        FROM customers c
        JOIN users u ON c.user_id = u.id
        JOIN orders o ON c.id = o.customer_id
        WHERE o.supplier_id = ?
        GROUP BY c.id, c.first_name, c.last_name, u.email, c.contact_number, c.city, c.province
        ORDER BY total_spent DESC
        LIMIT 20";

try {
    $top_customers = $database->fetchAll($sql, [$supplier_id]);
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to load top customers: ' . $e->getMessage();
    $top_customers = [];
}

// Handle CSV export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    try {
        $csv = "Customer Name,Email,Contact Number,City,Province,Total Orders,Total Spent,Avg Order Value,Last Order Date,First Order Date\n";
        foreach ($top_customers as $customer) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%.2f,%.2f,\"%s\",\"%s\"\n",
                str_replace('"', '""', $customer['customer_name']),
                str_replace('"', '""', $customer['email']),
                str_replace('"', '""', $customer['contact_number']),
                str_replace('"', '""', $customer['city']),
                str_replace('"', '""', $customer['province']),
                $customer['total_orders'],
                $customer['total_spent'],
                $customer['avg_order_value'] ?? 0,
                $customer['last_order_date'],
                $customer['first_order_date']
            );
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=customer_insights_' . date('Y-m-d') . '.csv');
        echo $csv;
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to export customer data: ' . $e->getMessage();
        redirect(base_url('supplier/customer-insights.php'));
    }
}

$page_title = 'Customer Insights';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="insights-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold"><i class="fas fa-users me-2"></i>Customer Insights</h3>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" data-bs-toggle="tooltip" title="Print this report">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_csv">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Export data as CSV">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Customer Overview Cards -->
        <section class="customer-overview mb-5">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Total Customers</div>
                                    <h5 class="mb-0"><?php echo number_format($customer_analytics['stats']['total_customers'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-users fa-2x text-primary ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Active Customers</div>
                                    <h5 class="mb-0"><?php echo number_format(count($customer_analytics['top_customers'] ?? [])); ?></h5>
                                </div>
                                <i class="fas fa-user-check fa-2x text-success ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">New This Month</div>
                                    <h5 class="mb-0"><?php echo number_format($customer_analytics['new_customers'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-user-plus fa-2x text-info ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Retention Rate</div>
                                    <h5 class="mb-0"><?php echo number_format($customer_analytics['retention_rate'] ?? 0, 1); ?>%</h5>
                                </div>
                                <i class="fas fa-heart fa-2x text-warning ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top Customers -->
        <section class="top-customers mb-5">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="fas fa-star me-2"></i>Top Customers</h5>
                    <?php if (!empty($top_customers)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="table-light">
                                        <th>Customer</th>
                                        <th>Location</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Avg Order</th>
                                        <th>Last Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($customer['city']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($customer['province']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary" data-bs-toggle="tooltip" title="<?php echo number_format($customer['total_orders']); ?> orders"><?php echo number_format($customer['total_orders']); ?></span>
                                            </td>
                                            <td>
                                                <strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                            </td>
                                            <td>₱<?php echo number_format($customer['avg_order_value'] ?? 0, 2); ?></td>
                                            <td>
                                                <?php echo format_date($customer['last_order_date']); ?><br>
                                                <small class="text-muted">Since <?php echo date('M Y', strtotime($customer['first_order_date'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Customer Data</h5>
                            <p class="text-muted">No customer data available yet. Complete some orders to see customer insights!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Geographic Distribution -->
        <!-- Removed as per user request -->
    </div>
</div>
<style>
    .alert {
        border-radius: 0.5rem;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .table a {
        text-decoration: none;
    }
    .table a:hover {
        text-decoration: underline;
    }
    .badge, .progress-bar {
        padding: 0.5em 0.75em;
    }
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .form-control, .form-select, .btn-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .card-title, h5 {
            font-size: 1.1rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .progress {
            height: 6px;
        }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});
</script>
</body>
</html>