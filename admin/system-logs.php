<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle AJAX requests for log data
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    try {
        $logs = $admin->getAuditLog($limit, $offset);
        echo json_encode(['success' => true, 'logs' => $logs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get recent logs for initial display
$recent_logs = $admin->getAuditLog(50);

$page_title = 'System Logs';
include '../includes/modern_admin_header.php';




?>

<?php include '../includes/modern_admin_sidebar.php'; ?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                System Logs
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <button type="button" class="modern-btn modern-btn-outline-secondary modern-btn-sm" onclick="refreshLogs()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <div class="container-fluid px-4">
        <!-- Filters -->
        <div class="modern-card role-card admin-card mb-4">
            <div class="modern-card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label for="actionFilter" class="form-label">Action Type</label>
                        <select class="form-select" id="actionFilter">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="delete">Delete</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tableFilter" class="form-label">Table</label>
                        <select class="form-select" id="tableFilter">
                            <option value="">All Tables</option>
                            <option value="users">Users</option>
                            <option value="suppliers">Suppliers</option>
                            <option value="orders">Orders</option>
                            <option value="customers">Customers</option>
                            <option value="inventory">Inventory</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dateFilter" class="form-label">Date Range</label>
                        <select class="form-select" id="dateFilter">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="modern-table-card">
            <div class="modern-table-header">
                <h5 class="modern-table-title">
                    <i class="fas fa-list"></i> System Activity Log
                </h5>
                <div class="modern-table-filters">
                    <span class="badge bg-info" id="logCount"><?php echo count($recent_logs); ?> entries</span>
                </div>
            </div>
            <div class="modern-table-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="logsTable">
                        <thead class="table-dark">
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
                        <tbody id="logsTableBody">
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-list-alt fa-3x text-muted mb-3"></i>
                                        <h5>No Activity Logs</h5>
                                        <p class="text-muted">No system activity logs found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($log['admin_email'] ?? 'System'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($log['action']) {
                                                    'create' => 'success',
                                                    'update' => 'warning',
                                                    'delete' => 'danger',
                                                    'login' => 'info',
                                                    'logout' => 'secondary',
                                                    default => 'primary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                        <td><?php echo htmlspecialchars($log['record_id']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['new_values'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                        onclick="showLogDetails('<?php echo htmlspecialchars(json_encode($log)); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">Showing latest 50 entries</small>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadMoreLogs()">
                            <i class="fas fa-plus"></i> Load More
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Entry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="logDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modern JavaScript -->
<script src="../assets/js/modern-sidebar.js"></script>

<script>
function refreshLogs() {
    window.location.reload();
}

function applyFilters() {
    const action = document.getElementById('actionFilter').value;
    const table = document.getElementById('tableFilter').value;
    const date = document.getElementById('dateFilter').value;

    // Build query parameters
    const params = new URLSearchParams();
    if (action) params.append('action', action);
    if (table) params.append('table', table);
    if (date) params.append('date', date);

    // Reload page with filters
    window.location.href = '?' + params.toString();
}

function loadMoreLogs() {
    // Implementation for loading more logs via AJAX
    fetch('?action=get_logs&page=2')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs.length > 0) {
                const tbody = document.getElementById('logsTableBody');
                data.logs.forEach(log => {
                    const row = createLogRow(log);
                    tbody.appendChild(row);
                });
                document.getElementById('logCount').textContent =
                    (parseInt(document.getElementById('logCount').textContent) + data.logs.length) + ' entries';
            }
        })
        .catch(error => {
            console.error('Error loading more logs:', error);
        });
}

function showLogDetails(logData) {
    const log = typeof logData === 'string' ? JSON.parse(logData) : logData;

    const content = `
        <div class="row">
            <div class="col-md-6">
                <strong>Timestamp:</strong><br>
                ${new Date(log.created_at).toLocaleString()}
            </div>
            <div class="col-md-6">
                <strong>User:</strong><br>
                ${log.admin_email || 'System'}
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-4">
                <strong>Action:</strong><br>
                <span class="badge bg-primary">${log.action}</span>
            </div>
            <div class="col-md-4">
                <strong>Table:</strong><br>
                ${log.table_name}
            </div>
            <div class="col-md-4">
                <strong>Record ID:</strong><br>
                ${log.record_id}
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-12">
                <strong>IP Address:</strong><br>
                ${log.ip_address}
            </div>
        </div>
        ${log.new_values ? `
        <hr>
        <div class="row">
            <div class="col-12">
                <strong>Changes:</strong><br>
                <pre class="bg-light p-2 rounded">${JSON.stringify(JSON.parse(log.new_values), null, 2)}</pre>
            </div>
        </div>
        ` : ''}
    `;

    document.getElementById('logDetailsContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    modal.show();
}

function createLogRow(log) {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><small>${new Date(log.created_at).toLocaleString()}</small></td>
        <td>${log.admin_email || 'System'}</td>
        <td><span class="badge bg-primary">${log.action}</span></td>
        <td>${log.table_name}</td>
        <td>${log.record_id}</td>
        <td><small>${log.ip_address}</small></td>
        <td>
            ${log.new_values ?
                `<button type="button" class="btn btn-sm btn-outline-info" onclick="showLogDetails('${JSON.stringify(log).replace(/'/g, "\\'")}')">
                    <i class="fas fa-eye"></i> View
                </button>` :
                '<span class="text-muted">-</span>'
            }
        </td>
    `;
    return row;
}
</script>

<!-- Modern JavaScript -->
<script src="../assets/js/modern-sidebar.js"></script>

