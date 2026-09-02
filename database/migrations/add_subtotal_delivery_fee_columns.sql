-- Migration: Add subtotal and delivery_fee columns to orders table
-- Date: 2025-09-04
-- Purpose: Fix SQL error "Unknown column 'subtotal' in 'field list'"

USE fingerling_marketplace;

-- Add missing columns to orders table
ALTER TABLE orders 
ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payment_method,
ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal;

-- Update existing orders to calculate subtotal and delivery_fee from total_amount
-- Assuming delivery_fee is typically 50.00 based on checkout.php
UPDATE orders 
SET delivery_fee = 50.00,
    subtotal = total_amount - 50.00
WHERE subtotal = 0.00 AND delivery_fee = 0.00;

-- Verify the columns were added successfully
DESCRIBE orders;

-- Show sample data to verify the update
SELECT id, order_number, subtotal, delivery_fee, total_amount 
FROM orders 
LIMIT 5;