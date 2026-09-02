<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $product_id = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 1);
    
    if (!$product_id || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
        exit;
    }
    
    $user_id = get_user_id();
    $customer = new Customer($database);
    
    // Get customer ID
    $sql = "SELECT id FROM customers WHERE user_id = ?";
    $customer_data = $database->fetch($sql, [$user_id]);
    
    if (!$customer_data) {
        echo json_encode(['success' => false, 'message' => 'Customer profile not found']);
        exit;
    }
    
    $customer_id = $customer_data['id'];
    
    // Verify product exists and is available
    $sql = "SELECT i.*, sp.name as species_name, s.business_name
            FROM inventory i
            JOIN species sp ON i.species_id = sp.id
            JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.id = ? AND i.availability_status = 'available'";
    $product = $database->fetch($sql, [$product_id]);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or not available']);
        exit;
    }
    
    // Check if enough stock is available
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    // Use the Cart class instead of direct database queries
    require_once '../classes/Cart.php';
    $cart = new Cart($database, $customer_id);

    try {
        $cart->addItem($product_id, $quantity);

        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'action' => 'added',
            'quantity' => $quantity
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding to cart']);
}
