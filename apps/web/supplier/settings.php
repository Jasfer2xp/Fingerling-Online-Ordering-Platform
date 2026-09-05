<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$pref_sql = "SELECT * FROM notification_preferences WHERE user_id = ?";
$preferences = $database->fetch($pref_sql, [$user_id]) ?: [
    'email_orders' => 1,
    'email_payments' => 1,
    'email_reviews' => 1,
    'email_marketing' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $_SESSION['error'] = 'All fields are required.';
            } elseif (!is_password_strong($new_password)) {
                $_SESSION['error'] = 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers.';
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error'] = 'New passwords do not match.';
            } else {
                if ($user->verifyPassword($user_id, $current_password)) {
                    $user->updatePassword($user_id, $new_password);
                    $_SESSION['success'] = 'Password updated successfully.';
                } else {
                    $_SESSION['error'] = 'Current password is incorrect.';
                }
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'save_preferences':
            $preferences = [
                'email_orders' => isset($_POST['email_orders']) ? 1 : 0,
                'email_payments' => isset($_POST['email_payments']) ? 1 : 0,
                'email_reviews' => isset($_POST['email_reviews']) ? 1 : 0,
                'email_marketing' => isset($_POST['email_marketing']) ? 1 : 0
            ];
            try {
                try { $database->query("DELETE FROM notification_preferences WHERE user_id = ?", [$user_id]); } catch (Exception $e) {}
                $sql = "INSERT INTO notification_preferences (user_id, email_orders, email_payments, email_reviews, email_marketing)
                        VALUES (?, ?, ?, ?, ?)";
                $database->query($sql, [
                    $user_id,
                    $preferences['email_orders'],
                    $preferences['email_payments'],
                    $preferences['email_reviews'],
                    $preferences['email_marketing']
                ]);
                $_SESSION['success'] = 'Notification preferences saved successfully!';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Failed to save preferences.';
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'send_current_email_otp':
            $result = $user->createEmailVerification($user_id, $profile['email']);
            if ($result['success']) {
                $_SESSION['email_change_step'] = 1;
                $_SESSION['email_change_user_id'] = $user_id;
                $_SESSION['success'] = "Verification code sent to your current email.";
            } else {
                $_SESSION['error'] = $result['error'];
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'verify_current_email':
            $otp_current = trim($_POST['otp_current'] ?? '');
            if (empty($otp_current)) {
                $_SESSION['error'] = "Please enter the verification code.";
            } else {
                $result = $user->verifyEmailCode($user_id, $otp_current);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 2;
                    $_SESSION['success'] = "Current email verified. Now enter your new email address.";
                } else {
                    $_SESSION['error'] = $result['error'];
                }
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'send_new_email_otp':
            $new_email = trim($_POST['new_email'] ?? '');
            if (empty($new_email)) {
                $_SESSION['error'] = "Please enter a new email address.";
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Invalid email format.";
            } elseif ($new_email === $profile['email']) {
                $_SESSION['error'] = "New email must be different from current email.";
            } else {
                $result = $user->createEmailChangeVerification($user_id, $new_email);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 3;
                    $_SESSION['success'] = "Verification code sent to <strong>$new_email</strong>.";
                } else {
                    $_SESSION['error'] = $result['error'];
                }
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'complete_email_change':
            $otp_new = trim($_POST['otp_new'] ?? '');
            if (empty($otp_new)) {
                $_SESSION['error'] = "Please enter the verification code.";
            } else {
                $result = $user->verifyEmailChangeCode($user_id, $otp_new);
                if ($result['success']) {
                    unset($_SESSION['email_change_step'], $_SESSION['email_change_user_id']);
                    $_SESSION['success'] = "Email successfully changed to <strong>" . htmlspecialchars($result['new_email']) . "</strong>!";
                } else {
                    $_SESSION['error'] = $result['error'];
                }
            }
            redirect(base_url('supplier/settings.php'));
            break;

        case 'cancel_email_change':
            unset($_SESSION['email_change_step'], $_SESSION['email_change_user_id']);
            $_SESSION['success'] = "Email change cancelled.";
            redirect(base_url('supplier/settings.php'));
            break;

        default:
            $_SESSION['error'] = 'Invalid action.';
            redirect(base_url('supplier/settings.php'));
            break;
    }
}

$page_title = 'Account Settings';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <section class="settings-header mb-5">
            <h3 class="fw-bold"><i class="fas fa-cog me-2"></i>Account Settings</h3>
        </section>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <section class="account-info col-md-6 mb-4">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Account Information</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <?php 
                                $email = $profile['email'] ?? '';
                                $display_email = $email;
                                if (!empty($email)) {
                                    $parts = explode('@', $email);
                                    if (count($parts) === 2) {
                                        $username = $parts[0];
                                        $domain = $parts[1];
                                        $masked_username = substr($username, 0, 3) . '***';
                                        $display_email = $masked_username . '@' . $domain;
                                    }
                                }
                                ?>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($display_email); ?>" readonly>
                                <?php if (empty($profile['google_id'])): ?>
                                    <button type="button" class="btn btn-outline-primary" id="start-email-change">Change</button>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($profile['google_id'])): ?>
                                <small class="text-muted d-block mt-2">Email cannot be changed for Google-linked accounts.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['business_name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <span class="badge bg-success"><?php echo ucfirst($profile['status'] ?? 'pending'); ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" value="<?php echo format_date($profile['created_at'] ?? ''); ?>" readonly>
                        </div>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">Edit Business Profile</a>
                    </div>
                </div>

                <?php if (empty($profile['google_id'])): ?>
                <div class="card border-0 shadow-sm mt-4 bg-light <?php echo ($_SESSION['email_change_step'] ?? 0) >= 1 ? 'border-warning' : ''; ?>" 
                     id="email-change-section" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) >= 1 ? 'block' : 'none'; ?>;">
                    <div class="card-body">
                        <h5 class="text-warning mb-4">Secure Email Change</h5>

                        <div id="step-1" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 1 ? 'block' : 'none'; ?>;">
                            <p><strong>Step 1: Verify Your Current Email</strong></p>
                            <p class="text-muted small">We sent a 6-digit code to <strong><?php echo htmlspecialchars($profile['email']); ?></strong></p>
                            <form method="POST" class="row g-3 mt-3">
                                <input type="hidden" name="action" value="verify_current_email">
                                <div class="col-md-7">
                                    <input type="text" name="otp_current" class="form-control text-center" placeholder="Enter 6-digit code" maxlength="6" required autofocus>
                                </div>
                                <div class="col-md-5">
                                    <button type="submit" class="btn btn-primary w-100">Verify & Continue</button>
                                </div>
                            </form>
                        </div>

                        <div id="step-2" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 2 ? 'block' : 'none'; ?>;">
                            <p><strong>Step 2: Enter Your New Email</strong></p>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="send_new_email_otp">
                                <div class="col-md-8">
                                    <input type="email" name="new_email" class="form-control" placeholder="Enter new email" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">Send Code</button>
                                </div>
                            </form>
                        </div>

                        <div id="step-3" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 3 ? 'block' : 'none'; ?>;">
                            <p><strong>Step 3: Verify New Email</strong></p>
                            <p class="text-muted small">Enter the code sent to your new email</p>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="complete_email_change">
                                <div class="col-md-7">
                                    <input type="text" name="otp_new" class="form-control text-center" placeholder="6-digit code" maxlength="6" required>
                                </div>
                                <div class="col-md-5">
                                    <button type="submit" class="btn btn-success w-100">Complete Change</button>
                                </div>
                            </form>
                        </div>

                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-secondary btn-sm" id="cancel-email-change">Cancel Email Change</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <section class="security-settings col-md-6 mb-4">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Security Settings</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">Show</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">Show</button>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" id="password-strength-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small id="password-strength-text" class="text-muted d-block mt-1">Enter a password to check strength</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">Show</button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
// FULL JAVASCRIPT — NOTHING CUT
document.addEventListener('DOMContentLoaded', function() {
    // Email Change
    document.getElementById('start-email-change')?.addEventListener('click', function() {
        document.getElementById('email-change-section').style.display = 'block';
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'send_current_email_otp';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    });

    document.getElementById('cancel-email-change')?.addEventListener('click', function() {
        if (confirm('Cancel email change? All progress will be lost.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'cancel_email_change';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });

    // Password strength function
    function is_password_strong(password) {
        if (password.length < 8) return false;
        if (!/[A-Z]/.test(password)) return false;
        if (!/[0-9]/.test(password)) return false;
        if (/^[0-9]+$/.test(password)) return false;
        return true;
    }

    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const currentPassword = document.getElementById('current_password');
    const passwordStrengthBar = document.getElementById('password-strength-bar');
    const passwordStrengthText = document.getElementById('password-strength-text');

    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];

        if (password.length === 0) {
            return { strength: 0, text: 'Enter a password to check strength', color: 'bg-secondary' };
        }
        if (password.length < 8) feedback.push('at least 8 characters');
        else strength += 25;
        if (!/[A-Z]/.test(password)) feedback.push('an uppercase letter');
        else strength += 25;
        if (!/[0-9]/.test(password)) feedback.push('a number');
        else strength += 25;
        if (/^[0-9]+$/.test(password)) feedback.push('not all numbers');
        else strength += 25;

        if (strength < 50) return { strength, text: 'Weak - Missing: ' + feedback.join(', '), color: 'bg-danger' };
        if (strength < 75) return { strength, text: 'Medium' + (feedback.length ? ' - Add: ' + feedback.join(', ') : ''), color: 'bg-warning' };
        if (strength < 100) return { strength, text: 'Strong - Almost there!', color: 'bg-info' };
        return { strength: 100, text: 'Very Strong - Perfect!', color: 'bg-success' };
    }

    function updatePasswordStrength() {
        const password = newPassword.value;
        const result = checkPasswordStrength(password);
        passwordStrengthBar.style.width = result.strength + '%';
        passwordStrengthBar.className = 'progress-bar ' + result.color;
        passwordStrengthText.textContent = result.text;
    }

    newPassword?.addEventListener('input', updatePasswordStrength);
    newPassword?.addEventListener('input', () => {
        if (confirmPassword.value && confirmPassword.value !== newPassword.value) {
            confirmPassword.classList.add('is-invalid');
        } else if (confirmPassword.value) {
            confirmPassword.classList.remove('is-invalid');
        }
    });

    confirmPassword?.addEventListener('input', () => {
        if (confirmPassword.value === newPassword.value && confirmPassword.value) {
            confirmPassword.classList.remove('is-invalid');
        } else if (confirmPassword.value) {
            confirmPassword.classList.add('is-invalid');
        }
    });

    // Toggle password visibility
    document.querySelectorAll('[id^="toggle"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.previousElementSibling;
            if (target.type === 'password') {
                target.type = 'text';
                this.innerHTML = 'Hide';
            } else {
                target.type = 'password';
                this.innerHTML = 'Show';
            }
        });
    });
});
</script>
