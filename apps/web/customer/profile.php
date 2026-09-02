<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!is_logged_in() || get_user_type() !== 'customer') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    redirect(base_url('auth/login.php'));
}

$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) unset($_SESSION['registration_just_completed']);

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if (!$just_completed_registration && (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Profile incomplete']);
        exit;
    }
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=customer&step=3'));
}

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($barangay) || empty($city) || empty($province)) {
        $errors[] = "All fields are required.";
    } else {
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'barangay' => $barangay,
            'city' => $city,
            'province' => $province
        ];

        try {
            $user->updateProfile($user_id, $data);
            $_SESSION['success_message'] = "Profile updated successfully!";
            redirect(base_url('customer/profile.php'));
        } catch (Exception $e) {
            $errors[] = "Failed to update profile.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_current_email_otp':
            $result = $user->createEmailVerification($user_id, $profile['email']);
            if ($result['success']) {
                $_SESSION['email_change_step'] = 1;
                $_SESSION['email_change_user_id'] = $user_id;
                $_SESSION['success_message'] = "Verification code sent to your current email.";
            } else {
                $_SESSION['error_message'] = $result['error'];
            }
            redirect(base_url('customer/profile.php'));
            break;
            
        case 'verify_current_email':
            $otp_current = trim($_POST['otp_current'] ?? '');
            if (empty($otp_current)) {
                $_SESSION['error_message'] = "Please enter the verification code.";
            } else {
                $result = $user->verifyEmailCode($user_id, $otp_current);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 2;
                    $_SESSION['success_message'] = "Current email verified. Please enter your new email address.";
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
            redirect(base_url('customer/profile.php'));
            break;
            
        case 'send_new_email_otp':
            $new_email = trim($_POST['new_email'] ?? '');
            if (empty($new_email)) {
                $_SESSION['error_message'] = "Please enter a new email address.";
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Please enter a valid email address.";
            } elseif ($new_email === $profile['email']) {
                $_SESSION['error_message'] = "New email must be different from current email.";
            } else {
                $result = $user->createEmailChangeVerification($user_id, $new_email);
                if ($result['success']) {
                    $_SESSION['email_change_step'] = 3;
                    $_SESSION['success_message'] = "Verification code sent to <strong>$new_email</strong>.";
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
            redirect(base_url('customer/profile.php'));
            break;
            
        case 'complete_email_change':
            $otp_new = trim($_POST['otp_new'] ?? '');
            if (empty($otp_new)) {
                $_SESSION['error_message'] = "Please enter the verification code.";
            } else {
                $result = $user->verifyEmailChangeCode($user_id, $otp_new);
                if ($result['success']) {
                    unset($_SESSION['email_change_step'], $_SESSION['email_change_user_id']);
                    $_SESSION['success_message'] = "Email successfully changed to <strong>" . htmlspecialchars($result['new_email']) . "</strong>!";
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
            redirect(base_url('customer/profile.php'));
            break;
            
        case 'cancel_email_change':
            unset($_SESSION['email_change_step'], $_SESSION['email_change_user_id']);
            $_SESSION['success_message'] = "Email change cancelled.";
            redirect(base_url('customer/profile.php'));
            break;
    }
}

$page_title = 'My Profile';
include '../includes/customer_header.php';
?>

<style>
    :root {
        --primary: #f59e0b;
        --primary-dark: #d97706;
        --danger: #ef4444;
        --success: #10b981;
        --gray-100: #f8f9fa;
        --gray-200: #e9ecef;
        --gray-600: #6c757d;
        --gray-800: #343a40;
        --border: #dee2e6;
    }

    body { background: #f8f9fa; color: #333; font-family: 'Inter', sans-serif; }
    .profile-container { display: flex; max-width: 1200px; margin: 40px auto; gap: 30px; padding: 0 20px; }
    .profile-sidebar { background: #fff; border-radius: 16px; width: 280px; flex-shrink: 0; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid var(--border); position: sticky; top: 20px; height: fit-content; }
    .profile-sidebar h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: #1f2937; }
    .sidebar-section ul { list-style: none; padding: 0; margin: 0; }
    .sidebar-section ul li a { display: block; padding: 12px 16px; border-radius: 10px; color: #4b5563; font-weight: 500; text-decoration: none; transition: all 0.2s; }
    .sidebar-section ul li a:hover, .sidebar-section ul li a.active { background: #fff8f0; color: var(--primary-dark); font-weight: 600; }

    .profile-main { background: #fff; flex: 1; border-radius: 16px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid var(--border); }
    .profile-main h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; color: #1f2937; }
    .profile-main p { color: #6b7280; margin-bottom: 32px; }

    .form-label { font-weight: 600; color: #374151; margin-bottom: 8px; }
    .form-control, .form-control:focus { border: 1.5px solid #d1d5db; border-radius: 12px; padding: 12px 16px; font-size: 1rem; background: #fff; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15); }

    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; color: #111; font-weight: 700; padding: 12px 28px; border-radius: 12px; transition: all 0.3s; box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(245, 158, 11, 0.4); }

    .btn-success { background: #10b981; color: white; font-weight: 700; border-radius: 12px; padding: 12px 28px; }
    .btn-success:hover { background: #059669; }

    .btn-secondary { background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 10px 20px; border-radius: 10px; font-weight: 600; }
    .btn-secondary:hover { background: #e2e8f0; }

    .alert { border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; font-weight: 500; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

    .email-box { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; font-weight: 600; color: #1e293b; }
    .email-change-section { background: #fffbeb; border: 1.5px dashed #fbbf24; border-radius: 16px; padding: 28px; margin-top: 32px; display: none; }
    .email-change-section.active { display: block; animation: fadeIn 0.5s ease; }
    .step { background: #fff8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; border-left: 6px solid var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @media (max-width: 900px) { .profile-container { flex-direction: column; } .profile-sidebar { width: 100%; position: static; } }
</style>

<main class="profile-container">
    <aside class="profile-sidebar">
        <div class="sidebar-section">
            <h3>My Account</h3>
            <ul>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><a href="addresses.php">Addresses</a></li>
                <li><a href="settings.php">Change Password</a></li>
            </ul>
        </div>
        <div class="sidebar-section">
            <h3>My Purchase</h3>
            <ul>
                <li><a href="orders.php">Orders</a></li>
            </ul>
        </div>
    </aside>

    <section class="profile-main">
        <h2>My Profile</h2>
        <p>Manage and protect your account information</p>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="profile-form">
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <div class="email-box mb-3">
                        <?php 
                        $email = $profile['email'] ?? '';
                        if (!empty($email)) {
                            $parts = explode('@', $email);
                            if (count($parts) === 2) {
                                $username = $parts[0];
                                $domain = $parts[1];
                                $masked_username = substr($username, 0, 3) . '***';
                                echo htmlspecialchars($masked_username . '@' . $domain);
                            } else {
                                echo htmlspecialchars($email);
                            }
                        }
                        ?>
                        <?php if (!empty($profile['google_id'])): ?>
                            <span class="badge bg-info text-dark ms-2">Google Account</span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($profile['google_id'])): ?>
                        <button type="button" class="btn btn-secondary" id="start-email-change">
                            <i class="fas fa-edit me-2"></i> Change Email Address
                        </button>
                    <?php else: ?>
                        <small class="text-muted d-block mt-2">Email cannot be changed for Google-linked accounts.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" readonly="readonly" disabled>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <label class="form-label">Barangay <span class="text-danger">*</span></label>
                    <input type="text" name="barangay" class="form-control" value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City / Municipality <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Province <span class="text-danger">*</span></label>
                    <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($profile['province'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="mt-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </form>

        <!-- SECURE EMAIL CHANGE FLOW -->
        <?php if (empty($profile['google_id'])): ?>
        <div class="email-change-section <?php echo ($_SESSION['email_change_step'] ?? 0) >= 1 ? 'active' : ''; ?>" id="email-change-section">
            <h4 class="mb-4"><i class="fas fa-shield-alt text-warning me-2"></i> Secure Email Change</h4>

            <!-- STEP 1: Verify Current Email -->
            <div class="step" id="step-1" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 1 ? 'block' : 'none'; ?>;">
                <h5><i class="fas fa-envelope me-2"></i> Step 1: Verify Your Current Email</h5>
                <p class="mb-3">We just sent a 6-digit code to:</p>
                <strong><?php echo htmlspecialchars($profile['email']); ?></strong>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="action" value="verify_current_email">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <input type="text" name="otp_current" class="form-control form-control-lg text-center" placeholder="Enter 6-digit code" maxlength="6" required autofocus>
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-primary w-100 h-100">Verify & Continue</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- STEP 2: Enter New Email -->
            <div class="step" id="step-2" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 2 ? 'block' : 'none'; ?>;">
                <h5><i class="fas fa-at me-2"></i> Step 2: Enter Your New Email</h5>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action" value="send_new_email_otp">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <input type="email" name="new_email" class="form-control form-control-lg" placeholder="Enter your new email address" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Send Code</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- STEP 3: Verify New Email -->
            <div class="step" id="step-3" style="display: <?php echo ($_SESSION['email_change_step'] ?? 0) == 3 ? 'block' : 'none'; ?>;">
                <h5><i class="fas fa-shield-check me-2 text-success"></i> Step 3: Verify New Email</h5>
                <p>Enter the 6-digit code sent to your new email address.</p>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action" value="complete_email_change">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <input type="text" name="otp_new" class="form-control form-control-lg text-center" placeholder="6-digit code" maxlength="6" required>
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-success w-100 h-100">
                                <i class="fas fa-check me-2"></i> Complete Change
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-5 text-center">
                <button type="button" class="btn btn-secondary" id="cancel-email-change">
                    <i class="fas fa-times me-2"></i> Cancel Email Change
                </button>
            </div>
            <small class="d-block text-center mt-3 text-muted">Your account security is our top priority.</small>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.getElementById('start-email-change')?.addEventListener('click', function() {
    const section = document.getElementById('email-change-section');
    section.classList.add('active');
    
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
    if (confirm('Are you sure you want to cancel the email change?')) {
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
</script>

<?php include '../includes/customer_footer.php'; ?>