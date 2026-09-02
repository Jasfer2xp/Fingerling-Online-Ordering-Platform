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

// Get user profile
$profile = $user->getUserProfile($user_id);

// Get analytics data
$default_end = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-30 days'));
$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;

$start_dt = DateTime::createFromFormat('Y-m-d', $start_date) ?: new DateTime($default_start);
$end_dt = DateTime::createFromFormat('Y-m-d', $end_date) ?: new DateTime($default_end);
if ($start_dt > $end_dt) {
    [$start_dt, $end_dt] = [$end_dt, $start_dt];
}

$max_range = new DateInterval('P5Y');
$min_allowed = (clone $end_dt)->sub($max_range);
if ($start_dt < $min_allowed) {
    $start_dt = $min_allowed;
}

$start_date = $start_dt->format('Y-m-d');
$end_date = $end_dt->format('Y-m-d');

function getSalesAnalyticsRange($database, $start_date, $end_date)
{
    $sql = "SELECT 
                DATE(o.created_at) as date,
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END), 0) as revenue,
                COUNT(DISTINCT o.customer_id) as unique_customers,
                COUNT(DISTINCT o.supplier_id) as active_suppliers
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC";

    return $database->fetchAll($sql, [$start_date, $end_date]);
}

$sales_analytics = getSalesAnalyticsRange($database, $start_date, $end_date);

// Get analytics data with date range
$supplier_performance = $admin->getSupplierPerformance($start_date, $end_date);
$popular_species = $admin->getPopularSpecies($start_date, $end_date);
$customer_activity = $admin->getCustomerActivity($start_date, $end_date);
$platform_stats = $admin->getPlatformStats($start_date, $end_date);

$page_title = 'Platform Analytics';

// Include header and sidebar
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- Custom Responsive CSS -->
<style>
    .role-main-content {
        min-height: 100vh;
        padding: 1.5rem 1rem;
    }

    .modern-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .modern-stat-card:hover {
        transform: translateY(-2px);
    }

    .card {
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        font-weight: 600;
        font-size: 1.1rem;
    }

    /* Chart Container Responsive Fix */
    .chart-container {
        position: relative;
        height: 280px;
        width: 100%;
    }

    @media (max-width: 768px) {
        .chart-container {
            height: 220px;
        }
    }

    @media (max-width: 576px) {
        .chart-container {
            height: 180px;
        }
    }

    /* Ensure tables don't overflow */
    .table-responsive {
        border-radius: 8px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Progress bars responsive */
    .progress {
        height: 8px;
        border-radius: 4px;
    }

    /* Button group wrap on small screens */
    .btn-group {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .btn-group .btn {
        flex: 1 1 auto;
        min-width: 100px;
    }

    @media (min-width: 576px) {
        .btn-group .btn {
            flex: 0 1 auto;
        }
    }

    /* Fix text alignment on mobile */
    @media (max-width: 576px) {
        .text-end {
            text-align: center !important;
            margin-top: 0.5rem;
        }
    }
</style>

<main class="role-main-content admin-main-content">
    <div class="container-fluid">

        <!-- Custom Date Range Selection -->
        <div class="row mb-4 align-items-end">
            <form method="GET" class="col-12 col-lg-8">
                <div class="row g-2">
                    <div class="col-sm-6 col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo htmlspecialchars($end_date); ?>" class="form-control" required>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" min="<?php echo htmlspecialchars($start_date); ?>" class="form-control" required>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply Range</button>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Select any range up to 5 years.</small>
            </form>
            <div class="col-12 col-lg-4 text-lg-end text-center mt-3 mt-lg-0">
                <span class="text-muted">
                    Analytics Range:<br>
                    <strong><?php echo date('M j, Y', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></strong>
                </span>
            </div>
        </div>

        <!-- Key Metrics -->
        <?php
        $success_rate = ($platform_stats['total_orders'] > 0)
            ? ($platform_stats['completed_orders'] / $platform_stats['total_orders']) * 100
            : 0;

        $avg_order = ($platform_stats['completed_orders'] > 0)
            ? $platform_stats['total_revenue'] / $platform_stats['completed_orders']
            : 0;
        ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Revenue</h6>
                            <p class="h5 mb-0 fw-bold"><?php echo format_currency($platform_stats['total_revenue'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-peso-sign fa-2x text-primary opacity-75"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Active Users</h6>
                            <p class="h5 mb-0 fw-bold"><?php echo number_format($platform_stats['total_customers'] + $platform_stats['total_suppliers']); ?></p>
                        </div>
                        <i class="fas fa-users fa-2x text-success opacity-75"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Order Success Rate</h6>
                            <p class="h5 mb-0 fw-bold"><?php echo number_format($success_rate, 1); ?>%</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-info opacity-75"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Avg Order Value</h6>
                            <p class="h5 mb-0 fw-bold"><?php echo format_currency($avg_order); ?></p>
                        </div>
                        <i class="fas fa-chart-line fa-2x text-warning opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i> Sales Trends
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="chart-container">
                            <canvas id="salesTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie text-info me-2"></i> User Distribution
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="chart-container" style="height: 240px !important;">
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 - Fully Responsive -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar text-success me-2"></i> Top Performing Suppliers
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="chart-container">
                            <canvas id="supplierPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-fish text-warning me-2"></i> Popular Species
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="chart-container">
                            <canvas id="popularSpeciesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy text-warning me-2"></i> Top Suppliers by Revenue
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($supplier_performance, 0, 10) as $supplier): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($supplier['business_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($supplier['city']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($supplier['completed_orders']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo format_currency($supplier['total_revenue'] ?? 0); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($supplier['rating'] > 0): ?>
                                                    <div class="rating-small d-inline">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted">(<?php echo $supplier['total_ratings']; ?>)</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No ratings</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-users text-info me-2"></i> Top Customers by Spending
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Orders</th>
                                        <th>Spent</th>
                                        <th>Last Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($customer_activity, 0, 10) as $customer): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($customer['city']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo number_format($customer['total_orders']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo format_currency($customer['total_spent']); ?></strong>
                                            </td>
                                            <td>
                                                <small><?php echo $customer['last_order_date'] ? format_date($customer['last_order_date']) : 'N/A'; ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Health -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat text-danger me-2"></i> Platform Health Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-6 col-md-3">
                                <h4 class="text-success"><?php echo number_format($success_rate, 1); ?>%</h4>
                                <p class="text-muted mb-2 small">Order Success Rate</p>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, $success_rate); ?>%"></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <h4 class="text-primary"><?php echo number_format($platform_stats['total_suppliers']); ?></h4>
                                <p class="text-muted mb-2 small">Active Suppliers</p>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <h4 class="text-info"><?php echo number_format($platform_stats['total_customers']); ?></h4>
                                <p class="text-muted mb-2 small">Registered Customers</p>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <h4 class="text-warning"><?php echo number_format($platform_stats['active_products']); ?></h4>
                                <p class="text-muted mb-2 small">Available Products</p>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Ensure DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        const salesData = <?php echo json_encode($sales_analytics); ?>;
        const supplierData = <?php echo json_encode(array_slice($supplier_performance, 0, 10)); ?>;
        const speciesData = <?php echo json_encode(array_slice($popular_species, 0, 8)); ?>;

        // Helper: Responsive font size
        const getFontSize = () => window.innerWidth < 576 ? 10 : window.innerWidth < 768 ? 11 : 12;

        // Sales Trends
        const salesCtx = document.getElementById('salesTrendsChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(d => new Date(d.date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })),
                datasets: [
                    {
                        label: 'Orders',
                        data: salesData.map(d => d.total_orders),
                        borderColor: '#36A2EB',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y',
                        pointRadius: 3
                    },
                    {
                        label: 'Revenue (₱)',
                        data: salesData.map(d => d.revenue),
                        borderColor: '#4BC0C0',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y1',
                        pointRadius: 3
                    },
                    {
                        label: 'Active Suppliers',
                        data: salesData.map(d => d.active_suppliers),
                        borderColor: '#FF6384',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y',
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: window.innerWidth < 768 ? 'bottom' : 'top', labels: { font: { size: getFontSize() } } },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Date' }, ticks: { font: { size: getFontSize() } } },
                    y: { position: 'left', title: { display: true, text: 'Count' } },
                    y1: { position: 'right', title: { display: true, text: 'Revenue (₱)' }, grid: { drawOnChartArea: false } }
                }
            }
        });

        // User Distribution
        new Chart(document.getElementById('userDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Customers', 'Suppliers', 'Admins'],
                datasets: [{
                    data: [<?php echo $platform_stats['total_customers']; ?>, <?php echo $platform_stats['total_suppliers']; ?>, 1],
                    backgroundColor: ['#36A2EB', '#4BC0C0', '#FF6384'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: getFontSize() } } }
                }
            }
        });

        // Supplier Performance
        new Chart(document.getElementById('supplierPerformanceChart'), {
            type: 'bar',
            data: {
                labels: supplierData.map(s => s.business_name.length > 12 ? s.business_name.substring(0,12)+'...' : s.business_name),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: supplierData.map(s => s.total_revenue || 0),
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() } }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Revenue (₱)' }, ticks: { font: { size: getFontSize() } } },
                    x: { ticks: { font: { size: getFontSize() }, maxRotation: 45, minRotation: 45 } }
                }
            }
        });

        // Popular Species
        new Chart(document.getElementById('popularSpeciesChart'), {
            type: 'bar',
            data: {
                labels: speciesData.map(s => s.name.length > 12 ? s.name.substring(0,12)+'...' : s.name),
                datasets: [{
                    label: 'Total Sold',
                    data: speciesData.map(s => s.total_sold || 0),
                    backgroundColor: 'rgba(255, 159, 64, 0.8)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Quantity Sold' }, ticks: { font: { size: getFontSize() } } },
                    x: { ticks: { font: { size: getFontSize() }, maxRotation: 45, minRotation: 45 } }
                }
            }
        });

        // Resize charts on window resize
        window.addEventListener('resize', () => {
            Chart.helpers.each(Chart.instances, chart => chart.resize());
        });
    });
</script>

</body>
</html>