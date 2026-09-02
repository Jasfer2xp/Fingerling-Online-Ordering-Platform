-- Add 'ready_for_delivery' status to orders table ENUM
-- This status represents orders that are prepared and ready to be assigned for delivery

ALTER TABLE orders 
MODIFY COLUMN status ENUM(
    'pending',
    'confirmed', 
    'preparing',
    'ready_for_delivery',
    'out_for_delivery',
    'delivered',
    'cancelled'
) DEFAULT 'pending';