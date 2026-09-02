<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is a supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$message = new Message($database);
$profile = $user->getUserProfile($user_id);

if (!function_exists('convert_to_manila_datetime')) {
    function convert_to_manila_datetime($timestamp)
    {
        if (empty($timestamp)) {
            return null;
        }

        try {
            $dt = new DateTime($timestamp, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            try {
                $dt = new DateTime($timestamp);
            } catch (Exception $e2) {
                return null;
            }
        }

        return $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    }

    function format_manila_time($timestamp, $format = 'g:i A')
    {
        $dt = convert_to_manila_datetime($timestamp);
        return $dt ? $dt->format($format) : '';
    }

    function time_ago_manila($timestamp)
    {
        $dt = convert_to_manila_datetime($timestamp);
        return $dt ? time_ago($dt->format('Y-m-d H:i:s')) : '';
    }
}

// Handle archive/unarchive/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['conversation_id'])) {
        $conversation_id = (int)$_POST['conversation_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'archive':
                $message->archiveConversation($conversation_id, $user_id);
                $_SESSION['success'] = 'Conversation archived successfully.';
                redirect(base_url('supplier/messages.php?archived=1'));
                break;
                
            case 'unarchive':
                $message->unarchiveConversation($conversation_id, $user_id);
                $_SESSION['success'] = 'Conversation unarchived successfully.';
                redirect(base_url('supplier/messages.php'));
                break;
                
            case 'delete':
                $message->deleteConversation($conversation_id, $user_id);
                $_SESSION['success'] = 'Conversation deleted successfully.';
                if (isset($_GET['conversation_id']) && (int)$_GET['conversation_id'] === $conversation_id) {
                    redirect(base_url('supplier/messages.php'));
                }
                break;
        }
        
        $redirect_url = base_url('supplier/messages.php');
        if (isset($_GET['archived'])) {
            $redirect_url .= '?archived=1';
        } else if (isset($_GET['conversation_id'])) {
            $redirect_url .= '?conversation_id=' . (int)$_GET['conversation_id'];
        }
        redirect($redirect_url);
    }
}

$page_title = 'Messages';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<!-- ===================== MESSAGES PAGE (SUPPLIER) ===================== -->
<div class="main-content">
    <div class="container-xl py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="fas fa-comments text-primary me-3" style="font-size: 1.8rem;"></i> Messages
            </h2>
            <div>
                <?php if (isset($_GET['archived'])): ?>
                    <a href="<?php echo base_url('supplier/messages.php'); ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Inbox
                    </a>
                <?php else: ?>
                    <a href="<?php echo base_url('supplier/messages.php?archived=1'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-archive me-2"></i>Archived
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success Alert -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Conversations List -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-semibold text-dark">
                            <?php echo isset($_GET['archived']) ? 'Archived Conversations' : 'Conversations'; ?>
                        </h5>
                    </div>
                    <div class="list-group list-group-flush overflow-auto" style="max-height: 70vh;">
                        <?php
                        if (isset($_GET['archived'])) {
                            $conversations = $message->getArchivedConversations($user_id, 'supplier');
                        } else {
                            $conversations = $message->getUserConversations($user_id, 'supplier');
                        }
                        $selected_conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;

                        if (!empty($conversations)):
                            foreach ($conversations as $conversation):
                                $is_selected = $selected_conversation_id == $conversation['id'];
                                $unread_count = $conversation['unread_count'] ?? 0;
                                ?>
                                <a href="?conversation_id=<?php echo $conversation['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $is_selected ? 'active' : ''; ?> border-0 rounded-0 py-3 px-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1 me-3">
                                            <div class="d-flex align-items-center mb-1">
                                                <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 160px;">
                                                    <?php echo htmlspecialchars($conversation['customer_name']); ?>
                                                </h6>
                                                <?php if ($unread_count > 0): ?>
                                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $unread_count; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($conversation['last_message'])): ?>
                                                <p class="mb-1 small text-muted text-truncate" style="max-width: 180px;">
                                                    <?php echo htmlspecialchars($conversation['last_message']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <?php echo time_ago_manila($conversation['last_message_time']); ?>
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-muted p-0" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (!isset($_GET['archived'])): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                                            <input type="hidden" name="action" value="archive">
                                                            <button type="submit" class="dropdown-item small">
                                                                <i class="fas fa-archive me-2"></i>Archive
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                                            <input type="hidden" name="action" value="unarchive">
                                                            <button type="submit" class="dropdown-item small">
                                                                <i class="fas fa-undo me-2"></i>Unarchive
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline delete-conversation-form">
                                                        <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="dropdown-item text-danger small">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </a>
                            <?php
                            endforeach;
                        else:
                            ?>
                            <div class="list-group-item text-center py-5">
                                <i class="fas fa-comment-slash text-muted mb-3" style="font-size: 2.5rem;"></i>
                                <p class="mb-0 text-muted">
                                    <?php echo isset($_GET['archived']) ? 'No archived conversations.' : 'No conversations yet.'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="col-lg-8">
                <?php
                $selected_conversation = null;
                $conversation_messages = [];

                if ($selected_conversation_id) {
                    $sql = "SELECT c.* FROM conversations c 
                            JOIN suppliers supp ON c.supplier_id = supp.id 
                            WHERE c.id = ? AND supp.user_id = ?";
                    $result = $database->fetch($sql, [$selected_conversation_id, $user_id]);

                    if ($result) {
                        $selected_conversation = $message->getConversation($selected_conversation_id);
                        $conversation_messages = $message->getMessages($selected_conversation_id);
                        $message->markAsRead($selected_conversation_id, $user_id);
                    }
                }
                ?>

                <?php if ($selected_conversation): ?>
                    <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 45px; height: 45px;">
                                        <i class="fas fa-user text-primary" style="font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-semibold">
                                        <?php echo htmlspecialchars(($selected_conversation['customer_first_name'] ?? '') . ' ' . ($selected_conversation['customer_last_name'] ?? '')); ?>
                                    </h5>
                                    <small class="text-muted">Customer</small>
                                </div>
                            </div>
                        </div>

                        <div class="card-body flex-grow-1 overflow-auto p-4" id="messages-container" style="max-height: 60vh;">
                            <?php if (!empty($conversation_messages)): ?>
                                <?php foreach ($conversation_messages as $msg): 
                                    // Determine if the message should be shown as sent by the supplier
                                    // This includes:
                                    // 1. Messages actually sent by the supplier ($msg['sender_id'] == $user_id)
                                    // 2. System messages (sender_id = 1) that are order confirmations
                                    $is_supplier_message = ($msg['sender_id'] == $user_id) || 
                                                          ($msg['sender_id'] == 1 && 
                                                           stripos($msg['message'], 'Hello ' . ($selected_conversation['customer_first_name'] ?? '')) === 0 &&
                                                           stripos($msg['message'], 'Thank you for purchasing from') !== false);
                                    ?>
                                    <div class="mb-4 <?php echo $is_supplier_message ? 'text-end' : ''; ?>">
                                        <div class="d-inline-block p-3 rounded position-relative <?php echo $is_supplier_message ? 'bg-primary text-white' : 'bg-light'; ?>"
                                             style="max-width: 75%; <?php echo $is_supplier_message
                                                 ? 'border-radius: 20px 20px 6px 20px;'
                                                 : 'border-radius: 20px 20px 20px 6px;'; ?>"
                                             data-message-id="<?php echo $msg['id']; ?>">
                                            <?php if (!empty($msg['is_unsent']) && $msg['is_unsent'] == 1): ?>
                                                <div class="message-content mb-1 fst-italic text-muted">
                                                    <?php 
                                                    if ($msg['sender_id'] == $user_id) {
                                                        echo 'You unsent a message';
                                                    } else {
                                                        echo htmlspecialchars(($msg['sender_business_name'] ?? $msg['sender_first_name'] ?? 'User')) . ' unsent a message';
                                                    }
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="message-content mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <small class="<?php echo $is_supplier_message ? 'text-white-50' : 'text-muted'; ?>">
                                                        <?php echo format_manila_time($msg['created_at']); ?>
                                                    </small>
                                                    <?php if ($is_supplier_message && empty($msg['is_unsent'])): ?>
                                                        <span class="message-actions ms-3" style="display: none;">
                                                            <a href="#" class="delete-message text-white-50" data-message-id="<?php echo $msg['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-comment-dots fa-3x mb-3"></i>
                                    <p>No messages yet. Say hello!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-white border-0 p-4">
                            <form id="message-form" method="post" action="send_message.php">
                                <input type="hidden" name="conversation_id" value="<?php echo $selected_conversation_id; ?>">
                                <div class="input-group">
                                    <textarea class="form-control border-0 shadow-sm" 
                                              name="message" 
                                              placeholder="Type your message..." 
                                              rows="2" 
                                              required 
                                              style="resize: none;"></textarea>
                                    <button class="btn btn-primary px-4" type="submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center">
                        <div class="text-center text-muted">
                            <i class="fas fa-comments fa-4x mb-4 text-light"></i>
                            <h5 class="fw-semibold">Select a conversation</h5>
                            <p>Choose a customer from the list to start chatting.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===================== JS: Sidebar Toggle + Dropdown Fix ===================== -->
<script>
// Wait for DOM
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const toggler = document.getElementById('sidebar-toggler');

    // Toggle sidebar on mobile
    if (toggler && sidebar && backdrop) {
        toggler.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });

        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            this.classList.add('d-none');
        });
    }

    // Auto-scroll messages
    const msgContainer = document.getElementById('messages-container');
    if (msgContainer) msgContainer.scrollTop = msgContainer.scrollHeight;

    // Confirm delete
    document.querySelectorAll('.delete-conversation-form').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Delete this conversation? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Message hover actions
    document.querySelectorAll('[data-message-id]').forEach(bubble => {
        const isOwn = bubble.closest('.text-end');
        if (!isOwn) return;

        bubble.addEventListener('mouseenter', () => {
            const actions = bubble.querySelector('.message-actions');
            if (actions) actions.style.display = 'inline';
        });
        bubble.addEventListener('mouseleave', () => {
            const actions = bubble.querySelector('.message-actions');
            if (actions) actions.style.display = 'none';
        });
    });

    // Send message with optimistic UI
    const form = document.getElementById('message-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(form);
            const text = fd.get('message').trim();
            if (!text) return;

            const container = document.getElementById('messages-container');
            const temp = document.createElement('div');
            temp.className = 'mb-4 text-end';
            temp.innerHTML = `
                <div class="d-inline-block p-3 rounded bg-primary text-white" style="max-width:75%; border-radius:20px 20px 6px 20px;">
                    <div class="message-content mb-1">${text}</div>
                    <small class="text-white-50">Sending...</small>
                </div>`;
            container.appendChild(temp);
            container.scrollTop = container.scrollHeight;
            form.reset();

            fetch('send_message.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    temp.remove();
                    if (data.success) {
                        const msg = document.createElement('div');
                        msg.className = 'mb-4 text-end';
                        msg.innerHTML = `
                            <div class="d-inline-block p-3 rounded bg-primary text-white" style="max-width:75%; border-radius:20px 20px 6px 20px;" data-message-id="${data.message_id}">
                                <div class="message-content mb-1">${text}</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-white-50">Just now</small>
                                    <span class="message-actions ms-3" style="display:none;">
                                        <a href="#" class="delete-message text-white-50" data-message-id="${data.message_id}"><i class="fas fa-trash"></i></a>
                                    </span>
                                </div>
                            </div>`;
                        container.appendChild(msg);
                        container.scrollTop = container.scrollHeight;

                        // Re-attach hover
                        const bubble = msg.querySelector('[data-message-id]');
                        bubble.addEventListener('mouseenter', () => bubble.querySelector('.message-actions').style.display = 'inline');
                        bubble.addEventListener('mouseleave', () => bubble.querySelector('.message-actions').style.display = 'none');
                    } else {
                        alert('Send failed: ' + (data.error || 'Try again'));
                    }
                })
                .catch(() => {
                    temp.remove();
                    alert('Network error');
                });
        });
    }

    // Delete message
    document.addEventListener('click', e => {
        const btn = e.target.closest('.delete-message');
        if (!btn) return;
        e.preventDefault();
        const id = btn.dataset.messageId;
        const bubble = btn.closest('[data-message-id]');
        const content = bubble.querySelector('.message-content');

        if (!confirm('Delete this message?')) return;

        fetch('delete_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `message_id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = 'You unsent a message';
                content.className = 'message-content mb-1 fst-italic text-muted';
                btn.closest('.message-actions')?.remove();
            } else {
                alert('Failed: ' + (data.error || 'Unknown'));
            }
        });
    });

    // ===================== REAL-TIME MESSAGE UPDATES =====================
    const conversationId = <?php echo $selected_conversation_id ?? 0; ?>;
    let lastMessageId = <?php echo !empty($conversation_messages) ? max(array_column($conversation_messages, 'id')) : 0; ?>;
    let pollingInterval = null;
    let badgeUpdateInterval = null;

    // Function to update message badge in header
    function updateMessageBadge() {
        fetch('<?php echo base_url('api/messages/get_unread_count.php'); ?>')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.message-badge');
                    const messageLink = document.querySelector('a[href*="messages.php"]');
                    
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        } else if (messageLink) {
                            // Create badge if it doesn't exist
                            let newBadge = messageLink.querySelector('.message-badge');
                            if (!newBadge) {
                                newBadge = document.createElement('span');
                                newBadge.className = 'message-badge';
                                messageLink.appendChild(newBadge);
                            }
                            newBadge.textContent = data.count > 99 ? '99+' : data.count;
                            newBadge.style.display = 'flex';
                        }
                    } else {
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(() => {});
    }

    // Function to add new message to chat
    function addMessageToChat(message) {
        const container = document.getElementById('messages-container');
        if (!container) return;

        // For supplier, auto messages appear as if supplier sent them
        const isSupplier = message.sender_id == <?php echo $user_id; ?>;
        const messageDiv = document.createElement('div');
        messageDiv.className = `mb-4 ${isSupplier ? 'text-end' : ''}`;
        messageDiv.setAttribute('data-message-id', message.id);

        const bubbleClass = isSupplier ? 'bg-primary text-white' : 'bg-light';
        const borderRadius = isSupplier ? 'border-radius: 20px 20px 6px 20px;' : 'border-radius: 20px 20px 20px 6px;';
        
        const time = new Date(message.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        messageDiv.innerHTML = `
            <div class="d-inline-block p-3 rounded position-relative ${bubbleClass}" style="max-width: 75%; ${borderRadius}">
                <div class="message-content mb-1">${escapeHtml(message.message)}</div>
                <div class="d-flex align-items-center justify-content-between">
                    <small class="${isSupplier ? 'text-white-50' : 'text-muted'}">${time}</small>
                    ${isSupplier ? `
                        <span class="message-actions ms-3" style="display: none;">
                            <a href="#" class="delete-message text-white-50" data-message-id="${message.id}">
                                <i class="fas fa-trash"></i>
                            </a>
                        </span>
                    ` : ''}
                </div>
            </div>
        `;

        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;

        // Add hover functionality for own messages
        if (isSupplier) {
            const bubble = messageDiv.querySelector('[data-message-id]');
            bubble.addEventListener('mouseenter', () => {
                const actions = bubble.querySelector('.message-actions');
                if (actions) actions.style.display = 'inline';
            });
            bubble.addEventListener('mouseleave', () => {
                const actions = bubble.querySelector('.message-actions');
                if (actions) actions.style.display = 'none';
            });
        }

        // Update last message ID
        lastMessageId = Math.max(lastMessageId, message.id);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    // Poll for new messages if conversation is open
    if (conversationId > 0) {
        pollingInterval = setInterval(() => {
            fetch(`<?php echo base_url('api/messages/get_new_messages.php'); ?>?conversation_id=${conversationId}&last_message_id=${lastMessageId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            addMessageToChat(msg);
                        });
                        updateMessageBadge();
                    }
                })
                .catch(() => {});
        }, 2000); // Poll every 2 seconds
    }

    // Update badge every 5 seconds
    badgeUpdateInterval = setInterval(updateMessageBadge, 5000);

    // Initial badge update
    updateMessageBadge();

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (pollingInterval) clearInterval(pollingInterval);
        if (badgeUpdateInterval) clearInterval(badgeUpdateInterval);
    });
});
</script>

<!-- ===================== STYLES: Compatible with Header & Sidebar ===================== -->
<style>
/* Use existing --sidebar-width from header */
.main-content {
    transition: margin-left 0.3s ease-in-out;
}

@media (min-width: 992px) {
    .main-content {
        margin-left: var(--sidebar-width, 220px);
    }
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0 !important;
    }
}

/* Fix dropdown z-index */
.dropdown-menu {
    z-index: 1055 !important;
}

/* Fix mobile backdrop */
.sidebar-backdrop {
    z-index: 1035;
    display: none;
}
.sidebar-backdrop.show {
    display: block;
}

/* Card & hover */
.card {
    border-radius: 12px;
    transition: all 0.2s ease;
}
.card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important;
}

/* Active conversation */
.list-group-item-action.active {
    background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
    color: white !important;
}
.list-group-item-action.active .text-muted { color: rgba(255,255,255,0.8) !important; }

/* Message bubbles */
.bg-primary { background: linear-gradient(135deg, #0ea5e9, #0284c7) !important; }

/* Input */
.input-group textarea { border-radius: 20px 0 0 20px !important; }
.input-group .btn { border-radius: 0 20px 20px 0 !important; }

/* Scrollbar */
.overflow-auto::-webkit-scrollbar { width: 6px; }
.overflow-auto::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 3px; }

/* Context Menu */
.dropdown-menu {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    border: 1px solid rgba(0, 0, 0, 0.1);
}
.dropdown-menu .dropdown-item {
    padding: 0.5rem 1rem;
}
.dropdown-menu .dropdown-item:hover {
    background-color: #f8f9fa;
}
.dropdown-menu .dropdown-item.text-danger:hover {
    background-color: #fff5f5;
}
</style>

<script>
// Add context menu functionality for conversations
document.addEventListener('DOMContentLoaded', function() {
    const conversationItems = document.querySelectorAll('.list-group-item-action');
    let contextMenu = null;

    // Create context menu
    function createContextMenu(x, y, conversationId) {
        // Remove existing context menu if any
        if (contextMenu) {
            contextMenu.remove();
        }

        contextMenu = document.createElement('div');
        contextMenu.className = 'dropdown-menu dropdown-menu-end show';
        contextMenu.style.position = 'fixed';
        contextMenu.style.top = y + 'px';
        contextMenu.style.left = x + 'px';
        contextMenu.style.zIndex = '1000';

        // Check if we're in archived view
        const isArchived = window.location.search.includes('archived=1');
        
        if (!isArchived) {
            const archiveItem = document.createElement('button');
            archiveItem.className = 'dropdown-item small';
            archiveItem.innerHTML = '<i class="fas fa-archive me-2"></i>Archive Conversation';
            archiveItem.onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="conversation_id" value="${conversationId}">
                    <input type="hidden" name="action" value="archive">
                `;
                document.body.appendChild(form);
                form.submit();
            };
            contextMenu.appendChild(archiveItem);
        } else {
            const unarchiveItem = document.createElement('button');
            unarchiveItem.className = 'dropdown-item small';
            unarchiveItem.innerHTML = '<i class="fas fa-undo me-2"></i>Unarchive Conversation';
            unarchiveItem.onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="conversation_id" value="${conversationId}">
                    <input type="hidden" name="action" value="unarchive">
                `;
                document.body.appendChild(form);
                form.submit();
            };
            contextMenu.appendChild(unarchiveItem);
        }

        const divider = document.createElement('hr');
        divider.className = 'dropdown-divider';
        contextMenu.appendChild(divider);

        const deleteItem = document.createElement('button');
        deleteItem.className = 'dropdown-item text-danger small';
        deleteItem.innerHTML = '<i class="fas fa-trash me-2"></i>Delete Conversation';
        deleteItem.onclick = function() {
            if (confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.className = 'delete-conversation-form';
                form.innerHTML = `
                    <input type="hidden" name="conversation_id" value="${conversationId}">
                    <input type="hidden" name="action" value="delete">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        };
        contextMenu.appendChild(deleteItem);

        document.body.appendChild(contextMenu);

        // Close context menu when clicking elsewhere
        const closeMenu = (e) => {
            if (contextMenu && !contextMenu.contains(e.target)) {
                contextMenu.remove();
                contextMenu = null;
                document.removeEventListener('click', closeMenu);
            }
        };

        setTimeout(() => {
            document.addEventListener('click', closeMenu);
        }, 100);
    }

    // Add right-click event for desktop
    conversationItems.forEach(item => {
        const conversationId = item.getAttribute('href').match(/conversation_id=(\d+)/);
        if (conversationId && conversationId[1]) {
            const id = conversationId[1];
            
            // Right-click context menu
            item.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                createContextMenu(e.clientX, e.clientY, id);
            });
            
            // Long-press context menu for mobile
            let pressTimer;
            item.addEventListener('touchstart', function(e) {
                pressTimer = setTimeout(() => {
                    const touch = e.touches[0];
                    createContextMenu(touch.clientX, touch.clientY, id);
                }, 500); // 500ms long press
            });
            
            item.addEventListener('touchend', function() {
                clearTimeout(pressTimer);
            });
            
            item.addEventListener('touchmove', function() {
                clearTimeout(pressTimer);
            });
        }
    });
});
</script>

