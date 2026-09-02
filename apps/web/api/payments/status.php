<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$pending_id = isset($_GET['pending_id']) ? intval($_GET['pending_id']) : 0;
if (empty($pending_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing pending_id parameter']));
}

try {
    $pending_sql = "SELECT po.*, p.order_id FROM pending_orders po LEFT JOIN payments p ON po.authorization_id = p.authorization_id WHERE po.id = ?";
    $pending_order = $database->fetch($pending_sql, [$pending_id]);
    
    if (!$pending_order) {
        throw new Exception('Pending order not found');
    }
    
    $current_time = new DateTime();
    $expires_time = new DateTime($pending_order['expires_at']);
    
    if ($current_time > $expires_time && $pending_order['status'] === 'pending') {
        $update_sql = "UPDATE pending_orders SET status = 'expired' WHERE id = ?";
        $database->query($update_sql, [$pending_id]);
        $pending_order['status'] = 'expired';
    }
    
    $response_data = [
        'success' => true,
        'pending_id' => $pending_order['id'],
        'status' => $pending_order['status'],
        'created_at' => $pending_order['created_at'],
        'expires_at' => $pending_order['expires_at']
    ];
    
    if ($pending_order['status'] === 'confirmed' && !empty($pending_order['order_id'])) {
        $response_data['order_id'] = $pending_order['order_id'];
    }
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Status Check Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Status check failed: ' . $e->getMessage()]);
}