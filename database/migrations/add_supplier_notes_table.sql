-- Migration to add supplier_notes and customer_notes tables for admin notes
-- Run this script to add the missing notes tables

-- Create supplier_notes table
CREATE TABLE IF NOT EXISTS supplier_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create customer_notes table
CREATE TABLE IF NOT EXISTS customer_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_supplier_notes_supplier_id ON supplier_notes(supplier_id);
CREATE INDEX IF NOT EXISTS idx_supplier_notes_created_at ON supplier_notes(created_at);
CREATE INDEX IF NOT EXISTS idx_customer_notes_customer_id ON customer_notes(customer_id);
CREATE INDEX IF NOT EXISTS idx_customer_notes_created_at ON customer_notes(created_at);
