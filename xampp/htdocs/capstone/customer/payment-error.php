<?php
require_once '../config/config.php';
require_once '../includes/customer_header.php';

$error_message = $_GET['error'] ?? 'An unknown error occurred during the payment process.';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="bg-white rounded-3 shadow-sm p-4 text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h3 class="fw-bold">Payment Error</h3>
                <p class="text-muted"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="<?php echo base_url('customer/cart.php'); ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-shopping-cart me-2"></i>Return to Cart
                </a>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/customer_footer.php'; ?>