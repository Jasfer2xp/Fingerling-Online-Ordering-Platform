<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All fields are required.';
            } elseif (!is_password_strong($new_password)) {
                $error = 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                // Verify current password
                $user = new User($database);
                if ($user->verifyPassword($user_id, $current_password)) {
                    // Update password
                    $user->updatePassword($user_id, $new_password);
                    $success = 'Password updated successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
            break;
            
        case 'request_email_change_otp':
            // Send OTP to current email for verification
            $result = $user->createEmailVerification($user_id, $profile['email']);
            if ($result['success']) {
                $_SESSION['email_change_step'] = 1; // Step 1: Verify current email
                $success = 'Verification code has been sent to your current email address.';
            } else {
                $error = $result['error'] ?? 'Failed to send verification code.';
            }
            break;
            
        case 'verify_current_email_otp':
            $verification_code = $_POST['verification_code'] ?? '';
            
            if (empty($verification_code)) {
                $error = 'Verification code is required.';
            } else {
                // Verify the code for current email
                $result = $user->verifyEmailCode($user_id, $verification_code);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 2; // Step 2: Enter new email
                    $success = 'Current email verified. Please enter your new email address.';
                } else {
                    $error = $result['error'] ?? 'Invalid verification code.';
                }
            }
            break;
            
        case 'request_new_email_otp':
            $new_email = $_POST['new_email'] ?? '';
            
            if (empty($new_email)) {
                $error = 'Email address is required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif ($new_email === $profile['email']) {
                $error = 'New email cannot be the same as your current email.';
            } else {
                // Send verification code to new email
                $result = $user->createEmailChangeVerification($user_id, $new_email);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 3; // Step 3: Verify new email
                    $_SESSION['new_email'] = $new_email;
                    $success = 'Verification code has been sent to your new email address.';
                } else {
                    $error = $result['error'] ?? 'Failed to send verification code.';
                }
            }
            break;
            
        case 'verify_new_email_otp':
            $verification_code = $_POST['verification_code'] ?? '';
            
            if (empty($verification_code)) {
                $error = 'Verification code is required.';
            } else {
                // Verify the code for new email
                $result = $user->verifyEmailChangeCode($user_id, $verification_code);
                if ($result['success']) {
                    // Refresh profile data
                    $profile = $user->getUserProfile($user_id);
                    unset($_SESSION['email_change_step']);
                    unset($_SESSION['new_email']);
                    $success = 'Email address updated successfully.';
                } else {
                    $error = $result['error'] ?? 'Invalid verification code.';
                }
            }
            break;
            
        default:
            try {
                $update_data = [
                    'full_name' => $_POST['full_name'],
                    'contact_number' => $_POST['contact_number']
                ];
                
                $user->updateProfile($user_id, $update_data);
                $success = 'Profile updated successfully.';
                
                // Refresh profile data
                $profile = $user->getUserProfile($user_id);
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
    }
}

// Check if we're in an email change process
$email_change_step = $_SESSION['email_change_step'] ?? 0;
$new_email = $_SESSION['new_email'] ?? '';

$page_title = 'Admin Profile';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';

// Function to mask email
function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    if (strlen($username) <= 2) {
        $maskedUsername = str_repeat('*', strlen($username));
    } else {
        $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . $username[strlen($username) - 1];
    }
    
    return $maskedUsername . '@' . $domain;
}
?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-user"></i>
                </div>
                Profile Settings
            </h1>
        </div>
        <div class="modern-dashboard-actions">

        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Form -->
    <div class="modern-card role-card admin-card">
        <div class="modern-card-header">
            <h5 class="modern-card-title">
                <i class="fas fa-user me-2"></i>
                Profile Information
            </h5>
        </div>
        <div class="modern-card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars(mask_email($profile['email'])); ?>" required readonly>
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#changeEmailModal">
                                    Change
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number"
                                   value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="user_type" class="form-label">Role</label>
                            <input type="text" class="form-control" value="Administrator" readonly>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="mb-3">Change Password (Optional)</h6>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength-meter mt-2">
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: 0;"></div>
                                </div>
                                <div class="password-strength-text small mt-1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-match-text small mt-1"></div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="modern-btn modern-btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Information -->
    <div class="modern-card role-card admin-card mt-4">
        <div class="modern-card-header">
            <h5 class="modern-card-title">
                <i class="fas fa-info-circle me-2"></i>
                Account Information
            </h5>
        </div>
        <div class="modern-card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Account Created:</strong> <?php echo format_date($profile['created_at']); ?></p>
                    <p><strong>Last Login:</strong> <?php echo isset($profile['last_login']) ? format_date($profile['last_login']) : 'Never'; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Account Status:</strong> 
                        <span class="badge bg-success">Active</span>
                    </p>
                    <p><strong>User ID:</strong> #<?php echo str_pad($profile['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Email Change Modal -->
<div class="modal fade" id="changeEmailModal" tabindex="-1" aria-labelledby="changeEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeEmailModalLabel">Change Email Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($email_change_step == 0): ?>
                    <!-- Step 0: Initial request to change email -->
                    <p>To change your email address, we need to verify your current email first.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="request_email_change_otp">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Send Verification Code to Current Email</button>
                        </div>
                    </form>
                <?php elseif ($email_change_step == 1): ?>
                    <!-- Step 1: Verify current email with OTP -->
                    <p>We've sent a verification code to your current email address: <?php echo mask_email($profile['email']); ?></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_current_email_otp">
                        <div class="mb-3">
                            <label for="verification_code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                   placeholder="Enter 6-digit code" maxlength="6" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Verify Code</button>
                            <button type="submit" class="btn btn-outline-secondary" formaction="?resend=1&action=request_email_change_otp">Resend Code</button>
                        </div>
                    </form>
                <?php elseif ($email_change_step == 2): ?>
                    <!-- Step 2: Enter new email address -->
                    <p>Current email verified. Please enter your new email address.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="request_new_email_otp">
                        <div class="mb-3">
                            <label for="new_email" class="form-label">New Email Address</label>
                            <input type="email" class="form-control" id="new_email" name="new_email" 
                                   placeholder="Enter new email address" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Send Verification Code to New Email</button>
                        </div>
                    </form>
                <?php elseif ($email_change_step == 3): ?>
                    <!-- Step 3: Verify new email with OTP -->
                    <p>We've sent a verification code to your new email address: <?php echo mask_email($new_email); ?></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_new_email_otp">
                        <div class="mb-3">
                            <label for="new_email_verification_code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="new_email_verification_code" name="verification_code" 
                                   placeholder="Enter 6-digit code" maxlength="6" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Verify New Email</button>
                            <button type="submit" class="btn btn-outline-secondary" formaction="?resend=1&action=request_new_email_otp">Resend Code</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Password validation
document.getElementById('new_password').addEventListener('input', function() {
    const newPassword = this.value;
    const confirmPassword = document.getElementById('confirm_password');
    const strengthMeter = this.parentElement.nextElementSibling;
    const strengthBar = strengthMeter.querySelector('.progress-bar');
    const strengthText = strengthMeter.querySelector('.password-strength-text');
    
    // Check password strength
    let strength = 0;
    let feedback = [];
    
    if (newPassword.length === 0) {
        strength = 0;
        feedback.push('Password is required');
    } else if (newPassword.length < 8) {
        strength = 25;
        feedback.push('At least 8 characters required');
    } else {
        strength = 50; // Minimum length met
        
        if (!/[A-Z]/.test(newPassword)) {
            feedback.push('Add an uppercase letter');
        } else {
            strength += 25;
        }
        
        if (!/[0-9]/.test(newPassword)) {
            feedback.push('Add a number');
        } else {
            strength += 25;
        }
        
        if (/^[0-9]+$/.test(newPassword)) {
            feedback.push('Cannot be all numbers');
            strength = Math.max(0, strength - 25); // Reduce strength if all numbers
        }
    }
    
    // Update strength meter UI
    strengthBar.style.width = strength + '%';
    strengthBar.className = 'progress-bar';
    
    if (strength < 50) {
        strengthBar.classList.add('bg-danger');
        strengthText.className = 'password-strength-text small mt-1 text-danger';
    } else if (strength < 75) {
        strengthBar.classList.add('bg-warning');
        strengthText.className = 'password-strength-text small mt-1 text-warning';
    } else {
        strengthBar.classList.add('bg-success');
        strengthText.className = 'password-strength-text small mt-1 text-success';
    }
    
    strengthText.textContent = feedback.join(', ') || 'Strong password';
    
    // Set validation based on strength
    if (strength < 100) {
        this.setCustomValidity('Password does not meet requirements: ' + feedback.join(', '));
    } else {
        this.setCustomValidity('');
    }
    
    // Check confirm password match
    if (confirmPassword.value && confirmPassword.value !== newPassword) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
    
    // Update confirm password match text
    const matchText = confirmPassword.parentElement.nextElementSibling;
    if (confirmPassword.value) {
        if (confirmPassword.value === newPassword) {
            matchText.textContent = 'Passwords match';
            matchText.className = 'password-match-text small mt-1 text-success';
        } else {
            matchText.textContent = 'Passwords do not match';
            matchText.className = 'password-match-text small mt-1 text-danger';
        }
    } else {
        matchText.textContent = '';
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const matchText = this.parentElement.nextElementSibling;
    
    if (this.value) {
        if (this.value === newPassword) {
            matchText.textContent = 'Passwords match';
            matchText.className = 'password-match-text small mt-1 text-success';
            this.setCustomValidity('');
        } else {
            matchText.textContent = 'Passwords do not match';
            matchText.className = 'password-match-text small mt-1 text-danger';
            this.setCustomValidity('Passwords do not match');
        }
    } else {
        matchText.textContent = '';
        this.setCustomValidity('');
    }
});

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const targetInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (targetInput.type === 'password') {
            targetInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            targetInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Show the email change modal after form submission if needed
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to show the modal based on session state
    <?php if ($email_change_step > 0): ?>
    var modal = new bootstrap.Modal(document.getElementById('changeEmailModal'));
    modal.show();
    <?php endif; ?>
});
</script>

<?php if (isset($_GET['resend']) && $_GET['resend'] == 1): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('changeEmailModal'));
    modal.show();
});
</script>
<?php endif; ?>

</body>
</html>
