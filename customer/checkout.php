<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Customer.php';
require_once '../classes/Cart.php';
require_once '../classes/Order.php';

// === AUTHENTICATION & REGISTRATION CHECK ===
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$paypal_success_order = $_SESSION['paypal_payment_order_id'] ?? null;
if ($paypal_success_order) {
    unset($_SESSION['paypal_payment_order_id']);
    redirect(base_url('customer/payment-successful.php?order_id=' . (int) $paypal_success_order));
}

$just_completed_registration = isset($_SESSION['registration_just_completed']) && $_SESSION['registration_just_completed'] === true;
if ($just_completed_registration) {
    unset($_SESSION['registration_just_completed']);
}

$user_id = get_user_id();
$user    = new User($database);
$profile = $user->getUserProfile($user_id);

if (!$just_completed_registration && (empty($profile['barangay']) || empty($profile['city']) || empty($profile['province']))) {
    $_SESSION['complete_registration_user_id']      = $user_id;
    $_SESSION['complete_registration_email']        = $profile['email'];
    $_SESSION['complete_registration_first_name']   = $profile['first_name'] ?? '';
    $_SESSION['complete_registration_last_name']    = $profile['last_name'] ?? '';
    $_SESSION['error'] = 'Your account is not yet complete. Please complete your registration.';
    redirect(base_url('auth/register.php?type=customer&step=3'));
}

// === LOGGING HELPER ===
function logInteraction($message, $type = 'INFO')
{
    $logFile = __DIR__ . '/interactions.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// === LOAD DATA ===
$user        = new User($database);
$customer    = new Customer($database);
$order       = new Order($database);
$profile     = $user->getUserProfile($user_id);
$customer_id = $profile['id'] ?? null;
$delivery_options_supported = ['truck' => true, 'boat' => true];
$prefill_delivery_type = 'truck';

if (!$customer_id) {
    $_SESSION['error'] = 'Customer profile not found.';
    redirect(base_url('auth/login.php'));
}

// === CHECK IF PAYING FOR EXISTING CONFIRMED ORDER ===
$order_id = intval($_GET['order_id'] ?? 0);
$is_paying_confirmed_order = false;
$confirmed_order_data = null;
$confirmed_order_items = [];

if ($order_id > 0) {
    $sql = "SELECT o.*, s.business_name 
            FROM orders o
            JOIN suppliers s ON o.supplier_id = s.id
            WHERE o.id = ? AND o.customer_id = ? AND o.status = 'confirmed'";
    $confirmed_order_data = $database->fetch($sql, [$order_id, $customer_id]);
    
        if ($confirmed_order_data) {
            $is_paying_confirmed_order = true;
            $confirmed_order_items = $order->getOrderItems($order_id);
            
            if (empty($confirmed_order_items)) {
                $_SESSION['error'] = 'Order items not found.';
                redirect(base_url('customer/orders.php'));
            }
            
            $subtotal = 0;
            foreach ($confirmed_order_items as $item) {
                if (isset($item['subtotal'])) {
                    $subtotal += (float)$item['subtotal'];
                } else {
                    $price = isset($item['price_per_piece']) ? (float)$item['price_per_piece'] : 0;
                    $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0;
                    $subtotal += $price * $qty;
                }
            }
            $subtotal = round($subtotal, 2);
            $transaction_fee = round($subtotal * 0.02576, 2);
            $total = round($subtotal + $transaction_fee, 2);
        }
}

// === REGULAR CART-BASED CHECKOUT (NEW ORDER) ===
if (!$is_paying_confirmed_order) {
    $customer_addresses = $user->getCustomerAddresses($customer_id);
    $default_address    = null;
    foreach ($customer_addresses as $addr) {
        if ($addr['is_default'] == 1) {
            $default_address = $addr;
            break;
        }
    }
    if (!$default_address && !empty($customer_addresses)) {
        $default_address = $customer_addresses[0];
    }

    $cart       = new Cart($database, $customer_id);
    $cart_items = $cart->getItems();

    if (empty($cart_items)) {
        $_SESSION['error'] = 'Your cart is empty.';
        redirect(base_url('customer/cart.php'));
    }

    $supplier_ids = array_unique(array_filter(array_map(fn($item) => $item['supplier_id'] ?? null, $cart_items)));
    $delivery_options_supported = ['truck' => false, 'boat' => false];
    if (!empty($supplier_ids)) {
        $placeholders = implode(',', array_fill(0, count($supplier_ids), '?'));
        try {
            $supplier_flags = $database->fetchAll(
                "SELECT id, supports_truck, supports_boat FROM suppliers WHERE id IN ($placeholders)",
                $supplier_ids
            );
            $total_suppliers = count($supplier_ids);
            $truck_supported = 0;
            $boat_supported = 0;
            foreach ($supplier_flags as $flag) {
                if (!empty($flag['supports_truck'])) {
                    $truck_supported++;
                }
                if (!empty($flag['supports_boat'])) {
                    $boat_supported++;
                }
            }
            $delivery_options_supported['truck'] = ($truck_supported === $total_suppliers) && $total_suppliers > 0;
            $delivery_options_supported['boat'] = ($boat_supported === $total_suppliers) && $total_suppliers > 0;
        } catch (Exception $e) {
            error_log('Checkout delivery option lookup failed: ' . $e->getMessage());
            $delivery_options_supported = ['truck' => true, 'boat' => true];
        }
    } else {
        $delivery_options_supported = ['truck' => true, 'boat' => true];
    }

    $prefill_delivery_type = $delivery_options_supported['truck']
        ? 'truck'
        : ($delivery_options_supported['boat'] ? 'boat' : '');

    $subtotal        = array_reduce($cart_items, fn($sum, $i) => $sum + ($i['price_per_piece'] ?? 0) * ($i['quantity'] ?? 0), 0);
    $transaction_fee = $subtotal * 0.02576;
    $total           = $subtotal + $transaction_fee;
} else {
    $default_address = null;
    $customer_addresses = [];
}

// === HANDLE ORDER PLACEMENT OR PAYMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // === PAYMENT FOR CONFIRMED ORDER ===
        if ($is_paying_confirmed_order && $action === 'process_payment') {
            $payment_method = $_POST['payment_method'] ?? '';
            
            if (!in_array($payment_method, ['xendit', 'paypal'])) {
                throw new Exception('Please select a valid payment method');
            }

            // Recalculate total based on selected payment method
            $final_total = $subtotal;
            if ($payment_method === 'xendit') {
                $final_total = $subtotal + ($subtotal * 0.02576);
            } elseif ($payment_method === 'paypal') {
                $final_total = $subtotal + ($subtotal * 0.0437) + 15;
            }
            $final_total = round($final_total, 2);

            $_SESSION['pending_order_payment'] = [
                'order_id'      => $order_id,
                'total_amount'  => $final_total,
                'subtotal'      => $subtotal,
                'payment_method'=> $payment_method,
                'business_name' => $confirmed_order_data['business_name'],
                'order_number'  => $confirmed_order_data['order_number']
            ];
            
            // Log payment method selection
            $logFile = __DIR__ . '/interactions.log';
            $logEntry = date('Y-m-d H:i:s') . " | CUSTOMER | SELECT_PAYMENT_METHOD | Order: {$order_id} ({$confirmed_order_data['order_number']}) | Method: {$payment_method} | Amount: {$final_total} | User: " . get_user_id() . "\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            if ($payment_method === 'paypal') {
                redirect(base_url('customer/paypal-checkout.php?order_id=' . $order_id));
            } elseif ($payment_method === 'xendit') {
                redirect(base_url('customer/xendit-checkout.php?order_id=' . $order_id));
            }
        }
        
        // === CREATE NEW ORDER FROM CART (NO PAYMENT METHOD REQUIRED) ===
        if ($action === 'place_order' && !$is_paying_confirmed_order) {
            $delivery_type = $_POST['delivery_type'] ?? '';

            $parts = array_filter([
                sanitize_input($_POST['address_line_1'] ?? ''),
                sanitize_input($_POST['address_line_2'] ?? ''),
                sanitize_input($_POST['barangay'] ?? ''),
                sanitize_input($_POST['city'] ?? 'Tangub City'),
                sanitize_input($_POST['province'] ?? 'Misamis Occidental'),
                sanitize_input($_POST['postal_code'] ?? '')
            ]);
            $delivery_address = implode(', ', $parts);
            if (empty($delivery_address)) {
                throw new Exception('Delivery address is required');
            }

            if (empty($delivery_type) || !in_array($delivery_type, ['truck', 'boat'])) {
                throw new Exception('Please select a delivery method');
            }

            logInteraction("User {$user_id} placing order (payment later)");

            $order_data = [
                'customer_id'     => $customer_id,
                'delivery_address'=> $delivery_address,
                'delivery_notes'  => sanitize_input($_POST['delivery_notes'] ?? ''),
                'payment_method'  => 'Pending',
                'subtotal'        => $subtotal,
                'delivery_fee'    => $transaction_fee,
                'total_amount'    => $total,
                'delivery_type'   => $delivery_type
            ];

            $order_ids = $order->createOrderFromCart($order_data, $cart_items);
            if (!$order_ids) {
                throw new Exception('Failed to create order');
            }

            $first_order_id = is_array($order_ids) ? $order_ids[0] : $order_ids;
            $_SESSION['recently_placed_order'] = $first_order_id;

            $_SESSION['success'] = 'Order placed successfully! The supplier will review and confirm your order. You will be notified when payment is required.';
            redirect(base_url('customer/orders.php'));
        }

    } catch (Exception $e) {
        logInteraction("Error for user {$user_id}: " . $e->getMessage(), "EXCEPTION");
        $_SESSION['error'] = $e->getMessage();
    }
}

$page_title = 'Checkout';
include '../includes/customer_header.php';
?>

<main class="container py-4">
    <div class="checkout-header mb-4 p-3 rounded-3 text-white" style="background: linear-gradient(90deg,#ee4d2d,#ff7337);">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0">Checkout</h3>
                <small><?php echo $is_paying_confirmed_order ? 'Complete your payment' : 'Review your order and delivery details'; ?></small>
            </div>
            <a href="<?php echo $is_paying_confirmed_order ? 'orders.php' : 'cart.php'; ?>" class="btn btn-light btn-sm">
                <?php echo $is_paying_confirmed_order ? 'Back to Orders' : 'Back to Cart'; ?>
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <input type="hidden" name="action" value="<?php echo $is_paying_confirmed_order ? 'process_payment' : 'place_order'; ?>">
        <?php if ($is_paying_confirmed_order): ?>
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">

                <?php if ($is_paying_confirmed_order): ?>
                    <div class="alert alert-success mb-3">
                        <strong>Order Confirmed!</strong> Order #<?php echo htmlspecialchars($confirmed_order_data['order_number']); ?> is ready for payment.
                    </div>
                    <div class="card mb-3 rounded-3 shadow-sm">
                        <div class="card-header bg-white border-0"><strong>Delivery Address</strong></div>
                        <div class="card-body">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($confirmed_order_data['delivery_address'])); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- FULL DELIVERY INFORMATION FORM — NOW FIXED -->
                    <div class="card mb-3 rounded-3 shadow-sm">
                        <div class="card-header bg-white border-0"><strong>Delivery Information</strong></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['contact_number']); ?>" readonly>
                                </div>
                            </div>

                            <?php if ($default_address): ?>
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="useDefaultAddress">Use Default Address</button>
                            </div>
                            <?php endif; ?>

                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <label for="address_label" class="form-label">Address Label *</label>
                                    <input type="text" class="form-control" id="address_label" name="address_label" placeholder="e.g. Home, Office" required value="<?php echo htmlspecialchars($default_address['label'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="recipient_name" class="form-label">Recipient Name *</label>
                                    <input type="text" class="form-control" id="recipient_name" name="recipient_name" required value="<?php echo htmlspecialchars($default_address['recipient_name'] ?? ($profile['first_name'] . ' ' . $profile['last_name'])); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="text" class="form-control" id="phone" name="phone" required value="<?php echo htmlspecialchars($default_address['phone'] ?? $profile['contact_number']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($default_address['postal_code'] ?? '7214'); ?>">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="address_line_1" class="form-label">Address Line 1 *</label>
                                <input type="text" class="form-control" id="address_line_1" name="address_line_1" placeholder="House No., Street, Purok" required value="<?php echo htmlspecialchars($default_address['address_line_1'] ?? ''); ?>">
                            </div>

                            <div class="mt-3">
                                <label for="address_line_2" class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line_2" name="address_line_2" placeholder="Near landmark (optional)" value="<?php echo htmlspecialchars($default_address['address_line_2'] ?? ''); ?>">
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-4">
                                    <label for="barangay" class="form-label">Barangay *</label>
                                    <select class="form-select" id="barangay" name="barangay" required>
                                        <option value="">Select Barangay</option>
                                        <?php
                                        $barangays = ['Aquino (Marcos)','Balatacan','Baluk','Banglay','Bintana','Bocator','Bongabong','Caniangan','Capalaran','Catagan','Barangay I – City Hall (Poblacion)','Barangay II – Marilou Annex (Poblacion)','Barangay III – Market/Kalubian (Poblacion)','Barangay IV – St. Michael (Poblacion)','Barangay V – Malubog (Poblacion)','Barangay VI – Lower Polao (Poblacion)','Barangay VII – Upper Polao (Poblacion)','Garang','Guinabot','Guinalaban','Hoyohoy','Isidro D. Tan (Dimalooc)','Kauswagan','Kimat','Labuyo','Lorenzo Tan','Lumban','Maloro','Mantic','Mantic','Manga','Maquilao','Matugnaw','Migcanaway','Minsubong','Owayan','Paiton','Panalsalan','Pangabuan','Prenza','Salimpuno','San Antonio','San Apolinario','San Vicente','Santa Cruz','Santa Maria (Baga)','Santo Niño','Sicot','Silanga','Silangit','Simasay','Sumirap','Taguite','Tituron','Tugas','Villaba'];
                                        foreach ($barangays as $b): ?>
                                            <option value="<?php echo htmlspecialchars($b); ?>" <?php echo (isset($default_address['barangay']) && $default_address['barangay'] == $b) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" value="Tangub City" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="province" class="form-label">Province *</label>
                                    <input type="text" class="form-control" id="province" name="province" value="Misamis Occidental" readonly>
                                </div>
                            </div>

                            <!-- FIXED DELIVERY METHOD SECTION -->
                            <div class="mb-3 mt-3">
                                <label class="form-label">Delivery Method *</label>
                                <div class="row" id="deliveryOptions">
                                    <?php if ($delivery_options_supported['truck']): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="delivery-card p-3 text-center border rounded" data-type="truck" onclick="selectDelivery('truck')">
                                                Truck
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($delivery_options_supported['boat']): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="delivery-card p-3 text-center border rounded" data-type="boat" onclick="selectDelivery('boat')">
                                                Boat
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$delivery_options_supported['truck'] && !$delivery_options_supported['boat']): ?>
                                    <div class="alert alert-warning small mb-0">
                                        Delivery options are unavailable for the selected supplier(s). Please adjust your cart or contact support.
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" name="delivery_type" id="delivery_type" value="<?php echo htmlspecialchars($prefill_delivery_type); ?>" required>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- PAYMENT METHOD — ONLY SHOWS WHEN PAYING CONFIRMED ORDER -->
                <?php if ($is_paying_confirmed_order): ?>
                <div class="card mb-3 rounded-3 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <strong>Payment Method</strong>
                        <small class="text-muted" id="fee-preview">Select a method to see fee</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-6">
                                <label class="payment-method-card border rounded-3 p-4 d-block position-relative h-100 cursor-pointer" for="pm-xendit">
                                    <input type="radio" name="payment_method" value="xendit" class="position-absolute top-0 start-0 opacity-0" id="pm-xendit" checked>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-wallet fa-2x text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">Choose Payments</div>
                                            <small class="text-muted">GCash • Maya • Cards • Bank Transfer • OTC</small>
                                            <div class="mt-2"><span class="badge bg-success text-white">2.576% fee</span></div>
                                        </div>
                                        <div class="ms-auto">
                                            <i class="fas fa-check-circle text-primary" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="payment-method-card border rounded-3 p-4 d-block position-relative h-100 cursor-pointer" for="pm-paypal">
                                    <input type="radio" name="payment_method" value="paypal" class="position-absolute top-0 start-0 opacity-0" id="pm-paypal">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fab fa-paypal fa-2x text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">PayPal</div>
                                            <small class="text-muted">Credit/Debit Card • PayPal Balance</small>
                                            <div class="mt-2"><span class="badge bg-warning text-dark">4.37% + ₱15</span></div>
                                        </div>
                                        <div class="ms-auto">
                                            <i class="fas fa-check-circle text-primary opacity-0" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ORDER ITEMS -->
                <div class="card mb-3 rounded-3 shadow-sm">
                    <div class="card-header bg-white border-0"><strong>Order Items</strong></div>
                    <div class="card-body">
                        <?php 
                        $items_to_show = $is_paying_confirmed_order ? $confirmed_order_items : $cart_items;
                        foreach ($items_to_show as $item): 
                            $image_src = asset_url('images/placeholder-fish.jpg');
                            if (!empty($item['image_path'])) {
                                $image_src = filter_var($item['image_path'], FILTER_VALIDATE_URL) ? $item['image_path'] : base_url($item['image_path']);
                            } elseif (!empty($item['image_url'])) {
                                $image_src = filter_var($item['image_url'], FILTER_VALIDATE_URL) ? $item['image_url'] : base_url($item['image_url']);
                            }
                            $price = $item['price_per_piece'] ?? 0;
                            $quantity = $item['quantity'] ?? 0;
                            $species_name = $item['species_name'] ?? 'Unknown';
                            $business_name = $is_paying_confirmed_order ? ($confirmed_order_data['business_name'] ?? '') : ($item['business_name'] ?? '');
                        ?>
                        <div class="row align-items-center border-bottom py-3">
                            <div class="col-7 col-md-6 d-flex align-items-center">
                                <img src="<?php echo $image_src; ?>" class="rounded me-3" style="width:64px;height:64px;object-fit:cover;" onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>'">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($species_name); ?></div>
                                    <small class="text-muted">from <?php echo htmlspecialchars($business_name); ?></small>
                                </div>
                            </div>
                            <div class="col-2 text-center d-none d-md-block"><span class="badge bg-primary"><?php echo $quantity; ?> pcs</span></div>
                            <div class="col-5 col-md-2 text-center"><?php echo format_currency($price); ?></div>
                            <div class="col-12 col-md-2 text-end fw-bold"><?php echo format_currency($price * $quantity); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ORDER SUMMARY (DYNAMIC) -->
            <div class="col-lg-4">
                <div class="card p-4 rounded-3 shadow-sm position-sticky" style="top:80px;">
                    <h5 class="mb-3">Order Summary</h5>
                    
                    <?php $total_items = $is_paying_confirmed_order ? array_sum(array_column($confirmed_order_items, 'quantity')) : array_sum(array_column($cart_items, 'quantity')); ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Items (<?php echo $total_items; ?>)</span>
                        <span id="subtotal-display"><?php echo format_currency($subtotal); ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span id="fee-label">Payment Fee</span>
                        <span id="fee-amount">₱0.00</span>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total Amount</strong>
                        <strong class="text-primary fs-5" id="total-display"><?php echo format_currency($subtotal); ?></strong>
                    </div>

                    <button type="submit" class="btn btn-shopee w-100 py-3 fw-bold">
                        <?php echo $is_paying_confirmed_order ? 'Proceed to Payment' : 'Place Order'; ?>
                    </button>
                    <div class="text-center mt-3 small text-muted">Secure checkout • 256-bit SSL</div>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const subtotal = <?php echo $subtotal; ?>;
    const deliveryTypeInput = document.getElementById('delivery_type');
    const feeLabel = document.getElementById('fee-label');
    const feeAmount = document.getElementById('fee-amount');
    const totalDisplay = document.getElementById('total-display');
    const feePreview = document.getElementById('fee-preview');
    const deliveryAvailability = <?php echo json_encode($delivery_options_supported); ?>;

    function updateTotal() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        let fee = 0;
        let label = "Payment Fee";

        if (selected) {
            if (selected.value === 'xendit') {
                fee = subtotal * 0.02576;
                label = "Xendit Fee (2.576%)";
            } else if (selected.value === 'paypal') {
                fee = (subtotal * 0.0437) + 15;
                label = "PayPal Fee (4.37% + ₱15)";
            }
        }

        fee = Math.round(fee * 100) / 100;
        const total = subtotal + fee;

        feeLabel.textContent = label;
        feeAmount.textContent = "₱" + fee.toLocaleString('en-PH', {minimumFractionDigits: 2});
        totalDisplay.textContent = "₱" + total.toLocaleString('en-PH', {minimumFractionDigits: 2});
        if (feePreview) feePreview.textContent = label + ": ₱" + fee.toLocaleString('en-PH', {minimumFractionDigits: 2});
    }

    // Initial calculation
    updateTotal();

    // Update on payment method change
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove selection styling from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('border-primary', 'bg-light');
                const checkIcon = card.querySelector('.fa-check-circle');
                if (checkIcon) {
                    checkIcon.classList.add('opacity-0');
                }
            });
            // Add selection styling to selected card
            const selectedCard = this.closest('.payment-method-card');
            if (selectedCard) {
                selectedCard.classList.add('border-primary', 'bg-light');
                const checkIcon = selectedCard.querySelector('.fa-check-circle');
                if (checkIcon) {
                    checkIcon.classList.remove('opacity-0');
                }
            }
            updateTotal();
        });
    });
    
    // Make entire payment method card clickable
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking the radio button itself
            if (e.target.type === 'radio') return;
            
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        });
    });
    
    // Initialize visual state for checked payment method
    const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (checkedPayment) {
        checkedPayment.dispatchEvent(new Event('change'));
    }

    window.selectDelivery = function(type) {
        if (!deliveryTypeInput || !deliveryAvailability[type]) return;
        
        document.querySelectorAll('.delivery-card').forEach(card => {
            card.classList.remove('border-primary', 'bg-light');
        });

        const selectedCard = document.querySelector(`.delivery-card[data-type="${type}"]`);
        if (selectedCard) {
            selectedCard.classList.add('border-primary', 'bg-light');
            deliveryTypeInput.value = type;
        }
    };

    if (deliveryTypeInput) {
        if (!deliveryTypeInput.value) {
            const defaultDelivery = deliveryAvailability.truck
                ? 'truck'
                : (deliveryAvailability.boat ? 'boat' : '');
            if (defaultDelivery) {
                selectDelivery(defaultDelivery);
            }
        } else {
            selectDelivery(deliveryTypeInput.value);
        }
    }

    // Use default address
    document.getElementById('useDefaultAddress')?.addEventListener('click', () => {
        const addr = <?php echo json_encode($default_address ?? []); ?>;
        if (addr) {
            document.getElementById('address_label').value = addr.label || '';
            document.getElementById('recipient_name').value = addr.recipient_name || '';
            document.getElementById('phone').value = addr.phone || '';
            document.getElementById('address_line_1').value = addr.address_line_1 || '';
            document.getElementById('address_line_2').value = addr.address_line__line_2 || '';
            document.getElementById('barangay').value = addr.barangay || '';
            document.getElementById('postal_code').value = addr.postal_code || '7214';
        }
    });

    // Form validation
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        let isValid = true;

        <?php if ($is_paying_confirmed_order): ?>
            if (!document.querySelector('input[name="payment_method"]:checked')) {
                alert('Please select a payment method');
                isValid = false;
            }
        <?php else: ?>
            if (!deliveryTypeInput.value) {
                alert('Please select a delivery method');
                isValid = false;
            }
        <?php endif; ?>

        if (!isValid) e.preventDefault();
    });
});
</script>

<style>
.payment-method-card, .delivery-card {
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.payment-method-card:hover, .payment-method-card.border-primary,
.delivery-card:hover, .delivery-card.border-primary {
    border-color: #ee4d2d !important;
    background-color: #fff8f5 !important;
}
.btn-shopee {
    background: linear-gradient(90deg,#ee4d2d,#ff7337);
    color: white;
    border: none;
}
.btn-shopee:hover {
    background: linear-gradient(90deg,#d63e1b,#e65c00);
}
</style>

<?php include '../includes/customer_footer.php'; ?>