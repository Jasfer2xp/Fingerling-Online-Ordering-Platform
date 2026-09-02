<!DOCTYPE html>
<html lang="en" data-theme="light" data-role="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
 echo ($page_title ?? 'Admin') . ' - ' . APP_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../assets/icons/fish.svg">

    <!-- CSRF -->
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/modern-framework.css">
    <link rel="stylesheet" href="../assets/css/role-themes.css">
    <link rel="stylesheet" href="../assets/css/modern-sidebar.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
        .mobile-toggle {
            font-size: 1.5rem;
            color: #1e40af;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background 0.2s;
            margin-right: 0.75rem;
        }
        .mobile-toggle:hover {
            background: rgba(30, 64, 175, 0.1);
        }

        /* Brand - Full text on all screens, only hide on extreme small */
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

        /* Only hide text on extreme small screens */
        @media (max-width: 360px) {
            .brand-name span {
                display: none;
            }
            .brand-name {
                max-width: 60px;
            }
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
        .logout-btn:hover {
            background: #c82333;
        }
        @media (max-width: 400px) {
            .logout-btn span { display: none; }
        }

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
            .admin-main-content {
                margin-left: 0 !important;
            }
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
            .modern-sidebar {
                transform: translateX(-100%);
            }
            .modern-sidebar.active {
                transform: translateX(0);
            }
        }
        @media (min-width: 993px) {
            .modern-sidebar {
                transform: translateX(0) !important;
            }
        }

        /* === OVERLAY === */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1030;
            display: none;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/modern-sidebar.js"></script>

    <!-- TOP HEADER -->
    <header class="top-header">
        <!-- Existing Menu Toggle -->
        <div class="mobile-toggle d-lg-none" id="existingSidebarToggle">
            <i class="fas fa-bars"></i>
        </div>

        <!-- Brand -->
        <span class="brand-name">
            <i class="fas fa-fish"></i>
            <span>Fingerling Online Ordering System</span>
        </span>
    </header>

    <!-- Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="admin-main-content">
        <!-- Page content goes here -->
    </main>

    <!-- Sidebar -->
    <aside class="modern-sidebar">
        <!-- Sidebar content goes here -->
    </aside>
</body>
</html>