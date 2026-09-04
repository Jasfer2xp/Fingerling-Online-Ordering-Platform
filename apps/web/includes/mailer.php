<?php

// PHPMailer-based mail sending helper for the platform
// Usage: send_app_email($to, $subject, $body, $isHtml=false)

if (!defined('APP_NAME')) {
    // Ensure config is loaded if this file is included directly
    require_once __DIR__ . '/../config/config.php';
}

// Include PHPMailer classes
require_once PROJECT_ROOT . '/packages/phpmailer/src/PHPMailer.php';
require_once PROJECT_ROOT . '/packages/phpmailer/src/SMTP.php';
require_once PROJECT_ROOT . '/packages/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Build SMTP configuration from environment-backed constants only.
 */
function _mail_smtp_config() {
    $host = defined('SMTP_HOST') ? SMTP_HOST : env('SMTP_HOST', 'smtp.gmail.com');
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : (int) env('SMTP_PORT', 587);
    $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : env('SMTP_USERNAME', '');
    $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : env('SMTP_PASSWORD', '');
    $from_email = defined('FROM_EMAIL') ? FROM_EMAIL : env('FROM_EMAIL', '');
    $from_name = defined('FROM_NAME') ? FROM_NAME : env('FROM_NAME', APP_NAME);
    $enc = env('SMTP_ENCRYPTION', 'tls');

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
