<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Ensure verification context exists
if (!isset($_SESSION['verify_user_id'], $_SESSION['verify_email'])) {
    // If user is logged in but not verified, use their session
    if (is_logged_in()) {
        $user_tmp = new User($database);
        $uid = get_user_id();
        if (!$user_tmp->isEmailVerified($uid)) {
            $_SESSION['verify_user_id'] = $uid;
            $_SESSION['verify_email'] = $_SESSION['email'] ?? '';
            $_SESSION['verify_user_type'] = $_SESSION['user_type'] ?? '';
            $_SESSION['redirect_source'] = 'login';
        } else {
            // Already verified - route to dashboard
            switch (get_user_type()) {
                case 'admin': redirect(base_url('admin/dashboard.php')); break;
                case 'supplier': redirect(base_url('supplier/dashboard.php')); break;
                case 'customer': default: redirect(base_url('customer/dashboard.php')); break;
            }
        }
    } else {
        redirect(base_url('auth/login.php'));
    }
}

$user_id = (int)($_SESSION['verify_user_id'] ?? 0);
$email = $_SESSION['verify_email'] ?? '';
$user_type = $_SESSION['verify_user_type'] ?? 'customer';
$redirect_source = $_SESSION['redirect_source'] ?? '';

// Trigger cleanup of unverified accounts (fallback if cron isn't running)
try {
    $database->beginTransaction();
    
    // Find and delete unverified accounts older than 30 minutes
    $sql = "SELECT u.id, u.user_type, u.email
            FROM users u
            INNER JOIN user_email_verifications v ON u.id = v.user_id
            WHERE v.verified_at IS NULL
            AND (
                v.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                OR u.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            )
            AND u.status != 'deleted'";
    
    $unverified_accounts = $database->fetchAll($sql);
    
    foreach ($unverified_accounts as $account) {
        try {
            $uid = $account['id'];
            $utype = $account['user_type'];
            
            // Delete customer or supplier record first
            if ($utype === 'customer') {
                $database->query("DELETE FROM customers WHERE user_id = ?", [$uid]);
            } elseif ($utype === 'supplier') {
                $database->query("DELETE FROM suppliers WHERE user_id = ?", [$uid]);
            }
            
            // Delete email verification record
            $database->query("DELETE FROM user_email_verifications WHERE user_id = ?", [$uid]);
            
            // Delete user record
            $database->query("DELETE FROM users WHERE id = ?", [$uid]);
        } catch (Exception $e) {
            // Continue with other accounts
        }
    }
    
    $database->commit();
} catch (Exception $e) {
    $database->rollback();
    // Silent fail - cron will handle it
}

// Get user creation time for countdown timer
$user_created_at = null;
$verification_created_at = null;
if ($user_id) {
    $user_data = $database->fetch("SELECT created_at FROM users WHERE id = ?", [$user_id]);
    if ($user_data) {
        $user_created_at = strtotime($user_data['created_at']);
    }
    $verification_data = $database->fetch("SELECT created_at FROM user_email_verifications WHERE user_id = ?", [$user_id]);
    if ($verification_data) {
        $verification_created_at = strtotime($verification_data['created_at']);
    }
}

// Calculate time remaining until account deletion (30 minutes from creation)
$time_remaining = null;
if ($user_created_at) {
    $deletion_time = $user_created_at + (30 * 60); // 30 minutes in seconds
    $time_remaining = max(0, $deletion_time - time());
}

// OTP-based verification: no pre-check, let OTP determine if email is real

$error = '';
$success = '';

$user = new User($database);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'verify';
        if ($action === 'resend') {
            // Simple rate limit: 60 seconds between sends
            $rec = $database->fetch("SELECT sent_at FROM user_email_verifications WHERE user_id = ?", [$user_id]);
            $last_sent = $rec ? strtotime($rec['sent_at']) : 0;
            if ($last_sent && (time() - $last_sent) < 60) {
                $error = 'Please wait at least 60 seconds before requesting another code.';
            } else {
                $res = $user->createEmailVerification($user_id, $email);
                if (is_array($res) && empty($res['success'])) {
                    $error = 'Failed to resend code: ' . htmlspecialchars($res['error'] ?? 'unknown error');
                } else {
                    $success = 'A new verification code has been sent to ' . htmlspecialchars($email) . '.';
                }
            }
        } else {
            $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
            if (strlen($code) !== 6) {
                $error = 'Please enter the 6-digit code.';
            } else {
                $result = $user->verifyEmailCode($user_id, $code);
                if (!empty($result['success'])) {
                    // Mark email as verified in session
                    $_SESSION['email_verified'] = true;
                    
                    // Fetch user to determine next step
                    $u = $user->getUserById($user_id);
                    if ($u) {
                        // For both customers and suppliers, redirect to step 3 of registration
                        // But only if they're coming from the registration process
                        if (isset($_SESSION['redirect_source']) && $_SESSION['redirect_source'] === 'register') {
                            // Redirect to step 3 of registration
                            redirect(base_url("auth/register.php?type={$u['user_type']}&step=3"));
                        } else {
                            // Handle users coming from login
                            if (isset($_SESSION['redirect_source']) && $_SESSION['redirect_source'] === 'login') {
                                // Check if they need to complete registration
                                $profile = $user->getUserProfile($user_id);
                                if (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province'])) {
                                    // User needs to complete registration
                                    $_SESSION['complete_registration_user_id'] = $user_id;
                                    $_SESSION['complete_registration_email'] = $email;
                                    $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? '';
                                    $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
                                    $_SESSION['registration_just_completed'] = true; // Email verification is complete
                                    redirect(base_url("auth/register.php?type={$u['user_type']}&step=3"));
                                } else {
                                    // User is fully registered, log them in
                                    $session_id = bin2hex(random_bytes(32));
                                    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                                    $database->query(
                                        "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
                                        [$session_id, $u['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires_at]
                                    );
                                    $_SESSION['session_id'] = $session_id;
                                    $_SESSION['user_id'] = $u['id'];
                                    $_SESSION['user_type'] = $u['user_type'];
                                    $_SESSION['email'] = $email; // Use plain text email from session, not hashed from database
                                    
                                    // Set flag to indicate registration completion
                                    $_SESSION['registration_just_completed'] = true;
                                    
                                    if ($u['user_type'] === 'customer') {
                                        redirect(base_url('customer/dashboard.php'));
                                    } else if ($u['user_type'] === 'supplier') {
                                        if ($u['status'] === 'pending') {
                                            $_SESSION['flash_success'] = 'Email verified successfully. Your supplier account is pending admin approval.';
                                            redirect(base_url('auth/login.php'));
                                        } else {
                                            redirect(base_url('supplier/dashboard.php'));
                                        }
                                    } else {
                                        redirect(base_url('auth/login.php'));
                                    }
                                }
                            } else {
                                // Not from registration - handle normal login flow
                                if ($u['user_type'] === 'customer' && $u['status'] === 'active') {
                                    // Auto-login active customers and redirect to dashboard
                                    $session_id = bin2hex(random_bytes(32));
                                    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                                    $database->query(
                                        "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
                                        [$session_id, $u['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires_at]
                                    );
                                    $_SESSION['session_id'] = $session_id;
                                    $_SESSION['user_id'] = $u['id'];
                                    $_SESSION['user_type'] = $u['user_type'];
                                    $_SESSION['email'] = $email; // Use plain text email from session, not hashed from database
                                    redirect(base_url('customer/dashboard.php'));
                                } else {
                                    // Suppliers and non-active users: redirect to login with a notice
                                    $_SESSION['flash_success'] = 'Email verified successfully. You can now log in.' . ($u && $u['user_type'] === 'supplier' ? ' Your supplier account is pending admin approval.' : '');
                                    redirect(base_url('auth/login.php'));
                                }
                            }
                        }
                    } else {
                        // User not found - redirect to login
                        redirect(base_url('auth/login.php'));
                    }
                } else {
                    $error = $result['error'] ?? 'Invalid verification code.';
                }
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
  <title>Verify your email - <?php echo APP_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #0f172a; color: #e5e7eb; }
    .card { border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
    .code-input { font-size: 1.5rem; letter-spacing: .5rem; text-align: center; }
    .btn-accent { background: linear-gradient(135deg,#f59e0b,#d97706); border: 0; color: #111827; font-weight: 800; }
    .text-muted-2 { color: #94a3b8; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark" style="background: rgba(0,0,0,.25);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url(); ?>">
      <span class="badge rounded-circle" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-fish text-white"></i></span>
      <span><?php echo APP_NAME; ?></span>
    </a>
  </div>
</nav>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      <div class="card p-4 p-md-5 bg-white text-dark">
        <h4 class="fw-bold mb-2">Verify your email</h4>
        <p class="mb-4 text-muted">We sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong>. Enter it below to continue.</p>

        <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
          <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
          <div>
            <strong>Important:</strong> If your email is valid, you will receive an OTP code. If you fail to submit the OTP within 30 minutes, your account will be deleted automatically.
            <?php if ($time_remaining !== null && $time_remaining > 0): ?>
              <div class="mt-2">
                <strong>Time remaining until account deletion: <span id="countdown-timer" class="text-danger"><?php echo gmdate('H:i:s', $time_remaining); ?></span></strong>
              </div>
            <?php endif; ?>
          </div>
        </div>

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

        <form method="POST" class="mb-3">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
          <div class="mb-3">
            <label class="form-label">6-digit code</label>
            <input type="text" inputmode="numeric" maxlength="6" class="form-control code-input" name="code" placeholder="••••••" required>
            <div class="form-text">Code expires in 15 minutes.</div>
          </div>
          <div class="d-grid gap-2 d-sm-flex">
            <button type="submit" name="action" value="verify" class="btn btn-accent px-4"><i class="fas fa-check me-2"></i>Verify</button>
            <button type="submit" name="action" value="resend" class="btn btn-outline-secondary px-4"><i class="fas fa-paper-plane me-2"></i>Resend code</button>
          </div>
        </form>

        <p class="text-muted-2 mb-0">Wrong email? <a href="<?php echo base_url('auth/register.php'); ?>">Go back to registration</a>.</p>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($time_remaining !== null && $time_remaining > 0): ?>
<script>
  // Countdown timer for account deletion
  let timeRemaining = <?php echo $time_remaining; ?>;
  const countdownElement = document.getElementById('countdown-timer');
  
  if (countdownElement && timeRemaining > 0) {
    const countdownInterval = setInterval(function() {
      timeRemaining--;
      
      if (timeRemaining <= 0) {
        clearInterval(countdownInterval);
        countdownElement.textContent = '00:00:00';
        countdownElement.parentElement.innerHTML = '<strong class="text-danger">Your account has been deleted due to non-verification.</strong>';
        // Redirect to registration page after 3 seconds
        setTimeout(function() {
          window.location.href = '<?php echo base_url('auth/register.php'); ?>';
        }, 3000);
        return;
      }
      
      const hours = Math.floor(timeRemaining / 3600);
      const minutes = Math.floor((timeRemaining % 3600) / 60);
      const seconds = timeRemaining % 60;
      
      countdownElement.textContent = 
        String(hours).padStart(2, '0') + ':' + 
        String(minutes).padStart(2, '0') + ':' + 
        String(seconds).padStart(2, '0');
      
      // Change color to red when less than 5 minutes remaining
      if (timeRemaining < 300) {
        countdownElement.classList.add('text-danger');
        countdownElement.classList.remove('text-warning');
      } else if (timeRemaining < 600) {
        countdownElement.classList.add('text-warning');
      }
    }, 1000);
  }
</script>
<?php endif; ?>
</body>
</html>
