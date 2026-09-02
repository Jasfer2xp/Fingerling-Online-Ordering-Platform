<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$supplier_id = intval($_GET['id'] ?? 0);

if (!$supplier_id) {
    redirect(base_url('admin/suppliers.php'));
}

// Get supplier details
$supplier = $admin->getSupplierById($supplier_id);
if (!$supplier) {
    $_SESSION['error'] = 'Supplier not found.';
    redirect(base_url('admin/suppliers.php'));
}

// Get supplier analytics data
try {
    // Sales analytics for the last 12 months
    $sql = "SELECT 
                DATE_FORMAT(o.created_at, '%Y-%m') as month,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value
            FROM orders o
            WHERE o.supplier_id = ? 
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND o.status = 'delivered'
            GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
            ORDER BY month DESC";
    $monthly_sales = $database->fetchAll($sql, [$supplier_id]);

    // Product performance
    $sql = "SELECT 
                sp.name as species_name,
                i.size_category,
                COUNT(oi.id) as orders_count,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as total_revenue
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.id
            JOIN species sp ON i.species_id = sp.id
            JOIN orders o ON oi.order_id = o.id
            WHERE i.supplier_id = ? 
            AND o.status = 'delivered'
            GROUP BY i.species_id, i.size_category
            ORDER BY total_revenue DESC
            LIMIT 10";
    $product_performance = $database->fetchAll($sql, [$supplier_id]);

    // Customer analytics
    $sql = "SELECT 
                COUNT(DISTINCT o.customer_id) as unique_customers,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value
            FROM orders o
            WHERE o.supplier_id = ? 
            AND o.status = 'delivered'";
    $customer_stats = $database->fetch($sql, [$supplier_id]);

    // Recent orders
    $sql = "SELECT o.*, c.first_name, c.last_name, c.contact_number
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE o.supplier_id = ?
            ORDER BY o.created_at DESC
            LIMIT 10";
    $recent_orders = $database->fetchAll($sql, [$supplier_id]);

} catch (Exception $e) {
    $error = 'Error loading analytics: ' . $e->getMessage();
}

$page_title = 'Supplier Analytics';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                Supplier Analytics
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <select id="timePeriodSelect" class="modern-form-control modern-form-control-sm" onchange="loadAnalyticsData()">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="90">Last 90 Days</option>
                <option value="365">Last Year</option>
            </select>
        </div>
    </div>

    <div class="container-fluid px-4">
        <!-- Supplier Info -->
        <div class="modern-card role-card admin-card mb-4">
            <div class="modern-card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="text-primary mb-2">
                            <i class="fas fa-store me-2"></i>
                            <?php echo htmlspecialchars($supplier['business_name']); ?>
                        </h4>
                        <p class="mb-0 text-secondary">
                            Owner: <?php echo htmlspecialchars($supplier['owner_name']); ?> | 
                            Location: <?php echo htmlspecialchars($supplier['city'] . ', ' . $supplier['province']); ?>
                        </p>
                    </div>
                    <div class="ms-3">
                        <div class="text-center">
                            <h5 class="text-success mb-1"><?php echo number_format($supplier['rating'], 1); ?></h5>
                            <small class="text-muted">Rating</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Stats -->
        <div class="modern-stats-grid mb-4">
            <div class="modern-stat-card role-stat-card admin-stat-card primary">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Total Orders</h6>
                        <p class="modern-stat-card-value"><?php echo number_format($customer_stats['total_orders'] ?? 0); ?></p>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>

            <div class="modern-stat-card role-stat-card admin-stat-card success">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Total Revenue</h6>
                        <p class="modern-stat-card-value"><?php echo format_currency($customer_stats['total_revenue'] ?? 0); ?></p>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                </div>
            </div>

            <div class="modern-stat-card role-stat-card admin-stat-card warning">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Unique Customers</h6>
                        <p class="modern-stat-card-value"><?php echo number_format($customer_stats['unique_customers'] ?? 0); ?></p>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="modern-stat-card role-stat-card admin-stat-card info">
                <div class="modern-stat-card-header">
                    <div class="modern-stat-card-info">
                        <h6 class="modern-stat-card-title">Avg Order Value</h6>
                        <p class="modern-stat-card-value"><?php echo format_currency($customer_stats['avg_order_value'] ?? 0); ?></p>
                    </div>
                    <div class="modern-stat-card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="modern-content-grid mb-4">
            <!-- Sales Chart -->
            <div class="modern-chart-card">
                <div class="modern-chart-header">
                    <h5 class="modern-chart-title">
                        <i class="fas fa-chart-line text-primary"></i>
                        Monthly Sales Trend
                    </h5>
                </div>
                <div class="modern-chart-body">
                    <div class="modern-chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Product Performance -->
            <div class="modern-content-card">
                <div class="modern-content-card-header">
                    <h5 class="modern-content-card-title">
                        <i class="fas fa-fish"></i>
                        Top Products
                    </h5>
                </div>
                <div class="modern-content-card-body">
                    <?php if (empty($product_performance)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No sales data available</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Product</th>
                                        <th>Size</th>
                                        <th>Orders</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($product_performance as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['species_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['size_category']); ?></td>
                                            <td><?php echo number_format($product['orders_count']); ?></td>
                                            <td><?php echo number_format($product['total_sold']); ?></td>
                                            <td><?php echo format_currency($product['total_revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="modern-table-card">
            <div class="modern-table-header">
                <h5 class="modern-table-title">
                    <i class="fas fa-list"></i> Recent Orders
                </h5>
            </div>
            <div class="modern-table-body">
                <?php if (empty($recent_orders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No orders yet</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['contact_number']); ?></small>
                                        </td>
                                        <td><?php echo format_currency($order['total_amount']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $order['status']; ?>">
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
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modern JavaScript -->
<script src="../assets/js/modern-sidebar.js"></script>
<!-- Removed dark-mode.js as we're not using dark mode functionality -->
<script src="../assets/js/modern-charts.js"></script>

<script>
// Sales analytics chart
const salesData = <?php echo json_encode($monthly_sales); ?>;

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if ModernChartManager is available
    if (typeof window.ModernChartManager !== 'undefined') {
        const modernChartManager = new ModernChartManager();
        
        // Sales Chart
        if (salesData.length > 0) {
            modernChartManager.createLineChart('salesChart', {
                labels: salesData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Orders',
                    data: salesData.map(item => item.total_orders),
                    borderColor: 'rgb(37, 99, 235)',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Revenue (₱)',
                    data: salesData.map(item => item.total_revenue),
                    borderColor: 'rgb(5, 150, 105)',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            }, {
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Orders'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            });
        }
    } else {
        console.warn('ModernChartManager not available. Charts will not be displayed.');
    }
});
</script>

