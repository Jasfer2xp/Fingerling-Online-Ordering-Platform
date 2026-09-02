<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Redirect if already logged in
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
    }
}

// Check if Google OAuth is configured
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    $_SESSION['error'] = 'Google OAuth is not configured.';
    redirect(base_url('auth/login.php'));
}

// Get role from query parameter
$role = $_GET['role'] ?? 'customer';
if (!in_array($role, ['customer', 'supplier'])) {
    $role = 'customer';
}

// Generate state parameter for security
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;

// Build OAuth URL with role parameter
$google_oauth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'scope' => 'email profile',
    'response_type' => 'code',
    'state' => $state . '|role:' . $role, // Include role in state parameter
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

// Redirect to Google OAuth
redirect($google_oauth_url);
?>