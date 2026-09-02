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

// Handle withdrawal status update BEFORE including headers (to prevent output issues)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_withdrawal'])) {
    // Start output buffering to prevent any output before redirect
    ob_start();
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $status = $_POST['status'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    try {
        if ($status === 'completed') {
            $database->query(
                "UPDATE withdrawals SET status = 'completed', updated_at = NOW() WHERE id = ?",
                [$withdrawal_id]
            );
            $_SESSION['success'] = 'Withdrawal marked as completed successfully.';
        } 
        elseif ($status === 'cancelled') {
            if (empty($reason)) {
                $_SESSION['error'] = 'Please provide a reason for cancellation.';
            } else {
                $database->query(
                    "UPDATE withdrawals SET status = 'cancelled', account_info = ?, updated_at = NOW() WHERE id = ?",
                    [$reason, $withdrawal_id]
                );
                $_SESSION['success'] = 'Withdrawal cancelled successfully.';
            }
        } 
        else {
            $_SESSION['error'] = 'Invalid status selected.';
        }
    } catch (Exception $e) {
        error_log("Withdrawal update failed: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update withdrawal. Please try again.';
    }

    // Clean any remaining output and redirect
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Redirect AFTER processing, with exit
    header('Location: ' . base_url('admin/withdrawals.php'));
    exit;
}

$page_title = 'Pending Withdrawals';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';

// Get pending withdrawals
$sql = "SELECT w.*, s.business_name, s.contact_number, u.email as supplier_email 
        FROM withdrawals w
        JOIN suppliers s ON w.supplier_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE w.status = 'pending'
        ORDER BY w.created_at DESC";
$pending_withdrawals = $database->fetchAll($sql);
?>

<div class="role-main-content admin-main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Pending Withdrawals</h3>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($pending_withdrawals)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-transfer fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No pending withdrawals</h5>
                        <p class="text-muted">All withdrawal requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Account Info</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_withdrawals as $w): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($w['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($w['business_name']); ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($w['supplier_email']); ?></small>
                                        </td>
                                        <td class="fw-bold text-success">₱<?= number_format($w['amount'], 2); ?></td>
                                        <td>
                                            <?php if ($w['method'] === 'gcash'): ?>
                                                <span class="text-success">GCash</span>
                                            <?php else: ?>
                                                <span class="text-primary">PayPal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $w['method'] === 'gcash' ? htmlspecialchars($w['contact_number']) : htmlspecialchars($w['supplier_email']); ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#processModal<?= $w['id']; ?>">
                                                Process
                                            </button>

                                            <!-- Process Modal -->
                                            <div class="modal fade" id="processModal<?= $w['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Process Withdrawal #<?= $w['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="withdrawal_id" value="<?= $w['id']; ?>">

                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Supplier</label>
                                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($w['business_name']); ?>" readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Amount</label>
                                                                        <input type="text" class="form-control" value="₱<?= number_format($w['amount'], 2); ?>" readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Method</label>
                                                                        <input type="text" class="form-control" value="<?= ucfirst($w['method']); ?>" readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Account</label>
                                                                        <input type="text" class="form-control" value="<?= $w['method'] === 'gcash' ? $w['contact_number'] : $w['supplier_email']; ?>" readonly>
                                                                    </div>
                                                                </div>

                                                                <hr class="my-4">

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Action</label>
                                                                    <div class="d-flex gap-4">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="radio" name="status" value="completed" id="complete<?= $w['id']; ?>" required>
                                                                            <label class="form-check-label text-success fw-bold" for="complete<?= $w['id']; ?>">
                                                                                Mark as Completed
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="radio" name="status" value="cancelled" id="cancel<?= $w['id']; ?>" required>
                                                                            <label class="form-check-label text-danger fw-bold" for="cancel<?= $w['id']; ?>">
                                                                                Cancel Withdrawal
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div id="reasonContainer<?= $w['id']; ?>" style="display:none;">
                                                                    <label for="reason<?= $w['id']; ?>" class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                                                                    <textarea class="form-control" name="reason" id="reason<?= $w['id']; ?>" rows="3" placeholder="e.g., Invalid account details, suspicious activity, etc."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" name="update_withdrawal" class="btn btn-primary">Update Status</button>
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

<script>
// Fixed: Proper event delegation for dynamic modals
document.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'status') {
        const modalId = e.target.closest('.modal').id;
        const withdrawalId = modalId.replace('processModal', '');
        const reasonContainer = document.getElementById('reasonContainer' + withdrawalId);
        const reasonField = document.getElementById('reason' + withdrawalId);

        if (e.target.value === 'cancelled') {
            reasonContainer.style.display = 'block';
            reasonField.required = true;
        } else {
            reasonContainer.style.display = 'none';
            reasonField.required = false;
            reasonField.value = '';
        }
    }
});
</script>
