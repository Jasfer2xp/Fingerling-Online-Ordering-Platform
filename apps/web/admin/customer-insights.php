<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get customer insights data
$customer_insights = $admin->getCustomerInsights();
$top_customers = $admin->getTopCustomers();
$customer_demographics = $admin->getCustomerDemographics();

// Get registration trend data
$registration_trend = $admin->getCustomerRegistrationTrend();

$page_title = 'Customer Insights';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid px-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h1 class="h3 fw-bold text-dark mb-0">Customer Insights</h1>
            <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" onclick="exportInsights()">
                <i class="fas fa-download"></i>
                <span class="d-none d-sm-inline">Export Report</span>
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Customers</h6>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($customer_insights['total_customers'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Active Customers</h6>
                            <h3 class="mb-0 fw-bold text-success"><?php echo number_format($customer_insights['active_customers'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                            <i class="fas fa-user-check fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">New This Month</h6>
                            <h3 class="mb-0 fw-bold text-info"><?php echo number_format($customer_insights['new_customers_month'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                            <i class="fas fa-user-plus fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Avg Order Value</h6>
                            <h3 class="mb-0 fw-bold text-warning"><?php echo format_currency($customer_insights['avg_order_value'] ?? 0); ?></h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                            <i class="fas fa-money-bill-wave fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="modern-stat-card bg-white">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-0 fw-semibold text-dark">Customer Registration Trend</h5>
                    </div>
                    <div class="card-body p-0">
                        <canvas id="registrationChart" class="p-3" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="modern-stat-card bg-white h-100">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-0 fw-semibold text-dark">Customer Status</h5>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="statusChart" width="250" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Customers + Behavior -->
        <div class="row g-4 mb-4">
            <!-- Top Customers -->
            <div class="col-lg-7">
                <div class="modern-stat-card bg-white">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pb-3">
                        <h5 class="mb-0 fw-semibold text-dark">Top Customers by Orders</h5>
                        <small class="text-muted">Last 30 days</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Customer</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary rounded-pill px-3">
                                                <?php echo number_format($customer['total_orders']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold text-success">
                                            <?php echo format_currency($customer['total_spent']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_customers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No customer data available</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Behavior Metrics -->
            <div class="col-lg-5">
                <div class="modern-stat-card bg-white h-100">
                    <div class="card-header bg-white border-0 pb-3">
                        <h5 class="mb-0 fw-semibold text-dark">Customer Behavior</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4 text-center">
                            <div class="col-6">
                                <div class="p-3">
                                    <h4 class="mb-1 text-primary fw-bold">
                                        <?php echo number_format($customer_insights['avg_orders_per_customer'] ?? 0, 1); ?>
                                    </h4>
                                    <p class="text-muted small mb-0">Avg Orders/Customer</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3">
                                    <h4 class="mb-1 text-success fw-bold">
                                        <?php echo number_format($customer_insights['repeat_customer_rate'] ?? 0, 1); ?>%
                                    </h4>
                                    <p class="text-muted small mb-0">Repeat Rate</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3">
                                    <h4 class="mb-1 text-info fw-bold">
                                        <?php echo number_format($customer_insights['avg_days_between_orders'] ?? 0); ?>
                                    </h4>
                                    <p class="text-muted small mb-0">Days Between Orders</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3">
                                    <h4 class="mb-1 text-warning fw-bold">
                                        <?php echo format_currency($customer_insights['customer_lifetime_value'] ?? 0); ?>
                                    </h4>
                                    <p class="text-muted small mb-0">Lifetime Value</p>
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
// Registration Trend Line Chart
const registrationCtx = document.getElementById('registrationChart').getContext('2d');
new Chart(registrationCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($registration_trend['labels']); ?>,
        datasets: [{
            label: 'New Customers',
            data: <?php echo json_encode($registration_trend['data']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                backgroundColor: 'rgba(0,0,0,0.8)',
                cornerRadius: 8
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// Status Doughnut Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Inactive', 'New This Month'],
        datasets: [{
            data: [
                <?php echo $customer_insights['active_customers'] ?? 0; ?>,
                <?php echo ($customer_insights['total_customers'] ?? 0) - ($customer_insights['active_customers'] ?? 0); ?>,
                <?php echo $customer_insights['new_customers_month'] ?? 0; ?>
            ],
            backgroundColor: [
                '#10b981', // emerald
                '#ef4444', // red
                '#f59e0b'  // amber
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 20, font: { size: 13 } }
            },
            tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', cornerRadius: 8 }
        },
        cutout: '68%'
    }
});

function exportInsights() {
    window.location.href = 'export-customer-insights.php';
}
</script>

<style>
/* Custom Card Style */
.modern-stat-card {
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
}
.modern-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}

/* Avatar */
.avatar-sm {
    width: 40px;
    height: 40px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Stat Icon */
.stat-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Table */
.table th {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}
.table td {
    vertical-align: middle;
    font-size: 0.92rem;
}

/* Responsive */
@media (max-width: 768px) {
    .modern-stat-card .stat-icon {
        width: 48px;
        height: 48px;
    }
    .avatar-sm {
        width: 36px;
        height: 36px;
    }
}
</style>

</body>
</html>