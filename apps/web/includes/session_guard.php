<?php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

// Get current URL path
$current_url = $_SERVER['REQUEST_URI'] ?? '';
$current_path = parse_url($current_url, PHP_URL_PATH);

// Define public paths (no login/OTP required)
$public_paths = [
    '/auth/login.php',
    '/auth/register.php',
    '/auth/forgot-password.php',
    '/auth/reset-password.php',
    '/auth/logout.php',
    '/auth/verify-email.php',
    '/auth/verify_otp.php',
    '/auth/send_otp.php',
    '/auth/resend_otp.php',
    '/auth/2fa-verify.php',
    '/auth/verify_2fa.php',
    '/auth/google-login.php',
    '/auth/google-callback.php',
    '/auth/report_suspension.php', // Suspended users MUST access this
];

// Check if current path is public
$is_public_path = false;
foreach ($public_paths as $public_path) {
    if ($current_path && strpos($current_path, $public_path) !== false) {
        $is_public_path = true;
        break;
    }
}

// Static assets
$static_extensions = ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.css', '.js', '.ico', '.woff', '.woff2', '.ttf', '.eot'];
$request_extension = strtolower(pathinfo($current_path, PATHINFO_EXTENSION));
if (in_array('.' . $request_extension, $static_extensions)) {
    $is_public_path = true;
}

// API & uploads
$api_paths = ['/api/', '/webhook/', '/vendor/', '/uploads/', '/business_permits/'];
foreach ($api_paths as $api_path) {
    if ($current_path && strpos($current_path, $api_path) !== false) {
        $is_public_path = true;
        break;
    }
}

// ALLOW ALL PUBLIC PATHS — NO CHECKS
if ($is_public_path) {
    return;
}

// Pending login 2FA — user has passed password check but not OTP yet
if (isset($_SESSION['2fa_user_id']) && (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true)) {
    if (strpos($current_path, '/auth/verify_2fa.php') === false) {
        redirect(base_url('auth/verify_2fa.php'));
    }
    exit;
}

// ————————————————————————————————————————
// FROM HERE: User must be logged in + OTP verified
// ————————————————————————————————————————

// Not logged in → go to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    if (strpos($current_path, '/auth/login.php') === false) {
        redirect(base_url('auth/login.php'));
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$session_id = $_SESSION['session_id'];

// Global session timeout
$sessionTimeout = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 3600;
if ($sessionTimeout > 0) {
    $lastActivity = $_SESSION['last_activity'] ?? time();
    if ((time() - $lastActivity) >= $sessionTimeout) {
        session_unset();
        session_destroy();
        redirect(base_url('auth/login.php?timeout=1'));
    }
    $_SESSION['last_activity'] = time();
}

// Validate session in DB
$valid = $database->fetch(
    "SELECT id FROM users WHERE id = ? AND current_session_id = ?",
    [$user_id, $session_id]
);

if (!$valid) {
    $user = new User($database);
    $user->logout();
    $_SESSION['error'] = 'Session expired or logged in from another device.';
    if (strpos($current_path, '/auth/login.php') === false) {
        redirect(base_url('auth/login.php'));
    }
    exit;
}

// ————————————————————————————————————————
// SUSPENDED SUPPLIER CHECK (THE FIX)
// ————————————————————————————————————————

// Only check suspension AFTER login + OTP flow is complete
// Allow access to verify_2fa.php and report_suspension.php even if suspended
$allowed_for_suspended = [
    '/auth/verify_2fa.php',
    '/auth/report_suspension.php',
    '/auth/login.php',
    '/auth/logout.php'
];

$is_allowed_for_suspended = false;
foreach ($allowed_for_suspended as $path) {
    if (strpos($current_path, $path) !== false) {
        $is_allowed_for_suspended = true;
        break;
    }
}

// If user is supplier AND suspended → block unless on allowed page
if ($_SESSION['user_type'] ?? '' === 'supplier') {
    $supplier = $database->fetch(
        "SELECT status, suspension_reason FROM suppliers WHERE user_id = ?",
        [$user_id]
    );

    if ($supplier && $supplier['status'] === 'suspended' && !$is_allowed_for_suspended) {
        // Set session data for report_suspension.php
        $_SESSION['suspended_supplier_id'] = $database->fetch("SELECT id FROM suppliers WHERE user_id = ?", [$user_id])['id'] ?? null;
        $_SESSION['suspension_reason'] = $supplier['suspension_reason'] ?? 'Your account has been suspended.';

        redirect(base_url('auth/report_suspension.php'));
        exit;
    }
}

// ————————————————————————————————————————
// OTP VERIFICATION CHECK
// ————————————————————————————————————————

if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    $otp_pages = ['/auth/verify_2fa.php', '/auth/verify_otp.php'];
    $is_otp_page = false;
    foreach ($otp_pages as $page) {
        if (strpos($current_path, $page) !== false) {
            $is_otp_page = true;
            break;
        }
    }

    if (!$is_otp_page) {
        $_SESSION['redirect_after_otp'] = $current_url;
        redirect(base_url('auth/verify_2fa.php'));
        exit;
    }
}

// Clean expired sessions
try {
    $database->query("DELETE FROM user_sessions WHERE expires_at < NOW()");
} catch (Exception $e) {
    // Silent fail — not critical
}