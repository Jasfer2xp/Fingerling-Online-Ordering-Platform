<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get supplier performance data
$suppliers = $admin->getSupplierPerformance();

$page_title = 'Supplier Performance';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid px-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-5 mt-3">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Supplier Performance</h1>
                <p class="text-muted mb-0">Real-time insights into supplier efficiency and business impact</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-2 shadow-sm" onclick="exportPerformance()">
                <i class="fas fa-download"></i>
                <span class="d-none d-sm-inline">Export Report</span>
            </button>
        </div>

        <!-- Performance Overview -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value text-primary"><?php echo number_format(count($suppliers)); ?></div>
                            <div class="stat-label">Active Suppliers</div>
                        </div>
                        <div class="stat-icon bg-primary-subtle">
                            <i class="fas fa-store"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value text-success"><?php echo number_format(array_sum(array_column($suppliers, 'total_orders'))); ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                        <div class="stat-icon bg-success-subtle">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value text-info"><?php echo format_currency(array_sum(array_column($suppliers, 'total_revenue'))); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        <div class="stat-icon bg-info-subtle">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value text-warning d-flex align-items-center gap-1">
                                <i class="fas fa-star"></i>
                                <?php 
                                $rated = array_filter($suppliers, fn($s) => $s['total_ratings'] > 0);
                                echo $rated ? number_format(array_sum(array_column($rated, 'rating')) / count($rated), 1) : '0.0';
                                ?>
                            </div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                        <div class="stat-icon bg-warning-subtle">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="row g-4 mb-5">
            <!-- Top by Revenue -->
            <div class="col-lg-4">
                <div class="leaderboard-card">
                    <div class="leaderboard-header bg-success text-white">
                        <i class="fas fa-trophy"></i> Top by Revenue
                    </div>
                    <div class="leaderboard-body">
                        <?php 
                        $top_revenue = array_slice(array_reverse(array_sort($suppliers, 'total_revenue')), 0, 3);
                        foreach ($top_revenue as $index => $supplier): 
                        ?>
                            <div class="leaderboard-item">
                                <div class="rank-badge"><?php echo $index + 1; ?></div>
                                <div class="flex-grow-1">
                                    <div class="leader-name"><?php echo htmlspecialchars($supplier['business_name']); ?></div>
                                    <div class="leader-metric text-success"><?php echo format_currency($supplier['total_revenue']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Top by Orders -->
            <div class="col-lg-4">
                <div class="leaderboard-card">
                    <div class="leaderboard-header bg-primary text-white">
                        <i class="fas fa-shopping-cart"></i> Top by Orders
                    </div>
                    <div class="leaderboard-body">
                        <?php 
                        $top_orders = array_slice(array_reverse(array_sort($suppliers, 'total_orders')), 0, 3);
                        foreach ($top_orders as $index => $supplier): 
                        ?>
                            <div class="leaderboard-item">
                                <div class="rank-badge"><?php echo $index + 1; ?></div>
                                <div class="flex-grow-1">
                                    <div class="leader-name"><?php echo htmlspecialchars($supplier['business_name']); ?></div>
                                    <div class="leader-metric text-primary"><?php echo number_format($supplier['total_orders']); ?> orders</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Top by Rating -->
            <div class="col-lg-4">
                <div class="leaderboard-card">
                    <div class="leaderboard-header bg-warning text-white">
                        <i class="fas fa-star"></i> Top by Rating
                    </div>
                    <div class="leaderboard-body">
                        <?php 
                        $top_rated = array_slice(array_reverse(array_sort($suppliers, 'rating')), 0, 3);
                        foreach ($top_rated as $index => $supplier): 
                        ?>
                            <div class="leaderboard-item">
                                <div class="rank-badge"><?php echo $index + 1; ?></div>
                                <div class="flex-grow-1">
                                    <div class="leader-name"><?php echo htmlspecialchars($supplier['business_name']); ?></div>
                                    <div class="leader-metric d-flex align-items-center gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-muted'; ?> small"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted small">(<?php echo number_format($supplier['rating'], 1); ?>)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Table -->
        <?php if (empty($suppliers)): ?>
            <div class="empty-state-card">
                <div class="text-center py-5">
                    <i class="fas fa-store-slash fa-4x text-muted mb-4"></i>
                    <h4 class="text-primary mb-3">No Performance Data Available</h4>
                    <p class="text-secondary mb-4">Performance metrics will appear as suppliers begin processing orders.</p>
                    <a href="suppliers.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> View All Suppliers
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="data-table-card">
                <div class="data-table-header">
                    <h5 class="mb-0 fw-semibold">Detailed Performance Overview</h5>
                </div>
                <div class="table-responsive">
                    <table class="table performance-table">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Location</th>
                                <th>Products</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Rating</th>
                                <th>Performance</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): 
                                $performance_score = $supplier['total_orders'] > 0 
                                    ? min(100, ($supplier['rating'] * 20) + ($supplier['total_orders'] / 10)) 
                                    : 0;
                                $performance_class = $performance_score >= 80 ? 'bg-success' : ($performance_score >= 60 ? 'bg-warning' : 'bg-danger');
                                $performance_text = $performance_score >= 80 ? 'text-white' : ($performance_score >= 60 ? 'text-dark' : 'text-white');
                            ?>
                            <tr class="table-row-hover">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="supplier-avatar">
                                            <?php echo strtoupper(substr($supplier['business_name'], 0, 1)); ?>
                                        </div>
                                        <div class="ms-3">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($supplier['business_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($supplier['owner_name'] ?? '—'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(($supplier['barangay'] ?? '') . ($supplier['barangay'] && $supplier['city'] ? ', ' : '') . ($supplier['city'] ?? '—')); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="metric-badge bg-info-subtle text-info"><?php echo number_format($supplier['total_products'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <span class="metric-badge bg-primary-subtle text-primary"><?php echo number_format($supplier['total_orders']); ?></span>
                                </td>
                                <td class="fw-semibold text-success"><?php echo format_currency($supplier['total_revenue']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-muted'; ?> small"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?php echo $supplier['total_ratings']; ?>)</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="performance-bar">
                                        <div class="<?php echo $performance_class; ?> <?php echo $performance_text; ?>"
                                             style="width: <?php echo $performance_score; ?>%">
                                            <?php echo number_format($performance_score, 0); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary action-btn" 
                                            title="View Details" 
                                            onclick="viewSupplier(<?php echo $supplier['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
function viewSupplier(supplierId) {
    window.location.href = `supplier-details.php?id=${supplierId}`;
}

function exportPerformance() {
    window.location.href = 'export-supplier-performance.php?format=excel';
}
</script>

<style>
/* === PROFESSIONAL DESIGN SYSTEM === */

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
}
.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.bg-primary-subtle { background-color: rgba(13, 110, 253, 0.1); }
.bg-success-subtle { background-color: rgba(25, 135, 84, 0.1); }
.bg-info-subtle { background-color: rgba(13, 202, 240, 0.1); }
.bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1); }

/* Leaderboard Cards */
.leaderboard-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}
.leaderboard-card:hover {
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
}
.leaderboard-header {
    padding: 1rem 1.5rem;
    font-weight: 600;
    font-size: 1rem;
}
.leaderboard-body {
    padding: 1.25rem;
}
.leaderboard-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f5;
}
.leaderboard-item:last-child {
    border-bottom: none;
}
.rank-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffc107;
    color: #212529;
    font-weight: 700;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}
.leader-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.leader-metric {
    font-size: 0.875rem;
    font-weight: 500;
}

/* Data Table */
.data-table-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.data-table-header {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.performance-table {
    margin-bottom: 0;
}
.performance-table th {
    font-weight: 600;
    color: #495057;
    font-size: 0.875rem;
    text-transform: none;
    letter-spacing: 0.5px;
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.performance-table td {
    padding: 1rem 1.5rem;
    vertical-align: middle;
    font-size: 0.925rem;
    border-bottom: 1px solid #f1f3f5;
}
.table-row-hover:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease;
}

/* Avatar */
.supplier-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #0d6efd;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Metric Badges */
.metric-badge {
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
}

/* Performance Bar */
.performance-bar {
    height: 32px;
    background: #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}
.performance-bar > div {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    transition: width 0.4s ease;
}

/* Action Button */
.action-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.action-btn:hover {
    background-color: #0d6efd;
    color: white !important;
    transform: scale(1.05);
}

/* Empty State */
.empty-state-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    text-align: center;
}

/* Responsive */
@media (max-width: 992px) {
    .stat-value { font-size: 1.75rem; }
    .stat-icon { width: 48px; height: 48px; font-size: 1.25rem; }
}
@media (max-width: 768px) {
    .stat-card { padding: 1.25rem; }
    .leaderboard-header { font-size: 0.95rem; }
    .performance-table th,
    .performance-table td { padding: 0.75rem 1rem; }
}
</style>

</body>
</html>

<?php 
// Helper function for PHP array sorting (kept exactly as-is)
function array_sort($array, $key) {
    usort($array, function($a, $b) use ($key) {
        return $a[$key] <=> $b[$key];
    });
    return $array;
}
?>