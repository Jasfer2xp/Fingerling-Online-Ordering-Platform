<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$supplier_id = $profile['id'];

// Check if supplier is approved
if ($profile['status'] !== 'approved') {
    $_SESSION['error'] = 'Your account is not approved yet.';
    redirect(base_url('supplier/dashboard.php'));
}

// Handle withdrawal submission BEFORE including headers (to prevent output issues)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    // Start output buffering to prevent any output before redirect
    ob_start();
    
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'] ?? '';
    $account_info = '';

    if ($amount <= 0) {
        $_SESSION['error'] = 'Please enter a valid amount.';
    } else {
        // Total earnings from delivered orders (Net after 5% Service Fee)
        $total_earned = $database->fetch(
            "SELECT COALESCE(SUM(oi.subtotal * 0.95), 0) as total
             FROM orders o
             JOIN order_items oi ON o.id = oi.order_id
             WHERE o.supplier_id = ? AND o.status = 'delivered'",
            [$supplier_id]
        )['total'];

        // Total already withdrawn (completed payouts)
        $total_withdrawn = $database->fetch(
            "SELECT COALESCE(SUM(amount), 0) as withdrawn FROM withdrawals WHERE supplier_id = ? AND status = 'completed'",
            [$supplier_id]
        )['withdrawn'];

        $available_earnings = $total_earned - $total_withdrawn;

        if ($amount > $available_earnings) {
            $_SESSION['error'] = 'Insufficient earnings. Available amount: ₱' . number_format($available_earnings, 2);
        } else {
            if ($method === 'gcash' || $method === 'paypal') {
                $account_info = trim($_POST['account_info'] ?? '');
                if (empty($account_info)) {
                    $_SESSION['error'] = 'Please provide your ' . ($method === 'gcash' ? 'GCash Number' : 'PayPal Email') . '.';
                }
            } else {
                $_SESSION['error'] = 'Invalid withdrawal method.';
            }

            if (!isset($_SESSION['error'])) {
                try {
                    $database->query(
                        "INSERT INTO withdrawals (supplier_id, amount, method, account_info, status, created_at) 
                         VALUES (?, ?, ?, ?, 'pending', NOW())",
                        [$supplier_id, $amount, $method, $account_info]
                    );
                    $_SESSION['success'] = 'Withdrawal request submitted successfully. Payouts typically take 2-3 business days.';
                } catch (Exception $e) {
                    error_log("Withdrawal submission error: " . $e->getMessage());
                    $_SESSION['error'] = 'Failed to submit request. Please try again.';
                }
            }
        }
    }

    // Prevent white screen — ensure no output before redirect
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Redirect back to withdraw page
    header('Location: ' . base_url('supplier/withdraw.php'));
    exit;
}

// Handle appeal submission BEFORE including headers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appeal'])) {
    // Start output buffering to prevent any output before redirect
    ob_start();
    
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $reason = trim($_POST['reason']);

    if (empty($reason)) {
        $_SESSION['error'] = 'Please provide a reason for your appeal.';
    } else {
        try {
            $check = $database->fetch("SELECT id FROM withdrawals WHERE id = ? AND supplier_id = ? AND status = 'completed'", [$withdrawal_id, $supplier_id]);
            if ($check) {
                $database->query("INSERT INTO withdrawal_appeals (withdrawal_id, supplier_id, reason, status) VALUES (?, ?, ?, 'pending')", [$withdrawal_id, $supplier_id, $reason]);
                $_SESSION['success'] = 'Appeal submitted successfully.';
            } else {
                $_SESSION['error'] = 'Invalid withdrawal request.';
            }
        } catch (Exception $e) {
            error_log("Appeal submission error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to submit appeal. Please try again.';
        }
    }
    
    // Clean output buffer and redirect
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Location: ' . base_url('supplier/withdraw.php'));
    exit;
}

$page_title = 'Withdraw Earnings';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';

// === CORRECT AVAILABLE EARNINGS CALCULATION (Net after 5% Service Fee) ===
$total_earned = $database->fetch(
    "SELECT COALESCE(SUM(oi.subtotal * 0.95), 0) as total
     FROM orders o
     JOIN order_items oi ON o.id = oi.order_id
     WHERE o.supplier_id = ? AND o.status = 'delivered'",
    [$supplier_id]
)['total'];

$total_withdrawn = $database->fetch(
    "SELECT COALESCE(SUM(amount), 0) as withdrawn FROM withdrawals WHERE supplier_id = ? AND status = 'completed'",
    [$supplier_id]
)['withdrawn'];

$available_earnings = max(0, $total_earned - $total_withdrawn); // Never negative

// Get withdrawal history
$withdrawals = $database->fetchAll("SELECT * FROM withdrawals WHERE supplier_id = ? ORDER BY created_at DESC", [$supplier_id]);
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Withdraw Earnings</h3>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Available Earnings</h5>
                        <h2 class="text-success">₱<?= number_format($available_earnings, 2); ?></h2>
                        <p class="text-muted">Total earnings from completed orders (minus paid withdrawals)</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Request Withdrawal</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount (₱)</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="50" max="<?= $available_earnings ?>" placeholder="Minimum ₱50.00" required>
                                <div class="form-text">Available amount: ₱<?= number_format($available_earnings, 2); ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Withdrawal Method</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="method" id="methodGcash" value="gcash" required>
                                        <label class="form-check-label" for="methodGcash">
                                            GCash
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="method" id="methodPaypal" value="paypal">
                                        <label class="form-check-label" for="methodPaypal">
                                            PayPal
                                        </label>
                                    </div>
                                </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3 mt-3">
                                    <label for="accountInfo" class="form-label">Account Info</label>
                                    <input type="text" class="form-control" id="accountInfo" name="account_info" 
                                           value="<?= htmlspecialchars($profile['contact_number'] ?? ''); ?>" required>
                                    <div class="form-text" id="accountInfoHelp">
                                        Enter your GCash Number or PayPal Email.
                                    </div>
                                </div>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const gcashRadio = document.getElementById('methodGcash');
                                    const paypalRadio = document.getElementById('methodPaypal');
                                    const accountInput = document.getElementById('accountInfo');
                                    const profileContact = "<?= htmlspecialchars($profile['contact_number'] ?? ''); ?>";
                                    const profileEmail = "<?= htmlspecialchars($profile['email'] ?? ''); ?>";

                                    function updateAccountField() {
                                        if (gcashRadio.checked) {
                                            accountInput.value = profileContact;
                                            accountInput.placeholder = "Enter GCash Number";
                                            accountInput.type = "text";
                                        } else if (paypalRadio.checked) {
                                            accountInput.value = profileEmail;
                                            accountInput.placeholder = "Enter PayPal Email";
                                            accountInput.type = "email";
                                        }
                                    }

                                    gcashRadio.addEventListener('change', updateAccountField);
                                    paypalRadio.addEventListener('change', updateAccountField);
                                    
                                    // Initialize
                                    updateAccountField();
                                });
                                </script>
                            </div>

                            <button type="submit" name="submit_withdrawal" class="btn btn-primary w-100">
                                Submit Withdrawal Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Withdrawal History</h5>
                        <?php if (empty($withdrawals)): ?>
                            <p class="text-muted text-center py-4">No withdrawal requests yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($withdrawals as $withdrawal): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($withdrawal['created_at'])); ?></td>
                                                <td>₱<?= number_format($withdrawal['amount'], 2); ?></td>
                                                <td>
                                                    <?= $withdrawal['method'] === 'gcash' ? 'GCash' : 'PayPal'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($withdrawal['status'] === 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif ($withdrawal['status'] === 'cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($withdrawal['status'] === 'completed'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#appealModal<?= $withdrawal['id']; ?>">
                                                            Submit Appeal
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Appeal Modals - Placed outside table for better compatibility -->
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <?php if ($withdrawal['status'] === 'completed'): ?>
                                    <div class="modal fade" id="appealModal<?= $withdrawal['id']; ?>" tabindex="-1" aria-labelledby="appealModalLabel<?= $withdrawal['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="appealModalLabel<?= $withdrawal['id']; ?>">Submit Appeal</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Reason</label>
                                                            <textarea class="form-control" name="reason" rows="4" required placeholder="Explain why you didn't receive the payout"></textarea>
                                                            <div class="form-text">Please provide a detailed explanation for your appeal.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="submit_appeal" class="btn btn-primary">Submit Appeal</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Ensure appeal buttons are clickable */
.btn[data-bs-toggle="modal"] {
    pointer-events: auto !important;
    cursor: pointer !important;
    z-index: 1;
    position: relative;
}

/* Ensure modals are above everything */
.modal {
    z-index: 1055;
}

/* Fix any table cell click issues */
table tbody td {
    position: relative;
}

table tbody td .btn {
    position: relative;
    z-index: 10;
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Prevent form resubmission on refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Wait for Bootstrap to load and ensure modals work
// Wait for Bootstrap to load and ensure modals work
(function() {
    function waitForBootstrap(callback, maxAttempts = 50) {
        let attempts = 0;
        const checkBootstrap = setInterval(function() {
            attempts++;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                clearInterval(checkBootstrap);
                callback();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkBootstrap);
                console.error('Bootstrap failed to load after', maxAttempts * 100, 'ms');
            }
        }, 100);
    }
    
    function initAppealModals() {
        // Verify all appeal buttons and modals exist
        const appealButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target^="#appealModal"]');
        console.log('Found', appealButtons.length, 'appeal button(s)');
        
        appealButtons.forEach(function(button) {
            const targetId = button.getAttribute('data-bs-target');
            const modalElement = document.querySelector(targetId);
            
            if (modalElement) {
                console.log('Modal found:', targetId);
                
                // Ensure button is clickable
                button.style.pointerEvents = 'auto';
                button.style.cursor = 'pointer';
                
                // Add click handler as backup (Bootstrap should handle it via data attributes)
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                });
            } else {
                console.error('Modal not found for:', targetId);
            }
        });
    }
    
    // Wait for DOM and Bootstrap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            waitForBootstrap(initAppealModals);
        });
    } else {
        waitForBootstrap(initAppealModals);
    }
})();
</script>
