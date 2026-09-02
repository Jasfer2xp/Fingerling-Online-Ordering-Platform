-- Migration script to add missing admin tables
-- Run this script to add the missing tables for admin functionality

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
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
CREATE TABLE IF NOT EXISTS refunds (
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
CREATE TABLE IF NOT EXISTS payments (
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

-- Add category_id to species table if it doesn't exist
ALTER TABLE species ADD COLUMN IF NOT EXISTS category_id INT;
ALTER TABLE species ADD CONSTRAINT fk_species_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Insert default categories
INSERT IGNORE INTO categories (name, description) VALUES
('Freshwater Fish', 'Fish species that live in freshwater environments'),
('Marine Fish', 'Fish species that live in saltwater environments'),
('Brackish Fish', 'Fish species that live in brackish water environments');

-- Insert sample announcements
INSERT IGNORE INTO announcements (title, content, type, target_audience) VALUES
('Welcome to Fingerling Online Ordering Platform', 'Welcome to our platform! We connect fish farmers with quality fingerling suppliers across the Philippines.', 'info', 'all'),
('New Payment Methods Available', 'We now accept GCash and PayMaya for faster transactions!', 'info', 'customers'),
('Supplier Guidelines Updated', 'Please review the updated supplier guidelines for better service quality.', 'warning', 'suppliers');

-- Update existing species to link to categories
UPDATE species SET category_id = 1 WHERE category = 'Freshwater';
UPDATE species SET category_id = 2 WHERE category = 'Marine';
UPDATE species SET category_id = 3 WHERE category = 'Brackish';

-- Add missing fields to suppliers table if they don't exist
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS rating DECIMAL(3, 2) DEFAULT 0.00;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS total_ratings INT DEFAULT 0;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8);
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8);
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS description TEXT;

-- Insert sample payment records for existing orders
INSERT IGNORE INTO payments (order_id, transaction_id, amount, payment_method, status)
SELECT 
    id as order_id,
    CONCAT('TXN_', LPAD(id, 8, '0')) as transaction_id,
    total_amount as amount,
    'gcash' as payment_method,
    CASE 
        WHEN status = 'delivered' THEN 'completed'
        WHEN status = 'cancelled' THEN 'failed'
        ELSE 'pending'
    END as status
FROM orders 
WHERE id NOT IN (SELECT order_id FROM payments);
