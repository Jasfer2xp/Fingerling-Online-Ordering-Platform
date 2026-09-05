<?php
require_once '../config/config.php';
require_once '../includes/login_otp.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['2fa_user_id']) || !isset($_SESSION['2fa_user_type'])) {
    redirect(base_url('auth/login.php'));
}

$user_id = (int) $_SESSION['2fa_user_id'];
$user_type = $_SESSION['2fa_user_type'];
$error = '';
$success = '';
$email = $_SESSION['2fa_email'] ?? '';
$devOtpDisplay = $_SESSION['dev_otp_display'] ?? null;
$devOtpMailError = $_SESSION['dev_otp_mail_error'] ?? '';

if ($user_type === 'supplier') {
    try {
        $supplier = $database->fetch(
            "SELECT s.id, s.status, s.suspension_reason
             FROM suppliers s
             WHERE s.user_id = ?",
            [$user_id]
        );

        if ($supplier && $supplier['status'] === 'suspended') {
            clear_2fa_session_flags();
            $_SESSION['suspended_supplier_id'] = $supplier['id'];
            $_SESSION['suspension_reason'] = $supplier['suspension_reason'] ?? 'Your account has been suspended by the administrator.';
            redirect(base_url('auth/report_suspension.php'));
        }
    } catch (Exception $e) {
        error_log("Suspension check failed in verify_2fa.php: " . $e->getMessage());
    }
}

$resendState = login_otp_resend_allowed();
$can_resend = $resendState['allowed'];
$resend_remaining = $resendState['remaining'];

if (isset($_POST['resend_otp'])) {
    if (!$can_resend) {
        $error = 'Please wait ' . $resend_remaining . ' seconds before requesting another code.';
    } else {
        $sendResult = issue_login_otp($database, $user_id, $email);
        if (!empty($sendResult['success'])) {
            mark_login_otp_sent();
            $can_resend = false;
            $resend_remaining = LOGIN_OTP_RESEND_COOLDOWN;
            if (!empty($sendResult['dev_fallback']) && !empty($sendResult['otp_plain'])) {
                $_SESSION['dev_otp_display'] = $sendResult['otp_plain'];
                $_SESSION['dev_otp_mail_error'] = $sendResult['mail_error'] ?? '';
                $devOtpDisplay = $sendResult['otp_plain'];
                $devOtpMailError = $_SESSION['dev_otp_mail_error'];
                $success = 'Email delivery failed in local dev mode. Use the code shown below.';
            } else {
                unset($_SESSION['dev_otp_display'], $_SESSION['dev_otp_mail_error']);
                $devOtpDisplay = null;
                $success = 'A new OTP has been sent to your email.';
            }
        } else {
            $error = 'Failed to send email: ' . ($sendResult['error'] ?? 'Please try again.');
            error_log('2FA resend failed: ' . ($sendResult['error'] ?? 'unknown'));
        }
    }
}

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
        $verifyResult = verify_login_otp($database, $user_id, $otp);

        if (!empty($verifyResult['success'])) {
            $user = new User($database);
            if (!$user->completeLogin($user_id)) {
                clear_2fa_session_flags();
                $error = 'Login failed. Please try again.';
            } else {
                $_SESSION['otp_verified'] = true;
                $_SESSION['otp_verified_at'] = time();
                $_SESSION['user_email'] = $email;
                session_regenerate_id(true);

                if (!empty($_SESSION['2fa_remember']) && !empty($_SESSION['2fa_remember_token'])) {
                    $token = $_SESSION['2fa_remember_token'];
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);

                    try {
                        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                        $database->query("DELETE FROM remember_tokens WHERE user_id = ?", [$user_id]);
                        $database->query("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)", [$user_id, $token, $expiresAt]);
                    } catch (Exception $rememberException) {
                        error_log('Remember token save failed: ' . $rememberException->getMessage());
                    }
                }

                if (isset($_SESSION['session_takeover']) && $_SESSION['session_takeover']) {
                    $_SESSION['success'] = 'Someone was already using this account. You have now taken over the session.';
                    unset($_SESSION['session_takeover']);
                }

                clear_2fa_session_flags();
                unset($_SESSION['dev_otp_display'], $_SESSION['dev_otp_mail_error']);

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
            }
        } else {
            $error = $verifyResult['error'] ?? 'Invalid or expired OTP code.';
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
        .btn-ghost:hover:not(:disabled) {
            background: rgba(148, 163, 184, 0.1);
            border-color: rgba(148, 163, 184, 0.6);
        }
        .btn-ghost:disabled {
            opacity: 0.55;
            cursor: not-allowed;
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
        .dev-otp-banner {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fde68a;
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.92rem;
        }
        .dev-otp-code {
            display: inline-block;
            margin-top: 0.35rem;
            font-size: 1.5rem;
            letter-spacing: 0.35rem;
            font-weight: 700;
            color: #fff;
        }
        .countdown {
            color: #fbbf24;
            font-weight: 600;
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

            <?php if ($devOtpDisplay): ?>
                <div class="dev-otp-banner">
                    Local development mode: email could not be sent
                    <?php if ($devOtpMailError): ?>
                        (<?php echo htmlspecialchars($devOtpMailError); ?>)
                    <?php endif; ?>.
                    <br>Use this OTP to continue:
                    <div class="dev-otp-code"><?php echo htmlspecialchars($devOtpDisplay); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error-alert mb-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success-alert mb-3"><?php echo htmlspecialchars($success); ?></div>
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
                    <button type="submit" name="resend_otp" class="btn-ghost" <?php echo $can_resend ? '' : 'disabled'; ?>>
                        Resend OTP
                        <?php if (!$can_resend): ?>
                            <span class="countdown">(<?php echo (int) $resend_remaining; ?>s)</span>
                        <?php endif; ?>
                    </button>
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

        <?php if (!$can_resend): ?>
        (function () {
            let seconds = <?php echo (int) $resend_remaining; ?>;
            const countdownEl = document.querySelector('.countdown');
            const resendBtn = countdownEl ? countdownEl.closest('button') : null;
            if (!countdownEl || !resendBtn) {
                return;
            }

            const timer = setInterval(function () {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    countdownEl.remove();
                    return;
                }
                countdownEl.textContent = '(' + seconds + 's)';
            }, 1000);
        })();
        <?php endif; ?>
    </script>
</body>
</html>
