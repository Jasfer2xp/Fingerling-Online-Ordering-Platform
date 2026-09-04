<?php
// Start session early
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Load environment variables from .env file
 */
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/helpers.php';
load_env();

/**
 * Application Configuration
 * Fingerling Online Ordering Platform System
 */

// Set Timezone to Manila
date_default_timezone_set('Asia/Manila');

// Error reporting - SET TO 0 IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', RUNTIME_PATH . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('Asia/Manila');

// Application settings
define('APP_NAME', 'Fingerling Online Ordering Platform');
define('APP_VERSION', '1.0.0');

// Base URL (defaults to production domain unless APP_URL is provided)
$configuredAppUrl = env('APP_URL');
$defaultProductionUrl = 'https://fingerling.shop/'; // Corrected: Root domain
$baseUrl = !empty($configuredAppUrl)
    ? rtrim($configuredAppUrl, '/') . '/'
    : $defaultProductionUrl;
define('BASE_URL', $baseUrl);
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Google OAuth Configuration
// OAuth credentials must only be supplied by the environment/.env file.
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', rtrim(BASE_URL, '/') . '/auth/google-callback.php');
define('GOOGLE_MAPS_API_KEY', env('GOOGLE_MAPS_API_KEY', ''));
define('GMAIL_VERIFY_STRICT', filter_var(env('GMAIL_VERIFY_STRICT', true), FILTER_VALIDATE_BOOL));
define('ESIGIL_API_KEY', env('ESIGIL_API_KEY', ''));

// Security settings
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

// Email settings
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('FROM_EMAIL', env('FROM_EMAIL', ''));
define('FROM_NAME', env('FROM_NAME', 'Fingerling Online Ordering Platform'));

// PayPal Configuration
define('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID', ''));
define('PAYPAL_CLIENT_SECRET', env('PAYPAL_CLIENT_SECRET', ''));
define('PAYPAL_MODE', env('PAYPAL_MODE', 'live'));

// Payment credentials must only be supplied by the environment/.env file.
define('XENDIT_API_KEY', env('XENDIT_API_KEY', ''));
define('XENDIT_WEBHOOK_TOKEN', env('XENDIT_WEBHOOK_TOKEN', ''));
define('XENDIT_BASE_URL', 'https://api.xendit.co');  // ← THIS WAS MISSING BEFORE!
define('XENDIT_API_VERSION', '2.0');
define('XENDIT_TRANSACTION_FEE_PERCENTAGE', 2.576); // 2.576%

// File & Pagination
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);
define('ITEMS_PER_PAGE', 12);

// Helper Functions
function time_ago($datetime) {
    // Ensure Manila timezone is set
    date_default_timezone_set('Asia/Manila');
    
    // Parse the datetime - timestamps in DB are already in Manila time
    // Create DateTime object assuming Manila timezone
    try {
        $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    } catch (Exception $e) {
        // Fallback to strtotime if DateTime fails
        $time = strtotime($datetime);
        $current = time();
        $diff = $current - $time;
        if ($diff < 60) return 'Just now';
        elseif ($diff < 3600) return floor($diff / 60) . ' minute' . (floor($diff / 60) > 1 ? 's' : '') . ' ago';
        elseif ($diff < 86400) return floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
        elseif ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
        else return date('M j, Y', $time);
    }
    
    // Get current time in Manila timezone
    $current = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $diff = $current->getTimestamp() - $dt->getTimestamp();
    
    if ($diff < 60) return 'Just now';
    elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }
    elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    else {
        return $dt->format('M j, Y');
    }
}

function columnExists($table, $column) {
    global $database;
    try {
        $sql = "SHOW COLUMNS FROM `$table` LIKE ?";
        $result = $database->fetch($sql, [$column]);
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

function base_url($path = '') { 
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'); 
}
function asset_url($path = '') { 
    return BASE_URL . 'assets/' . ltrim($path, '/'); 
}
function upload_url($path = '') { 
    return BASE_URL . 'uploads/' . ltrim($path, '/'); 
}

function redirect($url) { 
    header('Location: ' . $url); 
    exit(); 
}

function is_logged_in() {
    return isset($_SESSION['user_id'])
        && isset($_SESSION['session_id'])
        && isset($_SESSION['otp_verified'])
        && $_SESSION['otp_verified'] === true;
}
function get_user_type() { return $_SESSION['user_type'] ?? null; }
function get_user_id() { return $_SESSION['user_id'] ?? null; }
function get_customer_id() { return $_SESSION['customer_id'] ?? null; }

function csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function sanitize_input($data) {
    return $data === null ? '' : htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) { 
    return '₱' . number_format((float)$amount, 2); 
}
function format_date($date, $format = 'M d, Y') { 
    return $date ? date($format, strtotime($date)) : ''; 
}
function generate_order_number() { 
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)); 
}

// Include database
require_once __DIR__ . '/database.php';

// centralized timeout configuration
if (!defined('ORDER_TIMEOUT_INTERVAL')) {
    define('ORDER_TIMEOUT_INTERVAL', '+2 minutes');
}

if (!defined('ORDER_TIMEOUT_LABEL')) {
    define('ORDER_TIMEOUT_LABEL', '2 minutes');
}

if (!defined('DELIVERY_CONFIRMATION_TIMEOUT_HOURS')) {
    define('DELIVERY_CONFIRMATION_TIMEOUT_HOURS', 24);
}

// Autoload classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// SINGLE SESSION ENFORCEMENT - FINAL WORKING VERSION
if (isset($_SESSION['user_id']) && isset($_SESSION['session_id'])) {
    $user_id    = $_SESSION['user_id'];
    $session_id = $_SESSION['session_id'];

    $valid = $database->fetch(
        "SELECT id FROM users WHERE id = ? AND current_session_id = ?",
        [$user_id, $session_id]
    );

    if (!$valid) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        error_log("OLD SESSION TERMINATED - User ID: $user_id | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $database->query("DELETE FROM user_sessions WHERE expires_at < NOW()");

        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Session Terminated</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                body { background: linear-gradient(135deg, #ff6b6b, #ee5a52); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; font-family: system-ui; }
                .card { max-width: 500px; border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
                .card-body { background: white; padding: 3rem; text-align: center; }
                .icon { font-size: 4.5rem; color: #dc3545; margin-bottom: 1.5rem; }
                h1 { color: #dc3545; font-weight: 700; }
                .btn-login { background: #dc3545; border: none; padding: 0.8rem 2rem; border-radius: 50px; font-weight: 600; }
                .btn-login:hover { background: #c82333; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle icon"></i>
                    <h1>Account Logged In Elsewhere</h1>
                    <p class="lead mb-4">
                        Your account was just logged in from another device.<br>
                        <strong>This session has been terminated for security.</strong>
                    </p>
                    <p>Redirecting to login in <strong id="countdown">5</strong> seconds...</p>
                    <a href="' . base_url('auth/login.php') . '" class="btn btn-login text-white">
                        Go to Login Now
                    </a>
                </div>
            </div>

            <script>
                let sec = 5;
                const el = document.getElementById("countdown");
                const timer = setInterval(() => {
                    sec--;
                    el.textContent = sec;
                    if (sec <= 0) {
                        clearInterval(timer);
                        window.location.href = "' . base_url('auth/login.php') . '";
                    }
                }, 1000);
            </script>
        </body>
        </html>';
        exit();
    }

    // Clean expired sessions
    $database->query("DELETE FROM user_sessions WHERE expires_at < NOW()");
}
?>
