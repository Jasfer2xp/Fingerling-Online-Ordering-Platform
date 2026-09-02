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
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!is_password_strong($new_password)) {
        $error = 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Verify current password
        $user_obj = new User($database);
        if ($user_obj->verifyPassword($customer['user_id'], $current_password)) {
            // Update password
            $user_obj->updatePassword($customer['user_id'], $new_password);
            $success = 'Password updated successfully.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

$page_title = 'Change Password';
include '../includes/customer_header.php';
include '../includes/customer_sidebar.php';
?>

<!-- Main Content -->
<main class="role-main-content customer-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-key"></i>
                </div>
                Change Password
            </h1>
        </div>
        <div class="modern-dashboard-actions">

        </div>
    </div>

    <div class="container-fluid px-4">
        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Change Password Form -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="modern-card role-card customer-card">
                    <div class="modern-card-header">
                        <h5 class="modern-card-title">
                            <i class="fas fa-shield-alt text-primary"></i>
                            Security Settings
                        </h5>
                    </div>
                    <div class="modern-card-body">
                        <form method="POST" id="changePasswordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-key"></i> Current Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye" id="current_password_icon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock"></i> New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> Password must be at least 6 characters long
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check-double"></i> Confirm New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                                <div id="password_match_message" class="form-text"></div>
                            </div>

                            <!-- Password Strength Indicator -->
                            <div class="mb-3">
                                <label class="form-label">Password Strength</label>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" id="password_strength_bar" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                                <small id="password_strength_text" class="form-text text-muted">
                                    Enter a new password to see strength
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Change Password
                                </button>
                                <a href="profile.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Profile
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Tips -->
                <div class="modern-card role-card customer-card mt-4">
                    <div class="modern-card-header">
                        <h6 class="modern-card-title">
                            <i class="fas fa-lightbulb text-warning"></i>
                            Security Tips
                        </h6>
                    </div>
                    <div class="modern-card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Use a combination of letters, numbers, and special characters
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Make your password at least 8 characters long
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Don't use personal information like your name or birthday
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Use a unique password for each account
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success"></i>
                                Change your password regularly
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer Scripts -->
<script src="../assets/js/modern-sidebar.js"></script>
<script src="../assets/js/main.js"></script>

<?php include '../includes/customer_footer.php'; ?>
</body>
</html>
