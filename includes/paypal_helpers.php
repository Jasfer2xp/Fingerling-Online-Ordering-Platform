<?php

// includes/paypal_helpers.php
if (!function_exists('get_paypal_access_token')) {
    function get_paypal_access_token(): string
    {
        if (isset($_SESSION['paypal_token']) && $_SESSION['paypal_expires'] > time()) {
            return $_SESSION['paypal_token'];
        }

        $endpoint = (PAYPAL_MODE === 'live')
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';

        $ch = curl_init($endpoint . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http !== 200) {
            error_log("PayPal Token Error (HTTP $http): " . ($curl_error ?: substr($response, 0, 500)));
            if (!empty($curl_error)) {
                error_log("PayPal cURL Error: " . $curl_error);
            }
            return '';
        }

        $data = json_decode($response, true);
        $token = $data['access_token'] ?? '';
        
        if (empty($token)) {
            error_log("PayPal Token Error: No access_token in response. Response: " . substr($response, 0, 500));
            return '';
        }
        
        $expires = ($data['expires_in'] ?? 3600) - 60;

        $_SESSION['paypal_token'] = $token;
        $_SESSION['paypal_expires'] = time() + $expires;

        return $token;
    }
}

if (!function_exists('authorize_paypal_order')) {
    function authorize_paypal_order(string $order_id): array
    {
        $token = get_paypal_access_token();
        if (empty($token)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $endpoint = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/checkout/orders/{$order_id}/authorize"
            : "https://api.sandbox.paypal.com/v2/checkout/orders/{$order_id}/authorize";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . uniqid('auth_', true)
            ],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("PayPal Authorize cURL Error: $error");
            return ['success' => false, 'message' => 'Connection failed'];
        }

        $data = json_decode($response, true);

        if ($http_code !== 200 && $http_code !== 201) {
            $msg = $data['message'] ?? 'Unknown error';
            error_log("PayPal Authorize Failed [$http_code]: " . json_encode($data));
            return ['success' => false, 'message' => $msg];
        }

        $auth_id = $data['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;

        if (!$auth_id) {
            return ['success' => false, 'message' => 'Authorization ID not found'];
        }

        return [
            'success' => true,
            'authorization_id' => $auth_id,
            'status' => $data['status'] ?? 'AUTHORIZED'
        ];
    }
}

if (!function_exists('capture_paypal_payment')) {
    function capture_paypal_payment(string $order_id): array
    {
        $token = get_paypal_access_token();
        if (empty($token)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        // For PayPal checkout orders, we need to first get the authorization ID
        $endpoint = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/checkout/orders/{$order_id}"
            : "https://api.sandbox.paypal.com/v2/checkout/orders/{$order_id}";

        // First get the order details to extract authorization ID
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            $data = json_decode($response, true);
            $msg = $data['message'] ?? "HTTP $http_code";
            return ['success' => false, 'message' => $msg];
        }

        $data = json_decode($response, true);
        
        // Extract authorization ID from the order details
        $auth_id = $data['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
        
        if (!$auth_id) {
            return ['success' => false, 'message' => 'Authorization ID not found in order'];
        }

        // Now capture the authorization
        $endpoint = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/payments/authorizations/{$auth_id}/capture"
            : "https://api.sandbox.paypal.com/v2/payments/authorizations/{$auth_id}/capture";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . uniqid('capture_', true)
            ],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        if ($http_code !== 201) {
            $data = json_decode($response, true);
            $msg = $data['message'] ?? "HTTP $http_code";
            return ['success' => false, 'message' => $msg];
        }

        $data = json_decode($response, true);
        $capture_id = $data['id'] ?? '';

        return [
            'success' => true,
            'capture_id' => $capture_id,
            'status' => $data['status'] ?? 'COMPLETED',
            'data' => $data
        ];
    }
}

if (!function_exists('capture_paypal_authorization')) {
    function capture_paypal_authorization(string $authorization_id): array
    {
        $token = get_paypal_access_token();
        if (empty($token)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        // Capture the authorization directly
        $endpoint = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/payments/authorizations/{$authorization_id}/capture"
            : "https://api.sandbox.paypal.com/v2/payments/authorizations/{$authorization_id}/capture";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . uniqid('capture_', true)
            ],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        if ($http_code !== 201) {
            $data = json_decode($response, true);
            $msg = $data['message'] ?? "HTTP $http_code";
            return ['success' => false, 'message' => $msg];
        }

        $data = json_decode($response, true);
        $capture_id = $data['id'] ?? '';

        return [
            'success' => true,
            'capture_id' => $capture_id,
            'status' => $data['status'] ?? 'COMPLETED',
            'data' => $data
        ];
    }
}

if (!function_exists('void_paypal_authorization')) {
    function void_paypal_authorization(string $authorization_id): array
    {
        $token = get_paypal_access_token();
        if (empty($token)) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $endpoint = (PAYPAL_MODE === 'live')
            ? "https://api.paypal.com/v2/payments/authorizations/{$authorization_id}/void"
            : "https://api.sandbox.paypal.com/v2/payments/authorizations/{$authorization_id}/void";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . uniqid('void_', true)
            ],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Connection failed'];
        }

        // PayPal returns 204 No Content on success
        if ($http_code === 204 || $http_code === 200) {
            return ['success' => true, 'message' => 'Authorization voided successfully'];
        }

        $data = json_decode($response, true);
        $msg = $data['message'] ?? "HTTP $http_code";
        return ['success' => false, 'message' => $msg];
    }
}
?>