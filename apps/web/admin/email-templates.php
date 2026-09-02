<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_template':
                $template_key = $_POST['template_key'];
                $subject = $_POST['subject'];
                $content = $_POST['content'];
                
                $admin->updateSetting($template_key . '_subject', $subject);
                $admin->updateSetting($template_key . '_content', $content);
                $success = 'Email template updated successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current email templates
$settings = $admin->getSettings();

$templates = [
    'welcome_email' => [
        'name' => 'Welcome Email',
        'description' => 'Sent to new users after registration',
        'subject' => $settings['welcome_email_subject'] ?? 'Welcome to Fingerling Online Ordering Platform',
        'content' => $settings['welcome_email_content'] ?? 'Welcome to our platform! We\'re excited to have you join our community.'
    ],
    'order_confirmation' => [
        'name' => 'Order Confirmation',
        'description' => 'Sent when an order is placed',
        'subject' => $settings['order_confirmation_subject'] ?? 'Order Confirmation - #{order_number}',
        'content' => $settings['order_confirmation_content'] ?? 'Your order #{order_number} has been confirmed and is being processed.'
    ],
    'supplier_approval' => [
        'name' => 'Supplier Approval',
        'description' => 'Sent when supplier application is approved',
        'subject' => $settings['supplier_approval_subject'] ?? 'Supplier Application Approved',
        'content' => $settings['supplier_approval_content'] ?? 'Congratulations! Your supplier application has been approved.'
    ],
    'supplier_rejection' => [
        'name' => 'Supplier Rejection',
        'description' => 'Sent when supplier application is rejected',
        'subject' => $settings['supplier_rejection_subject'] ?? 'Supplier Application Update',
        'content' => $settings['supplier_rejection_content'] ?? 'We regret to inform you that your supplier application was not approved.'
    ],
    'order_shipped' => [
        'name' => 'Order Shipped',
        'description' => 'Sent when order is shipped',
        'subject' => $settings['order_shipped_subject'] ?? 'Your Order Has Been Shipped - #{order_number}',
        'content' => $settings['order_shipped_content'] ?? 'Great news! Your order #{order_number} has been shipped and is on its way.'
    ],
    'password_reset' => [
        'name' => 'Password Reset',
        'description' => 'Sent when user requests password reset',
        'subject' => $settings['password_reset_subject'] ?? 'Password Reset Request',
        'content' => $settings['password_reset_content'] ?? 'Click the link below to reset your password: {reset_link}'
    ]
];

$page_title = 'Email Templates';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Email Templates</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewTemplate()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetToDefaults()">
                            <i class="fas fa-undo"></i> Reset to Defaults
                        </button>
                    </div>
                </div>
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

            <!-- Template Variables Info -->
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> Available Variables</h6>
                <p class="mb-0">
                    <code>{user_name}</code> - User's name | 
                    <code>{order_number}</code> - Order number | 
                    <code>{business_name}</code> - Supplier business name | 
                    <code>{reset_link}</code> - Password reset link | 
                    <code>{site_name}</code> - Website name
                </p>
            </div>

            <!-- Email Templates -->
            <div class="row">
                <?php foreach ($templates as $key => $template): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-envelope"></i> <?php echo $template['name']; ?>
                                </h6>
                                <small class="text-muted"><?php echo $template['description']; ?></small>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_template">
                                    <input type="hidden" name="template_key" value="<?php echo $key; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Subject Line</label>
                                        <input type="text" class="form-control" name="subject" 
                                               value="<?php echo htmlspecialchars($template['subject']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email Content</label>
                                        <textarea class="form-control" name="content" rows="6" required><?php echo htmlspecialchars($template['content']); ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="previewTemplate('<?php echo $key; ?>')">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Save Template
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
    </main>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="border p-3" style="background-color: #f8f9fa;">
                    <div class="mb-3">
                        <strong>Subject:</strong> <span id="previewSubject"></span>
                    </div>
                    <div class="border bg-white p-3">
                        <div id="previewContent"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
                    <i class="fas fa-paper-plane"></i> Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function previewTemplate(templateKey) {
    let subject, content;
    
    if (templateKey) {
        // Preview specific template
        const form = document.querySelector(`input[value="${templateKey}"]`).closest('form');
        subject = form.querySelector('input[name="subject"]').value;
        content = form.querySelector('textarea[name="content"]').value;
    } else {
        // Preview currently selected template
        subject = 'Sample Subject';
        content = 'Sample content with variables replaced.';
    }
    
    // Replace variables with sample data
    subject = subject.replace('{order_number}', '000123')
                    .replace('{user_name}', 'John Doe')
                    .replace('{business_name}', 'Sample Fish Farm')
                    .replace('{site_name}', 'Fingerling Online Ordering Platform');
    
    content = content.replace('{order_number}', '000123')
                    .replace('{user_name}', 'John Doe')
                    .replace('{business_name}', 'Sample Fish Farm')
                    .replace('{site_name}', 'Fingerling Online Ordering Platform')
                    .replace('{reset_link}', 'https://example.com/reset-password?token=sample');
    
    document.getElementById('previewSubject').textContent = subject;
    document.getElementById('previewContent').innerHTML = content.replace(/\n/g, '<br>');
    
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}

function sendTestEmail() {
    const email = prompt('Enter email address to send test to:');
    if (email) {
        fetch('send-test-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                subject: document.getElementById('previewSubject').textContent,
                content: document.getElementById('previewContent').innerHTML
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test email sent successfully!');
            } else {
                alert('Failed to send test email: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all templates to default values? This action cannot be undone.')) {
        window.location.href = 'reset-email-templates.php';
    }
}
</script>

