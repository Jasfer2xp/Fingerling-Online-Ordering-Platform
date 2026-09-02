<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';

header('Content-Type: application/json');
ob_clean();

// Ensure session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

$user_id = get_user_id();
$supplier_id = get_supplier_id_by_user_id($user_id);

// Get request data
$order_id = intval($_POST['order_id'] ?? 0);
$status = sanitize_input($_POST['status'] ?? '');

if (!$order_id || !$status) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing required parameters']));
}

// Validate status
$allowed_statuses = ['confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid status']));
}

try {
    // Check if order belongs to this supplier
    $sql = "SELECT o.*, p.authorization_id, p.payment_method, p.status as payment_status
            FROM orders o
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE o.id = ? AND o.supplier_id = ?";
    $order = $database->fetch($sql, [$order_id, $supplier_id]);
    
    if (!$order) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Order not found']));
    }
    
    // Handle PayPal authorization capture/void based on status
    if ($order['payment_method'] === 'paypal' && !empty($order['authorization_id'])) {
        $orderObj = new Order($database);
        
        if ($status === 'confirmed') {
            // Capture the authorized payment
            if (!$orderObj->captureAuthorizedPayment($order_id)) {
                error_log("Failed to capture PayPal authorization for order $order_id");
                // We'll continue with the order update even if payment capture fails
            }
        } else if ($status === 'cancelled') {
            // Void the authorized payment
            if (!$orderObj->voidPayPalAuthorization($order_id)) {
                error_log("Failed to void PayPal authorization for order $order_id");
                // We'll continue with the order update even if payment void fails
            }
        }
    }
    
    // Update order status
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $database->query($sql, [$status, $order_id]);
    
    // Create notification for customer
    $notification_title = '';
    $notification_message = '';
    
    switch ($status) {
        case 'confirmed':
            $notification_title = 'Order Confirmed';
            $notification_message = 'Your order #' . $order['order_number'] . ' has been confirmed by the supplier.';
            break;
        case 'preparing':
            $notification_title = 'Order Preparing';
            $notification_message = 'Your order #' . $order['order_number'] . ' is now being prepared.';
            break;
        case 'out_for_delivery':
            $notification_title = 'Order Out for Delivery';
            $notification_message = 'Your order #' . $order['order_number'] . ' is out for delivery.';
            break;
        case 'delivered':
            $notification_title = 'Order Delivered';
            $notification_message = 'Your order #' . $order['order_number'] . ' has been delivered.';
            break;
        case 'cancelled':
            $notification_title = 'Order Cancelled';
            $notification_message = 'Your order #' . $order['order_number'] . ' has been cancelled by the supplier.';
            break;
    }
    
    if ($notification_title && $notification_message) {
        // Get customer user ID
        $customer_sql = "SELECT u.id as user_id FROM customers c JOIN users u ON c.user_id = u.id WHERE c.id = ?";
        $customer = $database->fetch($customer_sql, [$order['customer_id']]);
        
        if ($customer) {
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')";
            $database->query($notification_sql, [$customer['user_id'], $notification_title, $notification_message]);
        }
    }
    
    // Return success response
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    error_log("Order status update failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Failed to update order status']));
}

/**
 * Get supplier ID by user ID
 */
function get_supplier_id_by_user_id($user_id) {
    global $database;
    $sql = "SELECT id FROM suppliers WHERE user_id = ?";
    $result = $database->fetch($sql, [$user_id]);
    return $result ? $result['id'] : null;
}