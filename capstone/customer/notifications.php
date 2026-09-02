if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Return only notification items for the dropdown
    if (empty($notifications)) {
        echo '<div class="notification-empty">
                <i class="bi bi-bell-slash"></i>
                <p>No notifications yet</p>
              </div>';
    } else {
        foreach ($notifications as $notification) {
            // Extract order ID if this notification is related to an order
            $order_id = null;
            if (preg_match('/order #(\d+)/i', $notification['message'], $matches)) {
                $order_id = $matches[1];
            }
            
            $unread_class = !$notification['is_read'] ? 'unread' : '';
            $title_class = !$notification['is_read'] ? 'text-primary' : 'text-dark';
            
            echo "<div class='notification-item {$unread_class}' data-order-id='{$order_id}'>";
            if ($order_id) {
                echo "<a href='order-details.php?id={$order_id}' class='text-decoration-none'>";
            }
            
            echo "<div class='notification-title {$title_class}'>" . htmlspecialchars($notification['title']) . "</div>";
            echo "<div class='notification-message'>" . nl2br(htmlspecialchars($notification['message'])) . "</div>";
            echo "<div class='notification-time'>" . time_ago($notification['created_at']) . "</div>";
            
            if ($order_id) {
                echo "</a>";
            }
            echo "</div>";
        }
    }
    exit;
}