<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Redirect if already logged in
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
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email exists in the database
            $user = new User($database);
            if ($user->emailExists($email)) {
                // Check if this is a Google OAuth account by querying the database directly
                $user_data = $database->fetch("SELECT google_id FROM users WHERE email = ?", [$email]);
                if (!empty($user_data['google_id'])) {
                    // For Google OAuth accounts, show a specific message
                    $email_sent = true;
                    $success = 'This account uses Google Sign-In. Please use the "Continue with Google" option to log in.';
                } else {
                    // Generate reset token for non-Google accounts
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                    
                    try { $database->query("DELETE FROM password_resets WHERE email = ?", [$email]); } catch (Exception $e) {}
                    $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
                    $database->query($sql, [$email, $token, $expires_at]);
                    
                    // Send reset email
                    require_once '../includes/mailer.php';
                    
                    $reset_link = base_url('auth/reset-password.php?token=' . $token);
                    $subject = 'Password Reset Request - ' . APP_NAME;
                    $message = "Hello,\n\nYou have requested to reset your password. Click the link below to reset your password:\n\n" . 
                              $reset_link . "\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.\n\nRegards,\n" . 
                              APP_NAME . " Team";
                    
                    $send_result = send_app_email($email, $subject, $message, false);
                    
                    if ($send_result['success']) {
                        $email_sent = true;
                        $success = 'If your email is registered in our system, you will receive password reset instructions.';
                    } else {
                        $error = 'Failed to send reset email. Please try again later. Error: ' . ($send_result['error'] ?? 'Unknown error');
                    }
                }
            } else {
                // For security, we don't reveal if email exists or not
                $email_sent = true;
                $success = 'If your email is registered on our platform, you will receive a password reset link in your Gmail.';
            }
        } catch (PDOException $e) {
            // Log the actual error for debugging
            error_log('Forgot Password Database Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            
            // Provide more specific error messages for common issues
            if (strpos($e->getMessage(), 'password_resets') !== false && strpos($e->getMessage(), 'doesn\'t exist') !== false) {
                $error = 'System configuration error: Password reset table is missing. Please contact the system administrator.';
            } else {
                $error = 'A database error occurred. Please try again later.';
            }
        } catch (Exception $e) {
            // Log the actual error for debugging
            error_log('Forgot Password Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link rel="alternate icon" href="<?php echo base_url('assets/icons/fish.svg'); ?>">

    <!-- Fonts & Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-500: #3b82f6;
            --primary-700: #1d4ed8;
            --brand-accent: #f59e0b; /* warm yellow for CTA */
            --muted: #9ca3af;
        }

        html, body { height: 100%; }
        body { margin: 0; }

        /* Header blends with background GIF */
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
        .brand-icon i { font-size: 18px; line-height: 1; }
        .navbar-brand { font-weight: 700; }

        /* Full-bleed GIF background */
        .auth-hero {
            position: relative;
            min-height: 100vh; /* full viewport */
            display: flex; align-items: center;
            color: #fff;
            overflow: hidden;
            padding-top: 80px;
        }
        .auth-hero::before {
            content: ""; position: absolute; inset: 0;
            background-image: url('<?php echo base_url('gif/fish.gif'); ?>');
            background-size: cover; background-position: center; background-repeat: no-repeat;
            opacity: 1;
            transform: translateZ(0);
        }
        .auth-hero::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.35), rgba(2, 6, 23, 0.35));
        }
        .auth-hero > .container { position: relative; z-index: 1; }

        /* Minimal form (no box/card) */
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
        .btn-outline-light { border-width: 2px; }

        .links-row a { color: #e5e7eb; text-decoration: none; }
        .links-row a:hover { color: #fff; }

        .helper { color: var(--muted); }

        @media (max-width: 991.98px) {
            .auth-hero { align-items: flex-start; padding-top: calc(64px + 2rem); }
        }

        /* Accessibility: reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .auth-hero::before { display: none; }
            .auth-hero::after { background: linear-gradient(135deg, #0f172a, #1f2937); }
        }
    </style>
</head>
<body>

<!-- Header / Navbar -->
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
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url('auth/login.php'); ?>">Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero with inline form -->
<section class="auth-hero">
  <div class="container">
    <div class="row justify-content-start">
      <div class="col-12 col-md-8 col-lg-6">

        <div class="auth-form">
          <h1 class="display-5 auth-title mb-2">Forgot Password</h1>
          <p class="auth-subtitle mb-4">Enter your email address and we'll send you a link to reset your password.</p>

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

          <?php if (!$email_sent): ?>
          <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                <div class="invalid-feedback">Please provide a valid email address.</div>
              </div>
            </div>

            <div class="d-grid gap-3">
              <button type="submit" class="btn btn-accent">
                <i class="fas fa-paper-plane me-2"></i> Send Reset Link
              </button>
              <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i> Back to Login
              </a>
            </div>
          </form>
          <?php else: ?>
          <div class="d-grid gap-3">
            <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">
              <i class="fas fa-arrow-left me-2"></i> Back to Login
            </a>
          </div>
          <?php endif; ?>

          <p class="mt-4 helper mb-0">Don't have an account?
            <a href="<?php echo base_url('auth/register.php?type=customer'); ?>" class="text-decoration-none">Register as Customer</a>
            <span class="mx-1">/</span>
            <a href="<?php echo base_url('auth/register.php?type=supplier'); ?>" class="text-decoration-none">Register as Supplier</a>
          </p>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- Global Footer -->
<footer class="global-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="brand-badge"><i class="fas fa-fish"></i></div>
        <h5 class="brand-name"><?php echo APP_NAME; ?></h5>
        <p class="brand-desc">Connecting Filipino fish farmers with verified suppliers. Buy, sell, and grow with confidence.</p>
      </div>
      <div class="footer-links">
        <h6>Quick Links</h6>
        <ul>
          <li><a href="<?php echo base_url(); ?>">Home</a></li>
          <li><a href="<?php echo base_url('auth/register.php?type=customer'); ?>">Register as Customer</a></li>
          <li><a href="<?php echo base_url('auth/register.php?type=supplier'); ?>">Register as Supplier</a></li>
          <li><a href="<?php echo base_url('customer/help.php'); ?>">Help</a></li>
        </ul>
      </div>
      <div class="footer-contact">
        <h6>Contact</h6>
        <ul class="contact-list">
          <li><i class="fas fa-envelope"></i> info@fingerling.com</li>
          <li><i class="fas fa-phone"></i> +63 900 000 0000</li>
          <li><i class="fas fa-location-dot"></i> Philippines</li>
        </ul>
      </div>
    </div>
    <hr class="footer-divider" />
    <div class="footer-bar">
      <span>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
      <div class="footer-legal">
        <a href="#">Privacy</a>
        <span>•</span>
        <a href="#">Terms</a>
      </div>
    </div>
  </div>
</footer>

<style>
  .global-footer { background:#0f172a; color:#e5e7eb; padding: 3rem 0 1.25rem; }
  .global-footer .container { max-width: 1140px; margin: 0 auto; padding: 0 1rem; }
  .footer-grid { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 2rem; align-items: start; }
  .brand-badge { width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:inline-flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(37,99,235,.35); }
  .brand-badge i{ color:#fff; font-size:18px; line-height:1; }
  .brand-name { margin: .75rem 0 .5rem; font-weight:800; }
  .brand-desc { color:#cbd5e1; margin:0; }
  .footer-links h6, .footer-contact h6{ font-weight:700; margin-bottom:.75rem; }
  .footer-links ul, .footer-contact ul{ list-style:none; padding:0; margin:0; }
  .footer-links li{ margin:.4rem 0; }
  .footer-links a{ color:#e5e7eb; text-decoration:none; }
  .footer-links a:hover{ color:#fff; text-decoration:underline; }
  .contact-list li{ margin:.4rem 0; display:flex; align-items:center; gap:.5rem; color:#cbd5e1; }
  .footer-divider{ border-color: rgba(255,255,255,.08); margin:1.5rem 0; }
  .footer-bar{ display:flex; justify-content:space-between; align-items:center; color:#cbd5e1; font-size:.95rem; }
  .footer-legal{ display:flex; align-items:center; gap:.5rem; }
  .footer-legal a{ color:#e5e7eb; text-decoration:none; }
  .footer-legal a:hover{ color:#fff; text-decoration:underline; }
  @media (max-width: 991.98px){ .footer-grid{ grid-template-columns: 1fr 1fr; } }
  @media (max-width: 575.98px){ .footer-grid{ grid-template-columns: 1fr; } .footer-bar{ flex-direction:column; gap:.5rem; text-align:center; } }
</style>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Navbar scroll effect
  window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.hero-navbar');
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });

  // Bootstrap client-side validation
  (function() {
    'use strict';
    window.addEventListener('load', function() {
      var forms = document.getElementsByClassName('needs-validation');
      Array.prototype.forEach.call(forms, function(form) {
        form.addEventListener('submit', function(event) {
          if (form.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    }, false);
  })();
</script>
</body>
</html>