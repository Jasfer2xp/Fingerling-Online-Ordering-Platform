<?php
require_once '../config/config.php';

// Check if user is already logged in but needs OTP verification
if (isset($_SESSION['user_id']) && isset($_SESSION['session_id']) && (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true)) {
    // User is logged in but OTP not verified - use logged-in session
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'] ?? 'customer';
    $user_data = $database->fetch("SELECT email FROM users WHERE id = ?", [$user_id]);
    $user_email = $user_data['email'] ?? 'your email';
    $is_from_login = false;
} elseif (isset($_SESSION['2fa_pending']) && isset($_SESSION['2fa_user_id'])) {
    // User is coming from login with 2FA pending
    $user_id = $_SESSION['2fa_user_id'];
    $user_type = $_SESSION['user_type_temp'] ?? 'customer';
    $user_email = $database->fetch("SELECT email FROM users WHERE id = ?", [$user_id])['email'] ?? 'your email';
    $is_from_login = true;
} else {
    // Not logged in and not in 2FA flow - redirect to login
    redirect(base_url('auth/login.php'));
    exit();
}

$error   = '';
$success = '';

// === RESEND OTP COOLDOWN (30 seconds) ===
$resend_cooldown = 30;
$can_resend      = true;
$remaining_time  = 0;

if (isset($_SESSION['otp_sent_at'])) {
    $elapsed = time() - $_SESSION['otp_sent_at'];
    if ($elapsed < $resend_cooldown) {
        $can_resend     = false;
        $remaining_time = $resend_cooldown - $elapsed;
    }
}

// Auto-send OTP on page load if not already sent recently (for already logged-in users)
if ($is_from_login === false && (!isset($_SESSION['otp_sent_at']) || (time() - $_SESSION['otp_sent_at']) > 60)) {
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    $database->query(
        "INSERT INTO user_2fa_otps (user_id, otp_code, expires_at, created_at) 
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?, created_at = NOW()",
        [$user_id, $hashed_otp, $expires_at, $hashed_otp, $expires_at]
    );

    require_once __DIR__ . '/../includes/mailer.php';

    $email_body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 30px; background: #f9fafb; border-radius: 16px; text-align: center; line-height: 1.6;">
        <h1 style="color: #1e40af; font-size: 28px; margin-bottom: 20px;">Your Verification Code</h1>
        <p style="font-size: 16px; color: #374151;">Hello! Use this code to verify your access:</p>
        <div style="background: #3b82f6; color: white; font-size: 36px; letter-spacing: 10px; padding: 20px; border-radius: 12px; margin: 30px 0; font-weight: bold;">
            ' . chunk_split($otp, 1, ' ') . '
        </div>
        <p style="color: #dc2626; font-weight: bold;">This code expires in 5 minutes.</p>
        <hr style="margin: 40px 0; border: 1px dashed #ddd;">
        <small style="color: #6b7280;">' . APP_NAME . ' • Secure Access</small>
    </div>';

    $sent = send_app_email($user_email, 'Your Verification Code', $email_body, true);

    if ($sent['success']) {
        $_SESSION['otp_sent_at'] = time();
    }
}

// === RESEND OTP ===
if (isset($_POST['resend_otp']) && $can_resend) {
    $otp        = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    $database->query(
        "INSERT INTO user_2fa_otps (user_id, otp_code, expires_at, created_at) 
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?, created_at = NOW()",
        [$user_id, $hashed_otp, $expires_at, $hashed_otp, $expires_at]
    );

    require_once __DIR__ . '/../includes/mailer.php';

    $email_body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 30px; background: #f9fafb; border-radius: 16px; text-align: center; line-height: 1.6;">
        <h1 style="color: #1e40af; font-size: 28px; margin-bottom: 20px;">' . ($is_from_login ? 'Your Login Code' : 'Your Verification Code') . '</h1>
        <p style="font-size: 16px; color: #374151;">Hello! Use this code to ' . ($is_from_login ? 'complete your login' : 'verify your access') . ':</p>
        <div style="background: #3b82f6; color: white; font-size: 36px; letter-spacing: 10px; padding: 20px; border-radius: 12px; margin: 30px 0; font-weight: bold;">
            ' . chunk_split($otp, 1, ' ') . '
        </div>
        <p style="color: #dc2626; font-weight: bold;">This code expires in 5 minutes.</p>
        <hr style="margin: 40px 0; border: 1px dashed #ddd;">
        <small style="color: #6b7280;">' . APP_NAME . ' • ' . ($is_from_login ? 'Secure Login' : 'Secure Access') . '</small>
    </div>';

    $sent = send_app_email($user_email, 'Your Verification Code', $email_body, true);

    if ($sent['success']) {
        $_SESSION['otp_sent_at'] = time();
        $success = 'A new code has been sent to your email.';
    } else {
        $error = 'Failed to send code. Please try again.';
        error_log("2FA resend failed: " . ($sent['error'] ?? 'unknown'));
    }
}

// === VERIFY OTP ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp_input = trim($_POST['otp']);

    if (!preg_match('/^\d{6}$/', $otp_input)) {
        $error = 'Please enter a valid 6-digit code.';
    } else {
        $record = $database->fetch(
            "SELECT otp_code FROM user_2fa_otps 
             WHERE user_id = ? AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$user_id]
        );

        if ($record && password_verify($otp_input, $record['otp_code'])) {
            // OTP SUCCESS → FULL LOGIN or VERIFICATION
            $database->query("DELETE FROM user_2fa_otps WHERE user_id = ?", [$user_id]);

            // Only create new session if coming from login (not already logged in)
            if ($is_from_login) {
                $user_obj = new User($database);
                $user_data = $user_obj->getUserById($user_id);

                if ($user_data) {
                    // This kills any old session and creates a fresh one (single session enforcement)
                    // Create session using User class method or direct session setup
                    $session_id = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                    
                    $database->query(
                        "UPDATE users SET current_session_id = ? WHERE id = ?",
                        [$session_id, $user_id]
                    );
                    
                    $database->query(
                        "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE expires_at = ?",
                        [$session_id, $user_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires_at, $expires_at]
                    );
                    
                    $_SESSION['session_id'] = $session_id;
                }

                // Final session setup
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = $user_type;
            }

            // Remember Me (if requested during login)
            if ($is_from_login && !empty($_SESSION['2fa_remember'])) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 86400), '/', '', true, true);
                $database->query(
                    "INSERT INTO remember_tokens (user_id, token, expires_at) 
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                     ON DUPLICATE KEY UPDATE token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)",
                    [$user_id, $token, $token]
                );
            }

            // Set OTP verified flag for security
            $_SESSION['otp_verified'] = true;
            $_SESSION['otp_verified_at'] = time();
            
            // Regenerate session ID after OTP verification for security
            session_regenerate_id(true);

            // Clean up 2FA session flags (only if coming from login)
            if ($is_from_login) {
                unset(
                    $_SESSION['2fa_pending'],
                    $_SESSION['2fa_user_id'],
                    $_SESSION['user_type_temp'],
                    $_SESSION['otp_sent_at'],
                    $_SESSION['2fa_remember']
                );
            } else {
                // Just clear OTP sent time if already logged in
                unset($_SESSION['otp_sent_at']);
            }

            // Redirect to intended destination or dashboard
            if (isset($_SESSION['redirect_after_otp'])) {
                $redirect_url = $_SESSION['redirect_after_otp'];
                unset($_SESSION['redirect_after_otp']);
                redirect($redirect_url);
                exit();
            }

            // FINAL REDIRECT based on user type
            switch ($user_type) {
                case 'admin':
                    redirect(base_url('admin/dashboard.php'));
                    break;
                case 'supplier':
                    redirect(base_url('supplier/dashboard.php'));
                    break;
                case 'customer':
                default:
                    redirect(base_url('customer/dashboard.php'));
                    break;
            }
            exit();
        } else {
            $error = 'Invalid or expired code.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA • <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #3b82f6; --accent: #f59e0b; }
        body { margin:0; height:100vh; background:#0f172a; display:flex; align-items:center; justify-content:center; }
        .auth-card { max-width:420px; width:100%; border:0; border-radius:16px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.4); }
        .card-header { background:var(--primary); color:white; padding:2rem; text-align:center; }
        .otp-input { width:100%; padding:1.2rem; font-size:2.2rem; letter-spacing:12px; text-align:center; border:2px solid #e5e7eb; border-radius:12px; font-weight:bold; }
        .btn-accent { background:linear-gradient(135deg,var(--accent),#d97706); border:0; color:#111; font-weight:800; padding:1rem; box-shadow:0 10px 20px rgba(245,158,11,0.3); }
        .btn-resend { background:transparent; color:#94a3b8; border:1px solid #475569; }
        .btn-resend:not(.disabled):hover { background:#1e293b; color:white; }
        .countdown { color:var(--accent); font-weight:bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-11 col-md-8 col-lg-5">

            <div class="card auth-card">
                <div class="card-header">
                    <h2 class="mb-0 fw-bold">
                        <i class="fas fa-shield-alt me-2"></i> Two-Factor Authentication
                    </h2>
                </div>
                <div class="card-body p-5">

                    <?php if ($success): ?>
                        <div class="alert alert-success text-center mb-4"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center mb-4"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <p class="text-center text-muted mb-4">
                        Enter the 6-digit code sent to<br>
                        <strong><?php echo htmlspecialchars($user_email); ?></strong>
                    </p>

                    <form method="POST" class="text-center mb-4">
                        <input type="text" name="otp" class="otp-input mb-4" 
                               placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="off" required autofocus>
                        <button type="submit" class="btn btn-accent w-100">
                            <i class="fas fa-lock me-2"></i> Verify & Login
                        </button>
                    </form>

                    <div class="text-center">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="resend_otp" 
                                    class="btn btn-resend <?php echo $can_resend ? '' : 'disabled'; ?>"
                                    <?php echo $can_resend ? '' : 'disabled'; ?>>
                                <i class="fas fa-redo me-1"></i> Resend Code
                                <?php if (!$can_resend): ?>
                                    <span class="countdown">(<?php echo $remaining_time; ?>s)</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?php echo base_url('auth/login.php'); ?>" class="text-muted small">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Auto-format & submit on 6 digits
document.querySelector('input[name="otp"]').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0,6);
    if (this.value.length === 6) this.form.submit();
});

// Live countdown
<?php if (!$can_resend): ?>
setInterval(function() {
    let seconds = <?php echo $remaining_time; ?>;
    const el = document.querySelector('.countdown');
    const btn = el.closest('button');
    const timer = setInterval(function() {
        seconds--;
        el.textContent = '(' + seconds + 's)';
        if (seconds <= 0) {
            clearInterval(timer);
            btn.classList.remove('disabled');
            btn.disabled = false;
            el.remove();
        }
    }, 1000);
}, 100);
<?php endif; ?>
</script>

</body>
</html>