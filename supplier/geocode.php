<?php
require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$address = trim($_GET['address'] ?? '');
$lat = trim($_GET['lat'] ?? '');
$lng = trim($_GET['lng'] ?? '');

$isReverse = false;
if ($address !== '') {
    $query = http_build_query([
        'format' => 'json',
        'q' => $address,
        'limit' => 1,
        'addressdetails' => 0,
        'countrycodes' => 'ph'
    ]);
    $endpoint = "https://nominatim.openstreetmap.org/search?{$query}";
} elseif ($lat !== '' && $lng !== '') {
    $isReverse = true;
    $query = http_build_query([
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lng,
        'zoom' => 18,
        'addressdetails' => 0
    ]);
    $endpoint = "https://nominatim.openstreetmap.org/reverse?{$query}";
} else {
    respond(['error' => 'Missing parameters'], 400);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "User-Agent: Fingerling Marketplace/1.0\r\n"
    ]
]);

$rawResponse = @file_get_contents($endpoint, false, $context);
if ($rawResponse === false) {
    respond(['error' => 'Unable to reach geocoding service'], 502);
}

$data = json_decode($rawResponse, true);
if ($data === null) {
    respond(['error' => 'Invalid geocoding response'], 502);
}

if ($isReverse) {
    if (empty($data['lat']) || empty($data['lon'])) {
        respond(['error' => 'No location found'], 404);
    }

    respond([
        'lat' => (string) $data['lat'],
        'lng' => (string) $data['lon'],
        'display_name' => $data['display_name'] ?? ''
    ]);
}

if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
    respond(['error' => 'No location found'], 404);
}

$result = $data[0];
respond([
    'lat' => (string) $result['lat'],
    'lng' => (string) $result['lon'],
    'display_name' => $result['display_name'] ?? ''
]);

