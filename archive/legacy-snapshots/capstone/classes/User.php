    /**
     * Register a new user
     */
    public function register($email, $password, $user_type, $profile_data = []) {
        try {
            $this->db->beginTransaction();
            
            // Check if email already exists
            if ($this->emailExists($email)) {
                throw new Exception('Email already exists');
            }
            
            // Hash password
            $password_hash = password_hash($password, HASH_ALGO);
            
            // For suppliers, we set status to 'inactive' initially to indicate incomplete registration
            // This will be changed to 'pending' when they complete step 3
            $status = ($user_type === 'supplier') ? 'inactive' : 'active';
            $sql = "INSERT INTO users (email, password_hash, user_type, status) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->query($sql, [$email, $password_hash, $user_type, $status]);
            
            $user_id = $this->db->lastInsertId();
            
            // Insert profile data based on user type
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