<?php
require_once __DIR__ . '/config/config.php';

// Check if user is logged in and redirect accordingly
if (is_logged_in()) {
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
            redirect(base_url('auth/login.php'));
    }
} else {
    // Show landing page for non-logged in users
    include __DIR__ . '/views/landing.php';
}
?>
