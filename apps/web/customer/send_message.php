<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

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
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($conversation_id) || empty($message_text)) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID and message are required']);
        exit;
    }
    
    try {
        // Verify this conversation belongs to the customer and get the supplier user ID
        $sql = "SELECT c.*, s.user_id as supplier_user_id 
                FROM conversations c 
                JOIN customers cust ON c.customer_id = cust.id 
                JOIN suppliers s ON c.supplier_id = s.id
                WHERE c.id = ? AND cust.user_id = ?";
        $conversation = $database->fetch($sql, [$conversation_id, $user_id]);
        
        if (!$conversation) {
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            exit;
        }
        
        // Send the message
        $message_id = $message->sendMessage($conversation_id, $user_id, $conversation['supplier_user_id'], $message_text);
        
        echo json_encode(['success' => true, 'message_id' => $message_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}