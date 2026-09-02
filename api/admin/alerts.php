<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $admin = new Admin($database);
    
    $alerts = [];
    $alert_count = 0;
    
    // Check for pending suppliers
    $pending_suppliers = $admin->getPendingSuppliers();
    if (!empty($pending_suppliers)) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'clock',
            'title' => 'Pending Supplier Approvals',
            'message' => count($pending_suppliers) . ' supplier(s) waiting for approval',
            'action_url' => 'suppliers.php?status=pending'
        ];
        $alert_count++;
    }
    
    // Check for low stock items across all suppliers
    $sql = "SELECT COUNT(*) as count FROM inventory 
            WHERE stock_quantity <= 10 AND availability_status = 'available'";
    $low_stock = $database->fetch($sql);
    if ($low_stock['count'] > 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'exclamation-triangle',
            'title' => 'Low Stock Alert',
            'message' => $low_stock['count'] . ' product(s) running low on stock',
            'action_url' => 'inventory-alerts.php'
        ];
        $alert_count++;
    }
    
    // Check for recent failed orders
    // Check for recent failed orders
    // Use dynamic interval derived from config
    $interval_string = str_replace('+', '-', ORDER_TIMEOUT_INTERVAL);
    $cutoff_date = date('Y-m-d H:i:s', strtotime($interval_string));
    
    $sql = "SELECT COUNT(*) as count FROM orders 
            WHERE status = 'cancelled' AND created_at >= ?";
    $failed_orders = $database->fetch($sql, [$cutoff_date]);
    if ($failed_orders['count'] > 5) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'times-circle',
            'title' => 'High Cancellation Rate',
            'message' => $failed_orders['count'] . ' orders cancelled in the last ' . ORDER_TIMEOUT_LABEL,
            'action_url' => 'orders.php?status=cancelled'
        ];
        $alert_count++;
    }
    
    // Check for system errors (if error log table exists)
    $sql = "SHOW TABLES LIKE 'error_logs'";
    $table_exists = $database->fetch($sql);
    if ($table_exists) {
        $sql = "SELECT COUNT(*) as count FROM error_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND severity = 'error'";
        $errors = $database->fetch($sql);
        if ($errors['count'] > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'bug',
                'title' => 'System Errors',
                'message' => $errors['count'] . ' error(s) in the last hour',
                'action_url' => 'system-logs.php'
            ];
            $alert_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $alert_count,
        'alerts' => $alerts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load alerts'
    ]);
}
?>
