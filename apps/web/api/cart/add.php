<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Customer.php';
require_once '../../classes/Cart.php';

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
$quantity = intval($input['quantity'] ?? 1);

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
    exit;
}

try {
    // Get customer ID
    $customer = new Customer($database);
    $customer_id = $customer->getCustomerIdByUserId(get_user_id());

    if (!$customer_id) {
        throw new Exception('Customer profile not found');
    }
    
    // Add to cart
    $cart = new Cart($database, $customer_id);
    $cart->addItem($product_id, $quantity);
    
    // Get updated cart count
    $cart_count = $cart->getCartCount();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'cart_count' => $cart_count
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
