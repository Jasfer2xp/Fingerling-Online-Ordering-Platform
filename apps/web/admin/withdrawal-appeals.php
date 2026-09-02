<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Handle appeal status update BEFORE including headers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appeal'])) {
    // Start output buffering to prevent any output before redirect
    ob_start();
    
    $appeal_id = intval($_POST['appeal_id']);
    $status = $_POST['status'] ?? '';
    
    if (empty($status) || !in_array($status, ['approved', 'rejected'])) {
        $_SESSION['error'] = 'Invalid status selected.';
    } else {
        try {
            $sql = "UPDATE withdrawal_appeals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $database->query($sql, [$status, $appeal_id]);
            
            if ($status === 'approved') {
                $_SESSION['success'] = 'Appeal approved. Please process the payout manually.';
            } elseif ($status === 'rejected') {
                $_SESSION['success'] = 'Appeal rejected.';
            }
        } catch (Exception $e) {
            error_log("Appeal update error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to update appeal status. Please try again.';
        }
    }
    
    // Clean any remaining output and redirect
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Location: ' . base_url('admin/withdrawal-appeals.php'));
    exit;
}

$page_title = 'Withdrawal Appeals';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';

// Get pending appeals
$sql = "SELECT wa.*, w.amount, w.method, w.account_info, s.business_name, u.email as supplier_email 
        FROM withdrawal_appeals wa
        JOIN withdrawals w ON wa.withdrawal_id = w.id
        JOIN suppliers s ON wa.supplier_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE wa.status = 'pending'
        ORDER BY wa.created_at DESC";
$pending_appeals = $database->fetchAll($sql);
?>

<div class="role-main-content admin-main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Withdrawal Appeals</h3>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($pending_appeals)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No pending appeals</h5>
                        <p class="text-muted">All withdrawal appeals have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_appeals as $appeal): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($appeal['created_at'])); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($appeal['business_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo $appeal['supplier_email']; ?>
                                            </small>
                                        </td>
                                        <td>₱<?php echo number_format($appeal['amount'], 2); ?></td>
                                        <td>
                                            <?php if ($appeal['method'] === 'gcash'): ?>
                                                <i class="fas fa-mobile-alt me-1"></i> GCash
                                            <?php else: ?>
                                                <i class="fab fa-paypal me-1"></i> PayPal
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appeal['reason']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#processAppealModal<?php echo $appeal['id']; ?>">
                                                Process
                                            </button>
                                            
                                            <!-- Process Appeal Modal -->
                                            <div class="modal fade" id="processAppealModal<?php echo $appeal['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Process Appeal</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Supplier</label>
                                                                    <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars($appeal['business_name']); ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Amount</label>
                                                                    <input type="text" class="form-control-plaintext" value="₱<?php echo number_format($appeal['amount'], 2); ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Method</label>
                                                                    <input type="text" class="form-control-plaintext" value="<?php echo ucfirst($appeal['method']); ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Account Info</label>
                                                                    <input type="text" class="form-control-plaintext" value="<?php echo $appeal['account_info']; ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Reason</label>
                                                                    <textarea class="form-control-plaintext" rows="3" readonly><?php echo htmlspecialchars($appeal['reason']); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Action</label>
                                                                    <div>
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="status" id="statusApproved<?php echo $appeal['id']; ?>" value="approved" required>
                                                                            <label class="form-check-label" for="statusApproved<?php echo $appeal['id']; ?>">Approve</label>
                                                                        </div>
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="status" id="statusRejected<?php echo $appeal['id']; ?>" value="rejected" required>
                                                                            <label class="form-check-label" for="statusRejected<?php echo $appeal['id']; ?>">Reject</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" name="update_appeal" class="btn btn-primary">Update Status</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
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
    </div>
</div>

<?php include '../includes/modern_admin_footer.php'; ?>