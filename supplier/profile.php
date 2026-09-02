<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

// Check if this is a user who just completed registration
$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) {
    unset($_SESSION['registration_just_completed']);
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Check if supplier has completed registration (unless they just completed it)
if (!$just_completed_registration && (empty($profile['business_name']) || empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    $_SESSION['complete_registration_user_id'] = $user_id;
    $_SESSION['complete_registration_email'] = $profile['email'];
    $_SESSION['complete_registration_first_name'] = $profile['business_name'] ?? '';
    $_SESSION['complete_registration_last_name'] = '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=supplier&step=3'));
}

// Now check if the profile is approved
if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Get delivery type options
        $supports_truck = !empty($_POST['supports_truck']);
        $supports_boat = !empty($_POST['supports_boat']);
        
        // Validate that at least one delivery option is selected
        if (!$supports_truck && !$supports_boat) {
            $_SESSION['error'] = 'Please select at least one delivery option.';
            redirect(base_url('supplier/profile.php'));
        }
        
        $update_data = [
            'business_name' => $_POST['business_name'] ?? '',
            'owner_name' => $_POST['owner_name'] ?? '',
            'business_address' => $_POST['business_address'] ?? '',
            'certifications' => $_POST['certifications'] ?? '',
            'description' => $_POST['description'] ?? '',
            'supports_truck' => $supports_truck,
            'supports_boat' => $supports_boat
            // Removed barangay, city, province from update since they should be fixed
        ];
        
        try {
            if ($supplier->updateProfile($update_data)) {
                $_SESSION['success'] = 'Profile updated successfully!';
                $profile = $user->getUserProfile($user_id); // Refresh profile data
            } else {
                $_SESSION['error'] = 'Failed to update profile. Please try again.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
        }
        
        // Redirect to the same page to show messages
        redirect(base_url('supplier/profile.php'));
    } 
    // Handle profile picture upload
    else if ($action === 'upload_logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['logo']['name'];
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_size = $_FILES['logo']['size'];
            $file_type = $_FILES['logo']['type'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = 'Invalid file type. Please upload a JPEG or PNG image.';
                redirect(base_url('supplier/profile.php'));
            }
            
            // Validate file size (max 2MB)
            if ($file_size > 2 * 1024 * 1024) {
                $_SESSION['error'] = 'File size too large. Please upload an image smaller than 2MB.';
                redirect(base_url('supplier/profile.php'));
            }
            
            // Generate unique file name
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = 'supplier_' . $supplier_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/logos/' . $new_file_name;
            
            // Create uploads directory if it doesn't exist
            if (!is_dir('../uploads/logos/')) {
                mkdir('../uploads/logos/', 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Update database with new logo URL
                $logo_url = 'uploads/logos/' . $new_file_name;
                $update_sql = "UPDATE suppliers SET logo_url = ? WHERE id = ?";
                if ($database->query($update_sql, [$logo_url, $supplier_id])) {
                    $_SESSION['success'] = 'Profile picture updated successfully!';
                    $profile = $user->getUserProfile($user_id); // Refresh profile data
                } else {
                    $_SESSION['error'] = 'Failed to update profile picture in database.';
                }
            } else {
                $_SESSION['error'] = 'Failed to upload profile picture.';
            }
        } else {
            $_SESSION['error'] = 'Please select a file to upload.';
        }
        redirect(base_url('supplier/profile.php'));
    }
}

$page_title = 'Business Profile';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="profile-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold">Business Profile</h3>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm" onclick="previewProfile()">
                        Preview
                    </button>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Profile picture upload form -->
        <div class="card border-0 shadow-sm bg-white mb-5">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Profile Picture</h5>
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <img src="<?php echo !empty($profile['logo_url']) ? base_url($profile['logo_url']) : 'https://via.placeholder.com/150'; ?>" 
                                     class="rounded-circle" 
                                     alt="Profile Picture" 
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #e2e8f0;">
                            </div>
                            <div>
                                <p class="text-muted small mb-2">Upload a logo or profile picture for your business</p>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_logo">
                                    <input type="file" class="form-control form-control-sm" name="logo" accept="image/jpeg,image/png" required>
                                    <div class="form-text">JPG, PNG format, max 2MB</div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2">
                                        Upload Picture
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main profile form -->
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">

            <!-- Business Information -->
            <section class="business-info mb-5">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Business Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Business Name *</label>
                                    <input type="text" class="form-control" name="business_name" 
                                           value="<?php echo htmlspecialchars($profile['business_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Owner Name *</label>
                                    <input type="text" class="form-control" name="owner_name" 
                                           value="<?php echo htmlspecialchars($profile['owner_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Contact Number *</label>
                                    <input type="tel" class="form-control" name="contact_number" 
                                           value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" 
                                           placeholder="09XXXXXXXXX" readonly="readonly" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Valid ID</label>
                                    <?php 
                                    $permit_path = $profile['valid_id'] ?? '';
                                    if (!empty($permit_path)): 
                                        $file_url = base_url($permit_path);
                                        $file_info = pathinfo($permit_path);
                                        $extension = strtolower($file_info['extension'] ?? '');
                                        $filename = $file_info['basename'] ?? 'permit_file';
                                    ?>
                                        <div class="border rounded p-3 bg-light">
                                            <?php if (in_array($extension, ['jpg', 'jpeg', 'png'])): ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $file_url; ?>" target="_blank">
                                                        <img src="<?php echo $file_url; ?>" alt="Valid ID" 
                                                             class="img-fluid rounded" style="max-height: 150px;">
                                                    </a>
                                                </div>
                                            <?php elseif ($extension === 'pdf'): ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                                        View PDF
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                        View File
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <a href="<?php echo $file_url; ?>" 
                                               download="<?php echo 'valid_id_' . $supplier_id . '.' . $extension; ?>"
                                               class="btn btn-success btn-sm">
                                                Download File
                                            </a>
                                            <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($filename); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No permit uploaded</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Type Options -->
                        <div class="form-group mb-4">
                            <label class="form-label">Delivery Options *</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="supports_truck" id="supports_truck" 
                                       value="1" <?php echo !empty($profile['supports_truck']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="supports_truck">
                                    Supports Truck Delivery
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="supports_boat" id="supports_boat" 
                                       value="1" <?php echo !empty($profile['supports_boat']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="supports_boat">
                                    Supports Boat Delivery
                                </label>
                            </div>
                            <div class="form-text">Select at least one delivery option</div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Business Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Tell us about your business..."><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Certifications</label>
                            <textarea class="form-control" name="certifications" rows="3" 
                                      placeholder="List your certifications, licenses, and qualifications..."><?php echo htmlspecialchars($profile['certifications'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Location Information -->
            <section class="location-info mb-5">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Location Information</h5>
                        <div class="mb-3">
                            <label for="business_address" class="form-label">Business Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="business_address" name="business_address" 
                                   value="<?php echo htmlspecialchars($profile['business_address'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter your business address.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" 
                                       value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($profile['city'] ?? 'Tangub City'); ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="province" name="province" 
                                       value="<?php echo htmlspecialchars($profile['province'] ?? 'Misamis Occidental'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Business Statistics -->
            <section class="business-stats mb-5">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Business Statistics</h5>
                        <div class="row g-4">
                            <div class="col-md-3 text-center">
                                <h3 class="text-primary"><?php echo number_format($profile['rating'] ?? 0, 1); ?></h3>
                                <p class="text-muted">Average Rating</p>
                                <div class="rating-stars">
                                    <?php 
                                    $rating = round($profile['rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-muted'; ?>" 
                                           data-bs-toggle="tooltip" 
                                           title="<?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3 class="text-success"><?php echo number_format($profile['total_ratings'] ?? 0); ?></h3>
                                <p class="text-muted">Total Reviews</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3 class="text-info"><?php echo ucfirst($profile['status'] ?? 'pending'); ?></h3>
                                <p class="text-muted">Account Status</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3 class="text-warning"><?php echo format_date($profile['created_at'] ?? ''); ?></h3>
                                <p class="text-muted">Member Since</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Action Buttons -->
            <section class="action-buttons">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between flex-wrap">
                            <div>
                                <button type="submit" class="btn btn-success btn-sm">
                                    Save Changes
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">
                                    Reset
                                </button>
                            </div>
                            <div>
                                <a href="location.php" class="btn btn-outline-primary btn-sm">
                                    View Location
                                </a>
                                <a href="certifications.php" class="btn btn-outline-info btn-sm">
                                    Manage Certifications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </form>

        <!-- Profile Preview Modal -->
        <div class="modal fade" id="profilePreviewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Profile Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card border-0 shadow-sm bg-white">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4><?php echo htmlspecialchars($profile['business_name'] ?? 'Business Name'); ?></h4>
                                        <p class="text-muted">Owned by <?php echo htmlspecialchars($profile['owner_name'] ?? 'Owner Name'); ?></p>
                                        <p><?php echo htmlspecialchars($profile['description'] ?? 'No description available'); ?></p>
                                        <h6>Location</h6>
                                        <p>
                                            <?php echo htmlspecialchars($profile['business_address'] ?? ''); ?>,
                                            <?php echo htmlspecialchars($profile['barangay'] ? $profile['barangay'] . ', ' : ''); ?>
                                            <?php echo htmlspecialchars($profile['city'] ?? 'Tangub City'); ?>,
                                            <?php echo htmlspecialchars($profile['province'] ?? 'Misamis Occidental'); ?>
                                        </p>
                                        <h6>Contact</h6>
                                        <p><?php echo htmlspecialchars($profile['contact_number'] ?? 'No contact number'); ?></p>
                                        <h6>Certifications</h6>
                                        <p><?php echo htmlspecialchars($profile['certifications'] ?? 'No certifications listed'); ?></p>
                                        <h6>Valid ID</h6>
                                        <?php 
                                        $permit_path = $profile['valid_id'] ?? '';
                                        if (!empty($permit_path)): 
                                            $file_url = base_url($permit_path);
                                            $file_info = pathinfo($permit_path);
                                            $extension = strtolower($file_info['extension'] ?? '');
                                        ?>
                                            <div class="mt-2">
                                                <?php if (in_array($extension, ['jpg', 'jpeg', 'png'])): ?>
                                                    <div class="mb-2">
                                                        <a href="<?php echo $file_url; ?>" target="_blank">
                                                            <img src="<?php echo $file_url; ?>" 
                                                                 alt="Valid ID" 
                                                                 class="img-fluid rounded" 
                                                                 style="max-height: 200px;">
                                                        </a>
                                                    </div>
                                                <?php elseif ($extension === 'pdf'): ?>
                                                    <div class="mb-2">
                                                        <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                                            View PDF Permit
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-2">
                                                        <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            View Permit
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $file_url; ?>" 
                                                       download="<?php echo 'valid_id_' . $supplier_id . '.' . $extension; ?>"
                                                       class="btn btn-success btn-sm">
                                                        Download Valid ID
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p>No valid ID uploaded.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h5>Rating</h5>
                                        <h2 class="text-warning"><?php echo number_format($profile['rating'] ?? 0, 1); ?></h2>
                                        <div class="rating-stars mb-2">
                                            <?php 
                                            $rating = round($profile['rating'] ?? 0);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-muted'; ?>" 
                                                   data-bs-toggle="tooltip" 
                                                   title="<?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-muted"><?php echo number_format($profile['total_ratings'] ?? 0); ?> reviews</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .alert {
        border-radius: 0.5rem;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .rating-stars i {
        cursor: pointer;
    }
    .form-control.is-invalid {
        border-color: #dc3545;
    }
    .invalid-feedback {
        display: none;
    }
    .form-control.is-invalid ~ .invalid-feedback {
        display: block;
    }
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .btn-group .btn, .btn-sm, .form-select-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .card-title, h5 {
            font-size: 1.1rem;
        }
        .form-label {
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            font-size: 0.85rem;
        }
        .rating-stars i {
            font-size: 0.9rem;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewProfile() {
    const modal = new bootstrap.Modal(document.getElementById('profilePreviewModal'));
    modal.show();
}

function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        document.querySelector('form').reset();
        document.querySelectorAll('.form-control').forEach(input => input.classList.remove('is-invalid'));
    }
}

// Real-time form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const requiredFields = ['business_name', 'owner_name', 'contact_number', 'business_address'];
    const phoneInput = document.querySelector('[name="contact_number"]');

    requiredFields.forEach(field => {
        const input = document.querySelector(`[name="${field}"]`);
        if (input) {
            input.addEventListener('input', function() {
                if (input.value.trim()) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                }
            });
        }
    });

    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
            if (value.length === 11 && value.startsWith('09')) {
                e.target.classList.remove('is-invalid');
                e.target.classList.add('is-valid');
            } else {
                e.target.classList.remove('is-valid');
                e.target.classList.add('is-invalid');
            }
        });
    }

    form.addEventListener('submit', function(e) {
        let isValid = true;
        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field}"]`);
            if (input && !input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        });

        const phoneValue = phoneInput?.value.replace(/\D/g, '') || '';
        if (phoneValue.length !== 11 || !phoneValue.startsWith('09')) {
            phoneInput?.classList.add('is-invalid');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields and ensure a valid phone number.');
        }
    });

    // Sidebar toggle
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});
</script>
</body>
</html>