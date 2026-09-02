<?php

if (!function_exists('send_auto_payment_message')) {
    require_once __DIR__ . '/../classes/Message.php';

    /**
     * Send automated payment confirmation message to customer
     * This function is called when payment is successfully completed (Xendit PAID or PayPal Complete)
     * 
     * @param Database $database Database connection instance
     * @param int $orderId Order ID that was paid
     * @return bool True if message sent successfully, false otherwise
     */
    function send_auto_payment_message($database, $orderId)
    {
        if (empty($orderId) || $orderId <= 0) {
            error_log('AUTO MESSAGE: Invalid order ID provided: ' . $orderId);
            return false;
        }

        static $messageService = null;
        static $columnsEnsured = false;

        if ($messageService === null) {
            try {
                $messageService = new Message($database);
            } catch (Exception $e) {
                error_log('AUTO MESSAGE: Failed to initialize Message service - ' . $e->getMessage());
                return false;
            }
        }

        if (!$columnsEnsured) {
            try {
                // Check if columns exist
                $columns = $database->fetchAll(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'"
                );
                $names = array_map(static fn($row) => $row['COLUMN_NAME'], $columns ?? []);
                
                $columnsAdded = false;
                
                // Add is_auto column if it doesn't exist
                if (!in_array('is_auto', $names, true)) {
                    try {
                        $database->query("ALTER TABLE messages ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER message");
                        error_log('AUTO MESSAGE: Added is_auto column to messages table');
                        $columnsAdded = true;
                    } catch (Exception $e) {
                        error_log('AUTO MESSAGE: Failed to add is_auto column - ' . $e->getMessage());
                    }
                }
                
                // Add order_id column if it doesn't exist
                if (!in_array('order_id', $names, true)) {
                    try {
                        $database->query("ALTER TABLE messages ADD COLUMN order_id INT NULL AFTER is_auto");
                        error_log('AUTO MESSAGE: Added order_id column to messages table');
                        $columnsAdded = true;
                    } catch (Exception $e) {
                        error_log('AUTO MESSAGE: Failed to add order_id column - ' . $e->getMessage());
                    }
                }
                
                // Add index if columns were just added
                if ($columnsAdded) {
                    try {
                        // Check if index exists
                        $indexes = $database->fetchAll(
                            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'idx_messages_order_auto'"
                        );
                        if (empty($indexes)) {
                            $database->query("CREATE INDEX idx_messages_order_auto ON messages (order_id, is_auto)");
                            error_log('AUTO MESSAGE: Added index idx_messages_order_auto');
                        }
                    } catch (Exception $e) {
                        error_log('AUTO MESSAGE: Failed to add index - ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('AUTO MESSAGE: unable to ensure columns - ' . $e->getMessage());
            }
            $columnsEnsured = true;
        }

        try {
            $orderInfo = $database->fetch(
                "SELECT o.id, o.customer_id, o.supplier_id,
                        c.user_id AS customer_user_id,
                        s.user_id AS supplier_user_id,
                        s.business_name
                 FROM orders o
                 JOIN customers c ON o.customer_id = c.id
                 JOIN suppliers s ON o.supplier_id = s.id
                 WHERE o.id = ?",
                [$orderId]
            );
        } catch (Exception $e) {
            error_log('AUTO MESSAGE: failed fetching order ' . $orderId . ' - ' . $e->getMessage());
            return false;
        }

        if (!$orderInfo || empty($orderInfo['customer_user_id']) || empty($orderInfo['supplier_user_id'])) {
            return false;
        }

        // Check if auto message already sent for this order
        try {
            $existing = $database->fetch(
                "SELECT id FROM messages WHERE order_id = ? AND is_auto = 1 LIMIT 1",
                [$orderId]
            );
            if ($existing) {
                return true; // Already sent, prevent duplicate
            }
        } catch (Exception $e) {
            // ignore and attempt to create message
        }

        // Build the automated message with business name
        $business_name = $orderInfo['business_name'] ?? 'us';
        $autoText = "Thank you for purchasing from {$business_name}. Your order is now being prepared. Please stay active on your phone to receive updates. If you have any concerns, kindly reply to this message — you may speak Bisaya if you prefer — and wait for our response.";

        // Use getOrCreateConversation which handles lookup and creation more robustly
        $conversationId = null;
        try {
            // First try to find conversation by order_id
            $conversation = $database->fetch(
                "SELECT id, order_id, is_archived FROM conversations WHERE order_id = ? LIMIT 1",
                [$orderId]
            );
            
            if ($conversation && !empty($conversation['id'])) {
                $conversationId = (int) $conversation['id'];
                
                // Unarchive conversation if it was archived
                if (!empty($conversation['is_archived'])) {
                    try {
                        $database->query(
                            "UPDATE conversations SET is_archived = 0, updated_at = NOW() WHERE id = ?",
                            [$conversationId]
                        );
                        error_log("AUTO MESSAGE: Unarchived conversation {$conversationId} for order {$orderId}");
                    } catch (Exception $e) {
                        error_log("AUTO MESSAGE: Failed to unarchive conversation - " . $e->getMessage());
                    }
                }
                
                error_log("AUTO MESSAGE: Found existing conversation {$conversationId} by order_id for order {$orderId}");
            } else {
                // Use getOrCreateConversation to find or create by customer_id and supplier_id
                $conversationId = $messageService->getOrCreateConversation(
                    $orderInfo['customer_id'],
                    $orderInfo['supplier_id'],
                    $orderId
                );
                
                if ($conversationId) {
                    // Ensure the conversation has the order_id set and is not archived
                    try {
                        $update_check = $database->fetch(
                            "SELECT order_id, is_archived FROM conversations WHERE id = ?",
                            [$conversationId]
                        );
                        $needs_update = false;
                        $update_fields = [];
                        
                        if (empty($update_check['order_id'])) {
                            $needs_update = true;
                            $update_fields[] = "order_id = " . (int)$orderId;
                        }
                        if (!empty($update_check['is_archived'])) {
                            $needs_update = true;
                            $update_fields[] = "is_archived = 0";
                        }
                        
                        if ($needs_update) {
                            $database->query(
                                "UPDATE conversations SET " . implode(", ", $update_fields) . ", updated_at = NOW() WHERE id = ?",
                                [$conversationId]
                            );
                            error_log("AUTO MESSAGE: Updated conversation {$conversationId} with order_id {$orderId} and unarchived it");
                        }
                    } catch (Exception $e) {
                        error_log("AUTO MESSAGE: Failed to update conversation - " . $e->getMessage());
                        // Continue anyway, conversation exists
                    }
                    
                    error_log("AUTO MESSAGE: Got or created conversation {$conversationId} for order {$orderId}");
                } else {
                    error_log("AUTO MESSAGE: Failed to get or create conversation for order {$orderId}");
                }
            }
        } catch (Exception $e) {
            error_log('AUTO MESSAGE: Exception getting/creating conversation - ' . $e->getMessage());
            error_log('AUTO MESSAGE: Stack trace - ' . $e->getTraceAsString());
        }

        if (!$conversationId) {
            error_log("AUTO MESSAGE: No conversation ID available for order {$orderId}");
            return false;
        }

        // Insert the automated message with correct timezone
        try {
            // Use Asia/Manila timezone for timestamp
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            
            $result = $database->query(
                "INSERT INTO messages (conversation_id, sender_id, receiver_id, message, is_auto, order_id, is_read, created_at) 
                 VALUES (?, ?, ?, ?, 1, ?, 0, ?)",
                [
                    $conversationId,
                    $orderInfo['supplier_user_id'],
                    $orderInfo['customer_user_id'],
                    $autoText,
                    $orderId,
                    $manila_time
                ]
            );
            
            $message_id = $database->lastInsertId();
            error_log("AUTO MESSAGE: Successfully sent auto message (ID: {$message_id}) for order {$orderId} in conversation {$conversationId}");
            return true;
        } catch (Exception $e) {
            error_log('AUTO MESSAGE: Insert failed for order ' . $orderId . ' - ' . $e->getMessage());
            error_log('AUTO MESSAGE: SQL Error - ' . print_r([
                'conversation_id' => $conversationId,
                'sender_id' => $orderInfo['supplier_user_id'],
                'receiver_id' => $orderInfo['customer_user_id'],
                'order_id' => $orderId
            ], true));
            return false;
        }
    }
}

