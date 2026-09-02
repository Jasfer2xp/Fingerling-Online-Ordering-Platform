-- Fingerling Online Ordering Platform Database Schema
-- Created: 2025-08-12

-- Create database
CREATE DATABASE IF NOT EXISTS fingerling_marketplace;
USE fingerling_marketplace;

-- Users table (base table for all user types)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    user_type ENUM('customer', 'supplier', 'admin') NOT NULL,
    google_id VARCHAR(255) UNIQUE NULL,
    status ENUM('active', 'inactive', 'pending', 'rejected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customer profiles
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_initial CHAR(1),
    contact_number VARCHAR(20),
    address TEXT,
    barangay VARCHAR(100),
    city VARCHAR(100) DEFAULT 'Tangub City',
    province VARCHAR(100) DEFAULT 'Misamis Occidental',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    phone VARCHAR(20),
    postal_code VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Supplier profiles
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    business_address TEXT,
    barangay VARCHAR(100),
    city VARCHAR(100),
    province VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    business_permit VARCHAR(255),
    certifications TEXT,
    description TEXT,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supports_truck BOOLEAN DEFAULT TRUE,
    supports_boat BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Admin profiles
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role VARCHAR(100) DEFAULT 'admin',
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Fingerling species
CREATE TABLE species (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    scientific_name VARCHAR(255),
    description TEXT,
    image_url VARCHAR(500),
    category VARCHAR(100),
    category_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Supplier inventory
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    species_id INT NOT NULL,
    size_category VARCHAR(50), -- fingerling, juvenile, adult
    stock_quantity INT NOT NULL DEFAULT 0,
    price_per_piece DECIMAL(10, 2) NOT NULL,
    minimum_order INT DEFAULT 1,
    availability_status ENUM('available', 'out_of_stock', 'discontinued') DEFAULT 'available',
    description TEXT,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_species_size (supplier_id, species_id, size_category)
);

-- Orders
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    supplier_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    delivery_date DATE,
    delivery_worker VARCHAR(255),
    notes TEXT,
    delivery_type ENUM('truck', 'boat') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- Order items
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
);

-- Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('gcash', 'paypal') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_id VARCHAR(255),
    reference_number VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Feedback and ratings
CREATE TABLE feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    supplier_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_feedback (order_id)
);

-- Platform settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('order', 'payment', 'system', 'promotion') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit log
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Announcements table
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info', 'warning', 'urgent') DEFAULT 'info',
    target_audience ENUM('all', 'customers', 'suppliers') DEFAULT 'all',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Refunds table
CREATE TABLE refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    transaction_id VARCHAR(255) UNIQUE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('gcash', 'paymaya', 'bank_transfer', 'cod') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (email, password_hash, user_type, status) VALUES
('admin@fingerling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

INSERT INTO admins (user_id, full_name, role) VALUES
(1, 'System Administrator', 'super_admin');

-- Insert sample species
INSERT INTO species (name, scientific_name, description, category) VALUES
('Tilapia', 'Oreochromis niloticus', 'Fast-growing freshwater fish, ideal for aquaculture', 'Freshwater'),
('Bangus (Milkfish)', 'Chanos chanos', 'Popular marine fish in the Philippines', 'Marine'),
('Catfish', 'Clarias gariepinus', 'Hardy freshwater fish, easy to raise', 'Freshwater'),
('Carp', 'Cyprinus carpio', 'Common freshwater fish for pond culture', 'Freshwater'),
('Grouper', 'Epinephelus spp.', 'High-value marine fish', 'Marine');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'Fingerling Online Ordering Platform', 'Website name'),
('site_email', 'info@fingerling.com', 'Contact email'),
('gcash_enabled', '1', 'Enable GCash payments'),
('paypal_enabled', '1', 'Enable PayPal payments'),
('delivery_radius', '50', 'Maximum delivery radius in kilometers'),
('min_order_amount', '500', 'Minimum order amount in PHP');

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Freshwater Fish', 'Fish species that live in freshwater environments'),
('Marine Fish', 'Fish species that live in saltwater environments'),
('Brackish Fish', 'Fish species that live in brackish water environments');

-- Insert sample announcements
INSERT INTO announcements (title, content, type, target_audience) VALUES
('Welcome to Fingerling Online Ordering Platform', 'Welcome to our platform! We connect fish farmers with quality fingerling suppliers across the Philippines.', 'info', 'all'),
('New Payment Methods Available', 'We now accept GCash and PayMaya for faster transactions!', 'info', 'customers'),
('Supplier Guidelines Updated', 'Please review the updated supplier guidelines for better service quality.', 'warning', 'suppliers');

-- Password reset tokens table
CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) PRIMARY KEY,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
