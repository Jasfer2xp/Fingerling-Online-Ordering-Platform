<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle security actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_security':
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'security_') === 0) {
                        $admin->updateSetting($key, $value);
                    }
                }
                $success = 'Security settings updated successfully.';
                break;
            case 'block_ip':
                $ip = $_POST['ip_address'];
                $reason = $_POST['reason'];
                blockIPAddress($ip, $reason);
                $success = 'IP address blocked successfully.';
                break;
            case 'unblock_ip':
                $ip = $_POST['ip_address'];
                unblockIPAddress($ip);
                $success = 'IP address unblocked successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$settings = $admin->getSettings();
$blocked_ips = getBlockedIPs();
$recent_logins = getRecentLogins();

$page_title = 'Security Settings';
include '../includes/modern_admin_header.php';

function getBlockedIPs() {
    // This would typically come from a database table or file
    return [
        ['ip' => '192.168.1.100', 'reason' => 'Multiple failed login attempts', 'blocked_at' => '2025-08-14 10:30:00'],
        ['ip' => '10.0.0.50', 'reason' => 'Suspicious activity', 'blocked_at' => '2025-08-13 15:45:00']
    ];
}

function getRecentLogins() {
    global $database;
    $sql = "SELECT u.email, al.ip_address, al.created_at
            FROM audit_log al
            JOIN users u ON al.user_id = u.id
            WHERE al.action = 'user_login'
            ORDER BY al.created_at DESC
            LIMIT 20";
    
    return $database->fetchAll($sql);
}

function blockIPAddress($ip, $reason) {
    // Implementation would save to database or file
    return true;
}

function unblockIPAddress($ip) {
    // Implementation would remove from database or file
    return true;
}

include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Security Settings</h1>
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
                <input type="hidden" name="action" value="update_security">
                
                <!-- Authentication Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock"></i> Authentication Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maxLoginAttempts" class="form-label">Max Login Attempts</label>
                                    <input type="number" class="form-control" name="security_max_login_attempts" 
                                           id="maxLoginAttempts" value="<?php echo $settings['security_max_login_attempts'] ?? '5'; ?>" 
                                           min="1" max="20">
                                    <div class="form-text">Number of failed attempts before account lockout</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lockoutDuration" class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" name="security_lockout_duration" 
                                           id="lockoutDuration" value="<?php echo $settings['security_lockout_duration'] ?? '30'; ?>" 
                                           min="5" max="1440">
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_require_email_verification" 
                                           id="emailVerification" <?php echo ($settings['security_require_email_verification'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailVerification">
                                        Require email verification for new accounts
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sessionTimeout" class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" name="security_session_timeout" 
                                           id="sessionTimeout" value="<?php echo $settings['security_session_timeout'] ?? '120'; ?>" 
                                           min="15" max="1440">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="passwordMinLength" class="form-label">Minimum Password Length</label>
                                    <input type="number" class="form-control" name="security_password_min_length" 
                                           id="passwordMinLength" value="<?php echo $settings['security_password_min_length'] ?? '8'; ?>" 
                                           min="6" max="50">
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_require_strong_passwords" 
                                           id="strongPasswords" <?php echo ($settings['security_require_strong_passwords'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="strongPasswords">
                                        Require strong passwords (uppercase, lowercase, numbers)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access Control -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Access Control</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_enable_ip_whitelist" 
                                           id="ipWhitelist" <?php echo ($settings['security_enable_ip_whitelist'] ?? '0') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ipWhitelist">
                                        Enable IP whitelist for admin access
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="allowedIPs" class="form-label">Allowed IP Addresses</label>
                                    <textarea class="form-control" name="security_allowed_ips" id="allowedIPs" rows="3" 
                                              placeholder="Enter IP addresses, one per line"><?php echo $settings['security_allowed_ips'] ?? ''; ?></textarea>
                                    <div class="form-text">One IP address per line. Use CIDR notation for ranges.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_enable_2fa" 
                                           id="twoFactor" <?php echo ($settings['security_enable_2fa'] ?? '0') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="twoFactor">
                                        Enable Two-Factor Authentication
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_log_all_actions" 
                                           id="logActions" <?php echo ($settings['security_log_all_actions'] ?? '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="logActions">
                                        Log all user actions
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="security_enable_captcha" 
                                           id="captcha" <?php echo ($settings['security_enable_captcha'] ?? '0') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="captcha">
                                        Enable CAPTCHA on login forms
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Security Settings
                    </button>
                </div>
            </form>

            <!-- Blocked IPs -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-ban"></i> Blocked IP Addresses</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#blockIPModal">
                        <i class="fas fa-plus"></i> Block IP
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($blocked_ips)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-shield-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No IP addresses are currently blocked.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Reason</th>
                                        <th>Blocked Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blocked_ips as $blocked_ip): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($blocked_ip['ip']); ?></code></td>
                                            <td><?php echo htmlspecialchars($blocked_ip['reason']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($blocked_ip['blocked_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-outline-success btn-sm" 
                                                        onclick="unblockIP('<?php echo $blocked_ip['ip']; ?>')">
                                                    <i class="fas fa-unlock"></i> Unblock
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Login Attempts -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sign-in-alt"></i> Recent Login Attempts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Date/Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logins as $login): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($login['email']); ?></td>
                                        <td><code><?php echo htmlspecialchars($login['ip_address']); ?></code></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($login['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Success
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </main>

<!-- Block IP Modal -->
<div class="modal fade" id="blockIPModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Block IP Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="block_ip">
                    
                    <div class="mb-3">
                        <label for="ipAddress" class="form-label">IP Address</label>
                        <input type="text" class="form-control" name="ip_address" id="ipAddress" 
                               placeholder="192.168.1.100" required>
                        <div class="form-text">Enter the IP address to block</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="blockReason" class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" id="blockReason" rows="3" 
                                  placeholder="Reason for blocking this IP address..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Block IP Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function unblockIP(ip) {
    if (confirm(`Are you sure you want to unblock IP address ${ip}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="unblock_ip">
            <input type="hidden" name="ip_address" value="${ip}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>


