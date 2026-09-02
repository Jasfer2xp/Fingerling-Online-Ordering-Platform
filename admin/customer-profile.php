<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id <= 0) {
    $_SESSION['error'] = 'Invalid customer ID.';
    redirect(base_url('admin/customers.php'));
}

// Ensure customer-details receives a sanitized ID value
$_GET['id'] = (string)$customer_id;

// Re-use the existing rich profile layout from customer-details.php
require_once __DIR__ . '/customer-details.php';
exit;

