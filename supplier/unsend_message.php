<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

header('Content-Type: application/json');

// Unified response function
function send_json($success, $data = []) {
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    send_json(false, ['error' => 'Unauthorized']);
}

$user_id = get_user_id();
$database = new Database();
$message = new Message($database);

// Input validation
$message_id = (int)($_POST['message_id'] ?? 0);

if (empty($message_id)) {
    send_json(false, ['error' => 'Message ID is required']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify this message belongs to a conversation that belongs to the supplier
        $sql = "SELECT m.* 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN suppliers supp ON c.supplier_id = supp.id
                WHERE m.id = ? AND supp.user_id = ?";
        $message_data = $database->fetch($sql, [$message_id, $user_id]);

        if (!$message_data) {
            send_json(false, ['error' => 'Message not found or unauthorized']);
        }
        
        // Unsend the message
        $result = $message->unsendMessage($message_id, $user_id);
        
        if ($result) {
            send_json(true);
        } else {
            send_json(false, ['error' => 'Failed to unsend message']);
        }
    } catch (Exception $e) {
        send_json(false, ['error' => $e->getMessage()]);
    }
} else {
    send_json(false, ['error' => 'Invalid request method']);
}