<!-- Modern Customer Sidebar -->
<aside class="modern-sidebar role-sidebar customer-sidebar" id="sidebarMenu">
    <!-- Mobile Header -->
    <div class="modern-sidebar-mobile-header">
        <h5 class="modern-sidebar-mobile-title"><?php
 echo APP_NAME; ?></h5>
        <button class="modern-sidebar-close" type="button">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Sidebar Header -->
    <div class="modern-sidebar-header">
        <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="modern-sidebar-brand">
            <div class="modern-sidebar-logo">
                <i class="fas fa-fish"></i>
            </div>
            <span><?php echo APP_NAME; ?></span>
        </a>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="modern-sidebar-nav">
        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Main</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="modern-nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/browse.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'browse.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-search"></i></span>
                        <span class="modern-nav-text">Browse Products</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/suppliers.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-store"></i></span>
                        <span class="modern-nav-text">Suppliers</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/cart.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-shopping-cart"></i></span>
                        <span class="modern-nav-text">Shopping Cart</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Orders</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/orders.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-list"></i></span>
                        <span class="modern-nav-text">My Orders</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/order-history.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'order-history.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-history"></i></span>
                        <span class="modern-nav-text">Order History</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/tracking.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'tracking.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-truck"></i></span>
                        <span class="modern-nav-text">Track Orders</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Account</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/profile.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-user"></i></span>
                        <span class="modern-nav-text">My Profile</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/addresses.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'addresses.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span class="modern-nav-text">Addresses</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/feedback.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-star"></i></span>
                        <span class="modern-nav-text">My Reviews</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/settings.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-cog"></i></span>
                        <span class="modern-nav-text">Settings</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/help.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'help.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-question-circle"></i></span>
                        <span class="modern-nav-text">Help Center</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('customer/contact.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-envelope"></i></span>
                        <span class="modern-nav-text">Contact Support</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Logout Section -->
        <div class="modern-nav-section">
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('auth/logout.php'); ?>" class="modern-nav-link" style="color: #dc3545;">
                        <span class="modern-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="modern-nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>

<!-- Sidebar Overlay -->
<div class="modern-sidebar-overlay" id="sidebarOverlay"></div>