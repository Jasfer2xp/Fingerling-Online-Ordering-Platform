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

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = $_POST['settings'] ?? [];
        
        foreach ($settings as $key => $value) {
            $admin->updateSetting($key, $value);
        }
        
        $_SESSION['success'] = 'Settings updated successfully';
        redirect(base_url('admin/settings.php'));
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get current settings
$current_settings = $admin->getSettings();

// Default settings if not set
$default_settings = [
    'site_name' => APP_NAME,
    'site_description' => 'Fingerling Online Ordering Platform',
    'contact_email' => 'admin@fingerlingsph.com',
    'contact_phone' => '+63 123 456 7890',
    'maintenance_mode' => '0',
    'registration_enabled' => '1',
    'email_notifications' => '1',
    'sms_notifications' => '0',
    'order_auto_confirm' => '0',
    'min_order_amount' => '100',
    'max_order_amount' => '50000',
    'delivery_fee' => '50',
    'free_delivery_threshold' => '1000',
    'platform_commission' => '5',
    'currency' => 'PHP',
    'timezone' => 'Asia/Manila',
    'items_per_page' => '20',
    'session_timeout' => '3600',
    'max_upload_size' => '5',
    'allowed_file_types' => 'jpg,jpeg,png,gif,pdf',
    'google_maps_api_key' => '',
    'gcash_merchant_id' => '',
    'paypal_client_id' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'backup_frequency' => 'daily',
    'backup_retention' => '30'
];

// Merge with current settings
$settings = array_merge($default_settings, $current_settings);

$page_title = 'Platform Settings';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Platform Settings</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetToDefaults()">
                            <i class="fas fa-undo"></i> Reset to Defaults
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportSettings()">
                            <i class="fas fa-download"></i> Export Settings
                        </button>
                    </div>
                </div>
            </div>

            <form method="POST" id="settingsForm">
                <!-- General Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog"></i> General Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="settings[site_name]" 
                                       value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="settings[contact_email]" 
                                       value="<?php echo htmlspecialchars($settings['contact_email']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="settings[contact_phone]" 
                                       value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="settings[timezone]">
                                    <option value="Asia/Manila" <?php echo $settings['timezone'] === 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila</option>
                                    <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_description" class="form-label">Site Description</label>
                            <textarea class="form-control" id="site_description" name="settings[site_description]" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" 
                                           name="settings[maintenance_mode]" value="1" 
                                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        Maintenance Mode
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="registration_enabled" 
                                           name="settings[registration_enabled]" value="1" 
                                           <?php echo $settings['registration_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="registration_enabled">
                                        Allow New Registrations
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-business-time"></i> Business Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="settings[currency]">
                                    <option value="PHP" <?php echo $settings['currency'] === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (PHP)</option>
                                    <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="platform_commission" class="form-label">Platform Commission (%)</label>
                                <input type="number" class="form-control" id="platform_commission" 
                                       name="settings[platform_commission]" min="0" max="50" step="0.1"
                                       value="<?php echo htmlspecialchars($settings['platform_commission']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_order_amount" class="form-label">Minimum Order Amount</label>
                                <input type="number" class="form-control" id="min_order_amount" 
                                       name="settings[min_order_amount]" min="0" step="0.01"
                                       value="<?php echo htmlspecialchars($settings['min_order_amount']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="max_order_amount" class="form-label">Maximum Order Amount</label>
                                <input type="number" class="form-control" id="max_order_amount" 
                                       name="settings[max_order_amount]" min="0" step="0.01"
                                       value="<?php echo htmlspecialchars($settings['max_order_amount']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="delivery_fee" class="form-label">Standard Delivery Fee</label>
                                <input type="number" class="form-control" id="delivery_fee" 
                                       name="settings[delivery_fee]" min="0" step="0.01"
                                       value="<?php echo htmlspecialchars($settings['delivery_fee']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="free_delivery_threshold" class="form-label">Free Delivery Threshold</label>
                                <input type="number" class="form-control" id="free_delivery_threshold" 
                                       name="settings[free_delivery_threshold]" min="0" step="0.01"
                                       value="<?php echo htmlspecialchars($settings['free_delivery_threshold']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="order_auto_confirm" 
                                   name="settings[order_auto_confirm]" value="1" 
                                   <?php echo $settings['order_auto_confirm'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="order_auto_confirm">
                                Auto-confirm orders (skip manual approval)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bell"></i> Notification Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" 
                                           name="settings[email_notifications]" value="1" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Enable Email Notifications
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                           name="settings[sms_notifications]" value="1" 
                                           <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_notifications">
                                        Enable SMS Notifications
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-server"></i> System Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="items_per_page" class="form-label">Items Per Page</label>
                                <select class="form-select" id="items_per_page" name="settings[items_per_page]">
                                    <option value="10" <?php echo $settings['items_per_page'] === '10' ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $settings['items_per_page'] === '20' ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $settings['items_per_page'] === '50' ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $settings['items_per_page'] === '100' ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                <input type="number" class="form-control" id="session_timeout" 
                                       name="settings[session_timeout]" min="300" max="86400"
                                       value="<?php echo htmlspecialchars($settings['session_timeout']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="max_upload_size" class="form-label">Max Upload Size (MB)</label>
                                <input type="number" class="form-control" id="max_upload_size" 
                                       name="settings[max_upload_size]" min="1" max="100"
                                       value="<?php echo htmlspecialchars($settings['max_upload_size']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                <input type="text" class="form-control" id="allowed_file_types" 
                                       name="settings[allowed_file_types]" 
                                       value="<?php echo htmlspecialchars($settings['allowed_file_types']); ?>"
                                       placeholder="jpg,jpeg,png,gif,pdf">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                <select class="form-select" id="backup_frequency" name="settings[backup_frequency]">
                                    <option value="daily" <?php echo $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="backup_retention" class="form-label">Backup Retention (days)</label>
                                <input type="number" class="form-control" id="backup_retention" 
                                       name="settings[backup_retention]" min="1" max="365"
                                       value="<?php echo htmlspecialchars($settings['backup_retention']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integration Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plug"></i> Integration Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="google_maps_api_key" class="form-label">Google Maps API Key</label>
                            <input type="text" class="form-control" id="google_maps_api_key" 
                                   name="settings[google_maps_api_key]" 
                                   value="<?php echo htmlspecialchars($settings['google_maps_api_key']); ?>">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gcash_merchant_id" class="form-label">GCash Merchant ID</label>
                                <input type="text" class="form-control" id="gcash_merchant_id" 
                                       name="settings[gcash_merchant_id]" 
                                       value="<?php echo htmlspecialchars($settings['gcash_merchant_id']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                <input type="text" class="form-control" id="paypal_client_id" 
                                       name="settings[paypal_client_id]" 
                                       value="<?php echo htmlspecialchars($settings['paypal_client_id']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
    </main>

<script>
function resetToDefaults() {
    if (confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
        // Reset form to default values
        location.reload();
    }
}

function exportSettings() {
    // Export current settings as JSON
    const settings = {};
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('settings[')) {
            const settingKey = key.replace('settings[', '').replace(']', '');
            settings[settingKey] = value;
        }
    }
    
    const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `platform_settings_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

