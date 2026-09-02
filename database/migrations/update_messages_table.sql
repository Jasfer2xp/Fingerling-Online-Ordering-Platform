-- Add updated_at column to messages table
ALTER TABLE messages 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add index for better performance on updated_at column
CREATE INDEX idx_messages_updated ON messages(updated_at);