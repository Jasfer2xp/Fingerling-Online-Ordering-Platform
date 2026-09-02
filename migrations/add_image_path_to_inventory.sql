-- Migration to add image_path column to inventory table
ALTER TABLE inventory 
ADD COLUMN image_path VARCHAR(500) NULL AFTER availability_status;