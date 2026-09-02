<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $supplier_id = intval($_GET['supplier_id'] ?? 0);
    
    if (!$supplier_id) {
        echo json_encode(['success' => false, 'message' => 'Supplier ID is required']);
        exit;
    }
    
    // Get products for this supplier
    $sql = "SELECT i.*, sp.name as species_name, sp.scientific_name
            FROM inventory i
            JOIN species sp ON i.species_id = sp.id
            WHERE i.supplier_id = ? AND i.availability_status = 'available'
            ORDER BY sp.name, i.size_category
            LIMIT 10";
    $products = $database->fetchAll($sql, [$supplier_id]);
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch supplier products: ' . $e->getMessage()]);
}