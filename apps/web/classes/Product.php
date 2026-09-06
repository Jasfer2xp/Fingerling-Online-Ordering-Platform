<?php

/**
 * Product Class
 * Handles fingerling products and inventory
 */

class Product {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all products (including suspended ones) with supplier info for admin view
     */
    public function getAllProducts($filters = [], $limit = null, $offset = 0) {
        $where_conditions = ["s.status = 'approved'"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['species'])) {
            $where_conditions[] = "sp.id = ?";
            $params[] = $filters['species'];
        }
        
        if (!empty($filters['supplier'])) {
            $where_conditions[] = "s.id = ?";
            $params[] = $filters['supplier'];
        }
        
        if (!empty($filters['barangay'])) {
            $where_conditions[] = "s.barangay = ?";
            $params[] = $filters['barangay'];
        }
        
        if (!empty($filters['city'])) {
            $where_conditions[] = "s.city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['min_price'])) {
            $where_conditions[] = "i.price_per_piece >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $where_conditions[] = "i.price_per_piece <= ?";
            $params[] = $filters['max_price'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(sp.name LIKE ? OR sp.scientific_name LIKE ? OR s.business_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT i.*, i.image_path, i.description as inventory_description, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category, s.business_name, s.barangay, s.city, s.province,
                       s.rating, s.total_ratings, u.id as supplier_user_id
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                JOIN users u ON s.user_id = u.id
                {$where_clause}
                ORDER BY i.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get product by ID with supplier info
     */
    public function getProductById($product_id) {
        $sql = "SELECT i.*, i.image_path, i.description as inventory_description, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category, s.business_name, s.owner_name, s.city, s.barangay
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.id = ? AND i.availability_status = 'available'";
        return $this->db->fetch($sql, [$product_id]);
    }
    
    /**
     * Get product by ID with supplier info (regardless of status) for admin use
     */
    public function getProductByIdAdmin($product_id) {
        $sql = "SELECT i.*, i.image_path, i.description as inventory_description, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category, s.business_name, s.owner_name, s.city, s.barangay, s.user_id as supplier_user_id
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.id = ?";
        return $this->db->fetch($sql, [$product_id]);
    }
    
    /**
     * Get all available products with supplier info
     */
    public function getAvailableProducts($filters = [], $limit = null, $offset = 0) {
        $where_conditions = ["i.availability_status = 'available'", "i.stock_quantity > 0", "s.status = 'approved'"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['species'])) {
            $where_conditions[] = "sp.id = ?";
            $params[] = $filters['species'];
        }
        
        if (!empty($filters['supplier'])) {
            $where_conditions[] = "s.id = ?";
            $params[] = $filters['supplier'];
        }
        
        if (!empty($filters['barangay'])) {
            $where_conditions[] = "s.barangay = ?";
            $params[] = $filters['barangay'];
        }
        
        if (!empty($filters['city'])) {
            $where_conditions[] = "s.city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['min_price'])) {
            $where_conditions[] = "i.price_per_piece >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $where_conditions[] = "i.price_per_piece <= ?";
            $params[] = $filters['max_price'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(sp.name LIKE ? OR sp.scientific_name LIKE ? OR s.business_name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT i.*, i.image_path, i.description as inventory_description, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category, s.business_name, s.barangay, s.city, s.province,
                       s.rating, s.total_ratings
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                {$where_clause}
                ORDER BY i.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get supplier info
     */
    public function getSupplierInfo($supplier_id) {
        $sql = "SELECT s.*, u.email FROM suppliers s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.status = 'approved'";
        return $this->db->fetch($sql, [$supplier_id]);
    }

    /**
     * Get most sold fingerlings (delivered orders with quantity over 1000)
     */
    public function getMostSoldFingerlings($limit = 8, $customer_lat = null, $customer_lng = null) {
        if ($customer_lat !== null && $customer_lng !== null) {
            // Include distance calculation
            $sql = "SELECT 
                        i.id,
                        i.image_path,
                        sp.name as species_name,
                        sp.image_url,
                        s.business_name,
                        s.barangay,
                        s.city,
                        i.price_per_piece,
                        i.stock_quantity,
                        i.minimum_order,
                        i.size_category,
                        s.id as supplier_id,
                        s.latitude,
                        s.longitude,
                        COALESCE(SUM(oi.quantity), 0) as total_sold,
                        AVG(f.rating) as rating,
                        COUNT(f.id) as total_ratings,
                        (6371 * acos(
                            cos(radians(?)) * cos(radians(s.latitude)) * 
                            cos(radians(s.longitude) - radians(?)) + 
                            sin(radians(?)) * sin(radians(s.latitude))
                        )) AS distance_km
                    FROM inventory i
                    JOIN species sp ON i.species_id = sp.id
                    JOIN suppliers s ON i.supplier_id = s.id
                    LEFT JOIN order_items oi ON i.id = oi.inventory_id
                    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
                    LEFT JOIN feedback f ON s.id = f.supplier_id
                    WHERE i.availability_status = 'available'
                    AND s.status = 'approved'
                    AND s.latitude IS NOT NULL 
                    AND s.longitude IS NOT NULL
                    AND i.stock_quantity > 0  -- Added condition to filter out zero stock items
                    GROUP BY i.id, sp.name, s.business_name, s.barangay, s.city, i.price_per_piece, 
                             i.stock_quantity, i.minimum_order, i.size_category, s.id, sp.image_url, i.image_path,
                             s.latitude, s.longitude
                    HAVING COALESCE(SUM(oi.quantity), 0) > 1000
                    ORDER BY total_sold DESC
                    LIMIT ?";
                    
            return $this->db->fetchAll($sql, [$customer_lat, $customer_lng, $customer_lat, $limit]);
        } else {
            // Original query without distance calculation
            $sql = "SELECT 
                        i.id,
                        i.image_path,
                        sp.name as species_name,
                        sp.image_url,
                        s.business_name,
                        s.barangay,
                        s.city,
                        i.price_per_piece,
                        i.stock_quantity,
                        i.minimum_order,
                        i.size_category,
                        s.id as supplier_id,
                        COALESCE(SUM(oi.quantity), 0) as total_sold,
                        AVG(f.rating) as rating,
                        COUNT(f.id) as total_ratings
                    FROM inventory i
                    JOIN species sp ON i.species_id = sp.id
                    JOIN suppliers s ON i.supplier_id = s.id
                    LEFT JOIN order_items oi ON i.id = oi.inventory_id
                    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
                    LEFT JOIN feedback f ON s.id = f.supplier_id
                    WHERE i.availability_status = 'available'
                    AND s.status = 'approved'
                    AND i.stock_quantity > 0  -- Added condition to filter out zero stock items
                    GROUP BY i.id, sp.name, s.business_name, s.barangay, s.city, i.price_per_piece, 
                             i.stock_quantity, i.minimum_order, i.size_category, s.id, sp.image_url, i.image_path
                    HAVING COALESCE(SUM(oi.quantity), 0) > 1000
                    ORDER BY total_sold DESC
                    LIMIT ?";
                    
            return $this->db->fetchAll($sql, [$limit]);
        }
    }
    
    /**
     * Get all species
     */
    public function getAllSpecies() {
        $sql = "SELECT * FROM species WHERE status = 'active' ORDER BY name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Search species by name
     */
    public function searchSpecies($query) {
        $sql = "SELECT * FROM species 
                WHERE (name LIKE ? OR scientific_name LIKE ?) AND status = 'active'
                ORDER BY name";
        $searchTerm = '%' . $query . '%';
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }
    
    /**
     * Get suppliers by location
     */
    public function getSuppliersByLocation() {
        $sql = "SELECT DISTINCT barangay, city, province FROM suppliers 
                WHERE status = 'approved' AND barangay IS NOT NULL 
                ORDER BY province, city, barangay";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get featured products
     */
    public function getFeaturedProducts($limit = 8) {
        $sql = "SELECT i.*, i.image_path, sp.name as species_name, sp.scientific_name, sp.image_url,
                       s.business_name, s.barangay, s.city
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.availability_status = 'available' AND i.stock_quantity > 0 AND s.status = 'approved'
                ORDER BY s.rating DESC, i.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Search products
     */
    public function searchProducts($query, $limit = null) {
        $sql = "SELECT i.*, i.image_path, sp.name as species_name, sp.scientific_name, sp.image_url,
                       s.business_name, s.barangay, s.city, s.province
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.availability_status = 'available' 
                AND i.stock_quantity > 0
                AND s.status = 'approved'
                AND (sp.name LIKE ? OR sp.scientific_name LIKE ? OR s.business_name LIKE ? 
                     OR s.barangay LIKE ? OR s.city LIKE ? OR s.province LIKE ? OR i.size_category LIKE ?)
                ORDER BY i.created_at DESC";
        
        $search_term = '%' . $query . '%';
        $params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get products by supplier
     */
    public function getProductsBySupplier($supplier_id, $limit = null) {
        $sql = "SELECT i.*, i.image_path, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                WHERE i.supplier_id = ? AND i.availability_status = 'available'
                ORDER BY i.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            return $this->db->fetchAll($sql, [$supplier_id, $limit]);
        }
        
        return $this->db->fetchAll($sql, [$supplier_id]);
    }
    
    /**
     * Check product availability
     */
    public function checkAvailability($product_id, $quantity) {
        $sql = "SELECT stock_quantity, minimum_order FROM inventory 
                WHERE id = ? AND availability_status = 'available'";
        $product = $this->db->fetch($sql, [$product_id]);
        
        if (!$product) {
            return ['available' => false, 'message' => 'Product not found or unavailable'];
        }
        
        if ($quantity < $product['minimum_order']) {
            return [
                'available' => false, 
                'message' => "Minimum order quantity is {$product['minimum_order']}"
            ];
        }
        
        if ($quantity > $product['stock_quantity']) {
            return [
                'available' => false, 
                'message' => "Only {$product['stock_quantity']} items available"
            ];
        }
        
        return ['available' => true, 'message' => 'Product available'];
    }
    
    /**
     * Update stock quantity
     */
    public function updateStock($product_id, $quantity_change) {
        $sql = "UPDATE inventory SET stock_quantity = stock_quantity + ?, 
                updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return $this->db->query($sql, [$quantity_change, $product_id]);
    }
    
    /**
     * Get price range
     */
    public function getPriceRange() {
        $sql = "SELECT MIN(price_per_piece) as min_price, MAX(price_per_piece) as max_price 
                FROM inventory i
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.availability_status = 'available' AND s.status = 'approved'";
        return $this->db->fetch($sql);
    }
    
    /**
     * Get product statistics
     */
    public function getProductStats() {
        $sql = "SELECT 
                    COUNT(*) as total_products,
                    COUNT(DISTINCT i.supplier_id) as total_suppliers,
                    COUNT(DISTINCT sp.id) as total_species,
                    SUM(i.stock_quantity) as total_stock
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.availability_status = 'available' AND s.status = 'approved'";
        return $this->db->fetch($sql);
    }
    
    /**
     * Get recent products
     */
    public function getRecentProducts($limit = 8) {
        $sql = "SELECT i.*, i.image_path, sp.name as species_name, sp.scientific_name, sp.description as species_description,
                       sp.image_url, sp.category, s.business_name, s.barangay, s.city, s.province,
                       s.rating, s.total_ratings
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.availability_status = 'available' AND i.stock_quantity > 0 AND s.status = 'approved'
                ORDER BY i.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get top performing products based on sales and views
     */
    public function getTopPerformingProducts($limit = 8, $options = [], $customer_lat = null, $customer_lng = null) {
        $where_conditions = [
            "i.availability_status = 'available'",
            "i.stock_quantity > 0",
            "s.status = 'approved'"
        ];
        $params = [];
        $order_by = "total_sold DESC, i.created_at DESC";
        $distance_select = "";
        $rating_select = "";

        // Always filter for products sold more than 1000 units
        $where_conditions[] = "COALESCE(sales_count.total_sold, 0) > 1000";

        if (!empty($options['min_sold'])) {
            $where_conditions[] = "COALESCE(sales_count.total_sold, 0) >= ?";
            $params[] = $options['min_sold'];
        }

        if (!empty($options['size_category'])) {
            $where_conditions[] = "i.size_category = ?";
            $params[] = $options['size_category'];
        }

        // Add distance calculation if customer coordinates are provided
        if ($customer_lat !== null && $customer_lng !== null) {
            $distance_select = ", (6371 * acos(
                cos(radians(?)) * cos(radians(s.latitude)) * 
                cos(radians(s.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(s.latitude))
            )) AS distance_km";
            $params[] = $customer_lat;
            $params[] = $customer_lng;
            $params[] = $customer_lat;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "SELECT 
                    i.*, 
                    i.image_path, 
                    sp.name as species_name, 
                    sp.scientific_name, 
                    sp.image_url,
                    s.business_name, 
                    s.barangay, 
                    s.city,
                    COALESCE(s.rating, 0) as rating,
                    COALESCE(s.total_ratings, 0) as total_ratings,
                    COALESCE(sales_count.total_sold, 0) as total_sold
                    {$distance_select}
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN (
                    SELECT 
                        inventory_id, 
                        SUM(quantity) as total_sold
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status IN ('delivered', 'out_for_delivery', 'preparing', 'confirmed')
                    GROUP BY inventory_id
                ) sales_count ON i.id = sales_count.inventory_id
                {$where_clause}
                ORDER BY {$order_by}
                LIMIT ?";

        $params[] = $limit;
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Record product view for tracking popularity
     */
    public function recordProductView($product_id) {
        return true;
    }

    /**
     * Get products from nearby suppliers based on customer location
     */
    public function getNearbyProducts($lat, $lng, $radius = 50, $limit = 12) {
        $sql = "
            SELECT * FROM (
                SELECT 
                    i.id,
                    sp.name as species_name,
                    sp.image_url,
                    i.image_path,
                    s.business_name,
                    s.barangay,
                    s.city,
                    s.province,
                    s.rating,
                    s.total_ratings,
                    COALESCE(sales_count.total_sold, 0) as total_sold,
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(s.latitude)) * 
                        cos(radians(s.longitude) - radians(?)) + 
                        sin(radians(?)) * sin(radians(s.latitude))
                    )) AS distance_km
                FROM inventory i
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN (
                    SELECT 
                        inventory_id, 
                        SUM(quantity) as total_sold
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status IN ('delivered', 'out_for_delivery', 'preparing', 'confirmed')
                    GROUP BY inventory_id
                ) sales_count ON i.id = sales_count.inventory_id
                WHERE i.availability_status = 'available'
                  AND i.stock_quantity > 0
                  AND s.status = 'approved'
                  AND s.latitude IS NOT NULL 
                  AND s.longitude IS NOT NULL
            ) sub
            WHERE distance_km <= ?
            ORDER BY distance_km ASC
            LIMIT ?
        ";

        return $this->db->fetchAll($sql, [
            $lat, $lng, $lat, $radius, $limit
        ]);
    }
}
?>