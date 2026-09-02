<?php

/**
 * Admin Authentication Check
 * This file ensures all admin pages properly verify 2FA completion
 */

// Make sure we have access to the config
if (!defined('BASE_URL')) {
    // Try to include the config file
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user has completed 2FA by verifying all required session variables are set
$has_completed_2fa = isset($_SESSION['user_id']) && 
                     isset($_SESSION['user_type']) && 
                     isset($_SESSION['user_email']) &&
                     $_SESSION['user_type'] === 'admin';

if (!$has_completed_2fa) {
    // Make sure we have the base_url function
    if (!function_exists('base_url')) {
        function base_url($path = '') {
            // Fallback implementation - Production URL
            $baseUrl = 'https://fingerling.shop/';
            return $baseUrl . ltrim($path, '/');
        }
    }
    
    // Check if user is in 2FA process
    if (isset($_SESSION['2fa_user_id']) && isset($_SESSION['2fa_user_type']) && $_SESSION['2fa_user_type'] === 'admin') {
        // User is in 2FA process, redirect to 2FA verification
        header('Location: ' . base_url('auth/verify_2fa.php'));
        exit();
    } else {
        // User is not logged in at all, redirect to login
        header('Location: ' . base_url('auth/login.php'));
        exit();
    }
}

// Additional check to ensure user is actually an admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}
?>