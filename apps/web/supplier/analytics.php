<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$database = new Database();
$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Get analytics data
$period = $_GET['period'] ?? 'month';
$sales_analytics = $supplier->getSalesAnalytics($period);
$inventory_analytics = $supplier->getInventoryAnalytics();
$inventory_performance = $supplier->getInventoryPerformance();
$customer_analytics = $supplier->getCustomerAnalytics();

// Extract key metrics
$total_revenue = $sales_analytics['summary']['total_revenue'] ?? 0;
$total_orders = $sales_analytics['summary']['total_orders'] ?? 0;
$avg_order_value = $sales_analytics['summary']['average_order_value'] ?? 0;
$total_customers = $customer_analytics['stats']['total_customers'] ?? 0;

// Calculate sales trend direction (for arrow indicator)
$sales_trend_direction = 'neutral'; // Default
$trend_data = $sales_analytics['trend'] ?? [];
if (count($trend_data) >= 2) {
    $first_revenue = floatval($trend_data[0]['revenue'] ?? 0);
    $last_revenue = floatval($trend_data[count($trend_data) - 1]['revenue'] ?? 0);
    
    if ($last_revenue > $first_revenue) {
        $sales_trend_direction = 'up';
    } elseif ($last_revenue < $first_revenue) {
        $sales_trend_direction = 'down';
    }
}

$page_title = 'Analytics';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header -->
        <section class="analytics-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h3 class="fw-bold mb-0">Analytics</h3>
                <select class="form-select w-auto" id="periodSelect" onchange="changePeriod()">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                </select>
            </div>
        </section>

        <!-- Key Metrics -->
        <section class="key-metrics mb-5">
            <h4 class="fw-bold mb-4 text-dark">Key Metrics</h4>
            <div class="row g-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-peso-sign fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold">₱<?php echo number_format($total_revenue, 2); ?></h5>
                                <p class="text-muted small mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-shopping-cart fa-2x text-success"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo number_format($total_orders); ?></h5>
                                <p class="text-muted small mb-0">Total Orders</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-users fa-2x text-info"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo number_format($total_customers); ?></h5>
                                <p class="text-muted small mb-0">Total Customers</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-calculator fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold">₱<?php echo number_format($avg_order_value, 2); ?></h5>
                                <p class="text-muted small mb-0">Avg Order Value</p>
                                <?php if ($sales_trend_direction === 'up'): ?>
                                    <span class="text-success small"><i class="fas fa-arrow-up"></i> Sales increasing</span>
                                <?php elseif ($sales_trend_direction === 'down'): ?>
                                    <span class="text-danger small"><i class="fas fa-arrow-down"></i> Sales decreasing</span>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="fas fa-arrow-right"></i> Sales stable</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-5">

        <!-- Sales Trend Chart -->
        <section class="charts mb-5">
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0 text-dark">Sales Trend</h5>
                                <div>
                                    <?php if ($sales_trend_direction === 'up'): ?>
                                        <span class="text-success"><i class="fas fa-arrow-up"></i> Sales increasing</span>
                                    <?php elseif ($sales_trend_direction === 'down'): ?>
                                        <span class="text-danger"><i class="fas fa-arrow-down"></i> Sales decreasing</span>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-arrow-right"></i> Sales stable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-5">

        <!-- Detailed Analytics -->
        <section class="detailed-analytics">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3 text-dark">Inventory Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Stock</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($inventory_performance)): ?>
                                            <?php foreach ($inventory_performance as $item): ?>
                                                <tr>
                                                    <td class="fw-medium">
                                                        <?php echo htmlspecialchars($item['species_name'] ?? 'Unknown'); ?>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($item['size_category'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo number_format($item['stock_quantity'] ?? 0); ?></td>
                                                    <td><?php echo number_format($item['total_sold'] ?? 0); ?></td>
                                                    <td class="fw-bold text-success">₱<?php echo number_format($item['total_revenue'] ?? 0, 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3 text-dark">Customer Insights</h5>
                            <div class="row text-center g-3">
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-primary mb-1"><?php echo number_format($customer_analytics['stats']['total_customers'] ?? 0); ?></h3>
                                        <p class="text-muted small mb-0">Total Customers</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-success mb-1"><?php echo number_format($customer_analytics['stats']['total_orders'] ?? 0); ?></h3>
                                        <p class="text-muted small mb-0">Total Orders</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded mt-2">
                                        <h4 class="text-info mb-1">₱<?php echo number_format($customer_analytics['stats']['total_revenue'] ?? 0, 2); ?></h4>
                                        <p class="text-muted small mb-0">Total Revenue</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Change period
function changePeriod() {
    const period = document.getElementById('periodSelect').value;
    window.location.href = `analytics.php?period=${period}`;
}

// Sales Trend Chart — Professional, Clean, Business-Focused
const ctx = document.getElementById('salesChart');
<?php
// Extract real data from $sales_analytics['trend']
$labels = [];
$data = [];
$tooltips = [];

if (!empty($sales_analytics['trend'])) {
    foreach ($sales_analytics['trend'] as $point) {
        $labels[] = $point['label'] ?? '';
        $data[] = floatval($point['revenue'] ?? 0);
        $tooltips[] = '₱' . number_format($point['revenue'] ?? 0, 2);
    }
} else {
    $labels = ['No Data'];
    $data = [0];
    $tooltips = ['₱0.00'];
}
?>

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode($data); ?>,
            borderColor: '#4361ee',
            backgroundColor: 'rgba(67, 97, 238, 0.1)',
            borderWidth: 2.5,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#4361ee',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                cornerRadius: 8,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Revenue: ₱' + parseFloat(context.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    title: function(context) {
                        return context[0].label;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#6c757d' }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                ticks: {
                    color: '#6c757d',
                    callback: function(value) {
                        // Format currency properly
                        return '₱' + parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                }
            }
        }
    }
});

// Mobile: Sidebar + Dropdown Fix
document.addEventListener('DOMContentLoaded', function () {
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }

    // Mobile dropdowns
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                e.preventDefault();
                const menu = this.nextElementSibling;
                document.querySelectorAll('.dropdown-menu.show').forEach(m => m !== menu && m.classList.remove('show'));
                menu.classList.toggle('show');
            }
        });
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });
});
</script>

<!-- Responsive Layout -->
<style>
.main-content {
    transition: margin-left 0.3s ease-in-out;
}
@media (min-width: 992px) {
    .main-content { margin-left: var(--sidebar-width, 220px); }
}
@media (max-width: 991px) {
    .main-content { margin-left: 0 !important; }
}

.chart-container {
    position: relative;
    height: 340px;
    width: 100%;
}
@media (max-width: 576px) {
    .chart-container { height: 240px; }
}

.dropdown-menu { z-index: 1055 !important; }
.sidebar-backdrop { z-index: 1035; display: none; }
.sidebar-backdrop.show { display: block; }

.card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important; }
</style>