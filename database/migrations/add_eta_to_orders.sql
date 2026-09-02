ALTER TABLE orders 
ADD COLUMN eta_time VARCHAR(50) DEFAULT NULL COMMENT 'Estimated Time of Arrival (e.g., 8-9AM)' AFTER delivery_worker;