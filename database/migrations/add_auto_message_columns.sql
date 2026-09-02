-- Add columns for automated payment messages
-- Run this migration to enable auto payment messages

-- Add is_auto column to messages table
ALTER TABLE `messages` 
ADD COLUMN `is_auto` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message`;

-- Add order_id column to messages table
ALTER TABLE `messages` 
ADD COLUMN `order_id` INT NULL AFTER `is_auto`;

-- Add index for better performance when checking for existing auto messages
CREATE INDEX `idx_messages_order_auto` ON `messages` (`order_id`, `is_auto`);

-- Add foreign key constraint (optional, can be removed if causes issues)
-- ALTER TABLE `messages` 
-- ADD CONSTRAINT `fk_messages_order` 
-- FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL;

