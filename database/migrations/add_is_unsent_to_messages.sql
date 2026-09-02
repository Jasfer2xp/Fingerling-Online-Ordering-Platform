-- Add is_unsent column to messages table
ALTER TABLE messages 
ADD COLUMN is_unsent TINYINT(1) DEFAULT 0;

-- Add index for better performance on is_unsent column
CREATE INDEX idx_messages_unsent ON messages(is_unsent);