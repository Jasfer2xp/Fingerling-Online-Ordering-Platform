-- Migration to update size_category column in inventory table
-- Changes size_category from VARCHAR(50) to VARCHAR(20) to accommodate numeric values with units

ALTER TABLE inventory MODIFY size_category VARCHAR(20);