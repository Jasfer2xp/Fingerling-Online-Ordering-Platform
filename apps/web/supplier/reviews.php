<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../classes/User.php';

if (!function_exists('resolve_feedback_image_url')) {
    /**
     * Resolve stored feedback image paths to a public URL.
     */
    function resolve_feedback_image_url($image_path)
    {
        if (empty($image_path)) {
            return '';
        }

        if (filter_var($image_path, FILTER_VALIDATE_URL)) {
            return $image_path;
        }

        $clean_path = ltrim(str_replace(['../', './', '\\'], '', $image_path), '/');
        $filename = basename($clean_path);
        if ($filename === '') {
            return '';
        }

        $app_root = realpath(__DIR__ . '/..');
        if (!$app_root) {
            return base_url('uploads/feedback/' . $filename);
        }

        $candidates = array_unique([
            $clean_path,
            'customer/' . $clean_path,
            "uploads/feedback/$filename",
            "customer/uploads/feedback/$filename"
        ]);

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $absolute = $app_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (file_exists($absolute)) {
                return base_url(ltrim($candidate, '/'));
            }
        }

        return base_url('uploads/feedback/' . $filename);
    }
}

// ====================== AJAX REPLY HANDLER (MUST BE FIRST) ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_reply') {
    header('Content-Type: application/json');

    if (!is_logged_in() || get_user_type() !== 'supplier') {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $reply       = trim($_POST['reply'] ?? '');

    if ($feedback_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid feedback ID']);
        exit;
    }

    // Get supplier_id from user profile
    $user = new User($database);
    $profile = $user->getUserProfile(get_user_id());
    $supplier_id = $profile['id'] ?? 0;

    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        exit;
    }

    // Verify this feedback belongs to this supplier
    $check = $database->fetch(
        "SELECT id FROM feedback WHERE id = ? AND supplier_id = ?",
        [$feedback_id, $supplier_id]
    );

    if (!$check) {
        echo json_encode(['success' => false, 'error' => 'Not your feedback']);
        exit;
    }

    try {
        if ($reply === '') {
            $database->query(
                "UPDATE feedback SET supplier_reply = NULL, created_at = created_at WHERE id = ?",
                [$feedback_id]
            );
        } else {
            $database->query(
                "UPDATE feedback SET supplier_reply = ? WHERE id = ?",
                [$reply, $feedback_id]
            );
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ====================== MAIN PAGE ======================
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];

// Filters & Pagination
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;
$offset        = ($page - 1) * $per_page;
$rating_filter = $_GET['rating'] ?? '';
$search        = $_GET['search'] ?? '';

$sql = "SELECT f.*, 
               c.first_name, c.last_name,
               o.order_number,
               o.created_at AS order_date
        FROM feedback f
        JOIN orders o ON f.order_id = o.id
        JOIN customers c ON f.customer_id = c.id
        WHERE f.supplier_id = ? AND f.rating IS NOT NULL";

$params = [$supplier_id];

if ($rating_filter !== '') {
    $sql .= " AND f.rating = ?";
    $params[] = $rating_filter;
}
if ($search !== '') {
    $sql .= " AND (CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR o.order_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$total = $database->fetch("SELECT COUNT(*) AS cnt " . substr($sql, strpos($sql, 'FROM')), $params)['cnt'] ?? 0;
$total_pages = ceil($total / $per_page);

$sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$reviews = $database->fetchAll($sql, $params);

// Rating Stats
$stats = $database->fetch("
    SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
    FROM feedback WHERE supplier_id = ? AND rating IS NOT NULL
", [$supplier_id]);

$avg_rating = round($stats['avg_rating'] ?? 0, 1);
$total_reviews_count = $stats['total_reviews'] ?? 0;

// Update supplier rating
$database->query("UPDATE suppliers SET rating = ?, total_ratings = ? WHERE id = ?", [$avg_rating, $total_reviews_count, $supplier_id]);

$page_title = 'Customer Reviews';
include '../includes/supplier_header.php';
?>

<?php include '../includes/supplier_sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h3 class="fw-bold"><i class="fas fa-star text-warning"></i> Customer Reviews</h3>
            <select class="form-select w-auto" id="filterRating" onchange="applyFilters()">
                <option value="">All Ratings</option>
                <option value="5" <?= $rating_filter === '5' ? 'selected' : '' ?>>5 Stars</option>
                <option value="4" <?= $rating_filter === '4' ? 'selected' : '' ?>>4 Stars</option>
                <option value="3" <?= $rating_filter === '3' ? 'selected' : '' ?>>3 Stars</option>
                <option value="2" <?= $rating_filter === '2' ? 'selected' : '' ?>>2 Stars</option>
                <option value="1" <?= $rating_filter === '1' ? 'selected' : '' ?>>1 Star</option>
            </select>
        </div>

        <!-- Rating Summary -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <h1 class="text-warning fw-bold"><?= number_format($avg_rating, 1) ?></h1>
                        <div class="mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($avg_rating) ? 'text-warning' : 'text-muted' ?> fa-2x"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mb-0"><?= $total_reviews_count ?> reviews</p>
                    </div>
                    <div class="col-md-9">
                        <?php
                        $dist = $database->fetchAll("
                            SELECT rating, COUNT(*) AS cnt
                            FROM feedback WHERE supplier_id = ? AND rating IS NOT NULL
                            GROUP BY rating ORDER BY rating DESC
                        ", [$supplier_id]);
                        $dist_arr = array_column($dist, 'cnt', 'rating');
                        $total_dist = array_sum($dist_arr);
                        for ($i = 5; $i >= 1; $i--):
                            $cnt = $dist_arr[$i] ?? 0;
                            $pct = $total_dist > 0 ? ($cnt / $total_dist) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="me-2" style="width:50px"><?= $i ?> star</span>
                                <div class="progress flex-grow-1 me-3" style="height:12px;">
                                    <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="text-muted"><?= $cnt ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="input-group mb-4" style="max-width:400px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Search customer or order..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-outline-secondary" onclick="applyFilters()">Search</button>
        </div>

        <!-- Reviews List -->
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if ($reviews): ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="border rounded p-4 mb-4 bg-light">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                    <small class="text-muted ms-2">
                                        Order #<?= htmlspecialchars($r['order_number']) ?> • <?= date('M j, Y', strtotime($r['order_date'])) ?>
                                    </small>
                                </div>
                                <small class="text-muted"><?= time_ago($r['created_at']) ?></small>
                            </div>

                            <div class="mb-3">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $r['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2 text-muted">(<?= $r['rating'] ?> stars)</span>
                            </div>

                            <?php if ($r['comment']): ?>
                                <p class="mb-3"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($r['image_path'])): ?>
                                <?php
                                $img_src = resolve_feedback_image_url($r['image_path']);
                                if (!$img_src) {
                                    $img_src = asset_url('images/placeholder-fish.jpg');
                                }
                                ?>
                                <img src="<?= htmlspecialchars($img_src) ?>" class="img-fluid rounded mb-3" style="max-height:200px;"
                                     alt="Review image"
                                     onerror="this.src='<?= asset_url('images/placeholder-fish.jpg') ?>';this.onerror=null;">
                            <?php endif; ?>

                            <!-- REPLY SECTION -->
                            <div class="mt-4 pt-3 border-top" style="min-height: 80px;">
                                <strong class="d-block mb-2">Your Reply:</strong>
                                <div id="reply-container-<?= $r['id'] ?>">
                                    <?php if ($r['supplier_reply']): ?>
                                        <div class="alert alert-success py-2 mb-2 small">
                                            <?= nl2br(htmlspecialchars($r['supplier_reply'])) ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteReply(<?= $r['id'] ?>)">
                                            Delete Reply
                                        </button>
                                    <?php else: ?>
                                        <form onsubmit="saveReply(event, <?= $r['id'] ?>)" id="reply-form-<?= $r['id'] ?>">
                                            <div class="input-group">
                                                <textarea class="form-control" name="reply_text" rows="2" placeholder="Write a polite reply..." required></textarea>
                                                <button type="submit" class="btn btn-primary">Send</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-star fa-4x mb-3"></i>
                        <h5>No reviews yet</h5>
                        <p>Customer reviews will appear here once they leave feedback.</p>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&rating=<?= $rating_filter ?>&search=<?= urlencode($search) ?>">Previous</a>
                            </li>
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&rating=<?= $rating_filter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&rating=<?= $rating_filter ?>&search=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
/* Prevent reply form from jumping - stable container height */
[id^="reply-container-"] {
    min-height: 60px;
    position: relative;
}
</style>
<script>
function applyFilters() {
    const rating = document.getElementById('filterRating').value;
    const search = document.getElementById('searchInput').value.trim();
    const params = new URLSearchParams();
    if (rating) params.set('rating', rating);
    if (search) params.set('search', search);
    params.set('page', '1');
    window.location.search = params.toString();
}

async function saveReply(e, id) {
    e.preventDefault();
    e.stopPropagation();
    
    const form = e.target;
    const textarea = form.querySelector('textarea[name="reply_text"]');
    if (!textarea) {
        alert('Reply form error');
        return;
    }
    
    const reply = textarea.value.trim();
    if (!reply) {
        alert('Please enter a reply');
        return;
    }

    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    const formData = new FormData();
    formData.append('action', 'save_reply');
    formData.append('feedback_id', id);
    formData.append('reply', reply);

    try {
        const res = await fetch(window.location.href, { 
            method: 'POST', 
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!res.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to save reply'));
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch (err) {
        console.error('Reply save error:', err);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function deleteReply(id) {
    if (!confirm('Delete your reply?')) return;

    const formData = new FormData();
    formData.append('action', 'save_reply');
    formData.append('feedback_id', id);
    formData.append('reply', '');

    fetch(window.location.href, { 
        method: 'POST', 
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Failed to delete reply: ' + (d.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Delete reply error:', err);
            alert('Network error. Please try again.');
        });
}
</script>

</body>
</html>