<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!defined('SECRET_KEY')) {
    $envSecret = env('SECRET_KEY', '');
    define('SECRET_KEY', $envSecret ?: hash('sha256', APP_NAME . '_secret'));
}

if (!function_exists('encodeId')) {
    function encodeId($id)
    {
        $id = (string) (int) $id;
        try {
            $prefix = bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $prefix = bin2hex(substr(hash('sha256', microtime(true) . mt_rand(), true), 0, 4));
        }

        return $prefix . '-' . hash_hmac('sha256', $id, SECRET_KEY);
    }
}

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$customers = $admin->getCustomers($filters);

// Stats
$total = count($customers);
$active = count(array_filter($customers, fn($c) => $c['status'] === 'active'));
$with_orders = count(array_filter($customers, fn($c) => !empty($c['total_orders'])));
$new_30 = count(array_filter($customers, fn($c) => strtotime($c['created_at']) > strtotime('-30 days')));

$page_title = 'Customers';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- ====================== RESPONSIVE STYLES ====================== -->
<style>
    .admin-main-content { padding: 1rem; }
    .card { border-radius: 12px; overflow: hidden; }
    .table th { font-weight: 600; color: #374151; font-size: 0.875rem; }
    .table td { vertical-align: middle; font-size: 0.92rem; }

    /* Stat Cards */
    .modern-stat-card {
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: 16px;
        transition: all 0.3s ease;
        padding: 1.25rem;
        background: #fff;
    }
    .modern-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 600;
        font-size: 1.1rem;
    }

    /* Avatar */
    .avatar-sm {
        width: 40px;
        height: 40px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Mobile: Hide desktop table, show cards */
    @media (max-width: 991.98px) {
        .desktop-table { display: none !important; }
        .mobile-card { display: block !important; }
    }
    .mobile-card { display: none; }

    /* No horizontal scroll */
    body { overflow-x: hidden; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    /* Filter button on mobile */
    @media (max-width: 576px) {
        .filter-btn-mobile { width: 100%; }
    }
</style>

<main class="admin-main-content">
    <div class="container-fluid px-0 px-md-4">

        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 mt-3 gap-2">
            <h1 class="h3 fw-bold text-dark mb-0">Customer Management</h1>
            <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2 filter-btn-mobile" data-bs-toggle="modal" data-bs-target="#filtersModal">
                Filters
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Customers</h6>
                            <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($total); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            Users
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Active</h6>
                            <h3 class="mb-0 fw-bold text-success"><?php echo number_format($active); ?></h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            Active
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">With Orders</h6>
                            <h3 class="mb-0 fw-bold text-info"><?php echo number_format($with_orders); ?></h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            Orders
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="modern-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">New (30 days)</h6>
                            <h3 class="mb-0 fw-bold text-warning"><?php echo number_format($new_30); ?></h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            New
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + Filter -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-12 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white">Search</span>
                            <input type="search" name="search" class="form-control border-start-0" 
                                   placeholder="Name, email, contact..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
                <div class="mt-2 text-muted small">
                    Showing <?php echo count($customers); ?> customer<?php echo count($customers) !== 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>

        <!-- Customers Table / Mobile Cards -->
        <?php if (empty($customers)): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <h4 class="text-primary mb-3">No Customers Found</h4>
                    <p class="text-secondary mb-4">
                        <?php echo $filters['search'] || $filters['status'] 
                            ? 'Try adjusting your filters or search term.' 
                            : 'Customers will appear here as they register.'; ?>
                    </p>
                    <a href="customers.php" class="btn btn-primary">Clear Filters</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-none d-lg-block">
                    <h5 class="mb-0 fw-semibold text-dark">Customers List</h5>
                </div>
                <div class="card-body p-0">

                    <!-- Desktop Table -->
                    <div class="table-responsive desktop-table">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Customer</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Orders</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle me-3">
                                                <?php echo strtoupper(substr($c['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">
                                                    <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                                </div>
                                                <small class="text-muted">ID: #<?php echo $c['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo $c['email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($c['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['contact_number'] ?? '—'); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($c['address'] ?? '—'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info rounded-pill px-3">
                                            <?php echo $c['total_orders'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-3 <?php echo $c['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($c['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="order-details.php?id=<?php echo $c['id']; ?>" 
                                               class="btn btn-outline-info btn-sm" title="View Orders">
                                                Orders
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-card">
                        <?php foreach ($customers as $c): ?>
                        <div class="border-bottom p-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="avatar-sm bg-primary text-white rounded-circle me-3">
                                    <?php echo strtoupper(substr($c['first_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">
                                        <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                    </div>
                                    <small class="text-muted">ID: #<?php echo $c['id']; ?></small>
                                </div>
                                <span class="badge rounded-pill px-2 <?php echo $c['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </div>

                            <div class="small text-muted mb-2">
                                <div><strong>Email:</strong> <a href="mailto:<?php echo $c['email']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($c['email']); ?></a></div>
                                <div><strong>Contact:</strong> <?php echo htmlspecialchars($c['contact_number'] ?? '—'); ?></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($c['address'] ?? '—'); ?></div>
                                <div><strong>Orders:</strong> <span class="badge bg-info"><?php echo $c['total_orders'] ?? 0; ?></span></div>
                                <div><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($c['created_at'])); ?></div>
                            </div>

                            <div class="d-flex gap-1">
                                <a href="order-details.php?id=<?php echo $c['id']; ?>" 
                                   class="btn btn-outline-info btn-sm flex-fill">Orders</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Filters Modal -->
<div class="modal fade" id="filtersModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Advanced Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Name, email, contact..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                <div class="modal-footer flex-column gap-2">
                    <a href="customers.php" class="btn btn-secondary w-100">Clear Filters</a>
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>