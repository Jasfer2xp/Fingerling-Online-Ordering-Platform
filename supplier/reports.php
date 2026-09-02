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

// Get report period
$period = $_GET['period'] ?? 'month';
$valid_periods = ['week', 'month', 'year'];
if (!in_array($period, $valid_periods)) {
    $period = 'month';
}

// Get report data
try {
    $sales_stats = $supplier->getSalesStats($period);
    $sales_trends = $supplier->getSalesTrends($period === 'week' ? 7 : ($period === 'month' ? 30 : 365));
    $top_products = $supplier->getTopProducts(10);
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to load report data: ' . $e->getMessage();
    $sales_stats = ['total_orders' => 0, 'completed_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'unique_customers' => 0];
    $sales_trends = [];
    $top_products = [];
}

// Handle CSV export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    try {
        $csv = "Date,Orders,Revenue\n";
        foreach ($sales_trends as $item) {
            $csv .= sprintf("%s,%d,%.2f\n", $item['date'], $item['orders'], $item['revenue']);
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=sales_report_' . date('Y-m-d') . '.csv');
        echo $csv;
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to export report: ' . $e->getMessage();
        redirect(base_url('supplier/reports.php?period=' . $period));
    }
}

$page_title = 'Sales Reports';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="reports-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold"><i class="fas fa-chart-bar me-2"></i>Sales Reports</h3>
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

        <!-- Period Selection -->
        <section class="period-selection mb-5">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3">
                    <div class="btn-group" role="group">
                        <a href="?period=week" class="btn btn-sm <?php echo $period === 'week' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-bs-toggle="tooltip" title="View sales for the past week">This Week</a>
                        <a href="?period=month" class="btn btn-sm <?php echo $period === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-bs-toggle="tooltip" title="View sales for the past month">This Month</a>
                        <a href="?period=year" class="btn btn-sm <?php echo $period === 'year' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-bs-toggle="tooltip" title="View sales for the past year">This Year</a>
                    </div>
                </div>
                <div class="col-md-6 text-md-end mb-3">
                    <span class="text-muted">Report Period: <strong><?php echo ucfirst($period); ?></strong></span>
                </div>
            </div>
        </section>

        <!-- Summary Statistics -->
        <section class="summary-stats mb-5">
            <div class="row g-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Total Orders</div>
                                    <h5 class="mb-0"><?php echo number_format($sales_stats['total_orders'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-shopping-cart fa-2x text-primary ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Completed Orders</div>
                                    <h5 class="mb-0"><?php echo number_format($sales_stats['completed_orders'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-success ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Total Revenue</div>
                                    <h5 class="mb-0"><?php echo format_currency($sales_stats['total_revenue'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-peso-sign fa-2x text-warning ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted text-uppercase small mb-1">Avg Order Value</div>
                                    <h5 class="mb-0"><?php echo format_currency($sales_stats['avg_order_value'] ?? 0); ?></h5>
                                </div>
                                <i class="fas fa-chart-line fa-2x text-info ms-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sales Trends Chart -->
        <section class="sales-trends mb-5">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Sales Trends</h5>
                    <div class="chart-container" style="position: relative; width: 100%; max-height: 400px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                    <?php if (empty($sales_trends)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No sales data available for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Top Products and Customer Insights -->
        <section class="products-customers mb-5">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="fas fa-trophy me-2"></i>Top Selling Products</h5>
                            <?php if (empty($top_products)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No sales data available for this period.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-borderless align-middle mb-0">
                                        <thead>
                                            <tr class="table-light">
                                                <th>Product</th>
                                                <th>Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($product['species_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo ucfirst($product['size_category']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary" data-bs-toggle="tooltip" title="<?php echo number_format($product['total_quantity_sold'] ?? 0); ?> units sold"><?php echo number_format($product['total_quantity_sold'] ?? 0); ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo format_currency($product['total_revenue'] ?? 0); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="fas fa-users me-2"></i>Customer Insights</h5>
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <h4 class="text-primary"><?php echo number_format($sales_stats['unique_customers']); ?></h4>
                                    <p class="text-muted mb-0">Unique Customers</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success">
                                        <?php 
                                        $completion_rate = $sales_stats['total_orders'] > 0 ? 
                                            ($sales_stats['completed_orders'] / $sales_stats['total_orders']) * 100 : 0;
                                        echo number_format($completion_rate, 1); 
                                        ?>%
                                    </h4>
                                    <p class="text-muted mb-0">Completion Rate</p>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <h6>Performance Summary</h6>
                                <p class="text-muted small mb-0">
                                    You've completed <strong><?php echo number_format($sales_stats['completed_orders']); ?></strong> 
                                    out of <strong><?php echo number_format($sales_stats['total_orders']); ?></strong> orders this <?php echo $period; ?>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Business Insights -->
        <section class="business-insights">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2"></i>Business Insights</h5>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h6>Revenue Growth</h6>
                            <p class="text-muted">
                                <?php if ($sales_stats['total_revenue'] > 0): ?>
                                    Your revenue this <?php echo $period; ?> is <strong><?php echo format_currency($sales_stats['total_revenue']); ?></strong>.
                                    Keep up the great work!
                                <?php else: ?>
                                    No revenue recorded for this period. Consider promoting your products or adjusting prices.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>Order Performance</h6>
                            <p class="text-muted">
                                <?php if ($completion_rate >= 90): ?>
                                    Excellent! You have a <?php echo number_format($completion_rate, 1); ?>% completion rate.
                                <?php elseif ($completion_rate >= 70): ?>
                                    Good completion rate at <?php echo number_format($completion_rate, 1); ?>%. Room for improvement.
                                <?php else: ?>
                                    Consider improving your order fulfillment process. Current rate: <?php echo number_format($completion_rate, 1); ?>%.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>Customer Base</h6>
                            <p class="text-muted">
                                You've served <strong><?php echo number_format($sales_stats['unique_customers']); ?></strong> 
                                unique customers this <?php echo $period; ?>. 
                                <?php if ($sales_stats['unique_customers'] > 0): ?>
                                    Focus on customer retention for sustainable growth.
                                <?php else: ?>
                                    Work on attracting your first customers through competitive pricing and quality service.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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
    .badge {
        padding: 0.5em 0.75em;
    }
    .chart-container {
        position: relative;
        width: 100%;
        max-height: 400px;
        overflow: hidden;
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
        .chart-container {
            max-height: 200px;
        }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales trends chart
    const salesData = <?php echo json_encode($sales_trends); ?>;
    const ctx = document.getElementById('salesChart').getContext('2d');

    if (salesData.length > 0) {
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Orders',
                    data: salesData.map(item => item.orders),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (₱)',
                    data: salesData.map(item => item.revenue),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date',
                            font: { size: 12 }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Orders',
                            font: { size: 12 }
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₱)',
                            font: { size: 12 }
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label === 'Revenue (₱)') {
                                    return `${label}: ₱${context.parsed.y.toFixed(2)}`;
                                }
                                return `${label}: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });
    } else {
        ctx.canvas.style.display = 'none';
    }

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