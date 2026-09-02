<?php
require_once 'config/config.php';

try {
    // Initialize database if not already done
    if (!isset($database)) {
        $database = new Database();
    }
    
    // Check if there are suppliers with location data
    $sql = "SELECT id, business_name, latitude, longitude, barangay, city, province FROM suppliers WHERE status = 'approved' AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 5";
    $suppliers = $database->fetchAll($sql);
    
    echo "<h2>Suppliers with Location Data</h2>";
    
    if (count($suppliers) > 0) {
        echo "<p>Found " . count($suppliers) . " suppliers with location data:</p>";
        echo "<pre>";
        print_r($suppliers);
        echo "</pre>";
    } else {
        echo "<p>No suppliers with location data found.</p>";
        echo "<p>This explains why the map appears empty - there are no suppliers in the database with latitude and longitude values.</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>