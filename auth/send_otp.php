<?php
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/sms.php';
require_once __DIR__ . '/otp_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$user_type = $_POST['user_type'] ?? 'customer';

$response = dispatch_phone_otp($database, $phone, $user_type);
echo json_encode($response);