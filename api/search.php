<?php
/**
 * Search API endpoint for customer searches
 * Handles search for suppliers, barangays, species, products, etc.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Supplier.php';
require_once __DIR__ . '/../classes/Product.php';

header('Content-Type: application/json');

try {
    $query = $_GET['q'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'message' => 'Search query is required']);
        exit;
    }
    
    $db = new Database();
    $supplier = new Supplier($db);
    $product = new Product($db);
    
    $results = [];
    
    // Search suppliers by business name
    $suppliers = $supplier->searchSuppliers($query);
    foreach ($suppliers as $s) {
        $results[] = [
            'type' => 'supplier',
            'id' => $s['id'],
            'name' => $s['business_name'],
            'location' => $s['barangay'] . ', ' . $s['city'] . ', ' . $s['province'],
            'rating' => $s['rating']
        ];
    }
    
    // Search barangays
    $barangays = $supplier->searchBarangays($query);
    foreach ($barangays as $b) {
        // Check if this barangay is already in results
        $exists = false;
        foreach ($results as $result) {
            if ($result['type'] === 'barangay' && $result['name'] === $b['barangay']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $results[] = [
                'type' => 'barangay',
                'name' => $b['barangay'],
                'location' => $b['city'] . ', ' . $b['province']
            ];
        }
    }
    
    // Search species
    $species = $product->searchSpecies($query);
    foreach ($species as $s) {
        $results[] = [
            'type' => 'species',
            'id' => $s['id'],
            'name' => $s['name'],
            'scientific_name' => $s['scientific_name']
        ];
    }
    
    // Search products (fingerlings)
    $products = $product->searchProducts($query, 10); // Limit to 10 results
    foreach ($products as $p) {
        // Check if this product is already in results
        $exists = false;
        foreach ($results as $result) {
            if ($result['type'] === 'product' && $result['id'] === $p['id']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $results[] = [
                'type' => 'product',
                'id' => $p['id'],
                'name' => $p['species_name'],
                'supplier' => $p['business_name'],
                'price' => $p['price_per_piece'],
                'size' => $p['size_category'],
                'stock' => $p['stock_quantity']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
}