<?php

// PHPMailer-based mail sending helper for the platform
// Usage: send_app_email($to, $subject, $body, $isHtml=false)

if (!defined('APP_NAME')) {
    // Ensure config is loaded if this file is included directly
    require_once __DIR__ . '/../config/config.php';
}

// Include PHPMailer classes
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Retrieve a setting from DB settings table with simple static cache fallback
 */
function _mail_get_setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            if (isset($GLOBALS['database'])) {
                $rows = $GLOBALS['database']->fetchAll('SELECT setting_key, setting_value FROM settings');
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Throwable $e) {
            // ignore DB failures here
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Build SMTP configuration using constants, falling back to DB settings
 */
function _mail_smtp_config() {
    $host = defined('SMTP_HOST') && SMTP_HOST ? SMTP_HOST : _mail_get_setting('smtp_host', env('SMTP_HOST', 'smtp.gmail.com'));
    $port = defined('SMTP_PORT') && SMTP_PORT ? (int)SMTP_PORT : (int)_mail_get_setting('smtp_port', env('SMTP_PORT', 587));
    $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';

    if (!$username) { $username = _mail_get_setting('smtp_username', env('SMTP_USERNAME', '')); }
    if (!$password) { $password = _mail_get_setting('smtp_password', env('SMTP_PASSWORD', '')); }

    $from_email = defined('FROM_EMAIL') && FROM_EMAIL ? FROM_EMAIL : _mail_get_setting('from_email', env('FROM_EMAIL', 'noreply@example.com'));
    $from_name  = defined('FROM_NAME') && FROM_NAME ? FROM_NAME : _mail_get_setting('from_name', env('FROM_NAME', APP_NAME));

    $enc = _mail_get_setting('smtp_encryption', env('SMTP_ENCRYPTION', 'tls'));
    if (!$enc) { $enc = env('SMTP_ENCRYPTION', 'tls'); }

    return [
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'from_email' => $from_email,
        'from_name' => $from_name,
        'encryption' => $enc,
    ];
}

/**
 * Send an application email using PHPMailer over SMTP (Gmail-compatible)
 * Returns ['success' => bool, 'error' => string]
 */
function send_app_email($to, $subject, $body, $isHtml = false) {
    $cfg = _mail_smtp_config();

    if (empty($cfg['username']) || empty($cfg['password'])) {
        return [
            'success' => false,
            'error' => 'SMTP is not configured. Set SMTP_USERNAME and SMTP_PASSWORD in config or admin settings.'
        ];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->Port = $cfg['port'];
        $mail->CharSet = 'UTF-8';
        // Set timeouts to prevent blocking
        $mail->Timeout = 5;
        $mail->SMTPKeepAlive = false;

        // Encryption
        $enc = strtolower($cfg['encryption']);
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // implicit TLS/SSL
        } elseif ($enc === 'none') {
            $mail->SMTPSecure = false; // no encryption (not recommended)
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS
        }

        // Recipients
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return ['success' => true, 'error' => ''];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Mailer Error: ' . $mail->ErrorInfo
        ];
    }
}
?>