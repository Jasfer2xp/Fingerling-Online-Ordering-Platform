<?php

/**
 * Login 2FA OTP helpers — cryptographically secure generation,
 * hashed storage, and server-side attempt limiting.
 */

if (!defined('LOGIN_OTP_MAX_ATTEMPTS')) {
    define('LOGIN_OTP_MAX_ATTEMPTS', 5);
}

if (!defined('LOGIN_OTP_EXPIRY_MINUTES')) {
    define('LOGIN_OTP_EXPIRY_MINUTES', 15);
}

if (!defined('LOGIN_OTP_RESEND_COOLDOWN')) {
    define('LOGIN_OTP_RESEND_COOLDOWN', 30);
}

/**
 * Ensure user_2fa_otps supports hashed codes and attempt tracking.
 */
function ensure_login_otp_table($database) {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $database->query(
            "CREATE TABLE IF NOT EXISTS user_2fa_otps (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                otp_code VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    } catch (Exception $e) {
        // Table likely already created in PostgreSQL / MySQL schema import
    }

    $ensured = true;
}

function generate_login_otp() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function store_login_otp($database, $user_id, $otp_plain) {
    ensure_login_otp_table($database);

    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + (LOGIN_OTP_EXPIRY_MINUTES * 60));

    // Universal delete-then-insert works identically on PostgreSQL and MySQL
    try {
        $database->query("DELETE FROM user_2fa_otps WHERE user_id = ?", [$user_id]);
    } catch (Exception $e) {}

    $database->query(
        "INSERT INTO user_2fa_otps (user_id, otp_code, expires_at, attempts, created_at)
         VALUES (?, ?, ?, 0, NOW())",
        [$user_id, $otp_hash, $expires_at]
    );

    return $otp_plain;
}

function clear_login_otp($database, $user_id) {
    ensure_login_otp_table($database);
    $database->query("DELETE FROM user_2fa_otps WHERE user_id = ?", [$user_id]);
}

function send_login_otp_email($email, $otp_plain) {
    if (!function_exists('send_app_email')) {
        require_once __DIR__ . '/mailer.php';
    }

    $subject = APP_NAME . ' Login Verification';
    $message = "Your login verification code is: {$otp_plain}\n\n"
        . "This code expires in " . LOGIN_OTP_EXPIRY_MINUTES . " minutes.\n\n"
        . "If you did not attempt to log in, please ignore this email.";

    return send_app_email($email, $subject, $message, false);
}

function issue_login_otp($database, $user_id, $email) {
    try {
        $otp_plain = generate_login_otp();
        store_login_otp($database, $user_id, $otp_plain);
    } catch (Exception $e) {
        error_log('Login OTP store failed: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Could not save OTP. Please try again.',
            'otp_plain' => null,
            'dev_fallback' => false,
        ];
    }

    $sendResult = send_login_otp_email($email, $otp_plain);
    if (!empty($sendResult['success'])) {
        return array_merge($sendResult, [
            'otp_plain' => null,
            'dev_fallback' => false,
        ]);
    }

    if (mail_is_local_dev_mode()) {
        error_log('Login OTP dev fallback for user ' . $user_id . ': ' . $otp_plain);
        return [
            'success' => true,
            'error' => '',
            'otp_plain' => $otp_plain,
            'dev_fallback' => true,
            'mail_error' => $sendResult['error'] ?? 'unknown',
        ];
    }

    clear_login_otp($database, $user_id);

    return array_merge($sendResult, [
        'otp_plain' => null,
        'dev_fallback' => false,
    ]);
}

function verify_login_otp($database, $user_id, $otp_input) {
    ensure_login_otp_table($database);

    $record = $database->fetch(
        "SELECT otp_code, attempts FROM user_2fa_otps WHERE user_id = ? AND expires_at > NOW()",
        [$user_id]
    );

    if (!$record) {
        return ['success' => false, 'error' => 'Invalid or expired OTP code.'];
    }

    $attempts = (int) ($record['attempts'] ?? 0);
    if ($attempts >= LOGIN_OTP_MAX_ATTEMPTS) {
        return [
            'success' => false,
            'error' => 'Too many failed attempts. Please request a new OTP.',
        ];
    }

    $stored = (string) $record['otp_code'];
    $valid = password_verify($otp_input, $stored);

    // One-time migration path for legacy plain-text OTP rows.
    if (!$valid && strlen($stored) === 6 && ctype_digit($stored) && hash_equals($stored, $otp_input)) {
        $valid = true;
    }

    if ($valid) {
        clear_login_otp($database, $user_id);
        return ['success' => true, 'error' => ''];
    }

    $database->query(
        "UPDATE user_2fa_otps SET attempts = attempts + 1 WHERE user_id = ?",
        [$user_id]
    );

    $remaining = LOGIN_OTP_MAX_ATTEMPTS - ($attempts + 1);
    if ($remaining <= 0) {
        return [
            'success' => false,
            'error' => 'Too many failed attempts. Please request a new OTP.',
        ];
    }

    return [
        'success' => false,
        'error' => 'Invalid OTP code. ' . $remaining . ' attempt(s) remaining.',
    ];
}

function login_otp_resend_allowed() {
    if (!isset($_SESSION['login_otp_sent_at'])) {
        return ['allowed' => true, 'remaining' => 0];
    }

    $elapsed = time() - (int) $_SESSION['login_otp_sent_at'];
    if ($elapsed >= LOGIN_OTP_RESEND_COOLDOWN) {
        return ['allowed' => true, 'remaining' => 0];
    }

    return [
        'allowed' => false,
        'remaining' => LOGIN_OTP_RESEND_COOLDOWN - $elapsed,
    ];
}

function mark_login_otp_sent() {
    $_SESSION['login_otp_sent_at'] = time();
}

function clear_pending_login_session() {
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_type'],
        $_SESSION['session_id'],
        $_SESSION['email'],
        $_SESSION['user_email'],
        $_SESSION['otp_verified'],
        $_SESSION['otp_verified_at'],
        $_SESSION['2fa_pending']
    );
}

function begin_pending_2fa_session($user_id, $user_type, $email, $remember = false) {
    clear_pending_login_session();

    $_SESSION['2fa_user_id'] = $user_id;
    $_SESSION['2fa_user_type'] = $user_type;
    $_SESSION['2fa_email'] = $email;
    $_SESSION['2fa_remember'] = $remember;

    if ($remember) {
        $_SESSION['2fa_remember_token'] = bin2hex(random_bytes(32));
    } else {
        unset($_SESSION['2fa_remember_token']);
    }
}

function clear_2fa_session_flags() {
    unset(
        $_SESSION['2fa_user_id'],
        $_SESSION['2fa_user_type'],
        $_SESSION['2fa_email'],
        $_SESSION['2fa_remember'],
        $_SESSION['2fa_remember_token'],
        $_SESSION['login_otp_sent_at']
    );
}
