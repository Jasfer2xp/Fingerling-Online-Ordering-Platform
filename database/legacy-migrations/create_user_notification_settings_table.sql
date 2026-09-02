-- Create user notification settings table
CREATE TABLE IF NOT EXISTS user_notification_settings (
    user_id INT PRIMARY KEY,
    email_orders BOOLEAN DEFAULT TRUE,
    email_promotions BOOLEAN DEFAULT FALSE,
    sms_orders BOOLEAN DEFAULT FALSE,
    sms_promotions BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);