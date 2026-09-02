<?php
require_once 'config/config.php';

try {
    // Initialize database if not already done
    if (!isset($database)) {
        $database = new Database();
    }
    
    // Check if there are any suppliers
    $sql = "SELECT COUNT(*) as total FROM suppliers";
    $result = $database->fetch($sql);
    echo "<p>Total suppliers: " . $result['total'] . "</p>";
    
    // Check if there are any approved suppliers
    $sql = "SELECT COUNT(*) as total FROM suppliers WHERE status = 'approved'";
    $result = $database->fetch($sql);
    echo "<p>Approved suppliers: " . $result['total'] . "</p>";
    
    // Check if there are any approved suppliers with location data
    $sql = "SELECT COUNT(*) as total FROM suppliers WHERE status = 'approved' AND latitude IS NOT NULL AND longitude IS NOT NULL";
    $result = $database->fetch($sql);
    echo "<p>Approved suppliers with location data: " . $result['total'] . "</p>";
    
    if ($result['total'] > 0) {
        // Get one supplier with location data
        $sql = "SELECT * FROM suppliers WHERE status = 'approved' AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 1";
        $supplier = $database->fetch($sql);
        echo "<p>Sample supplier with location data:</p>";
        echo "<pre>";
        print_r($supplier);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>