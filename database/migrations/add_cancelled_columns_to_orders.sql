-- Add cancelled_at and cancellation_reason columns to orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL DEFAULT NULL;