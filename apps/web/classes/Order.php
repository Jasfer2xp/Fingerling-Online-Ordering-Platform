<?php

/**
 * Order Class
 * Handles order management
 */

class Order {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Normalize payment method labels for display/storage.
     */
    public static function formatPaymentMethodLabel(?string $method, ?string $channel = null): string
    {
        $method = trim((string) $method);
        $methodLower = strtolower($method);
        $channelKey = strtoupper(trim((string) $channel));

        $channelMap = [
            'GCASH'         => 'GCash',
            'MAYA'          => 'Maya',
            'MAYA_WALLET'   => 'Maya',
            'QRPH'          => 'QRPH',
            'QR_PH'         => 'QRPH',
            'CARD'          => 'Debit/Credit Card',
            'CREDIT_CARD'   => 'Credit Card',
            'DEBIT_CARD'    => 'Debit Card',
            'BANK_TRANSFER' => 'Bank Transfer',
            'OTC'           => 'Over-the-Counter',
            'OVER_THE_COUNTER' => 'Over-the-Counter',
            'EWALLET'       => 'E-Wallet',
            'GRABPAY'       => 'GrabPay',
        ];

        if (stripos($method, 'xendit -') === 0) {
            return 'Xendit - ' . trim(substr($method, 8));
        }

        if (strpos($methodLower, 'paypal') !== false) {
            return 'PayPal';
        }

        if (isset($channelMap[$channelKey])) {
            return 'Xendit - ' . $channelMap[$channelKey];
        }

        if ($channelKey !== '') {
            return 'Xendit - ' . ucwords(strtolower(str_replace('_', ' ', $channelKey)));
        }

        if (strpos($methodLower, 'xendit') !== false) {
            return 'Xendit - Payment';
        }

        if (isset($channelMap[strtoupper($methodLower)])) {
            return 'Xendit - ' . $channelMap[strtoupper($methodLower)];
        }

        return ucwords($method);
    }
    
    /**
     * Create a notification for a user or customer
     */
    private function createNotification($user_id, $title, $message, $type = 'order', $customer_id = null, $link = null) {
        // Use Manila timezone for timestamp to ensure consistency
        date_default_timezone_set('Asia/Manila');
        $manila_time = date('Y-m-d H:i:s');
        
        // If customer_id is provided, use it; otherwise use user_id
        if ($customer_id !== null) {
            $sql = "INSERT INTO notifications (user_id, customer_id, title, message, link, type, is_read, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?)";
            return $this->db->query($sql, [$user_id, $customer_id, $title, $message, $link, $type, $manila_time]);
        } else {
            $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, ?)";
            return $this->db->query($sql, [$user_id, $title, $message, $type, $manila_time]);
        }
    }
    
    /**
     * Create a conversation between customer and supplier after order confirmation
     */
    private function createOrderConversation($order_id, $customer_id, $supplier_id) {
        try {
            // Check if Message class exists
            if (!class_exists('Message')) {
                require_once __DIR__ . '/Message.php';
            }
            
            $message = new Message($this->db);
            
            // Get customer info
            $customer_sql = "SELECT c.first_name, u.email as customer_email 
                            FROM customers c 
                            JOIN users u ON c.user_id = u.id 
                            WHERE c.id = ?";
            $customer_info = $this->db->fetch($customer_sql, [$customer_id]);
            
            // Get supplier info
            $supplier_sql = "SELECT business_name, user_id 
                            FROM suppliers 
                            WHERE id = ?";
            $supplier_info = $this->db->fetch($supplier_sql, [$supplier_id]);
            
            if (!$customer_info || !$supplier_info) {
                throw new Exception("Failed to get customer or supplier information");
            }
            
            // Create conversation
            $conversation_id = $message->createConversation($customer_id, $supplier_id, $order_id);
            
            if (!$conversation_id) {
                throw new Exception("Failed to create conversation");
            }
            
            // Send automated confirmation message
            $customer_name = $customer_info['first_name'];
            $supplier_name = $supplier_info['business_name'];
            
            // Create a system message
            $auto_message = "🛒 Hello {$customer_name}!\n\n";
            $auto_message .= "Thank you for purchasing from {$supplier_name}.\n";
            $auto_message .= "Your order has been confirmed and is now being prepared for delivery.\n";
            $auto_message .= "You can reply here for any questions or updates about your order.";
            
            // Send message from system (user_id = 1, which is admin)
            $message->sendMessage($conversation_id, 1, $supplier_info['user_id'], $auto_message);
            
        } catch (Exception $e) {
            // Log error but don't interrupt order process
            error_log("Failed to create order conversation: " . $e->getMessage());
        }
    }
    
    /**
     * Create a new order from cart
     */
    public function createOrderFromCart($order_data, $cart_items) {
        try {
            $this->db->beginTransaction();
            
            // Extract data
            $customer_id = $order_data['customer_id'];
            $delivery_address = $order_data['delivery_address'];
            $delivery_notes = $order_data['delivery_notes'];
            $payment_method = $order_data['payment_method'];
            $payment_channel = $order_data['payment_channel'] ?? null;
            $normalized_payment_method = self::formatPaymentMethodLabel($payment_method, $payment_channel);
            $subtotal = $order_data['subtotal'];
            $delivery_fee = $order_data['delivery_fee'];
            $total_amount = $order_data['total_amount'];
            $delivery_type = $order_data['delivery_type'] ?? null;
            
            // Group items by supplier
            $suppliers = [];
            foreach ($cart_items as $item) {
                $supplier_id = $item['supplier_id'] ?? 0;
                if (!isset($suppliers[$supplier_id])) {
                    $suppliers[$supplier_id] = [
                        'items' => [],
                        'total' => 0
                    ];
                }
                $suppliers[$supplier_id]['items'][] = $item;
                $suppliers[$supplier_id]['total'] += $item['subtotal'] ?? 0;
            }
            
            // Keep track of created order IDs
            $order_ids = [];
            
            // Create order for each supplier
            foreach ($suppliers as $supplier_id => $supplier_data) {
                // Get supplier info
                $supplier_sql = "SELECT business_name FROM suppliers WHERE id = ?";
                $supplier_result = $this->db->fetch($supplier_sql, [$supplier_id]);
                
                if (!$supplier_result) {
                    throw new Exception("Supplier not found");
                }
                
                $supplier_name = $supplier_result['business_name'];
                
                // Generate order number
                $order_number = generate_order_number();
                
                // Create order - use the proportion of the supplier's total to the overall total
                // to determine how much of the total_amount should be assigned to this order
                $order_total = ($subtotal > 0) ? ($supplier_data['total'] / $subtotal) * $total_amount : 0;
                
                // Set supplier accept deadline (based on config)
                date_default_timezone_set('Asia/Manila');
                $supplier_accept_deadline = date('Y-m-d H:i:s', strtotime(ORDER_TIMEOUT_INTERVAL)); // updated deadline logic
                
                $sql = "INSERT INTO orders (order_number, customer_id, supplier_id, total_amount, 
                                         delivery_address, notes, payment_method, status, delivery_type, supplier_accept_deadline) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
                $this->db->query($sql, [
                    $order_number, $customer_id, $supplier_id, $order_total, 
                    $delivery_address, $delivery_notes, $normalized_payment_method, $delivery_type, $supplier_accept_deadline
                ]);
                
                $order_id = $this->db->lastInsertId();
                $order_ids[] = $order_id;
                
                // Create order items and update stock
                $order_items = [];
                foreach ($supplier_data['items'] as $item) {
                    // Get product details
                    $inventory_id = $item['inventory_id'] ?? $item['product_id'] ?? $item['id'] ?? 0;
                    $product_sql = "SELECT i.price_per_piece, s.name
                                    FROM inventory i
                                    JOIN species s ON i.species_id = s.id
                                    WHERE i.id = ?";
                    $product = $this->db->fetch($product_sql, [$inventory_id]);
                    
                    if (!$product) {
                        throw new Exception("Product not found");
                    }
                    
                    $quantity = $item['quantity'] ?? 1;
                    $item_subtotal = $quantity * ($product['price_per_piece'] ?? 0);
                    
                    $order_items[] = [
                        'inventory_id' => $inventory_id,
                        'quantity' => $quantity,
                        'unit_price' => $product['price_per_piece'] ?? 0,
                        'subtotal' => $item_subtotal
                    ];
                }
                
                // Create order items and update stock
                foreach ($order_items as $item) {
                    $sql = "INSERT INTO order_items (order_id, inventory_id, quantity, unit_price, subtotal) 
                            VALUES (?, ?, ?, ?, ?)";
                    $this->db->query($sql, [
                        $order_id, $item['inventory_id'], $item['quantity'], 
                        $item['unit_price'], $item['subtotal']
                    ]);
                    
                    // Reserve stock
                    $sql = "UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE id = ?";
                    $this->db->query($sql, [$item['quantity'], $item['inventory_id']]);
                }
                
                // Get supplier user_id to create notification
                $supplier_sql = "SELECT user_id, business_name FROM suppliers WHERE id = ?";
                $supplier_result = $this->db->fetch($supplier_sql, [$supplier_id]);
                
                if ($supplier_result) {
                    // Create notification for supplier
                    $notification_title = "New Order Received";
                    $notification_message = "You have received a new order (#{$order_number}) for PHP " . number_format($supplier_data['total'], 2);
                    $this->createNotification($supplier_result['user_id'], $notification_title, $notification_message, 'order');
                }
            }
            
            $this->db->commit();
            return $order_ids;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get order by ID
     */
    public function getOrderById($order_id, $customer_id = null) {
        $sql = "SELECT o.*,
                       c.first_name, c.last_name, c.contact_number,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       u.email as customer_email,
                       c.contact_number as customer_phone,
                       s.business_name, s.contact_number as supplier_contact,
                       o.delivery_worker, o.delivery_phone as delivery_worker_contact, o.delivery_notes
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                WHERE o.id = ?";
        
        $params = [$order_id];
        
        // If customer_id is provided, verify the order belongs to this customer
        if ($customer_id) {
            $sql .= " AND o.customer_id = ?";
            $params[] = $customer_id;
        }
        
        $order = $this->db->fetch($sql, $params);
        
        // Determine payment status based on payment_method
        if ($order) {
            $paymentMethod = strtolower($order['payment_method'] ?? '');
            if (strpos($paymentMethod, 'xendit') !== false || strpos($paymentMethod, 'paypal') !== false) {
                $order['payment_status'] = 'paid';
            } else {
                $order['payment_status'] = 'pending';
            }
        }
        
        return $order;
    }

    /**
     * Get order details (alias for getOrderById)
     */
    public function getOrderDetails($order_id, $customer_id = null) {
        return $this->getOrderById($order_id, $customer_id);
    }

    /**
     * Get order items
     */
    public function getOrderItems($order_id) {
        $sql = "SELECT oi.*,
                       sp.name as species_name, sp.scientific_name, sp.image_url,
                       sp.name as product_name,
                       i.size_category, i.image_path,
                       s.business_name as supplier_name,
                       'pcs' as unit,
                       oi.subtotal AS total_price,
                       oi.unit_price AS price_per_piece
                FROM order_items oi
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE oi.order_id = ?";
        
        return $this->db->fetchAll($sql, [$order_id]);
    }
    
    /**
     * Get customer orders
     */
    public function getCustomerOrders($customer_id, $status = null, $limit = null, $offset = 0) {
        $where_conditions = ["o.customer_id = ?"];
        $params = [$customer_id];
        
        if ($status) {
            $where_conditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Improved query: Use subquery for item count to avoid GROUP BY issues that might hide orders
        $sql = "SELECT o.*, s.business_name, s.barangay, s.city,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                       o.customer_confirmed_delivery, o.supplier_accept_deadline, o.payment_deadline
                FROM orders o
                JOIN suppliers s ON o.supplier_id = s.id
                WHERE {$where_clause}
                ORDER BY o.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get supplier orders
     */
    public function getSupplierOrders($supplier_id, $status = null, $limit = null, $offset = 0) {
        $where_conditions = ["o.supplier_id = ?"];
        $params = [$supplier_id];
        
        if ($status) {
            $where_conditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT o.*, c.first_name, c.last_name, c.contact_number,
                       o.supplier_accept_deadline, o.supplier_accepted_at, o.payment_deadline
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE {$where_clause}
                ORDER BY o.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus($order_id, $status, $delivery_date = null) {
        // Get current order information
        $sql = "SELECT o.*, s.business_name, c.user_id as customer_user_id FROM orders o 
                JOIN suppliers s ON o.supplier_id = s.id 
                JOIN customers c ON o.customer_id = c.id
                WHERE o.id = ?";
        $order = $this->db->fetch($sql, [$order_id]);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // CRITICAL: If delivery_date is provided, ALWAYS set status to scheduled_for_delivery
        // This handles cases where status might be empty or confirmed_and_paid
        if ($delivery_date) {
            $status = 'scheduled_for_delivery';
        }
        
        // Normalize empty status to handle database inconsistencies
        // If status is empty but delivery_date exists, treat as scheduled_for_delivery
        $current_status = !empty($order['status']) ? $order['status'] : (($order['delivery_date'] ?? null) ? 'scheduled_for_delivery' : 'pending');
        
        // Validate that order status transition is allowed
        $valid_transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled', 'confirmed_and_paid'],
            'confirmed_and_paid' => ['scheduled_for_delivery', 'preparing', 'cancelled'],
            'preparing' => ['scheduled_for_delivery', 'out_for_delivery'],
            'scheduled_for_delivery' => ['preparing', 'out_for_delivery'],
            'out_for_delivery' => ['delivered', 'pending_supplier_review'],
            'pending_supplier_review' => ['delivered', 'out_for_delivery'],
            'delivered' => [],
            'cancelled' => []
        ];
        
        // Allow transition if:
        // 1. It's a valid transition from current status, OR
        // 2. We're setting delivery_date (always allowed to go to scheduled_for_delivery), OR
        // 3. Current status is empty but has delivery_date (treat as scheduled_for_delivery), OR
        // 4. Transitioning from scheduled_for_delivery to preparing/out_for_delivery (normal flow)
        if (!empty($current_status) && $current_status !== 'pending') {
            // Allow idempotency: if status is already same, allow it (useful for delivered -> delivered)
            if ($current_status !== $status) {
                if (isset($valid_transitions[$current_status]) && !in_array($status, $valid_transitions[$current_status])) {
                    // Exception: allow transition to scheduled_for_delivery if delivery_date is set
                    if (!($delivery_date && $status === 'scheduled_for_delivery')) {
                        throw new Exception("Invalid status transition from {$current_status} to {$status}");
                    }
                }
            }
        }
        
        // Store old status for history (use original status, not normalized)
        $old_status = $order['status'];
        
        // Update order['status'] for rest of function to use normalized status
        $order['status'] = $current_status;
        
        // Prevent suppliers from marking orders as delivered directly
        // Only customers can confirm delivery (check if customer_confirmed_delivery is being set)
        if ($status === 'delivered') {
            // Check if customer_confirmed_delivery flag is being set (indicates customer confirmation)
            // This is set in confirm-delivery.php before calling updateOrderStatus
            $check_confirmed = "SELECT customer_confirmed_delivery FROM orders WHERE id = ?";
            $confirmed_check = $this->db->fetch($check_confirmed, [$order_id]);
            
            // If customer hasn't confirmed, check if auto-confirmed
            if (!$confirmed_check || empty($confirmed_check['customer_confirmed_delivery'])) {
                $check_auto = "SELECT auto_confirmed_delivery FROM orders WHERE id = ?";
                $auto_check = $this->db->fetch($check_auto, [$order_id]);
                
                // Allow if current status is 'pending_supplier_review', OR 'delivered' (idempotency), OR auto-confirmed
                // Fix: Check if $auto_check is valid array
                $is_auto_confirmed = $auto_check && !empty($auto_check['auto_confirmed_delivery']);
                
                if (!$is_auto_confirmed && !in_array($current_status, ['pending_supplier_review', 'delivered'])) {
                    throw new Exception("Orders can only be marked as delivered when the customer confirms receipt or after auto-confirmation. Please upload proof of delivery and wait for customer confirmation.");
                }
            }
        }
        
        // Prepare fields to update - ALWAYS update status (critical for preparing, out_for_delivery, delivered)
        $fields = ['status = ?'];
        $params = [$status];
        
        // Set payment deadline when order is confirmed (based on config)
        if ($status === 'confirmed') {
            date_default_timezone_set('Asia/Manila');
            $payment_deadline = date('Y-m-d H:i:s', strtotime(ORDER_TIMEOUT_INTERVAL)); // updated deadline logic
            $fields[] = 'payment_deadline = ?';
            $params[] = $payment_deadline;
        }
        
        // Add delivery date if provided
        if ($delivery_date) {
            $fields[] = 'delivery_date = ?';
            $params[] = $delivery_date;
        }
        
        // Check if eta_time column exists
        try {
            $check_sql = "SELECT eta_time FROM orders LIMIT 1";
            $this->db->query($check_sql);
            $etaColumnExists = true;
        } catch (Exception $e) {
            $etaColumnExists = false;
        }
        
        // Clear ETA when order is delivered (only if column exists)
        if ($status === 'delivered' && $etaColumnExists) {
            $fields[] = 'eta_time = NULL';
        }
        
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $order_id;
        
        // Log the update for debugging
        error_log("Order::updateOrderStatus - Updating order {$order_id} to status '{$status}' with SQL: {$sql}");
        
        $result = $this->db->query($sql, $params);
        
        // Compute 3% Admin Commission on delivery
        if ($status === 'delivered') {
            try {
                $commission_check = $this->db->fetch("SELECT id, total_amount, admin_commission FROM orders WHERE id = ?", [$order_id]);
                if ($commission_check && ($commission_check['admin_commission'] === null || $commission_check['admin_commission'] === '')) {
                    $commission = round(floatval($commission_check['total_amount']) * 0.03, 2);
                    $this->db->query("UPDATE orders SET admin_commission = ? WHERE id = ?", [$commission, $order_id]);
                    error_log("Admin Commission calculated for Order {$order_id}: {$commission} (3% of {$commission_check['total_amount']})");
                }
            } catch (Exception $e) {
                error_log("Failed to calculate admin commission: " . $e->getMessage());
            }
        }
        
        // Verify the update was successful
        $verify_sql = "SELECT status FROM orders WHERE id = ?";
        $verify_result = $this->db->fetch($verify_sql, [$order_id]);
        if ($verify_result && $verify_result['status'] !== $status) {
            error_log("WARNING: Order {$order_id} status update verification failed. Expected: '{$status}', Got: '{$verify_result['status']}'");
            // Force update directly
            $this->db->query("UPDATE orders SET status = ? WHERE id = ?", [$status, $order_id]);
        }
        
        // Add entry to order_status_history (only if status actually changed and no duplicate exists)
        if ($old_status !== $status) {
            try {
                // Check if order_status_history table exists
                $check_table = "SHOW TABLES LIKE 'order_status_history'";
                $table_exists = $this->db->fetch($check_table);
                
                if ($table_exists) {
                    // Check if this status change already exists (prevent duplicates)
                    $check_duplicate = "SELECT id FROM order_status_history WHERE order_id = ? AND status = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1";
                    $duplicate = $this->db->fetch($check_duplicate, [$order_id, $status]);
                    
                    if (!$duplicate) {
                        $history_sql = "INSERT INTO order_status_history (order_id, status, previous_status, created_at) VALUES (?, ?, ?, NOW())";
                        $this->db->query($history_sql, [$order_id, $status, $old_status]);
                    }
                } else {
                    // Create table if it doesn't exist
                    $create_table = "CREATE TABLE IF NOT EXISTS order_status_history (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_id INT NOT NULL,
                        status VARCHAR(50) NOT NULL,
                        previous_status VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_order_id (order_id),
                        INDEX idx_status (status),
                        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                    )";
                    $this->db->query($create_table);
                    
                    // Insert history entry
                    $history_sql = "INSERT INTO order_status_history (order_id, status, previous_status, created_at) VALUES (?, ?, ?, NOW())";
                    $this->db->query($history_sql, [$order_id, $status, $old_status]);
                }
            } catch (Exception $e) {
                // Log error but don't fail the status update
                error_log("Failed to insert order_status_history: " . $e->getMessage());
            }
        }
        
        // Handle stock updates based on status changes
        // Deduct stock when status changes to 'preparing'
        if ($status === 'preparing' && $order['status'] !== 'preparing') {
            $this->updateInventoryForStatusChange($order_id, 'deduct');
        }
        // Restore stock when status changes to 'cancelled' from 'preparing'
        else if ($status === 'cancelled' && $order['status'] === 'preparing') {
            $this->updateInventoryForStatusChange($order_id, 'restore');
        }
        
        // Send email notification when status changes to confirmed or cancelled
        if ($order && ($status === 'confirmed' || $status === 'cancelled')) {
            $this->sendOrderStatusEmail($order, $status);
            
            // Create notification for customer
            if ($status === 'confirmed') {
                // Check if delivery type is boat to create special notification
                if (!empty($order['delivery_type']) && strtolower($order['delivery_type']) === 'boat') {
                    // Special notification for boat delivery
                    $notification_title = "Order Confirmed";
                    $notification_message = "Order #{$order['order_number']} has been confirmed. The supplier will contact you to discuss the meet-up details. Please stay active in the chat messages.";
                } else {
                    // Standard notification for other delivery types (truck, etc.)
                    $notification_title = "Order Confirmed";
                    $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been confirmed and is now being prepared.";
                }
                
                // Create notification with customer_id and link
                $notification_link = base_url("customer/order-details.php?id=" . $order_id);
                $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
                
                $this->createOrderConversation($order_id, $order['customer_id'], $order['supplier_id']);
            }
        }
        // Send email notification for delivery-related status updates
        else if ($order && in_array($status, ['preparing', 'out_for_delivery', 'delivered'])) {
            $this->sendDeliveryUpdateEmail($order, $status, $delivery_date);
            
            // Create notification for customer to rate supplier when order is delivered
            if ($status === 'delivered') {
                $notification_title = "Order Delivered";
                $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been delivered. Please take a moment to rate your experience.";
                
                // Create notification with customer_id and link
                $notification_link = base_url("customer/rate-supplier.php?order_id=" . $order_id . "&supplier_id=" . $order['supplier_id']);
                $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            }
        }
        
        // Send SMS notification for order timeline stages (only send once per status change)
        if ($order && $order['status'] !== $status) {
            require_once __DIR__ . '/../config/sms.php';
            
            // Get customer contact number
            $customer_sql = "SELECT contact_number FROM customers WHERE id = ?";
            $customer = $this->db->fetch($customer_sql, [$order['customer_id']]);
            
            if ($customer && !empty($customer['contact_number'])) {
                $phone = $customer['contact_number'];
                // Ensure phone number is in correct format (add country code if needed)
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) === 11 && strpos($phone, '0') === 0) {
                    $phone = '63' . substr($phone, 1);
                } elseif (strlen($phone) === 10) {
                    $phone = '63' . $phone;
                }
                
                $order_number = $order['order_number'];
                $sms_message = '';
                
                switch ($status) {
                    case 'pending':
                        // SMS not sent for pending (initial state)
                        break;
                    case 'confirmed':
                    case 'confirmed_and_paid':
                        // SMS not sent for confirmed (handled separately)
                        break;
                    case 'preparing':
                        $sms_message = "Your order {$order_number} is now being prepared.";
                        break;
                    case 'scheduled_for_delivery':
                        // Get delivery date range and worker contact for SMS
                        $delivery_info_sql = "SELECT delivery_date_start, delivery_date_end, delivery_worker, delivery_worker_contact, delivery_notes FROM orders WHERE id = ?";
                        $delivery_info = $this->db->fetch($delivery_info_sql, [$order_id]);
                        $delivery_date_text = '';
                        $worker_contact_text = '';
                        
                        if ($delivery_info) {
                            // Format date range
                            if (!empty($delivery_info['delivery_date_start']) && !empty($delivery_info['delivery_date_end'])) {
                                $start_date = new DateTime($delivery_info['delivery_date_start']);
                                $end_date = new DateTime($delivery_info['delivery_date_end']);
                                $delivery_date_text = ' from ' . $start_date->format('M d, Y') . ' to ' . $end_date->format('M d, Y');
                            } elseif (!empty($delivery_info['delivery_date'])) {
                                $delivery_date = new DateTime($delivery_info['delivery_date']);
                                $delivery_date_text = ' on ' . $delivery_date->format('F j, Y');
                            }
                            
                            // Get delivery worker contact from notes or dedicated field
                            $worker_contact = '';
                            if (!empty($delivery_info['delivery_worker_contact'])) {
                                $worker_contact = $delivery_info['delivery_worker_contact'];
                            } elseif (!empty($delivery_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $delivery_info['delivery_notes'], $matches)) {
                                $worker_contact = trim($matches[1]);
                            }
                            
                            if ($worker_contact) {
                                $worker_contact_text = " Delivery worker contact: {$worker_contact}.";
                            }
                        }
                        $sms_message = "Your order {$order_number} is scheduled for delivery{$delivery_date_text}.{$worker_contact_text} Please select your preferred date and time.";
                        break;
                    case 'out_for_delivery':
                        // Get delivery worker contact for SMS
                        $worker_info_sql = "SELECT delivery_worker, delivery_worker_contact, delivery_notes FROM orders WHERE id = ?";
                        $worker_info = $this->db->fetch($worker_info_sql, [$order_id]);
                        $worker_contact_text = '';
                        
                        if ($worker_info) {
                            $worker_contact = '';
                            if (!empty($worker_info['delivery_worker_contact'])) {
                                $worker_contact = $worker_info['delivery_worker_contact'];
                            } elseif (!empty($worker_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $worker_info['delivery_notes'], $matches)) {
                                $worker_contact = trim($matches[1]);
                            }
                            
                            if ($worker_contact) {
                                $worker_name = !empty($worker_info['delivery_worker']) ? $worker_info['delivery_worker'] : 'Delivery worker';
                                $worker_contact_text = " Contact {$worker_name}: {$worker_contact}.";
                            }
                        }
                        $sms_message = "Your order {$order_number} is now out for delivery.{$worker_contact_text}";
                        break;
                    case 'delivered':
                        $sms_message = "Your order {$order_number} has been delivered. Thank you!";
                        break;
                }
                
                if (!empty($sms_message)) {
                    // Prevent duplicate SMS - check if SMS was already sent for this status (table might not exist)
                    $sms_sent = false;
                    try {
                        $sms_log_check = "SELECT id FROM order_sms_log WHERE order_id = ? AND status = ? LIMIT 1";
                        $sms_log_result = $this->db->fetch($sms_log_check, [$order_id, $status]);
                        $sms_sent = ($sms_log_result !== false);
                    } catch (Exception $e) {
                        // Table doesn't exist, that's okay - proceed with SMS
                    }
                    
                    if (!$sms_sent) {
                        // Use new function signature: sendSemaphoreSMS($orderId, $customerPhone, $message)
                        $sms_success = sendSemaphoreSMS($order_id, $phone, $sms_message);
                        if ($sms_success) {
                            // Log successful SMS send to prevent duplicates (table might not exist, use try-catch)
                            try {
                                $log_sql = "INSERT INTO order_sms_log (order_id, status, phone, message, sent_at) VALUES (?, ?, ?, ?, NOW())";
                                $this->db->query($log_sql, [$order_id, $status, $phone, $sms_message]);
                            } catch (Exception $e) {
                                // Table might not exist, that's okay - we'll rely on status check
                            }
                        }
                        // Errors are already logged in sendSemaphoreSMS function
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Cancel an order and restore inventory
     */
    public function cancelOrder($order_id) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $order_sql = "SELECT o.*, c.user_id as customer_user_id, s.user_id as supplier_user_id, s.business_name
                         FROM orders o
                         JOIN customers c ON o.customer_id = c.id
                         JOIN suppliers s ON o.supplier_id = s.id
                         WHERE o.id = ?";
            $order = $this->db->fetch($order_sql, [$order_id]);
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Update order status to cancelled
            $sql = "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$order_id]);
            
            // ensured stock is restored on cancellation
            $this->updateInventoryForStatusChange($order_id, 'restore');
            
            // Create notification for customer
            $notification_title = "Order Cancelled";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been cancelled.";
            // Create notification with customer_id and link
            $notification_link = base_url("customer/order-details.php?id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Send email notification
            $this->sendOrderStatusEmail($order, 'cancelled');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to cancel order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update inventory based on order status changes
     */
    private function updateInventoryForStatusChange($order_id, $action = 'deduct') {
        try {
            // Get order items
            $items = $this->getOrderItems($order_id);
            
            foreach ($items as $item) {
                $quantity = $item['quantity'];
                $inventory_id = $item['inventory_id'];
                
                // Deduct or restore stock based on action
                if ($action === 'deduct') {
                    $sql = "UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE id = ?";
                } else if ($action === 'restore') {
                    $sql = "UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE id = ?";
                } else {
                    continue; // Invalid action
                }
                
                $this->db->query($sql, [$quantity, $inventory_id]);
            }
        } catch (Exception $e) {
            error_log("Failed to update inventory for order $order_id: " . $e->getMessage());
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($user_id, $order_number) {
        try {
            $sql = "UPDATE notifications 
                    SET is_read = true 
                    WHERE user_id = ? AND message LIKE ? AND is_read = false";
            $message_pattern = "%order #$order_number has been delivered%";
            return $this->db->query($sql, [$user_id, $message_pattern]);
        } catch (Exception $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order status email
     */
    private function sendOrderStatusEmail($order, $new_status) {
        // Include mailer
        require_once __DIR__ . '/../includes/mailer.php';
        
        // Get customer information
        $customer = new Customer($this->db);
        $customer_info = $customer->getCustomerById($order['customer_id']);
        
        if (!$customer_info) {
            return; // Cannot send email without customer info
        }
        
        // Check if customer has email notifications enabled for orders
        // For now, we'll assume all customers want order notifications
        // In a more advanced implementation, we would check their preferences
        
        $to = $customer_info['email'];
        $subject = "Order #{$order['order_number']} Status Update";
        
        // Prepare email content based on status
        if ($new_status === 'confirmed') {
            $message = "<h2>Order Approved</h2>";
            $message .= "<p>Your order has been approved by the supplier and is now being prepared.</p>";
            $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
            $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
            $message .= "<p>Thank you for your order!</p>";
        } elseif ($new_status === 'cancelled') {
            $message = "<h2>Order Declined</h2>";
            $message .= "<p>We're sorry, but your order has been declined by the supplier. Please try again later.</p>";
            $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
            $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
            $message .= "<p>If you have any questions, please contact the supplier directly.</p>";
        } else {
            // For other statuses, don't send email
            return;
        }
        
        // Send email
        send_app_email($to, $subject, $message, true);
    }
    
    /**
     * Send email notification for delivery-related status updates
     */
    private function sendDeliveryUpdateEmail($order, $status, $delivery_date = null) {
        // Include mailer
        require_once __DIR__ . '/../includes/mailer.php';
        
        // Get customer information
        $customer = new Customer($this->db);
        $customer_info = $customer->getCustomerById($order['customer_id']);
        
        if (!$customer_info) {
            return; // Cannot send email without customer info
        }
        
        $to = $customer_info['email'];
        
        // Prepare email content based on status
        switch ($status) {
            case 'preparing':
            case 'scheduled_for_delivery':
                $subject = "Delivery Scheduled for Your Order #{$order['order_number']}";
                $message = "<h2>Delivery Scheduled</h2>";
                $message .= "<p>Your order for fingerlings has been scheduled for delivery.</p>";
                
                // Get delivery date range and worker contact
                $delivery_info_sql = "SELECT delivery_date_start, delivery_date_end, delivery_worker, delivery_worker_contact, delivery_notes FROM orders WHERE id = ?";
                $delivery_info = $this->db->fetch($delivery_info_sql, [$order['id']]);
                
                if ($delivery_info) {
                    if (!empty($delivery_info['delivery_date_start']) && !empty($delivery_info['delivery_date_end'])) {
                        $start_date = date('M d, Y', strtotime($delivery_info['delivery_date_start']));
                        $end_date = date('M d, Y', strtotime($delivery_info['delivery_date_end']));
                        $message .= "<p><strong>Delivery Date Range:</strong> {$start_date} - {$end_date}</p>";
                        $message .= "<p>Please select your preferred date and time from this range.</p>";
                    } elseif ($delivery_date) {
                        $message .= "<p><strong>Delivery Date:</strong> " . date('F j, Y', strtotime($delivery_date)) . "</p>";
                    }
                    
                    // Get delivery worker contact
                    $worker_contact = '';
                    if (!empty($delivery_info['delivery_worker_contact'])) {
                        $worker_contact = $delivery_info['delivery_worker_contact'];
                    } elseif (!empty($delivery_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $delivery_info['delivery_notes'], $matches)) {
                        $worker_contact = trim($matches[1]);
                    }
                    
                    if (!empty($delivery_info['delivery_worker'])) {
                        $message .= "<p><strong>Delivery Worker:</strong> {$delivery_info['delivery_worker']}";
                        if ($worker_contact) {
                            $message .= " (Contact: {$worker_contact})";
                        }
                        $message .= "</p>";
                    } elseif ($worker_contact) {
                        $message .= "<p><strong>Delivery Worker Contact:</strong> {$worker_contact}</p>";
                    }
                }
                
                $message .= "<p>Please prepare to receive your order.</p>";
                $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
                $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
                break;
                
            case 'out_for_delivery':
                $subject = "Your Order is Out for Delivery!";
                $message = "<h2>Order Out for Delivery</h2>";
                $message .= "<p>Your order is now on its way! Expect arrival soon.</p>";
                
                // Get delivery worker contact
                $worker_info_sql = "SELECT delivery_worker, delivery_worker_contact, delivery_notes FROM orders WHERE id = ?";
                $worker_info = $this->db->fetch($worker_info_sql, [$order['id']]);
                
                if ($worker_info) {
                    $worker_contact = '';
                    if (!empty($worker_info['delivery_worker_contact'])) {
                        $worker_contact = $worker_info['delivery_worker_contact'];
                    } elseif (!empty($worker_info['delivery_notes']) && preg_match('/Delivery Worker Contact:\s*([^\n]+)/', $worker_info['delivery_notes'], $matches)) {
                        $worker_contact = trim($matches[1]);
                    }
                    
                    if (!empty($worker_info['delivery_worker'])) {
                        $message .= "<p><strong>Delivery Worker:</strong> {$worker_info['delivery_worker']}";
                        if ($worker_contact) {
                            $message .= " (Contact: {$worker_contact})";
                        }
                        $message .= "</p>";
                    } elseif ($worker_contact) {
                        $message .= "<p><strong>Delivery Worker Contact:</strong> {$worker_contact}</p>";
                    }
                }
                
                $message .= "<p>Please keep your phone available for updates.</p>";
                $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
                $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
                break;
                
            case 'delivered':
                $subject = "Order Delivered Successfully";
                $message = "<h2>Order Delivered</h2>";
                $message .= "<p>Your order has been successfully delivered.</p>";
                $message .= "<p>Thank you for purchasing with us!</p>";
                $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
                $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
                break;
                
            default:
                return; // Don't send email for other statuses
        }
        
        // Send email
        send_app_email($to, $subject, $message, true);
    }
    
    /**
     * Send email notification when ETA is set
     */
    public function sendETAEmail($order, $eta_time) {
        // Include mailer
        require_once __DIR__ . '/../includes/mailer.php';
        
        // Get customer information
        $customer = new Customer($this->db);
        $customer_info = $customer->getCustomerById($order['customer_id']);
        
        if (!$customer_info) {
            return; // Cannot send email without customer info
        }
        
        $to = $customer_info['email'];
        $subject = "Estimated Arrival Time for Your Order #{$order['order_number']}";
        $message = "<h2>Estimated Arrival Time</h2>";
        $message .= "<p>Your order is expected to arrive around <strong>{$eta_time}</strong> today.</p>";
        $message .= "<p>Thank you for trusting our platform!</p>";
        $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
        $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
        
        // Send email
        send_app_email($to, $subject, $message, true);
    }
    
    /**
     * Get order statistics
     */
    public function getOrderStats($customer_id = null, $supplier_id = null) {
        $where_conditions = [];
        $params = [];
        
        if ($customer_id) {
            $where_conditions[] = "customer_id = ?";
            $params[] = $customer_id;
        }
        
        if ($supplier_id) {
            $where_conditions[] = "supplier_id = ?";
            $params[] = $supplier_id;
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
                    COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
                    COUNT(CASE WHEN status = 'out_for_delivery' THEN 1 END) as out_for_delivery_orders,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                    SUM(total_amount) as total_revenue
                FROM orders {$where_clause}";
        
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Get recent orders
     */
    public function getRecentOrders($customer_id, $limit = 5) {
        $sql = "SELECT o.*, s.business_name 
                FROM orders o
                JOIN suppliers s ON o.supplier_id = s.id
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$customer_id, $limit]);
    }
    
    /**
     * Check if customer can rate an order
     */
    public function canRateOrder($order_id, $customer_id) {
        // Check if order exists and belongs to customer
        $sql = "SELECT o.id, o.status FROM orders o 
                WHERE o.id = ? AND o.customer_id = ? AND o.status = 'delivered'";
        $order = $this->db->fetch($sql, [$order_id, $customer_id]);
        
        if (!$order) {
            return false;
        }
        
        // Check if already rated
        $sql = "SELECT id FROM feedback WHERE order_id = ?";
        $existing_feedback = $this->db->fetch($sql, [$order_id]);
        
        return !$existing_feedback;
    }
    
    /**
     * Confirm order by supplier - changes status to confirmed
     */
    public function confirmOrderBySupplier($order_id, $supplier_id) {
        try {
            $this->db->beginTransaction();
            
            // Verify order belongs to supplier and is in correct status
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.supplier_id = ? AND o.status = 'pending'";
            $order = $this->db->fetch($sql, [$order_id, $supplier_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not eligible for confirmation");
            }
            
            // Set payment deadline (based on config) and supplier accepted timestamp
            date_default_timezone_set('Asia/Manila');
            $payment_deadline = date('Y-m-d H:i:s', strtotime(ORDER_TIMEOUT_INTERVAL)); // updated deadline logic
            $supplier_accepted_at = date('Y-m-d H:i:s');
            
            // Update order status to confirmed, set supplier_accepted_at and payment_deadline
            $sql = "UPDATE orders SET status = 'confirmed', supplier_accepted_at = ?, payment_deadline = ?, updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$supplier_accepted_at, $payment_deadline, $order_id]);
            
            // Create notification for customer
            $notification_title = "Order Confirmed - Payment Required";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been confirmed. Please proceed with payment within " . ORDER_TIMEOUT_LABEL . ".";
            $notification_link = base_url("customer/checkout.php?order_id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Send email notification
            $this->sendOrderConfirmationEmail($order);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to confirm order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reject order by supplier
     */
    public function rejectOrderBySupplier($order_id, $supplier_id) {
        try {
            $this->db->beginTransaction();
            
            // Verify order belongs to supplier and is in correct status
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.supplier_id = ? AND o.status = 'pending'";
            $order = $this->db->fetch($sql, [$order_id, $supplier_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not eligible for rejection");
            }
            
            // Update order status to cancelled with reason
            $sql = "UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = 'rejected_by_supplier', updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$order_id]);
            
            // ensured stock is restored on cancellation
            $this->updateInventoryForStatusChange($order_id, 'restore');
            
            // Create notification for customer
            $notification_title = "Order Rejected";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been rejected by the supplier.";
            $notification_link = base_url("customer/order-details.php?id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Send email notification
            $this->sendOrderStatusEmail($order, 'cancelled');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to reject order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cancel order due to supplier timeout
     */
    public function cancelOrderSupplierNoResponse($order_id) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.status = 'pending'";
            $order = $this->db->fetch($sql, [$order_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not in pending status");
            }
            
            // Update order status to archived (timeout)
            $sql = "UPDATE orders SET status = 'archived', cancelled_at = NOW(), archived_at = NOW(), cancellation_reason = 'supplier_timeout', updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$order_id]);
            
            // ensured stock is restored on cancellation
            $this->updateInventoryForStatusChange($order_id, 'restore');
            
            // Create notification for customer
            $notification_title = "Order Cancelled - Supplier Timeout";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been cancelled because the supplier did not respond within " . ORDER_TIMEOUT_LABEL . ".";
            $notification_link = base_url("customer/order-details.php?id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to cancel order due to supplier timeout: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cancel order due to customer payment timeout
     */
    public function cancelOrderCustomerFailedToPay($order_id) {
        try {
            $this->db->beginTransaction();
            
            // Get order details
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.user_id as supplier_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.status = 'confirmed'";
            $order = $this->db->fetch($sql, [$order_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not in confirmed status");
            }
            
            // Update order status to archived (timeout)
            $sql = "UPDATE orders SET status = 'archived', cancelled_at = NOW(), archived_at = NOW(), cancellation_reason = 'customer_payment_timeout', updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$order_id]);
            
            // ensured stock is restored on cancellation
            $this->updateInventoryForStatusChange($order_id, 'restore');
            
            // Create notification for customer
            $notification_title = "Order Cancelled - Payment Timeout";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been cancelled because payment was not completed within " . ORDER_TIMEOUT_LABEL . ".";
            $notification_link = base_url("customer/order-details.php?id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Create notification for supplier
            $notification_title_supplier = "Order Cancelled - Customer Payment Timeout";
            $notification_message_supplier = "Order #{$order['order_number']} has been cancelled because the customer did not complete payment within " . ORDER_TIMEOUT_LABEL . ".";
            $this->createNotification($order['supplier_user_id'], $notification_title_supplier, $notification_message_supplier, 'order');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to cancel order due to payment timeout: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Archive order due to customer non-payment (Same logic as cancel but explicitly for archiving)
     * Used by supplier/orders.php auto-archive check
     */
    public function archiveOrderForNonPayment($order_id) {
        return $this->cancelOrderCustomerFailedToPay($order_id);
    }
    
    /**
     * Cancel order by supplier before payment (while in confirmed status)
     */
    public function cancelOrderBySupplierBeforePayment($order_id, $supplier_id) {
        try {
            $this->db->beginTransaction();
            
            // Verify order belongs to supplier and is in correct status
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.supplier_id = ? AND o.status = 'confirmed'";
            $order = $this->db->fetch($sql, [$order_id, $supplier_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not eligible for cancellation");
            }
            
            // Update order status to cancelled with reason
            $sql = "UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = 'cancelled_by_supplier', updated_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$order_id]);
            
            // ensured stock is restored on cancellation
            $this->updateInventoryForStatusChange($order_id, 'restore');
            
            // Create notification for customer
            $notification_title = "Order Cancelled by Supplier";
            $notification_message = "Your order #{$order['order_number']} from {$order['business_name']} has been cancelled by the supplier.";
            $notification_link = base_url("customer/order-details.php?id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Send email notification
            $this->sendOrderStatusEmail($order, 'cancelled');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to cancel order by supplier: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validate payment amount matches order total exactly
     */
    public function validatePaymentAmount($order_id, $payment_amount) {
        $sql = "SELECT total_amount FROM orders WHERE id = ?";
        $order = $this->db->fetch($sql, [$order_id]);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Compare amounts with precision (allowing for floating point differences)
        $difference = abs($order['total_amount'] - $payment_amount);
        $tolerance = 0.01; // Allow 1 cent difference for floating point precision
        
        if ($difference > $tolerance) {
            throw new Exception("Payment amount mismatch. Expected: " . number_format($order['total_amount'], 2) . ", Received: " . number_format($payment_amount, 2));
        }
        
        return true;
    }
    
    /**
     * Process successful payment - update order to confirmed_and_paid
     */
    public function processPaymentSuccess($order_id, $payment_data) {
        try {
            $this->db->beginTransaction();
            
            // Verify order is in confirmed or confirmed_and_paid status
            $sql = "SELECT o.*, c.user_id as customer_user_id, s.user_id as supplier_user_id, s.business_name 
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN suppliers s ON o.supplier_id = s.id
                    WHERE o.id = ? AND o.status IN ('confirmed', 'confirmed_and_paid')";
            $order = $this->db->fetch($sql, [$order_id]);
            
            if (!$order) {
                throw new Exception("Order not found or not in confirmed/confirmed_and_paid status");
            }
            
            // Validate payment amount
            $this->validatePaymentAmount($order_id, $payment_data['amount']);
            
            // Update order status to confirmed_and_paid (customer sees as "Paid & Processing", supplier sees "Set Date" button)
            // This happens atomically when payment succeeds
            // Generate 3-day delivery window (paid date + 0 to +3 days = 4 days total, e.g., Dec 4-7)
            date_default_timezone_set('Asia/Manila');
            $paid_date = new DateTime();
            $delivery_start = clone $paid_date;
            $delivery_end = clone $paid_date;
            $delivery_end->modify('+3 days'); // Paid date + 3 days = 4-day window (e.g., Dec 4-7)
            
            $sql = "UPDATE orders SET 
                    status = 'confirmed_and_paid', 
                    delivery_date_start = ?,
                    delivery_date_end = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
            $this->db->query($sql, [
                $delivery_start->format('Y-m-d'),
                $delivery_end->format('Y-m-d'),
                $order_id
            ]);
            
            // Create or update payment record
            $payment_check = "SELECT id FROM payments WHERE order_id = ?";
            $existing_payment = $this->db->fetch($payment_check, [$order_id]);
            
            if ($existing_payment) {
                $payment_sql = "UPDATE payments SET 
                                status = 'paid', 
                                payment_date = NOW(), 
                                transaction_id = ?,
                                payer_email = ?,
                                amount = ?,
                                currency = ?,
                                signature = ?
                                WHERE order_id = ?";
                $this->db->query($payment_sql, [
                    $payment_data['transaction_id'] ?? null,
                    $payment_data['payer_email'] ?? null,
                    $payment_data['amount'],
                    $payment_data['currency'] ?? 'PHP',
                    $payment_data['signature'] ?? null,
                    $order_id
                ]);
            } else {
                $payment_sql = "INSERT INTO payments (order_id, payment_method, amount, transaction_id, status, payment_date, payer_email, currency, signature, created_at) 
                               VALUES (?, ?, ?, ?, 'paid', NOW(), ?, ?, ?, NOW())";
                $this->db->query($payment_sql, [
                    $order_id,
                    $payment_data['payment_method'] ?? 'unknown',
                    $payment_data['amount'],
                    $payment_data['transaction_id'] ?? null,
                    $payment_data['payer_email'] ?? null,
                    $payment_data['currency'] ?? 'PHP',
                    $payment_data['signature'] ?? null
                ]);
            }
            
            // Create notification for customer with delivery date selection link
            $notification_title = "Payment Successful - Select Delivery Date";
            $delivery_window = $delivery_start->format('M d, Y') . ' - ' . $delivery_end->format('M d, Y');
            $notification_message = "Your payment for order #{$order['order_number']} has been processed. Please select your delivery date and time from the available window: {$delivery_window}";
            $notification_link = base_url("customer/select-delivery-date.php?order_id=" . $order_id);
            $this->createNotification($order['customer_user_id'], $notification_title, $notification_message, 'order', $order['customer_id'], $notification_link);
            
            // Create notification for supplier
            $notification_title_supplier = "Payment Received";
            $notification_message_supplier = "Payment has been received for order #{$order['order_number']}. Please proceed with order preparation.";
            $this->createNotification($order['supplier_user_id'], $notification_title_supplier, $notification_message_supplier, 'order');
            
            $this->db->commit();
            
            // Send auto payment message after payment is successfully processed
            try {
                require_once __DIR__ . '/../includes/auto_payment_message.php';
                if (function_exists('send_auto_payment_message')) {
                    $message_result = send_auto_payment_message($this->db, $order_id);
                    if ($message_result) {
                        error_log("ORDER: Auto payment message sent successfully for order {$order_id}");
                    } else {
                        error_log("ORDER: Failed to send auto payment message for order {$order_id}");
                    }
                }
            } catch (Exception $e) {
                error_log("ORDER: Exception sending auto payment message for order {$order_id} - " . $e->getMessage());
                // Don't fail the payment processing if message fails
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to process payment success: " . $e->getMessage());
            throw $e;
        }
    }
    



    
    /**
     * Send email notification when order is confirmed by supplier
     */
    private function sendOrderConfirmationEmail($order) {
        // Include mailer
        require_once __DIR__ . '/../includes/mailer.php';
        
        // Get customer information
        $customer = new Customer($this->db);
        $customer_info = $customer->getCustomerById($order['customer_id']);
        
        if (!$customer_info) {
            return; // Cannot send email without customer info
        }
        
        $to = $customer_info['email'];
        $subject = "Order #{$order['order_number']} Confirmed - Payment Required";
        $message = "<h2>Order Confirmed</h2>";
        $message .= "<p>Your order has been confirmed by the supplier. Please proceed with payment within " . ORDER_TIMEOUT_LABEL . ".</p>";
        $message .= "<p><strong>Order Number:</strong> {$order['order_number']}</p>";
        $message .= "<p><strong>Supplier:</strong> {$order['business_name']}</p>";
        $message .= "<p><strong>Total Amount:</strong> PHP " . number_format($order['total_amount'], 2) . "</p>";
         $message .= "<p><a href='" . base_url("customer/checkout.php?order_id=" . $order['id']) . "'>Click here to proceed with payment</a></p>";
        $message .= "<p>Thank you for your order!</p>";
        
        // Send email
        send_app_email($to, $subject, $message, true);
    }
}