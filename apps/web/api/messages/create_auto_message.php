<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'invalid_method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;

if ($order_id <= 0) {
    respond(['success' => false, 'error' => 'invalid_order_id'], 400);
}

try {
    $order = $database->fetch(
        "SELECT o.id, o.customer_id, o.supplier_id,
                cust.user_id AS customer_user_id,
                supp.user_id AS supplier_user_id
         FROM orders o
         JOIN customers cust ON o.customer_id = cust.id
         JOIN suppliers supp ON o.supplier_id = supp.id
         WHERE o.id = ?",
        [$order_id]
    );
} catch (Exception $e) {
    error_log('create_auto_message: order lookup failed - ' . $e->getMessage());
    respond(['success' => false, 'error' => 'order_lookup_failed'], 500);
}

if (!$order || empty($order['customer_user_id']) || empty($order['supplier_user_id'])) {
    respond(['success' => false, 'error' => 'order_not_found'], 404);
}

try {
    $existing_message = $database->fetch(
        "SELECT id, conversation_id FROM messages WHERE order_id = ? AND is_auto = 1 LIMIT 1",
        [$order_id]
    );

    if ($existing_message) {
        respond([
            'success' => true,
            'conversation_id' => (int)$existing_message['conversation_id'],
            'message_id' => (int)$existing_message['id'],
            'already_exists' => true
        ]);
    }
} catch (Exception $e) {
    error_log('create_auto_message: duplicate check failed - ' . $e->getMessage());
    // Continue; we can still attempt to insert
}

/**
 * Find or create the conversation for the given order.
 */
function ensureConversation(Database $database, array $orderRow, int $orderId): ?int
{
    try {
        $conversation = $database->fetch(
            "SELECT id, is_archived FROM conversations WHERE order_id = ? LIMIT 1",
            [$orderId]
        );

        if ($conversation) {
            $conversation_id = (int)$conversation['id'];
            if (!empty($conversation['is_archived'])) {
                $database->query(
                    "UPDATE conversations SET is_archived = 0, updated_at = NOW() WHERE id = ?",
                    [$conversation_id]
                );
            }
            return $conversation_id;
        }

        $conversation = $database->fetch(
            "SELECT id, order_id, is_archived FROM conversations WHERE customer_id = ? AND supplier_id = ? LIMIT 1",
            [$orderRow['customer_id'], $orderRow['supplier_id']]
        );

        if ($conversation) {
            $conversation_id = (int)$conversation['id'];
            $updates = [];
            $params = [];

            if (empty($conversation['order_id'])) {
                $updates[] = "order_id = ?";
                $params[] = $orderId;
            }

            if (!empty($conversation['is_archived'])) {
                $updates[] = "is_archived = 0";
            }

            if ($updates) {
                $set_clause = implode(', ', $updates);
                $params[] = $conversation_id;
                $database->query(
                    "UPDATE conversations SET {$set_clause}, updated_at = NOW() WHERE id = ?",
                    $params
                );
            }

            return $conversation_id;
        }

        $database->query(
            "INSERT INTO conversations (customer_id, supplier_id, order_id, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())",
            [$orderRow['customer_id'], $orderRow['supplier_id'], $orderId]
        );

        return (int)$database->lastInsertId();
    } catch (Exception $e) {
        error_log('create_auto_message: conversation ensure failed - ' . $e->getMessage());
        return null;
    }
}

$conversation_id = ensureConversation($database, $order, $order_id);

if (!$conversation_id) {
    respond(['success' => false, 'error' => 'conversation_failed'], 500);
}

$auto_message = "Your order is now being scheduled by the supplier. Please be active to your phone and messages.";

try {
    // Use Asia/Manila timezone for timestamp
    date_default_timezone_set('Asia/Manila');
    $manila_time = date('Y-m-d H:i:s');
    
    $database->query(
        "INSERT INTO messages (
            conversation_id, sender_id, receiver_id, message, is_auto, order_id, is_read, created_at
         ) VALUES (?, ?, ?, ?, 1, ?, 0, ?)",
        [
            $conversation_id,
            $order['supplier_user_id'],
            $order['customer_user_id'],
            $auto_message,
            $order_id,
            $manila_time
        ]
    );

    $message_id = (int)$database->lastInsertId();
    respond([
        'success' => true,
        'conversation_id' => $conversation_id,
        'message_id' => $message_id,
        'already_exists' => false
    ]);
} catch (Exception $e) {
    error_log('create_auto_message: insert failed - ' . $e->getMessage());
    respond(['success' => false, 'error' => 'insert_failed'], 500);
}


