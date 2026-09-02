<?php
require_once 'config/config.php';

try {
    // Get column information for orders table
    $sql = "SHOW COLUMNS FROM orders";
    $columns = $database->fetchAll($sql);
    
    echo "<h2>Orders Table Columns:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . htmlspecialchars($column['Field']) . "</strong> - " . htmlspecialchars($column['Type']);
        if ($column['Null'] === 'NO') {
            echo " (NOT NULL)";
        }
        if ($column['Key'] === 'PRI') {
            echo " (PRIMARY KEY)";
        } elseif ($column['Key'] === 'MUL') {
            echo " (INDEX)";
        }
        if ($column['Default'] !== null) {
            echo " [Default: " . htmlspecialchars($column['Default']) . "]";
        }
        echo "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>