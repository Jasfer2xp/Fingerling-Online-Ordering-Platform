-- Create user notification settings table
CREATE TABLE IF NOT EXISTS user_notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_orders BOOLEAN DEFAULT TRUE,
    email_promotions BOOLEAN DEFAULT FALSE,
    sms_orders BOOLEAN DEFAULT FALSE,
    sms_promotions BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- Create customer addresses table
CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) DEFAULT NULL,
    barangay VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Create order tracking table
CREATE TABLE IF NOT EXISTS order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Add tracking_number column to orders table if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(50) UNIQUE,
ADD COLUMN IF NOT EXISTS expected_delivery_date DATE,
ADD COLUMN IF NOT EXISTS delivery_phone VARCHAR(20),
ADD COLUMN IF NOT EXISTS delivery_notes TEXT;

-- Add missing columns to customers table if they don't exist
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20);

-- Create cart table (if not exists from Cart class)
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price_per_piece DECIMAL(10,2) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_product (customer_id, product_id)
);

-- Update existing orders to have tracking numbers if they don't have them
UPDATE orders 
SET tracking_number = CONCAT('FP-', YEAR(created_at), '-', LPAD(id, 6, '0'))
WHERE tracking_number IS NULL OR tracking_number = '';
