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

// Handle date filtering
$period = $_GET['period'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Default to current month if not specified
// Default to current month if not specified
switch ($period) {
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'all':
        $start_date = null;
        $end_date = null;
        break;
    case 'custom':
        // Keep existing GET params, fallback to current month if missing
        if (!$start_date || !$end_date) {
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
        }
        break;
    default:
        // Fallback for invalid period
        $period = 'all'; 
        $start_date = null;
        $end_date = null;
        break;
}

// Get dashboard data with filters
$stats = $admin->getPlatformStats($start_date, $end_date);
$pending_suppliers = $admin->getPendingSuppliers();
$recent_orders = $admin->getAllOrders([], 10);

// Get Chart Data (Daily/Monthly trends)
// Pass filtered dates to analytics so chart matches the stats
$sales_analytics = $admin->getSalesAnalytics($period === 'all' ? 'year' : $period, $start_date, $end_date); 

$page_title = 'Admin Dashboard';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
        <!-- Dashboard Header -->
        <div class="modern-dashboard-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="modern-dashboard-title mb-0">
                    <div class="modern-dashboard-title-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    Admin Dashboard
                </h1>
                <p class="text-muted small mt-1 mb-0">Overview of platform performance</p>
            </div>
            
            <!-- Date Filter -->
            <div class="d-flex gap-2">
                <form id="dateFilterForm" class="d-flex align-items-center bg-white p-2 rounded shadow-sm border" method="GET">
                    <select name="period" class="form-select form-select-sm me-2 border-0 bg-light" style="width: auto;" onchange="toggleCustomDates()">
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This Year</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Lifetime</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom</option>
                    </select>
                    
                    <div id="customDates" class="<?= $period !== 'custom' ? 'd-none' : '' ?> d-flex align-items-center">
                        <input type="date" name="start_date" class="form-control form-control-sm me-1" value="<?= htmlspecialchars($start_date ?? '') ?>">
                        <span class="mx-1 text-muted">-</span>
                        <input type="date" name="end_date" class="form-control form-control-sm me-2" value="<?= htmlspecialchars($end_date ?? '') ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;">
                        <i class="fas fa-filter"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="modern-stats-grid mb-4">
            <!-- Platform Revenue -->
            <div class="modern-stat-card role-stat-card admin-stat-card info">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Total Revenue</h6>
                        <p class="modern-stat-card-value"><?php echo format_currency($stats['total_revenue'] ?? 0); ?></p>
                        <small class="text-muted d-block mt-1">Gross sales from delivered orders</small>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <!-- Service Fees Collected -->
            <div class="modern-stat-card role-stat-card admin-stat-card primary">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Service Fees Collected</h6>
                        <p class="modern-stat-card-value"><?php echo format_currency($stats['total_service_fees'] ?? 0); ?></p>
                        <small class="text-primary d-block mt-1"><strong>5%</strong> of delivered sales</small>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>

            <!-- Total Suppliers -->
             <div class="modern-stat-card role-stat-card admin-stat-card success">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Active Suppliers</h6>
                        <p class="modern-stat-card-value"><?php echo number_format($stats['total_suppliers']); ?></p>
                        <small class="text-muted d-block mt-1"><?php echo number_format($stats['pending_suppliers']); ?> Pending Approval</small>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-store"></i>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
             <div class="modern-stat-card role-stat-card admin-stat-card warning">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Completed Orders</h6>
                        <p class="modern-stat-card-value"><?php echo number_format($stats['completed_orders']); ?></p>
                        <small class="text-muted d-block mt-1">Out of <?php echo number_format($stats['total_orders']); ?> Total</small>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Charts Section -->
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="card-title fw-bold mb-0"><i class="fas fa-chart-area me-2"></i>Revenue Trends</h5>
                    </div>
                    <div class="card-body">
                         <div style="position: relative; height: 300px; width: 100%;">
                             <canvas id="salesChart"></canvas>
                         </div>
                    </div>
                </div>
            </div>
            
             <!-- Quick Actions / Status -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                         <h5 class="card-title fw-bold mb-0"><i class="fas fa-poll me-2"></i>Order Status</h5>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div style="position: relative; height: 200px; width: 100%;">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                        <div class="mt-4 text-center">
                            <span class="badge bg-success me-2">Completed</span>
                            <span class="badge bg-warning me-2">Pending</span>
                            <span class="badge bg-danger">Cancelled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="modern-table-card mb-4">
            <div class="modern-table-header">
                <h5 class="modern-table-title">
                    <i class="fas fa-list"></i> Recent Orders
                </h5>
                <div class="modern-table-filters">
                    <a href="orders.php" class="modern-btn modern-btn-outline-primary modern-btn-sm">View All</a>
                </div>
            </div>
            <div class="modern-table-body">
                <?php if (empty($recent_orders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No orders yet</h5>
                        <p class="text-muted">Orders will appear here as customers start placing them.</p>
                    </div>
                <?php else: ?>
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Supplier</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['contact_number']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['business_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($order['barangay'] . ', ' . $order['city']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo format_currency($order['total_amount']); ?></td>
                                    <td>
                                        <span style="color: black;" class="badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($order['created_at']); ?></td>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>"
                                           class="modern-btn modern-btn-outline-primary modern-btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modern JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/modern-sidebar.js"></script>
    <script src="../assets/js/modern-charts.js"></script>
    <script>
    function toggleCustomDates() {
        const period = document.querySelector('select[name="period"]').value;
        const customDates = document.getElementById('customDates');
        if (period === 'custom') {
            customDates.classList.remove('d-none');
        } else {
            customDates.classList.add('d-none');
        }
    }
    
    // Sales analytics chart
    const salesData = <?php echo !empty($sales_analytics) ? json_encode($sales_analytics) : '[]'; ?>;

    // Initialize charts when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart !== 'undefined') {
            
            // Sales Chart (Line)
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: salesData.map(item => item.revenue),
                        borderColor: '#2563eb', // Blue
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y'
                    }, {
                        label: 'Service Fees (₱)', // Estimated from revenue for trends
                        data: salesData.map(item => item.revenue * 0.05),
                        borderColor: '#059669', // Green
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y' // Same axis scale
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                         y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                         x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                     plugins: {
                        legend: {
                            position: 'top',
                            align: 'end'
                        }
                    }
                }
            });

            // Order Status Chart (Doughnut)
            const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $stats['completed_orders']; ?>,
                            <?php echo $stats['total_orders'] - $stats['completed_orders']; ?>, // Simplified pending calculation
                            0 // Placeholder for cancelled if not tracked specifically in stats yet
                        ],
                        backgroundColor: [
                            '#10b981', // Success
                            '#f59e0b', // Warning
                            '#ef4444'  // Danger
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                     plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    });
    </script>
