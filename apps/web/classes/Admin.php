<?php

/**
 * Admin Class
 * Handles admin-specific functionality
 */

class Admin {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all feedback with pagination
     */
    public function getAllFeedback($limit = null, $offset = 0) {
        $sql = "SELECT 
                    f.id,
                    f.rating,
                    f.comment as review_text,
                    f.created_at,
                    f.image_path,
                    c.first_name,
                    c.last_name,
                    s.business_name,
                    o.order_number,
                    o.id as order_id,
                    s.id as supplier_id
                FROM feedback f
                JOIN customers c ON f.customer_id = c.id
                JOIN suppliers s ON f.supplier_id = s.id
                JOIN orders o ON f.order_id = o.id
                ORDER BY f.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            return $this->db->fetchAll($sql, [$limit, $offset]);
        }
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get total count of feedback records
     */
    public function getTotalFeedbackCount() {
        $sql = "SELECT COUNT(*) as total FROM feedback";
        $result = $this->db->fetch($sql);
        return $result['total'] ?? 0;
    }
    
    /**
     * Delete feedback by ID
     */
    public function deleteFeedback($feedback_id) {
        // First get the feedback to check if it exists and get image path
        $sql = "SELECT image_path FROM feedback WHERE id = ?";
        $feedback = $this->db->fetch($sql, [$feedback_id]);
        
        if (!$feedback) {
            throw new Exception('Feedback not found');
        }
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Delete the feedback record
            $sql = "DELETE FROM feedback WHERE id = ?";
            $this->db->query($sql, [$feedback_id]);
            
            // If there was an image, delete it from the filesystem
            if (!empty($feedback['image_path'])) {
                // Try multiple possible locations for the image
                $possible_paths = [
                    APP_ROOT . '/customer/' . ltrim($feedback['image_path'], '/'),
                    APP_ROOT . '/' . ltrim($feedback['image_path'], '/'),
                    UPLOAD_PATH . ltrim($feedback['image_path'], '/'),
                    UPLOAD_PATH . 'feedback/' . ltrim($feedback['image_path'], '/')
                ];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                        break; // Stop after deleting the first found file
                    }
                }
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Log the action
            $this->logAdminAction('feedback_deleted', 'feedback', $feedback_id, []);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get platform statistics
     */
    public function getPlatformStats($start_date = null, $end_date = null) {
        $date_condition = '';
        $params = [];
        
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        
        // Build date conditions for each subquery
        $orders_where = '';
        $completed_where = '';
        $revenue_where = '';
        $final_params = [];
        
        if ($start_date && $end_date) {
            $orders_where = "WHERE DATE(created_at) BETWEEN ? AND ?";
            $completed_where = "AND DATE(created_at) BETWEEN ? AND ?";
            $revenue_where = "AND DATE(o.created_at) BETWEEN ? AND ?";
            // Parameters: orders (2), completed_orders (2), revenue (2), service_fees (2) = 8 total
            $final_params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date];
        }
        
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM users WHERE user_type = 'customer') as total_customers,
                    (SELECT COUNT(*) FROM users WHERE user_type = 'supplier') as total_suppliers,
                    (SELECT COUNT(*) FROM suppliers WHERE status = 'pending') as pending_suppliers,
                    (SELECT COUNT(*) FROM orders {$orders_where}) as total_orders,
                    (SELECT COUNT(*) FROM orders WHERE status = 'delivered' {$completed_where}) as completed_orders,
                    (SELECT IFNULL(SUM(oi.subtotal), 0) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.status = 'delivered' {$revenue_where}) as total_revenue,
                    (SELECT IFNULL(SUM(oi.subtotal * 0.05), 0) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.status = 'delivered' {$revenue_where}) as total_service_fees,
                    (SELECT COUNT(*) FROM inventory WHERE availability_status = 'available') as active_products,
                    (SELECT COUNT(*) FROM species WHERE status = 'active') as total_species";
        
        return $this->db->fetch($sql, $final_params);
    }
    
    /**
     * Get supplier by ID
     */
    public function getSupplierById($supplier_id) {
        $sql = "SELECT s.*, u.email, u.created_at as registration_date
                FROM suppliers s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND s.status != 'deleted'";

        return $this->db->fetch($sql, [$supplier_id]);
    }

    /**
     * Get pending supplier registrations
     */
    public function getPendingSuppliers() {
        $sql = "SELECT s.*, u.email, u.created_at as registration_date
                FROM suppliers s
                JOIN users u ON s.user_id = u.id
                WHERE s.status = 'pending'
                ORDER BY u.created_at ASC";

        return $this->db->fetchAll($sql);
    }
    
    /**
     * Approve/reject/suspend supplier
     */
    public function updateSupplierStatus($supplier_id, $status, $admin_notes = '') {
        if (!in_array($status, ['approved', 'rejected', 'suspended'])) {
            throw new Exception('Invalid status');
        }
       
        try {
            $this->db->beginTransaction();
           
            // Update supplier status and suspension_reason if suspending
            $sql = "UPDATE suppliers SET status = ?, suspension_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $suspension_reason = ($status === 'suspended') ? $admin_notes : null;
            $this->db->query($sql, [$status, $suspension_reason, $supplier_id]);
           
            // Update user status
            $user_status = ($status === 'approved') ? 'active' : 'inactive';
            $sql = "UPDATE users u
                    JOIN suppliers s ON u.id = s.user_id
                    SET u.status = ?
                    WHERE s.id = ?";
            $this->db->query($sql, [$user_status, $supplier_id]);
           
            // Log the action
            $this->logAdminAction('supplier_status_update', 'suppliers', $supplier_id, [
                'new_status' => $status,
                'suspension_reason' => $suspension_reason
            ]);
           
            $this->db->commit();
            return true;
           
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get all users with filters
     */
    public function getUsers($filters = [], $limit = null, $offset = 0) {
        $where_conditions = [];
        $params = [];
        
        // Base query structure
        $sql = "SELECT 
                    u.*,
                    c.first_name, 
                    c.last_name, 
                    c.contact_number as customer_contact,
                    s.id as supplier_id, 
                    s.business_name, 
                    s.owner_name, 
                    s.contact_number as supplier_contact, 
                    s.status as supplier_status,
                    s.barangay, 
                    s.city, 
                    s.province, 
                    s.description, 
                    s.latitude, 
                    s.longitude,
                    s.rating, 
                    s.total_ratings, 
                    s.valid_id, 
                    s.permit_expiry, 
                    s.certifications,
                    s.business_address, 
                    s.purok,
                    CASE
                        WHEN s.permit_expiry IS NULL THEN 'no_date'
                        WHEN s.permit_expiry < CURDATE() THEN 'expired'
                        WHEN DATEDIFF(s.permit_expiry, CURDATE()) <= 30 THEN 'expiring_soon'
                        ELSE 'valid'
                    END as permit_status,
                    DATEDIFF(s.permit_expiry, CURDATE()) as days_until_expiry,
                    a.full_name as admin_name
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN suppliers s ON u.id = s.user_id
                LEFT JOIN admins a ON u.id = a.user_id";
        
        // Filter by user type
        if (!empty($filters['user_type'])) {
            $where_conditions[] = "u.user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        // Filter by status
        if (!empty($filters['status'])) {
            // For suppliers, use suppliers.status instead of users.status
            if (!empty($filters['user_type']) && $filters['user_type'] === 'supplier') {
                $where_conditions[] = "s.status = ?";
            } else {
                $where_conditions[] = "u.status = ?";
            }
            $params[] = $filters['status'];
        } 
        // Default filter for suppliers - exclude inactive
        else if (!empty($filters['user_type']) && $filters['user_type'] === 'supplier') {
            $where_conditions[] = "s.status != ?";
            $params[] = 'inactive';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = "(u.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR s.business_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        }
        
        // Build WHERE clause
        $where_clause = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);
        $sql .= $where_clause;
        
        // Order by creation date
        $sql .= " ORDER BY u.created_at DESC";
        
        // Pagination
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($user_id, $status) {
        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            throw new Exception('Invalid status');
        }
        
        $sql = "UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$status, $user_id]);
        
        $this->logAdminAction('user_status_update', 'users', $user_id, ['new_status' => $status]);
        
        return true;
    }
    
    /**
     * Get all orders with details
     */
    public function getAllOrders($filters = [], $limit = null, $offset = 0) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['supplier_id'])) {
            $where_conditions[] = "o.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT o.*, 
                       c.first_name, c.last_name, c.contact_number,
                       s.business_name, s.barangay, s.city,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                JOIN suppliers s ON o.supplier_id = s.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                {$where_clause}
                GROUP BY o.id
                ORDER BY o.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get sales analytics
     */
    public function getSalesAnalytics($period = 'month', $start_date = null, $end_date = null) {
        $date_condition = '';
        $group_by = '';
        $select_date = '';
        $params = [];
        
        // Handle custom date range if provided (overrides period)
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            
            // Determine grouping based on date range duration
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $diff = $start->diff($end)->days;
            
            if ($diff <= 31) {
                // Daily grouping for ranges <= 1 month
                $select_date = "DATE_FORMAT(o.created_at, '%Y-%m-%d') as date,";
                $group_by = "GROUP BY DATE_FORMAT(o.created_at, '%Y-%m-%d')";
            } elseif ($diff <= 365) {
                // Monthly grouping for ranges <= 1 year
                $select_date = "DATE_FORMAT(o.created_at, '%Y-%m-01') as date,";
                $group_by = "GROUP BY DATE_FORMAT(o.created_at, '%Y-%m-01')";
            } else {
                // Yearly grouping for ranges > 1 year
                 $select_date = "DATE_FORMAT(o.created_at, '%Y-01-01') as date,";
                $group_by = "GROUP BY DATE_FORMAT(o.created_at, '%Y-01-01')";
            }
        } else {
            // Fallback to period logic
            switch ($period) {
                case 'day':
                    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                    $select_date = "DATE_FORMAT(o.created_at, '%Y-%m-%d %H:00') as date,";
                    $group_by = "GROUP BY DATE_FORMAT(o.created_at, '%Y-%m-%d %H')";
                    break;
                case 'week':
                    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    $select_date = "DATE(o.created_at) as date,";
                    $group_by = "GROUP BY DATE(o.created_at)";
                    break;
                case 'month':
                    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    $select_date = "DATE(o.created_at) as date,";
                    $group_by = "GROUP BY DATE(o.created_at)";
                    break;
                case 'year':
                    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    $select_date = "DATE(o.created_at) as date,";
                    $group_by = "GROUP BY DATE(o.created_at)";
                    break;
                 case 'all':
                    // No date condition, group by month by default for lifetime
                    $date_condition = "";
                    $select_date = "DATE_FORMAT(o.created_at, '%Y-%m-01') as date,";
                    $group_by = "GROUP BY DATE_FORMAT(o.created_at, '%Y-%m-01')";
                    break;
            }
        }
        
        $sql = "SELECT 
                    {$select_date}
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END), 0) as revenue,
                    COUNT(DISTINCT o.customer_id) as unique_customers,
                    COUNT(DISTINCT o.supplier_id) as active_suppliers
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE 1=1 {$date_condition}
                {$group_by}
                ORDER BY date ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get supplier performance
     */
    public function getSupplierPerformance($start_date = null, $end_date = null) {
        $date_condition = '';
        $params = [];
        
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        
        $sql = "SELECT
                    s.id, s.business_name, s.owner_name, s.barangay, s.city, s.province,
                    s.rating, s.total_ratings,
                    COUNT(o.id) as total_orders,
                    COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END), 0) as total_revenue,
                    COALESCE(AVG(order_totals.order_revenue), 0) as avg_order_value,
                    COUNT(DISTINCT o.customer_id) as unique_customers,
                    COUNT(DISTINCT i.id) as total_products
                FROM suppliers s
                LEFT JOIN orders o ON s.id = o.supplier_id " . ($date_condition ? $date_condition : "") . "
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN (
                    SELECT order_id, SUM(subtotal) as order_revenue
                    FROM order_items
                    GROUP BY order_id
                ) order_totals ON o.id = order_totals.order_id
                LEFT JOIN inventory i ON s.id = i.supplier_id
                WHERE s.status = 'approved'
                GROUP BY s.id
                ORDER BY total_revenue DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        $tables = ['users', 'suppliers', 'orders', 'inventory', 'categories', 'payments'];
        $stats = [];

        foreach ($tables as $table) {
            $sql = "SELECT COUNT(*) as count FROM $table";
            $result = $this->db->fetch($sql);
            $stats[$table] = $result['count'] ?? 0;
        }

        // Map inventory to products for backward compatibility
        if (isset($stats['inventory'])) {
            $stats['products'] = $stats['inventory'];
        }

        return $stats;
    }

    /**
     * Create database backup
     */
    public function createDatabaseBackup() {
        $backup_dir = RUNTIME_PATH . '/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;

        // This is a simplified backup - in production, use mysqldump
        $tables = $this->db->fetchAll("SHOW TABLES");
        $backup_content = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $table_name = array_values($table)[0];
            $backup_content .= "-- Table: $table_name\n";
            $backup_content .= "DROP TABLE IF EXISTS `$table_name`;\n";

            // Get table structure
            $create_table = $this->db->fetch("SHOW CREATE TABLE `$table_name`");
            $backup_content .= $create_table['Create Table'] . ";\n\n";
        }

        file_put_contents($filepath, $backup_content);
        return $filepath;
    }

    /**
     * Restore database backup
     */
    public function restoreDatabaseBackup($backup_file) {
        $sql_content = file_get_contents($backup_file);

        // Split SQL content into individual queries
        $queries = explode(';', $sql_content);

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query) && !str_starts_with($query, '--')) {
                $this->db->query($query);
            }
        }

        return true;
    }

    /**
     * Get backup files
     */
    public function getBackupFiles() {
        $backup_dir = RUNTIME_PATH . '/backups/';
        $files = [];

        if (is_dir($backup_dir)) {
            $scan = scandir($backup_dir);
            foreach ($scan as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $filepath = $backup_dir . $file;
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($filepath),
                        'modified' => filemtime($filepath)
                    ];
                }
            }
        }

        return $files;
    }

    /**
     * Archive backup file (move to archived folder)
     */
    public function archiveBackupFile($filename) {
        $backup_dir = RUNTIME_PATH . '/backups/';
        $archive_dir = RUNTIME_PATH . '/backups/archived/';

        // Create archived directory if it doesn't exist
        if (!is_dir($archive_dir)) {
            mkdir($archive_dir, 0755, true);
        }

        $source_path = $backup_dir . basename($filename);
        $archive_path = $archive_dir . basename($filename);

        // Check if source file exists
        if (!file_exists($source_path)) {
            throw new Exception("Backup file not found: " . $filename);
        }

        // Move file to archived folder
        if (!rename($source_path, $archive_path)) {
            throw new Exception("Failed to archive backup file: " . $filename);
        }

        return true;
    }

    /**
     * Get popular species
     */
    public function getPopularSpecies($start_date = null, $end_date = null) {
        $date_condition = '';
        $params = [];
        
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        
        $sql = "SELECT 
                    sp.name, sp.scientific_name, sp.category,
                    COUNT(DISTINCT i.id) as suppliers_count,
                    COALESCE(SUM(oi.quantity), 0) as total_sold,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue
                FROM species sp
                JOIN inventory i ON sp.id = i.species_id
                LEFT JOIN order_items oi ON i.id = oi.inventory_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered' " . ($date_condition ? $date_condition : "") . "
                WHERE sp.status = 'active'
                GROUP BY sp.id
                ORDER BY total_sold DESC
                LIMIT 10";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get customer activity
     */
    public function getCustomerActivity($start_date = null, $end_date = null) {
        $date_condition = '';
        $params = [];
        
        if ($start_date && $end_date) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        
        $sql = "SELECT 
                    c.id, c.first_name, c.last_name, c.barangay, c.city,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent,
                    MAX(o.created_at) as last_order_date,
                    COUNT(f.id) as reviews_given
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id " . ($date_condition ? $date_condition : "") . "
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN feedback f ON c.id = f.customer_id
                GROUP BY c.id
                HAVING total_orders > 0
                ORDER BY total_spent DESC
                LIMIT 20";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Manage species
     */
    public function addSpecies($name, $scientific_name, $description, $category, $image_url = null) {
        $sql = "INSERT INTO species (name, scientific_name, description, category, image_url) 
                VALUES (?, ?, ?, ?, ?)";
        $this->db->query($sql, [$name, $scientific_name, $description, $category, $image_url]);
        
        $species_id = $this->db->lastInsertId();
        $this->logAdminAction('species_added', 'species', $species_id, [
            'name' => $name,
            'scientific_name' => $scientific_name
        ]);
        
        return $species_id;
    }
    
    /**
     * Update species
     */
    public function updateSpecies($species_id, $data) {
        $fields = ['name', 'scientific_name', 'description', 'category', 'image_url', 'status'];
        $updates = [];
        $values = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $values[] = $species_id;
            $sql = "UPDATE species SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->db->query($sql, $values);
            
            $this->logAdminAction('species_updated', 'species', $species_id, $data);
        }
        
        return true;
    }
    
    /**
     * Get platform settings
     */
    public function getSettings() {
        $sql = "SELECT * FROM settings ORDER BY setting_key";
        $settings = $this->db->fetchAll($sql);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        
        return $result;
    }
    
    /**
     * Update platform settings
     */
    public function updateSetting($key, $value) {
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $this->db->query($sql, [$key, $value]);
        
        $this->logAdminAction('setting_updated', 'settings', null, [
            'setting_key' => $key,
            'setting_value' => $value
        ]);
        
        return true;
    }
    
    /**
     * Log admin actions
     */
    private function logAdminAction($action, $table_name, $record_id, $data = []) {
        if (!is_logged_in()) return;
        
        $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            get_user_id(),
            $action,
            $table_name,
            $record_id,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
    
    /**
     * Get audit log
     */
    public function getAuditLog($limit = 50, $offset = 0) {
        $sql = "SELECT al.*, u.email as admin_email
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";

        return $this->db->fetchAll($sql, [$limit, $offset]);
    }

    /**
     * Get all users (alias for getUsers)
     */
    public function getAllUsers($filters = []) {
        return $this->getUsers($filters);
    }

    /**
     * Get customers only
     */
    public function getCustomers($filters = []) {
        $where_conditions = ["u.user_type = 'customer'"];
        $params = [];

        if (!empty($filters['status'])) {
            $where_conditions[] = "u.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(u.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT u.*, c.first_name, c.last_name, c.contact_number, c.address,
                       COUNT(o.id) as total_orders,
                       SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent
                FROM users u
                JOIN customers c ON u.id = c.user_id
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE {$where_clause}
                GROUP BY u.id
                ORDER BY u.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get orders with tracking info
     */
    public function getOrdersWithTracking() {
        $sql = "SELECT o.*,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       s.business_name as supplier_name,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                JOIN suppliers s ON o.supplier_id = s.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
                GROUP BY o.id
                ORDER BY o.created_at DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get all payments
     */
    public function getAllPayments($filters = []) {
        $where_conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['method'])) {
            $where_conditions[] = "p.payment_method = ?";
            $params[] = $filters['method'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(p.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(p.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $sql = "SELECT p.*, o.id as order_id,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       u.email as customer_email
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                JOIN customers c ON o.customer_id = c.id
                JOIN users u ON c.user_id = u.id
                {$where_clause}
                ORDER BY p.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get all refunds
     */
    public function getAllRefunds() {
        $sql = "SELECT r.*, o.id as order_id,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       u.email as customer_email
                FROM refunds r
                JOIN orders o ON r.order_id = o.id
                JOIN customers c ON o.customer_id = c.id
                JOIN users u ON c.user_id = u.id
                ORDER BY r.created_at DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Approve refund
     */
    public function approveRefund($refund_id) {
        $sql = "UPDATE refunds SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$refund_id]);

        $this->logAdminAction('refund_approved', 'refunds', $refund_id);
        return true;
    }

    /**
     * Reject refund
     */
    public function rejectRefund($refund_id, $reason = '') {
        $sql = "UPDATE refunds SET status = 'rejected', admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$reason, $refund_id]);

        $this->logAdminAction('refund_rejected', 'refunds', $refund_id, ['reason' => $reason]);
        return true;
    }

    /**
     * Approve supplier
     */
    public function approveSupplier($supplier_id) {
        // Get supplier information before updating status
        $supplier = $this->getSupplierById($supplier_id);
        if (!$supplier) {
            throw new Exception('Supplier not found');
        }
        
        $result = $this->updateSupplierStatus($supplier_id, 'approved');
        
        if ($result) {
            if (!function_exists('send_supplier_status_email')) {
                require_once __DIR__ . '/../includes/send_supplier_status_email.php';
            }
            $email_result = send_supplier_status_email($supplier_id, 'approved');
            if (!$email_result['success']) {
                error_log("Failed to send approval email to supplier {$supplier_id}: " . $email_result['error']);
            }
        }
        
        return $result;
    }

    /**
     * Reject supplier - DELETE supplier entirely
     * This will delete all supplier data including account, products, images, and related data
     * Supplier can re-register with the same email after deletion
     */
    public function rejectSupplier($supplier_id, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Get supplier information before deletion
            $supplier = $this->getSupplierById($supplier_id);
            if (!$supplier) {
                throw new Exception('Supplier not found');
            }
            
            $user_id = $supplier['user_id'];
            $supplier_email = $supplier['email'] ?? null;
            $business_name = $supplier['business_name'] ?? 'Supplier';
            
            if (!function_exists('send_supplier_status_email')) {
                require_once __DIR__ . '/../includes/send_supplier_status_email.php';
            }
            $email_result = send_supplier_status_email($supplier_id, 'rejected', $reason);
            if (!$email_result['success']) {
                error_log("Failed to send rejection email to supplier {$supplier_id}: " . $email_result['error']);
            }
            
            // Get all inventory items for this supplier to delete images
            $inventory_sql = "SELECT id, image_path FROM inventory WHERE supplier_id = ?";
            $inventory_items = $this->db->fetchAll($inventory_sql, [$supplier_id]);
            
            // Delete product images
            foreach ($inventory_items as $item) {
                if (!empty($item['image_path'])) {
                    $image_paths = [
                        APP_ROOT . '/' . ltrim($item['image_path'], '/'),
                        UPLOAD_PATH . 'products/' . basename($item['image_path'])
                    ];
                    
                    foreach ($image_paths as $path) {
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                }
            }
            
            // Delete supplier valid ID files
            if (!empty($supplier['valid_id'])) {
                $permit_paths = [
                    APP_ROOT . '/' . ltrim($supplier['valid_id'], '/'),
                    UPLOAD_PATH . 'permits/' . basename($supplier['valid_id'])
                ];
                
                foreach ($permit_paths as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
            
            // Delete supplier logo if exists
            if (!empty($supplier['logo_url'])) {
                $logo_paths = [
                    APP_ROOT . '/' . ltrim($supplier['logo_url'], '/'),
                    UPLOAD_PATH . 'logos/' . basename($supplier['logo_url'])
                ];
                
                foreach ($logo_paths as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
            
            // Delete pond photo if exists
            if (!empty($supplier['pond_photo'])) {
                $pond_paths = [
                    APP_ROOT . '/' . ltrim($supplier['pond_photo'], '/'),
                    UPLOAD_PATH . basename($supplier['pond_photo'])
                ];
                
                foreach ($pond_paths as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
            
            // Cancel all pending orders for this supplier
            $cancel_orders_sql = "UPDATE orders SET status = 'cancelled', updated_at = NOW() 
                                 WHERE supplier_id = ? AND status IN ('pending', 'confirmed', 'preparing')";
            $this->db->query($cancel_orders_sql, [$supplier_id]);
            
            // Delete supplier notes
            $delete_notes_sql = "DELETE FROM supplier_notes WHERE supplier_id = ?";
            $this->db->query($delete_notes_sql, [$supplier_id]);
            
            // Delete supplier appeals (if table exists)
            try {
                $delete_appeals_sql = "DELETE FROM supplier_appeals WHERE supplier_id = ?";
                $this->db->query($delete_appeals_sql, [$supplier_id]);
            } catch (Exception $e) {
                // Table might not exist, ignore error
                error_log("Could not delete supplier appeals (table may not exist): " . $e->getMessage());
            }
            
            // Delete supplier record (this will cascade delete inventory due to foreign key)
            $delete_supplier_sql = "DELETE FROM suppliers WHERE id = ?";
            $this->db->query($delete_supplier_sql, [$supplier_id]);
            
            // Delete user record (this will cascade delete related data)
            // This allows supplier to re-register with the same email
            $delete_user_sql = "DELETE FROM users WHERE id = ?";
            $this->db->query($delete_user_sql, [$user_id]);
            
            // Log the action
            $this->logAdminAction('supplier_rejected_deleted', 'suppliers', $supplier_id, [
                'reason' => $reason,
                'supplier_email' => $supplier_email,
                'business_name' => $supplier['business_name'] ?? ''
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get supplier locations
     */
    public function getSupplierLocations() {
        $sql = "SELECT s.*, u.email,
                       COUNT(i.id) as product_count
                FROM suppliers s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN inventory i ON s.id = i.supplier_id
                WHERE s.status = 'approved'
                GROUP BY s.id
                ORDER BY s.business_name";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get all categories
     */
    public function getAllCategories() {
        $sql = "SELECT c.*,
                       COUNT(s.id) as species_count
                FROM categories c
                LEFT JOIN species s ON c.id = s.category_id
                GROUP BY c.id
                ORDER BY c.name";

        return $this->db->fetchAll($sql);
    }

    /**
     * Add category
     */
    public function addCategory($name, $description = '') {
        $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $this->db->query($sql, [$name, $description]);

        $category_id = $this->db->lastInsertId();
        $this->logAdminAction('category_added', 'categories', $category_id, ['name' => $name]);

        return $category_id;
    }

    /**
     * Update category
     */
    public function updateCategory($category_id, $name, $description = '') {
        $sql = "UPDATE categories SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$name, $description, $category_id]);

        $this->logAdminAction('category_updated', 'categories', $category_id, ['name' => $name]);
        return true;
    }

    /**
     * Delete category
     */
    public function deleteCategory($category_id) {
        // Check if category has species
        $sql = "SELECT COUNT(*) as count FROM species WHERE category_id = ?";
        $result = $this->db->fetch($sql, [$category_id]);

        if ($result['count'] > 0) {
            throw new Exception('Cannot delete category with existing species');
        }

        $sql = "DELETE FROM categories WHERE id = ?";
        $this->db->query($sql, [$category_id]);

        $this->logAdminAction('category_deleted', 'categories', $category_id);
        return true;
    }

    /**
     * Toggle category status
     */
    public function toggleCategoryStatus($category_id) {
        $sql = "UPDATE categories SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?";
        $this->db->query($sql, [$category_id]);

        $this->logAdminAction('category_status_toggled', 'categories', $category_id);
        return true;
    }

    /**
     * Get sales report
     */
    public function getSalesReport($date_from, $date_to, $supplier_id = '') {
        $where_conditions = ["o.status = 'delivered'", "DATE(o.created_at) BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];

        if (!empty($supplier_id)) {
            $where_conditions[] = "o.supplier_id = ?";
            $params[] = $supplier_id;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT o.id, o.created_at, o.total_amount, o.status,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       s.business_name as supplier_name,
                       COUNT(oi.id) as total_items
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                JOIN suppliers s ON o.supplier_id = s.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE {$where_clause}
                GROUP BY o.id
                ORDER BY o.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts($date_from, $date_to, $limit = 10) {
        $sql = "SELECT sp.name as species_name, i.size_category,
                       SUM(oi.quantity) as total_sold,
                       SUM(oi.subtotal) as total_revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN inventory i ON oi.inventory_id = i.id
                JOIN species sp ON i.species_id = sp.id
                WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY i.species_id, i.size_category
                ORDER BY total_sold DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$date_from, $date_to, $limit]);
    }

    /**
     * Get top suppliers
     */
    public function getTopSuppliers($date_from, $date_to, $limit = 10) {
        $sql = "SELECT s.business_name, s.city,
                       COUNT(o.id) as total_orders,
                       SUM(o.total_amount) as total_revenue
                FROM suppliers s
                JOIN orders o ON s.id = o.supplier_id
                WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY s.id
                ORDER BY total_revenue DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$date_from, $date_to, $limit]);
    }

    /**
     * Get daily sales
     */
    public function getDailySales($date_from, $date_to) {
        $sql = "SELECT DATE(created_at) as date,
                       COUNT(*) as orders,
                       SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as revenue
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        return $this->db->fetchAll($sql, [$date_from, $date_to]);
    }

    /**
     * Get customer insights
     */
    public function getCustomerInsights() {
        // Get basic customer stats
        $sql = "SELECT
                    COUNT(DISTINCT u.id) as total_customers,
                    COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_customers,
                    COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN u.id END) as new_customers_month,
                    AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END) as avg_order_value
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE u.user_type = 'customer'";

        $result = $this->db->fetch($sql);
        
        // Get average orders per customer
        $avg_orders_sql = "SELECT AVG(order_count) as avg_orders_per_customer
                          FROM (
                              SELECT c.id, COUNT(o.id) as order_count
                              FROM customers c
                              JOIN users u ON c.user_id = u.id
                              LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'delivered'
                              WHERE u.user_type = 'customer'
                              GROUP BY c.id
                          ) customer_order_stats";
        
        $avg_orders_result = $this->db->fetch($avg_orders_sql);
        
        // Get repeat customer rate
        $repeat_sql = "SELECT 
                          (COUNT(CASE WHEN order_count > 1 THEN 1 END) * 100.0 / COUNT(*)) as repeat_customer_rate
                       FROM (
                           SELECT c.id, COUNT(o.id) as order_count
                           FROM customers c
                           JOIN users u ON c.user_id = u.id
                           LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'delivered'
                           WHERE u.user_type = 'customer'
                           GROUP BY c.id
                       ) customer_order_stats";
        
        $repeat_result = $this->db->fetch($repeat_sql);
        
        // Get customer lifetime value
        $lifetime_sql = "SELECT AVG(total_spent) as customer_lifetime_value
                        FROM (
                            SELECT c.id, SUM(o.total_amount) as total_spent
                            FROM customers c
                            JOIN users u ON c.user_id = u.id
                            LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'delivered'
                            WHERE u.user_type = 'customer'
                            GROUP BY c.id
                        ) customer_totals";
        
        $lifetime_value = $this->db->fetch($lifetime_sql);
        
        // Merge all results
        return array_merge($result, $avg_orders_result, $repeat_result, $lifetime_value);
    }

    /**
     * Get top customers
     */
    public function getTopCustomers($limit = 10) {
        $sql = "SELECT CONCAT(c.first_name, ' ', c.last_name) as full_name,
                       u.email,
                       COUNT(o.id) as total_orders,
                       SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent
                FROM customers c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE u.user_type = 'customer'
                GROUP BY c.id
                HAVING total_orders > 0
                ORDER BY total_spent DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get customer demographics
     */
    public function getCustomerDemographics() {
        $by_location_sql = "SELECT c.city, COUNT(*) as count
                           FROM customers c
                           JOIN users u ON c.user_id = u.id
                           WHERE u.user_type = 'customer' AND c.city IS NOT NULL
                           GROUP BY c.city
                           ORDER BY count DESC
                           LIMIT 10";

        return [
            'by_location' => $this->db->fetchAll($by_location_sql)
        ];
    }

    /**
     * Get all announcements
     */
    public function getAllAnnouncements() {
        $sql = "SELECT * FROM announcements ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Add announcement
     */
    public function addAnnouncement($title, $content, $type, $target_audience) {
        $sql = "INSERT INTO announcements (title, content, type, target_audience) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$title, $content, $type, $target_audience]);

        $announcement_id = $this->db->lastInsertId();
        $this->logAdminAction('announcement_added', 'announcements', $announcement_id, ['title' => $title]);

        return $announcement_id;
    }

    /**
     * Update announcement
     */
    public function updateAnnouncement($announcement_id, $title, $content, $type, $target_audience) {
        $sql = "UPDATE announcements SET title = ?, content = ?, type = ?, target_audience = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$title, $content, $type, $target_audience, $announcement_id]);

        $this->logAdminAction('announcement_updated', 'announcements', $announcement_id, ['title' => $title]);
        return true;
    }

    /**
     * Archive announcement (soft delete)
     */
    public function archiveAnnouncement($announcement_id) {
        $sql = "UPDATE announcements SET status = 'archived' WHERE id = ?";
        $this->db->query($sql, [$announcement_id]);

        $this->logAdminAction('announcement_archived', 'announcements', $announcement_id);
        return true;
    }

    /**
     * Archive category (soft delete)
     */
    public function archiveCategory($category_id) {
        $sql = "UPDATE categories SET status = 'archived' WHERE id = ?";
        $this->db->query($sql, [$category_id]);

        $this->logAdminAction('category_archived', 'categories', $category_id);
        return true;
    }

    /**
     * Toggle announcement status
     */
    public function toggleAnnouncementStatus($announcement_id) {
        $sql = "UPDATE announcements SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?";
        $this->db->query($sql, [$announcement_id]);

        $this->logAdminAction('announcement_status_toggled', 'announcements', $announcement_id);
        return true;
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings() {
        $sql = "SELECT * FROM settings WHERE setting_key LIKE 'notification_%' OR setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'";
        $results = $this->db->fetchAll($sql);

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings($settings) {
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $this->db->query($sql, [$key, $value]);
        }

        return true;
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings() {
        $sql = "SELECT * FROM settings WHERE setting_key LIKE 'payment_%' OR setting_key LIKE 'paypal_%' OR setting_key LIKE 'stripe_%' OR setting_key LIKE 'gcash_%' OR setting_key LIKE 'paymaya_%'";
        $results = $this->db->fetchAll($sql);

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Update payment settings
     */
    public function updatePaymentSettings($settings) {
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $this->db->query($sql, [$key, $value]);
        }

        return true;
    }

    /**
     * Get site content
     */
    public function getSiteContent() {
        $sql = "SELECT * FROM settings WHERE setting_key IN ('about_us', 'privacy_policy', 'terms_conditions', 'contact_info', 'shipping_policy', 'return_policy', 'faq', 'hero_title', 'hero_subtitle', 'hero_description')";
        $results = $this->db->fetchAll($sql);

        $content = [];
        foreach ($results as $row) {
            $content[$row['setting_key']] = $row['setting_value'];
        }

        return $content;
    }

    /**
     * Update site content
     */
    public function updateSiteContent($content) {
        foreach ($content as $key => $value) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $this->db->query($sql, [$key, $value]);
        }

        return true;
    }

    /**
     * Get all admins
     */
    public function getAllAdmins() {
        $sql = "SELECT a.id, a.full_name, u.email, a.role, a.permissions, a.created_at, u.status
                FROM admins a
                JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Add new admin
     */
    public function addAdmin($data) {
        try {
            $this->db->beginTransaction();

            // Insert into users table
            $sql = "INSERT INTO users (email, password_hash, user_type, status, created_at)
                    VALUES (?, ?, 'admin', 'active', NOW())";

            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $this->db->query($sql, [$data['email'], $password_hash]);

            // Get the user ID
            $user_id = $this->db->lastInsertId();

            // Insert into admins table
            $sql = "INSERT INTO admins (user_id, full_name, role, permissions, created_at)
                    VALUES (?, ?, 'admin', ?, NOW())";

            $full_name = ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '');
            $permissions = json_encode($data['permissions'] ?? []);

            $this->db->query($sql, [$user_id, trim($full_name), $permissions]);

            $this->db->commit();
            return $user_id;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update admin
     */
    public function updateAdmin($data) {
        try {
            $this->db->beginTransaction();

            // Update users table
            if (isset($data['password']) && !empty($data['password'])) {
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET email = ?, password_hash = ?, status = ? WHERE id = ?";
                $this->db->query($sql, [
                    $data['email'],
                    $password_hash,
                    $data['status'],
                    $data['id']
                ]);
            } else {
                $sql = "UPDATE users SET email = ?, status = ? WHERE id = ?";
                $this->db->query($sql, [
                    $data['email'],
                    $data['status'],
                    $data['id']
                ]);
            }

            // Update admins table
            $permissions = json_encode($data['permissions'] ?? []);
            $sql = "UPDATE admins SET full_name = ?, permissions = ? WHERE user_id = ?";
            $this->db->query($sql, [
                $data['full_name'],
                $permissions,
                $data['id']
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Archive admin (soft delete)
     */
    public function archiveAdmin($admin_id) {
        // Update both users and admins tables
        $sql1 = "UPDATE users SET status = 'archived' WHERE id = (SELECT user_id FROM admins WHERE id = ?)";
        $sql2 = "UPDATE admins SET status = 'archived' WHERE id = ?";

        $this->db->query($sql1, [$admin_id]);
        return $this->db->query($sql2, [$admin_id]);
    }

    /**
     * Restore admin from archive
     */
    public function restoreAdmin($admin_id) {
        // Update both users and admins tables
        $sql1 = "UPDATE users SET status = 'active' WHERE id = (SELECT user_id FROM admins WHERE id = ?)";
        $sql2 = "UPDATE admins SET status = 'active' WHERE id = ?";

        $this->db->query($sql1, [$admin_id]);
        return $this->db->query($sql2, [$admin_id]);
    }

    /**
     * Get all suppliers
     */
    public function getAllSuppliers() {
        $sql = "SELECT id, business_name FROM suppliers WHERE status = 'approved' ORDER BY business_name";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get orders report
     */
    public function getOrdersReport($date_from, $date_to, $supplier_id = '') {
        $where_conditions = ["DATE(o.created_at) BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];

        if (!empty($supplier_id)) {
            $where_conditions[] = "o.supplier_id = ?";
            $params[] = $supplier_id;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT o.id, o.order_number, o.created_at, o.total_amount, o.status,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                       s.business_name as supplier_name,
                       c.contact_number as customer_phone
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                JOIN suppliers s ON o.supplier_id = s.id
                WHERE {$where_clause}
                ORDER BY o.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get suppliers report
     */
    public function getSuppliersReport($date_from, $date_to) {
        $sql = "SELECT s.business_name, s.city, s.province, s.rating, s.status,
                       COUNT(o.id) as total_orders,
                       COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders,
                       SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_revenue
                FROM suppliers s
                LEFT JOIN orders o ON s.id = o.supplier_id AND DATE(o.created_at) BETWEEN ? AND ?
                WHERE s.status = 'approved'
                GROUP BY s.id
                ORDER BY total_revenue DESC";

        return $this->db->fetchAll($sql, [$date_from, $date_to]);
    }

    /**
     * Get customers report
     */
    public function getCustomersReport($date_from, $date_to) {
        $sql = "SELECT CONCAT(c.first_name, ' ', c.last_name) as full_name,
                       u.email, c.phone, u.status,
                       COUNT(o.id) as total_orders,
                       SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_spent,
                       MAX(o.created_at) as last_order
                FROM customers c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN orders o ON c.id = o.customer_id AND DATE(o.created_at) BETWEEN ? AND ?
                WHERE u.user_type = 'customer'
                GROUP BY c.id
                ORDER BY total_spent DESC";

        return $this->db->fetchAll($sql, [$date_from, $date_to]);
    }

    /**
     * Get products report
     */
    public function getProductsReport($date_from, $date_to, $supplier_id = '') {
        $where_conditions = ["DATE(o.created_at) BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];

        if (!empty($supplier_id)) {
            $where_conditions[] = "i.supplier_id = ?";
            $params[] = $supplier_id;
        }

        $where_clause = empty($where_conditions) ? '' : 'AND ' . implode(' AND ', $where_conditions);

        $sql = "SELECT sp.name, s.business_name as supplier_name, c.name as category_name,
                       i.price, i.stock_quantity, i.status,
                       COUNT(oi.id) as total_orders,
                       SUM(CASE WHEN o.status = 'delivered' THEN oi.subtotal ELSE 0 END) as total_revenue
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                JOIN categories c ON sp.category_id = c.id
                LEFT JOIN order_items oi ON i.id = oi.inventory_id
                LEFT JOIN orders o ON oi.order_id = o.id {$where_clause}
                GROUP BY i.id
                ORDER BY total_revenue DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get suppliers with expired permits
     */
    public function getExpiredPermits() {
        $sql = "SELECT
                    s.id, s.business_name, s.owner_name, s.contact_number,
                    s.permit_expiry, s.supplier_status,
                    DATEDIFF(s.permit_expiry, CURDATE()) as days_until_expiry
                FROM suppliers s
                WHERE s.permit_expiry IS NOT NULL
                AND s.permit_expiry < CURDATE()
                AND s.supplier_status != 'suspended'
                ORDER BY s.permit_expiry ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get suppliers with permits expiring within specified days
     */
    public function getExpiringPermits($days = 30) {
        $sql = "SELECT
                    s.id, s.business_name, s.owner_name, s.contact_number,
                    s.permit_expiry, s.supplier_status,
                    DATEDIFF(s.permit_expiry, CURDATE()) as days_until_expiry
                FROM suppliers s
                WHERE s.permit_expiry IS NOT NULL
                AND s.permit_expiry >= CURDATE()
                AND s.permit_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND s.supplier_status != 'suspended'
                ORDER BY s.permit_expiry ASC";

        return $this->db->fetchAll($sql, [$days]);
    }

    /**
     * Get permit status statistics
     */
    public function getPermitStats() {
        $sql = "SELECT
                    COUNT(*) as total_suppliers,
                    COUNT(CASE WHEN permit_expiry IS NULL THEN 1 END) as no_permit_date,
                    COUNT(CASE WHEN permit_expiry < CURDATE() THEN 1 END) as expired,
                    COUNT(CASE WHEN permit_expiry >= CURDATE() AND permit_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                    COUNT(CASE WHEN permit_expiry > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as valid
                FROM suppliers
                WHERE supplier_status != 'suspended'";

        return $this->db->fetch($sql);
    }

    /**
     * Auto-suspend suppliers with expired permits (optional feature)
     */
    public function autoSuspendExpiredPermits() {
        $sql = "UPDATE suppliers
                SET supplier_status = 'suspended',
                    updated_at = NOW()
                WHERE permit_expiry IS NOT NULL
                AND permit_expiry < CURDATE()
                AND supplier_status = 'approved'";

        return $this->db->execute($sql);
    }

    /**
     * Get detailed supplier information
     */
    public function getSupplierDetails($supplier_id) {
        $sql = "SELECT
                    s.*,
                    u.email,
                    u.created_at as registration_date,
                    CASE
                        WHEN s.permit_expiry IS NULL THEN 'no_date'
                        WHEN s.permit_expiry < CURDATE() THEN 'expired'
                        WHEN DATEDIFF(s.permit_expiry, CURDATE()) <= 30 THEN 'expiring_soon'
                        ELSE 'valid'
                    END as permit_status,
                    DATEDIFF(s.permit_expiry, CURDATE()) as days_until_expiry
                FROM suppliers s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ?";

        return $this->db->fetch($sql, [$supplier_id]);
    }

    /**
     * Update supplier permit information
     */
    public function updateSupplierPermit($supplier_id, $data) {
        $fields = [];
        $values = [];

        $allowed_fields = ['valid_id', 'permit_expiry'];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (!empty($fields)) {
            $values[] = $supplier_id;
            $sql = "UPDATE suppliers SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, $values);

            $this->logAdminAction('supplier_permit_updated', 'suppliers', $supplier_id, $data);
            return true;
        }

        return false;
    }

    /**
     * Get customer registration trend
     */
    public function getCustomerRegistrationTrend() {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%b') as month,
                    COUNT(*) as count
                FROM users 
                WHERE user_type = 'customer' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC";

        $result = $this->db->fetchAll($sql);
        
        // Create array with all months
        $months = [];
        $counts = [];
        
        // Initialize with 0 values
        for ($i = 11; $i >= 0; $i--) {
            $month = date('M', strtotime("-$i months"));
            $months[] = $month;
            $counts[$month] = 0;
        }
        
        // Fill in actual data
        foreach ($result as $row) {
            $counts[$row['month']] = (int)$row['count'];
        }
        
        return [
            'labels' => $months,
            'data' => array_values($counts)
        ];
    }

    // ===== NEW APPEAL METHODS BELOW =====

    /**
     * Get all supplier appeals
     */
    public function getSupplierAppeals($limit = null, $offset = 0) {
        $sql = "SELECT sa.*, s.business_name, s.owner_name, s.contact as supplier_contact, 
                       CONCAT(s.barangay, ', ', s.city, ', ', s.province) as location,
                       u.email
                FROM supplier_appeals sa
                JOIN suppliers s ON sa.supplier_id = s.id
                JOIN users u ON s.user_id = u.id
                ORDER BY sa.created_at DESC";
       
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $result = $this->db->fetchAll($sql, [$limit, $offset]);
        } else {
            $result = $this->db->fetchAll($sql);
        }
        
        // Map supplier_contact to contact for compatibility
        foreach ($result as &$row) {
            $row['contact'] = $row['supplier_contact'] ?? '';
        }
        
        return $result;
    }

    /**
     * Get appeal by ID
     */
    public function getAppealById($appeal_id) {
        $sql = "SELECT sa.*, s.business_name, s.owner_name, s.contact as supplier_contact,
                       CONCAT(s.barangay, ', ', s.city, ', ', s.province) as location,
                       u.email
                FROM supplier_appeals sa
                JOIN suppliers s ON sa.supplier_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE sa.id = ?";
        $result = $this->db->fetch($sql, [$appeal_id]);
        if ($result) {
            $result['contact'] = $result['supplier_contact'] ?? '';
        }
        return $result;
    }

    /**
     * Update appeal status
     */
    public function updateAppealStatus($appeal_id, $status, $admin_notes = '') {
        if (!in_array($status, ['reviewed', 'resolved'])) {
            throw new Exception('Invalid appeal status');
        }
        $sql = "UPDATE supplier_appeals SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$status, $admin_notes, $appeal_id]);
        $this->logAdminAction('appeal_status_updated', 'supplier_appeals', $appeal_id, [
            'new_status' => $status,
            'admin_notes' => $admin_notes
        ]);
        return true;
    }

    /**
     * Submit supplier appeal
     */
    public function submitAppeal($supplier_id, $appeal_message) {
        $sql = "INSERT INTO supplier_appeals (supplier_id, suspension_reason, appeal_message, status)
                SELECT ?, suspension_reason, ?, 'pending'
                FROM suppliers WHERE id = ? AND status = 'suspended'";
       
        $this->db->query($sql, [$supplier_id, $appeal_message, $supplier_id]);
        return $this->db->lastInsertId();
    }
}
