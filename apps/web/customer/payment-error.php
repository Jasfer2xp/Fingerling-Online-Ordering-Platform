<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

$error_message = $_GET['error'] ?? 'An unknown error occurred during the payment process.';
$error_message = htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8');

$page_title = 'Payment Error';
include '../includes/customer_header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Payment Error</h4>
                </div>
                <div class="card-body">
                    <p><?php echo $error_message; ?></p>
                    <p>Please try again or contact support if the problem persists.</p>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="<?php echo base_url('customer/cart.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Back to Cart
                        </a>
                        <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/customer_footer.php'; ?>