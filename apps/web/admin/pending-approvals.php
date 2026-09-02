<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $supplier_id = $_POST['supplier_id'] ?? '';
    
    try {
        switch ($action) {
            case 'approve':
                $admin->approveSupplier($supplier_id);
                $success = 'Supplier approved successfully.';
                break;
            case 'reject':
                $admin->rejectSupplier($supplier_id, $_POST['reason'] ?? '');
                $success = 'Supplier rejected successfully.';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get pending suppliers
$pending_suppliers = $admin->getPendingSuppliers();

$page_title = 'Pending Approvals';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Pending Approvals</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-warning fs-6">
                        <?php echo count($pending_suppliers); ?> pending
                    </span>
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

            <?php if (empty($pending_suppliers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>No Pending Approvals</h4>
                    <p class="text-muted">All supplier applications have been processed.</p>
                </div>
            <?php else: ?>
                <!-- Pending Suppliers -->
                <div class="row">
                    <?php foreach ($pending_suppliers as $supplier): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-store"></i> 
                                        <?php echo htmlspecialchars($supplier['business_name']); ?>
                                    </h6>
                                    <span class="badge bg-warning">Pending</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>Owner:</strong><br>
                                                <?php echo htmlspecialchars($supplier['owner_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Email:</strong><br>
                                                <?php echo htmlspecialchars($supplier['email']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Contact:</strong><br>
                                                <?php echo htmlspecialchars($supplier['contact_number']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>Address:</strong><br>
                                                <?php echo htmlspecialchars($supplier['business_address']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Location:</strong><br>
                                                <?php echo htmlspecialchars($supplier['barangay'] . ', ' . $supplier['city'] . ', ' . $supplier['province']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Applied:</strong><br>
                                                <?php echo date('M j, Y g:i A', strtotime($supplier['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($supplier['description']): ?>
                                        <div class="mt-3">
                                            <strong>Description:</strong>
                                            <p class="text-muted"><?php echo htmlspecialchars($supplier['description']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 d-flex gap-2">
                                        <button class="btn btn-success" onclick="approveSupplier(<?php echo $supplier['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-danger" onclick="rejectSupplier(<?php echo $supplier['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewDetails(<?php echo $supplier['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    </main>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Supplier Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="supplier_id" id="rejectSupplierId">
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" name="reason" id="reason" rows="4" 
                                  placeholder="Please provide a reason for rejecting this application..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveSupplier(supplierId) {
    if (confirm('Are you sure you want to approve this supplier?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="supplier_id" value="${supplierId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectSupplier(supplierId) {
    document.getElementById('rejectSupplierId').value = supplierId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

function viewDetails(supplierId) {
    window.location.href = `supplier-details.php?id=${supplierId}`;
}
</script>

