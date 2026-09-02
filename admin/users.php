<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$user = new User($database);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    try {
        switch ($action) {
            case 'activate':
                $user->updateStatus($user_id, 'active');
                $success = 'User activated successfully.';
                break;
            case 'deactivate':
                $user->updateStatus($user_id, 'inactive');
                $success = 'User deactivated successfully.';
                break;
            case 'delete':
                $user->deleteUser($user_id);
                $success = 'User deleted successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filters
$filters = [
    'user_type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get users
$users = $admin->getAllUsers($filters);

$page_title = 'User Management';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="fas fa-plus"></i> Add User
</button>';

include '../includes/modern_admin_header.php';
?>

<?php include '../includes/modern_admin_sidebar.php'; ?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <?php include '../includes/modern_admin_dashboard_header.php'; ?>

    <?php if (isset($success)): ?>
        <div class="modern-alert modern-alert-success">
            <div class="modern-alert-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="modern-alert-content">
                <p class="modern-alert-message"><?php echo $success; ?></p>
            </div>
            <button type="button" class="modern-alert-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="modern-alert modern-alert-danger">
            <div class="modern-alert-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="modern-alert-content">
                <p class="modern-alert-message"><?php echo $error; ?></p>
            </div>
            <button type="button" class="modern-alert-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- User Stats -->
    <div class="modern-dashboard-stats">
        <div class="modern-stat-card">
            <div class="modern-stat-card-icon admin-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="modern-stat-card-content">
                <h3 class="modern-stat-card-number"><?php echo count(array_filter($users, fn($u) => $u['user_type'] === 'customer')); ?></h3>
                <p class="modern-stat-card-label">Customers</p>
            </div>
        </div>

        <div class="modern-stat-card">
            <div class="modern-stat-card-icon admin-success">
                <i class="fas fa-store"></i>
            </div>
            <div class="modern-stat-card-content">
                <h3 class="modern-stat-card-number"><?php echo count(array_filter($users, fn($u) => $u['user_type'] === 'supplier')); ?></h3>
                <p class="modern-stat-card-label">Suppliers</p>
            </div>
        </div>

        <div class="modern-stat-card">
            <div class="modern-stat-card-icon admin-info">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="modern-stat-card-content">
                <h3 class="modern-stat-card-number"><?php echo count(array_filter($users, fn($u) => $u['status'] === 'active')); ?></h3>
                <p class="modern-stat-card-label">Active Users</p>
            </div>
        </div>

        <div class="modern-stat-card">
            <div class="modern-stat-card-icon admin-warning">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="modern-stat-card-content">
                <h3 class="modern-stat-card-number"><?php echo count($users); ?></h3>
                <p class="modern-stat-card-label">Total Users</p>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="modern-card">
        <div class="modern-card-header">
            <h5 class="modern-card-title">Users List</h5>
        </div>
        <div class="modern-card-body">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td><?php echo $user_item['id']; ?></td>
                                <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                <td>
                                    <span class="modern-badge modern-badge-<?php echo $user_item['user_type'] === 'admin' ? 'danger' : ($user_item['user_type'] === 'supplier' ? 'success' : 'primary'); ?>">
                                        <?php echo ucfirst($user_item['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="modern-badge modern-badge-<?php echo $user_item['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($user_item['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user_item['created_at'])); ?></td>
                                <td>
                                    <div class="modern-btn-group">
                                        <?php if ($user_item['status'] === 'active'): ?>
                                            <button class="modern-btn modern-btn-warning modern-btn-sm" onclick="updateUserStatus(<?php echo $user_item['id']; ?>, 'deactivate')">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="modern-btn modern-btn-success modern-btn-sm" onclick="updateUserStatus(<?php echo $user_item['id']; ?>, 'activate')">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="modern-btn modern-btn-danger modern-btn-sm" onclick="deleteUser(<?php echo $user_item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
function updateUserStatus(userId, action) {
    if (confirm('Are you sure you want to ' + action + ' this user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="${action}">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

