-- Migration: Add payment_method column to orders table
-- Date: 2025-09-04
-- Purpose: Fix SQL error "Unknown column 'payment_method' in 'field list'"

USE fingerling_marketplace;

-- Add payment_method column to orders table
ALTER TABLE orders 
ADD COLUMN payment_method ENUM('gcash', 'paypal', 'cod') DEFAULT 'cod' 
AFTER delivery_notes;

-- Update existing orders to have 'cod' as default payment method
UPDATE orders 
SET payment_method = 'cod' 
WHERE payment_method IS NULL;

-- Verify the column was added successfully
DESCRIBE orders;