<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

try {
    // Get customer ID
    $user = new User($database);
    $profile = $user->getUserProfile(get_user_id());
    $customer_id = $profile['id'];
    
    // Get cart count
    $cart = new Cart($database, $customer_id);
    $count = $cart->getItemCount();
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => $e->getMessage()
    ]);
}
?>
