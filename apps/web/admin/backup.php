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

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $backup_file = $admin->createDatabaseBackup();
        $message = "Backup created successfully: " . basename($backup_file);
    } catch (Exception $e) {
        $error = "Error creating backup: " . $e->getMessage();
    }
}

// Handle backup restoration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    try {
        if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $admin->restoreDatabaseBackup($_FILES['backup_file']['tmp_name']);
            $message = "Database restored successfully!";
        } else {
            $error = "Error uploading backup file.";
        }
    } catch (Exception $e) {
        $error = "Error restoring backup: " . $e->getMessage();
    }
}

// Handle backup archiving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_backup'])) {
    try {
        $admin->archiveBackupFile($_POST['backup_filename']);
        $message = "Backup archived successfully!";
    } catch (Exception $e) {
        $error = "Error archiving backup: " . $e->getMessage();
    }
}

// Get existing backups
$backups = $admin->getBackupFiles();

$page_title = 'Database Backup & Restore';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" onclick="createBackup()">
    <i class="fas fa-plus"></i> Create Backup
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
                    <i class="fas fa-database"></i>
                </div>
                Database Backup & Restore
            </h1>
        </div>
        <div class="modern-dashboard-actions">

        </div>
    </div>

    <div class="container-fluid px-4">
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Create Backup -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-download me-2"></i>Create Backup
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Create a complete backup of your database.</p>
                                <form method="POST">
                                    <button type="submit" name="create_backup" class="btn btn-primary">
                                        <i class="fas fa-download me-2"></i>Create Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Restore Backup -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-upload me-2"></i>Restore Backup
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Upload and restore a database backup file.</p>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                                    </div>
                                    <button type="submit" name="restore_backup" class="btn btn-warning" 
                                            onclick="return confirm('This will overwrite your current database. Are you sure?')">
                                        <i class="fas fa-upload me-2"></i>Restore Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Existing Backups -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-archive me-2"></i>Existing Backups
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($backups)): ?>
                                <p class="text-muted">No backup files found.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                                <td><?php echo formatBytes($backup['size']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', $backup['modified']); ?></td>
                                                <td>
                                                    <a href="download-backup.php?file=<?php echo urlencode($backup['name']); ?>"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                                            onclick="archiveBackup('<?php echo htmlspecialchars($backup['name']); ?>')">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
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

                <!-- Backup Information -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Backup Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>What's included in backups:</h6>
                                        <ul>
                                            <li>All database tables and data</li>
                                            <li>Table structure and indexes</li>
                                            <li>User accounts and permissions</li>
                                            <li>System settings and configurations</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Best practices:</h6>
                                        <ul>
                                            <li>Create regular backups (daily/weekly)</li>
                                            <li>Store backups in multiple locations</li>
                                            <li>Test restore procedures regularly</li>
                                            <li>Keep backups for at least 30 days</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Archive Backup Modal -->
    <div class="modal fade" id="archiveBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive the backup file <strong id="archive_backup_name"></strong>?</p>
                    <p class="text-warning">This backup will be moved to the archived folder and can be restored later if needed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="backup_filename" id="archive_backup_filename">
                        <button type="submit" name="archive_backup" class="btn btn-warning">Archive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function archiveBackup(filename) {
            document.getElementById('archive_backup_filename').value = filename;
            document.getElementById('archive_backup_name').textContent = filename;
            new bootstrap.Modal(document.getElementById('archiveBackupModal')).show();
        }
    </script>

<!-- Modern JavaScript -->
<script src="../assets/js/modern-sidebar.js"></script>

</body>
</html>