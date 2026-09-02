-- Add permit_expiry column to suppliers table
-- This migration adds the permit expiry date functionality

-- Add permit_expiry column to suppliers table
ALTER TABLE suppliers ADD COLUMN permit_expiry DATE NULL AFTER description;

-- Add index for permit expiry queries
CREATE INDEX idx_suppliers_permit_expiry ON suppliers(permit_expiry);

-- Update existing suppliers with a default future date (optional)
-- UPDATE suppliers SET permit_expiry = DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE permit_expiry IS NULL;

-- Add comment to the column
ALTER TABLE suppliers MODIFY COLUMN permit_expiry DATE NULL COMMENT 'Business permit expiry date';

-- Create a view for expired permits (for admin dashboard)
CREATE OR REPLACE VIEW expired_permits AS
SELECT 
    s.id,
    s.business_name,
    s.owner_name,
    s.contact_number,
    s.permit_expiry,
    s.supplier_status,
    DATEDIFF(s.permit_expiry, CURDATE()) as days_until_expiry,
    CASE 
        WHEN s.permit_expiry < CURDATE() THEN 'expired'
        WHEN DATEDIFF(s.permit_expiry, CURDATE()) <= 30 THEN 'expiring_soon'
        ELSE 'valid'
    END as permit_status
FROM suppliers s
WHERE s.permit_expiry IS NOT NULL
ORDER BY s.permit_expiry ASC;
