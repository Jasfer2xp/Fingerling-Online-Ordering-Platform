-- =====================================================
-- COMPLETE SETUP SCRIPT FOR XENDIT & NOTIFICATIONS
-- Copy and paste this entire script into phpMyAdmin SQL tab
-- Run this ONCE to set up all required tables
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

-- Check and add customer_id column (ignore error if already exists)
-- If you get "Duplicate column name" error, that's OK - column already exists
ALTER TABLE notifications 
ADD COLUMN customer_id INT NULL AFTER user_id;

-- Check and add link column (ignore error if already exists)
-- If you get "Duplicate column name" error, that's OK - column already exists
ALTER TABLE notifications 
ADD COLUMN link VARCHAR(500) NULL AFTER message;

-- Try to drop foreign key if it exists (ignore error if it doesn't exist)
-- If you get error about foreign key not existing, that's OK - just continue
SET @dbname = DATABASE();
SET @tablename = 'notifications';
SET @constraintname = 'fk_notifications_customer';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (constraint_name = @constraintname)
  ) > 0,
  'ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_customer',
  'SELECT "Foreign key does not exist, skipping"'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Add foreign key constraint
ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_customer 
FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;

-- Add index for customer_id (ignore error if already exists)
ALTER TABLE notifications 
ADD INDEX idx_customer_id (customer_id);

-- Migrate existing notifications: populate customer_id from user_id
UPDATE notifications n
INNER JOIN users u ON n.user_id = u.id
INNER JOIN customers c ON u.id = c.user_id
SET n.customer_id = c.id
WHERE n.customer_id IS NULL AND u.user_type = 'customer';

-- =====================================================
-- DONE! Your database is now ready
-- =====================================================
-- 
-- What was created/updated:
-- 1. xendit_invoices table - stores Xendit payment invoices
-- 2. payments table - now supports 'Xendit' payment method
-- 3. orders table - now has 'confirmed_and_paid' status
-- 4. notifications table - now has customer_id and link columns
-- 
-- =====================================================
