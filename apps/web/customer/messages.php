<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Ensure Manila timezone is set
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$message = new Message($database);
$profile = $user->getUserProfile($user_id);

$customer_record = $database->fetch("SELECT id FROM customers WHERE user_id = ?", [$user_id]);
$customer_table_id = $customer_record['id'] ?? null;
if (!$customer_table_id) {
    $_SESSION['error'] = 'Customer record not found.';
    redirect(base_url('auth/login.php'));
}

if (!function_exists('convert_to_manila_datetime')) {
    function convert_to_manila_datetime($timestamp)
    {
        if (empty($timestamp)) {
            return null;
        }

        try {
            // Timestamps in database are already stored in Manila time (see Message.php line 98-99)
            // So we should parse them as Manila time, not UTC
            $dt = new DateTime($timestamp, new DateTimeZone('Asia/Manila'));
        } catch (Exception $e) {
            try {
                // Fallback: try parsing without timezone (assumes server timezone is Manila)
                $dt = new DateTime($timestamp);
                // Ensure it's treated as Manila time
                if ($dt->getTimezone()->getName() !== 'Asia/Manila') {
                    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                }
            } catch (Exception $e2) {
                return null;
            }
        }

        return $dt;
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

$target_supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
if ($target_supplier_id > 0) {
    try {
        $conversation = $database->fetch(
            "SELECT id FROM conversations WHERE customer_id = ? AND supplier_id = ?",
            [$customer_table_id, $target_supplier_id]
        );

        if ($conversation) {
            redirect(base_url('customer/messages.php?conversation_id=' . (int) $conversation['id']));
            exit;
        }

        $newConversationId = $message->createConversation($customer_table_id, $target_supplier_id, null);
        if ($newConversationId) {
            redirect(base_url('customer/messages.php?conversation_id=' . $newConversationId));
            exit;
        }

        $_SESSION['error'] = 'Unable to start a conversation with this supplier right now.';
        redirect(base_url('customer/messages.php'));
        exit;
    } catch (Exception $e) {
        error_log('Messages contact redirect error: ' . $e->getMessage());
        $_SESSION['error'] = 'Unable to open chat at the moment.';
        redirect(base_url('customer/messages.php'));
        exit;
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
                // Redirect to archived view after archiving
                redirect(base_url('customer/messages.php?archived=1'));
                break;
                
            case 'unarchive':
                $message->unarchiveConversation($conversation_id, $user_id);
                $_SESSION['success'] = 'Conversation unarchived successfully.';
                // Redirect back to inbox after unarchiving
                redirect(base_url('customer/messages.php'));
                break;
                
            case 'delete':
                $message->deleteConversation($conversation_id, $user_id);
                $_SESSION['success'] = 'Conversation deleted successfully.';
                // Redirect to clean URL if we deleted the currently selected conversation
                if (isset($_GET['conversation_id']) && (int)$_GET['conversation_id'] === $conversation_id) {
                    redirect(base_url('customer/messages.php'));
                }
                break;
        }
        
        // Redirect to prevent resubmission
        $redirect_url = base_url('customer/messages.php');
        if (isset($_GET['archived'])) {
            $redirect_url .= '?archived=1';
        } else if (isset($_GET['conversation_id'])) {
            $redirect_url .= '?conversation_id=' . (int)$_GET['conversation_id'];
        }
        redirect($redirect_url);
    }
}

$page_title = 'Messages';
include '../includes/customer_header.php';
?>

<!-- ===================== MESSAGES PAGE ===================== -->
<main class="dashboard-wrapper">
    <div class="container dashboard-container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-11">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="fas fa-comments text-primary me-3" style="font-size: 1.8rem;"></i> Messages
                    </h2>
                    <div>
                        <?php if (isset($_GET['archived'])): ?>
                            <a href="<?php echo base_url('customer/messages.php'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Inbox
                            </a>
                        <?php else: ?>
                            <a href="<?php echo base_url('customer/messages.php?archived=1'); ?>" class="btn btn-outline-secondary">
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
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0 fw-semibold text-dark">
                                    <?php echo isset($_GET['archived']) ? 'Archived Conversations' : 'Conversations'; ?>
                                </h5>
                            </div>
                            <div class="list-group list-group-flush overflow-auto" style="max-height: 70vh;">
                                <?php
                                if (isset($_GET['archived'])) {
                                    $conversations = $message->getArchivedConversations($user_id, 'customer');
                                } else {
                                    $conversations = $message->getUserConversations($user_id, 'customer');
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
                                                        <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 180px;">
                                                            <?php echo htmlspecialchars($conversation['supplier_name']); ?>
                                                        </h6>
                                                        <?php if ($unread_count > 0): ?>
                                                            <span class="badge bg-primary rounded-pill ms-2"><?php echo $unread_count; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($conversation['last_message'])): ?>
                                                        <p class="mb-1 small text-muted text-truncate" style="max-width: 200px;">
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
                                                            data-bs-toggle="dropdown">
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
                    <div class="col-md-8">
                        <?php
                        $selected_conversation = null;
                        $conversation_messages = [];

                        if ($selected_conversation_id) {
                            $sql = "SELECT c.* FROM conversations c 
                                    JOIN customers cust ON c.customer_id = cust.id 
                                    WHERE c.id = ? AND cust.user_id = ?";
                            $result = $database->fetch($sql, [$selected_conversation_id, $user_id]);

                            if ($result) {
                                $selected_conversation = $message->getConversation($selected_conversation_id);
                                $conversation_messages = $message->getMessages($selected_conversation_id);
                                $message->markAsRead($selected_conversation_id, $user_id);
                            }
                        }

                        $last_message_timestamp = null;
                        if (!empty($conversation_messages)) {
                            $timestamps = array_column($conversation_messages, 'created_at');
                            if (!empty($timestamps)) {
                                $last_message_timestamp = max($timestamps);
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
                                            <h5 class="mb-0 fw-semibold"><?php echo htmlspecialchars($selected_conversation['supplier_name']); ?></h5>
                                            <small class="text-muted">Supplier</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body flex-grow-1 overflow-auto p-4" id="messages-container" style="max-height: 60vh;">
                                    <?php if (!empty($conversation_messages)): ?>
                                        <?php foreach ($conversation_messages as $msg): ?>
                                            <div class="mb-4 <?php echo ($msg['sender_id'] == $user_id) ? 'text-end' : ''; ?>">
                                                <div class="d-inline-block p-3 rounded position-relative <?php echo ($msg['sender_id'] == $user_id) ? 'bg-primary text-white' : 'bg-light'; ?>"
                                                     style="max-width: 75%; <?php echo ($msg['sender_id'] == $user_id)
                                                         ? 'border-radius: 20px 20px 6px 20px;'
                                                         : 'border-radius: 20px 20px 20px 6px;'; ?>"
                                                     data-message-id="<?php echo $msg['id']; ?>">
                                                    <?php if (!empty($msg['is_unsent']) && $msg['is_unsent'] == 1): ?>
                                                        <div class="message-content mb-1 fst-italic text-muted">
                                                            <?php 
                                                            if ($msg['sender_id'] == $user_id) {
                                                                echo 'You unsent a message';
                                                            } else {
                                                                echo htmlspecialchars($msg['sender_first_name'] ?? 'User') . ' unsent a message';
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="message-content mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <small class="<?php echo ($msg['sender_id'] == $user_id) ? 'text-white-50' : 'text-muted'; ?>">
                                                                <?php echo format_manila_time($msg['created_at']); ?>
                                                            </small>
                                                            <?php if ($msg['sender_id'] == $user_id && empty($msg['is_unsent'])): ?>
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
                                            <p>No messages yet. Start the conversation!</p>
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
                                    <p>Choose a conversation from the list to start messaging.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messages-container');
    if (container) container.scrollTop = container.scrollHeight;

    document.querySelectorAll('.delete-conversation-form').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Delete this conversation? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

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

    document.addEventListener('click', e => {
        const btn = e.target.closest('.delete-message');
        if (!btn) return;
        e.preventDefault();

        const msgId = btn.dataset.messageId;
        const bubble = btn.closest('[data-message-id]');
        const content = bubble?.querySelector('.message-content');

        if (!confirm('Delete this message?')) return;

        fetch('delete_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `message_id=${msgId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (content) {
                    content.innerHTML = 'You unsent a message';
                    content.className = 'message-content mb-1 fst-italic text-muted';
                }
                btn.closest('.message-actions')?.remove();
            } else {
                alert('Failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error'));
    });

    const form = document.getElementById('message-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);

            fetch('send_message.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.reset();
                        location.reload();
                    } else {
                        alert('Failed to send: ' + (data.error || 'Try again'));
                    }
                })
                .catch(() => alert('Send failed'));
        });
    }

    const conversationItems = document.querySelectorAll('.list-group-item-action');
    let contextMenu = null;

    function createContextMenu(x, y, conversationId) {
        if (contextMenu) {
            contextMenu.remove();
        }

        contextMenu = document.createElement('div');
        contextMenu.className = 'dropdown-menu dropdown-menu-end show';
        contextMenu.style.position = 'fixed';
        contextMenu.style.top = y + 'px';
        contextMenu.style.left = x + 'px';
        contextMenu.style.zIndex = '1000';

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

    conversationItems.forEach(item => {
        const conversationIdInput = item.querySelector('input[name="conversation_id"]');
        if (conversationIdInput) {
            const id = conversationIdInput.value;
            item.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                createContextMenu(e.clientX, e.clientY, id);
            });
            
            let pressTimer;
            item.addEventListener('touchstart', function(e) {
                pressTimer = setTimeout(() => {
                    createContextMenu(e.touches[0].clientX, e.touches[0].clientY, id);
                }, 500);
            });
            
            item.addEventListener('touchend', function() {
                clearTimeout(pressTimer);
            });
            
            item.addEventListener('touchmove', function() {
                clearTimeout(pressTimer);
            });
        }
    });

    const conversationId = <?php echo $selected_conversation_id ?? 0; ?>;
    const checkNewApiUrl = '<?php echo base_url('api/messages/check_new.php'); ?>';
    const unreadCountUrl = '<?php echo base_url('api/messages/get_unread_count.php'); ?>';
    let lastTimestamp = <?php echo $last_message_timestamp ? json_encode($last_message_timestamp) : 'null'; ?>;
    let pollingInterval = null;
    let badgeUpdateInterval = null;

    function updateMessageBadge() {
        fetch(unreadCountUrl)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.message-badge, #message-badge');
                    const messageLink = document.querySelector('a[href*="messages.php"]');
                    
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        } else if (messageLink) {
                            let newBadge = messageLink.querySelector('.message-badge');
                            if (!newBadge) {
                                newBadge = document.createElement('span');
                                newBadge.className = 'message-badge';
                                messageLink.appendChild(newBadge);
                            }
                            newBadge.textContent = data.count > 99 ? '99+' : data.count;
                            newBadge.style.display = 'flex';
                        }
                    } else if (badge) {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    // Format timestamp to Manila time (12-hour format)
    // IMPORTANT: Database timestamps are already stored in Manila time (see Message.php)
    // So we just need to parse and format them, NOT convert timezones
    function formatManilaTime(timestamp) {
        if (!timestamp) return '';
        
        // Parse the timestamp - it's already in Manila time from database
        // Create date object from the timestamp string
        const date = new Date(timestamp);
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return '';
        }
        
        // Get hours and minutes directly (timestamp is already in Manila time)
        let hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        const minutesStr = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutesStr + ' ' + ampm;
    }

    function addMessageToChat(message) {
        const feed = document.getElementById('messages-container');
        if (!feed) return;

        const isOwn = Number(message.sender_id) === <?php echo (int)$user_id; ?>;
        const wrapper = document.createElement('div');
        wrapper.className = `mb-4 ${isOwn ? 'text-end' : ''}`;

        const bubbleClass = isOwn ? 'bg-primary text-white' : 'bg-light';
        const borderRadius = isOwn ? 'border-radius: 20px 20px 6px 20px;' : 'border-radius: 20px 20px 20px 6px;';
        // Use server-provided timestamp format (already in Manila time)
        // If message has formatted_time, use it; otherwise format from created_at
        const time = message.formatted_time || formatManilaTime(message.created_at);

        wrapper.innerHTML = `
            <div class="d-inline-block p-3 rounded position-relative ${bubbleClass}" data-message-id="${message.id}" style="max-width: 75%; ${borderRadius}">
                <div class="message-content mb-1">${escapeHtml(message.message)}</div>
                <div class="d-flex align-items-center justify-content-between">
                    <small class="${isOwn ? 'text-white-50' : 'text-muted'}">${time}</small>
                    ${isOwn ? `
                        <span class="message-actions ms-3" style="display: none;">
                            <a href="#" class="delete-message text-white-50" data-message-id="${message.id}">
                                <i class="fas fa-trash"></i>
                            </a>
                        </span>
                    ` : ''}
                </div>
            </div>
        `;

        feed.appendChild(wrapper);
        feed.scrollTop = feed.scrollHeight;

        if (isOwn) {
            const bubble = wrapper.querySelector('[data-message-id]');
            bubble.addEventListener('mouseenter', () => {
                const actions = bubble.querySelector('.message-actions');
                if (actions) actions.style.display = 'inline';
            });
            bubble.addEventListener('mouseleave', () => {
                const actions = bubble.querySelector('.message-actions');
                if (actions) actions.style.display = 'none';
            });
        }

        lastTimestamp = message.created_at;
    }

    function pollForMessages() {
        const since = encodeURIComponent(lastTimestamp || '1970-01-01 00:00:00');
        fetch(`${checkNewApiUrl}?conversation_id=${conversationId}&since=${since}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.messages) && data.messages.length) {
                    data.messages.forEach(addMessageToChat);
                    updateMessageBadge();
                }
            })
            .catch(() => {});
    }

    if (conversationId > 0) {
        pollingInterval = setInterval(pollForMessages, 3000);
    }

    badgeUpdateInterval = setInterval(updateMessageBadge, 5000);
    updateMessageBadge();

    window.addEventListener('beforeunload', () => {
        if (pollingInterval) clearInterval(pollingInterval);
        if (badgeUpdateInterval) clearInterval(badgeUpdateInterval);
    });
});
</script>

<!-- ===================== STYLES ===================== -->
<style>
/* Base Dashboard Layout */
.dashboard-wrapper {
    background: linear-gradient(to bottom right, #e0f7ff, #f8fbff);
    padding: 3rem 0;
    min-height: calc(100vh - 140px);
}

.dashboard-container {
    max-width: 1300px; /* Slightly narrower than 1200px */
    margin: 0 auto;
    padding: 0 1rem;
}

/* Cards */
.card {
    border-radius: 12px;
    transition: all 0.2s ease;
}
.card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important;
}

/* Conversation List */
.list-group-item-action {
    transition: background-color 0.2s ease;
}
.list-group-item-action:hover {
    background-color: #f8f9fa !important;
}

/* Active conversation */
.list-group-item-action.active {
    background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
    border-color: #0ea5e9 !important;
    color: white !important;
}
.list-group-item-action.active .text-muted {
    color: rgba(255,255,255,0.8) !important;
}
.list-group-item-action.active .badge {
    background-color: rgba(255,255,255,0.3) !important;
}

/* Message Bubbles */
.bg-primary {
    background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
}
.bg-light {
    background-color: #f8f9fa !important;
}

/* Input */
.input-group textarea {
    border-radius: 20px 0 0 20px !important;
}
.input-group .btn {
    border-radius: 0 20px 20px 0 !important;
}

/* Scrollbar */
.overflow-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-auto::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 3px;
}

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

<?php include '../includes/customer_footer.php'; ?>