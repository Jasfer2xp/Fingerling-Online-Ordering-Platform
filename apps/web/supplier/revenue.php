<?php
// Quick debug: Enable errors temporarily
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Safely define status functions only if missing
if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        $status = strtolower($status ?? '');
        return match ($status) {
            'completed', 'paid', 'success' => 'success',
            'pending', 'processing'       => 'warning',
            'cancelled', 'failed', 'rejected' => 'danger',
            'shipped', 'delivered'        => 'info',
            default                       => 'secondary',
        };
    }
}

if (!function_exists('getStatusTooltip')) {
    function getStatusTooltip($status) {
        $status = strtolower($status ?? '');
        return match ($status) {
            'completed', 'paid' => 'Order has been fully processed and paid',
            'pending'           => 'Order is awaiting confirmation',
            'cancelled'         => 'Order was cancelled',
            'processing'        => 'Order is being processed',
            'shipped'           => 'Order has been shipped',
            'delivered'         => 'Order delivered to customer',
            default             => 'Unknown status',
        };
    }
}

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

try {
    $database = new Database();
    $user_id = get_user_id();
    $user = new User($database);
    $profile = $user->getUserProfile($user_id);

    if ($profile['status'] !== 'approved') {
        redirect(base_url('supplier/dashboard.php'));
    }

    $supplier_id = $profile['id'];
    $supplier = new Supplier($database, $supplier_id);

    // Filter Logic
    $period = $_GET['period'] ?? 'month';
    $custom_start = $_GET['start_date'] ?? '';
    $custom_end = $_GET['end_date'] ?? '';
    
    // Default dates based on period
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime('-30 days'));

    if ($period === 'custom' && $custom_start && $custom_end) {
        $start_date = $custom_start;
        $end_date = $custom_end;
    } else {
        // Preset periods
        switch ($period) {
            case 'week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'quarter':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            case 'month':
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
        }
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 10;
    
    // Fetch Data
    // 1. Transaction List & Summary (using custom dates)
    $revenue_data = $supplier->getRevenueTracking('custom', $page, $per_page, $start_date, $end_date);
    
    // 2. Species Earnings (using custom dates)
    $species_earnings = [];
    if (method_exists($supplier, 'getSpeciesEarnings')) {
        $species_earnings = $supplier->getSpeciesEarnings($start_date, $end_date);
    }

    // 3. Earning Trends (Filtered)
    $earning_trends = [];
    if (method_exists($supplier, 'getMonthlyEarningTrends')) {
        $earning_trends = $supplier->getMonthlyEarningTrends($period, $start_date, $end_date);
    }

    $total_transactions = $revenue_data['total_transactions'] ?? 0;
    $total_pages = ceil($total_transactions / $per_page);

} catch (Exception $e) {
    error_log("Revenue page error: " . $e->getMessage());
    $revenue_data = ['transactions' => [], 'total_transactions' => 0, 'summary' => ['total_revenue' => 0, 'total_earnings' => 0, 'pending_revenue' => 0], 'growth_rate' => 0];
    $species_earnings = [];
    $earning_trends = [];
    $total_transactions = 0;
    $total_pages = 1;
}

$page_title = 'Total Earnings';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        
        <!-- Header & Date Filter -->
        <section class="revenue-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h3 class="fw-bold"><i class="fas fa-coins me-2"></i>Total Earnings</h3>
                
                <form id="filterForm" class="d-flex align-items-center gap-2 bg-white p-2 rounded shadow-sm">
                    <select class="form-select form-select-sm" name="period" id="periodSelect" onchange="toggleCustomDates()">
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                    
                    <div id="customDateInputs" class="<?php echo $period === 'custom' ? '' : 'd-none'; ?> d-flex gap-2 text-nowrap align-items-center">
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                        <span class="text-muted">-</span>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="exportRevenue()">
                        <i class="fas fa-download"></i>
                    </button>
                </form>
            </div>
        </section>

        <!-- Summary Cards (Top Level) -->
        <section class="revenue-summary mb-5">
            <div class="row g-4">
                <!-- Total Earnings (Net) -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white h-100 position-relative overflow-hidden">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <p class="text-muted fw-medium mb-1">Total Earnings</p>
                                    <h3 class="fw-bold text-success mb-0">₱<?php echo number_format($revenue_data['summary']['total_earnings'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="icon-circle bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-info-circle me-1"></i> Net after service fees
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Revenue -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <p class="text-muted fw-medium mb-1">Pending Clearance</p>
                                    <h3 class="fw-bold text-warning mb-0">₱<?php echo number_format($revenue_data['summary']['pending_revenue'] ?? 0, 2); ?></h3>
                                </div>
                                <div class="icon-circle bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                            <div class="small text-muted">Awaiting delivery or confirmation</div>
                        </div>
                    </div>
                </div>

                <!-- Growth Rate etc -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <p class="text-muted fw-medium mb-1">Growth (vs prev period)</p>
                                    <h3 class="fw-bold text-primary mb-0"><?php echo number_format($revenue_data['growth_rate'] ?? 0, 1); ?>%</h3>
                                </div>
                                <div class="icon-circle bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                            <div class="small text-muted">Trend indicator</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4 mb-5">
            <!-- Left Column: Species Performance -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-fish me-2"></i>Earnings by Species</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Species</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-end">Earnings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($species_earnings)): ?>
                                        <?php foreach ($species_earnings as $species): ?>
                                            <tr>
                                                <td class="fw-medium"><?php echo htmlspecialchars($species['species_name']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary rounded-pill"><?php echo $species['order_count']; ?></span>
                                                </td>
                                                <td class="text-end fw-bold text-success">
                                                    ₱<?php echo number_format($species['net_earnings'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No data for this period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Earning Trends Chart -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Earning Trends (Net)</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 350px;">
                            <canvas id="earningChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Transaction Table -->
        <section class="revenue-breakdown">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>Transaction Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-3">Date</th>
                                    <th class="py-3">Order</th>
                                    <th class="py-3">Customer</th>
                                    <th class="py-3">Amount</th>
                                    <th class="py-3">Service Fee</th>
                                    <th class="py-3">Earnings</th>
                                    <th class="py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($revenue_data['transactions'])): ?>
                                    <?php foreach ($revenue_data['transactions'] as $transaction): ?>
                                        <tr>
                                            <td class="ps-3 text-muted small">
                                                <?php echo format_date($transaction['created_at'], 'M d, Y'); ?><br>
                                                <?php echo format_date($transaction['created_at'], 'h:i A'); ?>
                                            </td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $transaction['id']; ?>" class="fw-bold text-decoration-none">
                                                    #<?php echo $transaction['order_number']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                                            <td>₱<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                            <td class="text-danger small">-₱<?php echo number_format($transaction['service_fee'] ?? 0, 2); ?></td>
                                            <td class="fw-bold text-success">₱<?php echo number_format($transaction['earnings'] ?? 0, 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($transaction['status']); ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">No transactions found for this period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleCustomDates() {
    const period = document.getElementById('periodSelect').value;
    const inputs = document.getElementById('customDateInputs');
    if (period === 'custom') {
        inputs.classList.remove('d-none');
    } else {
        inputs.classList.add('d-none');
    }
}

function exportRevenue() {
    const period = document.getElementById('periodSelect').value;
    // Append custom dates if applicable
    let url = `export_revenue.php?period=${period}`;
    if (period === 'custom') {
        const start = document.querySelector('input[name="start_date"]').value;
        const end = document.querySelector('input[name="end_date"]').value;
        url += `&start_date=${start}&end_date=${end}`;
    }
    window.location.href = url;
}

// Chart Initialization
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('earningChart').getContext('2d');
    const trendsData = <?php echo json_encode($earning_trends); ?>;
    
    if (trendsData.length > 0) {
        new Chart(ctx, {
            type: 'bar', // Changed to Bar for better "Earnings" visualization per month
            data: {
                labels: trendsData.map(d => d.period_label),
                datasets: [{
                    label: 'Net Earnings (₱)',
                    data: trendsData.map(d => d.earnings),
                    backgroundColor: 'rgba(25, 135, 84, 0.7)', // Success Green
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Earnings: ₱' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 2] },
                        ticks: {
                            callback: function(value) { return '₱' + value / 1000 + 'k'; }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    } else {
        document.getElementById('earningChart').innerHTML = 'No Data Available';
    }
});
</script>
</body>
</html>