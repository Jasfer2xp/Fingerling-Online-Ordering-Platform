<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get date filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today

// Get sales data
$sales_data = $admin->getSalesReport($date_from, $date_to);
$top_products = $admin->getTopSellingProducts($date_from, $date_to, 10);
$top_suppliers = $admin->getTopSuppliers($date_from, $date_to, 10);
$daily_sales = $admin->getDailySales($date_from, $date_to);

$page_title = 'Sales Reports';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-outline-secondary modern-btn-sm" onclick="exportReport()">
    <i class="fas fa-download"></i> Export
</button>';

include '../includes/modern_admin_header.php';
?>

<?php include '../includes/modern_admin_sidebar.php'; ?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <?php include '../includes/modern_admin_dashboard_header.php'; ?>


            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sales Reports</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('today')">Today</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('week')">This Week</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('month')">This Month</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('year')">This Year</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sales Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo format_currency($sales_data['total_revenue'] ?? 0); ?></h4>
                                    <p class="mb-0">Total Revenue</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($sales_data['total_orders'] ?? 0); ?></h4>
                                    <p class="mb-0">Total Orders</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($sales_data['total_items'] ?? 0); ?></h4>
                                    <p class="mb-0">Items Sold</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-fish fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo format_currency(($sales_data['total_revenue'] ?? 0) / max(1, $sales_data['total_orders'] ?? 1)); ?></h4>
                                    <p class="mb-0">Avg Order Value</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daily Sales Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Sales by Category</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products and Suppliers -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Selling Products</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['species_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo ucfirst($product['size_category']); ?></small>
                                                </td>
                                                <td><?php echo number_format($product['total_sold']); ?></td>
                                                <td><?php echo format_currency($product['total_revenue']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Performing Suppliers</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Supplier</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_suppliers as $supplier): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($supplier['business_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($supplier['city']); ?></small>
                                                </td>
                                                <td><?php echo number_format($supplier['total_orders']); ?></td>
                                                <td><?php echo format_currency($supplier['total_revenue']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
        datasets: [{
            label: 'Daily Revenue',
            data: <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Category Chart (placeholder data)
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: ['Freshwater', 'Saltwater', 'Brackish'],
        datasets: [{
            data: [60, 25, 15],
            backgroundColor: [
                'rgb(54, 162, 235)',
                'rgb(255, 99, 132)',
                'rgb(255, 205, 86)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

function setDateRange(range) {
    const today = new Date();
    let fromDate, toDate = today.toISOString().split('T')[0];
    
    switch(range) {
        case 'today':
            fromDate = toDate;
            break;
        case 'week':
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            fromDate = weekAgo.toISOString().split('T')[0];
            break;
        case 'month':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            break;
        case 'year':
            fromDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('date_from').value = fromDate;
    document.getElementById('date_to').value = toDate;
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = `export-sales-report.php?${params.toString()}`;
}

function printReport() {
    window.print();
}
</script>


