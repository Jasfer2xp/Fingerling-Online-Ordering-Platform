<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $admin->addAnnouncement($_POST['title'], $_POST['content'], $_POST['type'], $_POST['target_audience']);
                $success = 'Announcement created successfully.';
                break;
            case 'edit':
                $admin->updateAnnouncement($_POST['announcement_id'], $_POST['title'], $_POST['content'], $_POST['type'], $_POST['target_audience']);
                $success = 'Announcement updated successfully.';
                break;
            case 'archive':
                $admin->archiveAnnouncement($_POST['announcement_id']);
                $success = 'Announcement archived successfully.';
                break;
            case 'toggle_status':
                $admin->toggleAnnouncementStatus($_POST['announcement_id']);
                $success = 'Announcement status updated successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get announcements
$announcements = $admin->getAllAnnouncements();

$page_title = 'Announcements';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Announcements</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                            <i class="fas fa-plus"></i> New Announcement
                        </button>
                    </div>
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

            <!-- Announcement Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count($announcements); ?></h4>
                                    <p class="mb-0">Total Announcements</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bullhorn fa-2x"></i>
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
                                    <h4><?php echo count(array_filter($announcements, fn($a) => $a['status'] === 'active')); ?></h4>
                                    <p class="mb-0">Active</p>
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
                                    <h4><?php echo count(array_filter($announcements, fn($a) => $a['type'] === 'urgent')); ?></h4>
                                    <p class="mb-0">Urgent</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                                    <h4><?php echo count(array_filter($announcements, fn($a) => strtotime($a['created_at']) > strtotime('-7 days'))); ?></h4>
                                    <p class="mb-0">This Week</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-week fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcements List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Announcements List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <h5>No Announcements</h5>
                            <p class="text-muted">Create your first announcement to communicate with users.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                                <i class="fas fa-plus"></i> Create Announcement
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card border-left-<?php echo $announcement['type'] === 'urgent' ? 'danger' : ($announcement['type'] === 'info' ? 'info' : 'primary'); ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?php echo $announcement['id']; ?>)">
                                                            <i class="fas fa-<?php echo $announcement['status'] === 'active' ? 'pause' : 'play'; ?>"></i> 
                                                            <?php echo $announcement['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-warning" href="#" onclick="archiveAnnouncement(<?php echo $announcement['id']; ?>)">
                                                            <i class="fas fa-archive"></i> Archive
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $announcement['type'] === 'urgent' ? 'danger' : ($announcement['type'] === 'info' ? 'info' : 'primary'); ?>">
                                                    <?php echo ucfirst($announcement['type']); ?>
                                                </span>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $announcement['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($announcement['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <p class="text-muted mb-2">
                                                <?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . (strlen($announcement['content']) > 150 ? '...' : ''); ?>
                                            </p>
                                            
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </main>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" name="content" id="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" name="type" id="type" required>
                                    <option value="info">Information</option>
                                    <option value="warning">Warning</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_audience" class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience" id="target_audience" required>
                                    <option value="all">All Users</option>
                                    <option value="customers">Customers Only</option>
                                    <option value="suppliers">Suppliers Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="announcement_id" id="editAnnouncementId">
                    
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" id="editTitle" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editContent" class="form-label">Content</label>
                        <textarea class="form-control" name="content" id="editContent" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editType" class="form-label">Type</label>
                                <select class="form-select" name="type" id="editType" required>
                                    <option value="info">Information</option>
                                    <option value="warning">Warning</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editTargetAudience" class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience" id="editTargetAudience" required>
                                    <option value="all">All Users</option>
                                    <option value="customers">Customers Only</option>
                                    <option value="suppliers">Suppliers Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAnnouncement(announcement) {
    document.getElementById('editAnnouncementId').value = announcement.id;
    document.getElementById('editTitle').value = announcement.title;
    document.getElementById('editContent').value = announcement.content;
    document.getElementById('editType').value = announcement.type;
    document.getElementById('editTargetAudience').value = announcement.target_audience;
    
    const modal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
    modal.show();
}

function toggleStatus(announcementId) {
    if (confirm('Are you sure you want to change the status of this announcement?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="announcement_id" value="${announcementId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function archiveAnnouncement(announcementId) {
    if (confirm('Are you sure you want to archive this announcement? It will be moved to archived status.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="announcement_id" value="${announcementId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.border-left-primary { border-left: 4px solid #007bff !important; }
.border-left-danger { border-left: 4px solid #dc3545 !important; }
.border-left-info { border-left: 4px solid #17a2b8 !important; }
</style>
