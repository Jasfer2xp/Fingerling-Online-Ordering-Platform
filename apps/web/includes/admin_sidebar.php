<!-- Modern Sidebar -->
<aside class="modern-sidebar role-sidebar admin-sidebar" id="sidebarMenu">
    <!-- Mobile Header -->
    <div class="modern-sidebar-mobile-header">
        <h5 class="modern-sidebar-mobile-title">Admin Panel</h5>
        <button class="modern-sidebar-close" type="button">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Sidebar Header -->
    <div class="modern-sidebar-header">
        <a href="<?php
 echo base_url('admin/dashboard.php'); ?>" class="modern-sidebar-brand">
            <div class="modern-sidebar-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span>Admin Panel</span>
        </a>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="modern-sidebar-nav">
        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Main</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="modern-nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/analytics.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="modern-nav-text">Analytics</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">User Management</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/users.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-users"></i></span>
                        <span class="modern-nav-text">All Users</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/suppliers.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-store"></i></span>
                        <span class="modern-nav-text">Suppliers</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/customers.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-user-friends"></i></span>
                        <span class="modern-nav-text">Customers</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/pending-approvals.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'pending-approvals.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-clock"></i></span>
                        <span class="modern-nav-text">Pending Approvals</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/admins.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'admins.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-user-shield"></i></span>
                        <span class="modern-nav-text">Admin Users</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Content</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/products.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-fish"></i></span>
                        <span class="modern-nav-text">Products</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/species.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'species.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-list"></i></span>
                        <span class="modern-nav-text">Species</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/categories.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-tags"></i></span>
                        <span class="modern-nav-text">Categories</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/orders.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-shopping-cart"></i></span>
                        <span class="modern-nav-text">Orders</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/order-tracking.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'order-tracking.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-truck"></i></span>
                        <span class="modern-nav-text">Order Tracking</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">Financial</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/payments.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-credit-card"></i></span>
                        <span class="modern-nav-text">Payments</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/refunds.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'refunds.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-undo"></i></span>
                        <span class="modern-nav-text">Refunds</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="modern-nav-section">
            <h6 class="modern-nav-section-title">System</h6>
            <ul class="modern-nav-list">
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/settings.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-cog"></i></span>
                        <span class="modern-nav-text">Settings</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/reports.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-chart-bar"></i></span>
                        <span class="modern-nav-text">Reports</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/system-logs.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'system-logs.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-file-alt"></i></span>
                        <span class="modern-nav-text">System Logs</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/security.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'security.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-shield-alt"></i></span>
                        <span class="modern-nav-text">Security</span>
                    </a>
                </li>
                <li class="modern-nav-item">
                    <a href="<?php echo base_url('admin/supplier-locations.php'); ?>" class="modern-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'supplier-locations.php' ? 'active' : ''; ?>">
                        <span class="modern-nav-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span class="modern-nav-text">Supplier Locations</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>


</aside>

<!-- Sidebar Overlay -->
<div class="modern-sidebar-overlay" id="sidebarOverlay"></div>
