<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $admin->addCategory($_POST['name'], $_POST['description']);
                $success = 'Category added successfully.';
                break;
            case 'edit':
                $admin->updateCategory($_POST['category_id'], $_POST['name'], $_POST['description']);
                $success = 'Category updated successfully.';
                break;
            case 'archive':
                $admin->archiveCategory($_POST['category_id']);
                $success = 'Category archived successfully.';
                break;
            case 'toggle_status':
                $admin->toggleCategoryStatus($_POST['category_id']);
                $success = 'Category status updated successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get categories
$categories = $admin->getAllCategories();

$page_title = 'Category Management';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
    <i class="fas fa-plus"></i> Add Category
</button>';

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
                    <i class="fas fa-tags"></i>
                </div>
                Category Management
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <?php echo $dashboard_actions; ?>
        </div>
    </div>

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

        <!-- Categories Stats -->
        <div class="modern-dashboard-stats">
            <div class="modern-stat-card">
                <div class="modern-stat-card-icon admin-primary">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="modern-stat-card-content">
                    <h3 class="modern-stat-card-number"><?php echo count($categories); ?></h3>
                    <p class="modern-stat-card-label">Total Categories</p>
                </div>
            </div>

            <div class="modern-stat-card">
                <div class="modern-stat-card-icon admin-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modern-stat-card-content">
                    <h3 class="modern-stat-card-number"><?php echo count(array_filter($categories, fn($c) => $c['status'] === 'active')); ?></h3>
                    <p class="modern-stat-card-label">Active Categories</p>
                </div>
            </div>
 
            <div class="modern-stat-card">
                <div class="modern-stat-card-icon admin-warning">
                    <i class="fas fa-pause-circle"></i>
                </div>
                <div class="modern-stat-card-content">
                    <h3 class="modern-stat-card-number"><?php echo count(array_filter($categories, fn($c) => $c['status'] === 'inactive')); ?></h3>
                    <p class="modern-stat-card-label">Inactive Categories</p>
                </div>
            </div>

            <div class="modern-stat-card">
                <div class="modern-stat-card-icon admin-info">
                    <i class="fas fa-fish"></i>
                </div>
                <div class="modern-stat-card-content">
                    <h3 class="modern-stat-card-number"><?php echo array_sum(array_column($categories, 'species_count')); ?></h3>
                    <p class="modern-stat-card-label">Total Species</p>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h5 class="modern-card-title">Categories List</h5>
            </div>
            <div class="modern-card-body">
                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Species Count</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="modern-badge modern-badge-info">
                                            <?php echo $category['species_count'] ?? 0; ?> species
                                        </span>
                                    </td>
                                    <td>
                                        <span class="modern-badge modern-badge-<?php echo $category['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <div class="modern-btn-group">
                                            <button class="modern-btn modern-btn-outline-primary modern-btn-sm" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="modern-btn modern-btn-outline-<?php echo $category['status'] === 'active' ? 'warning' : 'success'; ?> modern-btn-sm"
                                                    onclick="toggleStatus(<?php echo $category['id']; ?>)">
                                                <i class="fas fa-<?php echo $category['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                            <button class="modern-btn modern-btn-outline-danger modern-btn-sm" onclick="deleteCategory(<?php echo $category['id']; ?>)">
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    
                    <div class="mb-3">
                        <label for="editName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('editCategoryId').value = category.id;
    document.getElementById('editName').value = category.name;
    document.getElementById('editDescription').value = category.description;
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

function toggleStatus(categoryId) {
    if (confirm('Are you sure you want to change the status of this category?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="category_id" value="${categoryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function archiveCategory(categoryId) {
    if (confirm('Are you sure you want to archive this category? It will be moved to archived status.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="category_id" value="${categoryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
