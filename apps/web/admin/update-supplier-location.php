<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Validate required fields
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Optional address fields from frontend
    $purok = filter_input(INPUT_POST, 'purok', FILTER_SANITIZE_STRING);
    $barangay = filter_input(INPUT_POST, 'barangay', FILTER_SANITIZE_STRING);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
    $province = filter_input(INPUT_POST, 'province', FILTER_SANITIZE_STRING);
    $full_address = filter_input(INPUT_POST, 'full_address', FILTER_SANITIZE_STRING);
    
    if (!$supplier_id || $latitude === false || $longitude === false || $action !== 'update_location') {
        throw new Exception('Invalid or missing required fields');
    }
    
    // Validate coordinate ranges
    if ($latitude < -90 || $latitude > 90) {
        throw new Exception('Invalid latitude. Must be between -90 and 90');
    }
    
    if ($longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid longitude. Must be between -180 and 180');
    }
    
    // Initialize Admin class
    $admin = new Admin($database);

    // Check if supplier exists
    $supplier = $admin->getSupplierById($supplier_id);

    if (!$supplier) {
        throw new Exception('Supplier not found');
    }

    // Store old coordinates for logging
    $old_latitude = $supplier['latitude'];
    $old_longitude = $supplier['longitude'];

    // Perform reverse geocoding to get address details
    $reverse_geocode_result = reverseGeocodeCoordinates($latitude, $longitude);

    $update_fields = ['latitude = ?', 'longitude = ?', 'updated_at = NOW()'];
    $update_params = [$latitude, $longitude];

    // Use frontend-provided address fields if available, otherwise use reverse geocoding
    $address_source = 'none';

    if (!empty($purok) || !empty($barangay) || !empty($city) || !empty($province) || !empty($full_address)) {
        // Use frontend-provided address data
        $address_source = 'frontend';

        if (!empty($purok)) {
            $update_fields[] = 'purok = ?';
            $update_params[] = $purok;
        }

        if (!empty($barangay)) {
            $update_fields[] = 'barangay = ?';
            $update_params[] = $barangay;
        }

        if (!empty($city)) {
            $update_fields[] = 'city = ?';
            $update_params[] = $city;
        }

        if (!empty($province)) {
            $update_fields[] = 'province = ?';
            $update_params[] = $province;
        }

        if (!empty($full_address)) {
            $update_fields[] = 'full_address = ?';
            $update_params[] = $full_address;
        }

    } elseif ($reverse_geocode_result['success']) {
        // Use reverse geocoding results as fallback
        $address_source = 'reverse_geocoding';
        $address_data = $reverse_geocode_result['address'];

        if (!empty($address_data['purok'])) {
            $update_fields[] = 'purok = ?';
            $update_params[] = $address_data['purok'];
        }

        if (!empty($address_data['barangay'])) {
            $update_fields[] = 'barangay = ?';
            $update_params[] = $address_data['barangay'];
        }

        if (!empty($address_data['city'])) {
            $update_fields[] = 'city = ?';
            $update_params[] = $address_data['city'];
        }

        if (!empty($address_data['province'])) {
            $update_fields[] = 'province = ?';
            $update_params[] = $address_data['province'];
        }

        if (!empty($address_data['full_address'])) {
            $update_fields[] = 'full_address = ?';
            $update_params[] = $address_data['full_address'];
        }
    }

    // Add supplier_id to params
    $update_params[] = $supplier_id;

    // Update supplier location and address using database wrapper
    $update_sql = "UPDATE suppliers SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $update_result = $database->query($update_sql, $update_params);

    if (!$update_result) {
        throw new Exception('Failed to update supplier location');
    }

    // Log the location change
    $admin_id = get_user_id();
    $log_details = json_encode([
        'supplier_name' => $supplier['business_name'],
        'old_coordinates' => [
            'latitude' => $old_latitude,
            'longitude' => $old_longitude
        ],
        'new_coordinates' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],
        'updated_by' => $admin_id
    ]);

    // Try to log the activity (if table exists)
    try {
        $log_sql = "INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, created_at) VALUES (?, 'location_update', 'supplier', ?, ?, NOW())";
        $database->query($log_sql, [$admin_id, $supplier_id, $log_details]);
    } catch (Exception $log_error) {
        // Log error but don't fail the main operation
        error_log("Activity logging failed: " . $log_error->getMessage());
    }
    
    // Calculate distance moved (if old coordinates exist)
    $distance_moved = null;
    if ($old_latitude && $old_longitude) {
        $distance_moved = calculateDistance($old_latitude, $old_longitude, $latitude, $longitude);
    }
    
    // Prepare success response
    $response = [
        'success' => true,
        'message' => 'Supplier location updated successfully',
        'data' => [
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier['business_name'],
            'new_coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'distance_moved' => $distance_moved ? round($distance_moved, 2) . ' km' : null,
            'address_source' => $address_source,
            'reverse_geocoding' => $reverse_geocode_result
        ]
    ];
    
    // Set success session message
    $_SESSION['success'] = "Location updated for {$supplier['business_name']}";
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("Update supplier location error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate distance between two coordinates using Haversine formula
 * @param float $lat1 Latitude of first point
 * @param float $lon1 Longitude of first point
 * @param float $lat2 Latitude of second point
 * @param float $lon2 Longitude of second point
 * @return float Distance in kilometers
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Earth's radius in kilometers
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lon / 2) * sin($delta_lon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * Reverse geocode coordinates to get address details using Nominatim API
 */
function reverseGeocodeCoordinates($latitude, $longitude) {
    try {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&addressdetails=1&countrycodes=ph";

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Fingerling Online Ordering Platform Admin/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to reverse geocoding service'];
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['address'])) {
            return ['success' => false, 'error' => 'No address data found for coordinates'];
        }

        $address = $data['address'];

        // Extract address components
        $address_data = [
            'purok' => $address['road'] ?? $address['street'] ?? $address['pedestrian'] ?? '',
            'barangay' => $address['suburb'] ?? $address['neighbourhood'] ?? $address['village'] ?? '',
            'city' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? '',
            'province' => $address['state'] ?? $address['province'] ?? '',
            'country' => $address['country'] ?? '',
            'full_address' => $data['display_name'] ?? '',
            'postcode' => $address['postcode'] ?? ''
        ];

        // Clean up empty values
        $address_data = array_filter($address_data, function($value) {
            return !empty(trim($value));
        });

        return [
            'success' => true,
            'address' => $address_data,
            'raw_data' => $data
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Reverse geocoding error: ' . $e->getMessage()];
    }
}
?>
