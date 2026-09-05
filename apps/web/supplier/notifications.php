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

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'mark_read':
            $notification_id = intval($_POST['notification_id']);
            $sql = "UPDATE notifications SET is_read = true WHERE id = ? AND user_id = ?";
            $database->query($sql, [$notification_id, $user_id]);
            // For AJAX requests, just exit
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                exit;
            }
            $_SESSION['success'] = 'Notification marked as read.';
            redirect(base_url('supplier/notifications.php'));
            break;
            
        case 'mark_all_read':
            $sql = "UPDATE notifications SET is_read = true WHERE user_id = ?";
            $database->query($sql, [$user_id]);
            $_SESSION['success'] = 'All notifications marked as read.';
            break;
            
        case 'delete':
            $notification_id = intval($_POST['notification_id']);
            $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $database->query($sql, [$notification_id, $user_id]);
            // For AJAX requests, just exit
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                exit;
            }
            $_SESSION['success'] = 'Notification deleted.';
            redirect(base_url('supplier/notifications.php'));
            break;
            
        case 'delete_all_read':
            $sql = "DELETE FROM notifications WHERE user_id = ? AND is_read = true";
            $database->query($sql, [$user_id]);
            $_SESSION['success'] = 'All read notifications deleted.';
            break;
    }
    
    // Only redirect for non-AJAX requests
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        redirect(base_url('supplier/notifications.php'));
    }
}

// Get notifications with pagination
$page = $_GET['page'] ?? 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$total_notifications = $database->fetch($sql, [$user_id])['total'];
$total_pages = ceil($total_notifications / $per_page);

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$notifications = $database->fetchAll($sql, [$user_id, $per_page, $offset]);

$page_title = 'Notifications';
include '../includes/supplier_header.php';
?>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="notifications-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="fw-bold"><i class="fas fa-bell me-2"></i>Notifications</h3>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" onclick="markAllAsRead(); return false;">
                                <i class="fas fa-check-circle text-success me-2"></i>Mark all as read
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="deleteReadNotifications(); return false;">
                                <i class="fas fa-trash text-danger me-2"></i>Delete read notifications
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <section class="notifications-content">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <div class="input-group input-group-sm" style="max-width: 300px;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search notifications..." onkeyup="filterNotifications()">
                            <button class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">Sort by:</span>
                            <select class="form-select form-select-sm" id="sortSelect" onchange="sortNotifications()" style="width: auto;">
                                <option value="date-desc">Date (Newest)</option>
                                <option value="date-asc">Date (Oldest)</option>
                                <option value="read">Read Status</option>
                            </select>
                        </div>
                    </div>

                    <div class="notifications-list">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                // Extract order ID if this notification is related to an order
                                $order_id = null;
                                if (preg_match('/order[ #]*#?(\d+)/i', $notification['message'], $matches)) {
                                    $order_id = $matches[1];
                                } else if (preg_match('/order[ #]*#?(\d+)/i', $notification['title'], $matches)) {
                                    $order_id = $matches[1];
                                } else if (preg_match('/#(\d+)/', $notification['message'], $matches)) {
                                    $order_id = $matches[1];
                                }
                                ?>
                                <div class="notification-item border-bottom py-3 position-relative <?php echo $notification['is_read'] ? 'text-muted' : ''; ?>" 
                                     data-type="<?php echo $notification['type']; ?>" 
                                     data-date="<?php echo $notification['created_at']; ?>" 
                                     data-read="<?php echo $notification['is_read'] ? '1' : '0'; ?>"
                                     data-id="<?php echo $notification['id']; ?>"
                                     data-order-id="<?php echo $order_id; ?>"
                                     style="cursor: pointer;"
                                     onclick="handleNotificationClick(event, <?php echo $notification['id']; ?>, <?php echo $order_id ? "'".$order_id."'" : 'null'; ?>)"
                                     oncontextmenu="showContextMenu(event, <?php echo $notification['id']; ?>); return false;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-start flex-grow-1 pe-5">
                                            <i class="fas fa-<?php echo getNotificationIcon($notification['type']); ?> fa-lg me-3 mt-1 text-primary"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <p class="mb-2 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo format_date($notification['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Dropdown Button (3 dots) -->
                                        <div class="dropdown notification-dropdown" onclick="event.stopPropagation();">
                                            <button class="btn btn-link text-muted p-1 dropdown-toggle-no-caret" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false"
                                                    style="font-size: 1.2rem; line-height: 1;">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <?php if (!$notification['is_read']): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" class="dropdown-item small">
                                                                <i class="fas fa-check me-2"></i>Mark as Read
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger small" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5>No Notifications</h5>
                                <p class="text-muted">You don't have any notifications yet. We'll notify you about important updates!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center flex-wrap">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Context menu for right-click actions -->
<div id="contextMenu" class="dropdown-menu shadow" style="position: fixed; display: none; z-index: 10000;">
    <a class="dropdown-item text-danger small" href="#" onclick="deleteNotification(selectedNotificationId); return false;">
        <i class="fas fa-trash me-2"></i>Delete
    </a>
</div>

<style>
    .alert {
        border-radius: 0.5rem;
        font-size: 0.9rem;
    }
    .notification-item {
        transition: background-color 0.2s ease;
    }
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    .notification-item .dropdown-toggle-no-caret::after {
        display: none;
    }
    .notification-dropdown .dropdown-menu {
        min-width: 160px;
    }
    .pagination .page-link {
        color: #007bff;
        padding: 0.4rem 0.7rem;
    }
    .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }

    /* Mobile Optimizations */
    @media (max-width: 576px) {
        .container-fluid {
            padding: 0.5rem;
        }
        .notifications-header h3 {
            font-size: 1.3rem;
        }
        .input-group {
            max-width: 100% !important;
        }
        .form-select, .btn {
            font-size: 0.85rem;
            padding: 0.35rem 0.5rem;
        }
        .notification-item h6 {
            font-size: 0.95rem;
        }
        .notification-item p {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        .notification-item small {
            font-size: 0.7rem;
        }
        .notification-dropdown button {
            font-size: 1.1rem;
            padding: 0.25rem !important;
        }
        .dropdown-menu {
            font-size: 0.85rem;
        }
        .pagination {
            font-size: 0.8rem;
        }
        .page-link {
            padding: 0.3rem 0.5rem;
        }
    }

    @media (max-width: 400px) {
        .d-flex.justify-content-between {
            flex-direction: column;
            align-items: stretch !important;
        }
        .notification-item .d-flex {
            flex-direction: column;
        }
        .notification-dropdown {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variable to store the ID of the notification being right-clicked
let selectedNotificationId = null;

// Handle notification click (mark as read and redirect)
function handleNotificationClick(event, notificationId, orderId) {
    // Prevent if clicking inside dropdown
    if (event.target.closest('.notification-dropdown') || event.target.closest('.dropdown-toggle')) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'notifications.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            updateNotificationCount();
            if (orderId) {
                window.location.href = 'order-details.php?id=' + orderId;
            } else {
                location.reload();
            }
        }
    };
    xhr.send('action=mark_read&notification_id=' + notificationId);
}

// Show context menu on right-click
function showContextMenu(event, notificationId) {
    event.preventDefault();
    selectedNotificationId = notificationId;

    const contextMenu = document.getElementById('contextMenu');
    contextMenu.style.display = 'block';
    contextMenu.style.left = event.pageX + 'px';
    contextMenu.style.top = event.pageY + 'px';

    document.addEventListener('click', function hideMenu() {
        contextMenu.style.display = 'none';
        document.removeEventListener('click', hideMenu);
    }, { once: true });
}

// Delete notification via AJAX
function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification?')) return;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'notifications.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
        if (xhr-readyState === 4 && xhr.status === 200) {
            updateNotificationCount();
            const el = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (el) el.remove();
        }
    };
    xhr.send('action=delete&notification_id=' + notificationId);
}

function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'mark_all_read';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteReadNotifications() {
    if (confirm('Delete all read notifications? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete_all_read';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function filterNotifications() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const items = document.querySelectorAll('.notification-item');

    items.forEach(item => {
        const title = item.querySelector('h6').textContent.toLowerCase();
        const message = item.querySelector('p').textContent.toLowerCase();
        const matches = title.includes(search) || message.includes(search);
        item.style.display = matches ? '' : 'none';
    });
}

function sortNotifications() {
    const select = document.getElementById('sortSelect').value;
    const list = document.querySelector('.notifications-list');
    const items = Array.from(list.querySelectorAll('.notification-item'));

    items.sort((a, b) => {
        if (select === 'date-asc') return new Date(a.dataset.date) - new Date(b.dataset.date);
        if (select === 'date-desc') return new Date(b.dataset.date) - new Date(a.dataset.date);
        if (select === 'read') return parseInt(a.dataset.read) - parseInt(b.dataset.read);
        return 0;
    });

    list.innerHTML = '';
    items.forEach(item => list.appendChild(item));
}

// Update notification count in header
function updateNotificationCount() {
    const badge = document.querySelector('.notification-count');
    if (badge) {
        let count = parseInt(badge.textContent) || 0;
        if (count > 0) {
            count--;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }
}

// DOM Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Fix Bootstrap dropdown on mobile touch
    document.querySelectorAll('.notification-dropdown .dropdown-toggle').forEach(toggle => {
        let touchStart = null;

        toggle.addEventListener('touchstart', function(e) {
            touchStart = Date.now();
        }, { passive: true });

        toggle.addEventListener('touchend', function(e) {
            if (Date.now() - touchStart < 500) {
                e.preventDefault();
                const menu = this.nextElementSibling;
                const isOpen = menu.classList.contains('show');
                
                // Close all others
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });

                // Toggle current
                menu.classList.toggle('show', !isOpen);
            }
        });

        // Desktop click fallback
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth >= 768) return;
            e.preventDefault();
            e.stopPropagation();
            const menu = this.nextElementSibling;
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // Sidebar toggle (if exists)
    const toggler = document.getElementById('sidebar-toggler');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }
});
</script>

<?php
function getNotificationIcon($type) {
    return match ($type) {
        'order' => 'shopping-cart',
        'payment' => 'credit-card',
        'system' => 'cog',
        'promotion' => 'bullhorn',
        default => 'bell',
    };
}
?>
</body>
</html>