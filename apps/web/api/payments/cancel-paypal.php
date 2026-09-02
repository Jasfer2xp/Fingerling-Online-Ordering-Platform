<?php
require_once '../config/config.php';
if (isset($_SESSION['pending_order_data'])) {
    unset($_SESSION['pending_order_data']);
}
$_SESSION['error'] = 'PayPal payment was cancelled.';
redirect(base_url('customer/cart.php'));