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

$order_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['order_id'])) {
        $order_id = (int)$data['order_id'];
    } else {
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    }
} else {
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
}

if ($order_id <= 0) {
    respond(['success' => false, 'error' => 'invalid_order_id'], 400);
}

try {
    $order = $database->fetch(
        "SELECT o.id, o.customer_id, o.supplier_id
         FROM orders o
         WHERE o.id = ?",
        [$order_id]
    );
} catch (Exception $e) {
    error_log('get_conversation: order lookup failed - ' . $e->getMessage());
    respond(['success' => false, 'error' => 'order_lookup_failed'], 500);
}

if (!$order) {
    respond(['success' => false, 'error' => 'order_not_found'], 404);
}

require_once '../../classes/Message.php';
$messageService = new Message($database);

$conversation_id = $messageService->getOrCreateConversation(
    $order['customer_id'],
    $order['supplier_id'],
    $order_id
);

if (!$conversation_id) {
    respond(['success' => false, 'error' => 'conversation_failed'], 500);
}

respond(['success' => true, 'conversation_id' => (int)$conversation_id]);


