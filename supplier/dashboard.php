<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

// Check if this is a user who just completed registration
$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) {
    unset($_SESSION['registration_just_completed']);
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Check if supplier has completed registration (unless they just completed it)
if (!$just_completed_registration && (empty($profile['business_name']) || empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_business_name'] = $profile['business_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=supplier&step=3'));
}

// Check if supplier is approved
if ($profile['status'] !== 'approved') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Pending Approval</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f5f5;display:flex;align-items:center;justify-content:center;height:100vh;}.card{max-width:400px;}</style></head><body><div class="card text-center p-4"><i class="fas fa-clock fa-3x text-shopee mb-3" style="color:#ee4d2d;"></i><h3>Account Pending Approval</h3><p class="text-muted">Your account is under review. We\'ll notify you soon.</p><a href="' . base_url('auth/logout.php') . '" class="btn btn-shopee">Logout</a></div></body></html>';
    exit;
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Date Filtering Logic
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
            $period = 'month'; // Reset to valid default
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
    }
}

// Pass dates to getDashboardStats
$stats = $supplier->getDashboardStats($start_date, $end_date);
$recent_orders = $supplier->getRecentOrders(5);

$page_title = 'Supplier Dashboard';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        
        <!-- Header & Filter -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold mb-0">Seller Dashboard Overview</h3>
            
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
            </form>
        </div>

        <section class="dashboard-stats mb-5">
            <div class="row g-4">
                <div class="col-6 col-md-3">
                    <div class="card stats-card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-peso-sign stats-icon text-primary"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold">₱<?php echo number_format($stats['revenue'] ?? 0, 2); ?></h5>
                                <p class="card-text text-muted mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stats-card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-shopping-cart stats-icon text-info"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold"><?php echo number_format($stats['orders'] ?? 0); ?></h5>
                                <p class="card-text text-muted mb-0">Pending Orders</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stats-card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-box stats-icon text-success"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold"><?php echo number_format($stats['products'] ?? 0); ?></h5>
                                <p class="card-text text-muted mb-0">Active Products</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stats-card border-0 shadow-sm h-100 bg-white">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-users stats-icon text-warning"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold"><?php echo number_format($stats['customers'] ?? 0); ?></h5>
                                <p class="card-text text-muted mb-0">Customers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-5">

        <section class="quick-actions mb-5">
            <div class="section-header d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold"><i class="fas fa-bolt text-primary me-2"></i> Quick Actions</h4>
                <a href="inventory-add.php" class="see-all text-primary text-decoration-none fw-bold small">Add New Product <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3 g-lg-4">
                <?php
                $actions = [
                    ['icon' => 'fas fa-boxes', 'label' => 'Inventory', 'link' => 'inventory.php', 'color' => 'primary'],
                    ['icon' => 'fas fa-plus', 'label' => 'Add Product', 'link' => 'inventory-add.php', 'color' => 'success'],
                    ['icon' => 'fas fa-shopping-cart', 'label' => 'Orders', 'link' => 'orders.php', 'color' => 'info'],
                    ['icon' => 'fas fa-truck', 'label' => 'Deliveries', 'link' => 'deliveries.php', 'color' => 'secondary'],
                    ['icon' => 'fas fa-users', 'label' => 'Customers', 'link' => 'customers.php', 'color' => 'warning'],
                    ['icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'link' => 'reports.php', 'color' => 'danger'],
                ];
                foreach ($actions as $action):
                ?>
                    <div class="col text-center">
                        <a href="<?php echo $action['link']; ?>" class="text-decoration-none">
                            <div class="category-card p-4 rounded-4 shadow-sm bg-white border border-<?php echo $action['color']; ?>">
                                <i class="<?php echo $action['icon']; ?> category-icon mb-2 text-<?php echo $action['color']; ?>" style="font-size: 1.8rem;"></i>
                                <p class="mb-0 small text-dark fw-bold"><?php echo $action['label']; ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <hr class="my-5">

        <section class="recent-orders mb-5">
            <div class="section-header d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold"><i class="fas fa-history text-info me-2"></i> Recent Orders</h4>
                <a href="orders.php" class="see-all text-info text-decoration-none fw-bold small">View All Orders <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-0">
                    <?php if (empty($recent_orders)): ?>
                        <div class="p-5 text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">You have no recent orders. Start listing your products!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="table-light">
                                        <th class="py-3">Order ID</th>
                                        <th class="py-3">Customer</th>
                                        <th class="py-3">Date</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3">Amount</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td class="text-muted small">#<?php echo htmlspecialchars($order['id']); ?></td>
                                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo match($order['status']) {
                                                    'pending' => 'warning',
                                                    'confirmed' => 'info',
                                                    'preparing' => 'primary',
                                                    'shipping' => 'secondary',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                }; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle Sidebar
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
    });

    function toggleCustomDates() {
        const period = document.getElementById('periodSelect').value;
        const inputs = document.getElementById('customDateInputs');
        if (period === 'custom') {
            inputs.classList.remove('d-none');
        } else {
            inputs.classList.add('d-none');
        }
    }
</script>
</body>
</html>