<?php
require_once 'config/config.php';

echo "<h2>Checking for image_path column in inventory table</h2>";

try {
    $columns = $database->fetchAll("SHOW COLUMNS FROM inventory LIKE 'image_path'");
    
    if (empty($columns)) {
        echo "<p style='color: red;'>image_path column does not exist in inventory table</p>";
        echo "<p>You need to run the migration to add this column.</p>";
    } else {
        echo "<p style='color: green;'>image_path column exists in inventory table</p>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    }
    
    // Check a sample product to see if it has image_path data
    $sample_product = $database->fetch("SELECT id, image_path FROM inventory LIMIT 1");
    if ($sample_product) {
        echo "<h3>Sample product data:</h3>";
        echo "<pre>";
        print_r($sample_product);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>