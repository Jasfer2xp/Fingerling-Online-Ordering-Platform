<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

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
$token_valid = false;
$token = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = sanitize_input($_GET['token']);
    
    // Validate token
    try {
        $sql = "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()";
        $reset_request = $database->fetch($sql, [$token]);
        
        if ($reset_request) {
            $token_valid = true;
        } else {
            $error = 'Invalid or expired reset token.';
        }
    } catch (Exception $e) {
        error_log('Reset Password Token Validation Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        $error = 'An error occurred while validating your token. Please try again. Error: ' . $e->getMessage();
    }
} else {
    $error = 'No reset token provided.';
}

if ($token_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!is_password_strong($password)) {
            $error = 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and not be all numbers.';
        } else {
            try {
                // Get email from token
                $sql = "SELECT email FROM password_resets WHERE token = ?";
                $reset_request = $database->fetch($sql, [$token]);
                
                if ($reset_request) {
                    $email = $reset_request['email'];
                    
                    // Update password
                    $user = new User($database);
                    $sql = "SELECT id FROM users WHERE email = ?";
                    $user_record = $database->fetch($sql, [$email]);
                    
                    if ($user_record) {
                        $user->updatePassword($user_record['id'], $password);
                        
                        // Delete reset token
                        $sql = "DELETE FROM password_resets WHERE token = ?";
                        $database->query($sql, [$token]);
                        
                        $success = 'Your password has been successfully reset. You can now log in with your new password.';
                        $token_valid = false; // Hide form after successful reset
                    } else {
                        $error = 'User not found.';
                    }
                } else {
                    $error = 'Invalid reset token.';
                }
            } catch (Exception $e) {
                error_log('Reset Password Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                $error = 'An error occurred while resetting your password. Please try again. Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>

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
          <h1 class="display-5 auth-title mb-2">Reset Password</h1>
          
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
            <div class="d-grid gap-3">
              <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-accent">
                <i class="fas fa-right-to-bracket me-2"></i> Login Now
              </a>
            </div>
          <?php elseif ($token_valid): ?>
            <p class="auth-subtitle mb-4">Enter your new password below.</p>
            
            <form method="POST" class="needs-validation" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

              <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" minlength="8" required>
                  <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                </div>
                <div class="mt-2">
                  <div class="progress" style="height: 4px;">
                    <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div id="password-strength-text" class="form-text"></div>
                </div>
              </div>

              <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                  <div class="invalid-feedback">Please confirm your password.</div>
                </div>
              </div>

              <div class="d-grid gap-3">
                <button type="submit" class="btn btn-accent">
                  <i class="fas fa-key me-2"></i> Reset Password
                </button>
                <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">
                  <i class="fas fa-arrow-left me-2"></i> Back to Login
                </a>
              </div>
            </form>
          <?php else: ?>
            <div class="d-grid gap-3">
              <a href="<?php echo base_url('auth/forgot-password.php'); ?>" class="btn btn-accent">
                <i class="fas fa-paper-plane me-2"></i> Request New Reset Link
              </a>
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

  // Password strength meter
  function checkPasswordStrength(password) {
    const strengthBar = document.getElementById('password-strength');
    const strengthText = document.getElementById('password-strength-text');
    
    if (!password) {
      strengthBar.style.width = '0%';
      strengthBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
      strengthText.textContent = '';
      return 0;
    }
    
    let strength = 0;
    
    // Length 8+
    if (password.length >= 8) strength += 20;
    
    // At least one uppercase letter
    if (/[A-Z]/.test(password)) strength += 20;
    
    // At least one number
    if (/\d/.test(password)) strength += 20;
    
    // At least one special character
    if (/[^A-Za-z0-9]/.test(password)) strength += 20;
    
    // At least one lowercase letter
    if (/[a-z]/.test(password)) strength += 20;
    
    // Update strength meter
    strengthBar.style.width = strength + '%';
    
    if (strength < 40) {
      strengthBar.classList.remove('bg-success', 'bg-warning');
      strengthBar.classList.add('bg-danger');
      strengthText.textContent = 'Weak password';
      strengthText.className = 'form-text text-danger';
    } else if (strength < 80) {
      strengthBar.classList.remove('bg-success', 'bg-danger');
      strengthBar.classList.add('bg-warning');
      strengthText.textContent = 'Moderate password';
      strengthText.className = 'form-text text-warning';
    } else {
      strengthBar.classList.remove('bg-warning', 'bg-danger');
      strengthBar.classList.add('bg-success');
      strengthText.textContent = 'Strong password';
      strengthText.className = 'form-text text-success';
    }
    
    return strength;
  }

  // Update password strength on input
  const passwordInput = document.getElementById('password');
  if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      checkPasswordStrength(this.value);
    });
  }

  // Bootstrap client-side validation
  (function() {
    'use strict';
    window.addEventListener('load', function() {
      var forms = document.getElementsByClassName('needs-validation');
      Array.prototype.forEach.call(forms, function(form) {
        form.addEventListener('submit', function(event) {
          const pwd = document.getElementById('password');
          const cpw = document.getElementById('confirm_password');
          
          if (pwd && cpw && pwd.value !== cpw.value) {
            cpw.setCustomValidity('Passwords do not match');
          } else {
            cpw.setCustomValidity('');
          }
          
          // Check password strength
          const strength = checkPasswordStrength(pwd ? pwd.value : '');
          if (strength < 40) {
            if (pwd) {
              pwd.setCustomValidity('Password must be stronger');
              pwd.reportValidity();
            }
            event.preventDefault();
            event.stopPropagation();
            return false;
          } else {
            if (pwd) pwd.setCustomValidity('');
          }
          
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