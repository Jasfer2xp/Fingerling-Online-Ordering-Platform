-- =====================================================
-- MIGRATION: Update Order Status for New Workflow
-- Date: 2025-01-XX
-- Description: Updates orders table to support new workflow statuses
-- =====================================================

-- Step 1: Update the ENUM to include all new workflow statuses
ALTER TABLE orders 
MODIFY COLUMN status ENUM(
    'pending_supplier_confirmation',
    'awaiting_customer_payment',
    'paid_and_processing',
    'confirmed',
    'preparing',
    'out_for_delivery',
    'delivered',
    'canceled_due_to_supplier_timeout',
    'canceled_due_to_payment_timeout',
    'canceled_by_supplier',
    'cancelled',
    -- Legacy statuses (for backward compatibility)
    'pending',
    'confirmed_and_paid',
    'ready_for_delivery'
) DEFAULT 'pending_supplier_confirmation';

-- Step 2: Migrate existing 'pending' orders to 'pending_supplier_confirmation'
UPDATE orders 
SET status = 'pending_supplier_confirmation' 
WHERE status = 'pending';

-- Step 3: Verify the update
-- Run this query to check the results:
-- SELECT status, COUNT(*) as count FROM orders GROUP BY status;

