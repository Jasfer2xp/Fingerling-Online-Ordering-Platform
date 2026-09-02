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

// Get date range from query params or use defaults
$default_end = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-30 days'));
$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;

// Validate dates
$start_dt = DateTime::createFromFormat('Y-m-d', $start_date) ?: new DateTime($default_start);
$end_dt = DateTime::createFromFormat('Y-m-d', $end_date) ?: new DateTime($default_end);

// Ensure start is before end
if ($start_dt > $end_dt) {
    [$start_dt, $end_dt] = [$end_dt, $start_dt];
}

// Limit to 2 years max range
$max_range = new DateInterval('P2Y');
$min_allowed = (clone $end_dt)->sub($max_range);
if ($start_dt < $min_allowed) {
    $start_dt = $min_allowed;
}

$start_date = $start_dt->format('Y-m-d');
$end_date = $end_dt->format('Y-m-d');

// Calculate previous period for comparison
$days_diff = $start_dt->diff($end_dt)->days + 1;
$previous_end = (clone $start_dt)->modify('-1 day');
$previous_start = (clone $previous_end)->modify("-{$days_diff} days");

// Get analytics data
$species_breakdown = $supplier->getSpeciesSalesBreakdown($start_date, $end_date);
$historical_inventory = $supplier->getHistoricalInventory($start_date, $end_date);
$earnings = $supplier->getEarningsBreakdown($start_date, $end_date);
$comparison = $supplier->getSalesComparison(
    $start_date, $end_date,
    $previous_start->format('Y-m-d'), $previous_end->format('Y-m-d')
);

// Calculate best selling species
$best_species = 'N/A';
if (!empty($species_breakdown)) {
    $best_species = $species_breakdown[0]['species_name'] ?? 'N/A';
}

$page_title = 'Sales Report';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<style>
    .modern-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .modern-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -1px rgba(0, 0, 0, 0.15);
    }
    
    .chart-container {
        position: relative;
        height: 320px;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .chart-container {
            height: 250px;
        }
    }
    
    .date-filter-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .quick-date-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .quick-date-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }
    
    .table-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .change-positive {
        color: #10b981;
    }
    
    .change-negative {
        color: #ef4444;
    }
</style>

<div class="main-content">
    <div class="container-fluid p-4">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">
                <i class="fas fa-chart-line me-2"></i>Sales Report
            </h3>
            <a href="export-sales-report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="btn btn-success">
                <i class="fas fa-download me-2"></i>Export CSV
            </a>
        </div>

        <!-- Date Range Filter -->
        <div class="date-filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                           class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                           class="form-control" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-light w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
                <div class="col-md-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="quick-date-btn" onclick="setQuickDate(7)">7 Days</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate(30)">30 Days</button>
                    <button type="button" class="quick-date-btn" onclick="setQuickDate(90)">90 Days</button>
                </div>
            </form>
            <div class="mt-3 text-center">
                <small class="opacity-75">
                    Viewing: <strong><?php echo date('M j, Y', strtotime($start_date)); ?></strong> to 
                    <strong><?php echo date('M j, Y', strtotime($end_date)); ?></strong>
                    (<?php echo $days_diff; ?> days)
                </small>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-xl-3">
                <div class="modern-stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Revenue</h6>
                            <h4 class="mb-0 fw-bold"><?php echo format_currency($earnings['summary']['total_revenue'] ?? 0); ?></h4>
                        </div>
                        <i class="fas fa-peso-sign fa-2x text-primary opacity-75"></i>
                    </div>
                    <?php if (abs($comparison['changes']['revenue']) > 0): ?>
                        <small class="<?php echo $comparison['changes']['revenue'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                            <i class="fas fa-<?php echo $comparison['changes']['revenue'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs(number_format($comparison['changes']['revenue'], 1)); ?>% vs previous period
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Orders</h6>
                            <h4 class="mb-0 fw-bold"><?php echo number_format($earnings['summary']['total_orders'] ?? 0); ?></h4>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x text-success opacity-75"></i>
                    </div>
                    <?php if (abs($comparison['changes']['orders']) > 0): ?>
                        <small class="<?php echo $comparison['changes']['orders'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                            <i class="fas fa-<?php echo $comparison['changes']['orders'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs(number_format($comparison['changes']['orders'], 1)); ?>% vs previous period
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Avg Order Value</h6>
                            <h4 class="mb-0 fw-bold"><?php echo format_currency($earnings['summary']['avg_order_value'] ?? 0); ?></h4>
                        </div>
                        <i class="fas fa-chart-line fa-2x text-info opacity-75"></i>
                    </div>
                    <?php if (abs($comparison['changes']['avg_order_value']) > 0): ?>
                        <small class="<?php echo $comparison['changes']['avg_order_value'] >= 0 ? 'change-positive' : 'change-negative'; ?>">
                            <i class="fas fa-<?php echo $comparison['changes']['avg_order_value'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs(number_format($comparison['changes']['avg_order_value'], 1)); ?>% vs previous period
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Best Selling</h6>
                            <h4 class="mb-0 fw-bold text-truncate" style="font-size: 1.1rem;" title="<?php echo htmlspecialchars($best_species); ?>">
                                <?php echo htmlspecialchars($best_species); ?>
                            </h4>
                        </div>
                        <i class="fas fa-fish fa-2x text-warning opacity-75"></i>
                    </div>
                    <small class="text-muted">
                        <?php echo $earnings['summary']['species_sold'] ?? 0; ?> species sold
                    </small>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-chart-area text-primary me-2"></i>Revenue & Orders Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-chart-pie text-info me-2"></i>Species Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 280px;">
                            <canvas id="speciesDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Top 10 Species by Revenue</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topSpeciesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt text-success me-2"></i>Monthly Revenue Comparison</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyComparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="row g-4">
            <!-- Species Sales Breakdown -->
            <div class="col-12">
                <div class="table-card">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0"><i class="fas fa-list-ul text-primary me-2"></i>Species Sales Breakdown</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">Species</th>
                                        <th>Size Category</th>
                                        <th>Qty Sold</th>
                                        <th>Revenue</th>
                                        <th>Avg Price</th>
                                        <th>Orders</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($species_breakdown)): ?>
                                        <?php foreach ($species_breakdown as $item): ?>
                                            <tr>
                                                <td class="px-4">
                                                    <strong><?php echo htmlspecialchars($item['species_name']); ?></strong>
                                                    <?php if ($item['scientific_name']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['scientific_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['size_category'] ?? 'N/A'); ?></span></td>
                                                <td><?php echo number_format($item['total_quantity_sold']); ?></td>
                                                <td class="fw-bold text-success"><?php echo format_currency($item['total_revenue']); ?></td>
                                                <td><?php echo format_currency($item['average_price']); ?></td>
                                                <td><?php echo number_format($item['order_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-active fw-bold">
                                            <td class="px-4" colspan="2">TOTAL</td>
                                            <td><?php echo number_format(array_sum(array_column($species_breakdown, 'total_quantity_sold'))); ?></td>
                                            <td class="text-success"><?php echo format_currency(array_sum(array_column($species_breakdown, 'total_revenue'))); ?></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                No sales data for this period
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historical Inventory -->
            <div class="col-12">
                <div class="table-card">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0"><i class="fas fa-history text-info me-2"></i>Historical Inventory</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">Species</th>
                                        <th>Size</th>
                                        <th>Date Added</th>
                                        <th>Current Stock</th>
                                        <th>Current Price</th>
                                        <th>Sold in Period</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($historical_inventory)): ?>
                                        <?php foreach ($historical_inventory as $item): ?>
                                            <tr>
                                                <td class="px-4">
                                                    <strong><?php echo htmlspecialchars($item['species_name']); ?></strong>
                                                    <?php if ($item['scientific_name']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['scientific_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['size_category'] ?? 'N/A'); ?></span></td>
                                                <td><?php echo date('M j, Y', strtotime($item['date_added'])); ?></td>
                                                <td><?php echo number_format($item['current_stock']); ?></td>
                                                <td><?php echo format_currency($item['current_price']); ?></td>
                                                <td><?php echo number_format($item['total_sold_in_period']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_color = $item['availability_status'] === 'available' ? 'success' : 
                                                                   ($item['availability_status'] === 'out_of_stock' ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $item['availability_status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                No inventory items found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Earnings -->
            <div class="col-12">
                <div class="table-card">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0"><i class="fas fa-calendar text-success me-2"></i>Monthly Earnings Breakdown</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">Month</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($earnings['monthly_revenue'])): ?>
                                        <?php foreach ($earnings['monthly_revenue'] as $month): ?>
                                            <tr>
                                                <td class="px-4"><strong><?php echo $month['month_name']; ?></strong></td>
                                                <td><?php echo number_format($month['orders']); ?></td>
                                                <td class="fw-bold text-success"><?php echo format_currency($month['revenue']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                No monthly data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Quick date selector
function setQuickDate(days) {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - days);
    
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    window.location.href = `?start_date=${formatDate(start)}&end_date=${formatDate(end)}`;
}

// Prepare chart data
const monthlyData = <?php echo json_encode($earnings['monthly_revenue']); ?>;
const speciesData = <?php echo json_encode(array_slice($earnings['species_revenue'], 0, 10)); ?>;
const topSpecies = <?php echo json_encode(array_slice($species_breakdown, 0, 10)); ?>;

// Revenue Trend Chart
new Chart(document.getElementById('revenueTrendChart'), {
    type: 'line',
    data: {
        labels: monthlyData.map(d => d.month_name),
        datasets: [{
            label: 'Revenue (₱)',
            data: monthlyData.map(d => d.revenue),
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y',
            pointRadius: 5,
            pointHoverRadius: 7
        }, {
            label: 'Orders',
            data: monthlyData.map(d => d.orders),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1',
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: context => {
                        let label = context.dataset.label || '';
                        if (context.datasetIndex === 0) {
                            label += ': ₱' + context.parsed.y.toLocaleString();
                        } else {
                            label += ': ' + context.parsed.y;
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                position: 'left',
                title: { display: true, text: 'Revenue (₱)' },
                ticks: { callback: value => '₱' + value.toLocaleString() }
            },
            y1: {
                type: 'linear',
                position: 'right',
                title: { display: true, text: 'Orders' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

// Species Distribution Chart
const speciesColors = [
    '#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', 
    '#43e97b', '#fa709a', '#fee140', '#30cfd0', '#a8edea'
];

new Chart(document.getElementById('speciesDistributionChart'), {
    type: 'doughnut',
    data: {
        labels: speciesData.map(d => d.species_name),
        datasets: [{
            data: speciesData.map(d => d.revenue),
            backgroundColor: speciesColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: context => {
                        const label = context.label || '';
                        const value = '₱' + context.parsed.toLocaleString();
                        const percentage = speciesData[context.dataIndex].percentage;
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Top Species Bar Chart
new Chart(document.getElementById('topSpeciesChart'), {
    type: 'bar',
    data: {
        labels: topSpecies.map(d => {
            const name = d.species_name;
            return name.length > 15 ? name.substring(0, 15) + '...' : name;
        }),
        datasets: [{
            label: 'Revenue (₱)',
            data: topSpecies.map(d => d.total_revenue),
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: '#667eea',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: context => '₱' + context.parsed.x.toLocaleString()
                }
            }
        },
        scales: {
            x: {
                ticks: { callback: value => '₱' + value.toLocaleString() }
            }
        }
    }
});

// Monthly Comparison Chart
const currentPeriod = monthlyData;
new Chart(document.getElementById('monthlyComparisonChart'), {
    type: 'bar',
    data: {
        labels: currentPeriod.map(d => d.month_name),
        datasets: [{
            label: 'Revenue (₱)',
            data: currentPeriod.map(d => d.revenue),
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
            borderColor: '#10b981',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: context => '₱' + context.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: value => '₱' + value.toLocaleString() }
            }
        }
    }
});
</script>

