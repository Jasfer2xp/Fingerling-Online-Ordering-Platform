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
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message_text = trim($_POST['message'] ?? '');

if (empty($conversation_id) || empty($message_text)) {
    send_json(false, ['error' => 'Conversation ID and message are required']);
}

if (strlen($message_text) > 1000) { // Max 1000 characters
    send_json(false, ['error' => 'Message too long']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($conversation_id) || empty($message_text)) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID and message are required']);
        exit;
    }
    
    try {
        // Verify this conversation belongs to the supplier and get the customer user ID
        $sql = "SELECT c.*, cust.user_id as customer_user_id 
                FROM conversations c 
                JOIN suppliers supp ON c.supplier_id = supp.id 
                JOIN customers cust ON c.customer_id = cust.id
                WHERE c.id = ? AND supp.user_id = ?";
        $conversation = $database->fetch($sql, [$conversation_id, $user_id]);

        if (!$conversation) {
            send_json(false, ['error' => 'Conversation not found']);
        }
        
        // Send the message
        $message_id = $message->sendMessage($conversation_id, $user_id, $conversation['customer_user_id'], $message_text);

        // Optional: Log message
        // error_log("Supplier sent message: {$message_text}");

        // Use Asia/Manila timezone for timestamp (same as Message class)
        date_default_timezone_set('Asia/Manila');
        send_json(true, [
            'message_id' => $message_id,
            'message' => $message_text,
            'timestamp' => date('g:i A')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}