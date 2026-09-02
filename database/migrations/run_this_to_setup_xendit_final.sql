-- =====================================================
-- FINAL SETUP SCRIPT FOR XENDIT & NOTIFICATIONS
-- Run this script - it checks if columns exist first
-- =====================================================

-- =====================================================
-- PART 1: XENDIT PAYMENT SUPPORT
-- =====================================================

-- Update payments table to include 'Xendit' payment method
ALTER TABLE payments 
MODIFY COLUMN payment_method ENUM('gcash', 'paypal', 'Xendit') NOT NULL;

-- Add 'confirmed_and_paid' status to orders table
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'confirmed_and_paid', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending';

-- Create xendit_invoices table
CREATE TABLE IF NOT EXISTS xendit_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id VARCHAR(255) UNIQUE NOT NULL,
    invoice_url TEXT NOT NULL,
    xendit_status VARCHAR(50) DEFAULT 'pending',
    expiry DATETIME,
    order_id INT NULL,
    customer_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    external_id VARCHAR(255) UNIQUE,
    payment_method VARCHAR(50) DEFAULT 'Xendit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_external_id (external_id),
    INDEX idx_xendit_status (xendit_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- PART 2: NOTIFICATIONS TABLE UPDATES
-- =====================================================

-- Add customer_id column ONLY if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'customer_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE notifications ADD COLUMN customer_id INT NULL AFTER user_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add link column ONLY if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'link');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE notifications ADD COLUMN link VARCHAR(500) NULL AFTER message',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop foreign key if exists, then add it
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND CONSTRAINT_NAME = 'fk_notifications_customer');
SET @sql = IF(@fk_exists > 0, 
    'ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_customer',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key
ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_customer 
FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;

-- Add index ONLY if it doesn't exist
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'idx_customer_id');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE notifications ADD INDEX idx_customer_id (customer_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing notifications
UPDATE notifications n
INNER JOIN users u ON n.user_id = u.id
INNER JOIN customers c ON u.id = c.user_id
SET n.customer_id = c.id
WHERE n.customer_id IS NULL AND u.user_type = 'customer';

-- =====================================================
-- DONE!
-- =====================================================

