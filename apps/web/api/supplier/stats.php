<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get supplier ID
    $user = new User($database);
    $profile = $user->getUserProfile(get_user_id());
    
    if ($profile['status'] !== 'approved') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Supplier not approved']);
        exit;
    }
    
    $supplier_id = $profile['id'];
    $supplier = new Supplier($database, $supplier_id);
    
    // Get statistics
    $pending_orders = $supplier->getPendingOrdersCount();
    $low_stock = $supplier->getLowStockCount();
    $sales_stats = $supplier->getSalesStats('month');
    
    echo json_encode([
        'success' => true,
        'pending_orders' => $pending_orders,
        'low_stock' => $low_stock,
        'monthly_orders' => $sales_stats['total_orders'],
        'monthly_revenue' => $sales_stats['total_revenue'],
        'completed_orders' => $sales_stats['completed_orders']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
?>
