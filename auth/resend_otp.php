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
$normalizedPhone = preg_replace('/\D/', '', $phone);

if (empty($_SESSION['pending_phone']) || $_SESSION['pending_phone'] !== $normalizedPhone) {
    echo json_encode(['status' => 'error', 'message' => 'Please request a new OTP before trying to resend.']);
    exit;
}

$response = dispatch_phone_otp($database, $phone, $user_type, ['resend' => true]);
echo json_encode($response);

