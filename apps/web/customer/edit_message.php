<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$database = new Database();
$message = new Message($database);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_id = (int)($_POST['message_id'] ?? 0);
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($message_id) || empty($message_text)) {
        echo json_encode(['success' => false, 'error' => 'Message ID and text are required']);
        exit;
    }
    
    try {
        // Edit the message
        $result = $message->editMessage($message_id, $user_id, $message_text);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to edit message']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}