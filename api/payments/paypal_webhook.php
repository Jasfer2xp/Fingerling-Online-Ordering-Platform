<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Cart.php';
require_once '../../includes/auto_payment_message.php';

// For PayPal webhooks, we need to get the raw POST data
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();

foreach ($raw_post_array as $keyval) {
    $keyval = explode('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}

// Read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
    $get_magic_quotes_exists = true;
} else {
    $get_magic_quotes_exists = false;
}

foreach ($myPost as $key => $value) {
    if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
        $value = urlencode(stripslashes($value));
    } else {
        $value = urlencode($value);
    }
    $req .= "&$key=$value";
}

// Post IPN data back to PayPal to validate
$paypal_url = (PAYPAL_MODE === 'live') 
    ? 'https://ipnpb.paypal.com/cgi-bin/webscr' 
    : 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';

$ch = curl_init($paypal_url);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

$res = curl_exec($ch);
curl_close($ch);

// Inspect IPN validation result and act accordingly
if (strcmp($res, "VERIFIED") == 0) {
    // The IPN is verified, process the notification
    
    // Check the payment_status is Completed
    if ($_POST['payment_status'] == 'Completed') {
        // Check that txn_id has not been previously processed
        // Check that receiver_email is your Primary PayPal email
        // Check that payment_amount/payment_currency are correct
        
        // Process payment
        $txn_id = $_POST['txn_id'];
        $payment_amount = $_POST['mc_gross'];
        $payer_email = $_POST['payer_email'];
        
        // Log the verified IPN
        error_log("Verified IPN: txn_id=$txn_id");
        
        $orderId = 0;
        if (!empty($_POST['custom'])) {
            $customParts = explode('|', $_POST['custom']);
            $orderId = (int) array_pop($customParts);
        } elseif (!empty($_POST['invoice'])) {
            $orderId = (int) $_POST['invoice'];
        }

        if ($orderId > 0) {
            try {
                $database->query(
                    "UPDATE orders SET payment_method = ? WHERE id = ?",
                    ['PayPal', $orderId]
                );
            } catch (Exception $e) {
                error_log("PayPal IPN: failed to update payment method for order {$orderId} - " . $e->getMessage());
            }
            send_auto_payment_message($database, $orderId);
        }
    }
} else if (strcmp($res, "INVALID") == 0) {
    // Log for manual investigation
    error_log("Invalid IPN: " . $raw_post_data);
}

http_response_code(200);
echo "OK";
?>