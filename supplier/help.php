<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$page_title = 'Help & Support';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content supplier-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-question-circle"></i> Help & Support
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="fas fa-envelope"></i> Contact Support
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x text-primary mb-3"></i>
                            <h5>Inventory Management</h5>
                            <p class="text-muted">Learn how to add, update, and manage your fingerling inventory.</p>
                            <a href="#inventory-help" class="btn btn-outline-primary">Learn More</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-3x text-success mb-3"></i>
                            <h5>Order Processing</h5>
                            <p class="text-muted">Understand how to process orders and manage deliveries.</p>
                            <a href="#orders-help" class="btn btn-outline-success">Learn More</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                            <h5>Reports & Analytics</h5>
                            <p class="text-muted">Get insights from your sales data and customer analytics.</p>
                            <a href="#reports-help" class="btn btn-outline-info">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-question"></i> Frequently Asked Questions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                    How do I add new products to my inventory?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To add new products: 1) Go to Inventory → Add Product, 2) Select the species, 3) Enter stock quantity and price, 4) Set minimum order quantity, 5) Click Save. Your product will be available for customers immediately.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                    How do I process customer orders?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    When you receive an order: 1) Go to Orders section, 2) Review order details, 3) Confirm availability, 4) Update status to "Confirmed", 5) Prepare the order, 6) Update to "Ready for Delivery", 7) Coordinate with customer for pickup/delivery.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                    How do I update my business information?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to Business Profile → Business Info. You can update your business name, contact information, address, description, and certifications. Make sure to keep your information current for better customer trust.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                    How do payments work?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Customers can pay through GCash, PayMaya, or bank transfer. Once payment is confirmed, you'll receive a notification. Payments are processed securely through our platform, and you'll receive your earnings according to the payment schedule.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                    What should I do if I have low stock?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    The system will alert you when stock is low. You can: 1) Update stock quantity if you have more available, 2) Set status to "Out of Stock" temporarily, 3) Add new stock when available. Consider setting up stock alerts to avoid running out.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-phone"></i> Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Customer Support</strong><br>
                                <i class="fas fa-phone text-primary"></i> +63 912 345 6789<br>
                                <i class="fas fa-envelope text-primary"></i> support@fingerlingsmarketplace.com
                            </div>
                            
                            <div class="mb-3">
                                <strong>Technical Support</strong><br>
                                <i class="fas fa-phone text-info"></i> +63 912 345 6790<br>
                                <i class="fas fa-envelope text-info"></i> tech@fingerlingsmarketplace.com
                            </div>
                            
                            <div class="mb-3">
                                <strong>Business Hours</strong><br>
                                Monday - Friday: 8:00 AM - 6:00 PM<br>
                                Saturday: 9:00 AM - 4:00 PM<br>
                                Sunday: Closed
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-book"></i> Resources
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-file-pdf text-danger"></i> Supplier Guidelines (PDF)
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-video text-primary"></i> Video Tutorials
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-book text-success"></i> Best Practices Guide
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-users text-info"></i> Supplier Community Forum
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-newspaper text-warning"></i> Platform Updates & News
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<!-- Contact Support Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope"></i> Contact Support
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supportForm">
                    <div class="mb-3">
                        <label for="supportSubject" class="form-label">Subject *</label>
                        <select class="form-select" id="supportSubject" required>
                            <option value="">Select a topic</option>
                            <option value="inventory">Inventory Management</option>
                            <option value="orders">Order Processing</option>
                            <option value="payments">Payments & Billing</option>
                            <option value="technical">Technical Issues</option>
                            <option value="account">Account Settings</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="supportMessage" class="form-label">Message *</label>
                        <textarea class="form-control" id="supportMessage" rows="5" required 
                                  placeholder="Please describe your issue or question in detail..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="supportPriority" class="form-label">Priority</label>
                        <select class="form-select" id="supportPriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitSupport()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function submitSupport() {
    const subject = document.getElementById('supportSubject').value;
    const message = document.getElementById('supportMessage').value;
    
    if (!subject || !message) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Implement support ticket submission
    alert('Support ticket submitted successfully! We will get back to you within <?php echo ORDER_TIMEOUT_LABEL; ?>.');
    bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
    document.getElementById('supportForm').reset();
}
</script>

<?php include '../includes/supplier_footer.php'; ?>
