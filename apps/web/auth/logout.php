<?php
require_once '../config/config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (is_logged_in()) {
    try {
        $user = new User($database);
        $user->logout();
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log('Logout error: ' . $e->getMessage());
    }
}

// Clear OTP verification flag
unset($_SESSION['otp_verified']);
unset($_SESSION['otp_verified_at']);

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session (only if it's active)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Clear any authentication cookies
setcookie('remember_token', '', time() - 3600, '/');

// Redirect to home page
redirect(base_url());
?>
