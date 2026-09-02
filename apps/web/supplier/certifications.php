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

$page_title = 'Certifications';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content supplier-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-certificate"></i> Certifications
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCertModal">
                            <i class="fas fa-plus"></i> Add Certification
                        </button>
                    </div>
                </div>
            </div>

            <!-- Current Certifications -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-award"></i> Current Certifications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-certificate text-success"></i> 
                                        Aquaculture Business License
                                    </h6>
                                    <p class="card-text">
                                        <strong>Issuing Authority:</strong> Bureau of Fisheries and Aquatic Resources<br>
                                        <strong>Certificate Number:</strong> BFAR-2024-001234<br>
                                        <strong>Valid Until:</strong> January 15, 2025
                                    </p>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-certificate text-info"></i> 
                                        Good Aquaculture Practices (GAP)
                                    </h6>
                                    <p class="card-text">
                                        <strong>Issuing Authority:</strong> Department of Agriculture<br>
                                        <strong>Certificate Number:</strong> GAP-2024-5678<br>
                                        <strong>Valid Until:</strong> February 1, 2026
                                    </p>
                                    <span class="badge bg-info">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Keep your certifications up to date to maintain your supplier status and customer trust.
                    </div>
                </div>
            </div>

            <!-- Certification Requirements -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list-check"></i> Certification Requirements
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-check-circle text-success"></i> Required Certifications</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Business License
                                    <span class="badge bg-success rounded-pill">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Aquaculture Permit
                                    <span class="badge bg-success rounded-pill">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Health Certificate
                                    <span class="badge bg-warning rounded-pill">Pending</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="col-md-6">
                            <h6><i class="fas fa-star text-warning"></i> Recommended Certifications</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    GAP Certification
                                    <span class="badge bg-success rounded-pill">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    ISO 9001 Quality Management
                                    <span class="badge bg-secondary rounded-pill">Optional</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Organic Certification
                                    <span class="badge bg-secondary rounded-pill">Optional</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<!-- Add Certification Modal -->
<div class="modal fade" id="addCertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Add New Certification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="certificationForm">
                    <div class="mb-3">
                        <label for="certName" class="form-label">Certification Name *</label>
                        <input type="text" class="form-control" id="certName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="issuingAuthority" class="form-label">Issuing Authority *</label>
                        <input type="text" class="form-control" id="issuingAuthority" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="certNumber" class="form-label">Certificate Number</label>
                        <input type="text" class="form-control" id="certNumber">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="issueDate" class="form-label">Issue Date</label>
                            <input type="date" class="form-control" id="issueDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="expiryDate" class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" id="expiryDate">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="certFile" class="form-label">Upload Certificate</label>
                        <input type="file" class="form-control" id="certFile" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Accepted formats: PDF, JPG, PNG (Max 5MB)</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCertification()">
                    <i class="fas fa-save"></i> Save Certification
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function saveCertification() {
    // Implement save functionality
    alert('Certification save functionality will be implemented');
    bootstrap.Modal.getInstance(document.getElementById('addCertModal')).hide();
}
</script>

<?php include '../includes/supplier_footer.php'; ?>
