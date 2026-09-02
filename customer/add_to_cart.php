<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Cart.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart.']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get and validate input
    $product_id = intval($_POST['id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);

    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }

    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity.']);
        exit;
    }

    $user_id = get_user_id();

    // Get customer profile to get customer_id
    $user = new User($database);
    $profile = $user->getUserProfile($user_id);
    $customer_id = $profile['id'] ?? null;

    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'Customer profile not found.']);
        exit;
    }

    // Initialize classes with correct parameters
    $product = new Product($database);
    $cart = new Cart($database, $customer_id);

    // Get product details
    $product_details = $product->getProductById($product_id);

    if (!$product_details) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    // Check if product is available
    if ($product_details['availability_status'] !== 'available') {
        echo json_encode(['success' => false, 'message' => 'This product is currently unavailable.']);
        exit;
    }

    // Use the Cart class addItem method which handles existing items and stock validation automatically
    $result = $cart->addItem($product_id, $quantity);

    if ($result) {
        // Get updated cart count
        $cart_count = $cart->getItemCount();

        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully!',
            'cart_count' => $cart_count,
            'debug' => [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'customer_id' => $customer_id,
                'species_name' => $product_details['species_name'] ?? 'Unknown',
                'business_name' => $product_details['business_name'] ?? 'Unknown'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add item to cart. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}