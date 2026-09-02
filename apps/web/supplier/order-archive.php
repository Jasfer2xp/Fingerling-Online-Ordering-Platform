<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

require_once '../classes/Supplier.php';
$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get archived orders
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.contact_number
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.supplier_id = ? AND o.status = 'archived'
        ORDER BY o.archived_at DESC
        LIMIT ? OFFSET ?";
$archived_orders = $database->fetchAll($sql, [$supplier_id, $limit, $offset]);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM orders WHERE supplier_id = ? AND status = 'archived'";
$total_result = $database->fetch($count_sql, [$supplier_id]);
$total_orders = $total_result['total'] ?? 0;
$total_pages = ceil($total_orders / $limit);

$page_title = 'Order Archive';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <section class="orders-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold"><i class="fas fa-archive me-2"></i>Order Archive</h3>
                <a href="orders.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>
            <p class="text-muted mt-2">Orders that were archived due to non-payment within <?= ORDER_TIMEOUT_LABEL ?></p>
        </section>

        <?php if (empty($archived_orders)): ?>
            <div class="card border-0 shadow-sm bg-white text-center p-5">
                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                <h3>No Archived Orders</h3>
                <p class="text-muted">Orders that fail payment within <?= ORDER_TIMEOUT_LABEL ?> will appear here.</p>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless align-middle mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th class="py-3">Order #</th>
                                    <th class="py-3">Customer</th>
                                    <th class="py-3">Amount</th>
                                    <th class="py-3">Archived Date</th>
                                    <th class="py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archived_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo $order['archived_at'] ? format_date($order['archived_at']) : 'N/A'; ?></td>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>


