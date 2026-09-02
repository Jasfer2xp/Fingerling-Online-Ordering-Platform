<?php
require_once '../../config/config.php';
require_once '../../includes/session_guard.php';
require_once '../../config/database.php';
require_once '../../classes/Message.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$user_type = get_user_type();
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

try {
    $message = new Message($database);
    
    // Verify conversation belongs to user
    if ($user_type === 'customer') {
        $sql = "SELECT c.id FROM conversations c 
                JOIN customers cust ON c.customer_id = cust.id 
                WHERE c.id = ? AND cust.user_id = ?";
    } else {
        $sql = "SELECT c.id FROM conversations c 
                JOIN suppliers s ON c.supplier_id = s.id 
                WHERE c.id = ? AND s.user_id = ?";
    }
    
    $conversation = $database->fetch($sql, [$conversation_id, $user_id]);
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        exit;
    }
    
    // Get new messages since last_message_id
    $sql = "SELECT m.*, 
                   u1.email as sender_email,
                   c.first_name as sender_first_name,
                   c.last_name as sender_last_name,
                   s.business_name as sender_business_name
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN customers c ON u1.id = c.user_id
            LEFT JOIN suppliers s ON u1.id = s.user_id
            WHERE m.conversation_id = ? AND m.id > ?
            ORDER BY m.created_at ASC";
    
    $new_messages = $database->fetchAll($sql, [$conversation_id, $last_message_id]);
    
    // Mark messages as read for current user
    if (!empty($new_messages)) {
        $message_ids = array_column($new_messages, 'id');
        $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
        $database->query(
            "UPDATE messages SET is_read = 1 WHERE id IN ($placeholders) AND receiver_id = ?",
            array_merge($message_ids, [$user_id])
        );
    }
    
    // Format timestamps - they're already stored in Manila time in database
    date_default_timezone_set('Asia/Manila');
    foreach ($new_messages as &$msg) {
        if (!empty($msg['created_at'])) {
            // Timestamp is already in Manila time, just format it
            $dt = new DateTime($msg['created_at'], new DateTimeZone('Asia/Manila'));
            $msg['formatted_time'] = $dt->format('g:i A');
        }
    }
    unset($msg);
    
    echo json_encode([
        'success' => true,
        'messages' => $new_messages,
        'count' => count($new_messages)
    ]);
    
} catch (Exception $e) {
    error_log("Get new messages error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch messages']);
}

