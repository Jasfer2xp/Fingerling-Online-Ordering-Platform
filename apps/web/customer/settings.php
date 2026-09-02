<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception('All fields are required');
                }
                
                if (!is_password_strong($new_password)) {
                    throw new Exception('Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('New passwords do not match');
                }
                
                if (!$user->verifyPassword($user_id, $current_password)) {
                    throw new Exception('Current password is incorrect');
                }
                
                $user->updatePassword($user_id, $new_password);
                $_SESSION['success'] = 'Password updated successfully';
                redirect(base_url('customer/settings.php?password_updated=success'));
                break;
                
            case 'update_notifications':
                $notifications = [
                    'email_orders' => isset($_POST['email_orders']),
                    'email_promotions' => isset($_POST['email_promotions']),
                    'sms_orders' => isset($_POST['sms_orders']),
                    'sms_promotions' => isset($_POST['sms_promotions'])
                ];
                
                $user->updateNotificationSettings($user_id, $notifications);
                $_SESSION['success'] = 'Notification settings updated successfully';
                break;
                
            case 'deactivate_account':
                $password = $_POST['password'];
                
                if (!$user->verifyPassword($user_id, $password)) {
                    throw new Exception('Password is incorrect');
                }
                
                $user->deactivateAccount($user_id);
                session_destroy();
                redirect(base_url('auth/login.php?message=account_deactivated'));
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('customer/settings.php'));
}

// Get current notification settings
$notification_settings = $user->getNotificationSettings($user_id);

$page_title = 'Account Settings';
include '../includes/customer_header.php';
?>

<style>
/* ---------- GENERAL LAYOUT ---------- */
body {
    background-color: #f8f9fa;
    color: #333;
    font-family: 'Segoe UI', sans-serif;
}

.settings-container {
    display: flex;
    max-width: 1200px;
    margin: 40px auto;
    gap: 30px;
    padding: 0 20px;
}

/* ---------- SIDEBAR ---------- */
.profile-sidebar {
    background: #fff;
    border-radius: 10px;
    width: 260px;
    flex-shrink: 0;
    padding: 20px;
    position: sticky;
    top: 20px;
    height: fit-content;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.profile-sidebar h3 {
    font-size: 18px;
    margin-bottom: 15px;
    color: #222;
    font-weight: 600;
}

.sidebar-section {
    margin-bottom: 25px;
}

.sidebar-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-section ul li a {
    display: block;
    padding: 10px 12px;
    color: #555;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.sidebar-section ul li a:hover,
.sidebar-section ul li a.active {
    background: #f0f0f0;
    color: #222;
}

/* ---------- MAIN CONTENT ---------- */
.settings-main {
    background: #fff;
    flex: 1;
    border-radius: 10px;
    padding: 25px 30px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.settings-main h2 {
    font-size: 22px;
    margin-bottom: 20px;
    color: #222;
}

.settings-section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eee;
}

.settings-section h3 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #222;
}

.form-label {
    font-weight: 500;
    color: #444;
    margin-bottom: 5px;
    display: block;
}

.form-control {
    background: #fff;
    border: 1px solid #ddd;
    color: #333;
    border-radius: 6px;
    padding: 10px;
    width: 100%;
    margin-bottom: 15px;
    max-width: 500px;
}

.form-control:focus {
    border-color: #999;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.form-check-input {
    margin-right: 10px;
    width: 18px;
    height: 18px;
}

.form-check-label {
    color: #444;
}

.btn-save {
    background: #ff6600;
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
    font-size: 16px;
    margin-top: 10px;
}

.btn-save:hover {
    background: #ff7b1c;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 22px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
    font-size: 16px;
    margin-top: 10px;
}

.btn-danger:hover {
    background: #c82333;
}

.alert {
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.deactivate-section {
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 25px;
    margin-top: 30px;
}

.deactivate-section h3 {
    color: #dc3545;
    margin-bottom: 15px;
}

.modal-content {
    background: #fff;
    border: none;
    border-radius: 10px;
}

.modal-header {
    border-bottom: 1px solid #eee;
    padding: 15px 20px;
}

.modal-title {
    color: #222;
    font-size: 20px;
    font-weight: 600;
}

.modal-body {
    padding: 20px;
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 900px) {
    .settings-container { flex-direction: column; }
    .profile-sidebar { width: 100%; position: static; }
}
</style>

<main class="settings-container">
    <aside class="profile-sidebar">
        <div class="sidebar-section">
            <h3>My Account</h3>
            <ul>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="addresses.php">Addresses</a></li>
                <li><a href="settings.php" class="active">Change Password</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3>My Purchase</h3>
            <ul>
                <li><a href="orders.php">Orders</a></li>
            </ul>
        </div>
    </aside>

    <section class="settings-main">
            <!-- Password Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lock"></i> Password & Security</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="update_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <!-- Password Strength Indicator -->
                                <div class="mt-2">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" id="password-strength-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small id="password-strength-text" class="text-muted">Enter a password to check strength</small>
                                    </div>
                                </div>
                                <div class="form-text">Password must be at least 8 characters, including one uppercase letter and one number.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-cog"></i> Account Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-danger">Deactivate Account</h6>
                            <p class="text-muted">
                                Deactivating your account will disable your access to the platform. 
                                You can reactivate it by contacting support.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                <i class="fas fa-user-times"></i> Deactivate Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

<!-- Deactivate Account Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deactivate Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="deactivate_account">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will deactivate your account. 
                        You will lose access to the platform until you contact support to reactivate.
                    </div>
                    
                    <div class="mb-3">
                        <label for="deactivate_password" class="form-label">Enter your password to confirm *</label>
                        <input type="password" class="form-control" id="deactivate_password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-save" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-danger">Deactivate Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password strength validation function (matching PHP function)
function isPasswordStrong(password) {
    // Check if password is at least 8 characters
    if (password.length < 8) {
        return false;
    }
    
    // Check if password contains at least one uppercase letter
    if (!/[A-Z]/.test(password)) {
        return false;
    }
    
    // Check if password contains at least one number
    if (!/[0-9]/.test(password)) {
        return false;
    }
    
    // Check if password is all numbers
    if (/^[0-9]+$/.test(password)) {
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a success message in the session and show it as a popup
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('password_updated') && urlParams.get('password_updated') === 'success') {
        alert('Password updated successfully!');
        // Remove the parameter from URL without reloading the page
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const currentPassword = document.getElementById('current_password');
    
    // Password visibility toggle buttons
    const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
    const toggleNewPassword = document.getElementById('toggleNewPassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    
    // Password strength elements
    const passwordStrengthBar = document.getElementById('password-strength-bar');
    const passwordStrengthText = document.getElementById('password-strength-text');

    // Toggle password visibility
    function togglePasswordVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Add event listeners for password visibility toggles
    if (toggleCurrentPassword) {
        toggleCurrentPassword.addEventListener('click', function() {
            togglePasswordVisibility('current_password', this);
        });
    }
    
    if (toggleNewPassword) {
        toggleNewPassword.addEventListener('click', function() {
            togglePasswordVisibility('new_password', this);
        });
    }
    
    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
            togglePasswordVisibility('confirm_password', this);
        });
    }

    // Password strength checker
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];
        
        if (password.length === 0) {
            return { strength: 0, text: 'Enter a password to check strength', color: 'bg-secondary' };
        }
        
        if (password.length < 8) {
            feedback.push('at least 8 characters');
        } else {
            strength += 25;
        }
        
        if (!/[A-Z]/.test(password)) {
            feedback.push('an uppercase letter');
        } else {
            strength += 25;
        }
        
        if (!/[0-9]/.test(password)) {
            feedback.push('a number');
        } else {
            strength += 25;
        }
        
        if (/^[0-9]+$/.test(password)) {
            feedback.push('not all numbers');
        } else {
            strength += 25;
        }
        
        // Determine strength level and text
        if (strength < 50) {
            return { 
                strength: strength, 
                text: 'Weak - ' + (feedback.length ? 'Missing: ' + feedback.join(', ') : ''), 
                color: 'bg-danger' 
            };
        } else if (strength < 75) {
            return { 
                strength: strength, 
                text: 'Medium - ' + (feedback.length ? 'Add: ' + feedback.join(', ') : 'Good!'), 
                color: 'bg-warning' 
            };
        } else if (strength < 100) {
            return { 
                strength: strength, 
                text: 'Strong - Almost there!', 
                color: 'bg-info' 
            };
        } else {
            return { 
                strength: 100, 
                text: 'Very Strong - Password meets all requirements!', 
                color: 'bg-success' 
            };
        }
    }

    // Update password strength meter
    function updatePasswordStrength() {
        const password = newPassword.value;
        const strength = checkPasswordStrength(password);
        
        passwordStrengthBar.style.width = strength.strength + '%';
        passwordStrengthBar.className = 'progress-bar ' + strength.color;
        passwordStrengthText.textContent = strength.text;
        
        // Update validation classes
        if (strength.strength === 100) {
            newPassword.classList.remove('is-invalid');
            newPassword.classList.add('is-valid');
        } else if (password.length > 0) {
            newPassword.classList.remove('is-valid');
            newPassword.classList.add('is-invalid');
        } else {
            newPassword.classList.remove('is-valid', 'is-invalid');
        }
    }

    // Real-time password validation
    if (newPassword) {
        newPassword.addEventListener('input', function() {
            updatePasswordStrength();
            
            // Update confirm password validity
            if (confirmPassword.value && confirmPassword.value !== this.value) {
                confirmPassword.classList.remove('is-valid');
                confirmPassword.classList.add('is-invalid');
            } else if (confirmPassword.value) {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
            }
        });
        
        // Initialize the strength meter on page load
        updatePasswordStrength();
    }

    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value === newPassword.value && this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    // Form submission validation
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newValue = newPassword.value;
            const confirmValue = confirmPassword.value;
            const currentPass = currentPassword.value;
            
            // Reset validation classes
            newPassword.classList.remove('is-invalid', 'is-valid');
            confirmPassword.classList.remove('is-invalid', 'is-valid');
            currentPassword.classList.remove('is-invalid', 'is-valid');
            
            let isValid = true;
            let errorMessage = '';
            
            // Check current password
            if (!currentPass) {
                currentPassword.classList.add('is-invalid');
                isValid = false;
                errorMessage += 'Current password is required.\n';
            }
            
            // Check new password strength
            if (!isPasswordStrong(newValue)) {
                newPassword.classList.add('is-invalid');
                isValid = false;
                errorMessage += 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers.\n';
            } else {
                newPassword.classList.add('is-valid');
            }
            
            // Check password confirmation
            if (newValue !== confirmValue) {
                confirmPassword.classList.add('is-invalid');
                isValid = false;
                errorMessage += 'Passwords do not match.\n';
            } else if (confirmValue) {
                confirmPassword.classList.add('is-valid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
                return false;
            }
            // If validation passes, let the form submit normally without preventing default
        });
    }
});
</script>

<?php include '../includes/customer_footer.php'; ?>
</body>
</html>
