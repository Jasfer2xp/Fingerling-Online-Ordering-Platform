<?php
/**
 * Cancel PayPal Payment Flow
 * Clears pending order data and returns user to cart with error message.
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';

// Clear any pending PayPal order data
if (isset($_SESSION['pending_order_data'])) {
    unset($_SESSION['pending_order_data']);
}

$_SESSION['error'] = 'PayPal payment was cancelled. You can review your cart and try again.';

redirect(base_url('customer/cart.php'));