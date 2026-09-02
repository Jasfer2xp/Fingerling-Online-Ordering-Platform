<?php

/**
 * Cart Class
 * Handles shopping cart functionality
 */

class Cart {
    private $db;
    private $customer_id;

    public function __construct($database, $customer_id) {
        $this->db = $database;
        $this->customer_id = $customer_id;
        $this->createCartTable();
    }

    /**
     * Create cart table if it doesn't exist
     */
    private function createCartTable() {
        $create_cart_table = "CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price_per_piece DECIMAL(10,2) NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
            UNIQUE KEY unique_customer_product (customer_id, product_id)
        )";

        try {
            $this->db->query($create_cart_table);
        } catch (Exception $e) {
            // Table might already exist
        }
    }

    /**
     * Add item to cart
     */
    public function addItem($product_id, $quantity = 1) {
        // Check if product exists and is available
        $product = $this->getProductInfo($product_id);
        if (!$product) {
            throw new Exception('Product not found or unavailable');
        }

        // Check stock availability
        if ($quantity > $product['stock_quantity']) {
            throw new Exception('Insufficient stock available');
        }

        if ($quantity < $product['minimum_order']) {
            throw new Exception("Minimum order quantity is {$product['minimum_order']}");
        }

        // Check if item already exists in cart
        $existing = $this->getCartItem($product_id);

        if ($existing) {
            // Update quantity
            $new_quantity = $existing['quantity'] + $quantity;
            if ($new_quantity > $product['stock_quantity']) {
                throw new Exception('Total quantity exceeds available stock');
            }

            $sql = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE customer_id = ? AND product_id = ?";
            $this->db->query($sql, [$new_quantity, $this->customer_id, $product_id]);
        } else {
            // Add new item
            $sql = "INSERT INTO cart (customer_id, product_id, quantity, price_per_piece)
                    VALUES (?, ?, ?, ?)";
            $this->db->query($sql, [$this->customer_id, $product_id, $quantity, $product['price_per_piece']]);
        }

        return true;
    }
    
    /**
     * Update item quantity
     */
    public function updateQuantity($product_id, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($product_id);
        }
        
        $product = $this->getProductInfo($product_id);
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if ($quantity > $product['stock_quantity']) {
            throw new Exception('Quantity exceeds available stock');
        }
        
        if ($quantity < $product['minimum_order']) {
            throw new Exception("Minimum order quantity is {$product['minimum_order']}");
        }
        
        $sql = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP
                WHERE customer_id = ? AND product_id = ?";
        return $this->db->query($sql, [$quantity, $this->customer_id, $product_id]);
    }
    
    /**
     * Remove item from cart
     */
    public function removeItem($product_id) {
        $sql = "DELETE FROM cart WHERE customer_id = ? AND product_id = ?";
        return $this->db->query($sql, [$this->customer_id, $product_id]);
    }
    
    /**
     * Get cart items
     */
    public function getItems() {
        $sql = "SELECT ci.*, i.price_per_piece as current_price, i.stock_quantity, i.minimum_order, i.image_path,
                       sp.name as species_name, sp.scientific_name, sp.image_url, sp.category,
                       s.business_name, s.barangay, s.city, s.id as supplier_id,
                       (ci.quantity * ci.price_per_piece) as subtotal
                FROM cart ci
                JOIN inventory i ON ci.product_id = i.id
                JOIN species sp ON i.species_id = sp.id
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE ci.customer_id = ? AND i.availability_status = 'available' AND s.status = 'approved'
                ORDER BY ci.added_at DESC";
        
        return $this->db->fetchAll($sql, [$this->customer_id]);
    }
    
    /**
     * Get cart items grouped by supplier
     */
    public function getItemsBySupplier() {
        $items = $this->getItems();
        $grouped = [];
        
        foreach ($items as $item) {
            $supplier_id = $item['supplier_id'];
            if (!isset($grouped[$supplier_id])) {
                $grouped[$supplier_id] = [
                    'supplier_info' => [
                        'id' => $supplier_id,
                        'business_name' => $item['business_name'],
                        'location' => $item['barangay'] . ', ' . $item['city']
                    ],
                    'items' => [],
                    'total' => 0
                ];
            }
            
            $grouped[$supplier_id]['items'][] = $item;
            $grouped[$supplier_id]['total'] += $item['subtotal'];
        }
        
        return $grouped;
    }
    
    /**
     * Get cart count
     */
    public function getItemCount() {
        $sql = "SELECT COUNT(*) as count FROM cart WHERE customer_id = ?";
        $result = $this->db->fetch($sql, [$this->customer_id]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Get cart total
     */
    public function getTotal() {
        $sql = "SELECT SUM(quantity * price_per_piece) as total FROM cart WHERE customer_id = ?";
        $result = $this->db->fetch($sql, [$this->customer_id]);
        return $result['total'] ?? 0;
    }
    
    /**
     * Clear cart
     */
    public function clear() {
        $sql = "DELETE FROM cart WHERE customer_id = ?";
        return $this->db->query($sql, [$this->customer_id]);
    }
    
    /**
     * Clear cart for specific supplier
     */
    public function clearSupplierItems($supplier_id) {
        $sql = "DELETE ci FROM cart ci
                JOIN inventory i ON ci.product_id = i.id
                WHERE ci.customer_id = ? AND i.supplier_id = ?";
        return $this->db->query($sql, [$this->customer_id, $supplier_id]);
    }
    
    /**
     * Validate cart items
     */
    public function validateItems() {
        $items = $this->getItems();
        $issues = [];
        
        foreach ($items as $item) {
            // Check if price changed
            if ($item['price_per_piece'] != $item['current_price']) {
                $issues[] = [
                    'type' => 'price_change',
                    'product_id' => $item['product_id'],
                    'species_name' => $item['species_name'],
                    'old_price' => $item['price_per_piece'],
                    'new_price' => $item['current_price']
                ];
            }
            
            // Check stock availability
            if ($item['quantity'] > $item['stock_quantity']) {
                $issues[] = [
                    'type' => 'insufficient_stock',
                    'product_id' => $item['product_id'],
                    'species_name' => $item['species_name'],
                    'requested' => $item['quantity'],
                    'available' => $item['stock_quantity']
                ];
            }
            
            // Check minimum order
            if ($item['quantity'] < $item['minimum_order']) {
                $issues[] = [
                    'type' => 'below_minimum',
                    'product_id' => $item['product_id'],
                    'species_name' => $item['species_name'],
                    'quantity' => $item['quantity'],
                    'minimum' => $item['minimum_order']
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Update cart prices
     */
    public function updatePrices() {
        $sql = "UPDATE cart ci
                JOIN inventory i ON ci.product_id = i.id
                SET ci.price_per_piece = i.price_per_piece
                WHERE ci.customer_id = ?";
        return $this->db->query($sql, [$this->customer_id]);
    }
    
    /**
     * Get cart item
     */
    private function getCartItem($product_id) {
        $sql = "SELECT * FROM cart WHERE customer_id = ? AND product_id = ?";
        return $this->db->fetch($sql, [$this->customer_id, $product_id]);
    }
    
    /**
     * Get product info
     */
    private function getProductInfo($product_id) {
        $sql = "SELECT i.*, s.status as supplier_status, i.stock_quantity as stock_quantity
                FROM inventory i
                JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.id = ? AND i.availability_status = 'available' AND s.status = 'approved'";
        return $this->db->fetch($sql, [$product_id]);
    }
    
    /**
     * Create orders from cart
     */
    public function checkout($delivery_addresses, $notes = []) {
        try {
            $this->db->beginTransaction();
            
            // Validate cart
            $issues = $this->validateItems();
            if (!empty($issues)) {
                throw new Exception('Cart validation failed');
            }
            
            // Get items grouped by supplier
            $suppliers = $this->getItemsBySupplier();
            $order_ids = [];
            
            foreach ($suppliers as $supplier_id => $supplier_data) {
                $delivery_address = $delivery_addresses[$supplier_id] ?? '';
                $order_notes = $notes[$supplier_id] ?? '';
                
                if (empty($delivery_address)) {
                    throw new Exception("Delivery address required for {$supplier_data['supplier_info']['business_name']}");
                }
                
                // Prepare order items
                $order_items = [];
                foreach ($supplier_data['items'] as $item) {
                    $order_items[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity']
                    ];
                }
                
                // Create order
                $order = new Order($this->db);
                $order_id = $order->createOrder(
                    $this->customer_id,
                    $supplier_id,
                    $order_items,
                    $delivery_address,
                    $order_notes
                );
                
                $order_ids[] = $order_id;
                
                // Clear cart items for this supplier
                $this->clearSupplierItems($supplier_id);
            }
            
            $this->db->commit();
            return $order_ids;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get all cart items
     */
    public function getCartItems() {
        $sql = "SELECT c.*, i.species_id, i.size_category, i.price_per_piece as current_price,
                       i.stock_quantity, i.supplier_id, i.availability_status,
                       s.name as species_name, s.scientific_name, s.image_url,
                       sup.business_name, sup.owner_name, sup.city as supplier_city
                FROM cart c
                JOIN inventory i ON c.product_id = i.id
                LEFT JOIN species s ON i.species_id = s.id
                LEFT JOIN suppliers sup ON i.supplier_id = sup.id
                WHERE c.customer_id = ?
                ORDER BY c.added_at DESC";

        return $this->db->fetchAll($sql, [$this->customer_id]);
    }

    /**
     * Clear entire cart
     */
    public function clearCart() {
        $sql = "DELETE FROM cart WHERE customer_id = ?";
        return $this->db->query($sql, [$this->customer_id]);
    }

    /**
     * Get cart items count
     */
    public function getCartCount() {
        $sql = "SELECT SUM(quantity) as count FROM cart WHERE customer_id = ?";
        $result = $this->db->fetch($sql, [$this->customer_id]);
        return $result ? intval($result['count']) : 0;
    }

    /**
     * Get cart total amount
     */
    public function getCartTotal() {
        $sql = "SELECT SUM(quantity * price_per_piece) as total FROM cart WHERE customer_id = ?";
        $result = $this->db->fetch($sql, [$this->customer_id]);
        return $result ? floatval($result['total']) : 0;
    }
}
