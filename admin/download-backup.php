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

// Get the requested file
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    redirect(base_url('admin/backup.php?error=no_file'));
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

// Define backup directory
$backup_dir = '../backups/';
$file_path = $backup_dir . $filename;

// Check if file exists and is in the backup directory
if (!file_exists($file_path) || !is_file($file_path)) {
    redirect(base_url('admin/backup.php?error=file_not_found'));
}

// Verify file is a backup file (has .sql extension)
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    redirect(base_url('admin/backup.php?error=invalid_file'));
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Read and output file
readfile($file_path);
exit();
?>
