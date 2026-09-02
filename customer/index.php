<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php'; // OTP protection

// Check if user has completed 2FA
// If 2FA is completed, redirect to dashboard
// If in 2FA process, redirect to 2FA verification
// If not logged in at all, redirect to login
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer') {
    // User has completed 2FA, redirect to dashboard
    redirect(base_url('customer/dashboard.php'));
} else if (isset($_SESSION['2fa_user_id']) && isset($_SESSION['2fa_user_type']) && $_SESSION['2fa_user_type'] === 'customer') {
    // User is in 2FA process, redirect to 2FA verification
    redirect(base_url('auth/verify_2fa.php'));
} else {
    // User is not logged in at all, redirect to login
    redirect(base_url('auth/login.php'));
}
?>