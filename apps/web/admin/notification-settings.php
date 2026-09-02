<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);
$message = '';
$error = '';

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
            'push_notifications' => isset($_POST['push_notifications']) ? 1 : 0,
            'order_notifications' => isset($_POST['order_notifications']) ? 1 : 0,
            'payment_notifications' => isset($_POST['payment_notifications']) ? 1 : 0,
            'supplier_notifications' => isset($_POST['supplier_notifications']) ? 1 : 0,
            'system_notifications' => isset($_POST['system_notifications']) ? 1 : 0,
            'notification_frequency' => $_POST['notification_frequency'] ?? 'immediate',
            'email_template' => $_POST['email_template'] ?? 'default',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'from_email' => $_POST['from_email'] ?? '',
            'from_name' => $_POST['from_name'] ?? ''
        ];
        
        $admin->updateNotificationSettings($settings);
        $message = "Notification settings updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = $admin->getNotificationSettings();

$page_title = 'Notification Settings';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Notification Settings</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <!-- General Notification Settings -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-bell me-2"></i>General Settings
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" 
                                               id="email_notifications" <?php echo ($current_settings['email_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Email Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                               id="sms_notifications" <?php echo ($current_settings['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            SMS Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="push_notifications" 
                                               id="push_notifications" <?php echo ($current_settings['push_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="push_notifications">
                                            Push Notifications
                                        </label>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notification_frequency" class="form-label">Notification Frequency</label>
                                        <select class="form-select" name="notification_frequency" id="notification_frequency">
                                            <option value="immediate" <?php echo ($current_settings['notification_frequency'] ?? '') === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                            <option value="hourly" <?php echo ($current_settings['notification_frequency'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                            <option value="daily" <?php echo ($current_settings['notification_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                            <option value="weekly" <?php echo ($current_settings['notification_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Types -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i>Notification Types
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="order_notifications" 
                                               id="order_notifications" <?php echo ($current_settings['order_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="order_notifications">
                                            Order Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="payment_notifications" 
                                               id="payment_notifications" <?php echo ($current_settings['payment_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="payment_notifications">
                                            Payment Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="supplier_notifications" 
                                               id="supplier_notifications" <?php echo ($current_settings['supplier_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="supplier_notifications">
                                            Supplier Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="system_notifications" 
                                               id="system_notifications" <?php echo ($current_settings['system_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="system_notifications">
                                            System Notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Configuration -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-envelope me-2"></i>Email Configuration
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="smtp_host" id="smtp_host" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_host'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" name="smtp_port" id="smtp_port" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_port'] ?? '587'); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                                <select class="form-select" name="smtp_encryption" id="smtp_encryption">
                                                    <option value="tls" <?php echo ($current_settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                    <option value="ssl" <?php echo ($current_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    <option value="none" <?php echo ($current_settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" name="smtp_username" id="smtp_username" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_username'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" name="smtp_password" id="smtp_password" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_password'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="from_email" class="form-label">From Email</label>
                                                <input type="email" class="form-control" name="from_email" id="from_email" 
                                                       value="<?php echo htmlspecialchars($current_settings['from_email'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="from_name" class="form-label">From Name</label>
                                                <input type="text" class="form-control" name="from_name" id="from_name" 
                                                       value="<?php echo htmlspecialchars($current_settings['from_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="testEmailSettings()">
                                <i class="fas fa-paper-plane me-2"></i>Test Email
                            </button>
                        </div>
                    </div>
                </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testEmailSettings() {
            // Add AJAX call to test email settings
            alert('Email test functionality would be implemented here');
        }
    </script>


