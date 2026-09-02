<?php
require_once '../config/config.php';
require_once '../config/functions.php';
// Note: session_guard removed to allow guest users to verify OTP during registration

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$user_type = $_POST['user_type'] ?? '';

if (!preg_match('/^09\d{9}$/', $phone)) {
    echo json_encode(['status' => 'invalid', 'message' => 'Invalid phone number']);
    exit;
}

if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    echo json_encode(['status' => 'invalid', 'message' => 'OTP must be 6 digits']);
    exit;
}

try {
    $sql = "SELECT * FROM phone_verifications 
            WHERE phone = ? AND verified = 0 
            ORDER BY id DESC LIMIT 1";
    $record = $database->fetch($sql, [$phone]);

    if (!$record) {
        echo json_encode(['status' => 'not_found', 'message' => 'No active OTP found']);
        exit;
    }

    if (strtotime($record['expires_at']) < time()) {
        echo json_encode(['status' => 'expired', 'message' => 'OTP has expired']);
        exit;
    }

    // Verify OTP using password_verify for hashed OTPs
    $otpValid = false;
    $storedOtp = $record['otp'];
    
    // Check if the stored OTP is properly hashed (bcrypt produces 60 character strings)
    if (strlen($storedOtp) === 60) {
        // Hashed OTP - use password_verify
        $otpValid = password_verify($otp, $storedOtp);
    } else {
        // Plain OTP - for backward compatibility only
        // This should not happen with the updated send_otp.php but kept for safety
        $otpValid = ($storedOtp === $otp);
    }
    
    if (!$otpValid) {
        echo json_encode(['status' => 'invalid', 'message' => 'Incorrect OTP']);
        exit;
    }

    // Mark as verified
    $database->query("UPDATE phone_verifications SET verified = 1 WHERE id = ?", [$record['id']]);

    // Store verified phone in session
    $_SESSION['phone_verified'] = true;
    $_SESSION['verified_phone'] = $phone;
    
    // Set OTP verified flag for security (if user is logged in during registration)
    if (isset($_SESSION['user_id'])) {
        $_SESSION['otp_verified'] = true;
        $_SESSION['otp_verified_at'] = time();
        
        // Regenerate session ID after OTP verification for security
        session_regenerate_id(true);
    }

    echo json_encode(['status' => 'verified', 'message' => 'Phone number verified successfully!']);
} catch (Exception $e) {
    error_log("OTP Verify Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Verification failed']);
}