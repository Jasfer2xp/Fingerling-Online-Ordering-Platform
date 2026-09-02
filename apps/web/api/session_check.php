<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    echo json_encode(['valid' => false, 'reason' => 'not_logged_in']);
    exit;
}

// Check if session is valid in database
$sql = "SELECT id FROM user_sessions WHERE id = ? AND user_id = ? AND expires_at > NOW()";
$session = $database->fetch($sql, [$_SESSION['session_id'], $_SESSION['user_id']]);

if ($session) {
    echo json_encode(['valid' => true]);
} else {
    // Session is invalid - clear session
    $_SESSION = [];
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    echo json_encode([
        'valid' => false, 
        'reason' => 'session_invalid', 
        'message' => 'Your account was logged in from another device. Please log in again if this wasn\'t you.'
    ]);
}
?>