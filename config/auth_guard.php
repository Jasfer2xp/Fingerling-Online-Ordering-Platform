<?php

// config/auth_guard.php
// MUST be included at the very top of EVERY protected page
// (customer/dashboard.php, supplier/orders.php, admin/users.php, etc.)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php'; // Ensures BASE_URL, database, etc. are loaded

// === 1. Not logged in at all? → Login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    redirect(base_url('auth/login.php'));
    exit;
}

// === 2. Logged in but 2FA not completed? → Force 2FA page
if (isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] === true) {
    // Allow only these pages during 2FA flow
    $allowed_during_2fa = [
        'auth/2fa-verify.php',
        'auth/resend-otp.php',
        'auth/logout.php',
        'assets/',
        'api/',
    ];

    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base_path = parse_url(base_url(), PHP_URL_PATH);

    // Normalize paths
    $relative_path = ltrim(str_replace($base_path, '', $current_path), '/');

    $allowed = false;
    foreach ($allowed_during_2fa as $allowed_path) {
        if (strpos($relative_path, $allowed_path) === 0) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        // Force redirect to 2FA verification
        redirect(base_url('auth/2fa-verify.php'));
        exit;
    }
}

// === 3. Session takeover protection (from your existing config.php)
if (isset($_SESSION['session_id'])) {
    $valid = $database->fetch(
        "SELECT id FROM users WHERE id = ? AND current_session_id = ?",
        [$_SESSION['user_id'], $_SESSION['session_id']]
    );

    if (!$valid) {
        // Old session detected → show takeover alert
        $_SESSION = [];
        session_destroy();
        redirect(base_url('auth/login.php?takeover=1'));
        exit;
    }
}

// === OPTIONAL: Clean expired OTPs (performance)
$database->query("DELETE FROM user_2fa_otps WHERE expires_at < NOW()");

// All checks passed → user is fully authenticated
// Page continues loading normally
?>