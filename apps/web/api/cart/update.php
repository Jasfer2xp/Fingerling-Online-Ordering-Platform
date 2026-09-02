<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$product_id = intval($input['product_id'] ?? 0);
$quantity = intval($input['quantity'] ?? 0);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    // Get customer ID
    $user = new User($database);
    $profile = $user->getUserProfile(get_user_id());
    $customer_id = $profile['id'];
    
    // Update cart
    $cart = new Cart($database, $customer_id);
    
    if ($quantity <= 0) {
        $cart->removeItem($product_id);
        $message = 'Item removed from cart';
    } else {
        $cart->updateQuantity($product_id, $quantity);
        $message = 'Cart updated successfully';
    }
    
    // Get updated cart info
    $cart_count = $cart->getItemCount();
    $cart_total = $cart->getTotal();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cart_count,
        'cart_total' => $cart_total
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
