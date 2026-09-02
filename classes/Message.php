<?php

/**
 * Message Class
 * Handles in-app messaging between customers and suppliers
 */

class Message {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if messaging tables exist
     */
    private function tablesExist() {
        try {
            $this->db->query("SELECT 1 FROM conversations LIMIT 1");
            $this->db->query("SELECT 1 FROM messages LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create a new conversation between customer and supplier
     */
    public function createConversation($customer_id, $supplier_id, $order_id = null) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            $sql = "INSERT INTO conversations (customer_id, supplier_id, order_id) VALUES (?, ?, ?)";
            $this->db->query($sql, [$customer_id, $supplier_id, $order_id]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or create conversation between customer and supplier
     */
    public function getOrCreateConversation($customer_id, $supplier_id, $order_id = null) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Check if conversation already exists
            $sql = "SELECT id, order_id FROM conversations WHERE customer_id = ? AND supplier_id = ?";
            $conversation = $this->db->fetch($sql, [$customer_id, $supplier_id]);
            
            if ($conversation) {
                $conversation_id = $conversation['id'];
                
                // If order_id is provided and conversation doesn't have one, update it
                if ($order_id && empty($conversation['order_id'])) {
                    try {
                        $update_sql = "UPDATE conversations SET order_id = ? WHERE id = ?";
                        $this->db->query($update_sql, [$order_id, $conversation_id]);
                        error_log("Message: Updated conversation {$conversation_id} with order_id {$order_id}");
                    } catch (Exception $e) {
                        error_log("Message: Failed to update conversation order_id - " . $e->getMessage());
                        // Continue anyway, conversation exists
                    }
                }
                
                return $conversation_id;
            }
            
            // Create new conversation
            return $this->createConversation($customer_id, $supplier_id, $order_id);
        } catch (Exception $e) {
            error_log("Failed to get or create conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a message in a conversation
     */
    public function sendMessage($conversation_id, $sender_id, $receiver_id, $message) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Use Asia/Manila timezone for timestamp
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO messages (conversation_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, ?)";
            $this->db->query($sql, [$conversation_id, $sender_id, $receiver_id, $message, $manila_time]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to send message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get conversation details
     */
    public function getConversation($conversation_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            $sql = "SELECT c.*, 
                           cust.first_name as customer_first_name, 
                           cust.last_name as customer_last_name,
                           supp.business_name as supplier_name
                    FROM conversations c
                    JOIN customers cust ON c.customer_id = cust.id
                    JOIN suppliers supp ON c.supplier_id = supp.id
                    WHERE c.id = ?";
            return $this->db->fetch($sql, [$conversation_id]);
        } catch (Exception $e) {
            error_log("Failed to get conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get messages for a conversation
     */
    public function getMessages($conversation_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return [];
        }
        
        try {
            $sql = "SELECT m.*, 
                           u1.email as sender_email,
                           u2.email as receiver_email,
                           c.first_name as sender_first_name,
                           c.last_name as sender_last_name,
                           s.business_name as sender_business_name,
                           cr.first_name as receiver_first_name,
                           cr.last_name as receiver_last_name,
                           sr.business_name as receiver_business_name
                    FROM messages m
                    JOIN users u1 ON m.sender_id = u1.id
                    JOIN users u2 ON m.receiver_id = u2.id
                    LEFT JOIN customers c ON u1.id = c.user_id
                    LEFT JOIN suppliers s ON u1.id = s.user_id
                    LEFT JOIN customers cr ON u2.id = cr.user_id
                    LEFT JOIN suppliers sr ON u2.id = sr.user_id
                    WHERE m.conversation_id = ?
                    ORDER BY m.created_at ASC";
            return $this->db->fetchAll($sql, [$conversation_id]);
        } catch (Exception $e) {
            error_log("Failed to get messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get conversations for a user (customer or supplier)
     */
    public function getUserConversations($user_id, $user_type) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return [];
        }
        
        try {
            if ($user_type === 'customer') {
                $sql = "SELECT c.*, 
                               s.business_name as supplier_name,
                               s.user_id as supplier_user_id,
                               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
                               (SELECT m.message FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                               (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time
                        FROM conversations c
                        JOIN suppliers s ON c.supplier_id = s.id
                        WHERE c.customer_id = (SELECT id FROM customers WHERE user_id = ?)
                        AND c.is_archived = 0
                        ORDER BY (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) DESC";
                return $this->db->fetchAll($sql, [$user_id, $user_id]);
            } else if ($user_type === 'supplier') {
                $sql = "SELECT c.*, 
                               CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                               cust.user_id as customer_user_id,
                               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
                               (SELECT m.message FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                               (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time
                        FROM conversations c
                        JOIN customers cust ON c.customer_id = cust.id
                        WHERE c.supplier_id = (SELECT id FROM suppliers WHERE user_id = ?)
                        AND c.is_archived = 0
                        ORDER BY (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) DESC";
                return $this->db->fetchAll($sql, [$user_id, $user_id]);
            }
        } catch (Exception $e) {
            error_log("Failed to get user conversations: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($conversation_id, $user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            $sql = "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?";
            return $this->db->query($sql, [$conversation_id, $user_id]);
        } catch (Exception $e) {
            error_log("Failed to mark messages as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Edit a message
     */
    public function editMessage($message_id, $user_id, $new_message) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Verify that the user is the sender of the message
            $sql = "SELECT sender_id FROM messages WHERE id = ?";
            $message_data = $this->db->fetch($sql, [$message_id]);
            
            if (!$message_data || $message_data['sender_id'] != $user_id) {
                return false;
            }
            
            // Update the message
            $sql = "UPDATE messages SET message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, [$new_message, $message_id]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to edit message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread messages count for a user
     */
    public function getUnreadCount($user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return 0;
        }
        
        try {
            $sql = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
            $result = $this->db->fetch($sql, [$user_id]);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Failed to get unread messages count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Unsend a message (mark as unsent but keep in database)
     */
    public function unsendMessage($message_id, $user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Verify that the user is the sender of the message
            $sql = "SELECT sender_id, message FROM messages WHERE id = ?";
            $message_data = $this->db->fetch($sql, [$message_id]);
            
            if (!$message_data || $message_data['sender_id'] != $user_id) {
                return false;
            }
            
            // Update the message to mark it as unsent
            $sql = "UPDATE messages SET message = ?, is_unsent = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, ['[unsent]', $message_id]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to unsend message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Archive a conversation for a user
     */
    public function archiveConversation($conversation_id, $user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Verify that the user is part of the conversation
            $sql = "SELECT id FROM conversations 
                    WHERE id = ? AND (customer_id = (SELECT id FROM customers WHERE user_id = ?) 
                    OR supplier_id = (SELECT id FROM suppliers WHERE user_id = ?))";
            $conversation = $this->db->fetch($sql, [$conversation_id, $user_id, $user_id]);
            
            if (!$conversation) {
                return false;
            }
            
            // Archive the conversation
            $sql = "UPDATE conversations SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, [$conversation_id]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to archive conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unarchive a conversation for a user
     */
    public function unarchiveConversation($conversation_id, $user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Verify that the user is part of the conversation
            $sql = "SELECT id FROM conversations 
                    WHERE id = ? AND (customer_id = (SELECT id FROM customers WHERE user_id = ?) 
                    OR supplier_id = (SELECT id FROM suppliers WHERE user_id = ?))";
            $conversation = $this->db->fetch($sql, [$conversation_id, $user_id, $user_id]);
            
            if (!$conversation) {
                return false;
            }
            
            // Unarchive the conversation
            $sql = "UPDATE conversations SET is_archived = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, [$conversation_id]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to unarchive conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a conversation and all its messages
     */
    public function deleteConversation($conversation_id, $user_id) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return false;
        }
        
        try {
            // Verify that the user is part of the conversation
            $sql = "SELECT id FROM conversations 
                    WHERE id = ? AND (customer_id = (SELECT id FROM customers WHERE user_id = ?) 
                    OR supplier_id = (SELECT id FROM suppliers WHERE user_id = ?))";
            $conversation = $this->db->fetch($sql, [$conversation_id, $user_id, $user_id]);
            
            if (!$conversation) {
                return false;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Delete all messages in the conversation
            $sql = "DELETE FROM messages WHERE conversation_id = ?";
            $this->db->query($sql, [$conversation_id]);
            
            // Delete the conversation
            $sql = "DELETE FROM conversations WHERE id = ?";
            $this->db->query($sql, [$conversation_id]);
            
            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            error_log("Failed to delete conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get archived conversations for a user (customer or supplier)
     */
    public function getArchivedConversations($user_id, $user_type) {
        // Check if tables exist
        if (!$this->tablesExist()) {
            return [];
        }
        
        try {
            if ($user_type === 'customer') {
                $sql = "SELECT c.*, 
                               s.business_name as supplier_name,
                               s.user_id as supplier_user_id,
                               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
                               (SELECT m.message FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                               (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time
                        FROM conversations c
                        JOIN suppliers s ON c.supplier_id = s.id
                        WHERE c.customer_id = (SELECT id FROM customers WHERE user_id = ?)
                        AND c.is_archived = 1
                        ORDER BY (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) DESC";
                return $this->db->fetchAll($sql, [$user_id, $user_id]);
            } else if ($user_type === 'supplier') {
                $sql = "SELECT c.*, 
                               CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                               cust.user_id as customer_user_id,
                               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
                               (SELECT m.message FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                               (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time
                        FROM conversations c
                        JOIN customers cust ON c.customer_id = cust.id
                        WHERE c.supplier_id = (SELECT id FROM suppliers WHERE user_id = ?)
                        AND c.is_archived = 1
                        ORDER BY (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) DESC";
                return $this->db->fetchAll($sql, [$user_id, $user_id]);
            }
        } catch (Exception $e) {
            error_log("Failed to get archived conversations: " . $e->getMessage());
        }
        
        return [];
    }
}