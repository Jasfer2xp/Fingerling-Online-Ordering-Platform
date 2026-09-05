<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id && is_logged_in() && get_user_id() == $user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = true WHERE user_id = ? AND is_read = false";
            $database->query($sql, [$user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating notifications']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}