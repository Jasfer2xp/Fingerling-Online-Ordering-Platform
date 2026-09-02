-- Migration: Add customer_id and link columns to notifications table
-- Date: 2025-01-XX
-- Description: Adds customer_id and link columns for Facebook-style notification system

-- Add customer_id column to notifications table
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS customer_id INT NULL AFTER user_id,
ADD COLUMN IF NOT EXISTS link VARCHAR(500) NULL AFTER message;

-- Add foreign key constraint for customer_id
ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_customer 
FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;

-- Add index for customer_id for faster queries
ALTER TABLE notifications 
ADD INDEX idx_customer_id (customer_id);

-- Migrate existing notifications: populate customer_id from user_id
-- This updates notifications for users who are customers
UPDATE notifications n
INNER JOIN users u ON n.user_id = u.id
INNER JOIN customers c ON u.id = c.user_id
SET n.customer_id = c.id
WHERE n.customer_id IS NULL AND u.user_type = 'customer';

