<?php
// Migration to update payments table and create pending_orders table

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../config/database.php';

try {
    // Add authorization_id column to payments table (if not exists)
    try {
        $sql = "ALTER TABLE payments ADD COLUMN authorization_id VARCHAR(255) NULL AFTER transaction_id";
        $database->query($sql);
        echo "Added authorization_id column to payments table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "authorization_id column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add captured_at column to payments table (if not exists)
    try {
        $sql = "ALTER TABLE payments ADD COLUMN captured_at TIMESTAMP NULL AFTER payment_date";
        $database->query($sql);
        echo "Added captured_at column to payments table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "captured_at column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add voided_at column to payments table (if not exists)
    try {
        $sql = "ALTER TABLE payments ADD COLUMN voided_at TIMESTAMP NULL AFTER captured_at";
        $database->query($sql);
        echo "Added voided_at column to payments table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "voided_at column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Update status enum values in payments table
    try {
        $sql = "ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'refunded', 'authorized', 'captured', 'voided') DEFAULT 'pending'";
        $database->query($sql);
        echo "Updated status column in payments table\n";
    } catch (Exception $e) {
        echo "Warning: Could not update status column - " . $e->getMessage() . "\n";
    }
    
    // Create pending_orders table
    try {
        $sql = "CREATE TABLE IF NOT EXISTS pending_orders (
            id INT(11) NOT NULL AUTO_INCREMENT,
            customer_id INT(11) NOT NULL,
            paypal_order_id VARCHAR(255) NOT NULL,
            authorization_id VARCHAR(255) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            cart_data LONGTEXT NOT NULL,
            status ENUM('pending', 'confirmed', 'cancelled', 'expired') DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL DEFAULT DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 DAY),
            PRIMARY KEY (id),
            UNIQUE KEY unique_paypal_order_id (paypal_order_id),
            UNIQUE KEY unique_authorization_id (authorization_id),
            KEY idx_customer_id (customer_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $database->query($sql);
        echo "Created pending_orders table\n";
    } catch (Exception $e) {
        echo "Error creating pending_orders table: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    echo "Migration completed successfully\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}