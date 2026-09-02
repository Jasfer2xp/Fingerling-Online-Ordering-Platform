<style>
    :root {
        --primary-color: #0077b6;
        --secondary-color: #00b4d8;
        --light-gray: #f8f9fa;
        --text-dark: #343a40;
        --sidebar-width: 220px;
    }

    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background-color: #fff;
        border-right: 1px solid #dee2e6;
        padding-top: 65px;
        z-index: 1000;
        transition: transform 0.3s ease-in-out;
        overflow-y: auto;
    }

    .sidebar.hidden {
        transform: translateX(-100%);
    }

    .sidebar-nav {
        padding: 1.5rem 0.5rem;
    }

    .sidebar-heading {
        color: #adb5bd;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 0 0.5rem .5rem;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        color: #495057;
        font-weight: 500;
        padding: 0.6rem 0.75rem;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
        transition: background-color 0.2s, color 0.2s;
    }

    .sidebar .nav-link .nav-icon {
        width: 24px;
        text-align: center;
        margin-right: 0.75rem;
        font-size: 0.95rem;
        color: #6c757d;
        transition: color 0.2s;
    }

    .sidebar .nav-link:hover {
        background-color: var(--light-gray);
        color: var(--text-dark);
    }

    .sidebar .nav-link:hover .nav-icon {
        color: var(--text-dark);
    }

    .sidebar .nav-link.active {
        background-color: var(--primary-color);
        color: #fff;
        font-weight: 600;
    }

    .sidebar .nav-link.active .nav-icon {
        color: #fff;
    }

    .sidebar .logout-link {
        color: #dc3545;
    }

    .sidebar .logout-link:hover {
        background-color: #f8d7da;
        color: #58151c;
    }

    .sidebar .logout-link:hover .nav-icon {
        color: #58151c;
    }

    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(calc(-1 * var(--sidebar-width)));
            z-index: 1040;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1035;
        }
    }
</style>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <div class="mb-4">
            <h6 class="sidebar-heading">Main</h6>
            <ul class="list-unstyled">
                <li>
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="analytics.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line nav-icon"></i> Analytics
                    </a>
                </li>
            </ul>
        </div>
        <div class="mb-4">
            <h6 class="sidebar-heading">Inventory</h6>
            <ul class="list-unstyled">
                <li>
                    <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
                        <i class="fas fa-boxes nav-icon"></i> My Inventory
                    </a>
                </li>
                <li>
                    <a href="inventory-add.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'inventory-add.php' ? 'active' : ''; ?>">
                        <i class="fas fa-plus nav-icon"></i> Add Product
                    </a>
                </li>
                <li>
                    <a href="stock-alerts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'stock-alerts.php' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle nav-icon"></i> Stock Reports
                    </a>
                </li>
            </ul>
        </div>
        <div class="mb-4">
            <h6 class="sidebar-heading">Orders</h6>
            <ul class="list-unstyled">
                <li>
                    <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart nav-icon"></i> All Orders
                    </a>
                </li>
                <li>
                    <a href="order-archive.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'order-archive.php' ? 'active' : ''; ?>">
                        <i class="fas fa-archive nav-icon"></i> Order Archive
                    </a>
                </li>
                <li>
                    <a href="deliveries.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'deliveries.php' ? 'active' : ''; ?>">
                        <i class="fas fa-truck nav-icon"></i> Deliveries
                    </a>
                </li>
            </ul>
        </div>
        <div class="mb-4">
            <h6 class="sidebar-heading">Business</h6>
            <ul class="list-unstyled">
                <li>
                    <a href="sales-report.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'sales-report.php' ? 'active' : ''; ?>">
                       <i class="fas fa-chart-line nav-icon"></i> Sales Report
                    </a>
                </li>
                <li>
                    <a href="revenue.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'revenue.php' ? 'active' : ''; ?>">
                        <i class="fas fa-peso-sign nav-icon"></i> Total Earnings
                    </a>
                </li>
                <li>

                <li>
                    <a href="withdraw.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'withdraw.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-transfer nav-icon"></i> Withdraw Earnings
                    </a>
                </li>
                <li>
                    <a href="reviews.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : ''; ?>">
                        <i class="fas fa-star nav-icon"></i> Reviews
                    </a>
                </li>
            </ul>
        </div>
        <div class="mt-auto pt-4">
            <ul class="list-unstyled">
                <li>
                    <a href="<?php echo base_url('auth/logout.php'); ?>" class="nav-link logout-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>