<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'method' => $_GET['method'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get payments
$payments = $admin->getAllPayments($filters);

$page_title = 'Payments';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Payments</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filtersModal">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportPayments()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo format_currency(array_sum(array_column(array_filter($payments, fn($p) => $p['status'] === 'completed'), 'amount'))); ?></h4>
                                    <p class="mb-0">Total Revenue</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($payments, fn($p) => $p['status'] === 'completed')); ?></h4>
                                    <p class="mb-0">Completed</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($payments, fn($p) => $p['status'] === 'pending')); ?></h4>
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
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($payments, fn($p) => $p['status'] === 'failed')); ?></h4>
                                    <p class="mb-0">Failed</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $filters['status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="method">
                                <option value="">All Methods</option>
                                <option value="gcash" <?php echo $filters['method'] === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                                <option value="paymaya" <?php echo $filters['method'] === 'paymaya' ? 'selected' : ''; ?>>PayMaya</option>
                                <option value="bank_transfer" <?php echo $filters['method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="cod" <?php echo $filters['method'] === 'cod' ? 'selected' : ''; ?>>Cash on Delivery</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $filters['date_from']; ?>" placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" value="<?php echo $filters['date_to']; ?>" placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="payments.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['transaction_id']); ?></strong>
                                        </td>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $payment['order_id']; ?>" class="text-decoration-none">
                                                #<?php echo str_pad($payment['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo format_currency($payment['amount']); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $method_icons = [
                                                'gcash' => 'fas fa-mobile-alt text-primary',
                                                'paymaya' => 'fas fa-credit-card text-success',
                                                'bank_transfer' => 'fas fa-university text-info',
                                                'cod' => 'fas fa-money-bill text-warning'
                                            ];
                                            $icon = $method_icons[$payment['payment_method']] ?? 'fas fa-credit-card';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'refunded' => 'info'
                                            ];
                                            $color = $status_colors[$payment['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($payment['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($payment['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewPayment(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($payment['status'] === 'pending'): ?>
                                                    <button class="btn btn-outline-success" onclick="approvePayment(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($payment['status'] === 'completed'): ?>
                                                    <button class="btn btn-outline-warning" onclick="refundPayment(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-undo"></i>
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
    </main>

<script>
function viewPayment(paymentId) {
    window.location.href = `payment-details.php?id=${paymentId}`;
}

function approvePayment(paymentId) {
    if (confirm('Are you sure you want to approve this payment?')) {
        // Implement payment approval
        window.location.href = `approve-payment.php?id=${paymentId}`;
    }
}

function refundPayment(paymentId) {
    if (confirm('Are you sure you want to refund this payment?')) {
        // Implement payment refund
        window.location.href = `refund-payment.php?id=${paymentId}`;
    }
}

function exportPayments() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = `export-payments.php?${params.toString()}`;
}
</script>

