<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// ✅ Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$order = new Order($database);

// ✅ Get user profile
$profile = $user->getUserProfile($user_id);

// ✅ Get all customer orders
$sql = "SELECT o.*, s.business_name, s.barangay, s.city,
               COUNT(oi.id) as item_count
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ? AND o.status IN ('delivered', 'cancelled')
        GROUP BY o.id 
        ORDER BY o.created_at DESC";
$orders = $database->fetchAll($sql, [$user_id]);

// ✅ Stats
$total_orders = count($orders);
$completed_orders = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$cancelled_orders = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
$total_spent = array_sum(array_map(fn($o) => $o['status'] === 'delivered' ? $o['total_amount'] : 0, $orders));

$page_title = 'Order History';
include '../includes/customer_header.php';
?>

<style>
/* ---------- GENERAL LAYOUT ---------- */
body {
    background-color: #f8f9fa;
    color: #333;
    font-family: 'Segoe UI', sans-serif;
}

.profile-container {
    display: flex;
    max-width: 1200px;
    margin: 40px auto;
    gap: 30px;
    padding: 0 20px;
}

/* ---------- SIDEBAR ---------- */
.profile-sidebar {
    background: #fff;
    border-radius: 10px;
    width: 260px;
    flex-shrink: 0;
    padding: 20px;
    position: sticky;
    top: 20px;
    height: fit-content;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.profile-sidebar h3 {
    font-size: 18px;
    margin-bottom: 15px;
    color: #222;
    font-weight: 600;
}

.sidebar-section {
    margin-bottom: 25px;
}

.sidebar-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-section ul li a {
    display: block;
    padding: 10px 12px;
    color: #555;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.sidebar-section ul li a:hover,
.sidebar-section ul li a.active {
    background: #f0f0f0;
    color: #222;
}

/* ---------- MAIN CONTENT ---------- */
.profile-main {
    flex: 1;
    background: #fff;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}

.profile-main h1 {
    color: #2c3e50;
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    font-size: 24px;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    border: 1px solid #eee;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #7f8c8d;
    font-size: 16px;
    font-weight: normal;
}

.stat-card .value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.orders-table th,
.orders-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.orders-table th {
    background-color: #f8f9fa;
    color: #7f8c8d;
    font-weight: 600;
}

.orders-table tr:hover {
    background-color: #f8f9fa;
}

.orders-table .order-status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.order-status.delivered {
    background-color: #d4edda;
    color: #155724;
}

.order-status.cancelled {
    background-color: #f8d7da;
    color: #721c24;
}

.order-details {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.order-details:hover {
    text-decoration: underline;
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 768px) {
    .profile-container {
        flex-direction: column;
        margin: 20px auto;
        padding: 0 15px;
    }
    
    .profile-sidebar {
        width: 100%;
        position: relative;
        top: 0;
    }
    
    .stats-container {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="profile-container">
    <aside class="profile-sidebar">
        <div class="sidebar-section">
            <h3>My Account</h3>
            <ul>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="addresses.php">Addresses</a></li>
                <li><a href="settings.php">Change Password</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3>My Purchase</h3>
            <ul>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="order-history.php" class="active">Order History</a></li>
            </ul>
        </div>
    </aside>

    <section class="profile-main">
        <h1>Order History</h1>
        
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed</h3>
                <div class="value"><?php echo $completed_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>Cancelled</h3>
                <div class="value"><?php echo $cancelled_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Spent</h3>
                <div class="value">₱<?php echo number_format($total_spent, 2); ?></div>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                <p>You haven't placed any orders yet.</p>
            </div>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Location</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['barangay'] . ', ' . $order['city']); ?></td>
                            <td><?php echo $order['item_count']; ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="order-details">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</main>
<?php include '../includes/customer_footer.php'; ?>