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

// Date Range Logic
$default_end = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-30 days'));
$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
if ($page < 1) $page = 1;

// Fetch Data
$health_stats = $supplier->getStockHealthStats();
$low_stock_items = $supplier->getInventoryAlerts(10);

// Get totals for pagination
$total_movements = $supplier->getStockMovementCount($start_date, $end_date);
$total_pages = ceil($total_movements / $per_page);

// Fetch Paginated Data for Table
$stock_movements = $supplier->getStockMovementLog($start_date, $end_date, null, $page, $per_page);

// Fetch Full Data for Charts (Unpaginated)
$all_movements = $supplier->getStockMovementLog($start_date, $end_date);

// Calculate movement totals from full data
$total_sold_period = 0;
$total_restored_period = 0;
$movement_chart_data = [];

foreach ($all_movements as $move) {
    // Only count reductions/restorations once (the log contains unioned distinct events)
    // Actually, getStockMovementLog returns individual events, so just summing them is correct.
    
    $date = date('Y-m-d', strtotime($move['date']));
    
    if ($move['movement_type'] === 'reduction') {
        $total_sold_period += abs($move['quantity_change']);
        
        if (!isset($movement_chart_data[$date])) {
            $movement_chart_data[$date] = ['sold' => 0, 'restored' => 0];
        }
        $movement_chart_data[$date]['sold'] += abs($move['quantity_change']);
    } else {
        $total_restored_period += $move['quantity_change'];
        
        if (!isset($movement_chart_data[$date])) {
            $movement_chart_data[$date] = ['sold' => 0, 'restored' => 0];
        }
        $movement_chart_data[$date]['restored'] += $move['quantity_change'];
    }
}

// Sort chart data by date
ksort($movement_chart_data);

$page_title = 'Inventory Intelligence';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>

<style>
    .kpi-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
        height: 100%;
        color: white;
        overflow: hidden;
        position: relative;
    }
    .kpi-card:hover { transform: translateY(-3px); }
    .kpi-card .card-body { position: relative; z-index: 1; }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: rgba(255, 255, 255, 0.1);
        clip-path: circle(50% at 90% 10%);
    }
    .bg-gradient-primary { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .bg-gradient-warning { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
    .bg-gradient-success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .bg-gradient-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

    .chart-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        height: 100%;
    }
    
    .table-responsive {
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .status-dot {
        height: 10px; width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    
    .timeline-icon {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="main-content">
    <div class="container-fluid p-4">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4 gap-3">
            <div>
                <h3 class="fw-bold mb-1"><i class="fas fa-warehouse me-2"></i>Inventory Intelligence</h3>
                <p class="text-muted mb-0">Track stock health, movements, and alerts</p>
            </div>
            
            <div class="d-flex gap-2">
                <form class="d-flex gap-2" method="GET">
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" required>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i></button>
                    <?php if (isset($_GET['page'])): ?>
                        <input type="hidden" name="page" value="1"> <!-- Reset to page 1 on filter -->
                    <?php endif; ?>
                </form>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</a></li>
                        <li><a class="dropdown-item" href="#" onclick="alert('Export functionality coming soon')"><i class="fas fa-file-csv me-2"></i>CSV Export</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- KPI Row -->
        <div class="row g-4 mb-4">
            <!-- Total Stock Value -->
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card bg-gradient-info">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Total Inventory Value</h6>
                        <h3 class="fw-bold mb-0"><?php echo format_currency($health_stats['total_value']); ?></h3>
                        <div class="mt-3 small opacity-75">
                            <i class="fas fa-box me-1"></i> <?php echo number_format($health_stats['total_items']); ?> Products Active
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Alerts -->
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card bg-gradient-warning">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Low Stock Alerts</h6>
                        <h3 class="fw-bold mb-0"><?php echo number_format($health_stats['low_stock']); ?></h3>
                        <div class="mt-3 small opacity-75">
                            <i class="fas fa-exclamation-triangle me-1"></i> Requires Attention
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sold Quantity -->
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card bg-gradient-primary">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Sold (Selected Period)</h6>
                        <h3 class="fw-bold mb-0"><?php echo number_format($total_sold_period); ?></h3>
                        <div class="mt-3 small opacity-75">
                            <i class="fas fa-minus-circle me-1"></i> Stock Deductions
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Restored Quantity -->
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card bg-gradient-success">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase mb-2 opacity-75">Restored (Selected Period)</h6>
                        <h3 class="fw-bold mb-0"><?php echo number_format($total_restored_period); ?></h3>
                        <div class="mt-3 small opacity-75">
                            <i class="fas fa-undo me-1"></i> From Cancellations
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card bg-white p-4">
                    <h5 class="fw-bold mb-4">Stock Movement Trends</h5>
                    <div style="height: 300px;">
                        <canvas id="movementChart"></canvas>
                    </div>
                    <?php if (empty($movement_chart_data)): ?>
                        <div class="text-center text-muted mt-3">No activity recorded for this period.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card bg-white p-4">
                    <h5 class="fw-bold mb-4">Stock Health Distribution</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="healthChart"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <h2 class="mb-0 fw-bold"><?php echo $health_stats['total_items']; ?></h2>
                            <small class="text-muted">Products</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Stock Activity Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Recent Stock Movements</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border">
                                Total: <?php echo number_format($total_movements); ?>
                            </span>
                            <span class="badge bg-primary">
                                Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Date & Time</th>
                                        <th>Order Ref</th>
                                        <th>Type</th>
                                        <th>Species / Product</th>
                                        <th>Size</th>
                                        <th class="text-end">Qty Change</th>
                                        <th class="text-end pe-4">Current Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($stock_movements)): ?>
                                        <tr><td colspan="8" class="text-center py-5 text-muted">No stock movements found in this period.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($stock_movements as $move): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold"><?php echo date('M j, Y', strtotime($move['date'])); ?></div>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($move['date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        #<?php echo $move['order_number']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($move['movement_type'] === 'reduction'): ?>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger">Sold</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success">Restored</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="timeline-icon bg-<?php echo $move['movement_type'] === 'reduction' ? 'danger' : 'success'; ?> bg-opacity-10 me-3 text-<?php echo $move['movement_type'] === 'reduction' ? 'danger' : 'success'; ?>">
                                                            <i class="fas fa-<?php echo $move['movement_type'] === 'reduction' ? 'shopping-cart' : 'undo'; ?> fa-sm"></i>
                                                        </div>
                                                        <?php echo htmlspecialchars($move['species_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($move['size_category']); ?></td>
                                                <td class="text-end fw-bold <?php echo $move['quantity_change'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo $move['quantity_change'] > 0 ? '+' : ''; ?><?php echo $move['quantity_change']; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php echo number_format($move['current_stock']); ?>
                                                </td>
                                                <td>
                                                    <a href="orders.php?search=<?php echo $move['order_number']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                            <div class="small text-muted">
                                Showing <?php echo count($stock_movements); ?> of <?php echo $total_movements; ?> entries
                            </div>
                            <nav aria-label="Stock movement pagination">
                                <ul class="pagination mb-0">
                                    <!-- Previous -->
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" tabindex="-1">Previous</a>
                                    </li>
                                    
                                    <!-- Page Numbers (Simple logic: Show all if < 7, else show window around current) -->
                                    <?php
                                    $range = 2;
                                    for ($i = 1; $i <= $total_pages; $i++) {
                                        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                            $active = $i == $page ? 'active' : '';
                                            echo "<li class='page-item $active'><a class='page-link' href='?page=$i&start_date=$start_date&end_date=$end_date'>$i</a></li>";
                                        } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                                            echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                                        }
                                    }
                                    ?>
                                    
                                    <!-- Next -->
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Alert Section -->
        <?php if (!empty($low_stock_items)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x me-4"></i>
                    <div>
                        <h5 class="alert-heading fw-bold">Low Stock Warning!</h5>
                        <p class="mb-0">You have <strong><?php echo count($low_stock_items); ?></strong> items running low on stock. Please restock soon to avoid missing sales opportunities.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Data Preparation
const moveDates = <?php echo json_encode(array_keys($movement_chart_data)); ?>;
const soldData = <?php echo json_encode(array_column($movement_chart_data, 'sold')); ?>;
const restoredData = <?php echo json_encode(array_column($movement_chart_data, 'restored')); ?>;

// Movement Trends Chart
new Chart(document.getElementById('movementChart'), {
    type: 'bar',
    data: {
        labels: moveDates.length ? moveDates : ['No Data'],
        datasets: [
            {
                label: 'Sold (Reductions)',
                data: soldData,
                backgroundColor: '#ef4444',
                borderRadius: 4
            },
            {
                label: 'Restored (Cancellations)',
                data: restoredData,
                backgroundColor: '#10b981',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            x: { stacked: false, grid: { display: false } },
            y: { beginAtZero: true, grid: { borderDash: [2, 4] } }
        }
    }
});

// Stock Health Chart
new Chart(document.getElementById('healthChart'), {
    type: 'doughnut',
    data: {
        labels: ['Adequate Stock', 'Low Stock (<10)', 'Out of Stock'],
        datasets: [{
            data: [
                <?php echo $health_stats['adequate_stock']; ?>,
                <?php echo $health_stats['low_stock']; ?>,
                <?php echo $health_stats['out_of_stock']; ?>
            ],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
