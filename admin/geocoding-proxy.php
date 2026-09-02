<?php
/**
 * Geocoding Proxy for Nominatim API
 * Handles CORS issues by making server-side requests
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if this is a valid request
if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode(['error' => 'Query parameter is required']);
    exit;
}

try {
    // Sanitize the query
    $query = trim($_GET['q']);
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $limit = max(1, min(10, $limit)); // Limit between 1 and 10
    
    // Build Nominatim API URL
    $nominatim_url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q' => $query,
        'limit' => $limit,
        'addressdetails' => 1,
        'countrycodes' => 'ph',
        'bounded' => 1,
        'viewbox' => '116.9283,4.5693,126.6043,21.1210' // Philippines bounding box
    ]);
    
    // Set up cURL with proper headers
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $nominatim_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Fingerling Online Ordering Platform/1.0 (admin@fingerlingsmarketplace.com)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('cURL Error: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception('HTTP Error: ' . $http_code);
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON Decode Error: ' . json_last_error_msg());
    }
    
    // Process and clean the results
    $results = [];
    foreach ($data as $item) {
        $results[] = [
            'place_id' => $item['place_id'] ?? '',
            'display_name' => $item['display_name'] ?? '',
            'lat' => floatval($item['lat'] ?? 0),
            'lon' => floatval($item['lon'] ?? 0),
            'address' => [
                'house_number' => $item['address']['house_number'] ?? '',
                'road' => $item['address']['road'] ?? '',
                'suburb' => $item['address']['suburb'] ?? '',
                'village' => $item['address']['village'] ?? '',
                'town' => $item['address']['town'] ?? '',
                'city' => $item['address']['city'] ?? '',
                'municipality' => $item['address']['municipality'] ?? '',
                'province' => $item['address']['province'] ?? '',
                'state' => $item['address']['state'] ?? '',
                'postcode' => $item['address']['postcode'] ?? '',
                'country' => $item['address']['country'] ?? 'Philippines'
            ],
            'importance' => floatval($item['importance'] ?? 0),
            'type' => $item['type'] ?? '',
            'class' => $item['class'] ?? ''
        ];
    }
    
    // Log successful geocoding request
    error_log("Geocoding request successful: {$query} - " . count($results) . " results");
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'query' => $query,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Geocoding error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'query' => $_GET['q'] ?? ''
    ]);
}
?>
