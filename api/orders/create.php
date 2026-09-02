<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Cart.php';
require_once '../../classes/Order.php';
require_once '../../classes/Customer.php';

header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user_id = get_user_id();
    $input = json_decode(file_get_contents('php://input'), true);

    // Get customer ID from user ID
    $customer = new Customer($database);
    $customer_id = $customer->getCustomerIdByUserId($user_id);

    $cart = new Cart($database, $customer_id);
    $order = new Order($database);
    
    // Validate input
    $required_fields = ['delivery_address', 'payment_method'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }
    
    // Get cart items
    $cart_items = $cart->getCartItems();
    if (empty($cart_items)) {
        throw new Exception('Cart is empty');
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price_per_piece'] * $item['quantity'];
    }
    
    $delivery_fee = 50; // Default delivery fee
    $total = $subtotal + $delivery_fee;
    
    // Create order
    $order_data = [
        'customer_id' => $customer_id,
        'delivery_address' => sanitize_input($input['delivery_address']),
        'delivery_notes' => sanitize_input($input['delivery_notes'] ?? ''),
        'payment_method' => $input['payment_method'],
        'subtotal' => $subtotal,
        'delivery_fee' => $delivery_fee,
        'total_amount' => $total
    ];
    
    $order_id = $order->createOrderFromCart($order_data, $cart_items);
    
    // Clear cart
    $cart->clearCart();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $order_id,
        'total_amount' => $total
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
