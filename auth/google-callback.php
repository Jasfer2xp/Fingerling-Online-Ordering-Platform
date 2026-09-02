<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check for errors
if (isset($_GET['error'])) {
    $_SESSION['error'] = 'Google authentication failed: ' . $_GET['error'];
    redirect(base_url('auth/login.php'));
}

// Verify state parameter
$state = $_GET['state'] ?? '';
$expected_state = $_SESSION['oauth_state'] ?? '';

// Extract role from state parameter
$role = 'customer'; // default role
if (strpos($state, '|role:') !== false) {
    $parts = explode('|role:', $state);
    $state = $parts[0];
    $role = $parts[1] ?? 'customer';
}

if (!in_array($role, ['customer', 'supplier'])) {
    $role = 'customer';
}

if (!isset($_GET['state']) || $state !== $expected_state) {
    $_SESSION['error'] = 'Invalid OAuth state parameter.';
    redirect(base_url('auth/login.php'));
}

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['error'] = 'Authorization code not received.';
    redirect(base_url('auth/login.php'));
}

try {
    // Exchange code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $token_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to get access token');
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['access_token'])) {
        throw new Exception('Access token not found in response');
    }
    
    // Get user info from Google
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_data['access_token'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $user_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to get user information');
    }
    
    $user_data = json_decode($user_response, true);
    
    if (!isset($user_data['email']) || !isset($user_data['id'])) {
        throw new Exception('Required user data not found');
    }
    
    // Login or register user
    $user = new User($database);
    $result = $user->googleLogin($user_data['id'], $user_data['email'], $user_data['name'] ?? '', $role);
    
    if ($result) {
        // Redirect based on user type
        switch ($result['user_type']) {
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
                redirect(base_url());
        }
    } else {
        // New user - redirect to registration step 3 with the selected role
        $_SESSION['pending_google_registration'] = [
            'google_id' => $user_data['id'],
            'email' => $user_data['email'],
            'name' => $user_data['name'] ?? '',
            'role' => $role
        ];
        
        redirect(base_url("auth/register.php?type={$role}&step=3"));
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Google authentication failed: ' . $e->getMessage();
    redirect(base_url('auth/login.php'));
}

// Clean up session
unset($_SESSION['oauth_state']);
?>