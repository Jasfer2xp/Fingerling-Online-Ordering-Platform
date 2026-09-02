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
    
    // Get platform statistics
    $stats = $admin->getPlatformStats();
    $pending_suppliers = count($admin->getPendingSuppliers());
    
    echo json_encode([
        'success' => true,
        'total_users' => $stats['total_customers'] + $stats['total_suppliers'],
        'total_customers' => $stats['total_customers'],
        'total_suppliers' => $stats['total_suppliers'],
        'pending_suppliers' => $pending_suppliers,
        'total_orders' => $stats['total_orders'],
        'completed_orders' => $stats['completed_orders'],
        'total_revenue' => $stats['total_revenue'],
        'active_products' => $stats['active_products'],
        'total_species' => $stats['total_species']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
?>
