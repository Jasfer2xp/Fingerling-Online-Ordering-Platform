<?php
/**
 * XENDIT HELPER — FINAL & PRODUCTION-READY VERSION
 * 
 * Features:
 *  - No more 100× amount bug
 *  - Uses WEBHOOK (reliable), not redirect
 *  - Clean & secure
 *  - Full customer details
 *  - Proper external_id
 *  - Success page for good UX (optional)
 *  - Failure redirect preserved
 */

if (!function_exists('create_xendit_invoice')) {
    function create_xendit_invoice($amount_in_php, $description, $additional_data = [])
    {
        // === 1. ENSURE AMOUNT IS IN PHP PESOS (e.g. 125.50) ===
        $amount_in_php = (float) number_format((float) $amount_in_php, 2, '.', '');
        if ($amount_in_php <= 0) {
            error_log("XENDIT: Invalid amount passed: $amount_in_php");
            return false;
        }

        // === 2. Generate unique external ID ===
        $external_id = 'ord_' . time() . '_' . substr(uniqid('', true), -6);

        // === 3. Build Payload ===
        $payload = [
            'external_id'          => $external_id,
            'amount'               => $amount_in_php,
            'description'          => $description,
            'currency'             => 'PHP',
            'invoice_duration'     => 86400, // 24 hours
            'failure_redirect_url' => base_url('customer/payment-error.php'),
            'should_send_email'    => false,
            // Optional: Nice success page for user experience
            'success_redirect_url' => base_url('customer/payment-successful.php'), // ← Recommended
            // Webhook is set in Xendit Dashboard — NOT here
        ];

        // Add customer info (highly recommended by Xendit)
        if (!empty($additional_data['payer_email'])) {
            $payload['payer_email'] = $additional_data['payer_email'];
        }

        if (!empty($additional_data['customer'])) {
            $payload['customer'] = $additional_data['customer'];
        }

        // Optional: Add callback URL directly (backup — still set main one in dashboard)
        // $payload['callback_url'] = base_url('customer/xendit-webhook.php');

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // === 4. Send Request to Xendit ===
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.xendit.co/v2/invoices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(XENDIT_API_KEY . ':'),
                'xendit-verification-token: ' . XENDIT_API_KEY // Optional, but recommended
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // === 5. Error Handling ===
        if ($curl_error) {
            error_log("XENDIT CURL ERROR: $curl_error");
            return false;
        }

        if (!in_array($http_code, [200, 201])) {
            error_log("XENDIT HTTP ERROR ($http_code): $response");
            return false;
        }

        $result = json_decode($response, true);

        if (empty($result['id']) || empty($result['invoice_url'])) {
            error_log("XENDIT BAD RESPONSE: " . json_encode($result));
            return false;
        }

        // Attach external_id for your records
        $result['external_id'] = $external_id;

        return $result;
    }
}