-- Migration to add purok field to suppliers table
-- Run this script to add the purok field for more detailed address information

-- Add purok field to suppliers table
ALTER TABLE suppliers 
ADD COLUMN purok VARCHAR(100) AFTER business_address;

-- Update existing records to have empty purok (optional)
UPDATE suppliers SET purok = '' WHERE purok IS NULL;

-- Add index for better search performance on location fields
CREATE INDEX idx_suppliers_location ON suppliers(purok, barangay, city, province);

-- Add comment to document the change
ALTER TABLE suppliers COMMENT = 'Supplier profiles with enhanced location fields including purok';
