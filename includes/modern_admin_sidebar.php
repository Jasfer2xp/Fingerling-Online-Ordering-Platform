<?php

// includes/modern_admin_sidebar.php - FULL + TOGGLE FIXED + SUPPLIER APPEALS ADDED
?>
<aside class="modern-sidebar" id="sidebarMenu">
    <nav class="p-3" style="height: calc(100vh - 56px); overflow-y: auto;">
        <!-- Main -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">Main</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="dashboard.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-tachometer-alt me-3"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Management -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">User Management</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="suppliers.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-store me-3"></i>
                        <span>Suppliers</span>
                        <?php if (!empty($pending_suppliers)): ?>
                            <span class="badge bg-danger ms-auto"><?php echo count($pending_suppliers); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="customers.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-users me-3"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="pending-approvals.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'pending-approvals.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-clock me-3"></i>
                        <span>Pending Approvals</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="supplier-location.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'supplier-location.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-map-marker-alt me-3"></i>
                        <span>Supplier Location</span>
                    </a>
                </li>

                <!-- NEW: Supplier Appeals -->
                <li class="mb-2">
                    <a href="supplier-appeals.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'supplier-appeals.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-gavel me-3"></i>
                        <span>Supplier Appeals</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Business Operations -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">Business Operations</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="orders.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-shopping-cart me-3"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="products.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-shopping-cart me-3"></i>
                        <span>Supplier Products</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="withdrawals.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'withdrawals.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-money-bill-transfer me-3"></i>
                        <span>Pending Withdrawals</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="withdrawal-appeals.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'withdrawal-appeals.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-clock me-3"></i>
                        <span>Withdrawal Appeals</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Analytics & Reports -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">Analytics & Reports</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="analytics.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-chart-bar me-3"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="reports.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-file-alt me-3"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="supplier-performance.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'supplier-performance.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-trophy me-3"></i>
                        <span>Supplier Performance</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- System Management -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">System Management</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="feedback.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-comment me-3"></i>
                        <span>Feedback</span>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="profile.php"
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'bg-primary text-white shadow-sm' : 'text-dark'; ?>">
                        <i class="fas fa-user-cog me-3"></i>
                        <span>Admin Settings</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Logout -->
        <div class="mt-auto">
            <ul class="list-unstyled">
                <li>
                    <a href="../auth/logout.php" 
                       class="d-flex align-items-center text-decoration-none px-3 py-2 rounded-3 text-dark">
                        <i class="fas fa-sign-out-alt me-3"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>