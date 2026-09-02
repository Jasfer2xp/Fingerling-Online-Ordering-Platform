<?php
/**
 * Migration: Update order statuses to support new confirmation flow
 * 
 * This migration updates the orders table to include new statuses:
 * - pending_supplier_confirmation: Order created but waiting for supplier confirmation
 * - awaiting_customer_payment: Supplier confirmed, waiting for customer payment
 * - cancelled_supplier_no_response: Supplier didn't confirm within 24 hours
 * - cancelled_customer_failed_to_pay: Customer didn't pay within time limit
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';

try {
    // Update the orders table status enum to include new values
    $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM(
        'pending', 
        'pending_supplier_confirmation', 
        'awaiting_customer_payment', 
        'confirmed', 
        'preparing', 
        'out_for_delivery', 
        'delivered', 
        'cancelled',
        'cancelled_supplier_no_response',
        'cancelled_customer_failed_to_pay'
    ) DEFAULT 'pending'";
    
    $database->query($sql);
    
    echo "Migration completed successfully!\n";
    echo "Orders table updated with new statuses.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}