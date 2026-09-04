<?php

require_once __DIR__ . '/../config/config.php';

// Legacy route — all login 2FA now uses verify_2fa.php.
if (isset($_SESSION['2fa_user_id'], $_SESSION['2fa_user_type'])) {
    redirect(base_url('auth/verify_2fa.php'));
}

if (isset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id'])) {
    $_SESSION['2fa_user_type'] = $_SESSION['user_type_temp'] ?? ($_SESSION['user_type'] ?? 'customer');
    unset($_SESSION['2fa_pending'], $_SESSION['user_type_temp']);
    redirect(base_url('auth/verify_2fa.php'));
}

redirect(base_url('auth/login.php'));
