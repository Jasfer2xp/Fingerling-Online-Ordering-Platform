<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    $priority = sanitize_input($_POST['priority'] ?? 'normal');
    
    if (!empty($subject) && !empty($message)) {
        // In a real application, you would send an email or save to database
        $_SESSION['success'] = 'Your message has been sent successfully. We will get back to you soon.';
        redirect(base_url('customer/contact.php'));
    } else {
        $_SESSION['error'] = 'Please fill in all required fields.';
    }
}

$page_title = 'Contact Support';
include '../includes/customer_header.php';
?>

<!-- Main Content -->
<main class="min-vh-100 bg-light">
    <!-- Header -->
    <section class="bg-primary text-white py-5 mb-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="display-5 fw-bold mb-0">
                        <i class="fas fa-envelope-open-text me-3"></i>Contact Support
                    </h1>
                    <p class="lead mb-0 opacity-90">We're here to help. Send us a message anytime.</p>
                </div>
                <div class="col-auto d-none d-md-block">
                    <div class="bg-white bg-opacity-10 backdrop-blur rounded-circle p-4">
                        <i class="fas fa-headset fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-gradient-primary text-white border-0 py-4">
                        <h4 class="mb-0 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Send us a Message
                        </h4>
                    </div>
                    <div class="card-body p-5">
                        <form method="POST" class="needs-validation" novalidate>
                            <!-- Name & Email (Read-only) -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        <i class="fas fa-user me-1 text-primary"></i> Your Name
                                    </label>
                                    <input type="text" class="form-control form-control-lg bg-light" 
                                           value="<?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        <i class="fas fa-envelope me-1 text-primary"></i> Email Address
                                    </label>
                                    <input type="email" class="form-control form-control-lg bg-light" 
                                           value="<?= htmlspecialchars($profile['email']); ?>" readonly>
                                </div>
                            </div>

                            <!-- Subject & Priority -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="subject" class="form-label fw-semibold">
                                        <i class="fas fa-tag me-1 text-primary"></i> Subject <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="subject" name="subject" 
                                           placeholder="e.g., Order Issue, Account Help" required>
                                    <div class="invalid-feedback">Please provide a subject.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="priority" class="form-label fw-semibold">
                                        <i class="fas fa-exclamation-circle me-1 text-warning"></i> Priority
                                    </label>
                                    <select class="form-select form-select-lg" id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Message -->
                            <div class="mb-4">
                                <label for="message" class="form-label fw-semibold">
                                    <i class="fas fa-comment-dots me-1 text-primary"></i> Your Message <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control form-control-lg" id="message" name="message" rows="7" 
                                          placeholder="Describe your issue or question in detail..." required></textarea>
                                <div class="invalid-feedback">Please write your message.</div>
                            </div>

                            <!-- Submit -->
                            <div class="d-flex justify-content-end gap-3">
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Contact Info Sidebar -->
            <div class="col-lg-4">
                <!-- Contact Details -->
                <div class="card border-0 shadow-lg rounded-4 mb-4 overflow-hidden">
                    <div class="card-header bg-gradient-info text-white border-0 py-4">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-address-book me-2"></i>Contact Information
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled space-y-4">
                            <li class="d-flex align-items-start mb-4">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3 flex-shrink-0">
                                    <i class="fas fa-envelope text-primary"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark">Email Us</strong>
                                    <a href="mailto:support@fingerlingmarketplace.com" class="text-primary text-decoration-none">
                                        support@fingerlingmarketplace.com
                                    </a>
                                </div>
                            </li>
                            <li class="d-flex align-items-start mb-4">
                                <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3 flex-shrink-0">
                                    <i class="fas fa-phone text-success"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark">Call Us</strong>
                                    <a href="tel:+631234567890" class="text-success text-decoration-none">+63 123 456 7890</a>
                                </div>
                            </li>
                            <li class="d-flex align-items-start">
                                <div class="bg-info bg-opacity-10 rounded-circle p-3 me-3 flex-shrink-0">
                                    <i class="fas fa-clock text-info"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark">Business Hours</strong>
                                    <small class="text-muted">
                                        Mon–Fri: 8:00 AM – 6:00 PM<br>
                                        Sat–Sun: Closed
                                    </small>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Quick Help -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-gradient-warning text-white border-0 py-4">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-life-ring me-2"></i>Need Help Fast?
                        </h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-book-open text-warning fa-3x mb-3"></i>
                        <p class="text-muted mb-3">Check our knowledge base for instant answers.</p>
                        <a href="help.php" class="btn btn-outline-warning btn-lg w-100">
                            <i class="fas fa-external-link-alt me-2"></i>Visit Help Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Custom Styles -->
<style>
    :root {
        --bs-primary: #0d6efd;
        --bs-primary-rgb: 13, 110, 253;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7) !important;
    }
    .bg-gradient-info {
        background: linear-gradient(135deg, #0dcaf0, #06b6d4) !important;
    }
    .bg-gradient-warning {
        background: linear-gradient(135deg, #ffc107, #ffb200) !important;
    }

    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.12) !important;
    }

    .form-control-lg, .form-select-lg {
        border-radius: 12px;
    }

    .btn-lg {
        border-radius: 12px;
        font-weight: 600;
    }

    .space-y-4 > * + * {
        margin-top: 1rem;
    }

    @media (max-width: 768px) {
        .display-5 {
            font-size: 1.8rem;
        }
        .card-body {
            padding: 1.5rem !important;
        }
    }
</style>

<!-- Form Validation Script -->
<script>
    // Bootstrap form validation
    (function () {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

<?php include '../includes/customer_footer.php'; ?>