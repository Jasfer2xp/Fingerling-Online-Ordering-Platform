<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $result = $db->fetchAll('DESCRIBE suppliers');
    
    echo "<h2>Suppliers Table Structure</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    
    foreach($result as $row) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check a sample supplier record
    echo "<h2>Sample Supplier Record</h2>";
    $sample = $db->fetch('SELECT * FROM suppliers LIMIT 1');
    if ($sample) {
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
    } else {
        echo "<p>No supplier records found</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<?php
require_once 'config/config.php';

try {
    // Get column information for orders table
    $sql = "SHOW COLUMNS FROM orders";
    $columns = $database->fetchAll($sql);
    
    echo "<h2>Orders Table Structure:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'];
        if ($column['Null'] === 'NO') {
            echo " (NOT NULL)";
        }
        if ($column['Key'] === 'PRI') {
            echo " (PRIMARY KEY)";
        } elseif ($column['Key'] === 'MUL') {
            echo " (INDEX)";
        }
        if ($column['Default'] !== null) {
            echo " [Default: " . $column['Default'] . "]";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Test the query that's causing the error
    echo "<h2>Testing Query:</h2>";
    $test_sql = "SELECT COUNT(*) as total_transactions FROM orders o WHERE o.supplier_id = ? AND o.status = 'delivered' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    echo "<p>Query: " . $test_sql . "</p>";
    
    // Try to execute with a dummy supplier_id
    $result = $database->fetch($test_sql, [1]);
    echo "<p>Query executed successfully. Result: " . print_r($result, true) . "</p>";
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>