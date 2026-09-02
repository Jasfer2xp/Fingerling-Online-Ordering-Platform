<?php
require_once __DIR__ . '/../../config/database.php';

echo "Creating messaging tables...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Create conversations table
    $sql = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        supplier_id INT NOT NULL,
        order_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    echo "Created conversations table\n";
    
    // Create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "Created messages table\n";
    
    // Add indexes for better performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_conversations_customer ON conversations(customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_conversations_supplier ON conversations(supplier_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id)",
        "CREATE INDEX IF NOT EXISTS idx_messages_read ON messages(is_read)",
        "CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at)"
    ];
    
    foreach ($indexes as $index_sql) {
        $pdo->exec($index_sql);
    }
    echo "Created indexes\n";
    
    echo "Messaging tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}