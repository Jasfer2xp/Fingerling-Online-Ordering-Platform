<?php
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../includes/xendit_api_check.php';
require_once '../classes/Customer.php';

if (!is_logged_in()) redirect(base_url('auth/login.php'));

// Support both Xendit (invoice) and PayPal (order_id) success redirects
$invoice_id = $_SESSION['last_xendit_invoice']['invoice_id'] ?? $_GET['invoice_id'] ?? null;
$successful_order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$auto_message_order_id = 0;

// If we have invoice ID, sync the order status immediately
if ($invoice_id) {
    $invoice = $database->fetch(
        "SELECT order_id FROM xendit_invoices WHERE invoice_id = ?",
        [$invoice_id]
    );
    
    if ($invoice && !empty($invoice['order_id'])) {
        $auto_message_order_id = (int)$invoice['order_id'];
        // Sync order status from Xendit
        $sync_result = sync_order_from_xendit($auto_message_order_id);

        // Fire-and-forget call to auto message endpoint
        $auto_api_url = base_url('api/messages/create_auto_message.php');
        $payload = json_encode(['order_id' => $auto_message_order_id]);

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($auto_api_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                curl_setopt($ch, CURLOPT_NOSIGNAL, true);
                curl_exec($ch);
                curl_close($ch);
            } else {
                $context = stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/json\r\n",
                        'content' => $payload,
                        'timeout' => 2,
                    ],
                ]);
                @file_get_contents($auto_api_url, false, $context);
            }
        } catch (Exception $e) {
            error_log('Payment success auto message hook failed: ' . $e->getMessage());
        }
    }
}

// Handle PayPal return URL (when PayPal redirects back)
$paypal_return = isset($_GET['paypal_return']) && $_GET['paypal_return'] == '1';
if ($paypal_return && $successful_order_id > 0) {
    // PayPal redirected back - trigger payment processing
    require_once '../includes/auto_payment_message.php';
    require_once '../classes/Order.php';
    
    $user_id = get_user_id();
    if ($user_id) {
        $customer = new Customer($database);
        $customer_id = $customer->getCustomerIdByUserId($user_id);
        
        if ($customer_id) {
            // Check if order is already paid
            $order = $database->fetch(
                "SELECT id, status, payment_status FROM orders WHERE id = ? AND customer_id = ?",
                [$successful_order_id, $customer_id]
            );
            
            if ($order) {
                $auto_message_order_id = (int) $order['id'];
                
                // If not paid yet, try to process (payment might have completed but callback failed)
                if ($order['status'] !== 'confirmed_and_paid' && $order['payment_status'] !== 'paid') {
                    // Payment might still be processing - show message
                    $_SESSION['info'] = 'Your payment is being processed. Please check back in a few moments.';
                }
            }
        }
    }
}

// Fallback for PayPal: validate order ownership via query parameter
if (!$auto_message_order_id && $successful_order_id > 0) {
    $user_id = get_user_id();
    if ($user_id) {
        $customer = new Customer($database);
        $customer_id = $customer->getCustomerIdByUserId($user_id);

        if ($customer_id) {
            $order = $database->fetch(
                "SELECT id FROM orders WHERE id = ? AND customer_id = ?",
                [$successful_order_id, $customer_id]
            );

            if ($order) {
                $auto_message_order_id = (int) $order['id'];
            } else {
                error_log("Payment success: Order {$successful_order_id} not found for customer {$customer_id}");
            }
        }
    }
}

$page_title = "Payment Successful";
include '../includes/customer_header.php';
?>

<div class="container py-5 text-center">
    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
    <h1 class="mt-4">Payment Received!</h1>
    <p class="lead">Thank you! Your payment was successful.</p>
    <p>Your order is now being processed by the supplier.</p>
    <div class="mt-4">
        <a href="<?= base_url('customer/orders.php') ?>" class="btn btn-primary btn-lg me-2">
            View My Orders
        </a>
        <a href="<?= base_url('customer/messages.php') ?>" class="btn btn-outline-primary btn-lg">
            <i class="fas fa-envelope me-2"></i>Check Messages
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderId = <?php echo (int)$auto_message_order_id; ?>;
    const createAutoMessageUrl = '<?php echo base_url('api/messages/create_auto_message.php'); ?>';
    const unreadCountUrl = '<?php echo base_url('api/messages/get_unread_count.php'); ?>';

    function triggerAutoMessage(attempt = 1) {
        if (!orderId) return;
        fetch(createAutoMessageUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(r => r.json())
        .then(data => {
            if (!data?.success && attempt < 2) {
                setTimeout(() => triggerAutoMessage(attempt + 1), 1500);
            } else if (data?.success) {
                updateMessageBadge();
            }
        })
        .catch(() => {
            if (attempt < 2) {
                setTimeout(() => triggerAutoMessage(attempt + 1), 1500);
            }
        });
    }

    function updateMessageBadge() {
        fetch(unreadCountUrl)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    const badge = document.querySelector('.message-badge, #message-badge, .badge-count');
                    const messageLink = document.querySelector('a[href*="messages.php"]');
                    
                    if (badge) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'flex';
                    } else if (messageLink) {
                        let newBadge = messageLink.querySelector('.message-badge');
                        if (!newBadge) {
                            newBadge = document.createElement('span');
                            newBadge.className = 'message-badge badge-count';
                            newBadge.id = 'message-badge';
                            newBadge.style.cssText = 'display: flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; background: #dc3545; color: white; border-radius: 10px; font-size: 11px; font-weight: bold;';
                            messageLink.appendChild(newBadge);
                        }
                        newBadge.textContent = data.count > 99 ? '99+' : data.count;
                        newBadge.style.display = 'flex';
                    }
                    
                    console.log('New message available! Count:', data.count);
                }
            })
            .catch((error) => {
                console.error('Badge update error:', error);
            });
    }
    
    if (orderId) {
        triggerAutoMessage();
    }

    updateMessageBadge();
    const badgeInterval = setInterval(updateMessageBadge, 3000);
    setTimeout(() => clearInterval(badgeInterval), 30000);
});
</script>

<?php include '../includes/customer_footer.php'; ?>