<?php
require_once '../config/config.php';
require_once '../includes/mailer.php';
require_once '../includes/login_otp.php';

// Force start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: Full logout (clears everything)
function force_logout() {
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_type'],
        $_SESSION['session_id'],
        $_SESSION['otp_verified'],
        $_SESSION['otp_verified_at'],
        $_SESSION['2fa_user_id'],
        $_SESSION['2fa_user_type'],
        $_SESSION['2fa_email'],
        $_SESSION['2fa_remember'],
        $_SESSION['2fa_remember_token'],
        $_SESSION['suspended_supplier_id'],
        $_SESSION['suspension_reason']
    );
    session_regenerate_id(true);
}

// If already logged in → redirect immediately
if (is_logged_in()) {
    $user_type = get_user_type();
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
    }
}

$error = '';
$success = '';

if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

// Add support for flash messages
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $user = new User($database);
            $login_result = $user->login($email, $password, false);

            // === LOGIN FAILED (wrong credentials) ===
            if (!$login_result) {
                // Find user by plain text email only (no hashing)
                $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
                $found_user = $database->fetch($sql, [$email]);
                
                if ($found_user) {
                    // Check if password is correct but email not verified
                    if (password_verify($password, $found_user['password_hash'])) {
                        // Password is correct, check email verification
                        $sql = "SELECT verified_at FROM user_email_verifications WHERE user_id = ?";
                        $verification = $database->fetch($sql, [$found_user['id']]);
                        
                        if (!$verification || empty($verification['verified_at'])) {
                            // Email not verified - redirect to verify-email
                            $_SESSION['verify_user_id'] = $found_user['id'];
                            $_SESSION['verify_email'] = $email; // Plain text email
                            $_SESSION['verify_user_type'] = $found_user['user_type'];
                            $_SESSION['redirect_source'] = 'login';
                            redirect(base_url('auth/verify-email.php?redirect=login'));
                        }
                    }
                    
                    // Check if supplier is suspended
                    if ($found_user['user_type'] === 'supplier') {
                        $sql = "SELECT s.id AS supplier_id, s.status AS supplier_status, s.suspension_reason
                                FROM suppliers s
                                WHERE s.user_id = ? LIMIT 1";
                        $supplier_data = $database->fetch($sql, [$found_user['id']]);
                        
                        if ($supplier_data && $supplier_data['supplier_status'] === 'suspended') {
                            $supplier_id = $supplier_data['supplier_id'];
                            $appeal_check = $pdo->prepare("SELECT 1 FROM supplier_appeals WHERE supplier_id = ? LIMIT 1");
                            $appeal_check->execute([$supplier_id]);

                            if ($appeal_check->fetch()) {
                                $error = 'Your account is suspended. You have already submitted an appeal. It is under review. Please check your email for updates.';
                            } else {
                                $_SESSION['suspended_supplier_id'] = $supplier_id;
                                $_SESSION['suspension_reason'] = $supplier_data['suspension_reason'] ?? 'No reason provided.';
                                redirect(base_url('auth/report_suspension.php'));
                            }
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
            // === LOGIN SUCCESSFUL ===
            else {
                $result = $login_result;
                // Set session email with plain text email
                $_SESSION['email'] = $email;
                $requires_2fa = true;

                // === SUPPLIER: Check suspension + appeal ===
                if ($result['user_type'] === 'supplier') {
                    $stmt = $pdo->prepare("SELECT id, status, suspension_reason FROM suppliers WHERE user_id = ?");
                    $stmt->execute([$result['id']]);
                    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($supplier && $supplier['status'] === 'suspended') {
                        $supplier_id = $supplier['id'];

                        $appeal_check = $pdo->prepare("SELECT 1 FROM supplier_appeals WHERE supplier_id = ? LIMIT 1");
                        $appeal_check->execute([$supplier_id]);

                        if ($appeal_check->fetch()) {
                            // FORCE LOGOUT + SHOW MESSAGE
                            force_logout();
                            $error = 'Your account is suspended. You have already submitted an appeal. It is under review. Please check your email for updates.';
                            $requires_2fa = false;
                        } else {
                            $_SESSION['suspended_supplier_id'] = $supplier_id;
                            $_SESSION['suspension_reason'] = $supplier['suspension_reason'] ?? 'No reason provided.';
                            redirect(base_url('auth/report_suspension.php'));
                        }
                    }
                }

                // === PROFILE INCOMPLETE CHECK ===
                if ($requires_2fa && in_array($result['user_type'], ['supplier', 'customer'])) {
                    $profile = $user->getUserProfile($result['id']);
                    $required_fields = ['barangay', 'city', 'province'];
                    if ($result['user_type'] === 'supplier') {
                        $required_fields[] = 'business_name';
                    }

                    $missing = array_filter($required_fields, fn($field) => empty($profile[$field]));
                    if (!empty($missing)) {
                        $_SESSION['complete_registration_user_id'] = $result['id'];
                        $_SESSION['complete_registration_email'] = $email; // Use plain text email, not hashed
                        $error = 'Your account is not yet complete. <a href="'.base_url('auth/register.php?type='.$result['user_type'].'&step=3').'">Click here to complete your registration.</a>';
                        $requires_2fa = false;
                    }
                }

                // === SUPPLIER PENDING APPROVAL ===
                if ($requires_2fa && $result['user_type'] === 'supplier' && $result['status'] === 'pending') {
                    $error = 'Your supplier account is pending approval.';
                    $requires_2fa = false;
                }

                // === PROCEED TO 2FA ===
                if ($error === '' && $requires_2fa) {
                    $sendResult = issue_login_otp($database, $result['id'], $email);

                    if (!empty($sendResult['success'])) {
                        begin_pending_2fa_session($result['id'], $result['user_type'], $email, $remember);
                        mark_login_otp_sent();

                        if (!empty($sendResult['dev_fallback']) && !empty($sendResult['otp_plain'])) {
                            $_SESSION['dev_otp_display'] = $sendResult['otp_plain'];
                            $_SESSION['dev_otp_mail_error'] = $sendResult['mail_error'] ?? '';
                        }

                        redirect(base_url('auth/verify_2fa.php'));
                    } else {
                        clear_2fa_session_flags();
                        $error = 'Failed to send OTP. Please try again.';
                        if (mail_is_local_dev_mode()) {
                            $error .= ' Check SMTP_USERNAME/SMTP_PASSWORD in .env (use a Gmail App Password, not your login password).';
                        }
                        error_log('Login OTP email failed: ' . ($sendResult['error'] ?? 'unknown'));
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please log in.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-500: #3b82f6;
            --primary-700: #1d4ed8;
            --brand-accent: #f59e0b;
            --muted: #9ca3af;
        }

        html, body { height: 100%; margin: 0; }
        body { background: #0f172a; }

        .hero-navbar {
            background: rgba(0, 0, 0, 0.25) !important;
            transition: background .2s ease, backdrop-filter .2s ease;
        }
        .hero-navbar.scrolled {
            background: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: saturate(160%) blur(8px);
        }
        .brand-icon { 
            width: 36px; height: 36px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700)); color: #fff;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }
        .brand-icon i { font-size: 18px; }

        .auth-hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            color: #fff;
            overflow: hidden;
            padding-top: 80px;
        }
        .auth-hero::before {
            content: ""; position: absolute; inset: 0;
            background-image: url('<?php echo base_url('gif/fish.gif'); ?>');
            background-size: cover; background-position: center;
            opacity: 1;
        }
        .auth-hero::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.35), rgba(2, 6, 23, 0.35));
        }
        .auth-hero > .container { position: relative; z-index: 1; }

        .auth-form { width: 100%; max-width: 480px; }
        .auth-title { font-weight: 800; letter-spacing: .3px; }
        .auth-subtitle { color: #e5e7eb; }

        .form-label { color: #e5e7eb; font-weight: 600; }
        .input-group-text { background: rgba(255,255,255,.9); border: 0; }
        .form-control {
            background: rgba(255,255,255,.9);
            border: 0; color: #0f172a; padding: .9rem 1rem;
            border-radius: 12px;
        }
        .form-control:focus { box-shadow: 0 0 0 .2rem rgba(59,130,246,.2); }
        .btn-accent {
            background: linear-gradient(135deg, var(--brand-accent), #d97706);
            border: 0; color: #111827; font-weight: 800; border-radius: 12px; padding: .9rem 1rem;
            box-shadow: 0 12px 24px rgba(245, 158, 11, 0.25);
        }
        .btn-accent:hover { filter: brightness(1.05); }

        .links-row a { color: #e5e7eb; text-decoration: none; }
        .links-row a:hover { color: #fff; }
        .helper { color: var(--muted); }
        .field-icon {
            float: right;
            margin-left: -25px;
            margin-top: -25px;
            position: relative;
            z-index: 2;
            cursor: pointer;
        }
        .password-field-wrapper {
            position: relative;
            width: 100%;
        }
        .password-field-wrapper .field-icon {
            position: absolute;
            top: 50%;
            right: 12px;
            margin: 0;
            transform: translateY(-50%);
            color: #6b7280 !important;
            z-index: 10;
            font-size: 1rem;
            pointer-events: auto;
            background: transparent;
            display: inline-block !important;
        }
        .password-field-wrapper .field-icon:hover {
            color: #374151 !important;
        }
        .password-field-wrapper input.form-control {
            padding-right: 2.5rem;
        }

        @media (max-width: 991.98px) {
            .auth-hero { align-items: flex-start; padding-top: calc(64px + 2rem); }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top hero-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url(); ?>">
      <span class="brand-icon"><i class="fas fa-fish"></i></span>
      <span><?php echo APP_NAME; ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url(); ?>">Home</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Register</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?php echo base_url('auth/register.php?type=customer'); ?>">Customer</a></li>
            <li><a class="dropdown-item" href="<?php echo base_url('auth/register.php?type=supplier'); ?>">Supplier</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link active" href="#">Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="auth-hero">
  <div class="container">
    <div class="row justify-content-start">
      <div class="col-12 col-md-8 col-lg-6">

        <div class="auth-form">
          <h1 class="display-5 auth-title mb-2">Welcome back</h1>
          <p class="auth-subtitle mb-4">Sign in to continue to <?php echo APP_NAME; ?>.</p>

          <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
              <i class="fas fa-circle-exclamation me-2"></i>
              <div><?php echo $error; ?></div>
            </div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
              <i class="fas fa-circle-check me-2"></i>
              <div><?php echo $success; ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="password-field-wrapper">
                <input id="login-password-field" type="password" class="form-control" name="password" placeholder="Enter your password" required>
                <span toggle="#login-password-field" class="fa fa-fw fa-eye field-icon toggle-password" style="color: #6b7280 !important; cursor: pointer; display: inline-block !important; visibility: visible !important;" title="Show/Hide Password"></span>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
              </div>
              <a href="<?php echo base_url('auth/forgot-password.php'); ?>">Forgot password?</a>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-accent">
                <i class="fas fa-right-to-bracket me-2"></i> Sign In
              </button>
            </div>

            <p class="mt-4 helper text-center mb-0">Don't have an account?
              <a href="<?php echo base_url('auth/register.php?type=customer'); ?>">Register as Customer</a> /
              <a href="<?php echo base_url('auth/register.php?type=supplier'); ?>">Supplier</a>
            </p>
          </form>
        </div>

      </div>
    </div>
  </div>
</section>

<script src="<?php echo asset_url('js/password-toggle.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.addEventListener('scroll', () => {
    document.querySelector('.hero-navbar').classList.toggle('scrolled', window.scrollY > 20);
  });
</script>
</body>
</html>