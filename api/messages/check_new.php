<?php
require_once '../../config/config.php';
require_once '../../includes/session_guard.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$user_id = get_user_id();
$user_type = get_user_type();
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$since_param = $_GET['since'] ?? '';
$since = '1970-01-01 00:00:00';

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_conversation_id']);
    exit;
}

if (!empty($since_param)) {
    $timestamp = strtotime($since_param);
    if ($timestamp !== false) {
        $since = date('Y-m-d H:i:s', $timestamp);
    }
}

try {
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
        echo json_encode(['success' => false, 'error' => 'conversation_not_found']);
        exit;
    }

    $messages = $database->fetchAll(
        "SELECT m.*, 
                u1.email as sender_email,
                c.first_name as sender_first_name,
                c.last_name as sender_last_name,
                s.business_name as sender_business_name
         FROM messages m
         JOIN users u1 ON m.sender_id = u1.id
         LEFT JOIN customers c ON u1.id = c.user_id
         LEFT JOIN suppliers s ON u1.id = s.user_id
         WHERE m.conversation_id = ? AND m.created_at > ?
         ORDER BY m.created_at ASC
         LIMIT 100",
        [$conversation_id, $since]
    );

    // Format timestamps - they're already stored in Manila time in database
    date_default_timezone_set('Asia/Manila');
    foreach ($messages as &$msg) {
        if (!empty($msg['created_at'])) {
            // Timestamp is already in Manila time, just format it
            $dt = new DateTime($msg['created_at'], new DateTimeZone('Asia/Manila'));
            $msg['formatted_time'] = $dt->format('g:i A');
        }
    }
    unset($msg);

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
} catch (Exception $e) {
    error_log('check_new messages error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'failed']);
}


