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

$page_title = 'Two-Factor Authentication';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-500: #3b82f6;
            --primary-700: #1d4ed8;
            --brand-accent: #f59e0b;
            --brand-accent-hover: #d97706;
            --brand-gradient: linear-gradient(135deg, #3b82f6, #1d4ed8);
            --muted: #9ca3af;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #ffffff;
        }

        /* Top Navbar matching Landing & Login */
        .hero-navbar {
            background: rgba(0, 0, 0, 0.35) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--brand-gradient);
            color: #fff;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
            flex-shrink: 0;
        }

        .brand-icon i { font-size: 18px; line-height: 1; }

        .navbar-brand {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff !important;
            font-size: 1.15rem;
            letter-spacing: -0.01em;
        }

        /* Hero Background matching Landing page */
        .auth-hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow: hidden;
            padding-top: 100px;
            padding-bottom: 50px;
        }

        .auth-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('<?php echo base_url('gif/fish.gif'); ?>');
            background-size: cover;
            background-position: center;
            opacity: 0.9;
        }

        .auth-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(2, 6, 23, 0.85) 100%);
        }

        .auth-hero > .container {
            position: relative;
            z-index: 1;
        }

        /* Clean Modern Card */
        .auth-card {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .shield-badge {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .auth-title {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }

        .auth-subtitle {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.75rem;
        }

        .email-pill {
            display: inline-block;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.88rem;
            word-break: break-all;
        }

        /* Clean OTP Inputs */
        .otp-inputs {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.65rem;
            margin-bottom: 1.5rem;
        }

        .otp-input {
            width: 100%;
            height: 58px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.95);
            color: #0f172a;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            transition: all 0.2s ease;
        }

        .otp-input:focus {
            outline: none;
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.35);
            transform: translateY(-2px);
        }

        /* Buttons matching Landing & Login */
        .btn-accent {
            background: linear-gradient(135deg, var(--brand-accent), var(--brand-accent-hover));
            border: 0;
            color: #111827;
            font-weight: 800;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            width: 100%;
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.25);
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .btn-accent:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
            color: #111827;
        }

        .btn-resend {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-resend:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        .btn-resend:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .countdown-badge {
            color: #fbbf24;
            font-weight: 700;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 1.5rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #ffffff;
        }

        /* Alerts */
        .alert-custom {
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
        }

        @media (max-width: 576px) {
            .auth-card {
                padding: 2rem 1.25rem;
            }
            .otp-input {
                height: 50px;
                font-size: 1.25rem;
            }
            .otp-inputs {
                gap: 0.4rem;
            }
        }
    </style>
</head>
<body>

    <!-- Fixed Glass Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top hero-navbar">
        <div class="container">
            <a class="navbar-brand" href="<?php echo base_url(); ?>">
                <span class="brand-icon"><i class="fas fa-fish"></i></span>
                <span><?php echo APP_NAME; ?></span>
            </a>
            <div class="d-flex align-items-center">
                <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Hero -->
    <section class="auth-hero">
        <div class="container">
            <div class="auth-card text-center">
                
                <div class="shield-badge">
                    <i class="fas fa-shield-halved"></i>
                </div>

                <h1 class="auth-title">Two-Factor Authentication</h1>
                <p class="auth-subtitle">
                    We sent a 6-digit security code to<br>
                    <span class="email-pill mt-1"><?php echo htmlspecialchars($email); ?></span>
                </p>

                <?php if ($devOtpDisplay): ?>
                    <div class="alert alert-warning mb-3 text-start">
                        <i class="fas fa-info-circle me-1"></i> <strong>Dev Mode OTP:</strong>
                        <div class="fs-4 fw-bold text-center my-1 letter-spacing-2"><?php echo htmlspecialchars($devOtpDisplay); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-custom alert-error mb-3 text-start">
                        <i class="fas fa-circle-exclamation flex-shrink-0"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert-custom alert-success mb-3 text-start">
                        <i class="fas fa-circle-check flex-shrink-0"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="otpForm">
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

                    <button type="submit" class="btn btn-accent mb-3">
                        <i class="fas fa-lock-open me-2"></i> Verify & Continue
                    </button>
                </form>

                <form method="POST" class="mb-2">
                    <button type="submit" name="resend_otp" class="btn btn-resend" <?php echo $can_resend ? '' : 'disabled'; ?>>
                        <i class="fas fa-redo-alt me-1"></i> Resend Code
                        <?php if (!$can_resend): ?>
                            <span class="countdown-badge ms-1">(<?php echo (int) $resend_remaining; ?>s)</span>
                        <?php endif; ?>
                    </button>
                </form>

                <a href="<?php echo base_url('auth/login.php'); ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Log in with another account
                </a>

            </div>
        </div>
    </section>

    <script>
        const otpInputs = document.querySelectorAll('[data-otp-inputs] .otp-input');
        const form = document.getElementById('otpForm');

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                const value = event.target.value.replace(/[^0-9]/g, '');
                event.target.value = value;
                if (value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Auto submit if all 6 digits entered
                if (index === otpInputs.length - 1 && value) {
                    let complete = true;
                    otpInputs.forEach(i => { if (!i.value) complete = false; });
                    if (complete) form.submit();
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
                
                if (digits.length === 6) {
                    form.submit();
                }
            });
        });

        // Focus first input on load
        otpInputs[0]?.focus();

        // Resend countdown timer
        <?php if (!$can_resend): ?>
        (function () {
            let seconds = <?php echo (int) $resend_remaining; ?>;
            const countdownEl = document.querySelector('.countdown-badge');
            const resendBtn = countdownEl ? countdownEl.closest('button') : null;
            if (!countdownEl || !resendBtn) return;

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
