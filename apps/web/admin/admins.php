<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        try {
            $admin_data = [
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'permissions' => $_POST['permissions'] ?? []
            ];
            
            $admin->addAdmin($admin_data);
            $message = "Admin added successfully!";
        } catch (Exception $e) {
            $error = "Error adding admin: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_admin'])) {
        try {
            $admin_data = [
                'id' => $_POST['admin_id'],
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'status' => $_POST['status'],
                'permissions' => $_POST['permissions'] ?? []
            ];
            
            if (!empty($_POST['password'])) {
                $admin_data['password'] = $_POST['password'];
            }
            
            $admin->updateAdmin($admin_data);
            $message = "Admin updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating admin: " . $e->getMessage();
        }
    } elseif (isset($_POST['archive_admin'])) {
        try {
            $admin->archiveAdmin($_POST['admin_id']);
            $message = "Admin archived successfully!";
        } catch (Exception $e) {
            $error = "Error archiving admin: " . $e->getMessage();
        }
    } elseif (isset($_POST['restore_admin'])) {
        try {
            $admin->restoreAdmin($_POST['admin_id']);
            $message = "Admin restored successfully!";
        } catch (Exception $e) {
            $error = "Error restoring admin: " . $e->getMessage();
        }
    }
}

// Get all admins
$admins = $admin->getAllAdmins();

// Available permissions
$available_permissions = [
    'manage_users' => 'Manage Users',
    'manage_suppliers' => 'Manage Suppliers',
    'manage_orders' => 'Manage Orders',
    'manage_products' => 'Manage Products',
    'manage_payments' => 'Manage Payments',
    'view_reports' => 'View Reports',
    'manage_settings' => 'Manage Settings',
    'manage_content' => 'Manage Content',
    'system_backup' => 'System Backup'
];

$page_title = 'Admin Management';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
    <i class="fas fa-plus"></i> Add Admin
</button>';

include '../includes/modern_admin_header.php';
?>

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
                    <i class="fas fa-user-shield"></i>
                </div>
                Admin Management
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <button type="button" class="modern-btn modern-btn-primary modern-btn-sm" data-bs-toggle="modal"
                data-bs-target="#addAdminModal">
                <i class="fas fa-plus"></i> Add New Admin
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="modern-alert modern-alert-success">
            <div class="modern-alert-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="modern-alert-content">
                <p class="modern-alert-message"><?php echo htmlspecialchars($message); ?></p>
            </div>
            <button type="button" class="modern-alert-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="modern-alert modern-alert-danger">
            <div class="modern-alert-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="modern-alert-content">
                <p class="modern-alert-message"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <button type="button" class="modern-alert-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

                <!-- Admins Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="adminsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin_user): ?>
                                    <tr>
                                        <td><?php echo $admin_user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($admin_user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($admin_user['email']); ?></td>
                                        <td>N/A</td>
                                        <td>
                                            <?php if ($admin_user['status'] === 'archived'): ?>
                                                <span class="badge bg-warning">Archived</span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $admin_user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($admin_user['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Never</td>
                                        <td><?php echo date('M d, Y', strtotime($admin_user['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin_user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($admin_user['id'] != get_user_id()): ?>
                                                <?php if ($admin_user['status'] === 'archived'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success"
                                                            onclick="restoreAdmin(<?php echo $admin_user['id']; ?>, '<?php echo htmlspecialchars($admin_user['full_name']); ?>')">
                                                        <i class="fas fa-undo"></i> Restore
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                                            onclick="archiveAdmin(<?php echo $admin_user['id']; ?>, '<?php echo htmlspecialchars($admin_user['full_name']); ?>')">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                <?php foreach ($available_permissions as $key => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editAdminForm">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" id="edit_phone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" name="password" id="edit_password">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row" id="edit_permissions">
                                <?php foreach ($available_permissions as $key => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="edit_perm_<?php echo $key; ?>">
                                        <label class="form-check-label" for="edit_perm_<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_admin" class="btn btn-primary">Update Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="archiveAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive admin <strong id="archive_admin_name"></strong>?</p>
                    <p class="text-warning">This admin will be moved to archived status and can be restored later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="admin_id" id="archive_admin_id">
                        <button type="submit" name="archive_admin" class="btn btn-warning">Archive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal fade" id="restoreAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore admin <strong id="restore_admin_name"></strong>?</p>
                    <p class="text-success">This admin will be restored to active status.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="admin_id" id="restore_admin_id">
                        <button type="submit" name="restore_admin" class="btn btn-success">Restore</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    $(document).ready(function() {
        $('#adminsTable').DataTable({
            responsive: true,
            order: [[0, 'desc']]
        });

        // Fix aria-hidden accessibility issue
        $('.modal').on('hidden.bs.modal', function () {
            $(this).removeAttr('aria-hidden');
        });

        $('.modal').on('show.bs.modal', function () {
            $(this).removeAttr('aria-hidden');
        });
    });

    function editAdmin(adminData) {
        document.getElementById('edit_admin_id').value = adminData.id;
        document.getElementById('edit_full_name').value = adminData.full_name;
        document.getElementById('edit_email').value = adminData.email;
        document.getElementById('edit_status').value = adminData.status;

        // Clear all permission checkboxes
        document.querySelectorAll('#edit_permissions input[type="checkbox"]').forEach(cb => cb.checked = false);

        // Check permissions if they exist
        if (adminData.permissions) {
            try {
                let permissions;
                if (typeof adminData.permissions === 'string') {
                    permissions = JSON.parse(adminData.permissions);
                } else {
                    permissions = adminData.permissions;
                }

                if (Array.isArray(permissions)) {
                    permissions.forEach(perm => {
                        const checkbox = document.getElementById('edit_perm_' + perm);
                        if (checkbox) checkbox.checked = true;
                    });
                }
            } catch (e) {
                console.warn('Error parsing permissions:', e);
            }
        }

        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    }

    function archiveAdmin(adminId, adminName) {
        document.getElementById('archive_admin_id').value = adminId;
        document.getElementById('archive_admin_name').textContent = adminName;
        new bootstrap.Modal(document.getElementById('archiveAdminModal')).show();
    }

    function restoreAdmin(adminId, adminName) {
        document.getElementById('restore_admin_id').value = adminId;
        document.getElementById('restore_admin_name').textContent = adminName;
        new bootstrap.Modal(document.getElementById('restoreAdminModal')).show();
    }
</script>

