<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../includes/mailer.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === HELPER: Safe Redirect ===
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}

// === HELPER: Base URL ===
if (!function_exists('base_url')) {
    function base_url($path = '') {
        if (defined('BASE_URL')) {
            return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
        }
        // Fallback: construct from current request
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'fingerling.shop';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        // Remove /auth from path if we're in auth directory
        $base = $protocol . $host . str_replace('/auth', '', $scriptDir);
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

// === SECURITY: Must be in 2FA flow ===
if (!isset($_SESSION['2fa_user_id']) || !isset($_SESSION['2fa_user_type'])) {
    redirect(base_url('auth/login.php'));
}

// === CRITICAL FIX: BLOCK SUSPENDED SUPPLIERS BEFORE OTP VERIFICATION ===
$user_id = $_SESSION['2fa_user_id'];
$user_type = $_SESSION['2fa_user_type'];

if ($user_type === 'supplier') {
    try {
        $stmt = $pdo->prepare("SELECT s.id, s.status, s.suspension_reason 
                               FROM suppliers s 
                               WHERE s.user_id = ?");
        $stmt->execute([$user_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($supplier && $supplier['status'] === 'suspended') {
            // Clear 2FA session to prevent loop
            unset(
                $_SESSION['2fa_user_id'],
                $_SESSION['2fa_user_type'],
                $_SESSION['2fa_email'],
                $_SESSION['2fa_remember'],
                $_SESSION['2fa_remember_token']
            );

            // Prepare suspension page
            $_SESSION['suspended_supplier_id'] = $supplier['id'];
            $_SESSION['suspension_reason'] = $supplier['suspension_reason'] ?? 'Your account has been suspended by the administrator.';

            redirect(base_url('auth/report_suspension.php'));
        }
    } catch (Exception $e) {
        error_log("Suspension check failed in verify_2fa.php: " . $e->getMessage());
        // Continue — don't break login if DB fails
    }
}

// Now safe to proceed
$error = '';
$success = '';
$email = $_SESSION['2fa_email'] ?? '';

// === RESEND OTP ===
if (isset($_POST['resend_otp'])) {
    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $sent = false;

    try {
        $sql = "INSERT INTO user_2fa_otps (user_id, otp_code, expires_at, created_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), expires_at = VALUES(expires_at), created_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $otp, $expires_at]);
        $sent = true;
    } catch (Exception $e) {
        try {
            $sql = "INSERT INTO user_otps (user_id, otp, expires_at, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE otp = VALUES(otp), expires_at = VALUES(expires_at), created_at = NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $otp, $expires_at]);
            $sent = true;
        } catch (Exception $e2) {
            error_log("Resend OTP failed: " . $e2->getMessage());
        }
    }

    if ($sent) {
        $subject = "Your OTP Code";
        $message = "Your OTP code is: $otp\n\nThis code will expire in 15 minutes.";
        if (send_app_email($email, $subject, $message)) {
            $success = 'New OTP has been sent to your email.';
        } else {
            $error = 'Failed to send email. Please try again.';
        }
    } else {
        $error = 'Failed to generate OTP. Please try again.';
    }
}

// === VERIFY OTP ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['otp']) || isset($_POST['otp_digits']))) {
    $otp = '';
    if (isset($_POST['otp'])) {
        $otp = trim($_POST['otp']);
    } elseif (isset($_POST['otp_digits']) && is_array($_POST['otp_digits'])) {
        $otp = trim(implode('', array_map('trim', $_POST['otp_digits'])));
    }

    if (empty($otp) || strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'Please enter the 6-digit OTP code.';
    } else {
        $otp_record = null;

        try {
            $sql = "SELECT * FROM user_2fa_otps WHERE user_id = ? AND otp_code = ? AND expires_at > NOW()";
            $otp_record = $database->fetch($sql, [$user_id, $otp]);
        } catch (Exception $e) {
            try {
                $sql = "SELECT * FROM user_otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()";
                $otp_record = $database->fetch($sql, [$user_id, $otp]);
            } catch (Exception $e2) {
                error_log("OTP check failed: " . $e2->getMessage());
            }
        }

        if ($otp_record) {
            // === OTP VALID: Complete Login ===
            try {
                $delete_sql = "DELETE FROM user_2fa_otps WHERE user_id = ?";
                $stmt = $pdo->prepare($delete_sql);
                $stmt->execute([$user_id]);
            } catch (Exception $e) {
                try {
                    $delete_sql = "DELETE FROM user_otps WHERE user_id = ?";
                    $stmt = $pdo->prepare($delete_sql);
                    $stmt->execute([$user_id]);
                } catch (Exception $e2) {}
            }

            // Set authenticated session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_email'] = $email;

            // Set OTP verified flag for security
            $_SESSION['otp_verified'] = true;
            $_SESSION['otp_verified_at'] = time();
            
            // Regenerate session ID after OTP verification for security
            session_regenerate_id(true);

            if (!empty($_SESSION['2fa_remember']) && !empty($_SESSION['2fa_remember_token'])) {
                $token = $_SESSION['2fa_remember_token'];
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);

                if (isset($pdo) && $pdo instanceof PDO) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                        $stmt->execute([$user_id, $token]);
                    } catch (Exception $rememberException) {
                        error_log('Remember token save failed: ' . $rememberException->getMessage());
                    }
                }
            }

            if (isset($_SESSION['session_takeover']) && $_SESSION['session_takeover']) {
                $_SESSION['success'] = 'Someone was already using this account. You have now taken over the session.';
                unset($_SESSION['session_takeover']);
            }

            // Clear 2FA temp data
            unset(
                $_SESSION['2fa_user_id'],
                $_SESSION['2fa_user_type'],
                $_SESSION['2fa_email'],
                $_SESSION['2fa_remember'],
                $_SESSION['2fa_remember_token']
            );

            // Redirect to dashboard
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
                default:
                    redirect(base_url());
            }
        } else {
            $error = 'Invalid or expired OTP code.';
        }
    }
}

$page_title = 'Verify OTP';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #2563eb;
            --brand-secondary: #0ea5e9;
            --brand-dark: #111827;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, rgba(37,99,235,0.25), transparent 45%),
                        radial-gradient(circle at bottom, rgba(14,165,233,0.25), transparent 40%),
                        #0f172a;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .otp-shell {
            width: 100%;
            max-width: 480px;
        }
        .otp-card {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 70px rgba(15, 23, 42, 0.4);
            position: relative;
            overflow: hidden;
        }
        .otp-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, rgba(37,99,235,0.35), rgba(14,165,233,0.35));
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
        }
        .otp-card h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }
        .otp-card p {
            color: #cbd5f5;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .otp-inputs {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.75rem;
            margin: 1.5rem 0;
        }
        .otp-input {
            width: 100%;
            aspect-ratio: 1 / 1.25;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.5);
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }
        .otp-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
            transform: translateY(-2px);
        }
        .btn-primary-gradient {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.95rem 1rem;
            border-radius: 14px;
            width: 100%;
            transition: transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.35);
        }
        .btn-primary-gradient:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.45);
        }
        .btn-ghost {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: #e2e8f0;
            background: transparent;
            padding: 0.85rem;
            font-weight: 500;
            transition: background .2s ease, color .2s ease, border-color .2s ease;
        }
        .btn-ghost:hover {
            background: rgba(148, 163, 184, 0.1);
            border-color: rgba(148, 163, 184, 0.6);
        }
        .alert {
            border-radius: 14px;
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
        }
        .error-alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fecaca;
        }
        .success-alert {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #bbf7d0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #cbd5f5;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 1.5rem;
            transition: color .2s ease, gap .2s ease;
        }
        .back-link:hover {
            color: #fff;
            gap: 0.55rem;
        }
        @media (max-width: 575px) {
            .otp-card {
                padding: 2rem 1.5rem;
            }
            .otp-inputs {
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="otp-shell">
        <div class="otp-card">
            <h1>Verify your account</h1>
            <p>Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>

            <?php if ($error): ?>
                <div class="alert error-alert mb-3"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success-alert mb-3"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="otp-inputs" data-otp-inputs>
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            class="otp-input"
                            name="otp_digits[]"
                            data-index="<?php echo $i; ?>"
                            autocomplete="one-time-code"
                            aria-label="OTP digit <?php echo $i + 1; ?>"
                            required
                        >
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn-primary-gradient">Verify OTP</button>
            </form>

            <div class="mt-3">
                <form method="POST">
                    <button type="submit" name="resend_otp" class="btn-ghost">Resend OTP</button>
                </form>
            </div>

            <a href="<?php echo base_url('auth/login.php'); ?>" class="back-link">
                <span>←</span>
                <span>Back to login</span>
            </a>
        </div>
    </div>

    <script>
        const otpInputs = document.querySelectorAll('[data-otp-inputs] .otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                const value = event.target.value.replace(/[^0-9]/g, '');
                event.target.value = value;
                if (value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !event.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    otpInputs[index - 1].focus();
                }
                if (event.key === 'ArrowRight' && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text');
                const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
                digits.split('').forEach((digit, idx) => {
                    if (otpInputs[index + idx]) {
                        otpInputs[index + idx].value = digit;
                    }
                });
                const nextIndex = Math.min(index + digits.length, otpInputs.length - 1);
                otpInputs[nextIndex].focus();
            });
        });

        otpInputs[0]?.focus();
    </script>
</body>
</html>