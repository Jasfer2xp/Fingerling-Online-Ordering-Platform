<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/paypal_helpers.php';

ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['authorization_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$authorization_id = $data['authorization_id'];
$endpoint = (PAYPAL_MODE === 'live')
    ? 'https://api.paypal.com'
    : 'https://api.sandbox.paypal.com';

// Capture the authorized payment
$url = $endpoint . '/v2/payments/authorizations/' . urlencode($authorization_id) . '/capture';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . get_paypal_access_token(),
        'PayPal-Request-Id: ' . uniqid('capture_', true)
    ],
    CURLOPT_TIMEOUT        => 30
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("PayPal Capture Authorization cURL Error: $curlError");
    http_response_code(502);
    exit(json_encode(['success' => false, 'message' => 'Connection failed']));
}

if (strpos($response, '<html') !== false) {
    error_log("PayPal HTML Error: $response");
    http_response_code(502);
    exit(json_encode(['success' => false, 'message' => 'Authentication failed']));
}

$result = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("PayPal Invalid JSON: $response");
    http_response_code(502);
    exit(json_encode(['success' => false, 'message' => 'Invalid response']));
}

if ($httpCode !== 201 || !in_array($result['status'] ?? '', ['COMPLETED'])) {
    $msg = $result['message'] ?? 'HTTP ' . $httpCode;
    error_log("PayPal Capture Authorization Failed: " . json_encode($result));
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Payment capture failed: ' . $msg]));
}

// Return success response
ob_clean();
echo json_encode([
    'success' => true,
    'message' => 'Payment captured successfully',
    'capture_id' => $result['id'],
    'status' => $result['status'],
    'amount' => $result['amount']['value'] ?? 0
]);