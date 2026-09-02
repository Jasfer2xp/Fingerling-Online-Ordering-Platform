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
    'action' => $_GET['action'] ?? '',
    'table' => $_GET['table'] ?? '',
    'user' => $_GET['user'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get audit log entries
$audit_logs = $admin->getAuditLog(100, 0);

$page_title = 'Audit Log';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Audit Log</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshLog()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportLog()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select class="form-select" name="action">
                                <option value="">All Actions</option>
                                <option value="user_login" <?php echo $filters['action'] === 'user_login' ? 'selected' : ''; ?>>User Login</option>
                                <option value="user_logout" <?php echo $filters['action'] === 'user_logout' ? 'selected' : ''; ?>>User Logout</option>
                                <option value="order_created" <?php echo $filters['action'] === 'order_created' ? 'selected' : ''; ?>>Order Created</option>
                                <option value="supplier_approved" <?php echo $filters['action'] === 'supplier_approved' ? 'selected' : ''; ?>>Supplier Approved</option>
                                <option value="settings_updated" <?php echo $filters['action'] === 'settings_updated' ? 'selected' : ''; ?>>Settings Updated</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="table">
                                <option value="">All Tables</option>
                                <option value="users" <?php echo $filters['table'] === 'users' ? 'selected' : ''; ?>>Users</option>
                                <option value="suppliers" <?php echo $filters['table'] === 'suppliers' ? 'selected' : ''; ?>>Suppliers</option>
                                <option value="orders" <?php echo $filters['table'] === 'orders' ? 'selected' : ''; ?>>Orders</option>
                                <option value="settings" <?php echo $filters['table'] === 'settings' ? 'selected' : ''; ?>>Settings</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $filters['date_from']; ?>" placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" value="<?php echo $filters['date_to']; ?>" placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="user" value="<?php echo htmlspecialchars($filters['user']); ?>" placeholder="User email">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Log Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">System Activity Log</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($audit_logs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5>No Audit Logs</h5>
                            <p class="text-muted">No system activities have been logged yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>IP Address</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                                                <small class="text-muted"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['admin_email']): ?>
                                                    <strong><?php echo htmlspecialchars($log['admin_email']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $action_colors = [
                                                    'user_login' => 'success',
                                                    'user_logout' => 'secondary',
                                                    'order_created' => 'primary',
                                                    'supplier_approved' => 'success',
                                                    'supplier_rejected' => 'danger',
                                                    'settings_updated' => 'warning'
                                                ];
                                                $color = $action_colors[$log['action']] ?? 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($log['action'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($log['table_name']); ?></code>
                                            </td>
                                            <td>
                                                <?php echo $log['record_id'] ? '#' . $log['record_id'] : '-'; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['new_values']): ?>
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewDetails(<?php echo htmlspecialchars($log['new_values']); ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
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

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="logDetails" class="bg-light p-3"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function refreshLog() {
    window.location.reload();
}

function exportLog() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = `export-audit-log.php?${params.toString()}`;
}

function viewDetails(details) {
    try {
        const formatted = JSON.stringify(details, null, 2);
        document.getElementById('logDetails').textContent = formatted;
    } catch (e) {
        document.getElementById('logDetails').textContent = details;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
}
</script>
