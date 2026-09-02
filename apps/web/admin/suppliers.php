<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../includes/send_supplier_status_email.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle POST actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'approve':
                $admin->approveSupplier($supplier_id);
                $_SESSION['success'] = 'Supplier approved successfully.';
                break;
            case 'reject':
                $reason = sanitize_input($_POST['reason'] ?? '');
                $admin->rejectSupplier($supplier_id, $reason);
                $_SESSION['success'] = 'Supplier rejected successfully.';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    redirect(base_url('admin/suppliers.php' . (isset($_GET['status']) ? '?status=' . $_GET['status'] : '')));
}

// Handle AJAX for suspend/reactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'))) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');

    error_log("AJAX Request - Action: $action, Supplier ID: $supplier_id");

    try {
        if ($action === 'suspend_supplier') {
            $admin->updateSupplierStatus($supplier_id, 'suspended', $admin_notes);
            $email_result = send_supplier_status_email($supplier_id, 'suspended', $admin_notes);
            if (!$email_result['success']) {
                error_log("Failed to send suspension email to supplier {$supplier_id}: " . $email_result['error']);
            }
            echo json_encode(['success' => true, 'message' => 'Supplier suspended successfully', 'new_status' => 'suspended']);
        } 
        elseif ($action === 'reactivate_supplier') {
            // === CRITICAL FIX: Delete all previous appeals when reactivating ===
            $delete_appeals = $pdo->prepare("DELETE FROM supplier_appeals WHERE supplier_id = ?");
            $delete_appeals->execute([$supplier_id]);

            // Now reactivate the supplier
            $admin->updateSupplierStatus($supplier_id, 'approved', $admin_notes);
            $email_result = send_supplier_status_email($supplier_id, 'reactivated', $admin_notes);
            if (!$email_result['success']) {
                error_log("Failed to send reactivation email to supplier {$supplier_id}: " . $email_result['error']);
            }
            echo json_encode(['success' => true, 'message' => 'Supplier reactivated successfully. Previous appeals cleared.', 'new_status' => 'approved']);
        } 
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } catch (Exception $e) {
        error_log("Supplier status update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$filters = ['user_type' => 'supplier'];
if ($status_filter) $filters['status'] = $status_filter;
if ($search) $filters['search'] = $search;

$suppliers = $admin->getUsers($filters);
$pending_count = count($admin->getPendingSuppliers());

$page_title = 'Suppliers';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- ====================== RESPONSIVE STYLES ====================== -->
<style>
    .admin-main-content { padding: 1rem; }
    .card { border-radius: 12px; overflow:hidden; }
    .table th { font-weight: 600; color:#374151; }
    .btn-group-sm .btn { padding:.35rem .5rem; }

    /* ---- Mobile table collapse ---- */
    @media (max-width: 992px) {
        .desktop-only { display:none; }
        .mobile-only { display:block !important; }
        .mobile-row-details { background:#f8f9fa; padding:.75rem; font-size:.9rem; }
        .mobile-row-details .row { margin-bottom:.25rem; }
    }
    .mobile-only { display:none; }

    /* ---- No horizontal scroll ---- */
    .table-responsive { -webkit-overflow-scrolling:touch; }
    body { overflow-x:hidden; }

    /* ---- Nav pills wrap nicely ---- */
    .nav-pills .nav-link { white-space:nowrap; }
</style>

<main class="admin-main-content">
    <div class="container-fluid px-0 px-md-4">

        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 mt-3">
            <h1 class="h3 fw-bold text-dark mb-2 mb-md-0">Supplier Management</h1>
            <div class="text-muted small">Updated just now</div>
        </div>

        <!-- Status Tabs -->
        <div class="mb-4">
            <div class="nav nav-pills flex-wrap gap-2" role="tablist">
                <a href="suppliers.php"
                   class="nav-link flex-fill text-center <?php echo !$status_filter ? 'active' : ''; ?>">
                    All <span class="badge bg-secondary ms-1"><?php echo count($admin->getUsers(['user_type' => 'supplier'])); ?></span>
                </a>
                <a href="suppliers.php?status=pending"
                   class="nav-link flex-fill text-center <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="badge bg-warning ms-1"><?php echo $pending_count; ?></span>
                </a>
                <a href="suppliers.php?status=approved"
                   class="nav-link flex-fill text-center <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    Approved <span class="badge bg-success ms-1"><?php echo count($admin->getUsers(['user_type' => 'supplier', 'status' => 'approved'])); ?></span>
                </a>
                <a href="suppliers.php?status=suspended"
                   class="nav-link flex-fill text-center <?php echo $status_filter === 'suspended' ? 'active' : ''; ?>">
                    Suspended <span class="badge bg-danger ms-1"><?php echo count($admin->getUsers(['user_type' => 'supplier', 'status' => 'suspended'])); ?></span>
                </a>
            </div>
        </div>

        <!-- Search -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-12 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white">Search</span>
                            <input type="search" name="search" class="form-control border-start-0"
                                   placeholder="Name, email, business…" value="<?php echo htmlspecialchars($search); ?>">
                            <?php if ($status_filter): ?>
                                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-md-end">
                        <button type="submit" class="btn btn-primary me-2 w-100 w-md-auto">Search</button>
                        <a href="suppliers.php<?php echo $status_filter ? '?status=' . $status_filter : ''; ?>"
                           class="btn btn-outline-secondary w-100 w-md-auto mt-1 mt-md-0">Reset</a>
                    </div>
                </form>
                <div class="mt-2 text-muted small">
                    Showing <?php echo count($suppliers); ?> supplier<?php echo count($suppliers) !== 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Suppliers Table -->
        <?php if (empty($suppliers)): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <h4 class="text-primary mb-3">
                        <?php echo $status_filter ? ucfirst($status_filter) . ' Suppliers Not Found' : 'No Suppliers Yet'; ?>
                    </h4>
                    <p class="text-secondary mb-4">
                        <?php echo $status_filter ? "No suppliers with '$status_filter' status." : 'New suppliers will appear here when they register.'; ?>
                    </p>
                    <a href="suppliers.php" class="btn btn-primary">Back to All Suppliers</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <!-- ==== DESKTOP TABLE ==== -->
                        <table class="table table-hover align-middle mb-0 d-none d-lg-table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Business</th>
                                    <th>Owner</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $s): ?>
                                <tr data-supplier-id="<?php echo $s['supplier_id']; ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                                 style="width:38px;height:38px;font-size:0.9rem;">
                                                <?php echo strtoupper(substr($s['business_name'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($s['business_name'] ?? 'N/A'); ?></div>
                                                <small class="text-muted">ID: #<?php echo $s['supplier_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['owner_name'] ?? 'N/A'); ?></td>
                                    <td><a href="mailto:<?php echo $s['email']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($s['email']); ?></a></td>
                                    <td><?php echo htmlspecialchars($s['supplier_contact'] ?? '—'); ?></td>
                                    <td><small><?php echo htmlspecialchars(($s['barangay'] ?? '') . ($s['barangay'] && ($s['city'] || $s['province']) ? ', ' : '') . ($s['city'] ?? '') . ($s['city'] && $s['province'] ? ', ' : '') . ($s['province'] ?? '')); ?></small></td>
                                    <td><?php echo format_date($s['created_at']); ?></td>
                                    <td>
                                        <span class="badge rounded-pill px-3 <?php
                                            echo $s['supplier_status'] === 'approved' ? 'bg-success' :
                                                 ($s['supplier_status'] === 'pending' ? 'bg-warning' : 'bg-danger');
                                        ?>">
                                            <?php echo ucfirst($s['supplier_status'] ?? '—'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="supplier-details.php?id=<?php echo $s['supplier_id']; ?>" class="btn btn-outline-primary btn-sm">View</a>

                                            <?php if ($s['supplier_status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm" onclick="approveSupplier(<?php echo $s['supplier_id']; ?>)">Approve</button>
                                                <button class="btn btn-danger btn-sm" onclick="rejectSupplier(<?php echo $s['supplier_id']; ?>)">Reject</button>
                                            <?php elseif ($s['supplier_status'] === 'approved'): ?>
                                                <button class="btn btn-warning btn-sm" onclick="actOnSupplier(<?php echo $s['supplier_id']; ?>, '<?php echo addslashes($s['business_name']); ?>', 'suspend')">Suspend</button>
                                            <?php elseif ($s['supplier_status'] === 'suspended'): ?>
                                                <button class="btn btn-success btn-sm" onclick="actOnSupplier(<?php echo $s['supplier_id']; ?>, '<?php echo addslashes($s['business_name']); ?>', 'reactivate')">Reactivate</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- ==== MOBILE CARD LIST ==== -->
                        <div class="d-lg-none">
                            <?php foreach ($suppliers as $s): ?>
                            <div class="border-bottom p-3" data-supplier-id="<?php echo $s['supplier_id']; ?>">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                         style="width:36px;height:36px;font-size:0.85rem;">
                                        <?php echo strtoupper(substr($s['business_name'] ?? 'S', 0, 1)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($s['business_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted">ID: #<?php echo $s['supplier_id']; ?></small>
                                    </div>
                                    <span class="badge rounded-pill px-2 <?php
                                        echo $s['supplier_status'] === 'approved' ? 'bg-success' :
                                             ($s['supplier_status'] === 'pending' ? 'bg-warning' : 'bg-danger');
                                    ?>">
                                        <?php echo ucfirst($s['supplier_status'] ?? '—'); ?>
                                    </span>
                                </div>

                                <div class="small text-muted mb-2">
                                    <strong>Owner:</strong> <?php echo htmlspecialchars($s['owner_name'] ?? 'N/A'); ?><br>
                                    <strong>Email:</strong> <a href="mailto:<?php echo $s['email']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($s['email']); ?></a><br>
                                    <strong>Contact:</strong> <?php echo htmlspecialchars($s['supplier_contact'] ?? '—'); ?><br>
                                    <strong>Location:</strong> <?php echo htmlspecialchars(($s['barangay'] ?? '') . ($s['barangay'] && ($s['city'] || $s['province']) ? ', ' : '') . ($s['city'] ?? '') . ($s['city'] && $s['province'] ? ', ' : '') . ($s['province'] ?? '')); ?><br>
                                    <strong>Registered:</strong> <?php echo format_date($s['created_at']); ?>
                                </div>

                                <div class="d-flex flex-wrap gap-1">
                                    <a href="supplier-details.php?id=<?php echo $s['supplier_id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">View</a>

                                    <?php if ($s['supplier_status'] === 'pending'): ?>
                                        <button class="btn btn-success btn-sm flex-fill" onclick="approveSupplier(<?php echo $s['supplier_id']; ?>)">Approve</button>
                                        <button class="btn btn-danger btn-sm flex-fill" onclick="rejectSupplier(<?php echo $s['supplier_id']; ?>)">Reject</button>
                                    <?php elseif ($s['supplier_status'] === 'approved'): ?>
                                        <button class="btn btn-warning btn-sm flex-fill" onclick="actOnSupplier(<?php echo $s['supplier_id']; ?>, '<?php echo addslashes($s['business_name']); ?>', 'suspend')">Suspend</button>
                                    <?php elseif ($s['supplier_status'] === 'suspended'): ?>
                                        <button class="btn btn-success btn-sm flex-fill" onclick="actOnSupplier(<?php echo $s['supplier_id']; ?>, '<?php echo addslashes($s['business_name']); ?>', 'reactivate')">Reactivate</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- ====================== MODALS (unchanged) ====================== -->
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
                        <label for="reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" id="reason" rows="4" placeholder="Please provide a reason..." required></textarea>
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

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="actionForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action_type">
                    <input type="hidden" name="supplier_id" id="action_supplier_id">
                    <input type="hidden" name="ajax" value="1">
                    <p id="action_message"></p>
                    <div class="mb-3">
                        <label class="form-label" id="reason_label">Reason for suspending <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="admin_notes" id="admin_notes" rows="3" placeholder="Please provide a reason..." required></textarea>
                        <div class="invalid-feedback">Reason is required for suspension.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="action_submit">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Approve Supplier
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

// Reject Supplier Modal
function rejectSupplier(supplierId) {
    document.getElementById('rejectSupplierId').value = supplierId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function actOnSupplier(id, name, action) {
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    const title = document.getElementById('actionModalTitle');
    const msg = document.getElementById('action_message');
    const submit = document.getElementById('action_submit');
    const typeInput = document.getElementById('action_type');
    const idInput = document.getElementById('action_supplier_id');
    const reasonLabel = document.getElementById('reason_label');
    const textarea = document.getElementById('admin_notes');

    idInput.value = id;
    typeInput.value = action === 'suspend' ? 'suspend_supplier' : 'reactivate_supplier';

    if (action === 'suspend') {
        title.textContent = 'Suspend Supplier';
        msg.innerHTML = `Suspend <strong>${name}</strong>? They will lose access temporarily.`;
        reasonLabel.innerHTML = 'Reason for suspending <span class="text-danger">*</span>';
        textarea.setAttribute('required', 'required');
        textarea.placeholder = 'Please provide a reason...';
        submit.className = 'btn btn-warning';
        submit.textContent = 'Suspend';
    } else {
        title.textContent = 'Reactivate Supplier';
        msg.innerHTML = `Reactivate <strong>${name}</strong>? They will regain access.`;
        reasonLabel.textContent = 'Admin Notes (Optional)';
        textarea.removeAttribute('required');
        textarea.placeholder = 'Add notes...';
        submit.className = 'btn btn-success';
        submit.textContent = 'Reactivate';
    }
    modal.show();
}

// AJAX Submit
document.getElementById('actionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (document.getElementById('action_type').value === 'suspend_supplier' && !document.getElementById('admin_notes').value.trim()) {
        document.getElementById('admin_notes').classList.add('is-invalid');
        return;
    }
    document.getElementById('admin_notes').classList.remove('is-invalid');

    const formData = new FormData(this);
    const submitBtn = document.getElementById('action_submit');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';

    fetch('suppliers.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(err => {
        console.error('AJAX Error:', err);
        alert('Failed to update status.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});
</script>

</body>
</html>