-- Migration: Add Xendit Payment Support
-- Date: 2025-01-XX
-- Description: Adds Xendit payment method support and creates xendit_invoices table

-- Update payments table to include 'Xendit' payment method (capital X)
ALTER TABLE payments 
MODIFY COLUMN payment_method ENUM('gcash', 'paypal', 'Xendit') NOT NULL;

-- Add 'confirmed_and_paid' status to orders table
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'confirmed', 'confirmed_and_paid', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending';

-- Create xendit_invoices table to store Xendit invoice data
CREATE TABLE IF NOT EXISTS xendit_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id VARCHAR(255) UNIQUE NOT NULL,
    invoice_url TEXT NOT NULL,
    xendit_status VARCHAR(50) DEFAULT 'pending',
    expiry DATETIME,
    order_id INT NULL,
    customer_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    external_id VARCHAR(255) UNIQUE,
    payment_method VARCHAR(50) DEFAULT 'Xendit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_external_id (external_id),
    INDEX idx_xendit_status (xendit_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

