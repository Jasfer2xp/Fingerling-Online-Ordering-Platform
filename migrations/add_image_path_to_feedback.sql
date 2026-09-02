-- Add image_path column to feedback table
ALTER TABLE feedback ADD COLUMN image_path VARCHAR(500) NULL AFTER comment;