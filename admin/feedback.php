<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!function_exists('feedback_image_url')) {
    function feedback_image_url($image_path)
    {
        if (empty($image_path)) {
            return '';
        }

        if (filter_var($image_path, FILTER_VALIDATE_URL)) {
            return $image_path;
        }

        $clean_path = ltrim(str_replace(['../', './', '\\'], '', (string) $image_path), '/');
        $filename = basename($clean_path);
        if ($filename === '') {
            return '';
        }

        $root = realpath(__DIR__ . '/..');
        $candidates = array_unique([
            $clean_path,
            'customer/' . $clean_path,
            'uploads/feedback/' . $filename,
            'customer/uploads/feedback/' . $filename
        ]);

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (file_exists($absolute)) {
                return base_url(ltrim($candidate, '/'));
            }
        }

        return base_url('uploads/feedback/' . $filename);
    }
}

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Pagination + Search
$search_term = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 4;

$feedback_from_clause = "FROM feedback f
            JOIN customers c ON f.customer_id = c.id
            JOIN suppliers s ON f.supplier_id = s.id
            JOIN orders o ON f.order_id = o.id";

$where_clause = '';
$query_params = [];

if ($search_term !== '') {
    $like = '%' . $search_term . '%';
    $where_clause = " WHERE (
        CONCAT_WS(' ', c.first_name, c.last_name) LIKE ?
        OR s.business_name LIKE ?
        OR f.comment LIKE ?
        OR o.order_number LIKE ?
        OR CAST(o.id AS CHAR) LIKE ?
    )";
    $query_params = [$like, $like, $like, $like, $like];
}

$count_sql = "SELECT COUNT(*) as total {$feedback_from_clause}{$where_clause}";
$total_feedback_row = $database->fetch($count_sql, $query_params);
$total_feedback = (int) ($total_feedback_row['total'] ?? 0);
$raw_total_pages = (int) ceil($total_feedback / $limit);
$max_available_page = $raw_total_pages > 0 ? $raw_total_pages : 1;

if ($page > $max_available_page) {
    $page = $max_available_page;
}

$offset = ($page - 1) * $limit;

$data_sql = "SELECT 
                f.id,
                f.rating,
                f.comment as review_text,
                f.created_at,
                f.image_path,
                c.first_name,
                c.last_name,
                s.business_name,
                o.order_number,
                o.id as order_id,
                s.id as supplier_id
            {$feedback_from_clause}
            {$where_clause}
            ORDER BY f.created_at DESC
            LIMIT ? OFFSET ?";

$data_params = array_merge($query_params, [$limit, $offset]);
$all_feedback = $database->fetchAll($data_sql, $data_params);
$total_pages = $max_available_page;

// Get feedback statistics
$sql = "SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as total_reviews,
            COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_reviews,
            COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_reviews
        FROM feedback";
$feedback_stats = $database->fetch($sql);

$page_title = 'Customer Feedback Management';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid px-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h1 class="h3 fw-bold text-dark mb-0">Customer Feedback Management</h1>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" onclick="refreshFeedback()">
                    <i class="fas fa-sync"></i>
                    <span class="d-none d-sm-inline">Refresh</span>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" onclick="exportFeedback()">
                    <i class="fas fa-download"></i>
                    <span class="d-none d-sm-inline">Export CSV</span>
                </button>
            </div>
        </div>

        <!-- Feedback Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Total Reviews</h6>
                            <h3 class="mb-0 fw-bold text-primary"><?php echo number_format($feedback_stats['total_reviews']); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                            <i class="fas fa-star fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Average Rating</h6>
                            <h3 class="mb-0 fw-bold text-success">
                                <?php echo number_format($feedback_stats['avg_rating'] ?? 0, 1); ?>/5.0
                            </h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                            <i class="fas fa-chart-line fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Positive Reviews</h6>
                            <h3 class="mb-0 fw-bold text-info">
                                <?php 
                                $positive_rate = $feedback_stats['total_reviews'] > 0 ? 
                                    ($feedback_stats['positive_reviews'] / $feedback_stats['total_reviews']) * 100 : 0;
                                echo number_format($positive_rate, 1); 
                                ?>%
                            </h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                            <i class="fas fa-thumbs-up fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="modern-stat-card bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase mb-1">Negative Reviews</h6>
                            <h3 class="mb-0 fw-bold text-warning">
                                <?php 
                                $negative_rate = $feedback_stats['total_reviews'] > 0 ? 
                                    ($feedback_stats['negative_reviews'] / $feedback_stats['total_reviews']) * 100 : 0;
                                echo number_format($negative_rate, 1); 
                                ?>%
                            </h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                            <i class="fas fa-thumbs-down fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-12 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0"
                                   placeholder="Search by customer, supplier, comment, or order ID"
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                    <div class="col-6 col-md-2">
                        <a href="feedback.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
                <?php if ($search_term !== ''): ?>
                    <div class="mt-2 text-muted small">
                        Showing results for "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Feedback List -->
        <?php if (empty($all_feedback)): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                    <h4 class="text-primary mb-3">No Feedback Yet</h4>
                    <p class="text-secondary mb-4">Customer reviews will appear here once they submit feedback.</p>
                    <button class="btn btn-outline-primary" onclick="refreshFeedback()">
                        <i class="fas fa-sync me-1"></i> Check Again
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-semibold text-dark">
                        <i class="fas fa-comments"></i> Customer Reviews
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($all_feedback as $f): 
                        $is_negative = $f['rating'] <= 2;
                    ?>
                    <div class="p-4 border-bottom <?php echo $is_negative ? 'bg-warning bg-opacity-5' : ''; ?>">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1 fw-semibold">
                                            <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?>
                                            <span class="text-muted small">reviewed</span>
                                            <strong><?php echo htmlspecialchars($f['business_name']); ?></strong>
                                        </h6>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $f['rating'] ? 'text-warning' : 'text-muted'; ?> small"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-muted small"><?php echo $f['rating']; ?>/5</span>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo format_date($f['created_at']); ?></small>
                                </div>

                                <?php if ($f['review_text']): ?>
                                    <p class="mb-2 text-dark"><?php echo nl2br(htmlspecialchars($f['review_text'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-2 text-muted fst-italic">No written review</p>
                                <?php endif; ?>

                                <small class="text-muted">
                                    <i class="fas fa-receipt"></i> Order #<?php echo htmlspecialchars($f['order_number']); ?>
                                </small>
                                
                                <?php if (!empty($f['image_path'])): ?>
                                    <div class="mt-2">
                                        <strong>Image Attachment:</strong>
                                        <div class="mt-1">
                                            <?php
                                            $feedback_image_src = feedback_image_url($f['image_path']);
                                            ?>
                                            <?php if ($feedback_image_src): ?>
                                                <a href="<?php echo htmlspecialchars($feedback_image_src); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($feedback_image_src); ?>" 
                                                         alt="Feedback Image" 
                                                         class="img-fluid rounded" 
                                                         style="max-height: 150px; max-width: 200px; object-fit: cover;"
                                                         onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';this.onerror=null;">
                                                </a>
                                            <?php else: ?>
                                                <img src="<?php echo asset_url('images/placeholder-fish.jpg'); ?>" 
                                                     alt="Feedback Image" 
                                                     class="img-fluid rounded" 
                                                     style="max-height: 150px; max-width: 200px; object-fit: cover;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-grid gap-2 mt-3 mt-lg-0">
                                    <a href="order-details.php?id=<?php echo $f['order_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View Order
                                    </a>
                                    <a href="supplier-details.php?id=<?php echo $f['supplier_id']; ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-store"></i> View Supplier
                                    </a>
                                    <?php if ($is_negative): ?>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="flagReview(<?php echo $f['id']; ?>)">
                                            <i class="fas fa-flag"></i> Flag Review
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Delete Feedback Button -->
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteFeedback(<?php echo $f['id']; ?>, '<?php echo $_SESSION['csrf_token']; ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pagination -->
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<script>
function refreshFeedback() {
    location.reload();
}

function exportFeedback() {
    const feedback = <?php echo json_encode($all_feedback); ?>;
    let csv = 'Customer,Supplier,Rating,Review,Order,Date\n';
    
    feedback.forEach(item => {
        const review = (item.review_text || '').replace(/"/g, '""');
        const row = [
            `"${item.first_name} ${item.last_name}"`,
            `"${item.business_name}"`,
            item.rating,
            `"${review}"`,
            item.order_number,
            item.created_at
        ].join(',');
        csv += row + '\n';
    });
    
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `feedback_export_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

function flagReview(feedbackId) {
    if (confirm('Flag this review for admin investigation?')) {
        // Placeholder for future AJAX call
        alert('Review flagged successfully.');
    }
}

function deleteFeedback(feedbackId, csrfToken) {
    if (confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
        // Send AJAX request to delete the feedback
        fetch('../api/admin/delete_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                action: 'delete_feedback',
                feedback_id: feedbackId,
                csrf_token: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('Feedback deleted successfully');
                // Reload the page to reflect changes
                location.reload();
            } else {
                // Show error message
                alert('Error deleting feedback: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting feedback: ' + error.message);
        });
    }
}
</script>

<style>
/* Card Style */
.modern-stat-card {
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 16px;
    transition: all 0.3s ease;
    padding: 1.25rem;
}
.modern-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}

/* Icons */
.stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Feedback Item */
.border-bottom:last-child {
    border-bottom: none !important;
}

/* Stars */
.fa-star {
    font-size: 0.9rem;
}
.fa-star.text-warning { color: #ffc107 !important; }
.fa-star.text-muted { color: #dee2e6 !important; }

/* Responsive */
@media (max-width: 768px) {
    .stat-icon {
        width: 40px;
        height: 40px;
    }
    .btn-sm {
        font-size: 0.8rem;
    }
}
</style>

</body>
</html>