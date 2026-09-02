<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle notification settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'notification_') === 0) {
                $admin->updateSetting($key, $value);
            }
        }
        $success = 'Notification settings updated successfully.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$settings = $admin->getSettings();

$page_title = 'Notification Settings';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" onclick="saveSettings()">
    <i class="fas fa-save"></i> Save Settings
</button>';

include '../includes/modern_admin_header.php';
?>

<?php include '../includes/modern_admin_sidebar.php'; ?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-bell"></i>
                </div>
                Notification Settings
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <?php echo $dashboard_actions; ?>
        </div>
    </div>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notification Settings</h1>
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

            <form method="POST">
                <!-- Email Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-envelope"></i> Email Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_new_orders" 
                                           id="newOrders" <?php echo ($settings['notification_new_orders'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="newOrders">
                                        <strong>New Orders</strong><br>
                                        <small class="text-muted">Notify when new orders are placed</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_new_suppliers" 
                                           id="newSuppliers" <?php echo ($settings['notification_new_suppliers'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="newSuppliers">
                                        <strong>New Supplier Applications</strong><br>
                                        <small class="text-muted">Notify when suppliers apply for approval</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_payment_issues" 
                                           id="paymentIssues" <?php echo ($settings['notification_payment_issues'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="paymentIssues">
                                        <strong>Payment Issues</strong><br>
                                        <small class="text-muted">Notify when payments fail or need attention</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_refund_requests" 
                                           id="refundRequests" <?php echo ($settings['notification_refund_requests'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="refundRequests">
                                        <strong>Refund Requests</strong><br>
                                        <small class="text-muted">Notify when customers request refunds</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_low_inventory" 
                                           id="lowInventory" <?php echo ($settings['notification_low_inventory'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="lowInventory">
                                        <strong>Low Inventory</strong><br>
                                        <small class="text-muted">Notify when supplier inventory is low</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notification_system_alerts" 
                                           id="systemAlerts" <?php echo ($settings['notification_system_alerts'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="systemAlerts">
                                        <strong>System Alerts</strong><br>
                                        <small class="text-muted">Notify about system issues and maintenance</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMS Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sms"></i> SMS Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="sms_urgent_only" 
                                           id="smsUrgent" <?php echo ($settings['sms_urgent_only'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smsUrgent">
                                        <strong>Urgent Notifications Only</strong><br>
                                        <small class="text-muted">Send SMS only for urgent matters</small>
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smsApiKey" class="form-label">SMS API Key</label>
                                    <input type="text" class="form-control" name="sms_api_key" id="smsApiKey" 
                                           value="<?php echo htmlspecialchars($settings['sms_api_key'] ?? ''); ?>" 
                                           placeholder="Enter SMS service API key">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smsProvider" class="form-label">SMS Provider</label>
                                    <select class="form-select" name="sms_provider" id="smsProvider">
                                        <option value="semaphore" <?php echo ($settings['sms_provider'] ?? '') === 'semaphore' ? 'selected' : ''; ?>>Semaphore</option>
                                        <option value="itexmo" <?php echo ($settings['sms_provider'] ?? '') === 'itexmo' ? 'selected' : ''; ?>>iTEXMO</option>
                                        <option value="globe" <?php echo ($settings['sms_provider'] ?? '') === 'globe' ? 'selected' : ''; ?>>Globe Labs</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adminPhone" class="form-label">Admin Phone Number</label>
                                    <input type="text" class="form-control" name="admin_phone" id="adminPhone" 
                                           value="<?php echo htmlspecialchars($settings['admin_phone'] ?? ''); ?>" 
                                           placeholder="+639XXXXXXXXX">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Push Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bell"></i> Push Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="push_notifications_enabled" 
                                           id="pushEnabled" <?php echo ($settings['push_notifications_enabled'] ?? '0') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="pushEnabled">
                                        <strong>Enable Push Notifications</strong><br>
                                        <small class="text-muted">Enable browser push notifications</small>
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fcmServerKey" class="form-label">FCM Server Key</label>
                                    <input type="text" class="form-control" name="fcm_server_key" id="fcmServerKey" 
                                           value="<?php echo htmlspecialchars($settings['fcm_server_key'] ?? ''); ?>" 
                                           placeholder="Firebase Cloud Messaging server key">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fcmSenderId" class="form-label">FCM Sender ID</label>
                                    <input type="text" class="form-control" name="fcm_sender_id" id="fcmSenderId" 
                                           value="<?php echo htmlspecialchars($settings['fcm_sender_id'] ?? ''); ?>" 
                                           placeholder="Firebase sender ID">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="vapidKey" class="form-label">VAPID Key</label>
                                    <input type="text" class="form-control" name="vapid_key" id="vapidKey" 
                                           value="<?php echo htmlspecialchars($settings['vapid_key'] ?? ''); ?>" 
                                           placeholder="VAPID key for push notifications">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Frequency -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Notification Frequency</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="digestFrequency" class="form-label">Email Digest Frequency</label>
                                    <select class="form-select" name="email_digest_frequency" id="digestFrequency">
                                        <option value="daily" <?php echo ($settings['email_digest_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo ($settings['email_digest_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo ($settings['email_digest_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="disabled" <?php echo ($settings['email_digest_frequency'] ?? '') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="digestTime" class="form-label">Digest Send Time</label>
                                    <input type="time" class="form-control" name="email_digest_time" id="digestTime" 
                                           value="<?php echo $settings['email_digest_time'] ?? '08:00'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="maxNotifications" class="form-label">Max Notifications per Hour</label>
                                    <input type="number" class="form-control" name="max_notifications_per_hour" id="maxNotifications" 
                                           value="<?php echo $settings['max_notifications_per_hour'] ?? '10'; ?>" min="1" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>

            <!-- Test Notifications -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-vial"></i> Test Notifications</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send test notifications to verify your settings are working correctly.</p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="testEmailNotification()">
                            <i class="fas fa-envelope"></i> Test Email
                        </button>
                        <button class="btn btn-outline-success" onclick="testSMSNotification()">
                            <i class="fas fa-sms"></i> Test SMS
                        </button>
                        <button class="btn btn-outline-info" onclick="testPushNotification()">
                            <i class="fas fa-bell"></i> Test Push
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function testEmailNotification() {
    if (confirm('Send a test email notification?')) {
        fetch('test-notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({type: 'email'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test email sent successfully!');
            } else {
                alert('Failed to send test email: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function testSMSNotification() {
    if (confirm('Send a test SMS notification?')) {
        fetch('test-notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({type: 'sms'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test SMS sent successfully!');
            } else {
                alert('Failed to send test SMS: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function testPushNotification() {
    if (confirm('Send a test push notification?')) {
        fetch('test-notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({type: 'push'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test push notification sent successfully!');
            } else {
                alert('Failed to send test push notification: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}
</script>


