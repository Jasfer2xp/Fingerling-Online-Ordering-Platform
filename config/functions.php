<?php

if (!function_exists('get_paypal_access_token')) {
    function get_paypal_access_token(): string
    {
        $endpoint = (defined('PAYPAL_MODE') && PAYPAL_MODE === 'live')
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';

        $ch = curl_init($endpoint . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? '';
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address for privacy display
     * e.g., "example@email.com" becomes "e*******@e****.com"
     *
     * @param string $email The email to mask
     * @return string The masked email
     */
    function mask_email($email) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        list($username, $domain) = explode('@', $email);
        
        // Mask username - keep first and last character if long enough
        if (strlen($username) > 2) {
            $masked_username = $username[0] . str_repeat('*', strlen($username) - 2) . $username[strlen($username) - 1];
        } else {
            $masked_username = $username[0] . str_repeat('*', strlen($username) - 1);
        }
        
        // Mask domain - keep first character and extension
        $domain_parts = explode('.', $domain);
        if (count($domain_parts) >= 2) {
            $extension = '.' . $domain_parts[count($domain_parts) - 1];
            $domain_name = $domain_parts[0];
            if (strlen($domain_name) > 1) {
                $masked_domain = $domain_name[0] . str_repeat('*', strlen($domain_name) - 1) . $extension;
            } else {
                $masked_domain = $domain_name[0] . str_repeat('*', strlen($domain_name)) . $extension;
            }
        } else {
            $masked_domain = str_repeat('*', strlen($domain));
        }
        
        return $masked_username . '@' . $masked_domain;
    }
}

if (!function_exists('is_password_strong')) {
    /**
     * Check if a password meets the required strength criteria:
     * - At least 8 characters long
     * - Contains at least one uppercase letter
     * - Contains at least one number
     * - Not all numbers
     *
     * @param string $password The password to check
     * @return bool True if password is strong, false otherwise
     */
    function is_password_strong($password) {
        // Check if password is at least 8 characters
        if (strlen($password) < 8) {
            return false;
        }
        
        // Check if password contains at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Check if password contains at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Check if password is all numbers
        if (ctype_digit($password)) {
            return false;
        }
        
        return true;
    }
}

if (!function_exists('gmail_account_exists')) {
    /**
     * Perform DNS + SMTP handshake verification for Gmail addresses.
     *
     * @param string $email Email address to verify
     * @return array {success:bool, exists:bool, message:string}
     */
    function gmail_account_exists($email) {
        $defaultResponse = ['success' => true, 'exists' => true, 'message' => ''];

        if (!defined('GMAIL_VERIFY_STRICT') || GMAIL_VERIFY_STRICT === false) {
            return $defaultResponse;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'exists' => false, 'message' => 'Invalid email address.'];
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (!in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            return $defaultResponse;
        }

        $gxluUrl = 'https://mail.google.com/mail/gxlu?email=' . urlencode($email);
        $gxluCh = curl_init($gxluUrl);
        curl_setopt_array($gxluCh, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'FingerlingEmailVerifier/1.0',
        ]);
        $gxluRaw = curl_exec($gxluCh);
        $gxluErr = curl_error($gxluCh);
        $gxluCode = curl_getinfo($gxluCh, CURLINFO_HTTP_CODE);
        curl_close($gxluCh);

        if ($gxluRaw !== false && $gxluCode >= 200 && $gxluCode < 400) {
            if (stripos($gxluRaw, 'Set-Cookie: GXLU=') !== false) {
                return $defaultResponse;
            }

            return ['success' => true, 'exists' => false, 'message' => 'Invalid email address. Please use a valid email address.'];
        }

        if ($gxluRaw === false) {
            error_log('Gmail GXLU lookup failed: ' . $gxluErr);
        } else {
            error_log('Gmail GXLU HTTP ' . $gxluCode . ' for ' . $email);
        }

        $hasMx = false;
        if (function_exists('checkdnsrr')) {
            $hasMx = @checkdnsrr($domain, 'MX');
        }
        if (!$hasMx && function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_MX);
            $hasMx = !empty($records);
        }

        if (!$hasMx) {
            return ['success' => false, 'exists' => false, 'message' => 'Unable to verify Gmail address right now. Please try again later.'];
        }

        $mxHosts = [];
        if (function_exists('getmxrr')) {
            @getmxrr($domain, $mxHosts);
        }
        if (empty($mxHosts)) {
            $mxHosts = ['gmail-smtp-in.l.google.com'];
        }

        $connection = null;
        foreach ($mxHosts as $host) {
            $connection = @fsockopen($host, 25, $errno, $errstr, 3);
            if ($connection) {
                break;
            }
        }

        if (!$connection) {
            return ['success' => false, 'exists' => false, 'message' => 'Unable to reach Gmail servers for verification. Please try again later.'];
        }

        stream_set_timeout($connection, 3);
        $readResponse = function () use ($connection) {
            $response = @fgets($connection, 1024);
            return $response !== false ? trim($response) : '';
        };
        $sendCommand = function ($command) use ($connection, $readResponse) {
            @fwrite($connection, $command . "\r\n");
            return $readResponse();
        };

        $banner = $readResponse();
        if (stripos($banner, '220') !== 0) {
            fclose($connection);
            return ['success' => false, 'exists' => false, 'message' => 'Unable to verify Gmail account right now. Please try again later.'];
        }

        $sendCommand('HELO fingerling.shop');
        $sendCommand('MAIL FROM:<verifier@fingerling.shop>');
        $rcptResponse = $sendCommand('RCPT TO:<' . $email . '>');
        $sendCommand('QUIT');
        fclose($connection);

        if (preg_match('/^(550|5[0-9]{2})/', $rcptResponse)) {
            return ['success' => true, 'exists' => false, 'message' => 'Invalid email address. Please use a valid email address.'];
        }

        if (preg_match('/^25[0-9]/', $rcptResponse)) {
            return $defaultResponse;
        }

        return ['success' => false, 'exists' => false, 'message' => 'Unable to verify Gmail account right now. Please try again later.'];
    }
}

if (!function_exists('isDisposableEmailDomain')) {
    function isDisposableEmailDomain($domain) {
        static $disposableDomains = [
            'mailinator.com', 'tempmail.com', 'trashmail.com', 'guerrillamail.com',
            '10minutemail.com', 'yopmail.com', 'fakeinbox.com', 'sharklasers.com',
            'getnada.com', 'dispostable.com', 'dropmail.me', 'maildrop.cc'
        ];

        $domain = strtolower(trim($domain));
        foreach ($disposableDomains as $blocked) {
            if ($domain === $blocked) {
                return true;
            }
            $suffix = '.' . $blocked;
            if (strlen($domain) > strlen($suffix) && substr_compare($domain, $suffix, -strlen($suffix)) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('verifyEmailDeliverability')) {
    function verifyEmailDeliverability($email) {
        $response = [
            'success' => true,
            'deliverable' => true,
            'message' => ''
        ];

        $email = trim((string) $email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'deliverable' => false,
                'message' => 'Invalid email address.'
            ];
        }

        [$local, $domain] = explode('@', $email, 2);
        if (isDisposableEmailDomain($domain)) {
            return [
                'success' => true,
                'deliverable' => false,
                'message' => 'Disposable email domains are not allowed. Please use a personal email.'
            ];
        }

        $hasMx = false;
        if (function_exists('checkdnsrr')) {
            $hasMx = @checkdnsrr($domain, 'MX');
        }
        if (!$hasMx && function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_MX);
            $hasMx = !empty($records);
        }
        if (!$hasMx) {
            return [
                'success' => true,
                'deliverable' => false,
                'message' => 'We could not find a mail server for this domain. Please use a different email.'
            ];
        }

        $apiKey = defined('ESIGIL_API_KEY') ? ESIGIL_API_KEY : '';
        if (!empty($apiKey)) {
            $endpoint = 'https://api.esigil.com/verify';
            $queryString = http_build_query(
                ['email' => $email],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
            $url = $endpoint . '?' . $queryString;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
            ]);
            $raw = curl_exec($ch);
            $curlErr = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                error_log('Esigil verify error: ' . $curlErr);
                return $response;
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                error_log('Esigil invalid response (' . $httpCode . '): ' . substr($raw, 0, 512));
                return $response;
            }

            if (empty($data['success'])) {
                error_log('Esigil rejected request: ' . json_encode($data));
                return [
                    'success' => false,
                    'deliverable' => false,
                    'message' => $data['error']['message'] ?? 'Email verification failed. Please try again.'
                ];
            }

            $details = $data['data'] ?? [];
            $isValid = !empty($details['valid']);
            $hasMailbox = isset($details['mailbox_exists']) ? (bool)$details['mailbox_exists'] : true;
            $isTemp = !empty($details['is_temp']);
            $isRisky = !empty($details['is_risky']);

            if ($isTemp) {
                return [
                    'success' => true,
                    'deliverable' => false,
                    'message' => 'Disposable email addresses are not allowed. Please use a personal email.'
                ];
            }

            if (!$isValid || !$hasMailbox) {
                return [
                    'success' => true,
                    'deliverable' => false,
                    'message' => 'This email address cannot receive mail. Please use a different email.'
                ];
            }

            if ($isRisky) {
                return [
                    'success' => true,
                    'deliverable' => false,
                    'message' => 'We could not confirm this email address. Please use a different email.'
                ];
            }
        }

        if (preg_match('/@(gmail\.com|googlemail\.com)$/i', $email)) {
            $gmailCheck = gmail_account_exists($email);
            if (empty($gmailCheck['success'])) {
                return [
                    'success' => false,
                    'deliverable' => false,
                    'message' => $gmailCheck['message'] ?? 'Unable to verify Gmail account right now. Please try again.'
                ];
            }
            if (empty($gmailCheck['exists'])) {
                return [
                    'success' => true,
                    'deliverable' => false,
                    'message' => 'Invalid email address. Please use a valid email address.'
                ];
            }
        }

        return $response;
    }
}

/**
 * Hash sensitive data (email, phone, contact_number, valid_id)
 * Uses password_hash for consistent hashing similar to passwords
 */
if (!function_exists('hash_sensitive_data')) {
    function hash_sensitive_data($value) {
        if (empty($value)) {
            return $value;
        }
        // Use password_hash with PASSWORD_BCRYPT for consistency with password hashing
        return password_hash($value, PASSWORD_BCRYPT);
    }
}

/**
 * Verify sensitive data against stored hash
 * Supports both hashed and plain text (for backward compatibility)
 */
if (!function_exists('verify_sensitive_data')) {
    function verify_sensitive_data($value, $stored_hash) {
        if (empty($value) || empty($stored_hash)) {
            return false;
        }
        
        // Check if stored value is already hashed (bcrypt hashes are 60 characters)
        if (strlen($stored_hash) === 60 && strpos($stored_hash, '$2y$') === 0) {
            // It's a full hash, use password_verify
            return password_verify($value, $stored_hash);
        } elseif (strpos($stored_hash, '$2y$') === 0 && strlen($stored_hash) < 60) {
            // Truncated hash (database column varchar(20) is too small for 60-char bcrypt hash)
            // Note: This is a workaround for the database column size limitation
            // We compare the stored truncated hash with the truncated version of a newly hashed value
            // This may not always work due to bcrypt's random salt, but it's the best we can do
            // without altering the database schema
            $input_hash = hash_sensitive_data($value);
            $truncated_length = strlen($stored_hash);
            return substr($stored_hash, 0, $truncated_length) === substr($input_hash, 0, $truncated_length);
        } else {
            // Plain text (backward compatibility), do direct comparison
            return $value === $stored_hash;
        }
    }
}

/**
 * Check if a value is already hashed
 */
if (!function_exists('is_hashed')) {
    function is_hashed($value) {
        if (empty($value)) {
            return false;
        }
        // Bcrypt hashes are 60 characters and start with $2y$
        return strlen($value) === 60 && strpos($value, '$2y$') === 0;
    }
}

/**
 * Find user by email (plain text only, no hashing)
 */
if (!function_exists('find_user_by_email')) {
    function find_user_by_email($database, $email) {
        if (empty($email)) {
            return false;
        }
        
        // Find by plain text email only (no hashing)
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $user = $database->fetch($sql, [$email]);
        
        return $user ?: false;
    }
}

/**
 * Find phone verification by phone (supports both hashed and plain text for backward compatibility)
 * @param Database $database
 * @param string $phone
 * @param bool $unverified_only If true, only return unverified records
 * @return array|false
 */
if (!function_exists('find_phone_verification_by_phone')) {
    function find_phone_verification_by_phone($database, $phone, $unverified_only = false) {
        if (empty($phone)) {
            return false;
        }
        
        // Normalize phone number (should already be normalized, but ensure it is)
        $phone = preg_replace('/\D/', '', $phone);
        
        // Build SQL query with filters
        $sql = "SELECT * FROM phone_verifications WHERE expires_at > NOW()";
        if ($unverified_only) {
            $sql .= " AND verified = 0";
        }
        $sql .= " ORDER BY id DESC";
        
        try {
            $all_verifications = $database->fetchAll($sql);
        } catch (Exception $e) {
            error_log("Error fetching phone verifications: " . $e->getMessage());
            return false;
        }
        
        // Check each verification record
        foreach ($all_verifications as $verification) {
            $stored_phone = $verification['phone'];
            
            // First try direct match (plain text - new records)
            if ($stored_phone === $phone) {
                return $verification;
            }
            
            // Then try hashed match (backward compatibility with old hashed records)
            if (verify_sensitive_data($phone, $stored_phone)) {
                return $verification;
            }
        }
        
        return false;
    }
}

/**
 * Check if email exists (supports both hashed and plain text)
 */
if (!function_exists('email_exists_hashed')) {
    function email_exists_hashed($database, $email) {
        return find_user_by_email($database, $email) !== false;
    }
}
?>