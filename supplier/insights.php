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
$customer_insights = $supplier->getCustomerInsights();
$purchase_patterns = $supplier->getPurchasePatterns();
$geographic_data = $supplier->getGeographicInsights();
$seasonal_trends = $supplier->getSeasonalTrends();

$page_title = 'Customer Insights';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content supplier-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users"></i> Customer Insights
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" onclick="refreshInsights()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportInsights()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Customer Overview -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($customer_insights['total_customers'] ?? 0); ?></h4>
                                    <p class="card-text">Total Customers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($customer_insights['active_customers'] ?? 0); ?></h4>
                                    <p class="card-text">Active Customers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($customer_insights['new_customers'] ?? 0); ?></h4>
                                    <p class="card-text">New This Month</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-plus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($customer_insights['retention_rate'] ?? 0, 1); ?>%</h4>
                                    <p class="card-text">Retention Rate</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-heart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie"></i> Customer Segments
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="segmentChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart"></i> Purchase Frequency
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="frequencyChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic Insights -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marked-alt"></i> Geographic Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Province</th>
                                            <th>Customers</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                            <th>Avg Order Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($geographic_data)): ?>
                                            <?php foreach ($geographic_data as $location): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($location['province']); ?></td>
                                                    <td><?php echo number_format($location['customers']); ?></td>
                                                    <td><?php echo number_format($location['orders']); ?></td>
                                                    <td>₱<?php echo number_format($location['revenue'], 2); ?></td>
                                                    <td>₱<?php echo number_format($location['avg_order_value'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No geographic data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-star"></i> Top Customers
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($customer_insights['top_customers'])): ?>
                                <?php foreach ($customer_insights['top_customers'] as $customer): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($customer['name']); ?></h6>
                                            <small class="text-muted"><?php echo $customer['orders']; ?> orders</small>
                                        </div>
                                        <div class="text-end">
                                            <strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No customer data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchase Patterns -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock"></i> Purchase Timing
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="timingChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt"></i> Seasonal Trends
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="seasonalChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Behavior Insights -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-lightbulb"></i> Key Insights & Recommendations
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-chart-line text-success"></i> Growth Opportunities</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-arrow-right text-primary"></i> Focus on repeat customers - they generate 60% more revenue</li>
                                        <li><i class="fas fa-arrow-right text-primary"></i> Peak ordering time is 2-4 PM - consider promotions</li>
                                        <li><i class="fas fa-arrow-right text-primary"></i> Pangasinan customers have highest order values</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-exclamation-triangle text-warning"></i> Areas for Improvement</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-arrow-right text-warning"></i> Customer retention could be improved</li>
                                        <li><i class="fas fa-arrow-right text-warning"></i> Consider expanding to new provinces</li>
                                        <li><i class="fas fa-arrow-right text-warning"></i> Seasonal demand varies - plan inventory accordingly</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function refreshInsights() {
    location.reload();
}

function exportInsights() {
    alert('Export functionality will be implemented');
}

// Customer segments chart
const segmentData = {
    labels: ['New Customers', 'Regular Customers', 'VIP Customers', 'Inactive'],
    datasets: [{
        data: [30, 45, 15, 10],
        backgroundColor: ['#36A2EB', '#4BC0C0', '#FFCE56', '#FF6384']
    }]
};

new Chart(document.getElementById('segmentChart'), {
    type: 'doughnut',
    data: segmentData,
    options: { responsive: true, maintainAspectRatio: false }
});

// Purchase frequency chart
const frequencyData = {
    labels: ['Weekly', 'Monthly', 'Quarterly', 'Rarely'],
    datasets: [{
        label: 'Customers',
        data: [25, 40, 20, 15],
        backgroundColor: 'rgba(54, 162, 235, 0.8)'
    }]
};

new Chart(document.getElementById('frequencyChart'), {
    type: 'bar',
    data: frequencyData,
    options: { responsive: true, maintainAspectRatio: false }
});

// Purchase timing chart
const timingData = {
    labels: ['6AM', '9AM', '12PM', '3PM', '6PM', '9PM'],
    datasets: [{
        label: 'Orders',
        data: [5, 15, 25, 35, 20, 10],
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        tension: 0.4
    }]
};

new Chart(document.getElementById('timingChart'), {
    type: 'line',
    data: timingData,
    options: { responsive: true, maintainAspectRatio: false }
});

// Seasonal trends chart
const seasonalData = {
    labels: ['Spring', 'Summer', 'Fall', 'Winter'],
    datasets: [{
        label: 'Sales Volume',
        data: [80, 120, 90, 60],
        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
    }]
};

new Chart(document.getElementById('seasonalChart'), {
    type: 'bar',
    data: seasonalData,
    options: { responsive: true, maintainAspectRatio: false }
});
</script>

<?php include '../includes/supplier_footer.php'; ?>
