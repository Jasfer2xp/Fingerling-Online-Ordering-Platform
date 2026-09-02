<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Ensure Manila timezone is set
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

// Get notifications (using customer_id)
$sql = "SELECT * FROM notifications 
        WHERE customer_id = ? 
        ORDER BY created_at DESC";
$notifications = $database->fetchAll($sql, [$customer_id]);

// Mark all notifications as read (using customer_id)
$update_sql = "UPDATE notifications SET is_read = 1 WHERE customer_id = ?";
$database->query($update_sql, [$customer_id]);

$page_title = 'Notifications';
include '../includes/customer_header.php';
?>

<!-- ===================== NOTIFICATIONS PAGE ===================== -->
<main class="dashboard-wrapper">
    <div class="container dashboard-container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 py-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-bell text-primary me-3" style="font-size: 1.5rem;"></i>
                            <h3 class="mb-0 fw-bold text-dark">Notifications</h3>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash text-muted" style="font-size: 3.5rem;"></i>
                                <p class="mt-3 text-muted fs-5">No notifications yet</p>
                                <p class="text-muted small">We'll let you know when something important happens.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item px-0 py-3 border-start-0 border-end-0 <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-semibold <?php echo !$notification['is_read'] ? 'text-primary' : 'text-dark'; ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <p class="mb-1 text-muted small lh-sm">
                                                    <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php 
                                                    // Format timestamp in Manila timezone
                                                    $dt = new DateTime($notification['created_at'], new DateTimeZone('Asia/Manila'));
                                                    echo $dt->format('M j, Y \a\t g:i A');
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="ms-3 text-end">
                                                <small class="text-muted d-block"><?php echo time_ago($notification['created_at']); ?></small>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary rounded-pill mt-1">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ===================== STYLES ===================== -->
<style>
/* Reusing dashboard base styles with narrower container */
.dashboard-wrapper {
    background: linear-gradient(to bottom right, #e0f7ff, #f8fbff);
    padding: 3rem 0;
    min-height: calc(100vh - 140px);
}

.dashboard-container {
    max-width: 1400px; /* Reduced from 1200px for a cozier feel */
    margin: 0 auto;
    padding: 0 1rem;
}

/* Card enhancements */
.card {
    transition: transform 0.2s ease;
}
.card:hover {
    transform: translateY(-2px);
}

/* List group item styling */
.list-group-item {
    transition: background-color 0.2s ease;
}
.list-group-item:hover {
    background-color: #f8f9fa !important;
}

/* Badge for unread */
.badge {
    font-size: 0.65rem;
    padding: 0.35em 0.55em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 0 0.75rem;
    }
    .card-header h3 {
        font-size: 1.25rem;
    }
}
</style>

<!-- ===================== FOOTER ===================== -->
<?php include '../includes/customer_footer.php'; ?>