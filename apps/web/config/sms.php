<?php

if (!function_exists('sendSemaphoreSMS')) {
    /**
     * Send SMS via Semaphore API
     * @param int $orderId Order ID for logging
     * @param string $customerPhone Customer phone number
     * @param string $message SMS message to send
     * @return bool True on success, false on failure
     */
    function sendSemaphoreSMS($orderId, $customerPhone, $message)
    {
        $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 3);
        $logDir = defined('RUNTIME_PATH') ? RUNTIME_PATH . '/logs' : $projectRoot . '/runtime/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $errorLogFile = $logDir . '/sms_semaphore_errors.log';
        $debugLogFile = $logDir . '/sms_debug.log';

        $apiKey = getenv('SEMAPHORE_API_KEY');
        if (!$apiKey && isset($_ENV['SEMAPHORE_API_KEY'])) {
            $apiKey = $_ENV['SEMAPHORE_API_KEY'];
        }
        
        // Fallback for .env parsing
        if (!$apiKey) {
             $envPath = $projectRoot . '/.env';
             if (file_exists($envPath)) {
                 $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                 foreach ($lines as $line) {
                     if (strpos(trim($line), 'SEMAPHORE_API_KEY') === 0) {
                         $parts = explode('=', $line, 2);
                         if (count($parts) == 2) {
                             $apiKey = trim(trim($parts[1]), '"\'');
                             break;
                         }
                     }
                 }
             }
        }
        
        if (empty($apiKey)) {
            $err = "Missing SEMAPHORE_API_KEY.";
            file_put_contents($errorLogFile, date('c') . " | $orderId | $customerPhone | $err\n", FILE_APPEND);
            return false;
        }

        // Clean phone number
        $cleanPhone = preg_replace('/\D/', '', $customerPhone);
        
        // Normalize to 11 digits (09...) or 12 digits (639...)
        // Semaphore usually expects 09... or 639...
        // Format: 09171234567 (11) -> 639171234567 (12)
        
        if (strlen($cleanPhone) === 11 && strpos($cleanPhone, '0') === 0) {
            // 0917... -> 63917...
            $phone = '63' . substr($cleanPhone, 1);
        } elseif (strlen($cleanPhone) === 10 && strpos($cleanPhone, '9') === 0) {
            // 917... -> 63917...
            $phone = '63' . $cleanPhone;
        } elseif (strlen($cleanPhone) === 12 && strpos($cleanPhone, '63') === 0) {
            // 63917... -> keep
            $phone = $cleanPhone;
        } else {
            // Invalid format
            file_put_contents($errorLogFile, date('c') . " | $orderId | $customerPhone | Invalid phone format: $cleanPhone\n", FILE_APPEND);
            return false;
        }

        // Build POST data
        $post = http_build_query([
            'apikey'  => $apiKey,
            'number'  => $phone,
            'message' => $message
        ]);

        $endpoint = "https://api.semaphore.co/api/v4/messages";
        
        // Log Initial Attempt
        file_put_contents($debugLogFile, date('c') . " | Attempting to send to $phone | Key: " . substr($apiKey, 0, 4) . "***\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL             => $endpoint,
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => $post,
            CURLOPT_TIMEOUT         => 5,
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Extensive Logging
        $logEntry = date('c') . " | $orderId | $phone | HTTP $httpCode | Err: $err | Resp: " . substr($response, 0, 100);
        file_put_contents($debugLogFile, $logEntry . "\n", FILE_APPEND);

        if ($err || ($httpCode < 200 || $httpCode >= 300)) {
            file_put_contents($errorLogFile, $logEntry . "\n", FILE_APPEND);
            return false;
        }

        // Verify JSON response
        $data = json_decode($response, true);
        $success = false;
        
        if (is_array($data)) {
            // Semaphore returns array of objects for bulk, or single object?
            // Usually [ { "message_id": 123, ... } ]
            if (isset($data[0]['message_id']) || isset($data['message_id'])) {
                $success = true;
            }
        }

        if (!$success) {
            file_put_contents($errorLogFile, date('c') . " | $orderId | $phone | API Success False | Resp: $response\n", FILE_APPEND);
            return false;
        }
        
        return true;
    }
}

if (!function_exists('sendCustomerRegistrationSMS')) {
    function sendCustomerRegistrationSMS($number, $firstname)
    {
        $message = "FINGERLINGS: Welcome to Fingerlings Online Ordering Platform, {$firstname}! Your account has been successfully registered.";
        return sendSemaphoreSMS(0, $number, $message);
    }
}

if (!function_exists('sendSupplierRegistrationSMS')) {
    function sendSupplierRegistrationSMS($number, $supplier_name)
    {
        $message = "FINGERLINGS: Hello {$supplier_name}, your supplier account has been registered. You may now start receiving orders.";
        return sendSemaphoreSMS(0, $number, $message);
    }
}

if (!function_exists('sendDeliverySuccessSMS')) {
    function sendDeliverySuccessSMS($number, $order_id)
    {
        $message = "FINGERLINGS: Your delivery for order {$order_id} has been successfully completed. Thank you for ordering!";
        return sendSemaphoreSMS($order_id, $number, $message);
    }
}
?>


