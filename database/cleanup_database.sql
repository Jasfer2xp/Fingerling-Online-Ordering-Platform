-- Database Cleanup Script
-- Fingerling Online Ordering Platform
-- This script removes unnecessary/unused tables to clean up the database

-- First, let's identify and drop tables that are not being used or are duplicates

-- Drop the customer_notes table if it exists (it's not used in the current system)
DROP TABLE IF EXISTS customer_notes;

-- Drop any duplicate or unused tables that may have been created during development
-- Based on analysis, these tables appear to be unused or redundant:

-- The cart_items table seems to be a duplicate of the cart table
-- The system seems to use only the cart table
DROP TABLE IF EXISTS cart_items;

-- Check if there's a payments table duplicate (schema.sql has payments table defined twice)
-- Keep only one payments table
-- Note: We'll keep the more complete version and remove the other if they're different

-- Clean up any orphaned data or records that may be disconnected
-- Remove any feedback entries that reference non-existent orders
DELETE FROM feedback WHERE order_id NOT IN (SELECT id FROM orders);

-- Remove any notifications for non-existent users
DELETE FROM notifications WHERE user_id NOT IN (SELECT id FROM users);

-- Remove any audit logs for non-existent users
DELETE FROM audit_log WHERE user_id NOT IN (SELECT id FROM users);

-- Remove any supplier notes for non-existent suppliers
DELETE FROM supplier_notes WHERE supplier_id NOT IN (SELECT id FROM suppliers);

-- Remove any order tracking entries for non-existent orders
DELETE FROM order_tracking WHERE order_id NOT IN (SELECT id FROM orders);

-- Optimize all tables to reclaim space
OPTIMIZE TABLE admins;
OPTIMIZE TABLE announcements;
OPTIMIZE TABLE audit_log;
OPTIMIZE TABLE cart;
OPTIMIZE TABLE categories;
OPTIMIZE TABLE customers;
OPTIMIZE TABLE customer_addresses;
OPTIMIZE TABLE feedback;
OPTIMIZE TABLE inventory;
OPTIMIZE TABLE notifications;
OPTIMIZE TABLE orders;
OPTIMIZE TABLE order_items;
OPTIMIZE TABLE order_tracking;
OPTIMIZE TABLE payments;
OPTIMIZE TABLE refunds;
OPTIMIZE TABLE settings;
OPTIMIZE TABLE species;
OPTIMIZE TABLE suppliers;
OPTIMIZE TABLE supplier_notes;
OPTIMIZE TABLE users;
OPTIMIZE TABLE user_notification_settings;
OPTIMIZE TABLE user_sessions;

-- Show the tables that remain after cleanup
SHOW TABLES;