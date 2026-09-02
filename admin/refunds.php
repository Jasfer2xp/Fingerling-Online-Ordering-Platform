<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle refund actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refund_id = $_POST['refund_id'] ?? '';
    
    try {
        switch ($action) {
            case 'approve':
                $admin->approveRefund($refund_id);
                $success = 'Refund approved successfully.';
                break;
            case 'reject':
                $admin->rejectRefund($refund_id, $_POST['reason'] ?? '');
                $success = 'Refund rejected successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get refunds
$refunds = $admin->getAllRefunds();

$page_title = 'Refunds';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Refunds</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-warning fs-6">
                        <?php echo count(array_filter($refunds, fn($r) => $r['status'] === 'pending')); ?> pending
                    </span>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Refund Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($refunds, fn($r) => $r['status'] === 'pending')); ?></h4>
                                    <p class="mb-0">Pending</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($refunds, fn($r) => $r['status'] === 'approved')); ?></h4>
                                    <p class="mb-0">Approved</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($refunds, fn($r) => $r['status'] === 'rejected')); ?></h4>
                                    <p class="mb-0">Rejected</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo format_currency(array_sum(array_column(array_filter($refunds, fn($r) => $r['status'] === 'approved'), 'amount'))); ?></h4>
                                    <p class="mb-0">Total Refunded</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-money-bill fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Refunds List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Refund Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($refunds)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-undo fa-3x text-muted mb-3"></i>
                            <h5>No Refund Requests</h5>
                            <p class="text-muted">There are no refund requests at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Refund ID</th>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($refunds as $refund): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo str_pad($refund['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $refund['order_id']; ?>" class="text-decoration-none">
                                                    #<?php echo str_pad($refund['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($refund['customer_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($refund['customer_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo format_currency($refund['amount']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($refund['reason']); ?>">
                                                    <?php echo htmlspecialchars(substr($refund['reason'], 0, 50)) . (strlen($refund['reason']) > 50 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'processed' => 'info'
                                                ];
                                                $color = $status_colors[$refund['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($refund['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($refund['created_at'])); ?><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($refund['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="viewRefund(<?php echo $refund['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($refund['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-success" onclick="approveRefund(<?php echo $refund['id']; ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="rejectRefund(<?php echo $refund['id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </main>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Refund Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="refund_id" id="rejectRefundId">
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" name="reason" id="reason" rows="4" 
                                  placeholder="Please provide a reason for rejecting this refund request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewRefund(refundId) {
    window.location.href = `refund-details.php?id=${refundId}`;
}

function approveRefund(refundId) {
    if (confirm('Are you sure you want to approve this refund?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="refund_id" value="${refundId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectRefund(refundId) {
    document.getElementById('rejectRefundId').value = refundId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>


