<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if (!$profile) {
    $_SESSION['error'] = 'User profile not found.';
    redirect(base_url('auth/login.php'));
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'middle_initial' => $_POST['middle_initial'] ?? '',
            'barangay' => $_POST['barangay'] ?? '',
            'city' => $_POST['city'] ?? '',
            'province' => $_POST['province'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        
        // Validate required fields
        if (empty($data['first_name']) || empty($data['last_name']) || 
            empty($data['barangay']) || empty($data['city']) || empty($data['province'])) {
            $_SESSION['error'] = 'Please fill in all required fields.';
            redirect(base_url('customer/profile.php'));
        }
        
        // Update profile
        if ($user->updateProfile($user_id, $data)) {
            $_SESSION['success'] = 'Profile updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update profile.';
        }
        
        redirect(base_url('customer/profile.php'));
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
        redirect(base_url('customer/profile.php'));
    }
}

$page_title = 'My Profile';
include '../includes/customer_header.php';
?>

<div class="container py-4">
    <div class="row">
        <?php include '../includes/customer_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Profile</h1>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5>Personal Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="middle_initial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="middle_initial" name="middle_initial" 
                                       value="<?php echo htmlspecialchars($profile['middle_initial'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                                       value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address_line_1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="address_line_1" name="address_line_1" 
                                   value="<?php echo htmlspecialchars($profile['address_line_1'] ?? ''); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" 
                                       value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="province" name="province" 
                                       value="<?php echo htmlspecialchars($profile['province'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="postal_code" class="form-label">Postal Code</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                   value="<?php echo htmlspecialchars($profile['postal_code'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/customer_footer.php'; ?>