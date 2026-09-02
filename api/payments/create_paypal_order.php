<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/paypal_helpers.php';

header('Content-Type: application/json');
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['error' => 'Invalid method']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    exit(json_encode(['error' => 'Invalid JSON payload']));
}

$total       = floatval($data['total'] ?? 0); // Already in PHP (pesos)
$description = $data['description'] ?? 'Order';

if ($total <= 0) {
    exit(json_encode(['error' => 'Invalid total amount']));
}

// DO NOT divide by 100 — total is already in PHP
$amount_pesos = number_format($total, 2, '.', '');

$payload = [
    'intent' => 'AUTHORIZE',
    'purchase_units' => [[
        'amount' => [
            'currency_code' => 'PHP',
            'value'         => $amount_pesos
        ],
        'description' => $description
    ]],
    'application_context' => [
        'brand_name'   => APP_NAME,
        'landing_page' => 'NO_PREFERENCE',
        'user_action'  => 'PAY_NOW',
        'return_url'   => base_url('customer/paypal-success.php'), // Ensure this file exists
        'cancel_url'   => base_url('customer/cancel-paypal.php')
    ]
];

$access_token = get_paypal_access_token();
if (empty($access_token)) {
    error_log("PayPal Token Failed");
    exit(json_encode(['error' => 'Authentication failed']));
}

// 7. Set endpoint
$endpoint = (PAYPAL_MODE === 'live')
    ? 'https://api.paypal.com/v2/checkout/orders'
    : 'https://api.sandbox.paypal.com/v2/checkout/orders';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . uniqid()
    ]
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if (!empty($curl_error)) {
    error_log("PayPal cURL Error: " . $curl_error);
    exit(json_encode(['error' => 'Connection error']));
}

$response_data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || $http_code >= 400) {
    $msg = $response_data['message'] ?? 'HTTP ' . $http_code;
    error_log("PayPal Create Order Failed [$http_code]: " . json_encode($response_data));
    exit(json_encode(['error' => 'PayPal error: ' . $msg]));
}

ob_clean();
echo json_encode($response_data);