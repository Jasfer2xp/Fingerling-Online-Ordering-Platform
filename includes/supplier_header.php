<?php

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent recursive loading
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';

// Create user instance once
if (!isset($user)) {
    $user = new User($database);
}

// Get user profile if logged in
$profile = null;
$unread_notifications_count = 0;
$unread_message_count = 0;
$isLoggedIn = false; // Add this variable

if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'supplier') {
    $isLoggedIn = true; // Set to true when user is logged in
    $profile = $user->getUserProfile($_SESSION['user_id']);
    // Get unread messages count
    $unread_message_count = 0;
    if (is_logged_in() && get_user_type() === 'supplier') {
        try {
            $user_id = get_user_id();
            // Get supplier ID
            $sql = "SELECT id FROM suppliers WHERE user_id = ?";
            $supplier_data = $database->fetch($sql, [$user_id]);
            
            if ($supplier_data) {
                // Count unread messages for this supplier
                $sql = "SELECT COUNT(*) as unread_count 
                        FROM messages m
                        JOIN conversations c ON m.conversation_id = c.id
                        WHERE c.supplier_id = ? AND m.receiver_id = ? AND m.is_read = 0";
                $result = $database->fetch($sql, [$supplier_data['id'], $user_id]);
                $unread_message_count = $result ? (int)$result['unread_count'] : 0;
            }
        } catch (Exception $e) {
            // Silently fail, unread count will remain 0
            error_log("Failed to get unread messages count: " . $e->getMessage());
        }
    }
}

// Get unread notifications count
$unread_notifications_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $notification_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $result = $database->fetch($notification_sql, [$user_id]);
    $unread_notifications_count = $result ? $result['count'] : 0;
}
    
// Get unread messages count if messaging system is available
if (file_exists(__DIR__ . '/../classes/Message.php')) {
    require_once __DIR__ . '/../classes/Message.php';
    try {
        $message_system = new Message($database);
        $unread_message_count = $message_system->getUnreadCount($user_id);
    } catch (Exception $e) {
        // If there's an error (like tables don't exist), just continue without messaging
        $unread_message_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Fingerling Seller Center'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0077b6;
            --secondary-color: #00b4d8;
            --light-gray: #f8f9fa;
            --text-dark: #343a40;
            --sidebar-width: 220px;
        }

        body {
            background-color: var(--light-gray);
            margin: 0;
        }

        /* Main Header */
        .main-header {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            height: 65px;
        }

        .header-logo {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
        }

        .header-logo:hover {
            color: var(--secondary-color);
        }

        .header-toggler {
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            background: none;
            cursor: pointer;
            z-index: 1035;
        }

        .header-toggler:focus {
            outline: none;
            box-shadow: none;
        }

        /* Notification Bell */
        .notification-bell, .message-icon {
            position: relative;
            color: #6c757d;
            font-size: 1.2rem;
            margin-right: 1rem;
            text-decoration: none;
        }

        .notification-bell:hover, .message-icon:hover {
            color: var(--primary-color);
        }

        .notification-badge, .message-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.2em 0.4em;
            font-size: 0.7em;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* User Dropdown */
        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #343a40;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        /* Main Content Area */
        .main-content {
            padding-top: 65px;
            min-height: calc(100vh - 65px);
            transition: margin-left 0.3s ease-in-out;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }
    </style>
</head>
<body>
    <header class="main-header shadow-sm">
        <div class="container-fluid h-100">
            <div class="d-flex align-items-center justify-content-between h-100">
                <div class="d-flex align-items-center">
                    <button id="sidebar-toggler" class="header-toggler me-3 d-lg-none" type="button">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="<?php echo base_url('supplier/dashboard.php'); ?>" class="header-logo">
                        <i class="fas fa-fish me-2"></i>Seller Center
                    </a>
                </div>

                <div class="d-flex align-items-center">
                    <?php if (file_exists(__DIR__ . '/../classes/Message.php')): ?>
                    <!-- Message Icon -->
                    <a href="<?php echo base_url('supplier/messages.php'); ?>" class="message-icon me-3">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_message_count > 0): ?>
                            <span class="message-badge"><?php echo $unread_message_count > 99 ? '99+' : $unread_message_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Notification Bell -->
                    <a href="<?php echo base_url('supplier/notifications.php'); ?>" class="notification-bell me-3">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications_count > 99 ? '99+' : $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <a class="user-dropdown-toggle dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($profile['business_name'] ?? 'S', 0, 1)); ?>
                            </div>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($profile['business_name'] ?? 'Supplier'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo base_url('supplier/profile.php'); ?>"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('supplier/settings.php'); ?>"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('auth/logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- Mobile Sidebar Backdrop -->
<div class="sidebar-backdrop d-none"></div>

<script>
// Ensure Bootstrap JS is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Fix dropdowns on mobile
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                e.preventDefault();
                const menu = this.nextElementSibling;
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                menu.classList.toggle('show');
            }
        });
    });

    // Auto-update message badge every 5 seconds
    function updateMessageBadge() {
        fetch('<?php echo base_url('api/messages/get_unread_count.php'); ?>')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.message-badge');
                    const messageLink = document.querySelector('a[href*="messages.php"]');
                    
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        } else if (messageLink) {
                            // Create badge if it doesn't exist
                            let newBadge = document.createElement('span');
                            newBadge.className = 'message-badge';
                            newBadge.textContent = data.count > 99 ? '99+' : data.count;
                            newBadge.style.display = 'flex';
                            messageLink.appendChild(newBadge);
                        }
                    } else {
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(() => {});
    }

    // Update badge every 5 seconds
    setInterval(updateMessageBadge, 5000);
    // Initial update after 1 second
    setTimeout(updateMessageBadge, 1000);

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });
});

// Session Check Script
(function() {
    // Only run if user is logged in
    if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
        // Check session every 3 seconds (as per requirements)
        setInterval(function() {
            fetch('../api/check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        // Show alert message
                        alert(data.message || 'Your account was logged in from another device. Please log in again if this wasn\'t you.');
                        // Redirect to login page
                        window.location.href = '../auth/login.php';
                    }
                })
                .catch(error => {
                    console.error('Session check failed:', error);
                });
        }, 3000);
    }
})();
</script>