<?php
/**
 * Test PayPal Credentials
 * Run this file to verify PayPal API credentials are working
 * Access: https://fingerling.shop/test_paypal_credentials.php
 */

require_once 'config/config.php';

// Start session for token storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PayPal Credentials Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PayPal Credentials Test</h1>
        
        <?php
        // Check if credentials are defined
        echo "<h2>1. Configuration Check</h2>";
        
        $has_client_id = defined('PAYPAL_CLIENT_ID') && !empty(PAYPAL_CLIENT_ID);
        $has_client_secret = defined('PAYPAL_CLIENT_SECRET') && !empty(PAYPAL_CLIENT_SECRET);
        $has_mode = defined('PAYPAL_MODE');
        
        if ($has_client_id) {
            echo "<div class='success'>✓ PAYPAL_CLIENT_ID is set (length: " . strlen(PAYPAL_CLIENT_ID) . ")</div>";
        } else {
            echo "<div class='error'>✗ PAYPAL_CLIENT_ID is NOT set or empty</div>";
        }
        
        if ($has_client_secret) {
            echo "<div class='success'>✓ PAYPAL_CLIENT_SECRET is set (length: " . strlen(PAYPAL_CLIENT_SECRET) . ")</div>";
        } else {
            echo "<div class='error'>✗ PAYPAL_CLIENT_SECRET is NOT set or empty</div>";
        }
        
        if ($has_mode) {
            echo "<div class='info'>ℹ PAYPAL_MODE: " . PAYPAL_MODE . "</div>";
        } else {
            echo "<div class='error'>✗ PAYPAL_MODE is NOT set</div>";
        }
        
        if (!$has_client_id || !$has_client_secret) {
            echo "<div class='error'><strong>ERROR:</strong> PayPal credentials are missing. Please check config/config.php</div>";
            exit;
        }
        
        // Test API connection
        echo "<h2>2. API Connection Test</h2>";
        
        require_once 'includes/paypal_helpers.php';
        
        $endpoint = (PAYPAL_MODE === 'live')
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
        
        echo "<div class='info'>Testing connection to: <strong>{$endpoint}</strong></div>";
        
        $ch = curl_init($endpoint . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            echo "<div class='error'><strong>cURL Error:</strong> {$curl_error}</div>";
        }
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                echo "<div class='success'><strong>✓ SUCCESS!</strong> PayPal API connection is working.</div>";
                echo "<div class='info'>Access Token received (length: " . strlen($data['access_token']) . ")</div>";
                echo "<div class='info'>Token expires in: " . ($data['expires_in'] ?? 'N/A') . " seconds</div>";
            } else {
                echo "<div class='error'><strong>✗ FAILED:</strong> No access_token in response</div>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "<div class='error'><strong>✗ FAILED:</strong> HTTP {$http_code}</div>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
            
            // Try to parse error
            $error_data = json_decode($response, true);
            if (isset($error_data['error'])) {
                echo "<div class='error'><strong>PayPal Error:</strong> {$error_data['error']}</div>";
            }
            if (isset($error_data['error_description'])) {
                echo "<div class='error'><strong>Description:</strong> {$error_data['error_description']}</div>";
            }
        }
        
        echo "<h2>3. Recommendations</h2>";
        if ($http_code !== 200) {
            echo "<div class='error'>";
            echo "<strong>Possible Issues:</strong><ul>";
            echo "<li>PayPal Client ID or Secret is incorrect</li>";
            echo "<li>PayPal account is not activated</li>";
            echo "<li>Network/firewall blocking PayPal API</li>";
            echo "<li>PayPal mode (live/sandbox) mismatch with credentials</li>";
            echo "</ul></div>";
        } else {
            echo "<div class='success'>PayPal credentials are working correctly!</div>";
        }
        ?>
    </div>
</body>
</html>

