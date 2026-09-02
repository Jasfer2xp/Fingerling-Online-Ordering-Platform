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

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o 
              WHERE o.supplier_id = ? 
              AND o.status IN ('ready_for_delivery', 'out_for_delivery', 'delivered')";
$total_result = $database->fetch($count_sql, [$supplier_id]);
$total_orders = $total_result['total'] ?? 0;
$total_pages = ceil($total_orders / $limit);

// Get delivery orders with pagination
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
               c.contact_number, c.address, c.city, c.province,
               COALESCE(order_revenue.net_amount, 0) as net_amount
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        LEFT JOIN (
            SELECT order_id, SUM(subtotal) AS net_amount
            FROM order_items
            GROUP BY order_id
        ) order_revenue ON order_revenue.order_id = o.id
        WHERE o.supplier_id = ? 
        AND o.status IN ('ready_for_delivery', 'out_for_delivery', 'delivered')
        ORDER BY 
            CASE 
                WHEN o.status = 'ready_for_delivery' THEN 1
                WHEN o.status = 'out_for_delivery' THEN 2
                WHEN o.status = 'delivered' THEN 3
            END,
            o.delivery_date ASC, o.created_at DESC
        LIMIT ? OFFSET ?";

$deliveries = $database->fetchAll($sql, [$supplier_id, $limit, $offset]);

// Handle delivery status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_delivery_status') {
        $order_id = $_POST['order_id'] ?? '';
        $new_status = $_POST['status'] ?? '';
        $delivery_worker = $_POST['delivery_worker'] ?? '';
        $delivery_notes = $_POST['delivery_notes'] ?? '';
        
        if ($order_id && $new_status) {
            $update_data = [
                'status' => $new_status,
                'delivery_worker' => $delivery_worker,
                'delivery_notes' => $delivery_notes
            ];
            
            if ($new_status === 'delivered') {
                $update_data['delivery_date'] = date('Y-m-d H:i:s');
            }
            
            if ($supplier->updateOrderDelivery($order_id, $update_data)) {
                $success_message = "Delivery status updated successfully!";
                $deliveries = $database->fetchAll($sql, [$supplier_id]);
            } else {
                $error_message = "Failed to update delivery status.";
            }
        }
    }
}

$page_title = 'Deliveries';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="deliveries-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold"><i class="fas fa-truck me-2"></i>Deliveries</h3>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm" onclick="printDeliveryList()">
                        <i class="fas fa-print me-2"></i>Print List
                    </button>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Delivery Summary -->
        <section class="delivery-summary mb-5">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1"><?php echo count(array_filter($deliveries, fn($d) => $d['status'] === 'ready_for_delivery')); ?></h4>
                                <p class="card-text text-muted">Ready for Delivery</p>
                            </div>
                            <i class="fas fa-box fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1"><?php echo count(array_filter($deliveries, fn($d) => $d['status'] === 'out_for_delivery')); ?></h4>
                                <p class="card-text text-muted">Out for Delivery</p>
                            </div>
                            <i class="fas fa-truck fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1"><?php echo count(array_filter($deliveries, fn($d) => $d['status'] === 'delivered')); ?></h4>
                                <p class="card-text text-muted">Delivered</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Deliveries List -->
        <section class="deliveries-list">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-borderless align-middle mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th class="py-3">Order #</th>
                                    <th class="py-3">Customer</th>
                                    <th class="py-3">Amount</th>
                                    <th class="py-3">Delivery Date</th>
                                    <th class="py-3">Worker</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveries as $order): ?>
                                    <tr>
                                        <td><?php echo $order['order_number']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>₱<?php echo number_format($order['net_amount'] ?? $order['total_amount'], 2); ?></td>
                                        <td><?php echo format_date($order['delivery_date']); ?></td>
                                        <td><?php echo htmlspecialchars($order['delivery_worker'] ?? 'Not assigned'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($order['status']) {
                                                    'ready_for_delivery' => 'warning',
                                                    'out_for_delivery' => 'info',
                                                    'delivered' => 'success',
                                                    default => 'secondary'
                                                }; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($order['status'] === 'ready_for_delivery'): ?>
                                                    <button class="btn btn-sm btn-outline-info" onclick="updateDeliveryStatus(<?php echo $order['id']; ?>, 'out_for_delivery', '<?php echo $order['order_number']; ?>')">
                                                        <i class="fas fa-truck"></i>
                                                    </button>
                                                <?php elseif ($order['status'] === 'out_for_delivery'): ?>
                                                    <button class="btn btn-sm btn-outline-success" onclick="updateDeliveryStatus(<?php echo $order['id']; ?>, 'delivered', '<?php echo $order['order_number']; ?>')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
        </section>
    </div>
</div>

<!-- Update Delivery Status Modal -->
<div class="modal fade" id="deliveryStatusModal" tabindex="-1" aria-labelledby="deliveryStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deliveryStatusModalLabel">Update Delivery Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deliveryStatusForm">
                <input type="hidden" name="action" value="update_delivery_status">
                <input type="hidden" name="order_id" id="updateOrderId">
                <input type="hidden" name="status" id="updateStatus">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <input type="text" class="form-control" id="updateOrderNumber" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_worker" class="form-label">Delivery Worker</label>
                        <input type="text" class="form-control" id="delivery_worker" name="delivery_worker" 
                               placeholder="Enter delivery worker name">
                    </div>
                    <div class="mb-3">
                        <label for="delivery_notes" class="form-label">Delivery Notes</label>
                        <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3" 
                                  placeholder="Optional delivery notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateStatusBtn">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Delivery Worker Assignment Modal removed as per user request -->
<style>
    .alert {
        border-radius: 0.5rem;
    }
    .badge {
        padding: 0.5em 0.75em;
    }
    .delivery-summary .card {
        transition: transform 0.2s;
    }
    .delivery-summary .card:hover {
        transform: translateY(-2px);
    }
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .btn-group .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .card-title {
            font-size: 1.2rem;
        }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateDeliveryStatus(orderId, status, orderNumber) {
    document.getElementById('updateOrderId').value = orderId;
    document.getElementById('updateStatus').value = status;
    document.getElementById('updateOrderNumber').value = '#' + orderNumber;
    
    const statusBtn = document.getElementById('updateStatusBtn');
    if (status === 'out_for_delivery') {
        statusBtn.innerHTML = '<i class="fas fa-truck me-2"></i>Dispatch Order';
    } else if (status === 'delivered') {
        statusBtn.innerHTML = '<i class="fas fa-check me-2"></i>Mark as Delivered';
    }
    
    new bootstrap.Modal(document.getElementById('deliveryStatusModal')).show();
}

function printDeliveryList() {
    window.print();
}

// Sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
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
</script>
</body>
</html>