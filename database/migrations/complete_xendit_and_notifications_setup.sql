-- =====================================================
-- COMPLETE SETUP SCRIPT FOR XENDIT & NOTIFICATIONS
-- Run this script in your database to enable all features
-- =====================================================

-- =====================================================
-- PART 1: XENDIT PAYMENT SUPPORT
-- =====================================================

-- Update payments table to include 'Xendit' payment method (capital X)
ALTER TABLE payments 
MODIFY COLUMN payment_method ENUM('gcash', 'paypal', 'Xendit') NOT NULL;

-- Add 'confirmed_and_paid' status to orders table
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'confirmed_and_paid', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending';

-- Create xendit_invoices table to store Xendit invoice data
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

-- Add customer_id column to notifications table (if not exists)
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS customer_id INT NULL AFTER user_id;

-- Add link column to notifications table (if not exists)
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS link VARCHAR(500) NULL AFTER message;

-- Drop existing foreign key if it exists (to avoid errors)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notifications'
    AND CONSTRAINT_NAME = 'fk_notifications_customer'
);

SET @sql = IF(@fk_exists > 0, 
    'ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_customer',
    'SELECT "Foreign key does not exist, skipping drop"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint for customer_id
ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_customer 
FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;

-- Add index for customer_id for faster queries (if not exists)
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notifications'
    AND INDEX_NAME = 'idx_customer_id'
);

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE notifications ADD INDEX idx_customer_id (customer_id)',
    'SELECT "Index already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing notifications: populate customer_id from user_id
-- This updates notifications for users who are customers
UPDATE notifications n
INNER JOIN users u ON n.user_id = u.id
INNER JOIN customers c ON u.id = c.user_id
SET n.customer_id = c.id
WHERE n.customer_id IS NULL AND u.user_type = 'customer';

-- =====================================================
-- VERIFICATION QUERIES (Optional - run to check)
-- =====================================================

-- Check if xendit_invoices table exists
-- SELECT 'xendit_invoices table' AS check_item, COUNT(*) AS exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'xendit_invoices';

-- Check if payments table has Xendit option
-- SHOW COLUMNS FROM payments WHERE Field = 'payment_method';

-- Check if orders table has confirmed_and_paid status
-- SHOW COLUMNS FROM orders WHERE Field = 'status';

-- Check if notifications table has customer_id column
-- SHOW COLUMNS FROM notifications WHERE Field = 'customer_id';

-- =====================================================
-- END OF SCRIPT
-- =====================================================

