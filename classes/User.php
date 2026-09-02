<?php

/**
 * User Class
 * Handles user authentication and management
 * NOW WITH SINGLE SESSION ENFORCEMENT (Nov 2025)
 */

class User {
    private $db;
    private $table = 'users';
    
    public function __construct($database) {
        $this->db = $database;
        $this->ensureEmailVerificationTable();
    }
    
    public function register($email, $password, $user_type, $profile_data = []) {
        try {
            $this->db->beginTransaction();
            
            if ($this->emailExists($email)) {
                throw new Exception('Email already exists');
            }
            
            $password_hash = password_hash($password, HASH_ALGO);
            // Email is stored as plain text (not hashed) for login and verification compatibility
            $status = ($user_type === 'supplier') ? 'inactive' : 'active';
            $sql = "INSERT INTO users (email, password_hash, user_type, status) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->query($sql, [$email, $password_hash, $user_type, $status]);
            
            $user_id = $this->db->lastInsertId();
            
            switch ($user_type) {
                case 'customer':
                    $this->createCustomerProfile($user_id, $profile_data);
                    break;
                case 'supplier':
                    $this->createSupplierProfile($user_id, $profile_data);
                    break;
                case 'admin':
                    $this->createAdminProfile($user_id, $profile_data);
                    break;
            }
            
            $this->db->commit();
            return $user_id;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function login($email, $password) {
        // Find user by plain text email only (no hashing)
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $user = $this->db->fetch($sql, [$email]);
        
        if (!$user) {
            return false;
        }
        
        // Check if user is deleted
        if ($user['status'] === 'deleted') {
            return false;
        }
        
        // Get supplier_id if exists
        $sql = "SELECT id AS supplier_id FROM suppliers WHERE user_id = ? LIMIT 1";
        $supplier = $this->db->fetch($sql, [$user['id']]);
        if ($supplier) {
            $user['supplier_id'] = $supplier['supplier_id'];
        }

        if (empty($user['google_id'])) {
            $sql = "SELECT verified_at FROM user_email_verifications WHERE user_id = ?";
            $verification = $this->db->fetch($sql, [$user['id']]);
            
            if ($verification && empty($verification['verified_at'])) {
                return false;
            }
        }

        if (!empty($user['google_id'])) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Email is already plain text, ensure it's set correctly
            $user['email'] = $email;
            $this->createSession($user);
            return $user;
        }
        
        return false;
    }
    
    public function googleLogin($google_id, $email, $name, $role = 'customer') {
        $sql = "SELECT * FROM users WHERE google_id = ?";
        $user = $this->db->fetch($sql, [$google_id]);
        
        if ($user) {
            $this->createSession($user);
            return $user;
        }
        
        // Find user by plain text email only (no hashing)
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $user = $this->db->fetch($sql, [$email]);
        
        if ($user) {
            $sql = "UPDATE users SET google_id = ? WHERE id = ?";
            $this->db->query($sql, [$google_id, $user['id']]);
            $this->createSession($user);
            return $user;
        }
        
        return false;
    }
    
    public function logout() {
        try {
            if (isset($_SESSION['session_id'])) {
                $this->db->query("DELETE FROM user_sessions WHERE id = ?", [$_SESSION['session_id']]);
            }

            if (isset($_SESSION['user_id'])) {
                $this->db->query("DELETE FROM user_sessions WHERE user_id = ?", [$_SESSION['user_id']]);
                $this->db->query("UPDATE users SET current_session_id = NULL WHERE id = ?", [$_SESSION['user_id']]);
                
                // Try to update remember_token if column exists (ignore if column doesn't exist)
                try {
                    $this->db->query("UPDATE users SET remember_token = NULL WHERE id = ?", [$_SESSION['user_id']]);
                } catch (Exception $e) {
                    // Column doesn't exist or other error - ignore silently
                    // Only log if it's not a column not found error
                    if (strpos($e->getMessage(), 'Unknown column') === false) {
                        error_log('Logout remember_token update error: ' . $e->getMessage());
                    }
                }
            }

        } catch (Exception $e) {
            error_log('Database logout error: ' . $e->getMessage());
        }

        $_SESSION = [];
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    public function emailExists($email) {
        // Check for plain text email only (no hashing)
        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $user = $this->db->fetch($sql, [$email]);
        return $user ? true : false;
    }
    
    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function getUserProfile($user_id) {
        $user = $this->getUserById($user_id);
        if (!$user) return false;
        
        switch ($user['user_type']) {
            case 'customer':
                $sql = "SELECT u.*, c.* FROM users u JOIN customers c ON u.id = c.user_id WHERE u.id = ?";
                break;
            case 'supplier':
                $sql = "SELECT u.*, s.* FROM users u JOIN suppliers s ON u.id = s.user_id WHERE u.id = ?";
                break;
            case 'admin':
                $sql = "SELECT u.*, a.* FROM users u JOIN admins a ON u.id = a.user_id WHERE u.id = ?";
                break;
            default:
                return $user;
        }
        
        return $this->db->fetch($sql, [$user_id]);
    }
    
    public function updateProfile($user_id, $data) {
        $user = $this->getUserById($user_id);
        if (!$user) return false;
        
        try {
            $this->db->beginTransaction();
            
            if (isset($data['email'])) {
                // Email is stored as plain text (not hashed) for login and verification compatibility
                $sql = "UPDATE users SET email = ? WHERE id = ?";
                $this->db->query($sql, [$data['email'], $user_id]);
            }
            
            switch ($user['user_type']) {
                case 'customer':
                    $this->updateCustomerProfile($user_id, $data);
                    break;
                case 'supplier':
                    $this->updateSupplierProfile($user_id, $data);
                    break;
                case 'admin':
                    $this->updateAdminProfile($user_id, $data);
                    break;
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function changePassword($user_id, $old_password, $new_password) {
        $user = $this->getUserById($user_id);
        
        if (!$user || !password_verify($old_password, $user['password_hash'])) {
            return false;
        }
        
        $new_hash = password_hash($new_password, HASH_ALGO);
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $this->db->query($sql, [$new_hash, $user_id]);
        
        return true;
    }

    private function createSession($user) {
        $session_id = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $previous_session_id = null;

        $current = $this->db->fetch("SELECT current_session_id FROM users WHERE id = ? FOR UPDATE", [$user['id']]);

        if ($current && !empty($current['current_session_id'])) {
            $previous_session_id = $current['current_session_id'];
            $this->db->query("DELETE FROM user_sessions WHERE id = ?", [$previous_session_id]);
        }

        $sql = "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            $session_id,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expires_at
        ]);

        $this->db->query("UPDATE users SET current_session_id = ? WHERE id = ?", [$session_id, $user['id']]);

        $_SESSION['session_id'] = $session_id;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        // Email should already be plain text from login() method, but ensure it is
        $_SESSION['email'] = $user['email'];

        if ($previous_session_id) {
            $_SESSION['session_takeover'] = true;
        }
    }

    private function createCustomerProfile($user_id, $data) {
        // Contact number stored as plain text (not hashed)
        $contact_number = $data['contact_number'] ?? '';
        
        $sql = "INSERT INTO customers (user_id, first_name, last_name, contact_number, address, barangay, city, province) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            $user_id,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $contact_number,
            $data['address'] ?? '',
            $data['barangay'] ?? '',
            $data['city'] ?? '',
            $data['province'] ?? ''
        ]);
    }
    
    private function createSupplierProfile($user_id, $data) {
        // Check which column exists: valid_id or business_permit
        $idColumn = $this->getValidIdColumnName();
        $idValue = $data['valid_id'] ?? $data['business_permit'] ?? '';
        
        // Hash valid_id if it's not already hashed (backward compatibility and double-hash prevention)
        // Note: valid_id is only hashed when coming from register.php step 3, not here
        if (!empty($idValue) && !is_hashed($idValue)) {
            $idValue = hash_sensitive_data($idValue);
        }
        
        // Contact number stored as plain text (not hashed)
        $contact_number = $data['contact_number'] ?? '';
        
        // Use backticks for column name to prevent SQL injection (though we validate it)
        $sql = "INSERT INTO suppliers (user_id, business_name, permit_file, permit_expiry, owner_name, contact_number, business_address, purok, barangay, city, province, latitude, longitude, `{$idColumn}`, certifications, description, rating, total_ratings, status, supplier_status, store_name, contact_name, full_address, product_description, pond_photo, logo_url, supports_truck, supports_boat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            $user_id,
            $data['business_name'] ?? '',
            $data['permit_file'] ?? '',
            $data['permit_expiry'] ?? null,
            $data['owner_name'] ?? '',
            $contact_number,
            $data['business_address'] ?? '',
            $data['purok'] ?? '',
            $data['barangay'] ?? '',
            $data['city'] ?? '',
            $data['province'] ?? '',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $idValue,
            $data['certifications'] ?? '',
            $data['description'] ?? '',
            0.00,
            0,
            'inactive',
            $data['supplier_status'] ?? 'inactive',
            $data['store_name'] ?? $data['business_name'] ?? '',
            $data['contact_name'] ?? $data['owner_name'] ?? '',
            $data['full_address'] ?? implode(', ', array_filter([$data['purok'] ?? '', $data['barangay'] ?? '', $data['city'] ?? '', $data['province'] ?? ''])),
            $data['product_description'] ?? $data['description'] ?? '',
            $data['pond_photo'] ?? '',
            $data['logo_url'] ?? '',
            $data['supports_truck'] ?? 1,
            $data['supports_boat'] ?? 0
        ]);
    }
    
    private function getValidIdColumnName() {
        // Check if valid_id column exists, otherwise use business_permit
        // This allows backward compatibility until migration is run
        static $cachedColumnName = null;
        if ($cachedColumnName !== null) {
            return $cachedColumnName;
        }
        
        try {
            $result = $this->db->fetch("SHOW COLUMNS FROM suppliers LIKE 'valid_id'");
            if ($result) {
                $cachedColumnName = 'valid_id';
            } else {
                $cachedColumnName = 'business_permit';
            }
        } catch (Exception $e) {
            // If check fails, default to business_permit for backward compatibility
            $cachedColumnName = 'business_permit';
        }
        return $cachedColumnName;
    }
    
    private function createAdminProfile($user_id, $data) {
        $sql = "INSERT INTO admins (user_id, full_name, role, permissions) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [
            $user_id,
            $data['full_name'] ?? '',
            $data['role'] ?? 'admin',
            json_encode($data['permissions'] ?? [])
        ]);
    }
    
    private function updateCustomerProfile($user_id, $data) {
        $fields = ['first_name', 'last_name', 'contact_number', 'address', 'barangay', 'city', 'province'];
        $updates = [];
        $values = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                // Contact number stored as plain text (not hashed)
                $values[] = $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $values[] = $user_id;
            $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $this->db->query($sql, $values);
        }
    }
    
    private function updateSupplierProfile($user_id, $data) {
        // Get the correct column name (valid_id or business_permit)
        $idColumn = $this->getValidIdColumnName();
        
        $fields = ['business_name', 'owner_name', 'contact_number', 'business_address', 
                   'barangay', 'city', 'province', 'latitude', 'longitude', 
                   'certifications', 'description', 'supports_truck', 'supports_boat',
                   'permit_file', 'permit_expiry', 'purok', 'supplier_status', 'store_name', 
                   'contact_name', 'full_address', 'product_description', 'pond_photo', 'logo_url'];
        $updates = [];
        $values = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "`$field` = ?";
                // Contact number stored as plain text (not hashed)
                $values[] = $data[$field];
            }
        }
        
        // Handle valid_id or business_permit field
        // Note: valid_id is only hashed when coming from register.php step 3, not here
        if (isset($data['valid_id']) || isset($data['business_permit'])) {
            $idValue = $data['valid_id'] ?? $data['business_permit'] ?? '';
            // Only hash if not already hashed (backward compatibility and double-hash prevention)
            if (!empty($idValue) && !is_hashed($idValue)) {
                $idValue = hash_sensitive_data($idValue);
            }
            $updates[] = "`{$idColumn}` = ?";
            $values[] = $idValue;
        }

        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $values[] = $data['status'];
            $updates[] = "supplier_status = ?";
            $values[] = $data['status'];
        }
        
        if (!empty($updates)) {
            $values[] = $user_id;
            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $this->db->query($sql, $values);
            
            if (isset($data['status'])) {
                $user_status = ($data['status'] === 'pending') ? 'pending' : 'active';
                $sql = "UPDATE users SET status = ? WHERE id = ?";
                $this->db->query($sql, [$user_status, $user_id]);
                
                $sql = "UPDATE suppliers SET status = ? WHERE user_id = ?";
                $this->db->query($sql, [$data['status'], $user_id]);
            }
        }
    }
    
    private function updateAdminProfile($user_id, $data) {
        $fields = ['full_name', 'role'];
        $updates = [];
        $values = [];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (isset($data['permissions'])) {
            $updates[] = "permissions = ?";
            $values[] = json_encode($data['permissions']);
        }

        if (!empty($updates)) {
            $values[] = $user_id;
            $sql = "UPDATE admins SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $this->db->query($sql, $values);
        }
    }

    public function verifyPassword($user_id, $password) {
        $user = $this->getUserById($user_id);
        return $user && password_verify($password, $user['password_hash']);
    }

    public function updatePassword($user_id, $new_password) {
        $password_hash = password_hash($new_password, HASH_ALGO);
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $this->db->query($sql, [$password_hash, $user_id]);
    }

    public function getNotificationSettings($user_id) {
        try {
            $sql = "SELECT * FROM user_notification_settings WHERE user_id = ?";
            $settings = $this->db->fetch($sql, [$user_id]);
        } catch (Exception $e) {
            $settings = false;
        }

        if (!$settings) {
            return [
                'email_orders' => true,
                'email_promotions' => false,
                'sms_orders' => false,
                'sms_promotions' => false
            ];
        }

        return [
            'email_orders' => (bool)$settings['email_orders'],
            'email_promotions' => (bool)$settings['email_promotions'],
            'sms_orders' => (bool)$settings['sms_orders'],
            'sms_promotions' => (bool)$settings['sms_promotions']
        ];
    }

    public function updateNotificationSettings($user_id, $settings) {
        try {
            $sql = "INSERT INTO user_notification_settings
                    (user_id, email_orders, email_promotions, sms_orders, sms_promotions)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    email_orders = VALUES(email_orders),
                    email_promotions = VALUES(email_promotions),
                    sms_orders = VALUES(sms_orders),
                    sms_promotions = VALUES(sms_promotions)";

            $this->db->query($sql, [
                $user_id,
                $settings['email_orders'] ? 1 : 0,
                $settings['email_promotions'] ? 1 : 0,
                $settings['sms_orders'] ? 1 : 0,
                $settings['sms_promotions'] ? 1 : 0
            ]);
        } catch (Exception $e) {}
    }

    public function deactivateAccount($user_id) {
        $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
        $this->db->query($sql, [$user_id]);
    }

    public function getCustomerAddresses($customer_id) {
        $sql = "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC";
        return $this->db->fetchAll($sql, [$customer_id]);
    }

    public function addCustomerAddress($address_data) {
        try {
            $this->db->beginTransaction();

            if ($address_data['is_default']) {
                $sql = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?";
                $this->db->query($sql, [$address_data['customer_id']]);
            }

            $sql = "INSERT INTO customer_addresses
                    (customer_id, label, recipient_name, phone, address_line_1, address_line_2,
                     barangay, city, province, postal_code, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->query($sql, [
                $address_data['customer_id'],
                $address_data['label'],
                $address_data['recipient_name'],
                $address_data['phone'],
                $address_data['address_line_1'],
                $address_data['address_line_2'],
                $address_data['barangay'],
                $address_data['city'],
                $address_data['province'],
                $address_data['postal_code'],
                $address_data['is_default'] ? 1 : 0
            ]);

            $this->db->commit();
            return $this->db->lastInsertId();

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateCustomerAddress($address_id, $customer_id, $address_data) {
        try {
            $this->db->beginTransaction();

            $sql = "SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?";
            $address = $this->db->fetch($sql, [$address_id, $customer_id]);
            if (!$address) {
                throw new Exception('Address not found');
            }

            if ($address_data['is_default']) {
                $sql = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ? AND id != ?";
                $this->db->query($sql, [$customer_id, $address_id]);
            }

            $sql = "UPDATE customer_addresses SET
                    label = ?, recipient_name = ?, phone = ?, address_line_1 = ?, address_line_2 = ?,
                    barangay = ?, city = ?, province = ?, postal_code = ?, is_default = ?
                    WHERE id = ? AND customer_id = ?";

            $this->db->query($sql, [
                $address_data['label'],
                $address_data['recipient_name'],
                $address_data['phone'],
                $address_data['address_line_1'],
                $address_data['address_line_2'],
                $address_data['barangay'],
                $address_data['city'],
                $address_data['province'],
                $address_data['postal_code'],
                $address_data['is_default'] ? 1 : 0,
                $address_id,
                $customer_id
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function deleteCustomerAddress($address_id, $customer_id) {
        $sql = "DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?";
        $this->db->query($sql, [$address_id, $customer_id]);
    }

    public function setDefaultAddress($address_id, $customer_id) {
        try {
            $this->db->beginTransaction();

            $sql = "SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?";
            $address = $this->db->fetch($sql, [$address_id, $customer_id]);
            if (!$address) {
                throw new Exception('Address not found');
            }

            $sql = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?";
            $this->db->query($sql, [$customer_id]);

            $sql = "UPDATE customer_addresses SET is_default = 1 WHERE id = ? AND customer_id = ?";
            $this->db->query($sql, [$address_id, $customer_id]);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateStatus($user_id, $status) {
        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            throw new Exception('Invalid status');
        }

        $sql = "UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$status, $user_id]);

        return true;
    }

    public function deleteUser($user_id) {
        try {
            $this->db->beginTransaction();

            $user = $this->getUserById($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }

            $sql = "DELETE FROM users WHERE id = ?";
            $this->db->query($sql, [$user_id]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function ensureEmailVerificationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_email_verifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $this->db->query($sql);
        } catch (Exception $e) {}
    }

    public function createEmailVerification($user_id, $email) {
        $this->ensureEmailVerificationTable();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', time() + 15 * 60);

        $this->db->query("DELETE FROM user_email_verifications WHERE user_id = ?", [$user_id]);
        $this->db->query(
            "INSERT INTO user_email_verifications (user_id, code_hash, expires_at, attempts, verified_at) VALUES (?, ?, ?, 0, NULL)",
            [$user_id, $code_hash, $expires_at]
        );

        require_once __DIR__ . '/../includes/mailer.php';
        $subject = 'Your ' . APP_NAME . ' Verification Code';
        $message = "Hello,\n\nYour email verification code is: {$code}\nThis code will expire in 15 minutes.\n\nIf you did not request this, please ignore this email.\n\nRegards,\n" . FROM_NAME;
        $send = send_app_email($email, $subject, $message, false);
        if (!$send['success']) {
            try { $this->db->query("DELETE FROM user_email_verifications WHERE user_id = ?", [$user_id]); } catch (Exception $e) {}
            error_log('Email send failed: ' . ($send['error'] ?? 'unknown'));
            return ['success' => false, 'error' => $send['error'] ?? 'Failed to send verification code'];
        }

        return ['success' => true];
    }

    public function verifyEmailCode($user_id, $code_input) {
        $this->ensureEmailVerificationTable();
        $rec = $this->db->fetch("SELECT * FROM user_email_verifications WHERE user_id = ?", [$user_id]);
        if (!$rec) {
            return ['success' => false, 'error' => 'Verification not found.'];
        }
        if (!empty($rec['verified_at'])) {
            return ['success' => true];
        }
        if (strtotime($rec['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Verification code has expired.'];
        }
        if ((int)$rec['attempts'] >= 5) {
            return ['success' => false, 'error' => 'Too many failed attempts. Try resending the code.'];
        }

        $valid = password_verify($code_input, $rec['code_hash']);
        if ($valid) {
            $this->db->query("UPDATE user_email_verifications SET verified_at = NOW(), updated_at = NOW() WHERE user_id = ?", [$user_id]);
            return ['success' => true];
        } else {
            $this->db->query("UPDATE user_email_verifications SET attempts = attempts + 1, updated_at = NOW() WHERE user_id = ?", [$user_id]);
            return ['success' => false, 'error' => 'Invalid verification code.'];
        }
    }

    public function isEmailVerified($user_id) {
        $rec = $this->db->fetch("SELECT verified_at FROM user_email_verifications WHERE user_id = ?", [$user_id]);
        return $rec && !empty($rec['verified_at']);
    }

    public function maskEmail($email) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        list($username, $domain) = explode('@', $email);
        
        if (strlen($username) < 3) {
            $maskedUsername = str_repeat('*', strlen($username));
        } else {
            $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . $username[strlen($username) - 1];
        }
        
        return $maskedUsername . '@' . $domain;
    }
    
    public function createEmailChangeVerification($user_id, $new_email) {
        // Check if email exists (plain text only, no hashing)
        $existing_user = find_user_by_email($this->db, $new_email);
        if ($existing_user && $existing_user['id'] != $user_id) {
            return ['success' => false, 'error' => 'This email is already in use'];
        }
        
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', time() + 15 * 60);
        
        $sql = "INSERT INTO user_email_verifications (user_id, code_hash, expires_at, attempts, verified_at) 
                VALUES (?, ?, ?, 0, NULL) 
                ON DUPLICATE KEY UPDATE 
                code_hash = VALUES(code_hash), 
                expires_at = VALUES(expires_at), 
                attempts = 0, 
                verified_at = NULL";
        $this->db->query($sql, [$user_id, $code_hash, $expires_at]);
        
        $_SESSION['email_change_new_email_' . $user_id] = $new_email;
        
        require_once __DIR__ . '/../includes/mailer.php';
        $subject = 'Email Change Verification for ' . APP_NAME;
        $message = "Hello,\n\nYou have requested to change your email address.\n\nYour verification code is: {$code}\nThis code will expire in 15 minutes.\n\nIf you did not request this change, please ignore this email.\n\nRegards,\n" . FROM_NAME;
        $send = send_app_email($new_email, $subject, $message, false);
        if (!$send['success']) {
            try { 
                $this->db->query("DELETE FROM user_email_verifications WHERE user_id = ?", [$user_id]); 
                unset($_SESSION['email_change_new_email_' . $user_id]);
            } catch (Exception $e) {}
            error_log('Email send failed: ' . ($send['error'] ?? 'unknown'));
            return ['success' => false, 'error' => $send['error'] ?? 'Failed to send verification code'];
        }
        
        return ['success' => true];
    }
    
    public function verifyEmailChangeCode($user_id, $code_input) {
        $rec = $this->db->fetch("SELECT * FROM user_email_verifications WHERE user_id = ?", [$user_id]);
        if (!$rec) {
            return ['success' => false, 'error' => 'Verification not found.'];
        }
        if (!empty($rec['verified_at'])) {
            return ['success' => false, 'error' => 'Code already used.'];
        }
        if (strtotime($rec['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Verification code has expired.'];
        }
        if ((int)$rec['attempts'] >= 5) {
            return ['success' => false, 'error' => 'Too many failed attempts. Try requesting a new code.'];
        }
        
        $valid = password_verify($code_input, $rec['code_hash']);
        if ($valid) {
            $this->db->query("UPDATE user_email_verifications SET verified_at = NOW(), updated_at = NOW() WHERE user_id = ?", [$user_id]);
            
            $new_email = $_SESSION['email_change_new_email_' . $user_id] ?? null;
            if (!$new_email) {
                return ['success' => false, 'error' => 'Email change request not found.'];
            }
            
            // Email is stored as plain text (not hashed) for login and verification compatibility
            $sql = "UPDATE users SET email = ? WHERE id = ?";
            $this->db->query($sql, [$new_email, $user_id]);
            
            unset($_SESSION['email_change_new_email_' . $user_id]);
            
            return ['success' => true, 'new_email' => $new_email];
        } else {
            $this->db->query("UPDATE user_email_verifications SET attempts = attempts + 1, updated_at = NOW() WHERE user_id = ?", [$user_id]);
            return ['success' => false, 'error' => 'Invalid verification code.'];
        }
    }
}