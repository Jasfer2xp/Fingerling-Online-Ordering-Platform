    /**
     * Get all users with filters
     */
    public function getUsers($filters = [], $limit = null, $offset = 0) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['user_type'])) {
            $where_conditions[] = "u.user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        if (!empty($filters['status'])) {
            // For suppliers, we should use suppliers.status instead of users.status
            if (!empty($filters['user_type']) && $filters['user_type'] === 'supplier') {
                $where_conditions[] = "s.status = ?";
            } else {
                $where_conditions[] = "u.status = ?";
            }
            $params[] = $filters['status'];
        } else if (!empty($filters['user_type']) && $filters['user_type'] === 'supplier') {
            // For suppliers, exclude those with 'inactive' status (incomplete registration)
            $where_conditions[] = "s.status != ?";
            $params[] = 'inactive';
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(u.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR s.business_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT u.*,
                       c.first_name, c.last_name, c.contact_number as customer_contact,
                       s.id as supplier_id, s.business_name, s.owner_name, s.contact_number as supplier_contact, s.status as supplier_status,
                       s.barangay, s.city, s.province, s.description, s.latitude, s.longitude,
                       s.rating, s.total_ratings, s.valid_id, s.permit_expiry, s.certifications,
                       s.business_address, s.purok,
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
                LEFT JOIN admins a ON u.id = a.user_id
                {$where_clause}
                ORDER BY u.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get pending supplier registrations
     */
    public function getPendingSuppliers() {
        $sql = "SELECT s.*, u.email, u.created_at as registration_date
                FROM suppliers s
                JOIN users u ON s.user_id = u.id
                WHERE s.status = 'pending' AND s.status != 'inactive'
                ORDER BY u.created_at ASC";

        return $this->db->fetchAll($sql);
    }