<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    error_log("Session check API: User not logged in");
    echo json_encode(['valid' => false, 'reason' => 'not_logged_in']);
    exit;
}

// Log session data for debugging
$user_id = $_SESSION['user_id'];
$session_id = $_SESSION['session_id'];

error_log("Session check API called for user: $user_id, session_id: " . substr($session_id, 0, 20) . "...");

// Check if session is valid (same method as session_guard.php)
$valid = $database->fetch(
    "SELECT id FROM users WHERE id = ? AND current_session_id = ?",
    [$user_id, $session_id]
);

error_log("Session validation result for user " . $_SESSION['user_id'] . ": " . ($valid ? "VALID" : "INVALID"));

if ($valid) {
    echo json_encode(['valid' => true]);
} else {
    // Log the session invalidation
    error_log("Invalid session detected for user: $user_id. Logging out.");
    
    // Session is invalid - logout user
    try {
        $user = new User($database);
        $user->logout();
        error_log("User $user_id successfully logged out due to invalid session");
    } catch (Exception $e) {
        error_log("Error during logout for user $user_id: " . $e->getMessage());
    }
    
    echo json_encode([
        'valid' => false, 
        'reason' => 'session_invalid', 
        'message' => 'Your account was logged in from another device. Please log in again if this wasn\'t you.',
        'session_id' => $session_id
    ]);
}
?>