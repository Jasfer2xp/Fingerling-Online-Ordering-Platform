<?php
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    // Get all approved suppliers with location data
    $sql = "SELECT s.*, 
                   AVG(f.rating) as avg_rating,
                   COUNT(f.id) as total_reviews,
                   COUNT(DISTINCT i.id) as product_count
            FROM suppliers s
            LEFT JOIN feedback f ON s.id = f.supplier_id
            LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
            WHERE s.status = 'approved' AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
            GROUP BY s.id
            ORDER BY s.business_name";
    $suppliers = $database->fetchAll($sql);
    
    // Format the data for the frontend
    $formatted_suppliers = array_map(function($supplier) {
        return [
            'id' => (int)$supplier['id'],
            'business_name' => htmlspecialchars($supplier['business_name']),
            'owner_name' => htmlspecialchars($supplier['owner_name'] ?? ''),
            'contact_number' => htmlspecialchars($supplier['contact_number'] ?? ''),
            'barangay' => htmlspecialchars($supplier['barangay'] ?? ''),
            'city' => htmlspecialchars($supplier['city'] ?? ''),
            'province' => htmlspecialchars($supplier['province'] ?? ''),
            'latitude' => (float)$supplier['latitude'],
            'longitude' => (float)$supplier['longitude'],
            'avg_rating' => $supplier['avg_rating'] ? round((float)$supplier['avg_rating'], 1) : null,
            'total_reviews' => (int)$supplier['total_reviews'],
            'product_count' => (int)$supplier['product_count']
        ];
    }, $suppliers);
    
    echo json_encode($formatted_suppliers);
} catch (Exception $e) {
    http_response_code(500);
    // Add more detailed error logging for debugging
    error_log("Error fetching suppliers map data: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch suppliers data']);
}