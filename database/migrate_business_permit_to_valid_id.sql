-- Migration: Rename business_permit column to valid_id in suppliers table
-- Date: 2025-01-27

ALTER TABLE suppliers CHANGE COLUMN business_permit valid_id VARCHAR(255) DEFAULT NULL;



