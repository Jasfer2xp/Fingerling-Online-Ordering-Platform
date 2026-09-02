<?php
require_once '../config/config.php';
require_once '../config/functions.php';

function handlePermitUpload($file) {
    $upload_dir = '../uploads/permits/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }
    }
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024;
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and PDF files are allowed.'];
    }
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size too large. Maximum size is 5MB.'];
    }
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'permit_' . uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => 'uploads/permits/' . $filename];
    }
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

function googleGeocodeRequest(array $params) {
    $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Google Maps API key is not configured.'];
    }

    $params['key'] = $apiKey;
    $params['region'] = 'ph';
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($params);

    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Fingerling Online Ordering Platform/1.0\r\n"
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'Unable to contact geocoding service.'];
        }

        $data = json_decode($response, true);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) {
            return ['success' => false, 'error' => $data['status'] ?? 'No coordinates found'];
        }

        return ['success' => true, 'result' => $data['results'][0]];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Geocoding error: ' . $e->getMessage()];
    }
}

function extractGoogleComponents(array $components = []): array {
    $result = ['purok' => '', 'barangay' => ''];

    foreach ($components as $component) {
        $types = $component['types'] ?? [];

        if (!$result['barangay'] && (in_array('sublocality_level_1', $types, true) || in_array('administrative_area_level_3', $types, true) || in_array('administrative_area_level_2', $types, true))) {
            $result['barangay'] = $component['long_name'];
        }

        if (
            !$result['purok'] &&
            (in_array('sublocality_level_2', $types, true)
                || in_array('route', $types, true)
                || in_array('neighborhood', $types, true)
                || in_array('premise', $types, true))
        ) {
            $result['purok'] = $component['long_name'];
        }
    }

    return $result;
}

function geocodeAddress($address) {
    $address = trim((string) $address);
    if (strlen($address) < 6) {
        return ['success' => false, 'error' => 'Address too short for geocoding'];
    }

    $response = googleGeocodeRequest([
        'address' => $address . ', Tangub City, Misamis Occidental, Philippines'
    ]);

    if (!$response['success']) {
        return $response;
    }

    $result = $response['result'];
    $location = $result['geometry']['location'] ?? null;

    if (!$location) {
        return ['success' => false, 'error' => 'No coordinates found'];
    }

    return [
        'success' => true,
        'latitude' => (float) $location['lat'],
        'longitude' => (float) $location['lng'],
        'formatted_address' => $result['formatted_address'] ?? $address,
        'components' => extractGoogleComponents($result['address_components'] ?? [])
    ];
}

function reverseGeocodeCoordinates($lat, $lng) {
    $lat = (float) $lat;
    $lng = (float) $lng;
    if (!$lat || !$lng) {
        return ['success' => false, 'error' => 'Invalid coordinates provided.'];
    }

    $response = googleGeocodeRequest([
        'latlng' => $lat . ',' . $lng
    ]);

    if (!$response['success']) {
        return $response;
    }

    $result = $response['result'];
    $location = $result['geometry']['location'] ?? ['lat' => $lat, 'lng' => $lng];

    return [
        'success' => true,
        'latitude' => (float) $location['lat'],
        'longitude' => (float) $location['lng'],
        'formatted_address' => $result['formatted_address'] ?? '',
        'components' => extractGoogleComponents($result['address_components'] ?? [])
    ];
}

if (isset($_GET['action']) && $_GET['action'] === 'geocode') {
    header('Content-Type: application/json');
    $addressQuery = trim($_GET['address'] ?? '');
    if (strlen($addressQuery) < 6) {
        echo json_encode(['error' => 'Provide a complete address to geocode.']);
        exit;
    }

    $geoResult = geocodeAddress($addressQuery);
    if (!$geoResult['success']) {
        echo json_encode(['error' => $geoResult['error'] ?? 'Unable to geocode address.']);
        exit;
    }

    echo json_encode([
        'lat' => $geoResult['latitude'],
        'lng' => $geoResult['longitude'],
        'purok' => $geoResult['components']['purok'] ?? '',
        'barangay' => $geoResult['components']['barangay'] ?? '',
        'formatted_address' => $geoResult['formatted_address'] ?? ''
    ]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'reverse_geocode') {
    header('Content-Type: application/json');
    $lat = $_GET['lat'] ?? '';
    $lng = $_GET['lng'] ?? '';
    if ($lat === '' || $lng === '') {
        echo json_encode(['error' => 'Missing coordinates.']);
        exit;
    }
    $reverseResult = reverseGeocodeCoordinates($lat, $lng);
    if (!$reverseResult['success']) {
        echo json_encode(['error' => $reverseResult['error'] ?? 'Unable to reverse geocode coordinates.']);
        exit;
    }
    echo json_encode([
        'lat' => $reverseResult['latitude'],
        'lng' => $reverseResult['longitude'],
        'purok' => $reverseResult['components']['purok'] ?? '',
        'barangay' => $reverseResult['components']['barangay'] ?? '',
        'formatted_address' => $reverseResult['formatted_address'] ?? ''
    ]);
    exit;
}

if (is_logged_in()) {
    $requested_step = (int)($_GET['step'] ?? 0);
    $requested_type = $_GET['type'] ?? 'customer';
    $has_redirect_session = isset($_SESSION['complete_registration_user_id']) || isset($_SESSION['verify_user_id']);
    $completing_registration = ($requested_step === 3 && $has_redirect_session);
    if (!$completing_registration && $requested_step === 3) {
        try {
            $user = new User($database);
            $user_id = get_user_id();
            $profile = $user->getUserProfile($user_id);
            $is_incomplete = false;
            if ($requested_type === 'supplier') {
                $is_incomplete = empty($profile['business_name']) || empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']);
            } else {
                $is_incomplete = empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']);
            }
            if ($is_incomplete) {
                $_SESSION['complete_registration_user_id'] = $user_id;
                $_SESSION['complete_registration_email'] = $profile['email'] ?? '';
                $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? ($profile['business_name'] ?? '');
                $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
                $completing_registration = true;
            }
        } catch (Exception $e) {
        }
    }
    if (!$completing_registration) {
        $user_type = get_user_type();
        switch ($user_type) {
            case 'admin': redirect(base_url('admin/dashboard.php')); break;
            case 'supplier': redirect(base_url('supplier/dashboard.php')); break;
            case 'customer': redirect(base_url('customer/dashboard.php')); break;
        }
    }
}

$complete_registration_user_id = $_SESSION['complete_registration_user_id'] ?? null;
$complete_registration_email = $_SESSION['complete_registration_email'] ?? '';
$complete_registration_first_name = $_SESSION['complete_registration_first_name'] ?? '';
$complete_registration_last_name = $_SESSION['complete_registration_last_name'] ?? '';
$user_type = $_GET['type'] ?? 'customer';
if (!in_array($user_type, ['customer', 'supplier'])) { $user_type = 'customer'; }
$error = '';
$success = '';

$session_error = $_SESSION['registration_error'] ?? '';
if (!empty($session_error)) {
    $error = $session_error;
    unset($_SESSION['registration_error']);
}

$step = $_GET['step'] ?? 0;
if (!in_array($step, [0, 1, 2, 3])) { $step = 0; }

$google_registration_data = $_SESSION['pending_google_registration'] ?? null;
if ($google_registration_data && $google_registration_data['role'] !== $user_type) {
    unset($_SESSION['pending_google_registration']);
    $google_registration_data = null;
}

if (isset($_SESSION['registration_data'])) {
    $registration_data = $_SESSION['registration_data'];
    if ($user_type !== $registration_data['user_type']) {
        unset($_SESSION['registration_data']);
        $registration_data = [];
    }
} else {
    $registration_data = [];
}

if ($complete_registration_user_id && $step == 3 && $user_type == 'customer') {
    $registration_data = [
        'user_type' => 'customer',
        'email' => $complete_registration_email,
        'first_name' => $complete_registration_first_name,
        'last_name' => $complete_registration_last_name
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
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
        else if ($step == 1) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (!is_password_strong($password)) {
                $error = 'Password must be at least 8 characters long, contain at least one uppercase letter, one number, and cannot be all numbers.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                // OTP-based verification: allow any email to proceed, verification happens via OTP
                if (empty($error)) {
                    try {
                        $user = new User($database);
                        if ($user->emailExists($email)) {
                            // Find user by plain text email
                            $found_user = find_user_by_email($database, $email);
                            if ($found_user) {
                                $sql = "SELECT verified_at FROM user_email_verifications WHERE user_id = ?";
                                $verification = $database->fetch($sql, [$found_user['id']]);
                                $existing_user = null;
                                if (!$verification || empty($verification['verified_at'])) {
                                    $existing_user = [
                                        'id' => $found_user['id'],
                                        'user_type' => $found_user['user_type']
                                    ];
                                }
                            } else {
                                $existing_user = null;
                            }
                            if ($existing_user) {
                                if ($existing_user['user_type'] !== $user_type) {
                                    $error = "This email is already registered as a {$existing_user['user_type']}. Please use a different email or login with your existing account.";
                                } else {
                                    $_SESSION['registration_data'] = [
                                        'user_type' => $user_type,
                                        'email' => $email,
                                        'password' => $password,
                                        'first_name' => $first_name,
                                        'last_name' => $last_name
                                    ];
                                    $user_id = $existing_user['id'];
                                    $sql = "UPDATE users SET user_type = ? WHERE id = ?";
                                    $database->query($sql, [$user_type, $user_id]);
                                    if ($user_type === 'customer') {
                                        $sql = "SELECT id FROM customers WHERE user_id = ?";
                                        $customer = $database->fetch($sql, [$user_id]);
                                        if ($customer) {
                                            $sql = "UPDATE customers SET first_name = ?, last_name = ? WHERE user_id = ?";
                                            $database->query($sql, [$first_name, $last_name, $user_id]);
                                        } else {
                                            $sql = "INSERT INTO customers (user_id, first_name, last_name) VALUES (?, ?, ?)";
                                            $database->query($sql, [$user_id, $first_name, $last_name]);
                                        }
                                    } else {
                                        $sql = "SELECT id FROM suppliers WHERE user_id = ?";
                                        $supplier = $database->fetch($sql, [$user_id]);
                                        if ($supplier) {
                                            $sql = "UPDATE suppliers SET business_name = ? WHERE user_id = ?";
                                            $database->query($sql, ['', $user_id]);
                                        } else {
                                            $sql = "INSERT INTO suppliers (user_id, business_name) VALUES (?, ?)";
                                            $database->query($sql, [$user_id, '']);
                                        }
                                    }
                                }
                                } else {
                                    $defaultEmailError = 'Email already registered. Please use a different email.';
                                    $error = $defaultEmailError;
                                    if ($user_type === 'customer') {
                                        // Find user by plain text email
                                        $found_user = find_user_by_email($database, $email);
                                    $existing_customer = null;
                                    if ($found_user && $found_user['user_type'] === $user_type) {
                                        $sql = "SELECT c.first_name, c.last_name, c.barangay, c.city, c.province
                                                FROM customers c
                                                WHERE c.user_id = ?";
                                        $customer_data = $database->fetch($sql, [$found_user['id']]);
                                        if ($customer_data) {
                                            $existing_customer = array_merge(['id' => $found_user['id']], $customer_data);
                                        }
                                    }
                                    if ($existing_customer) {
                                        $needs_completion = empty($existing_customer['barangay']) || empty($existing_customer['city']) || empty($existing_customer['province']);
                                        if ($needs_completion) {
                                            $_SESSION['complete_registration_user_id'] = $existing_customer['id'];
                                            $_SESSION['complete_registration_email'] = $email;
                                            $_SESSION['complete_registration_first_name'] = $existing_customer['first_name'] ?? '';
                                            $_SESSION['complete_registration_last_name'] = $existing_customer['last_name'] ?? '';
                                            $error = 'Your account is not yet complete. <a href="'.base_url('auth/register.php?type='.$user_type.'&step=3').'">Click here to complete your registration.</a>';
                                        }
                                    }
                                } else if ($user_type === 'supplier') {
                                    // Find user by plain text email
                                    $found_user = find_user_by_email($database, $email);
                                    $existing_supplier = null;
                                    if ($found_user && $found_user['user_type'] === $user_type) {
                                        $sql = "SELECT s.business_name, s.barangay, s.city, s.province, s.valid_id, s.business_permit
                                                FROM suppliers s
                                                WHERE s.user_id = ?";
                                        $supplier_data = $database->fetch($sql, [$found_user['id']]);
                                        if ($supplier_data) {
                                            $existing_supplier = array_merge(['id' => $found_user['id']], $supplier_data);
                                        }
                                    }
                                    if ($existing_supplier) {
                                        $has_id = !empty($existing_supplier['valid_id']) || !empty($existing_supplier['business_permit']);
                                        $needs_completion = empty($existing_supplier['business_name']) || empty($existing_supplier['barangay']) || empty($existing_supplier['city']) || empty($existing_supplier['province']) || !$has_id;
                                        
                                        if ($needs_completion) {
                                            $_SESSION['complete_registration_user_id'] = $existing_supplier['id'];
                                            $_SESSION['complete_registration_email'] = $email;
                                            $_SESSION['complete_registration_first_name'] = $existing_supplier['business_name'] ?? '';
                                            $_SESSION['complete_registration_last_name'] = '';
                                            $error = 'Your account is not yet complete. <a href="'.base_url('auth/register.php?type='.$user_type.'&step=3').'">Click here to complete your registration.</a>';
                                        }
                                    }
                                }
                                
                                if ($error === $defaultEmailError) {
                                    unset($_SESSION['complete_registration_user_id'], $_SESSION['complete_registration_email'], $_SESSION['complete_registration_first_name'], $_SESSION['complete_registration_last_name']);
                                }
                            }
                        }
                        if (empty($error)) {
                            if (!isset($user_id)) {
                                $_SESSION['registration_data'] = [
                                    'user_type' => $user_type,
                                    'email' => $email,
                                    'password' => $password,
                                    'first_name' => $first_name,
                                    'last_name' => $last_name
                                ];
                                $profile_data = [
                                    'first_name' => $first_name,
                                    'last_name' => $last_name
                                ];
                                if ($user_type === 'supplier') {
                                    $profile_data['business_name'] = '';
                                }
                                $user_id = $user->register($email, $password, $user_type, $profile_data);
                            }
                            if ($user_id) {
                                $result = $user->createEmailVerification($user_id, $email);
                                if (is_array($result) && !$result['success']) {
                                    $error = 'Registration failed: ' . ($result['error'] ?? 'Unknown error');
                                } else {
                                    $_SESSION['verify_user_id'] = $user_id;
                                    $_SESSION['verify_email'] = $email;
                                    $_SESSION['verify_user_type'] = $user_type;
                                    $_SESSION['redirect_source'] = 'register';
                                    redirect(base_url('auth/verify-email.php?redirect=register'));
                                }
                            } else {
                                $error = 'Failed to create user account. Please try again.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Registration failed. Please try again.';
                        // Log the actual error for debugging
                        error_log('Registration error: ' . $e->getMessage());
                        // Show detailed error in development mode
                        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
                            $error .= ' Error: ' . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
        else if ($step == 3) {
            $is_google_registration = isset($_SESSION['pending_google_registration']);
            if (!$is_google_registration &&
                !$complete_registration_user_id &&
                (!isset($_SESSION['verify_user_id'], $_SESSION['email_verified']) ||
                !$_SESSION['email_verified'])) {
                if (is_logged_in()) {
                    $user = new User($database);
                    $user_id = get_user_id();
                    $profile = $user->getUserProfile($user_id);
                    $is_incomplete = false;
                    if ($user_type === 'supplier') {
                        $is_incomplete = empty($profile['business_name']) || empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']);
                    } else {
                        $is_incomplete = empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']);
                    }
                    if ($is_incomplete) {
                        $_SESSION['complete_registration_user_id'] = $user_id;
                        $_SESSION['complete_registration_email'] = $profile['email'] ?? '';
                        $_SESSION['complete_registration_first_name'] = $profile['first_name'] ?? ($profile['business_name'] ?? '');
                        $_SESSION['complete_registration_last_name'] = $profile['last_name'] ?? '';
                    } else if ($user->isEmailVerified($user_id)) {
                        $_SESSION['email_verified'] = true;
                        $_SESSION['verify_user_id'] = $user_id;
                    } else {
                        redirect(base_url('auth/register.php'));
                    }
                } else {
                    redirect(base_url('auth/register.php'));
                }
            }
            try {
                $profile_data = [];
                if ($user_type === 'customer') {
                    $contact_number = trim($_POST['contact_number'] ?? '');
                    $address = trim($_POST['address'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $city = 'Tangub City';
                    $province = 'Misamis Occidental';
                    if (empty($barangay)) {
                        $error = 'Please select your barangay.';
                    } else {
                        if (!empty($contact_number)) {
                            $contact_number = preg_replace('/\D/', '', $contact_number);
                            if (strlen($contact_number) == 11 && str_starts_with($contact_number, '09')) {
                                if (empty($_SESSION['phone_verified']) || $_SESSION['verified_phone'] !== $contact_number) {
                                    $error = 'Please verify your phone number.';
                                }
                            } else if (!empty($contact_number)) {
                                $error = 'Please enter a valid 11-digit Philippine mobile number.';
                            }
                        }
                        if (empty($error)) {
                            $profile_data = [
                                'contact_number' => $contact_number,
                                'address' => $address,
                                'barangay' => $barangay,
                                'city' => $city,
                                'province' => $province
                            ];
                        }
                    }
                } else {
                    $business_name = trim($_POST['business_name'] ?? '');
                    $contact_number = trim($_POST['contact_number'] ?? '');
                    $purok = trim($_POST['purok'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $city = 'Tangub City';
                    $province = 'Misamis Occidental';
                    $latitude = trim($_POST['latitude'] ?? '');
                    $longitude = trim($_POST['longitude'] ?? '');
                    $supports_truck = !empty($_POST['supports_truck']);
                    $supports_boat = !empty($_POST['supports_boat']);
                    if (empty($business_name) || empty($barangay)) {
                        $error = 'Please fill in all required fields.';
                    } else if (!$supports_truck && !$supports_boat) {
                        $error = 'Please select at least one delivery option.';
                    } else {
                        $contact_number = preg_replace('/\D/', '', $contact_number);
                        if (strlen($contact_number) != 11 || !str_starts_with($contact_number, '09')) {
                            $error = 'Please enter a valid 11-digit Philippine mobile number.';
                        } else if (empty($_SESSION['phone_verified']) || $_SESSION['verified_phone'] !== $contact_number) {
                            $error = 'Please verify your phone number.';
                        } else {
                            if (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
                                $error = 'Please upload a valid ID.';
                            } else {
                                $upload_result = handlePermitUpload($_FILES['valid_id']);
                                if (!$upload_result['success']) {
                                    $error = 'Failed to upload valid ID: ' . ($upload_result['error'] ?? 'Unknown error');
                                } else {
                                    $permit_path = $upload_result['file_path'];
                                    if (empty($latitude) || empty($longitude)) {
                                        $full_address = "$purok, $barangay, $city, $province";
                                        $geo_result = geocodeAddress($full_address);
                                        if ($geo_result['success']) {
                                            $latitude = $geo_result['latitude'];
                                            $longitude = $geo_result['longitude'];
                                            $components = $geo_result['components'] ?? [];
                                            if (empty($purok) && !empty($components['purok'])) {
                                                $purok = $components['purok'];
                                            }
                                            if (empty($barangay) && !empty($components['barangay'])) {
                                                $barangay = $components['barangay'];
                                            }
                                        }
                                    }
                                    // Hash valid_id before storing
                                    $valid_id_hash = !empty($permit_path) ? hash_sensitive_data($permit_path) : '';
                                    
                                    $profile_data = [
                                        'business_name' => $business_name,
                                        'contact_number' => $contact_number,
                                        'purok' => $purok,
                                        'barangay' => $barangay,
                                        'city' => $city,
                                        'province' => $province,
                                        'valid_id' => $valid_id_hash,
                                        'latitude' => $latitude,
                                        'longitude' => $longitude,
                                        'status' => 'pending',
                                        'supports_truck' => $supports_truck,
                                        'supports_boat' => $supports_boat,
                                        'full_address' => implode(', ', array_filter([$purok, $barangay, $city, $province])),
                                        'store_name' => $business_name
                                    ];
                                }
                            }
                        }
                    }
                }
                if (empty($error)) {
                    $user = new User($database);
                    $user_id = $complete_registration_user_id ?? $_SESSION['verify_user_id'] ?? null;
                    if ($complete_registration_user_id) {
                        $user->updateProfile($user_id, $profile_data);
                        unset($_SESSION['complete_registration_user_id'], $_SESSION['complete_registration_email'], $_SESSION['complete_registration_first_name'], $_SESSION['complete_registration_last_name']);
                        $_SESSION['registration_just_completed'] = true;
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_type'] = $user_type;
                        $_SESSION['user_email'] = $_SESSION['complete_registration_email'] ?? $_SESSION['verify_email'] ?? '';
                        $_SESSION['flash_success'] = 'Registration completed successfully! Please login to access your account.';
                        redirect(base_url('auth/login.php'));
                    } else {
                        $user->updateProfile($user_id, $profile_data);
                        unset($_SESSION['registration_data'], $_SESSION['verify_user_id'], $_SESSION['verify_email'], $_SESSION['verify_user_type'], $_SESSION['email_verified'], $_SESSION['phone_verified'], $_SESSION['verified_phone']);
                        if ($user_type === 'supplier') {
                            $_SESSION['flash_success'] = 'Your account and documents are being reviewed by our administrators, we will send an email if the review is successful.';
                        } else {
                            $_SESSION['flash_success'] = 'Registration completed successfully! Please login to access your account.';
                        }
                        redirect(base_url('auth/login.php'));
                    }
                }
            } catch (Exception $e) {
                $error = 'Failed to complete registration. Please try again.';
            }
        }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link rel="alternate icon" href="<?php echo base_url('assets/icons/fish.svg'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary-500:#3b82f6; --primary-700:#1d4ed8; --brand-accent:#f59e0b; --muted:#9ca3af; }
        html, body { height:100%; }
        body { margin:0; }
        .hero-navbar { background: rgba(0,0,0,.25)!important; transition: background .2s ease, backdrop-filter .2s ease; }
        .hero-navbar.scrolled { background: rgba(0,0,0,.6)!important; backdrop-filter: saturate(160%) blur(8px); }
        .brand-icon { width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary-500),var(--primary-700));color:#fff;box-shadow:0 6px 18px rgba(37,99,235,.35); }
        .brand-icon i{ font-size:18px; line-height:1; }
        .navbar-brand{ font-weight:700; }
        .auth-hero { position:relative; min-height:100vh; display:flex; align-items:center; color:#fff; overflow:hidden; padding-top: 80px; }
        .auth-hero::before { content:""; position:absolute; inset:0; background-image:url('<?php echo base_url('gif/fish.gif'); ?>'); background-size:cover; background-position:center; opacity:1; pointer-events:none; }
        .auth-hero::after { content:""; position:absolute; inset:0; background: linear-gradient(135deg, rgba(17,24,39,.35), rgba(2,6,23,.35)); pointer-events:none; }
        .auth-hero > .container{ position:relative; z-index:1; }
        .auth-container { display: flex; gap: 2rem; align-items: center; min-height: calc(100vh - 100px); }
        .auth-content { flex: 1; color: white; padding: 2rem; }
        .auth-content h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; }
        .auth-content p { font-size: 1.1rem; line-height: 1.6; opacity: 0.9; }
        .auth-form-container { flex: 1; background: rgba(15, 23, 42, 0.85); border-radius: 16px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-width: 500px; margin: 2rem 0; border: 1px solid rgba(255,255,255,0.1); }
        .auth-title { font-weight: 800; letter-spacing:.3px; color: #f1f5f9; margin-bottom: 1.5rem; }
        .switcher { position: relative; z-index: 2; margin-bottom: 1.5rem; }
        .switcher a { color:#e2e8f0; text-decoration:none; padding:.5rem 1rem; border:1px solid rgba(255,255,255,0.2); border-radius:999px; }
        .switcher a.active { background: #3b82f6; color:#fff; border-color:#3b82f6; font-weight:700; }
        .form-label { color:#e2e8f0; font-weight:600; margin-bottom: 0.5rem; }
        .form-control, .form-select { background: rgba(30, 41, 59, 0.7); border:1px solid rgba(255,255,255,0.1); color:#f1f5f9; padding: 0.75rem; border-radius:8px; margin-bottom: 1rem; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 .2rem rgba(59,130,246,.2); border-color: #3b82f6; }
        .form-control::placeholder { color: #94a3b8; }
        .form-control:disabled { color: #000; background-color: #e9ecef; opacity: 1; }
        .input-group-text{ background: rgba(30, 41, 59, 0.7); border:1px solid rgba(255,255,255,0.1); color: #e2e8f0; }
        .btn-accent{ background: linear-gradient(135deg, var(--brand-accent), #d97706); border:0; color:#111827; font-weight:700; border-radius:8px; padding:0.75rem 1.5rem; box-shadow:0 4px 6px rgba(245,158,11,.25); width: 100%; margin-bottom: 0.5rem; }
        .btn-accent:hover{ filter:brightness(1.05); }
        .btn-outline-light { border: 1px solid rgba(255,255,255,0.2); color: #e2e8f0; font-weight: 500; border-radius:8px; padding:0.75rem 1.5rem; width: 100%; background: rgba(30, 41, 59, 0.5); }
        .btn-outline-light:hover { background: rgba(56, 70, 97, 0.7); }
        .helper { color: #94a3b8; font-size: 0.875rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group:last-child { margin-bottom: 0; }
        .alert { border-radius: 8px; color: #111827; }
        .alert-danger { background-color: #fecaca; border-color: #fca5a5; }
        .alert-success { background-color: #bbf7d0; border-color: #86efac; }
        #supplierMap{ height:280px; border-radius:12px; overflow:hidden; margin-bottom: 1rem; }
        .field-icon {
            float: right;
            margin-left: -25px;
            margin-top: -25px;
            position: relative;
            z-index: 2;
            cursor: pointer;
        }
        .password-field-wrapper {
            position: relative;
            width: 100%;
        }
        .password-field-wrapper .field-icon {
            position: absolute;
            top: 50%;
            right: 12px;
            margin: 0;
            transform: translateY(-50%);
        }
        .password-field-wrapper input.form-control {
            padding-right: 2.5rem;
        }
        @media (max-width: 991.98px){
            .auth-hero{ align-items:flex-start; padding-top: calc(64px + 2rem);}
            .auth-container { flex-direction: column; }
            .auth-content { text-align: center; }
            .auth-form-container { max-width: 100%; width: 100%; }
        }
        @media (prefers-reduced-motion: reduce){ .auth-hero::before{ display:none; } .auth-hero::after{ background:linear-gradient(135deg,#0f172a,#1f2937);} }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top hero-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url(); ?>">
      <span class="brand-icon"><i class="fas fa-fish"></i></span>
      <span><?php echo APP_NAME; ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url(); ?>">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo base_url('auth/login.php'); ?>">Login</a></li>
      </ul>
    </div>
  </div>
</nav>
<section class="auth-hero">
  <div class="container">
    <div class="auth-container">
      <div class="auth-content">
        <h1>Join Our Aquaculture Community</h1>
        <p>Fingerling Online Ordering Platform connects local fish farmers with quality fingerling suppliers in Tangub City. Join us to access premium fingerlings, competitive prices, and reliable suppliers right in your area.</p>
        <p>As a platform exclusive to Tangub City residents, we ensure that you get the best local service and support for your aquaculture needs.</p>
      </div>
      <div class="auth-form-container">
        <h1 class="auth-title text-center">
            <?php if ($step == 0): ?>
                Do you live in Tangub City?
            <?php elseif ($step == 1): ?>
                Create your account
            <?php elseif ($step == 3): ?>
                <?php echo $user_type === 'customer' ? 'Complete Customer Registration' : 'Complete Supplier Registration'; ?>
            <?php endif; ?>
        </h1>
        <div class="switcher d-flex justify-content-center gap-2">
          <a href="<?php echo base_url('auth/register.php?type=customer&step=0'); ?>" class="<?php echo $user_type==='customer'?'active':''; ?>">Customer</a>
          <a href="<?php echo base_url('auth/register.php?type=supplier&step=0'); ?>" class="<?php echo $user_type==='supplier'?'active':''; ?>">Supplier</a>
        </div>
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="fas fa-circle-exclamation me-2"></i>
          <div><?php echo $error; ?></div>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="fas fa-circle-check me-2"></i>
          <div><?php echo $success; ?></div>
        </div>
        <?php if ($user_type === 'supplier' || !empty($success)): ?>
        <div class="d-grid mt-3">
            <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-accent">Go to Login</a>
        </div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (!$success): ?>
      <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="registrationForm">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
        <?php if ($step == 0): ?>
        <div class="form-group">
          <p class="lead">This platform is exclusive for Tangub City only, that's why we have to confirm if you are living in Tangub City. Otherwise, you will not be able to create an account.</p>
        </div>
        <div class="form-group">
          <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="is_tangub_resident" id="resident_yes" value="yes" required>
            <label class="form-check-label text-light" for="resident_yes">
              Yes, I live in Tangub City
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="is_tangub_resident" id="resident_no" value="no" required>
            <label class="form-check-label text-light" for="resident_no">
              No, I don't live in Tangub City
            </label>
          </div>
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-accent">Continue</button>
          <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">Already have an account? Sign in</a>
        </div>
        <?php elseif ($step == 1): ?>
        <div class="form-group">
          <label class="form-label" for="email">Email *</label>
          <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($_POST['email'] ?? $registration_data['email'] ?? ''); ?>" required>
          <div class="invalid-feedback">Valid email required.</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="first_name">First name *</label>
          <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $registration_data['first_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="last_name">Last name *</label>
          <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? $registration_data['last_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label class="col-md-4 control-label form-label">Password *</label>
          <div class="col-md-6 password-field-wrapper">
            <input id="password-field-register" type="password" class="form-control" name="password" minlength="8" required>
            <span toggle="#password-field-register" class="fa fa-fw fa-eye field-icon toggle-password"></span>
          </div>
          <div class="invalid-feedback">Minimum 8 characters.</div>
          <div class="mt-2">
            <div class="d-flex justify-content-between mb-1">
              <small>Password Strength:</small>
              <small id="passwordStrengthText">Weak</small>
            </div>
            <div class="progress" style="height: 4px;">
              <div id="passwordStrengthBar" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <ul class="mt-2" style="font-size: 0.75rem;">
              <li>At least 8 characters</li>
              <li>One uppercase letter</li>
              <li>One lowercase letter</li>
              <li>One number</li>
              <li>One special character</li>
            </ul>
          </div>
        </div>
        <div class="form-group">
          <label class="col-md-4 control-label form-label">Confirm Password *</label>
          <div class="col-md-6 password-field-wrapper">
            <input id="confirm-password-field-register" type="password" class="form-control" name="confirm_password" required>
            <span toggle="#confirm-password-field-register" class="fa fa-fw fa-eye field-icon toggle-password"></span>
          </div>
          <div class="invalid-feedback">Please confirm password.</div>
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-accent">Create <?php echo ucfirst($user_type); ?> Account</button>
          <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">Already have an account? Sign in</a>
        </div>
        <?php elseif ($step == 3): ?>
        <?php if ($user_type === 'customer'): ?>
          <div class="form-group">
    <label class="form-label" for="contact_number">Contact Number *</label>
    <div class="input-group mb-2">
        <input type="text" class="form-control" id="contact_number" name="contact_number" 
               placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" required>
        <button type="button" class="btn btn-outline-primary" id="verifyPhoneBtn">
            Verify
        </button>
    </div>
    <div class="form-text text-muted mb-2">Enter your 11-digit mobile number (e.g. 09123456789)</div>

    <div id="phoneVerificationStatus"></div>

    <!-- THIS IS THE MISSING OTP FIELD – NOW FULLY VISIBLE & WORKING -->
    <div id="smsCodeContainer" class="mt-3 d-none">
        <label class="form-label">Enter 6-digit SMS Code</label>
        <div class="input-group">
            <input type="text" class="form-control" id="smsCode" maxlength="6" placeholder="000000" autocomplete="off">
            <button type="button" class="btn btn-success" id="confirmCodeBtn">Verify Code</button>
        </div>
        <div id="smsCodeStatus" class="mt-2 small"></div>
        <div class="mt-2">
            <small class="text-muted">Resend code in <span id="resendTimer">60</span>s</small>
            <a href="#" id="resendLink" class="d-none ms-2 text-primary">Resend now</a>
        </div>
    </div>
</div>
          <div class="form-group">
            <label class="form-label" for="address">Purok or Street</label>
            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="barangay_select">Barangay</label>
            <select class="form-select" id="barangay_select" required>
              <option value="" disabled selected>Select your barangay</option>
            </select>
            <input type="hidden" name="barangay" id="barangay" value="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
            <input type="text" class="form-control mt-2 d-none" id="barangay_other" placeholder="Enter barangay (if not listed)">
          </div>
          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" class="form-control" value="Tangub City" disabled>
            <input type="hidden" name="city" value="Tangub City">
          </div>
          <div class="form-group">
            <label class="form-label">Province</label>
            <input type="text" class="form-control" value="Misamis Occidental" disabled>
            <input type="hidden" name="province" value="Misamis Occidental">
          </div>
        <?php else: ?>
          <div class="row">
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">Business Name *</label>
                      <input type="text" class="form-control" name="business_name"
                             value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>"
                             placeholder="Business Name" required>
                  </div>
              </div>
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">Contact Number *</label>
                      <input type="tel" class="form-control" name="contact_number"
                             value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>"
                             placeholder="09XXXXXXXXX" required>
                      <div class="mt-2">
                        <button class="btn btn-outline-secondary verify-phone-btn" type="button" id="verifyPhoneBtn">
                          <i class="fas fa-shield-alt"></i> Verify
                        </button>
                      </div>
                      <div id="phoneVerificationStatus" class="mt-2"></div>
                      <div id="smsCodeContainer" class="mt-2 d-none">
                        <label class="form-label">Enter SMS Code</label>
                        <div class="input-group">
                          <input type="text" class="form-control" id="smsCode" placeholder="Enter 6-digit code" maxlength="6">
                          <button class="btn btn-outline-success" type="button" id="confirmCodeBtn">Confirm</button>
                        </div>
                        <div id="smsCodeStatus" class="mt-1"></div>
                        <div class="mt-1">
                          <small class="text-muted">Resend in <span id="resendTimer">60</span>s</small>
                          <a href="#" id="resendLink" class="d-none text-primary ms-2">Resend code</a>
                        </div>
                      </div>
                  </div>
              </div>
          </div>
          <div class="row">
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">Purok or Street</label>
                      <input type="text" class="form-control" name="purok"
                             value="<?php echo htmlspecialchars($_POST['purok'] ?? ''); ?>"
                             placeholder="Purok or Street">
                  </div>
              </div>
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label" for="barangay_select">Barangay *</label>
                      <select class="form-select" id="barangay_select" required>
                          <option value="" disabled selected>Select your barangay</option>
                      </select>
                      <input type="hidden" name="barangay" id="barangay" value="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
                      <input type="text" class="form-control mt-2 d-none" id="barangay_other" placeholder="Enter barangay (if not listed)">
                  </div>
              </div>
          </div>
          <div class="row">
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">City *</label>
                      <input type="text" class="form-control" value="Tangub City" disabled>
                      <input type="hidden" name="city" value="Tangub City">
                  </div>
              </div>
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">Province *</label>
                      <input type="text" class="form-control" value="Misamis Occidental" disabled>
                      <input type="hidden" name="province" value="Misamis Occidental">
                  </div>
              </div>
          </div>
          <div class="row">
              <div class="col-md-6">
                  <div class="form-group mb-3">
                      <label class="form-label">Valid ID</label>
                      <input type="file" class="form-control" name="valid_id"
                             accept=".jpg,.jpeg,.png,.pdf">
                      <div class="form-text">Upload valid ID here (JPG, PNG, or PDF, max 5MB)</div>
                  </div>
              </div>
          </div>
          <div class="form-group mb-4">
              <label class="form-label">Business Location</label>
              <div id="supplierMap" style="height: 300px; border-radius: 8px; margin-bottom: 15px;"></div>
              <div class="row">
                  <div class="col-md-6">
                      <input type="text" class="form-control" id="latitude" name="latitude" placeholder="Latitude" readonly value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                      <input type="text" class="form-control" id="longitude" name="longitude" placeholder="Longitude" readonly value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                  </div>
              </div>
              <div class="form-text">Click on the map to set your business location</div>
          </div>
          <div class="form-group mb-4">
              <label class="form-label">Delivery Options *</label>
              <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="supports_truck" id="supports_truck"
                         value="1" <?php echo (!isset($_POST['supports_truck']) || !empty($_POST['supports_truck'])) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="supports_truck">
                      <i class="fas fa-truck me-2"></i>Supports Truck Delivery
                  </label>
              </div>
              <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="supports_boat" id="supports_boat"
                         value="1" <?php echo !empty($_POST['supports_boat']) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="supports_boat">
                      <i class="fas fa-ship me-2"></i>Supports Boat Delivery
                  </label>
              </div>
              <div class="form-text">Select at least one delivery option</div>
          </div>
          <input type="hidden" id="business_address" name="business_address" value="<?php echo htmlspecialchars($_POST['business_address'] ?? ''); ?>">
        <?php endif; ?>
        <div class="form-group">
          <button type="submit" class="btn btn-accent">Complete Registration</button>
          <a href="<?php echo base_url('auth/login.php'); ?>" class="btn btn-outline-light">Already have an account? Sign in</a>
        </div>
      <?php endif; ?>
      </form>
      <?php endif; ?>
      <p class="mt-3 helper mb-0 text-center">By continuing, you agree to our <a href="#" class="text-decoration-none" style="color: #93c5fd;">Terms</a> and <a href="#" class="text-decoration-none" style="color: #93c5fd;">Privacy Policy</a>.</p>
    </div>
  </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo asset_url('js/password-toggle.js'); ?>"></script>
<script>
  const sendOtpUrl = "<?php echo base_url('auth/send_otp.php'); ?>";
  const resendOtpUrl = "<?php echo base_url('auth/resend_otp.php'); ?>";
  const verifyOtpUrl = "<?php echo base_url('auth/verify_otp.php'); ?>";
  const geocodeApiUrl = "<?php echo base_url('supplier/geocode.php'); ?>";
  window.addEventListener('scroll', function(){
    const navbar = document.querySelector('.hero-navbar');
    if (window.scrollY > 50) navbar.classList.add('scrolled'); else navbar.classList.remove('scrolled');
  });
  (function(){
    'use strict';
    window.addEventListener('load', function(){
      const forms = document.getElementsByClassName('needs-validation');
      Array.prototype.forEach.call(forms, function(form){
        form.addEventListener('submit', function(event){
          const pwd = document.getElementById('password-field-register');
          const cpw = document.getElementById('confirm-password-field-register');
          if (pwd && cpw && pwd.value !== cpw.value){
            cpw.setCustomValidity('Passwords do not match');
          } else if (cpw){ cpw.setCustomValidity(''); }
          if (form.checkValidity() === false){ event.preventDefault(); event.stopPropagation(); }
          form.classList.add('was-validated');
        }, false);
      });
    }, false);
  })();
  (function(){
    const passwordInput = document.getElementById('password-field-register');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    if (!passwordInput || !strengthBar) return;
    function updatePasswordStrength() {
      const password = passwordInput.value;
      let strength = 0;
      if (password.length >= 8) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[a-z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;
      strengthBar.style.width = (strength * 20) + '%';
      if (strength <= 2) {
        strengthBar.className = 'progress-bar bg-danger';
        strengthText.textContent = 'Weak';
      } else if (strength === 3) {
        strengthBar.className = 'progress-bar bg-warning';
        strengthText.textContent = 'Medium';
      } else if (strength === 4) {
        strengthBar.className = 'progress-bar bg-info';
        strengthText.textContent = 'Strong';
      } else if (strength === 5) {
        strengthBar.className = 'progress-bar bg-success';
        strengthText.textContent = 'Very Strong';
      }
      if (strength < 2 && password.length > 0) {
        passwordInput.setCustomValidity('Please enter a stronger password.');
      } else {
        passwordInput.setCustomValidity('');
      }
    }
    passwordInput.addEventListener('input', updatePasswordStrength);
  })();
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    if (form) {
      form.addEventListener('submit', function(e) {
        const phoneInput = document.querySelector('input[name="contact_number"]');
        const phoneVerifiedInput = document.getElementById('phone_verified');
        if (phoneInput && !phoneVerifiedInput) {
          const phone = phoneInput.value.trim().replace(/\D/g, '');
          if (phone.length === 11 && phone.startsWith('09')) {
            e.preventDefault();
            alert('Please verify your phone number before submitting the form.');
            return false;
          }
        }
      });
    }
  });
  document.addEventListener('DOMContentLoaded', function() {
    const verifyBtn = document.getElementById('verifyPhoneBtn');
    const phoneInput = document.querySelector('input[name="contact_number"]');
    const statusDiv = document.getElementById('phoneVerificationStatus');
    const smsCodeContainer = document.getElementById('smsCodeContainer');
    const smsCodeInput = document.getElementById('smsCode');
    const confirmCodeBtn = document.getElementById('confirmCodeBtn');
    const smsCodeStatus = document.getElementById('smsCodeStatus');
    const resendTimer = document.getElementById('resendTimer');
    const resendLink = document.getElementById('resendLink');
    if (!verifyBtn || !phoneInput || !statusDiv) return;

    let verificationSent = false;
    let resendAttempts = 0;
    const maxResendAttempts = 3;
    const waitSeconds = 60;
    let countdownInterval = null;
    const userType = '<?php echo $user_type; ?>';

    function setButtonLoading(isLoading) {
      if (isLoading) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      } else {
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Verify';
      }
    }

    function startCountdown() {
      let remaining = waitSeconds;
      clearInterval(countdownInterval);
      if (resendLink) resendLink.classList.add('d-none');
      if (resendTimer) {
        resendTimer.parentElement.classList.remove('d-none');
        resendTimer.textContent = remaining;
      }
      countdownInterval = setInterval(() => {
        remaining -= 1;
        if (resendTimer) {
          resendTimer.textContent = remaining;
        }
        if (remaining <= 0) {
          clearInterval(countdownInterval);
          if (resendTimer) {
            resendTimer.parentElement.classList.add('d-none');
          }
          if (resendLink) {
            resendLink.classList.remove('d-none');
          }
        }
      }, 1000);
    }

    function handleOtpSuccess(message) {
      verificationSent = true;
      statusDiv.innerHTML = '<small class="text-success"><i class="fas fa-check-circle"></i> ' + (message || 'OTP sent! Check your SMS.') + '</small>';
      if (smsCodeContainer) {
        smsCodeContainer.classList.remove('d-none');
      }
      phoneInput.readOnly = true;
      startCountdown();
    }

    function sendOtp(endpoint, isResend = false) {
      const phone = phoneInput.value.trim().replace(/\D/g, '');
      if (phone.length !== 11 || !phone.startsWith('09')) {
        statusDiv.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Please enter a valid 11-digit mobile number starting with 09.</small>';
        return;
      }
      if (!isResend && verificationSent) {
        statusDiv.innerHTML = '<small class="text-info"><i class="fas fa-clock"></i> Verification already sent. Please check your SMS.</small>';
        return;
      }
      if (isResend && resendAttempts >= maxResendAttempts) {
        statusDiv.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle"></i> Maximum resend attempts reached. Please try again later.</small>';
        return;
      }

      setButtonLoading(true);
      statusDiv.innerHTML = '<small class="text-info"><i class="fas fa-paper-plane"></i> Sending verification code...</small>';
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'phone=' + encodeURIComponent(phone) + '&user_type=' + encodeURIComponent(userType)
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        setButtonLoading(false);
        if (data.status === 'sent') {
          if (isResend) {
            resendAttempts++;
          }
          handleOtpSuccess(data.message);
        } else {
          statusDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle"></i> ' + (data.message || 'Failed to send SMS. Please try again.') + '</small>';
        }
      })
      .catch(() => {
        setButtonLoading(false);
        statusDiv.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-circle"></i> Unable to send OTP. Please try again.</small>';
      });
    }

    verifyBtn.addEventListener('click', function() {
      sendOtp(sendOtpUrl);
    });

    if (resendLink) {
      resendLink.addEventListener('click', function(e) {
        e.preventDefault();
        sendOtp(resendOtpUrl, true);
      });
    }

    if (confirmCodeBtn && smsCodeInput) {
      confirmCodeBtn.addEventListener('click', function() {
        const phone = phoneInput.value.trim().replace(/\D/g, '');
        const enteredCode = smsCodeInput.value.trim();
        if (enteredCode.length !== 6 || !/^\d+$/.test(enteredCode)) {
          smsCodeStatus.innerHTML = '<small class="text-danger">Please enter a valid 6-digit code.</small>';
          return;
        }
        fetch(verifyOtpUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'phone=' + encodeURIComponent(phone) + '&otp=' + encodeURIComponent(enteredCode) + '&user_type=' + encodeURIComponent(userType)
        })
        .then(response => response.json())
        .then(verifyData => {
          if (verifyData.status === 'verified') {
            smsCodeStatus.innerHTML = '<small class="text-success"><i class="fas fa-shield-check"></i> Phone number verified!</small>';
            phoneInput.readOnly = true;
            verifyBtn.style.display = 'none';
            if (smsCodeContainer) smsCodeContainer.classList.add('d-none');
            let hiddenInput = document.getElementById('phone_verified');
            if (!hiddenInput) {
              hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.id = 'phone_verified';
              hiddenInput.name = 'phone_verified';
              hiddenInput.value = '1';
              verifyBtn.parentNode.appendChild(hiddenInput);
            } else {
              hiddenInput.value = '1';
            }
          } else {
            smsCodeStatus.innerHTML = '<small class="text-danger">' + (verifyData.message || 'Invalid code. Please try again.') + '</small>';
          }
        })
        .catch(() => {
          smsCodeStatus.innerHTML = '<small class="text-danger">Verification failed. Try again.</small>';
        });
      });
    }
  });
  <?php if ($step == 3 && $user_type === 'supplier'): ?>
  (function(){
    const mapEl = document.getElementById('supplierMap');
    if (!mapEl) return;
    const defaultLat = 8.0611;
    const defaultLng = 123.7475;
    const map = L.map('supplierMap').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    let marker = null;
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    function setMarker(lat, lng) {
      if (marker) {
        map.removeLayer(marker);
      }
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.bindPopup("Your business location").openPopup();
      if (latInput) latInput.value = lat.toFixed(6);
      if (lngInput) lngInput.value = lng.toFixed(6);
      map.setView([lat, lng], 17);
      marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        if (latInput) latInput.value = position.lat.toFixed(6);
        if (lngInput) lngInput.value = position.lng.toFixed(6);
        updateAddressFromCoords(position.lat, position.lng);
      });
    }
    const presetLat = parseFloat(latInput?.value || '');
    const presetLng = parseFloat(lngInput?.value || '');
    if (!isNaN(presetLat) && !isNaN(presetLng) && presetLat !== 0 && presetLng !== 0) {
      setMarker(presetLat, presetLng);
    } else {
      setMarker(defaultLat, defaultLng);
    }
    map.on('click', function(e) {
      setMarker(e.latlng.lat, e.latlng.lng);
      updateAddressFromCoords(e.latlng.lat, e.latlng.lng);
    });
    const purokInput = document.querySelector('input[name="purok"]');
    const barangayHidden = document.getElementById('barangay');
    const barangaySelect = document.getElementById('barangay_select');
    const barangayOther = document.getElementById('barangay_other');
    const businessAddressInput = document.getElementById('business_address');
    let geocodeTimer;
    function buildAddressQuery() {
      const parts = [];
      if (purokInput && purokInput.value.trim().length) {
        parts.push(purokInput.value.trim());
      }
      if (barangayHidden && barangayHidden.value.trim().length) {
        parts.push(barangayHidden.value.trim());
      }
      parts.push('Tangub City', 'Misamis Occidental', 'Philippines');
      return parts.filter(Boolean).join(', ');
    }
    function queueGeocode() {
      clearTimeout(geocodeTimer);
      geocodeTimer = setTimeout(() => {
        const query = buildAddressQuery();
        if (query.length < 6) {
          return;
        }
        fetch(geocodeApiUrl + '?address=' + encodeURIComponent(query))
          .then(res => res.json())
          .then(data => {
            if (typeof data.lat !== 'undefined' && typeof data.lng !== 'undefined') {
              const lat = parseFloat(data.lat);
              const lng = parseFloat(data.lng);
              if (!isNaN(lat) && !isNaN(lng)) {
                setMarker(lat, lng);
                if (businessAddressInput && data.display_name) {
                  businessAddressInput.value = data.display_name;
                }
              }
            } else if (data.error) {
              console.error('Geocoding error:', data.error);
            }
          })
          .catch(err => console.error('Geocoding error:', err));
      }, 700);
    }
    function updateAddressFromCoords(lat, lng) {
      fetch(geocodeApiUrl + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
        .then(res => res.json())
        .then(data => {
          if (typeof data.lat !== 'undefined' && typeof data.lng !== 'undefined') {
            if (businessAddressInput && data.display_name) {
              businessAddressInput.value = data.display_name;
            }
          } else if (data.error) {
            console.error('Reverse geocoding error:', data.error);
          }
        })
        .catch(err => console.error('Reverse geocoding error:', err));
    }
    if (purokInput) {
      purokInput.addEventListener('input', queueGeocode);
    }
    if (barangaySelect) {
      barangaySelect.addEventListener('change', queueGeocode);
    }
    if (barangayOther) {
      barangayOther.addEventListener('input', queueGeocode);
    }
    queueGeocode();
  })();
  <?php endif; ?>
  <?php if ($step == 3): ?>
  (function(){
    const select = document.getElementById('barangay_select');
    const hiddenInput = document.getElementById('barangay');
    const otherInput = document.getElementById('barangay_other');
    if (!select || !hiddenInput) return;
    const barangays = [
  'Aquino (Marcos)',
  'Balatacan',
  'Baluk',
  'Banglay',
  'Bintana',
  'Bocator',
  'Bongabong',
  'Caniangan',
  'Capalaran',
  'Catagan',
  'Barangay I – City Hall (Poblacion)',
  'Barangay II – Marilou Annex (Poblacion)',
  'Barangay III – Market/Kalubian (Poblacion)',
  'Barangay IV – St. Michael (Poblacion)',
  'Barangay V – Malubog (Poblacion)',
  'Barangay VI – Lower Polao (Poblacion)',
  'Barangay VII – Upper Polao (Poblacion)',
  'Garang',
  'Guinabot',
  'Guinalaban',
  'Hoyohoy',
  'Isidro D. Tan (Dimalooc)',
  'Kauswagan',
  'Kimat',
  'Labuyo',
  'Lorenzo Tan',
  'Lumban',
  'Maloro',
  'Mantic',
  'Manga',
  'Maquilao',
  'Matugnaw',
  'Migcanaway',
  'Minsubong',
  'Owayan',
  'Paiton',
  'Panalsalan',
  'Pangabuan',
  'Prenza',
  'Salimpuno',
  'San Antonio',
  'San Apolinario',
  'San Vicente',
  'Santa Cruz',
  'Santa Maria (Baga)',
  'Santo Niño',
  'Sicot',
  'Silanga',
  'Silangit',
  'Simasay',
  'Sumirap',
  'Taguite',
  'Tituron',
  'Tugas',
  'Villaba',
  'Other...'
];
    barangays.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b === 'Other...' ? '' : b;
      opt.textContent = b;
      if (b === 'Other...') opt.dataset.other = '1';
      select.appendChild(opt);
    });
    if (hiddenInput.value) {
      const exists = Array.from(select.options).some(o => o.value === hiddenInput.value);
      if (exists) select.value = hiddenInput.value;
      else { select.value = ''; otherInput.classList.remove('d-none'); otherInput.value = hiddenInput.value; }
    }
    select.addEventListener('change', function(){
      const chosen = select.options[select.selectedIndex];
      if (chosen && chosen.dataset.other) {
        otherInput.classList.remove('d-none');
        otherInput.focus();
        hiddenInput.value = '';
      } else {
        otherInput.classList.add('d-none');
        otherInput.value = '';
        hiddenInput.value = select.value;
      }
    });
    otherInput && otherInput.addEventListener('input', function(){
      hiddenInput.value = this.value.trim();
    });
  })();
  <?php endif; ?>
  document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[name="contact_number"]');
    phoneInputs.forEach(function(input) {
      input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) {
          value = value.substring(0, 11);
        }
        e.target.value = value;
      });
      input.addEventListener('blur', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 0 && (value.length < 11 || value.substring(0, 2) !== '09')) {
          e.target.setCustomValidity('Please enter a valid Philippine mobile number starting with 09 (e.g., 09123456789).');
        } else if (value.length > 11) {
          e.target.setCustomValidity('Phone number is too long. Please enter a valid Philippine mobile number.');
        } else {
          e.target.setCustomValidity('');
        }
      });
    });
    const switcherLinks = document.querySelectorAll('.switcher a');
    switcherLinks.forEach(function(link) {
      link.addEventListener('click', function(e) {
        return true;
      });
    });
  });

</script>
</body>
</html>