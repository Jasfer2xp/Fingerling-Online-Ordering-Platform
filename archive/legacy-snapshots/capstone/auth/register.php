// === SINGLE POST HANDLING BLOCK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Step 0: Tangub City residency
        if ($step == 0) {
            $is_tangub_resident = $_POST['is_tangub_resident'] ?? '';
            if (empty($is_tangub_resident)) {
                $error = 'Please select an option.';
            } elseif ($is_tangub_resident === 'no') {
                $error = 'This platform is exclusive only for Tangub City.';
            } else {
                redirect(base_url("auth/register.php?type={$user_type}&step=1"));
            }
        }

        // Step 1: Basic info
        elseif ($step == 1) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');

            if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    $user = new User($database);
                    if ($user->emailExists($email)) {
                        $sql = "SELECT u.id, u.user_type
                                FROM users u
                                LEFT JOIN user_email_verifications v ON u.id = v.user_id
                                WHERE u.email = ? AND (v.verified_at IS NULL OR v.user_id IS NULL)";
                        
                        $existing_user = $database->fetch($sql, [$email]);
                        
                        if ($existing_user) {
                            // User exists but not verified - continue with registration
                            $user_id = $existing_user['id'];
                            $user_type = $existing_user['user_type'];
                            
                            // Store registration data for completion
                            $_SESSION['registration_data'] = [
                                'user_type' => $user_type,
                                'email' => $email,
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                                'user_id' => $user_id
                            ];
                            
                            redirect(base_url("auth/register.php?type={$user_type}&step=3"));
                        } else {
                            // User exists and verified - redirect to dashboard
                            $user_id = $existing_user['id'];
                            $user_type = $existing_user['user_type'];
                            
                            // Set session variables
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['user_type'] = $user_type;
                            
                            redirect(base_url("{$user_type}/dashboard.php"));
                        }
                    } else {
                        // Create new user
                        $user_id = $user->createUser([
                            'email' => $email,
                            'password' => password_hash($password, HASH_ALGO),
                            'user_type' => $user_type,
                            'status' => 'pending'
                        ]);
                        
                        // Store registration data for completion
                        $_SESSION['registration_data'] = [
                            'user_type' => $user_type,
                            'email' => $email,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'user_id' => $user_id
                        ];
                        
                        redirect(base_url("auth/register.php?type={$user_type}&step=3"));
                    }
                } catch (Exception $e) {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        }

        // Step 2: Address details
        elseif ($step == 2) {
            $barangay = sanitize_input($_POST['barangay'] ?? '');
            $city = sanitize_input($_POST['city'] ?? '');
            $province = sanitize_input($_POST['province'] ?? '');
            $postal_code = sanitize_input($_POST['postal_code'] ?? '');
            $contact_number = sanitize_input($_POST['contact_number'] ?? '');
            
            if (empty($barangay) || empty($city) || empty($province)) {
                $error = 'Please fill in all address fields.';
            } else {
                // Geocode the address to get latitude and longitude
                $address = "{$barangay}, {$city}, {$province}, Philippines";
                $geocode_result = geocodeAddress($address);
                
                $latitude = null;
                $longitude = null;
                
                if ($geocode_result && $geocode_result['success']) {
                    $latitude = $geocode_result['latitude'];
                    $longitude = $geocode_result['longitude'];
                }
                
                // Save customer profile
                $customer = new Customer($database);
                $result = $customer->createCustomer($_SESSION['registration_data']['user_id'], [
                    'first_name' => $_SESSION['registration_data']['first_name'],
                    'last_name' => $_SESSION['registration_data']['last_name'],
                    'contact_number' => $contact_number,
                    'barangay' => $barangay,
                    'city' => $city,
                    'province' => $province,
                    'postal_code' => $postal_code,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
                
                if ($result) {
                    // Mark registration as complete
                    unset($_SESSION['registration_data']);
                    $_SESSION['complete_registration_user_id'] = $_SESSION['registration_data']['user_id'] ?? null;
                    $_SESSION['success'] = 'Registration completed successfully!';
                    
                    // Redirect to dashboard
                    redirect(base_url("{$user_type}/dashboard.php"));
                } else {
                    $error = 'Failed to save your information. Please try again.';
                }
            }
        }

        // Step 3: Complete registration (for existing users)
        elseif ($step == 3) {
            // This step is for completing registration of existing users
            // We'll handle this in the next block
        }
    }
}