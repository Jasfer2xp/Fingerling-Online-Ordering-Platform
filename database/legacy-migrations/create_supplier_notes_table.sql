-- Create supplier_notes table for admin notes about suppliers
CREATE TABLE IF NOT EXISTS supplier_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    admin_id INT NOT NULL,
    note_type ENUM('approval', 'rejection', 'suspension', 'general', 'warning', 'compliance') DEFAULT 'general',
    note_title VARCHAR(255) DEFAULT NULL,
    note_content TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT TRUE COMMENT 'Whether note is internal admin-only or visible to supplier',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('active', 'resolved', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    
    -- Indexes for better performance
    INDEX idx_supplier_notes_supplier_id (supplier_id),
    INDEX idx_supplier_notes_admin_id (admin_id),
    INDEX idx_supplier_notes_type (note_type),
    INDEX idx_supplier_notes_status (status),
    INDEX idx_supplier_notes_created (created_at),
    INDEX idx_supplier_notes_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data for testing
INSERT INTO supplier_notes (supplier_id, admin_id, note_type, note_title, note_content, priority) VALUES
(1, 1, 'approval', 'Initial Approval', 'Supplier approved after document verification. All requirements met.', 'medium'),
(1, 1, 'general', 'Business Permit Reminder', 'Business permit expires in 30 days. Reminder sent to supplier.', 'high'),
(2, 1, 'warning', 'Late Delivery Warning', 'Multiple complaints about late deliveries. Monitoring required.', 'high'),
(2, 1, 'compliance', 'Document Update Required', 'Business permit needs to be updated. Expiry date approaching.', 'urgent');
