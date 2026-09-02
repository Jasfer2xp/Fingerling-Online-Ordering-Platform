<?php

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
$isLoggedIn = false;
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'admin') {
    $isLoggedIn = true;
}

// === SESSION TAKEOVER DETECTION (ADMIN) ===
// This runs on every page load — same logic as config.php but safe for header
$show_takeover_alert = false;
if ($isLoggedIn && isset($_SESSION['session_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/User.php';
    
    $database = new Database();
    $user_id = $_SESSION['user_id'];
    $session_id = $_SESSION['session_id'];

    $valid = $database->fetch(
        "SELECT id FROM users WHERE id = ? AND current_session_id = ?",
        [$user_id, $session_id]
    );

    if (!$valid) {
        // Kill only this session (DO NOT touch DB current_session_id)
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // Mark to show alert
        $show_takeover_alert = true;

        // Log event
        error_log("ADMIN SESSION TAKEOVER - User ID: $user_id | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    // Clean expired sessions
    $database->query("DELETE FROM user_sessions WHERE expires_at < NOW()");
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-role="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($page_title ?? 'Admin') . ' - ' . APP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../assets/icons/fish.svg">

    <!-- CSRF -->
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/modern-framework.css">
    <link rel="stylesheet" href="../assets/css/role-themes.css">
    <link rel="stylesheet" href="../assets/css/modern-sidebar.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* === TOP HEADER === */
        .top-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 56px;
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            z-index: 1050;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-family: system-ui, -apple-system, sans-serif;
        }

        /* Mobile Toggle */
        .modern-sidebar-toggle {
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background 0.2s;
            margin-right: 0.75rem;
            background: transparent;
            border: none;
        }
        .modern-sidebar-toggle:hover {
            background: rgba(30, 64, 175, 0.1);
            color: #1e40af;
        }

        /* Brand */
        .brand-name {
            font-weight: 700;
            font-size: 1.25rem;
            color: #1e40af;
            text-decoration: none;
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        .brand-name i {
            font-size: 1.4rem;
            margin-right: 0.5rem;
        }

        @media (max-width: 360px) {
            .brand-name span { display: none; }
            .brand-name { max-width: 60px; }
        }

        /* User Menu */
        .user-menu {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            font-size: 0.85rem;
            padding: 0.35rem 0.6rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .logout-btn:hover { background: #c82333; }
        @media (max-width: 400px) { .logout-btn span { display: none; } }

        /* === MAIN CONTENT === */
        .admin-main-content {
            margin-left: 260px;
            margin-top: 56px;
            padding: 1.25rem;
            min-height: calc(100vh - 56px);
            background: #f8fafc;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 992px) {
            .admin-main-content { margin-left: 0 !important; }
        }

        /* === SIDEBAR === */
        .modern-sidebar {
            width: 260px;
            position: fixed;
            top: 56px;
            left: 0;
            height: calc(100vh - 56px);
            background: #ffffff;
            z-index: 1040;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 12px rgba(0,0,0,0.06);
        }
        @media (max-width: 992px) {
            .modern-sidebar { transform: translateX(-100%); }
            .modern-sidebar.active { transform: translateX(0); }
        }
        @media (min-width: 993px) {
            .modern-sidebar { transform: translateX(0) !important; }
        }

        /* === OVERLAY === */
        .modern-sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1030;
            display: none;
            backdrop-filter: blur(2px);
        }
        .modern-sidebar-overlay.show { display: block; }

        /* === TAKEOVER ALERT (ADMIN) === */
        #sessionTakeoverAlert {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(220, 53, 69, 0.98);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s ease-out;
        }
        #sessionTakeoverAlert.show {
            display: flex;
        }
        .takeover-card {
            background: white;
            padding: 3rem 2.5rem;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            text-align: center;
            max-width: 500px;
            width: 90%;
            animation: slideUp 0.5s ease-out;
        }
        .takeover-icon {
            font-size: 4.5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        .takeover-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .takeover-text {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn-takeover {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.9rem 2.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .btn-takeover:hover {
            background: #c82333;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>

    <!-- SESSION TAKEOVER ALERT (ADMIN) -->
    <?php if ($show_takeover_alert): ?>
    <div id="sessionTakeoverAlert" class="show">
        <div class="takeover-card">
            <i class="fas fa-exclamation-triangle takeover-icon"></i>
            <h1 class="takeover-title">Admin Session Terminated</h1>
            <p class="takeover-text">
                Your admin account was logged in from another device.<br>
                <strong>This session has been terminated for security.</strong>
            </p>
            <p class="mb-4">Redirecting to login in <strong id="countdown">5</strong> seconds...</p>
            <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-takeover">
                <i class="fas fa-sign-in-alt"></i> Go to Login Now
            </a>
        </div>
    </div>

    <script>
        let seconds = 5;
        const countdown = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = '<?php echo base_url('auth/login.php'); ?>';
            }
        }, 1000);
    </script>
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/modern-sidebar.js"></script>
    
    <!-- Session Check Script (kept for extra safety) -->
    <script>
    (function() {
        <?php if ($isLoggedIn && !$show_takeover_alert): ?>
        setInterval(function() {
            fetch('../api/check_session.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.valid) {
                        document.getElementById('sessionTakeoverAlert')?.classList.add('show');
                    }
                })
                .catch(() => {});
        }, 3000);
        <?php endif; ?>
    })();
    </script>

    <!-- TOP HEADER -->
    <header class="top-header">
        <button class="modern-sidebar-toggle d-lg-none" type="button">
            <i class="fas fa-bars"></i>
        </button>
        <span class="brand-name">
            <i class="fas fa-fish"></i>
            <span>Fingerling Online Ordering System</span>
        </span>
    </header>

    <!-- Overlay -->
    <div class="modern-sidebar-overlay" id="sidebarOverlay"></div>

</body>
</html>