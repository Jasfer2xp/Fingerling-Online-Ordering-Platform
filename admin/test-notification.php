<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$type = $input['type'];
$user_id = get_user_id();

try {
    switch ($type) {
        case 'email':
            // Test email notification
            $to = $_SESSION['user_email'] ?? 'admin@example.com';
            $subject = 'Test Email Notification - ' . APP_NAME;
            $message = "This is a test email notification sent from " . APP_NAME . " admin panel.\n\n";
            $message .= "If you received this email, your email notification settings are working correctly.\n\n";
            $message .= "Sent at: " . date('Y-m-d H:i:s') . "\n";
            $message .= "From: Admin Panel";
            
            $headers = "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
            $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            if (mail($to, $subject, $message, $headers)) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $to]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send test email']);
            }
            break;
            
        case 'sms':
            // Test SMS notification (placeholder - would need SMS service integration)
            echo json_encode([
                'success' => true, 
                'message' => 'SMS testing is not configured yet. Please configure SMS service in settings.'
            ]);
            break;
            
        case 'push':
            // Test push notification (placeholder - would need push service integration)
            echo json_encode([
                'success' => true, 
                'message' => 'Push notification testing is not configured yet. Please configure push service in settings.'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid notification type']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
