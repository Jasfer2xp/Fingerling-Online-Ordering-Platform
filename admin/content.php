<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_content'])) {
        try {
            $content_data = [
                'about_us' => $_POST['about_us'] ?? '',
                'privacy_policy' => $_POST['privacy_policy'] ?? '',
                'terms_conditions' => $_POST['terms_conditions'] ?? '',
                'contact_info' => $_POST['contact_info'] ?? '',
                'shipping_policy' => $_POST['shipping_policy'] ?? '',
                'return_policy' => $_POST['return_policy'] ?? '',
                'faq' => $_POST['faq'] ?? '',
                'hero_title' => $_POST['hero_title'] ?? '',
                'hero_subtitle' => $_POST['hero_subtitle'] ?? '',
                'hero_description' => $_POST['hero_description'] ?? ''
            ];
            
            $admin->updateSiteContent($content_data);
            $message = "Content updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating content: " . $e->getMessage();
        }
    }
}

// Get current content
$current_content = $admin->getSiteContent();

$page_title = 'Content Management';

// Set dashboard actions
$dashboard_actions = '<button type="button" class="modern-btn modern-btn-primary modern-btn-sm" onclick="saveAllContent()">
    <i class="fas fa-save"></i> Save All
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
                    <i class="fas fa-file-alt"></i>
                </div>
                Content Management
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <?php echo $dashboard_actions; ?>
        </div>
    </div>

    <div class="container-fluid px-4">

                <?php if (isset($message) && $message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Hero Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-home me-2"></i>Homepage Hero Section
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hero_title" class="form-label">Hero Title</label>
                                        <input type="text" class="form-control" name="hero_title" id="hero_title" 
                                               value="<?php echo htmlspecialchars($current_content['hero_title'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hero_subtitle" class="form-label">Hero Subtitle</label>
                                        <input type="text" class="form-control" name="hero_subtitle" id="hero_subtitle" 
                                               value="<?php echo htmlspecialchars($current_content['hero_subtitle'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="hero_description" class="form-label">Hero Description</label>
                                <textarea class="form-control" name="hero_description" id="hero_description" rows="3"><?php echo htmlspecialchars($current_content['hero_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- About Us -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>About Us
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="about_us" id="about_us" rows="10"><?php echo htmlspecialchars($current_content['about_us'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-phone me-2"></i>Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="contact_info" id="contact_info" rows="8"><?php echo htmlspecialchars($current_content['contact_info'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- FAQ -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question-circle me-2"></i>Frequently Asked Questions
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="faq" id="faq" rows="10"><?php echo htmlspecialchars($current_content['faq'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Privacy Policy -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="privacy_policy" id="privacy_policy" rows="15"><?php echo htmlspecialchars($current_content['privacy_policy'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-contract me-2"></i>Terms & Conditions
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="terms_conditions" id="terms_conditions" rows="15"><?php echo htmlspecialchars($current_content['terms_conditions'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Shipping Policy -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shipping-fast me-2"></i>Shipping Policy
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="shipping_policy" id="shipping_policy" rows="10"><?php echo htmlspecialchars($current_content['shipping_policy'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Return Policy -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-undo me-2"></i>Return Policy
                            </h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="return_policy" id="return_policy" rows="10"><?php echo htmlspecialchars($current_content['return_policy'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="submit" name="update_content" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save All Content
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

<?php
$page_js = "
    // Initialize CKEditor for rich text editing
    CKEDITOR.replace('about_us');
    CKEDITOR.replace('contact_info');
    CKEDITOR.replace('faq');
    CKEDITOR.replace('privacy_policy');
    CKEDITOR.replace('terms_conditions');
    CKEDITOR.replace('shipping_policy');
    CKEDITOR.replace('return_policy');
";
?>
