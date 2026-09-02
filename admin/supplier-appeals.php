<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

// Fetch appeals with supplier info
$appeals = [];
try {
    $sql = "
        SELECT 
            sa.id,
            sa.supplier_id,
            sa.suspension_reason,
            sa.appeal_message,
            sa.status,
            sa.admin_notes,
            sa.created_at,
            s.business_name,
            s.owner_name,
            s.contact_number,
            CONCAT_WS(', ', NULLIF(s.barangay, ''), NULLIF(s.city, ''), NULLIF(s.province, '')) AS location,
            u.email
        FROM supplier_appeals sa
        INNER JOIN suppliers s ON sa.supplier_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        ORDER BY sa.created_at DESC
    ";
    $appeals = $database->fetchAll($sql) ?? [];
} catch (Exception $e) {
    error_log('Supplier appeals fetch failed: ' . $e->getMessage());
}

include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid px-4">  
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h1 class="h3 fw-bold text-dark mb-0">Supplier Appeals</h1>
            <div class="text-muted small">
                <?php echo count($appeals); ?> appeal<?php echo count($appeals) !== 1 ? 's' : ''; ?> pending review
            </div>
        </div>

        <?php if (empty($appeals)): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="fas fa-inbox text-primary mb-3" style="font-size: 3rem;"></i>
                    <h4 class="text-primary mb-3">No Appeals Yet</h4>
                    <p class="text-secondary">When suspended suppliers submit appeals, they will appear here for review.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($appeals as $appeal): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($appeal['business_name']); ?></h5>
                                        <div class="text-muted small">
                                            Supplier ID: <?php echo (int) $appeal['supplier_id']; ?> &middot;
                                            Appeal ID: <?php echo (int) $appeal['id']; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge rounded-pill px-3 <?php
                                            echo $appeal['status'] === 'pending' ? 'bg-warning text-dark' :
                                                 ($appeal['status'] === 'reviewed' ? 'bg-info' : 'bg-success');
                                        ?>">
                                            <?php echo ucfirst($appeal['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-3 row g-3">
                                    <div class="col-md-4">
                                        <div class="small text-muted">Contact</div>
                                        <div class="small">
                                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($appeal['email']); ?><br>
                                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($appeal['contact_number'] ?? '—'); ?><br>
                                            <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($appeal['location'] ?? '—'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Suspension Reason</div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($appeal['suspension_reason'] ?? 'Not provided.')); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Submitted</div>
                                        <p class="mb-0"><?php echo format_date($appeal['created_at']); ?></p>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div class="small text-muted mb-1">Appeal Message</div>
                                    <div class="border rounded p-3 bg-light">
                                        <?php echo nl2br(htmlspecialchars($appeal['appeal_message'])); ?>
                                    </div>
                                </div>

                                <?php if (!empty($appeal['admin_notes'])): ?>
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Admin Notes</div>
                                        <div class="border rounded p-3">
                                            <?php echo nl2br(htmlspecialchars($appeal['admin_notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>