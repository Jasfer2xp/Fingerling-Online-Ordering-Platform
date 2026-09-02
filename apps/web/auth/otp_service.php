<?php
// Ensure functions.php is loaded for hash_sensitive_data and verify_sensitive_data
if (!function_exists('hash_sensitive_data')) {
    require_once __DIR__ . '/../config/functions.php';
}

if (!function_exists('dispatch_phone_otp')) {
    /**
     * Send or resend a phone OTP via Semaphore.
     *
     * @param Database $database
     * @param string   $phone
     * @param string   $user_type
     * @param array    $options ['resend' => bool]
     * @return array
     */
    function dispatch_phone_otp($database, $phone, $user_type, array $options = []) {
        $isResend = !empty($options['resend']);
        $phone = preg_replace('/\D/', '', $phone ?? '');

        if (!preg_match('/^09\d{9}$/', $phone)) {
            return ['status' => 'error', 'message' => 'Invalid Philippine mobile number. Must start with 09 and be 11 digits.'];
        }

        if (!in_array($user_type, ['customer', 'supplier'], true)) {
            return ['status' => 'error', 'message' => 'Invalid user type'];
        }

        // Basic rate limiting per phone using session timestamps
        $cooldownSeconds = $isResend ? 60 : 30;
        $sentTimestamps = $_SESSION['otp_sent_times'] ?? [];
        if (isset($sentTimestamps[$phone])) {
            $elapsed = time() - $sentTimestamps[$phone];
            if ($elapsed < $cooldownSeconds) {
                $waitFor = $cooldownSeconds - $elapsed;
                return ['status' => 'error', 'message' => "Please wait {$waitFor}s before requesting another code."];
            }
        }

        // Phone number stored as plain text (not hashed)
        
        try {
            // Clean up stale codes for this phone
            $database->query("DELETE FROM phone_verifications WHERE phone = ? AND (verified = 1 OR expires_at < NOW())", [$phone]);
        } catch (Exception $e) {
            // Table might not have the exact columns; ignore cleanup errors
        }

        try {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $otp = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $hashedOtp = password_hash($otp, PASSWORD_BCRYPT);
        if ($hashedOtp === false) {
            return ['status' => 'error', 'message' => 'Failed to generate secure OTP. Please try again.'];
        }

        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        try {
            $database->query(
                "INSERT INTO phone_verifications (phone, otp, expires_at) VALUES (?, ?, ?)",
                [$phone, $hashedOtp, $expiresAt]
            );
        } catch (Exception $e) {
            error_log("OTP insert error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'System error. Please try again.'];
        }

        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $message = "Your Fingerling OTP: {$otp} (Valid for 5 mins)";
        $orderTag = 'OTP-' . substr($phone, -4);
        $smsSuccess = sendSemaphoreSMS($orderTag, $phone, $message);

        if ($smsSuccess) {
            $_SESSION['pending_phone'] = $phone;
            $_SESSION['pending_otp_sent'] = true;
            $_SESSION['otp_sent_time'] = time();
            $_SESSION['otp_sent_times'][$phone] = time();

            $logMsg = date('Y-m-d H:i:s') . " - OTP " . ($isResend ? 'Resent' : 'Sent') . " - Phone: {$phone}\n";
            file_put_contents($logDir . '/app.log', $logMsg, FILE_APPEND);

            return [
                'status' => 'sent',
                'message' => $isResend ? 'OTP resent successfully.' : 'OTP sent successfully.'
            ];
        }

        $errorMsg = date('Y-m-d H:i:s') . " - OTP Send Error - Phone: {$phone}\n";
        file_put_contents($logDir . '/app.log', $errorMsg, FILE_APPEND);

        return ['status' => 'error', 'message' => 'Failed to send SMS. Please try again.'];
    }
}

