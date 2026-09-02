<?php
require_once __DIR__ . '/../../config/config.php';

// Check user authentication and redirect appropriately
if (!is_logged_in()) {
    // Not logged in at all, redirect to login page
    redirect(base_url('auth/login.php'));
} else {
    // User is logged in, redirect to their dashboard
    $user_type = get_user_type();
    switch ($user_type) {
        case 'admin':
            redirect(base_url('admin/dashboard.php'));
            break;
        case 'supplier':
            redirect(base_url('supplier/dashboard.php'));
            break;
        case 'customer':
            redirect(base_url('customer/dashboard.php'));
            break;
        default:
            // If we can't determine user type, redirect to login
            redirect(base_url('auth/login.php'));
    }
}