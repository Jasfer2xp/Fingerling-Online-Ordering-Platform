<?php

// Ensure Manila timezone is set for all timestamp operations
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout for customers - Check if user is logged in and is a customer
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'customer') {
    // Set the inactivity timeout to 5 minutes (300 seconds)
    $timeout_duration = 300;
    
    // Check if last activity time is set
    if (isset($_SESSION['last_activity'])) {
        // Calculate inactive time
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // If inactive time exceeds timeout duration, destroy session
        if ($inactive_time > $timeout_duration) {
            // Destroy session and redirect to login with timeout message
            session_unset();
            session_destroy();
            header("Location: " . base_url('auth/login.php?timeout=1'));
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';

// Initialize language
$preferred_language = $_SESSION['preferred_language'] ?? 'en';
require_once __DIR__ . '/../classes/Language.php';
$language = new Language($preferred_language);

if (!isset($user)) {
    $user = new User($database);
}

$profile = null;
$cart_count = 0;
$unread_notifications = 0;
$unread_messages = 0;
$notifications = [];
$isLoggedIn = false; // Add this variable
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'customer') {
    $isLoggedIn = true; // Set to true when user is logged in
    $profile = $user->getUserProfile($_SESSION['user_id']);
    require_once __DIR__ . '/../classes/Cart.php';
    $customer_id = $profile['id'];
    $cart = new Cart($database, $customer_id);
    $cart_count = $cart->getItemCount();
    
    // Get unread notifications count (using customer_id)
    if ($profile) {
        $notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE customer_id = ? AND is_read = 0";
        $notif_result = $database->fetch($notif_sql, [$customer_id]);
        $unread_notifications = $notif_result['count'] ?? 0;
        
        // Fetch notifications for dropdown (using customer_id)
        $notifications_sql = "SELECT * FROM notifications WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5";
        $notifications = $database->fetchAll($notifications_sql, [$customer_id]);
        
        // Process notifications to determine redirect URLs
        foreach ($notifications as &$notification) {
            // Priority: Use explicit link from database if available (e.g. for delivery date selection)
            if (!empty($notification['link']) && $notification['link'] !== '#') {
                $notification['redirect_url'] = $notification['link'];
            } else {
                // Fallback: Generate link based on message content
                $notification['redirect_url'] = '#';
                
                if (strpos($notification['message'], 'order #') !== false && strpos($notification['message'], 'confirmed') !== false) {
                    preg_match('/order #([A-Z0-9\-]+)/i', $notification['message'], $matches);
                    if (isset($matches[1])) {
                        $order_number = $matches[1];
                        $order_sql = "SELECT id FROM orders WHERE order_number = ?";
                        $order = $database->fetch($order_sql, [$order_number]);
                        if ($order) {
                            $notification['redirect_url'] = base_url("customer/order-details.php?id=" . $order['id']);
                        }
                    }
                } else if (strpos($notification['message'], 'order #') !== false && strpos($notification['message'], 'delivered') !== false) {
                    preg_match('/order #([A-Z0-9\-]+)/i', $notification['message'], $matches);
                    if (isset($matches[1])) {
                        $order_number = $matches[1];
                        $order_sql = "SELECT id, supplier_id FROM orders WHERE order_number = ?";
                        $order = $database->fetch($order_sql, [$order_number]);
                        if ($order) {
                            $notification['redirect_url'] = base_url("customer/rate-supplier.php?order_id=" . $order['id'] . "&supplier_id=" . $order['supplier_id']);
                        }
                    }
                } else if (strpos($notification['message'], 'Thank you for rating') !== false) {
                    $supplier_sql = "SELECT supplier_id FROM orders WHERE customer_id = (SELECT id FROM customers WHERE user_id = ?) ORDER BY created_at DESC LIMIT 1";
                    $supplier = $database->fetch($supplier_sql, [$profile['user_id']]);
                    if ($supplier) {
                        $notification['redirect_url'] = base_url("supplier-profile.php?id=" . $supplier['supplier_id']);
                    }
                } else {
                    if (preg_match('/order #([A-Z0-9\-]+)/i', $notification['message'], $matches)) {
                        $order_number = $matches[1];
                        $order_sql = "SELECT id FROM orders WHERE order_number = ?";
                        $order = $database->fetch($order_sql, [$order_number]);
                        if ($order) {
                            $notification['redirect_url'] = base_url("customer/order-details.php?id=" . $order['id']);
                        }
                    }
                }
            }
            $notification['redirect_url'] = htmlspecialchars($notification['redirect_url']);
        }
        
        // Get unread messages count
        if (file_exists(__DIR__ . '/../classes/Message.php')) {
            require_once __DIR__ . '/../classes/Message.php';
            try {
                $message_system = new Message($database);
                $unread_messages = $message_system->getUnreadCount($profile['user_id']);
            } catch (Exception $e) {
                $unread_messages = 0;
            }
        }
    }
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

// Initialize message system if user is logged in
$message = null;
$unread_count = 0;
if (is_logged_in()) {
    if (file_exists(__DIR__ . '/../classes/Message.php')) {
        require_once __DIR__ . '/../classes/Message.php';
        try {
            $message = new Message($database);
            $unread_count = $message->getUnreadCount(get_user_id());
        } catch (Exception $e) {
            $message = null;
            $unread_count = 0;
        }
    }
}

if (!isset($database)) {
    $database = new Database();
}

// Get unread messages count for customer
$unread_messages = 0;
if (is_logged_in() && get_user_type() === 'customer') {
    try {
        $user_id = get_user_id();
        $sql = "SELECT id FROM customers WHERE user_id = ?";
        $customer = $database->fetch($sql, [$user_id]);
        
        if ($customer) {
            $sql = "SELECT COUNT(*) as unread_count 
                    FROM messages m
                    JOIN conversations c ON m.conversation_id = c.id
                    WHERE c.customer_id = ? AND m.receiver_id = ? AND m.is_read = 0";
            $result = $database->fetch($sql, [$customer['id'], $user_id]);
            $unread_messages = $result ? (int)$result['unread_count'] : 0;
        }
    } catch (Exception $e) {
        error_log("Failed to get unread messages count: " . $e->getMessage());
    }
}

// Determine the correct contact link for messaging
$contact_link = base_url('customer/messages.php');
$resolved_supplier_id = null;
if (isset($supplier_id) && (int)$supplier_id > 0) {
    $resolved_supplier_id = (int) $supplier_id;
} elseif (isset($supplier) && is_array($supplier) && isset($supplier['id']) && (int)$supplier['id'] > 0) {
    $resolved_supplier_id = (int) $supplier['id'];
} elseif (isset($order_details) && is_array($order_details) && isset($order_details['supplier_id']) && (int)$order_details['supplier_id'] > 0) {
    $resolved_supplier_id = (int) $order_details['supplier_id'];
} elseif (isset($_GET['supplier_id']) && (int)$_GET['supplier_id'] > 0) {
    $resolved_supplier_id = (int) $_GET['supplier_id'];
}

if (!$resolved_supplier_id) {
    try {
        if (isset($_GET['order_id']) && (int) $_GET['order_id'] > 0) {
            $orderLookup = $database->fetch("SELECT supplier_id FROM orders WHERE id = ?", [(int) $_GET['order_id']]);
            if (!empty($orderLookup['supplier_id'])) {
                $resolved_supplier_id = (int) $orderLookup['supplier_id'];
            }
        }

        if (!$resolved_supplier_id && isset($_GET['id']) && (int) $_GET['id'] > 0) {
            $candidateId = (int) $_GET['id'];
            $supplierExists = $database->fetch("SELECT id FROM suppliers WHERE id = ?", [$candidateId]);
            if ($supplierExists) {
                $resolved_supplier_id = $candidateId;
            } else {
                $inventoryLookup = $database->fetch("SELECT supplier_id FROM inventory WHERE id = ?", [$candidateId]);
                if (!empty($inventoryLookup['supplier_id'])) {
                    $resolved_supplier_id = (int) $inventoryLookup['supplier_id'];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Contact link resolution failed: ' . $e->getMessage());
    }
}

if ($resolved_supplier_id) {
    $contact_link = base_url('customer/messages.php?supplier_id=' . $resolved_supplier_id);
}

// SESSION TAKEOVER ALERT - SHOW ONLY ONCE
$session_takeover_alert = '';
if (isset($_SESSION['session_takeover']) && $_SESSION['session_takeover'] === true) {
    $session_takeover_alert = '
    <div class="alert alert-warning alert-dismissible fade show position-fixed" style="top: 50px; left: 50%; transform: translateX(-50%); z-index: 9999; max-width: 90%; box-shadow: 0 8px 25px rgba(0,0,0,0.2); border-radius: 12px; font-size: 1rem;" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
            <div>
                <strong>Security Alert</strong><br>
                Your account was logged in from another device.<br>
                <strong>This session is no longer active.</strong> Please log in again if this wasn’t you.
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['session_takeover']); // Show only once
}
?>

<!DOCTYPE html>
<html lang="<?php echo $preferred_language; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Fingerling Online Ordering Platform'; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    body {
      background-color: #f1f5f9;
      margin: 0;
      padding-top: 120px;
    }

    /* Top Utility Bar */
    .top-bar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 35px;
      background-color: #1f2937;
      color: #d1d5db;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      padding: 0 20px;
      z-index: 1050;
    }

    /* Main Header */
    .main-header {
      position: fixed;
      top: 35px;
      left: 0;
      width: 100%;
      background-color: #111827;
      padding: 10px 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 1040;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .offcanvas {
      z-index: 1060 !important;
    }

    .offcanvas-backdrop {
      z-index: 1055 !important;
    }

    .offcanvas-header,
    .offcanvas-body {
      background-color: #111827 !important;
      color: #f1f5f9 !important;
    }

    .offcanvas .list-group-item {
      background-color: transparent !important;
      color: #e2e8f0 !important;
      border-color: #334155 !important;
      padding: 12px 20px;
      font-weight: 500;
    }

    .offcanvas .list-group-item:hover,
    .offcanvas .list-group-item.active {
      background-color: #1e293b !important;
      color: #fff !important;
    }

    .top-bar a { color: #d1d5db; text-decoration: none; margin: 0 8px; }
    .top-bar a:hover { color: #fff; }
    .brand { display: flex; align-items: center; gap: 10px; color: #fff; font-size: 20px; font-weight: 600; text-decoration: none; }
    .brand i { font-size: 26px; color: #22d3ee; }
    .search-bar { flex: 1; max-width: 800px; margin: 0 15px; position: relative; }
    .search-bar input { width: 100%; padding: 8px 40px 8px 15px; border-radius: 25px; border: none; outline: none; background: #374151; color: #f9fafb; font-size: 14px; }
    .search-bar input::placeholder { color: #9ca3af; }
    .search-bar button { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #d1d5db; font-size: 16px; cursor: pointer; }
    .right-section { display: flex; align-items: center; gap: 14px; }
    .icon-badge { position: relative; display: inline-flex; }
    .badge-count { position: absolute; top: -8px; right: -8px; background-color: #ef4444; color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; font-weight: bold; display: flex; align-items: center; justify-content: center; }
    .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: #3b82f6; color: #fff; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .nav-links { position: fixed; top: 90px; left: 0; width: 100%; background-color: #1e293b; padding: 6px 0; display: flex; justify-content: center; flex-wrap: wrap; z-index: 1030; }
    .nav-links a { color: #d1d5db; text-decoration: none; margin: 0 15px; font-weight: 500; padding: 6px 0; }
    .nav-links a:hover, .nav-links a.active { color: #fff; }
    .dropdown-menu { background-color: #1e293b; border: 1px solid #334155; }
    .dropdown-item { color: #e2e8f0; padding: 8px 16px; }
    .dropdown-item:hover { background-color: #334155; color: #fff; }
    .notification-dropdown-menu { background-color: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); min-width: 320px; max-height: 70vh; overflow-y: auto; z-index: 1070 !important; }
    .notification-dropdown-menu .dropdown-header { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #1e293b; padding: 10px 16px; }
    .notification-item { border-bottom: 1px solid #e2e8f0; color: #1e293b; padding: 12px 16px; cursor: pointer; }
    .notification-item:hover { background-color: #f1f5f9 !important; }
    .notification-item a { color: inherit; text-decoration: none; display: block; }
    .notification-item a:hover { color: #0ea5e9; }
    .notification-empty { color: #94a3b8; text-align: center; padding: 20px; }

    @media (max-width: 992px) {
      .brand { display: none; }
      .search-bar { margin: 0 10px; max-width: 300px; }
      .search-bar input { font-size: 13px; padding: 6px 35px 6px 12px; }
      .nav-links { display: none; }
      .main-header { padding: 8px 10px; }
      .top-bar { padding: 0 15px; font-size: 13px; }
    }

    @media (max-width: 576px) {
      .top-bar { font-size: 12px; }
      .top-bar a { margin: 0 4px; }
      .search-bar { max-width: 180px; }
      .search-bar input { font-size: 12px; }
    }

    .supplier-popup h6 {
      margin-bottom: 0.5rem;
      color: #1e293b;
    }
    
    .supplier-popup .product-list {
      max-height: 150px;
      overflow-y: auto;
      margin-top: 0.5rem;
      padding-right: 0.5rem;
    }
    
    .supplier-popup .product-item {
      padding: 0.25rem 0;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
    }
    
    .supplier-popup .product-item:last-child {
      border-bottom: none;
    }
    
    .supplier-popup .btn-primary {
      background-color: #ff6600;
      border-color: #ff6600;
      font-size: 0.8rem;
      padding: 0.25rem 0.5rem;
    }
    
    .supplier-popup .btn-primary:hover {
      background-color: #ff7b1c;
      border-color: #ff7b1c;
    }
    
    .supplier-popup .popup-actions {
      margin-top: 0.75rem;
      text-align: center;
    }
  </style>
</head>
<body>

  <!-- SESSION TAKEOVER ALERT -->
  <?php echo $session_takeover_alert; ?>

  <!-- Top Bar -->
  <div class="top-bar">
    <div>
      <a href="<?php echo htmlspecialchars($contact_link); ?>"><?php echo $language->translate('Contact'); ?></a> |
      <a href="#" onclick="confirmLogout()"><?php echo $language->translate('Become a Supplier'); ?></a>
    </div>
    <div>
      <a href="../customer/help.php">Help</a>
    </div>
  </div>

  <!-- Main Header -->
  <header class="main-header">
    <button class="navbar-toggler d-lg-none text-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
      <i class="bi bi-list fs-3"></i>
    </button>

    <a class="brand d-none d-lg-flex" href="<?php echo base_url('../customer/dashboard.php'); ?>">
      <i class="bi bi-fish"></i> Fingerling Online
    </a>

    <div class="search-bar">
      <form action="<?php echo base_url('customer/search-results.php'); ?>" method="GET">
        <input type="text" name="q" placeholder="<?php echo $language->translate('Search...'); ?>" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        <button type="submit"><i class="bi bi-search"></i></button>
      </form>
    </div>

    <div class="right-section">
      <!-- Notifications Dropdown -->
      <div class="dropdown notification-dropdown">
        <a href="#" class="text-light fs-5 icon-badge dropdown-toggle" 
           id="notificationDropdown" data-bs-toggle="dropdown" data-bs-auto-close="false">
          <i class="bi bi-bell"></i>
          <span class="badge-count" id="notification-badge" style="display: <?php echo $unread_notifications > 0 ? 'flex' : 'none'; ?>;">
            <?php echo $unread_notifications; ?>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-end mt-2 notification-dropdown-menu" aria-labelledby="notificationDropdown">
          <div class="dropdown-header d-flex justify-content-between align-items-center">
            <strong>Notifications</strong>
            <?php if (!empty($notifications)): ?>
              <a href="../customer/notifications.php" class="small text-decoration-none">View All</a>
            <?php endif; ?>
          </div>
          <div class="notification-list">
            <?php if (empty($notifications)): ?>
              <div class="dropdown-item notification-empty">
                <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                <p class="mb-0">No notifications</p>
              </div>
            <?php else: ?>
              <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                     data-notification-id="<?php echo $notification['id']; ?>"
                     data-redirect-url="<?php echo $notification['redirect_url']; ?>">
                  <a href="javascript:void(0)" class="text-decoration-none text-dark d-block">
                    <div class="d-flex">
                      <div class="flex-shrink-0">
                        <?php echo $notification['type'] === 'order' ? '<i class="fas fa-shopping-cart text-primary"></i>' : 
                                ($notification['type'] === 'payment' ? '<i class="fas fa-credit-card text-success"></i>' : 
                                ($notification['type'] === 'promotion' ? '<i class="fas fa-tag text-warning"></i>' : '<i class="fas fa-info-circle text-info"></i>')); ?>
                      </div>
                      <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                        <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <small class="text-muted"><?php echo time_ago($notification['created_at']); ?></small>
                      </div>
                      <?php if (!$notification['is_read']): ?>
                        <div class="flex-shrink-0 ms-2">
                          <span class="badge bg-danger">New</span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <?php if (file_exists(__DIR__ . '/../classes/Message.php')): ?>
      <a href="../customer/messages.php" class="text-light fs-5 icon-badge">
        <i class="bi bi-envelope"></i>
        <span class="badge-count" style="display: <?php echo $unread_messages > 0 ? 'flex' : 'none'; ?>;">
          <?php echo $unread_messages; ?>
        </span>
      </a>
      <?php endif; ?>

      <!-- Cart -->
      <a href="../customer/cart.php" class="text-light fs-5 icon-badge">
        <i class="bi bi-cart3"></i>
        <span class="badge-count" style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;">
          <?php echo $cart_count; ?>
        </span>
      </a>

      <!-- User Menu -->
      <?php if ($profile): ?>
        <div class="dropdown">
          <a href="#" class="d-flex align-items-center text-light text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
            <div class="user-avatar">
              <?php echo strtoupper(substr($profile['first_name'] ?? 'C', 0, 1) . substr($profile['last_name'] ?? 'U', 0, 1)); ?>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="../customer/profile.php">My Profile</a></li>
            <li><a class="dropdown-item" href="../customer/settings.php">Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../auth/logout.php">Logout</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="../auth/login.php" class="btn btn-outline-light btn-sm">Login</a>
        <a href="../auth/register.php" class="btn btn-light btn-sm text-dark">Register</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Desktop Nav Links -->
  <div class="nav-links d-lg-flex d-none">
    <a href="../customer/dashboard.php" class="active">Dashboard</a>
    <a href="#" onclick="showSuppliersMap()">View Supplier Location</a>
    <a href="../customer/orders.php">My Orders</a>
  </div>

  <!-- Mobile Offcanvas Menu -->
  <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title text-white">Menu</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
      <div class="list-group list-group-flush">
        <a href="../customer/dashboard.php" class="list-group-item list-group-item-action nav-link active">Dashboard</a>
        <a href="javascript:void(0)" onclick="showSuppliersMap(); bootstrap.Offcanvas.getInstance(document.getElementById('mobileMenu')).hide();" class="list-group-item list-group-item-action nav-link">View Supplier Location</a>
        <a href="../customer/orders.php" class="list-group-item list-group-item-action nav-link">My Orders</a>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    // Confirm logout function
    function confirmLogout() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../auth/logout.php';
      }
    }

    // Map Modal
    let suppliersMap = null;
    function showSuppliersMap() {
      const modal = new bootstrap.Modal(document.getElementById('suppliersMapModal'));
      modal.show();
      setTimeout(() => {
        if (!suppliersMap) initializeMap();
        else suppliersMap.invalidateSize();
      }, 300);
    }

    function initializeMap() {
      const tangubCenter = [8.1468, 123.7503];
      suppliersMap = L.map('suppliersMap').setView(tangubCenter, 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(suppliersMap);

      fetch('../api/suppliers_map_data.php')
        .then(r => r.json())
        .then(suppliers => {
          const validMarkers = [];
          const promises = suppliers.map(s => {
            const lat = parseFloat(s.latitude), lng = parseFloat(s.longitude);
            if (isNaN(lat) || isNaN(lng)) return Promise.resolve();
            
            let popupContent = `<div class="supplier-popup">
                                  <h6><strong>${s.business_name}</strong></h6>
                                  <p class="mb-1"><small>${s.barangay}, ${s.city}, ${s.province}</small></p>
                                  <p class="mb-2"><small>Products: ${s.product_count || 0} | Rating: ${s.avg_rating ? parseFloat(s.avg_rating).toFixed(1) : 'N/A'} (${s.total_reviews || 0} reviews)</small></p>`;
            
            if (s.product_count > 0) {
              popupContent += `<div class="supplier-products mb-2">
                                 <small><strong>Available Products:</strong></small>
                                 <div id="supplier-${s.id}" class="product-list">
                                   <small>Loading products...</small>
                                 </div>
                               </div>`;
            }
            
            popupContent += `<div class="popup-actions">
                               <a href="../customer/supplier-profile.php?id=${s.id}" class="btn btn-sm btn-primary">View Profile</a>
                             </div>
                           </div>`;
            
            const marker = L.marker([lat, lng]).addTo(suppliersMap);
            validMarkers.push(marker);
            marker.bindPopup(popupContent, {maxWidth: 300});
            
            if (s.product_count > 0) {
              return fetch(`../api/supplier/products.php?supplier_id=${s.id}`)
                .then(res => res.json())
                .then(products => {
                  if (products.success && products.data && products.data.length > 0) {
                    let productHtml = '';
                    const displayProducts = products.data.slice(0, 3);
                    displayProducts.forEach(product => {
                      productHtml += `<div class="product-item">
                                        <small>${product.species_name} (${product.size_category})</small>
                                        <small class="float-end">₱${parseFloat(product.price_per_piece).toFixed(2)}</small>
                                      </div>`;
                    });
                    
                    if (products.data.length > 3) {
                      productHtml += `<div class="product-item">
                                        <small>and ${products.data.length - 3} more...</small>
                                      </div>`;
                    }
                    
                    const popupElement = marker.getPopup();
                    const content = popupElement.getContent();
                    const updatedContent = content.replace(
                      `<div id="supplier-${s.id}" class="product-list">
                                   <small>Loading products...</small>
                                 </div>`,
                      `<div id="supplier-${s.id}" class="product-list">
                                   ${productHtml}
                                 </div>`
                    );
                    marker.setPopupContent(updatedContent);
                  } else {
                    const popupElement = marker.getPopup();
                    const content = popupElement.getContent();
                    const updatedContent = content.replace(
                      `<div id="supplier-${s.id}" class="product-list">
                                   <small>Loading products...</small>
                                 </div>`,
                      `<div id="supplier-${s.id}" class="product-list">
                                   <small>No products available</small>
                                 </div>`
                    );
                    marker.setPopupContent(updatedContent);
                  }
                })
                .catch(err => {
                  console.error('Error fetching products:', err);
                  const popupElement = marker.getPopup();
                  const content = popupElement.getContent();
                  const updatedContent = content.replace(
                    `<div id="supplier-${s.id}" class="product-list">
                                   <small>Loading products...</small>
                                 </div>`,
                    `<div id="supplier-${s.id}" class="product-list">
                                   <small>Error loading products</small>
                                 </div>`
                  );
                  marker.setPopupContent(updatedContent);
                });
            }

            return Promise.resolve();
          });
          
          Promise.all(promises).then(() => {
            if (validMarkers.length > 0) {
              const group = new L.featureGroup(validMarkers);
              suppliersMap.fitBounds(group.getBounds().pad(0.15));
            } else {
              suppliersMap.setView(tangubCenter, 12);
            }
          });
        });
    }

    // FACEBOOK-STYLE NOTIFICATION CLEARING - Mark all as read when bell is clicked
    document.addEventListener('DOMContentLoaded', function() {
        const dropdown = new bootstrap.Dropdown(document.getElementById('notificationDropdown'), { autoClose: false });
        const notificationBell = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notification-badge');
        let hasMarkedAllRead = false;

        // Mark all notifications as read when dropdown is shown (bell clicked)
        notificationBell.addEventListener('click', function(e) {
            // Only mark all as read once per session, and only if there are unread notifications
            if (!hasMarkedAllRead && notificationBadge && notificationBadge.style.display !== 'none') {
                fetch('../api/notifications/mark_all_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Reset badge count to 0 immediately
                        if (notificationBadge) {
                            notificationBadge.textContent = '0';
                            notificationBadge.style.display = 'none';
                        }

                        // Remove "New" badges from all notification items
                        document.querySelectorAll('.notification-item .badge.bg-danger').forEach(badge => {
                            badge.remove();
                        });

                        // Remove unread class from all notification items
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });

                        hasMarkedAllRead = true;
                    }
                })
                .catch(err => console.error('Failed to mark all as read:', err));
            }
        });

        // REAL-TIME SINGLE NOTIFICATION MARK AS READ (for individual clicks)
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const notifId = this.getAttribute('data-notification-id');
                const redirectUrl = this.getAttribute('data-redirect-url');
                const isUnread = this.classList.contains('unread');

                if (!notifId || !redirectUrl || redirectUrl === '#') {
                    if (redirectUrl && redirectUrl !== '#') {
                        window.location.href = redirectUrl;
                    }
                    return;
                }

                // Only mark as read if unread (though all should already be marked as read)
                if (!isUnread) {
                    window.location.href = redirectUrl;
                    return;
                }

                fetch('../api/notifications/mark_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notifId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Remove "New" badge
                        const newBadge = this.querySelector('.badge.bg-danger');
                        if (newBadge) newBadge.remove();

                        // Remove unread class
                        this.classList.remove('unread');

                        // Update main badge count
                        const badge = document.getElementById('notification-badge');
                        let count = parseInt(badge.textContent) || 0;
                        if (count > 0) {
                            count--;
                            badge.textContent = count;
                            badge.style.display = count > 0 ? 'flex' : 'none';
                        }
                    }
                })
                .catch(err => console.error('Failed to mark as read:', err))
                .finally(() => {
                    // Always redirect after marking
                    dropdown.hide();
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 150);
                });
            });
        });
    });
  </script>

  <!-- Session Timeout Handling -->
  <script>
  // Client-side session timeout handling
  (function() {
      // Set the same timeout duration (5 minutes = 300000 milliseconds)
      var timeoutDuration = 300000;
      var warningTime = 60000; // Show warning 1 minute before timeout
      
      var timeoutWarning;
      var timeout;
      
      function resetTimeout() {
          // Clear existing timeouts
          clearTimeout(timeoutWarning);
          clearTimeout(timeout);
          
          // Set new timeouts
          timeoutWarning = setTimeout(function() {
              // Show warning 1 minute before actual timeout
              if (confirm("Your session will expire in 1 minute due to inactivity. Do you want to continue?")) {
                  // If user clicks OK, reset activity (this will trigger a new request)
                  location.reload();
              }
          }, timeoutDuration - warningTime);
          
          timeout = setTimeout(function() {
              // Redirect to login page with timeout message
              window.location.href = "<?php echo base_url('auth/login.php?timeout=1'); ?>";
          }, timeoutDuration);
      }
      
      // Reset timeout on any user activity
      function setupActivityListeners() {
          // Mouse events
          document.addEventListener("mousedown", resetTimeout);
          document.addEventListener("mousemove", resetTimeout);
          
          // Keyboard events
          document.addEventListener("keydown", resetTimeout);
          
          // Scroll events
          document.addEventListener("scroll", resetTimeout);
          
          // Touch events for mobile devices
          document.addEventListener("touchstart", resetTimeout);
          document.addEventListener("touchmove", resetTimeout);
      }
      
      // Initialize
      resetTimeout();
      setupActivityListeners();
  })();
  </script>

  <!-- Session Check Script -->
  <script>
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

  // Auto-update message badge every 5 seconds
  function updateMessageBadge() {
    fetch('<?php echo base_url('api/messages/get_unread_count.php'); ?>')
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // Try multiple selectors to find the badge
          const messageLink = document.querySelector('a[href*="messages.php"]');
          let badge = null;
          
          if (messageLink) {
            badge = messageLink.querySelector('.badge-count');
            if (!badge) {
              // Try finding by ID or class on the link itself
              badge = document.querySelector('#message-badge, .message-badge');
            }
          }
          
          if (data.count > 0) {
            if (badge) {
              badge.textContent = data.count > 99 ? '99+' : data.count;
              badge.style.display = 'flex';
            } else if (messageLink) {
              // Create badge if it doesn't exist
              let newBadge = document.createElement('span');
              newBadge.className = 'badge-count';
              newBadge.id = 'message-badge';
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
      .catch((error) => {
        console.error('Badge update error:', error);
      });
  }

  // Update badge every 5 seconds
  setInterval(updateMessageBadge, 5000);
  // Initial update after 1 second
  setTimeout(updateMessageBadge, 1000);
  </script>

  <!-- Map Modal -->
  <div class="modal fade" id="suppliersMapModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Suppliers Map</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
          <div id="suppliersMap" style="height: 500px;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

</body>
</html>