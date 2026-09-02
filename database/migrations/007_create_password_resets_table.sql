-- Migration: Create password_resets table
-- Description: Table for storing password reset tokens

CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) PRIMARY KEY,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
