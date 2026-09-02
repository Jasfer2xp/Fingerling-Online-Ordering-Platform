<?php
require_once __DIR__ . '/../../config/database.php';

echo "Adding is_archived column to conversations table...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if the column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'is_archived'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the is_archived column
        $sql = "ALTER TABLE conversations ADD COLUMN is_archived TINYINT(1) DEFAULT 0";
        $pdo->exec($sql);
        echo "Added is_archived column to conversations table\n";
        
        // Add index for better performance
        $sql = "CREATE INDEX idx_conversations_archived ON conversations(is_archived)";
        $pdo->exec($sql);
        echo "Created index on is_archived column\n";
    } else {
        echo "is_archived column already exists\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>