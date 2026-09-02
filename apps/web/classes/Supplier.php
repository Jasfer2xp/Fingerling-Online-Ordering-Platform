<?php

/**
 * Supplier Class
 * Handles supplier-specific functionality
 */

class Supplier {
    private $db;
    private $supplier_id;
    
    public function __construct($database, $supplier_id = null) {
        $this->db = $database;
        $this->supplier_id = $supplier_id;
    }
    
    /**
     * Count orders by payment status
     */
    public function getOrderCountByPaymentStatus($payment_status, $order_status = 'pending') {
        $sql = "SELECT COUNT(*) as count 
                FROM orders 
                WHERE supplier_id = ? 
                AND status = ? 
                AND payment_method = 'paypal'
                AND (SELECT status FROM payments WHERE order_id = orders.id ORDER BY created_at DESC LIMIT 1) = ?";
        
        $result = $this->db->fetch($sql, [$this->supplier_id, $order_status, $payment_status]);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get supplier profile
     */
    public function getProfile($supplier_id = null) {
        $id = $supplier_id ?: $this->supplier_id;
        
        $sql = "SELECT *, (SELECT email FROM users WHERE users.id = suppliers.user_id) as email FROM suppliers 
                WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Update supplier profile
     */
    public function updateProfile($data) {
        $fields = ['business_name', 'owner_name', 'contact_number', 'business_address',
                   'barangay', 'city', 'province', 'latitude', 'longitude',
                   'valid_id', 'certifications', 'description', 'permit_expiry', 'permit_file',
                   'supports_truck', 'supports_boat'];
        
        $updates = [];
        $values = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                // Contact number stored as plain text (not hashed)
                // Valid_id is only hashed when coming from register.php step 3, not here
                if ($field === 'valid_id' && !empty($data[$field]) && !is_hashed($data[$field])) {
                    $values[] = hash_sensitive_data($data[$field]);
                } else {
                    $values[] = $data[$field];
                }
            }
        }
        
        if (!empty($updates)) {
            $values[] = $this->supplier_id;
            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            return $this->db->query($sql, $values);
        }
        
        return false;
    }
    
    /**
     * Get supplier inventory
     */
    public function getInventory($filters = []) {
        $where_conditions = ["supplier_id = ?"];
        $params = [$this->supplier_id];
        
        if (!empty($filters['species_id'])) {
            $where_conditions[] = "species_id = ?";
            $params[] = $filters['species_id'];
        }
        
        if (!empty($filters['availability_status'])) {
            $where_conditions[] = "availability_status = ?";
            $params[] = $filters['availability_status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.name LIKE ? OR s.scientific_name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT i.*, s.name as species_name, s.scientific_name, s.description as species_description
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                WHERE {$where_clause}
                ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get inventory statistics for charts
     */
    public function getInventoryStats() {
        $sql = "SELECT 
                    s.name as species_name,
                    SUM(i.stock_quantity) as total_stock,
                    SUM(i.stock_quantity * i.price_per_piece) as total_value
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                WHERE i.supplier_id = ?
                GROUP BY s.name
                ORDER BY total_stock DESC";
                
        return $this->db->fetchAll($sql, [$this->supplier_id]);
    }
    
    /**
     * Get inventory item by ID
     */
    public function getInventoryItem($item_id) {
        $sql = "SELECT i.*, s.name as species_name, s.scientific_name, s.description as species_description
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                WHERE i.id = ? AND i.supplier_id = ?";
        return $this->db->fetch($sql, [$item_id, $this->supplier_id]);
    }
    
    /**
     * Add item to supplier inventory
     */
    public function addInventoryItem($species_id, $size_category, $stock_quantity, $price_per_piece, $minimum_order = 1, $image_path = '', $description = '') {
        $sql = "INSERT INTO inventory (supplier_id, species_id, size_category, stock_quantity, price_per_piece, minimum_order, availability_status, description";
        
        // Add image_path column if image_path is provided
        if (!empty($image_path)) {
            $sql .= ", image_path";
        }
        
        $sql .= ") VALUES (?, ?, ?, ?, ?, ?, 'available', ?";
        
        // Add image_path value if image_path is provided
        if (!empty($image_path)) {
            $sql .= ", ?";
        }
        
        $sql .= ")";
        
        $params = [$this->supplier_id, $species_id, $size_category, $stock_quantity, $price_per_piece, $minimum_order, $description];
        
        // Add image_path parameter if image_path is provided
        if (!empty($image_path)) {
            $params[] = $image_path;
        }
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update inventory item
     */
    public function updateInventoryItem($item_id, $data) {
        $fields = ['species_id', 'size_category', 'stock_quantity', 'price_per_piece',
                   'minimum_order', 'availability_status', 'image_path'];
        
        $updates = [];
        $values = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $values[] = $item_id;
            $sql = "UPDATE inventory SET " . implode(', ', $updates) . " WHERE id = ? AND supplier_id = ?";
            return $this->db->query($sql, array_merge($values, [$this->supplier_id]));
        }
        
        return false;
    }
    
    /**
     * Delete inventory item
     */
    public function deleteInventoryItem($item_id) {
        $sql = "DELETE FROM inventory WHERE id = ? AND supplier_id = ?";
        return $this->db->query($sql, [$item_id, $this->supplier_id]);
    }
    
    /**
     * Get orders for supplier
     */
    public function getOrders($filters = [], $limit = null, $offset = 0) {
        $where_conditions = ["o.supplier_id = ?"];
        $params = [$this->supplier_id];
        
        if (!empty($filters['status'])) {
            // Handle scheduled_for_delivery: include orders with delivery_date set but exclude preparing/out_for_delivery/delivered/pending_reviews
            if ($filters['status'] === 'scheduled_for_delivery') {
                $where_conditions[] = "(o.status = 'scheduled_for_delivery' OR (o.delivery_date IS NOT NULL AND o.delivery_date != '' AND o.status NOT IN ('preparing', 'out_for_delivery', 'delivered', 'cancelled', 'pending_supplier_review', 'pending_admin_verification')))";
            } else {
                $where_conditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        // If filtering by 'confirmed' and exclude_paid is set, exclude orders that have been paid
        if (!empty($filters['exclude_paid']) && $filters['status'] === 'confirmed') {
            $where_conditions[] = "NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.order_id = o.id 
                AND p.status IN ('paid', 'completed')
            )";
        }
        
        if (!empty($filters['customer_id'])) {
            $where_conditions[] = "o.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT o.id, o.order_number, o.customer_id, o.total_amount, 
                       COALESCE(order_revenue.net_amount, 0) as net_amount,
                       o.status, o.created_at, o.updated_at, o.delivery_date, 
                       o.delivery_worker, o.eta_time,
                       o.supplier_accept_deadline, o.supplier_accepted_at, o.payment_deadline,
                       c.first_name, c.last_name, c.contact_number as customer_contact
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                LEFT JOIN (
                    SELECT order_id, SUM(subtotal) AS net_amount
                    FROM order_items
                    GROUP BY order_id
                ) order_revenue ON order_revenue.order_id = o.id
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
    public function updateOrderStatus($order_id, $status, $delivery_date = null, $delivery_worker = null) {
        // Validate that the order belongs to this supplier
        $sql = "SELECT id, status FROM orders WHERE id = ? AND supplier_id = ?";
        $order = $this->db->fetch($sql, [$order_id, $this->supplier_id]);
        
        if (!$order) {
            throw new Exception("Order not found or does not belong to this supplier");
        }
        
        // Update order status
        $updates = ["status = ?"];
        $params = [$status, $order_id];
        
        // If delivery date is provided, update it
        if ($delivery_date) {
            $updates[] = "delivery_date = ?";
            $params[] = $delivery_date;
        }
        
        // If delivery worker is provided, update it
        if ($delivery_worker) {
            $updates[] = "delivery_worker = ?";
            $params[] = $delivery_worker;
        }
        
        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get order count for supplier with optional status filter
     */
    public function getOrderCount($status = null) {
        $where_conditions = ["supplier_id = ?"];
        $params = [$this->supplier_id];
        
        if (!empty($status)) {
            // Handle scheduled_for_delivery: include orders with delivery_date set but exclude preparing/out_for_delivery/delivered/pending_reviews
            if ($status === 'scheduled_for_delivery') {
                $where_conditions[] = "(status = 'scheduled_for_delivery' OR (delivery_date IS NOT NULL AND delivery_date != '' AND status NOT IN ('preparing', 'out_for_delivery', 'delivered', 'cancelled', 'pending_supplier_review', 'pending_admin_verification')))";
            } else {
                $where_conditions[] = "status = ?";
                $params[] = $status;
            }
            
            // If counting 'confirmed' orders, exclude those that have been paid
            if ($status === 'confirmed') {
                $where_conditions[] = "NOT EXISTS (
                    SELECT 1 FROM payments p 
                    WHERE p.order_id = orders.id 
                    AND p.status IN ('paid', 'completed')
                )";
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT COUNT(*) as count FROM orders WHERE {$where_clause}";
        $result = $this->db->fetch($sql, $params);
        
        return $result['count'] ?? 0;
    }

    /**
     * Get order details
     */
    public function getOrderDetails($order_id) {
        $sql = "SELECT *, 
                (SELECT first_name FROM customers WHERE customers.id = orders.customer_id) as first_name,
                (SELECT last_name FROM customers WHERE customers.id = orders.customer_id) as last_name,
                (SELECT contact_number FROM customers WHERE customers.id = orders.customer_id) as customer_contact,
                (SELECT address FROM customers WHERE customers.id = orders.customer_id) as customer_address,
                (SELECT barangay FROM customers WHERE customers.id = orders.customer_id) as customer_barangay,
                (SELECT city FROM customers WHERE customers.id = orders.customer_id) as customer_city,
                (SELECT province FROM customers WHERE customers.id = orders.customer_id) as customer_province
                FROM orders
                WHERE id = ? AND supplier_id = ?";
        
        $order = $this->db->fetch($sql, [$order_id, $this->supplier_id]);
        
        if ($order) {
            $sql = "SELECT *, 
                    (SELECT size_category FROM inventory WHERE inventory.id = order_items.inventory_id) as size_category,
                    (SELECT name FROM species WHERE species.id = (SELECT species_id FROM inventory WHERE inventory.id = order_items.inventory_id)) as species_name
                    FROM order_items
                    WHERE order_id = ?";
            
            $order['items'] = $this->db->fetchAll($sql, [$order_id]);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     */
    public function getOrderItems($order_id) {
        $sql = "SELECT * FROM order_items WHERE order_id = ?";
        return $this->db->fetchAll($sql, [$order_id]);
    }
    
    /**
     * Get dashboard statistics for supplier
     */
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($start_date = null, $end_date = null) {
        $date_condition = "";
        $params = [$this->supplier_id];
        
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        } else {
            // Default to current month if no dates provided
            $date_condition = "AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
        }

        // Get monthly revenue (sum of item subtotals from delivered orders)
        $revenue_sql = "SELECT COALESCE(SUM(total_amount), 0) as revenue 
                       FROM orders
                       WHERE supplier_id = ? 
                       AND status = 'delivered' 
                       {$date_condition}";
        $revenue_result = $this->db->fetch($revenue_sql, $params);
        $revenue = $revenue_result['revenue'] ?? 0;
        
        // Get pending orders count (pending_supplier_confirmation)
        $orders_sql = "SELECT COUNT(*) as orders 
                      FROM orders 
                      WHERE supplier_id = ? 
                      AND status = 'pending_supplier_confirmation'
                      {$date_condition}";
        $orders_result = $this->db->fetch($orders_sql, $params);
        $orders = $orders_result['orders'] ?? 0;
        
        // Get active products count (Inventory Snapshot - ignore date)
        $products_sql = "SELECT COUNT(*) as products 
                        FROM inventory 
                        WHERE supplier_id = ? 
                        AND availability_status = 'available'";
        $products_result = $this->db->fetch($products_sql, [$this->supplier_id]);
        $products = $products_result['products'] ?? 0;
        
        // Get customers count (distinct customers who have ordered from this supplier)
        $customers_sql = "SELECT COUNT(DISTINCT customer_id) as customers 
                         FROM orders 
                         WHERE supplier_id = ?
                         {$date_condition}";
        $customers_result = $this->db->fetch($customers_sql, $params);
        $customers = $customers_result['customers'] ?? 0;
        
        // Get monthly total earnings (Total Amount - Service Fee)
        // Service Fee is 5% flat
        $earnings_sql = "SELECT SUM(total_amount * 0.95) as total_earnings 
                       FROM orders 
                       WHERE supplier_id = ? 
                       AND status = 'delivered' 
                       {$date_condition}";
        $earnings_result = $this->db->fetch($earnings_sql, $params);
        $total_earnings = $earnings_result['total_earnings'] ?? 0;
        
        return [
            'revenue' => $revenue,
            'total_earnings' => $total_earnings,
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers
        ];
    }
    
    /**
     * Get recent orders for supplier dashboard
     */
    public function getRecentOrders($limit = 5) {
        $sql = "SELECT o.*, 
                c.first_name,
                c.last_name
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE o.supplier_id = ?
                ORDER BY o.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$this->supplier_id, $limit]);
    }
    
    /**
     * Get monthly revenue trends
     */
    public function getMonthlyRevenueTrends($period = 'month') {
        // Check if created_at column exists
        $date_column = 'created_at';
        try {
            $check_sql = "SHOW COLUMNS FROM orders LIKE 'created_at'";
            $column_exists = $this->db->fetch($check_sql);
            if (!$column_exists) {
                $date_column = 'order_date'; // Fallback if created_at doesn't exist
                $check_sql = "SHOW COLUMNS FROM orders LIKE 'order_date'";
                $column_exists = $this->db->fetch($check_sql);
                if (!$column_exists) {
                    $date_column = null; // No date column found
                }
            }
        } catch (Exception $e) {
            $date_column = null; // Error checking column
        }
        
        if (!$date_column) {
            // Return empty data if no date column found
            return [];
        }
        
        // Determine the date range based on period
        switch ($period) {
            case 'week':
                $interval = 'INTERVAL 1 MONTH';
                $date_format = "'%Y-%m-%d'";
                $group_by = "DATE({$date_column})";
                $order_by = "DATE({$date_column})";
                break;
            case 'month':
                $interval = 'INTERVAL 6 MONTH';
                $date_format = "'%Y-%m'";
                $group_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                $order_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                break;
            case 'quarter':
                $interval = 'INTERVAL 6 MONTH';
                $date_format = "'%Y-%m'";
                $group_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                $order_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                break;
            case 'year':
                $interval = 'INTERVAL 12 MONTH';
                $date_format = "'%Y-%m'";
                $group_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                $order_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                break;
            default:
                $interval = 'INTERVAL 6 MONTH';
                $date_format = "'%Y-%m'";
                $group_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
                $order_by = "DATE_FORMAT({$date_column}, '%Y-%m')";
        }
        
        $sql = "SELECT 
                    DATE_FORMAT({$date_column}, {$date_format}) as month,
                    COALESCE(SUM(oi.subtotal), 0) as revenue,
                    COUNT(*) as order_count
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE supplier_id = ? 
                AND status = 'delivered'
                AND {$date_column} >= DATE_SUB(NOW(), {$interval})
                GROUP BY {$group_by}
                ORDER BY {$order_by}";
        
        return $this->db->fetchAll($sql, [$this->supplier_id]);
    }
    
    /**
     * Get inventory alerts for low stock items
     */
    public function getInventoryAlerts($threshold = 10) {
        $sql = "SELECT 
                    i.id,
                    s.name as species_name,
                    i.size_category,
                    i.stock_quantity,
                    i.minimum_order,
                    i.availability_status
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                WHERE i.supplier_id = ?
                AND i.stock_quantity <= ?
                AND i.availability_status = 'available'
                ORDER BY i.stock_quantity ASC";
        
        return $this->db->fetchAll($sql, [$this->supplier_id, $threshold]);
    }
    
    /**
     * Get sales analytics data
     */
    public function getSalesAnalytics($period = 'month') {
        // Determine the date range based on period
        switch ($period) {
            case 'week':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
            default:
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        // Get total sales and order count - Revenue = price × quantity ONLY (no delivery fees)
        $sql = "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                COALESCE(AVG(order_totals.order_revenue), 0) as average_order_value
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN (
                SELECT order_id, SUM(subtotal) as order_revenue
                FROM order_items
                GROUP BY order_id
            ) order_totals ON o.id = order_totals.order_id
            WHERE o.supplier_id = ? 
            AND o.status = 'delivered'
            {$date_condition}";
        
        $sales_summary = $this->db->fetch($sql, [$this->supplier_id]);
        
        // Get sales trend data - Revenue = price × quantity ONLY
        $trend_sql = "SELECT 
                    DATE(o.created_at) as date,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as revenue
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.supplier_id = ? 
                AND o.status = 'delivered'
                {$date_condition}
                GROUP BY DATE(o.created_at)
                ORDER BY date";
        
        $sales_trend = $this->db->fetchAll($trend_sql, [$this->supplier_id]);
        
        // Get top selling products
        $products_sql = "SELECT 
                        s.name as species_name,
                        SUM(oi.quantity) as total_quantity_sold,
                        COUNT(*) as order_count
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    JOIN inventory i ON oi.inventory_id = i.id
                    JOIN species s ON i.species_id = s.id
                    WHERE o.supplier_id = ? 
                    AND o.status = 'delivered'
                    {$date_condition}
                    GROUP BY i.species_id, s.name
                    ORDER BY total_quantity_sold DESC
                    LIMIT 10";
        
        $top_products = $this->db->fetchAll($products_sql, [$this->supplier_id]);
        
        return [
            'summary' => $sales_summary,
            'trend' => $sales_trend,
            'top_products' => $top_products
        ];
    }
    
    /**
     * Get inventory performance data
     */
    public function getInventoryPerformance() {
        // Get inventory items with sales data
        $sql = "SELECT 
                    s.name as species_name,
                    i.size_category,
                    i.stock_quantity,
                    COALESCE(SUM(oi.quantity), 0) as total_sold,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                LEFT JOIN order_items oi ON i.id = oi.inventory_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
                WHERE i.supplier_id = ?
                GROUP BY i.id, s.name, i.size_category, i.stock_quantity
                ORDER BY total_revenue DESC, total_sold DESC";
        
        $inventory_performance = $this->db->fetchAll($sql, [$this->supplier_id]);
        
        return $inventory_performance;
    }
    
    /**
     * Get inventory analytics
     */
    public function getInventoryAnalytics() {
        // Get inventory summary
        $sql = "SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN availability_status = 'available' THEN 1 END) as active_products,
                    COUNT(CASE WHEN availability_status = 'unavailable' THEN 1 END) as inactive_products,
                    COALESCE(SUM(stock_quantity), 0) as total_stock
                FROM inventory
                WHERE supplier_id = ?";
        
        $inventory_summary = $this->db->fetch($sql, [$this->supplier_id]);
        
        // Get low stock items (less than 10)
        $low_stock_sql = "SELECT 
                            s.name as species_name,
                            i.size_category,
                            i.stock_quantity,
                            i.minimum_order
                        FROM inventory i
                        JOIN species s ON i.species_id = s.id
                        WHERE i.supplier_id = ?
                        AND i.stock_quantity < 10
                        AND i.availability_status = 'available'
                        ORDER BY i.stock_quantity ASC";
        
        $low_stock_items = $this->db->fetchAll($low_stock_sql, [$this->supplier_id]);
        
        // Get out of stock items
        $out_of_stock_sql = "SELECT 
                                s.name as species_name,
                                i.size_category
                            FROM inventory i
                            JOIN species s ON i.species_id = s.id
                            WHERE i.supplier_id = ?
                            AND i.stock_quantity = 0
                            AND i.availability_status = 'available'";
        
        $out_of_stock_items = $this->db->fetchAll($out_of_stock_sql, [$this->supplier_id]);
        
        return [
            'summary' => $inventory_summary,
            'low_stock_items' => $low_stock_items,
            'out_of_stock_items' => $out_of_stock_items
        ];
    }
    
    /**
     * Get customer insights for supplier dashboard
     */
    public function getCustomerInsights() {
        // Get customer demographics and ordering patterns - Revenue = price × quantity ONLY
        $sql = "SELECT 
                    c.city,
                    c.province,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as total_spent,
                    MAX(o.created_at) as last_order_date
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN customers c ON o.customer_id = c.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                GROUP BY c.city, c.province, o.customer_id
                ORDER BY total_spent DESC
                LIMIT 10";
        
        $customer_locations = $this->db->fetchAll($sql, [$this->supplier_id]);
        
        // Get ordering patterns by time - Revenue = price × quantity ONLY
        $pattern_sql = "SELECT 
                    DAYNAME(o.created_at) as day_of_week,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN (
                    SELECT order_id, SUM(subtotal) as order_revenue
                    FROM order_items
                    GROUP BY order_id
                ) order_totals ON o.id = order_totals.order_id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                GROUP BY DAYNAME(o.created_at)
                ORDER BY DAYOFWEEK(o.created_at)";
        
        $ordering_patterns = $this->db->fetchAll($pattern_sql, [$this->supplier_id]);
        
        // Get customer lifetime value - Revenue = price × quantity ONLY
        $clv_sql = "SELECT 
                        c.id,
                        c.first_name,
                        c.last_name,
                        COUNT(DISTINCT o.id) as total_orders,
                        COALESCE(SUM(oi.subtotal), 0) as total_spent,
                        COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value,
                        MIN(o.created_at) as first_order_date,
                        MAX(o.created_at) as last_order_date
                    FROM customers c
                    JOIN orders o ON c.id = o.customer_id
                    JOIN order_items oi ON o.id = oi.order_id
                    LEFT JOIN (
                        SELECT order_id, SUM(subtotal) as order_revenue
                        FROM order_items
                        GROUP BY order_id
                    ) order_totals ON o.id = order_totals.order_id
                    WHERE o.supplier_id = ?
                    AND o.status = 'delivered'
                    GROUP BY c.id, c.first_name, c.last_name
                    ORDER BY total_spent DESC
                    LIMIT 10";
        
        $customer_lifetime_value = $this->db->fetchAll($clv_sql, [$this->supplier_id]);
        
        return [
            'locations' => $customer_locations,
            'patterns' => $ordering_patterns,
            'lifetime_value' => $customer_lifetime_value
        ];
    }

    /**
     * Get geographic insights for supplier dashboard
     */
    public function getGeographicInsights() {
        // Get geographic distribution of customers - Revenue = price × quantity ONLY
        $sql = "SELECT 
                    c.barangay,
                    c.city,
                    c.province,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                    COUNT(DISTINCT o.customer_id) as unique_customers
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN customers c ON o.customer_id = c.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                GROUP BY c.barangay, c.city, c.province
                ORDER BY order_count DESC, total_revenue DESC
                LIMIT 15";
        
        $geographic_data = $this->db->fetchAll($sql, [$this->supplier_id]);
        
        // Get delivery area statistics (without latitude/longitude since they don't exist in the table)
        $delivery_sql = "SELECT 
                    COUNT(*) as total_deliveries
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'";
        
        $delivery_stats = $this->db->fetch($delivery_sql, [$this->supplier_id]);
        
        return [
            'distribution' => $geographic_data,
            'delivery_stats' => $delivery_stats
        ];
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics() {
        // Get customer statistics - Revenue = price × quantity ONLY
        $sql = "SELECT 
                COUNT(DISTINCT o.customer_id) as total_customers,
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.supplier_id = ?
            AND o.status = 'delivered'";
        
        $customer_stats = $this->db->fetch($sql, [$this->supplier_id]);
        
        // Get new customers this month
        $new_customers_sql = "SELECT COUNT(DISTINCT customer_id) as new_customers
            FROM orders
            WHERE supplier_id = ?
            AND status = 'delivered'
            AND customer_id IN (
                SELECT customer_id 
                FROM orders o2 
                WHERE o2.customer_id = orders.customer_id 
                AND o2.supplier_id = ?
                AND DATE(o2.created_at) = (
                    SELECT MIN(DATE(o3.created_at))
                    FROM orders o3
                    WHERE o3.customer_id = o2.customer_id
                    AND o3.supplier_id = ?
                )
                AND MONTH(o2.created_at) = MONTH(NOW())
                AND YEAR(o2.created_at) = YEAR(NOW())
            )";
        
        $new_customers_result = $this->db->fetch($new_customers_sql, [$this->supplier_id, $this->supplier_id, $this->supplier_id]);
        $new_customers = $new_customers_result['new_customers'] ?? 0;
        
        // Calculate retention rate (customers with more than one order)
        $retention_sql = "SELECT 
            COUNT(*) as total_customers,
            COUNT(CASE WHEN order_count > 1 THEN 1 END) as returning_customers
        FROM (
            SELECT 
                customer_id,
                COUNT(*) as order_count
            FROM orders
            WHERE supplier_id = ?
            AND status = 'delivered'
            GROUP BY customer_id
        ) as customer_orders";
        
        $retention_result = $this->db->fetch($retention_sql, [$this->supplier_id]);
        $retention_rate = 0;
        if (($retention_result['total_customers'] ?? 0) > 0) {
            $retention_rate = (($retention_result['returning_customers'] ?? 0) / $retention_result['total_customers']) * 100;
        }
        
        // Get top customers
        $customers_sql = "SELECT 
                        c.first_name,
                        c.last_name,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_spent
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    WHERE o.supplier_id = ?
                    AND o.status = 'delivered'
                    GROUP BY o.customer_id, c.first_name, c.last_name
                    ORDER BY total_spent DESC
                    LIMIT 10";
        
        $top_customers = $this->db->fetchAll($customers_sql, [$this->supplier_id]);
        
        // Get customer order frequency
        $frequency_sql = "SELECT 
                        customer_type,
                        COUNT(*) as customer_count
                    FROM (
                        SELECT 
                            CASE 
                                WHEN order_count = 1 THEN 'one_time'
                                WHEN order_count BETWEEN 2 AND 5 THEN 'regular'
                                WHEN order_count > 5 THEN 'frequent'
                            END as customer_type
                        FROM (
                            SELECT 
                                customer_id,
                                COUNT(*) as order_count
                            FROM orders
                            WHERE supplier_id = ?
                            AND status = 'delivered'
                            GROUP BY customer_id
                        ) AS customer_orders
                    ) AS customer_types
                    WHERE customer_type IS NOT NULL
                    GROUP BY customer_type";
        
        $customer_frequency = $this->db->fetchAll($frequency_sql, [$this->supplier_id]);
        
        return [
            'stats' => $customer_stats,
            'new_customers' => $new_customers,
            'retention_rate' => $retention_rate,
            'top_customers' => $top_customers,
            'frequency' => $customer_frequency
        ];
    }
    
    /**
     * Get revenue tracking data
     */
    public function getRevenueTracking($period = 'month', $page = 1, $per_page = 10, $start_date = null, $end_date = null) {
        // Determine the date range based on period
        $date_condition = "";
        
        if ($period === 'custom' && $start_date && $end_date) {
            // Validate dates to prevent SQL injection
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                $date_condition = "AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
            } else {
                // Fallback if invalid
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            }
        } else {
            switch ($period) {
                case 'week':
                    $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case 'quarter':
                    $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                    break;
                case 'year':
                    $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                    break;
                default:
                    $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            }
        }
        
        // Calculate offset for pagination
        $offset = ($page - 1) * $per_page;
        
        // Get transactions with accurate revenue calculation
        // Note: admin_commission column is used for service fee calculation
        $sql = "SELECT 
                    o.id,
                    o.order_number,
                    COALESCE(SUM(oi.subtotal), 0) as total_amount,

                    o.admin_commission,
                    o.status,
                    o.created_at,
                    c.first_name,
                    c.last_name,
                    p.payment_method
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                LEFT JOIN payments p ON o.id = p.order_id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                {$date_condition}
                GROUP BY o.id, o.order_number, o.admin_commission, o.status, o.created_at, c.first_name, c.last_name, p.payment_method
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
        
        $transactions = $this->db->fetchAll($sql, [$this->supplier_id, $per_page, $offset]);
        
        // Process transactions to calculate service fees and earnings per row
        foreach ($transactions as &$t) {
            $t['service_fee'] = $t['total_amount'] * 0.05;
            $t['earnings'] = $t['total_amount'] - $t['service_fee'];
        }
        unset($t); // break ref
        
        // Get total transaction count
        $count_sql = "SELECT COUNT(*) as total FROM orders o
                     WHERE o.supplier_id = ?
                     AND o.status = 'delivered'
                     {$date_condition}";
        $count_result = $this->db->fetch($count_sql, [$this->supplier_id]);
        $total_transactions = $count_result['total'] ?? 0;
        
        // Get revenue summary data - Calculate Service Fee (5% flat)
        $summary_sql = "SELECT 
                            COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END), 0) as total_revenue,
                            COALESCE(SUM(
                                CASE 
                                    WHEN o.status = 'delivered' THEN oi.subtotal * 0.95
                                    ELSE 0 
                                END
                            ), 0) as total_earnings,
                            COALESCE(SUM(
                                CASE 
                                    WHEN o.status = 'delivered' THEN oi.subtotal * 0.05
                                    ELSE 0 
                                END
                            ), 0) as total_service_fees,
                            COALESCE(SUM(CASE WHEN o.status IN ('pending_supplier_confirmation', 'awaiting_customer_payment', 'paid_and_processing', 'confirmed', 'preparing', 'out_for_delivery') THEN oi.subtotal ELSE 0 END), 0) as pending_revenue
                        FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.supplier_id = ?
                        {$date_condition}";
        $summary_data = $this->db->fetch($summary_sql, [$this->supplier_id]);
        
        // Calculate growth rate (compare with previous period)
        $prev_period_condition = '';
        switch ($period) {
            case 'week':
                $prev_period_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 2 WEEK) AND o.created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $prev_period_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND o.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'quarter':
                $prev_period_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND o.created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
            case 'year':
                $prev_period_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR) AND o.created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        $prev_summary_sql = "SELECT 
                                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END), 0) as prev_revenue
                            FROM orders o
                            JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.supplier_id = ? 
                            {$prev_period_condition}";
        $prev_summary_data = $this->db->fetch($prev_summary_sql, [$this->supplier_id]);
        
        $current_revenue = $summary_data['total_revenue'] ?? 0;
        $prev_revenue = $prev_summary_data['prev_revenue'] ?? 0;
        
        // Calculate growth rate
        if ($prev_revenue > 0) {
            $growth_rate = (($current_revenue - $prev_revenue) / $prev_revenue) * 100;
        } else {
            $growth_rate = $current_revenue > 0 ? 100 : 0; // Handle case where previous revenue is 0
        }
        
        return [
            'transactions' => $transactions,
            'total_transactions' => $total_transactions,
            'summary' => $summary_data,
            'growth_rate' => $growth_rate,
            'current_revenue' => $current_revenue,
            'previous_revenue' => $prev_revenue
        ];
    }
    
    /**
     * Get sales statistics for reports
     */
    public function getSalesStats($period = 'month') {
        // Determine the date range based on period
        switch ($period) {
            case 'week':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                break;
            default:
                $date_condition = "AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        // Get sales statistics - Revenue = price × quantity ONLY (no delivery fees)
        $sql = "SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                    COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value,
                    COUNT(DISTINCT o.customer_id) as unique_customers
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN (
                    SELECT order_id, SUM(subtotal) as order_revenue
                    FROM order_items
                    GROUP BY order_id
                ) order_totals ON o.id = order_totals.order_id
                WHERE o.supplier_id = ? 
                AND o.status = 'delivered'
                {$date_condition}";
        
        return $this->db->fetch($sql, [$this->supplier_id]);
    }
    
    /**
     * Get sales trends for reports
     */
    public function getSalesTrends($days = 30) {
        // Revenue = price × quantity ONLY (no delivery fees)
        $sql = "SELECT 
                    DATE(o.created_at) as date,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as revenue
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.supplier_id = ? 
                AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND o.status != 'cancelled'
                GROUP BY DATE(o.created_at)
                ORDER BY date";
        
        return $this->db->fetchAll($sql, [$this->supplier_id, $days]);
    }
    
    /**
     * Get top selling products for reports
     */
    public function getTopProducts($limit = 10) {
        $sql = "SELECT 
                    s.name as species_name,
                    i.size_category,
                    SUM(oi.quantity) as total_quantity_sold,
                    COUNT(*) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species s ON i.species_id = s.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                GROUP BY s.name, i.size_category, i.species_id
                ORDER BY total_quantity_sold DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$this->supplier_id, $limit]);
    }
    
    /**
     * Search suppliers by business name
     */
    public function searchSuppliers($query) {
        $sql = "SELECT s.*, 
                       AVG(f.rating) as avg_rating,
                       COUNT(f.id) as total_reviews,
                       COUNT(DISTINCT i.id) as product_count
                FROM suppliers s
                LEFT JOIN feedback f ON s.id = f.supplier_id
                LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
                WHERE s.status = 'approved' 
                AND s.business_name LIKE ?
                GROUP BY s.id
                ORDER BY s.business_name";
        
        $searchTerm = '%' . $query . '%';
        return $this->db->fetchAll($sql, [$searchTerm]);
    }

    /**
     * Helper method to get the correct date column for orders table
     */
    private function getDateColumn() {
        // Check if created_at column exists
        $date_column = 'created_at';
        try {
            $check_sql = "SHOW COLUMNS FROM orders LIKE 'created_at'";
            $column_exists = $this->db->fetch($check_sql);
            if (!$column_exists) {
                // Check for order_date as fallback
                $check_sql = "SHOW COLUMNS FROM orders LIKE 'order_date'";
                $column_exists = $this->db->fetch($check_sql);
                if ($column_exists) {
                    $date_column = 'order_date';
                } else {
                    return null; // No suitable date column found
                }
            }
        } catch (Exception $e) {
            // If checking fails, default to created_at but it might fail later
            return 'created_at';
        }
        return $date_column;
    }

    /**
     * Get species sales breakdown for date range
     */
    public function getSpeciesSalesBreakdown($start_date, $end_date) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return [];

        $sql = "SELECT 
                    s.id as species_id,
                    s.name as species_name,
                    s.scientific_name,
                    i.size_category,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.quantity) as total_quantity_sold,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                    COALESCE(AVG(oi.unit_price), 0) as average_price,
                    MIN(o.{$date_column}) as first_sale_date,
                    MAX(o.{$date_column}) as last_sale_date
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species s ON i.species_id = s.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                AND DATE(o.{$date_column}) BETWEEN ? AND ?
                GROUP BY s.id, s.name, s.scientific_name, i.size_category
                ORDER BY total_revenue DESC, total_quantity_sold DESC";
        
        return $this->db->fetchAll($sql, [$this->supplier_id, $start_date, $end_date]);
    }
    
    /**
     * Get historical inventory - all species ever listed
     */
    public function getHistoricalInventory($start_date, $end_date) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return [];

        $sql = "SELECT 
                    s.name as species_name,
                    s.scientific_name,
                    i.size_category,
                    i.stock_quantity as current_stock,
                    i.price_per_piece as current_price,
                    i.availability_status,
                    i.created_at as date_added,
                    i.updated_at as last_updated,
                    COALESCE(SUM(oi.quantity), 0) as total_sold_in_period,
                    COALESCE(SUM(oi.subtotal), 0) as revenue_in_period
                FROM inventory i
                JOIN species s ON i.species_id = s.id
                LEFT JOIN order_items oi ON i.id = oi.inventory_id
                LEFT JOIN orders o ON oi.order_id = o.id 
                    AND o.status = 'delivered'
                    AND DATE(o.{$date_column}) BETWEEN ? AND ?
                WHERE i.supplier_id = ?
                GROUP BY i.id, s.name, s.scientific_name, i.size_category, i.stock_quantity, 
                         i.price_per_piece, i.availability_status, i.created_at, i.updated_at
                ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($sql, [$start_date, $end_date, $this->supplier_id]);
    }
    
    /**
     * Get comprehensive earnings breakdown for date range
     */
    public function getEarningsBreakdown($start_date, $end_date) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return [
            'summary' => [], 
            'monthly_revenue' => [], 
            'species_revenue' => []
        ];

        // Get total earnings summary
        $summary_sql = "SELECT 
                            COUNT(DISTINCT o.id) as total_orders,
                            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                            COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value,
                            COUNT(DISTINCT o.customer_id) as unique_customers,
                            COUNT(DISTINCT i.species_id) as species_sold
                        FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        JOIN inventory i ON oi.inventory_id = i.id
                        LEFT JOIN (
                            SELECT order_id, SUM(subtotal) as order_revenue
                            FROM order_items
                            GROUP BY order_id
                        ) order_totals ON o.id = order_totals.order_id
                        WHERE o.supplier_id = ?
                        AND o.status = 'delivered'
                        AND DATE(o.{$date_column}) BETWEEN ? AND ?";
        
        $summary = $this->db->fetch($summary_sql, [$this->supplier_id, $start_date, $end_date]);
        
        // Get revenue by month
        $monthly_sql = "SELECT 
                            DATE_FORMAT(o.{$date_column}, '%Y-%m') as month,
                            DATE_FORMAT(o.{$date_column}, '%M %Y') as month_name,
                            COUNT(DISTINCT o.id) as orders,
                            COALESCE(SUM(oi.subtotal), 0) as revenue
                        FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.supplier_id = ?
                        AND o.status = 'delivered'
                        AND DATE(o.{$date_column}) BETWEEN ? AND ?
                        GROUP BY DATE_FORMAT(o.{$date_column}, '%Y-%m'), DATE_FORMAT(o.{$date_column}, '%M %Y')
                        ORDER BY month";
        
        $monthly_revenue = $this->db->fetchAll($monthly_sql, [$this->supplier_id, $start_date, $end_date]);
        
        // Get revenue by species
        $species_sql = "SELECT 
                            s.name as species_name,
                            COALESCE(SUM(oi.subtotal), 0) as revenue,
                            ROUND((SUM(oi.subtotal) / (SELECT SUM(oi2.subtotal)
                                FROM order_items oi2
                                JOIN orders o2 ON oi2.order_id = o2.id
                                WHERE o2.supplier_id = ? 
                                AND o2.status = 'delivered'
                                AND DATE(o2.{$date_column}) BETWEEN ? AND ?
                            )) * 100, 2) as percentage
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        JOIN inventory i ON oi.inventory_id = i.id
                        JOIN species s ON i.species_id = s.id
                        WHERE o.supplier_id = ?
                        AND o.status = 'delivered'
                        AND DATE(o.{$date_column}) BETWEEN ? AND ?
                        GROUP BY s.id, s.name
                        ORDER BY revenue DESC";
        
        $species_revenue = $this->db->fetchAll($species_sql, [
            $this->supplier_id, $start_date, $end_date,
            $this->supplier_id, $start_date, $end_date
        ]);
        
        return [
            'summary' => $summary,
            'monthly_revenue' => $monthly_revenue,
            'species_revenue' => $species_revenue
        ];
    }
    
    /**
     * Get sales comparison between two periods
     */
    public function getSalesComparison($current_start, $current_end, $previous_start, $previous_end) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return [
            'current' => [], 
            'previous' => [], 
            'changes' => ['revenue' => 0, 'orders' => 0, 'avg_order_value' => 0]
        ];

        // Current period stats
        $current_sql = "SELECT 
                            COUNT(DISTINCT o.id) as orders,
                            COALESCE(SUM(oi.subtotal), 0) as revenue,
                            COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value
                        FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        LEFT JOIN (
                            SELECT order_id, SUM(subtotal) as order_revenue
                            FROM order_items
                            GROUP BY order_id
                        ) order_totals ON o.id = order_totals.order_id
                        WHERE o.supplier_id = ?
                        AND o.status = 'delivered'
                        AND DATE(o.{$date_column}) BETWEEN ? AND ?";
        
        $current = $this->db->fetch($current_sql, [$this->supplier_id, $current_start, $current_end]);
        
        // Previous period stats
        $previous = $this->db->fetch($current_sql, [$this->supplier_id, $previous_start, $previous_end]);
        
        // Calculate percentage changes
        $revenue_change = 0;
        $orders_change = 0;
        $avg_value_change = 0;
        
        if (($previous['revenue'] ?? 0) > 0) {
            $revenue_change = ((($current['revenue'] ?? 0) - ($previous['revenue'] ?? 0)) / ($previous['revenue'] ?? 1)) * 100;
        } else if (($current['revenue'] ?? 0) > 0) {
            $revenue_change = 100;
        }
        
        if (($previous['orders'] ?? 0) > 0) {
            $orders_change = ((($current['orders'] ?? 0) - ($previous['orders'] ?? 0)) / ($previous['orders'] ?? 1)) * 100;
        } else if (($current['orders'] ?? 0) > 0) {
            $orders_change = 100;
        }
        
        if (($previous['avg_order_value'] ?? 0) > 0) {
            $avg_value_change = ((($current['avg_order_value'] ?? 0) - ($previous['avg_order_value'] ?? 0)) / ($previous['avg_order_value'] ?? 1)) * 100;
        } else if (($current['avg_order_value'] ?? 0) > 0) {
            $avg_value_change = 100;
        }
        
        return [
            'current' => $current,
            'previous' => $previous,
            'changes' => [
                'revenue' => $revenue_change,
                'orders' => $orders_change,
                'avg_order_value' => $avg_value_change
            ]
        ];
    }
    /**
     * Get overall stock health statistics
     */
    public function getStockHealthStats() {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    COUNT(CASE WHEN stock_quantity <= 10 AND stock_quantity > 0 THEN 1 END) as low_stock,
                    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN stock_quantity > 10 THEN 1 END) as adequate_stock,
                    COALESCE(SUM(stock_quantity * price_per_piece), 0) as total_value
                FROM inventory
                WHERE supplier_id = ? AND availability_status != 'discontinued'";
        return $this->db->fetch($sql, [$this->supplier_id]);
    }

    /**
     * Get stock movement log (reductions from orders, restorations from cancellations)
     */
    public function getStockMovementLog($start_date, $end_date, $species_id = null, $page = null, $per_page = null) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return [];
        
        $species_condition = "";
        $params_base = [$this->supplier_id, $start_date, $end_date];
        
        if ($species_id) {
            $species_condition = "AND i.species_id = ?";
            $params_base[] = $species_id;
        }
        
        // Reductions (Orders Placed)
        $sql = "SELECT 
                    o.{$date_column} as date,
                    'Order Placed' as type,
                    'reduction' as movement_type,
                    -oi.quantity as quantity_change,
                    o.order_number,
                    s.name as species_name,
                    i.size_category,
                    i.stock_quantity as current_stock,
                    o.id as order_id
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species s ON i.species_id = s.id
                WHERE o.supplier_id = ?
                AND DATE(o.{$date_column}) BETWEEN ? AND ?
                {$species_condition}";
        
        // Restorations (Cancelled Orders)
        $sql .= " UNION ALL
                SELECT 
                    o.updated_at as date,
                    'Order Cancelled' as type,
                    'restoration' as movement_type,
                    oi.quantity as quantity_change,
                    o.order_number,
                    s.name as species_name,
                    i.size_category,
                    i.stock_quantity as current_stock,
                    o.id as order_id
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species s ON i.species_id = s.id
                WHERE o.supplier_id = ?
                AND o.status IN ('cancelled', 'refunded')
                AND DATE(o.updated_at) BETWEEN ? AND ?
                {$species_condition}
                ORDER BY date DESC";
        
        // Merge parameters for both parts of Union
        $params = array_merge($params_base, $params_base);
        
        // Wrap in subquery to allow global sorting and pagination
        $final_sql = "SELECT * FROM ({$sql}) as combined_log ORDER BY date DESC";
        
        // Add pagination if requested
        if ($page !== null && $per_page !== null) {
            $offset = ($page - 1) * $per_page;
            $final_sql .= " LIMIT ? OFFSET ?";
            $params[] = $per_page;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($final_sql, $params);
    }

    /**
     * Get total count of stock movements for pagination
     */
    public function getStockMovementCount($start_date, $end_date, $species_id = null) {
        $date_column = $this->getDateColumn();
        if (!$date_column) return 0;
        
        $species_condition = "";
        $params_base = [$this->supplier_id, $start_date, $end_date];
        
        if ($species_id) {
            $species_condition = "AND i.species_id = ?";
            $params_base[] = $species_id;
        }
        
        // Reductions count
        $sql = "SELECT COUNT(*) as count
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                WHERE o.supplier_id = ?
                AND DATE(o.{$date_column}) BETWEEN ? AND ?
                {$species_condition}";
        
        // Restorations count
        $sql .= " UNION ALL
                SELECT COUNT(*) as count
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                WHERE o.supplier_id = ?
                AND o.status IN ('cancelled', 'refunded')
                AND DATE(o.updated_at) BETWEEN ? AND ?
                {$species_condition}";
        
        $params = array_merge($params_base, $params_base);
        $results = $this->db->fetchAll($sql, $params);
        
        $total = 0;
        foreach ($results as $row) {
            $total += $row['count'];
        }
        
        return $total;
    }

    /**
     * Get earnings broken down by species
     */
    public function getSpeciesEarnings($start_date, $end_date) {
        // Calculate earnings per species using proportional service fee allocation
        // If order has admin_commission, allocate it based on item subtotal / order total
        // Otherwise use default 5% fee
        
        $sql = "SELECT 
                    s.name as species_name,
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(oi.subtotal), 0) as total_sales,
                    COALESCE(SUM(oi.subtotal * 0.95), 0) as net_earnings
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species s ON i.species_id = s.id
                WHERE o.supplier_id = ?
                AND o.status = 'delivered'
                AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY s.id, s.name
                                ORDER BY net_earnings DESC";
                
        return $this->db->fetchAll($sql, [$this->supplier_id, $start_date, $end_date]);
    }

    /**
     * Get monthly earning trends (Net after Service Fee)
     */
    public function getMonthlyEarningTrends($period = 'year', $start_date = null, $end_date = null) {
        $date_condition = "";
        $params = [$this->supplier_id];
        
        if ($period === 'custom' && $start_date && $end_date) {
             $date_condition = "AND DATE(created_at) BETWEEN ? AND ?";
             $params[] = $start_date;
             $params[] = $end_date;
        } else {
            switch ($period) {
                case 'week':
                    $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case 'quarter':
                    $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                    break;
                case 'year':
                    $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
                    break;
                default:
                     $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
            }
        }

        // Format by Month for trends
        // If the period is short (week/month), maybe group by day?
        // Let's stick to Month for now unless specified, but for 'week', grouping by Month is useless (1 point).
        // Let's adjust grouping based on period.
        
        $group_format = '%Y-%m'; // Default Month
        if ($period === 'week' || $period === 'month') {
            $group_format = '%Y-%m-%d'; // Day for short periods
        }
        
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '{$group_format}') as period_label,
                    COALESCE(SUM(total_amount * 0.95), 0) as earnings,
                    COUNT(*) as order_count
                FROM orders
                WHERE supplier_id = ?
                AND status = 'delivered'
                {$date_condition}
                GROUP BY DATE_FORMAT(created_at, '{$group_format}')
                ORDER BY period_label ASC";
                
        return $this->db->fetchAll($sql, $params);
    }
}
?>