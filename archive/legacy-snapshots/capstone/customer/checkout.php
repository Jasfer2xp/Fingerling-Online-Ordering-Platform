// === HANDLE ORDER PLACEMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action !== 'place_order') {
            throw new Exception('Invalid action');
        }

        $payment_method = $_POST['payment_method'] ?? '';
        $delivery_type  = $_POST['delivery_type'] ?? '';

        // Build delivery address
        $parts = array_filter([
            sanitize_input($_POST['address_line_1'] ?? ''),
            sanitize_input($_POST['address_line_2'] ?? ''),
            sanitize_input($_POST['barangay'] ?? ''),
            sanitize_input($_POST['city'] ?? 'Tangub City'),
            sanitize_input($_POST['province'] ?? 'Misamis Occidental'),
            sanitize_input($_POST['postal_code'] ?? '')
        ]);
        $delivery_address = implode(', ', $parts);
        if (empty($delivery_address)) {
            throw new Exception('Delivery address is required');
        }

        if (!in_array($payment_method, ['gcash', 'paypal'])) {
            throw new Exception('Please select a valid payment method');
        }

        // Delivery type validation
        if (empty($delivery_type) || !in_array($delivery_type, ['truck', 'boat'])) {
            throw new Exception('Please select a valid delivery method');
        }

        logInteraction("User {$user_id} placing order with {$payment_method}");

        // === PAYPAL: defer order creation until payment success ===
        if ($payment_method === 'paypal') {
            $_SESSION['pending_order_data'] = [
                'customer_id'     => $customer_id,
                'delivery_address'=> $delivery_address,
                'payment_method'  => \Order::formatPaymentMethodLabel($payment_method),
                'subtotal'        => $subtotal,
                'delivery_fee'    => $transaction_fee,
                'total_amount'    => $total,
                'delivery_type'   => $delivery_type,
                'cart_items'      => $cart_items
            ];

            $first_item   = $cart_items[0];
            $description  = "Order from {$first_item['business_name']} - " . count($cart_items) . " item(s)";
            redirect(base_url("customer/paypal-checkout.php?total={$total}&desc=" . urlencode($description)));
        }

        // === XENDIT: create order immediately ===
        $order_data = [
            'customer_id'     => $customer_id,
            'delivery_address'=> $delivery_address,
            'payment_method'  => \Order::formatPaymentMethodLabel($payment_method),
            'subtotal'        => $subtotal,
            'delivery_fee'    => $transaction_fee,
            'total_amount'    => $total,
            'delivery_type'   => $delivery_type
        ];

        $order_ids = $order->createOrderFromCart($order_data, $cart_items);
        if (!$order_ids) {
            throw new Exception('Failed to create order');
        }

        $cart->clearCart();
        $first_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;
        $_SESSION['recently_placed_order'] = $first_order_id;

        if ($payment_method === 'gcash') {
            redirect(base_url("customer/payment.php?order_id={$first_order_id}&method=gcash"));
        } else {
            $_SESSION['success'] = 'Order placed successfully!';
            redirect(base_url('customer/orders.php'));
        }

    } catch (Exception $e) {
        logInteraction("Error for user {$user_id}: " . $e->getMessage(), "EXCEPTION");
        $_SESSION['error'] = $e->getMessage();
    }
}