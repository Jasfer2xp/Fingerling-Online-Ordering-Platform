<?php

/**
 * Customer Class
 * Handles customer-related operations
 */

class Customer {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get customer ID by user ID
     */
    public function getCustomerIdByUserId($user_id) {
        $sql = "SELECT id FROM customers WHERE user_id = ?";
        $result = $this->db->fetch($sql, [$user_id]);
        return $result ? $result['id'] : null;
    }
    
    /**
     * Get customer profile by user ID
     */
    public function getCustomerProfile($user_id) {
        $sql = "SELECT c.*, u.email, u.created_at as user_created_at
                FROM customers c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.user_id = ?";
        return $this->db->fetch($sql, [$user_id]);
    }
    
    /**
     * Get customer by ID
     */
    public function getCustomerById($customer_id) {
        $sql = "SELECT c.*, u.email, u.created_at as user_created_at
                FROM customers c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.id = ?";
        return $this->db->fetch($sql, [$customer_id]);
    }
    
    /**
     * Update customer profile
     */
    public function updateProfile($user_id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Update users table - email stored as plain text (not hashed)
            if (isset($data['email'])) {
                $sql = "UPDATE users SET email = ? WHERE id = ?";
                $this->db->query($sql, [$data['email'], $user_id]);
            }
            
            // Update customers table
            $customer_fields = [];
            $customer_values = [];
            
            $allowed_fields = ['first_name', 'last_name', 'contact_number', 'barangay', 'city', 'province', 'postal_code'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $customer_fields[] = "$field = ?";
                    // Contact number stored as plain text (not hashed)
                    $customer_values[] = $data[$field];
                }
            }
            
            if (!empty($customer_fields)) {
                $customer_values[] = $user_id;
                $sql = "UPDATE customers SET " . implode(', ', $customer_fields) . " WHERE user_id = ?";
                $this->db->query($sql, $customer_values);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get customer orders
     */
    public function getCustomerOrders($customer_id, $limit = null) {
        $sql = "SELECT o.*, s.business_name, s.owner_name, s.city as supplier_city, s.province as supplier_province,
                       s.latitude, s.longitude, s.barangay
                FROM orders o
                LEFT JOIN suppliers s ON o.supplier_id = s.id
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $this->db->fetchAll($sql, [$customer_id]);
    }
    
    /**
     * Get customer order statistics
     */
    public function getCustomerStats($customer_id) {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
                    COUNT(CASE WHEN status IN ('pending', 'confirmed', 'processing') THEN 1 END) as pending_orders,
                    COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount END), 0) as total_spent
                FROM orders 
                WHERE customer_id = ?";
        
        return $this->db->fetch($sql, [$customer_id]);
    }
    
    /**
     * Create new customer
     */
    public function createCustomer($user_id, $data) {
        // Contact number stored as plain text (not hashed)
        $contact_number = $data['contact_number'] ?? '';
        
        $sql = "INSERT INTO customers (user_id, first_name, last_name, contact_number, barangay, city, province, postal_code, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        return $this->db->query($sql, [
            $user_id,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $contact_number,
            $data['barangay'] ?? '',
            $data['city'] ?? '',
            $data['province'] ?? '',
            $data['postal_code'] ?? ''
        ]);
    }
    
    /**
     * Get supplier by ID (for customer viewing)
     */
    public function getSupplierById($supplier_id) {
        $sql = "SELECT s.*, u.email
                FROM suppliers s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.status = 'approved'";
        return $this->db->fetch($sql, [$supplier_id]);
    }
    
    /**
     * Search suppliers
     */
    public function searchSuppliers($search_term = '', $city = '', $limit = 20) {
        $sql = "SELECT s.*, u.email
                FROM suppliers s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.status = 'approved'";
        
        $params = [];
        
        if (!empty($search_term)) {
            $sql .= " AND (s.business_name LIKE ? OR s.owner_name LIKE ? OR s.description LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($city)) {
            $sql .= " AND s.city LIKE ?";
            $params[] = "%$city%";
        }
        
        $sql .= " ORDER BY s.business_name LIMIT " . intval($limit);
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get customer's favorite suppliers
     */
    public function getFavoriteSuppliers($customer_id) {
        $sql = "SELECT s.*, u.email, COUNT(o.id) as order_count
                FROM suppliers s 
                JOIN users u ON s.user_id = u.id
                JOIN orders o ON s.id = o.supplier_id
                WHERE o.customer_id = ? AND s.status = 'approved'
                GROUP BY s.id
                ORDER BY order_count DESC, s.business_name
                LIMIT 10";
        
        return $this->db->fetchAll($sql, [$customer_id]);
    }
    
    /**
     * Check if customer exists
     */
    public function customerExists($user_id) {
        $sql = "SELECT id FROM customers WHERE user_id = ?";
        $result = $this->db->fetch($sql, [$user_id]);
        return $result !== false;
    }
    
    /**
     * Get customer dashboard data
     */
    public function getDashboardData($customer_id) {
        // Get recent orders
        $recent_orders = $this->getCustomerOrders($customer_id, 5);
        
        // Get statistics
        $stats = $this->getCustomerStats($customer_id);
        
        // Get favorite suppliers
        $favorite_suppliers = $this->getFavoriteSuppliers($customer_id);
        
        return [
            'recent_orders' => $recent_orders,
            'stats' => $stats,
            'favorite_suppliers' => $favorite_suppliers
        ];
    }
}
