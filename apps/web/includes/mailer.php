<?php

// PHPMailer-based mail sending helper for the platform
// Usage: send_app_email($to, $subject, $body, $isHtml=false)

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once PROJECT_ROOT . '/packages/phpmailer/src/PHPMailer.php';
require_once PROJECT_ROOT . '/packages/phpmailer/src/SMTP.php';
require_once PROJECT_ROOT . '/packages/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function _mail_normalize_password($password) {
    $password = trim((string) $password);
    // Gmail app passwords are often copied with spaces (e.g. "abcd efgh ijkl mnop").
    return preg_replace('/\s+/', '', $password);
}

function _mail_smtp_settings_from_db() {
    global $database;

    if (!isset($database)) {
        return [];
    }

    try {
        $rows = $database->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'"
        );
    } catch (Exception $e) {
        return [];
    }

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        return [];
    }

    return [
        'host' => $settings['smtp_host'] ?? 'smtp.gmail.com',
        'port' => (int) ($settings['smtp_port'] ?? 587),
        'username' => trim($settings['smtp_username']),
        'password' => _mail_normalize_password($settings['smtp_password']),
        'from_email' => trim($settings['smtp_from_email'] ?? $settings['smtp_username']),
        'from_name' => trim($settings['smtp_from_name'] ?? APP_NAME),
        'encryption' => strtolower($settings['smtp_encryption'] ?? 'tls'),
        'source' => 'database',
    ];
}

/**
 * Build SMTP configuration from .env constants, with optional DB fallback.
 */
function _mail_smtp_config($preferDatabase = false) {
    $envCfg = [
        'host' => defined('SMTP_HOST') ? SMTP_HOST : env('SMTP_HOST', 'smtp.gmail.com'),
        'port' => defined('SMTP_PORT') ? (int) SMTP_PORT : (int) env('SMTP_PORT', 587),
        'username' => trim((string) (defined('SMTP_USERNAME') ? SMTP_USERNAME : env('SMTP_USERNAME', ''))),
        'password' => _mail_normalize_password(defined('SMTP_PASSWORD') ? SMTP_PASSWORD : env('SMTP_PASSWORD', '')),
        'from_email' => trim((string) (defined('FROM_EMAIL') ? FROM_EMAIL : env('FROM_EMAIL', ''))),
        'from_name' => defined('FROM_NAME') ? FROM_NAME : env('FROM_NAME', APP_NAME),
        'encryption' => strtolower((string) env('SMTP_ENCRYPTION', 'tls')),
        'source' => 'env',
    ];

    if ($envCfg['from_email'] === '') {
        $envCfg['from_email'] = $envCfg['username'];
    }

    $dbCfg = _mail_smtp_settings_from_db();

    if ($preferDatabase && !empty($dbCfg)) {
        return $dbCfg;
    }

    if ($envCfg['username'] !== '' && $envCfg['password'] !== '') {
        return $envCfg;
    }

    if (!empty($dbCfg)) {
        return $dbCfg;
    }

    return $envCfg;
}

function _mail_send_with_config(array $cfg, $to, $subject, $body, $isHtml) {
    if ($cfg['username'] === '' || $cfg['password'] === '') {
        return [
            'success' => false,
            'error' => 'SMTP is not configured. Set SMTP_USERNAME and SMTP_PASSWORD in .env or admin email settings.',
        ];
    }

    if ($cfg['from_email'] === '') {
        $cfg['from_email'] = $cfg['username'];
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
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;

        $enc = strtolower($cfg['encryption']);
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'none') {
            $mail->SMTPSecure = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        return ['success' => true, 'error' => '', 'source' => $cfg['source'] ?? 'env'];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Mailer Error: ' . $mail->ErrorInfo,
            'source' => $cfg['source'] ?? 'env',
        ];
    }
}

/**
 * Send an application email using PHPMailer over SMTP.
 * Returns ['success' => bool, 'error' => string, 'source' => string]
 */
function send_app_email($to, $subject, $body, $isHtml = false) {
    $configs = [];

    $primary = _mail_smtp_config(false);
    $configs[] = $primary;

    $dbCfg = _mail_smtp_settings_from_db();
    if (!empty($dbCfg) && ($dbCfg['username'] !== ($primary['username'] ?? '') || $dbCfg['password'] !== ($primary['password'] ?? ''))) {
        $configs[] = $dbCfg;
    }

    $lastError = 'SMTP is not configured.';
    $seen = [];

    foreach ($configs as $cfg) {
        $key = ($cfg['username'] ?? '') . '|' . ($cfg['password'] ?? '') . '|' . ($cfg['host'] ?? '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $result = _mail_send_with_config($cfg, $to, $subject, $body, $isHtml);
        if (!empty($result['success'])) {
            return $result;
        }

        $lastError = $result['error'] ?? $lastError;
        error_log('SMTP send failed (' . ($cfg['source'] ?? 'unknown') . '): ' . $lastError);
    }

    return ['success' => false, 'error' => $lastError, 'source' => $primary['source'] ?? 'env'];
}

function mail_is_local_dev_mode() {
    $env = strtolower((string) env('APP_ENV', 'production'));
    $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);

    return $debug && in_array($env, ['development', 'local', 'dev'], true);
}
