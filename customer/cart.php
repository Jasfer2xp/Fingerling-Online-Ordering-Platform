<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';
require_once '../classes/Cart.php';

// Check if user is logged in and is a customer
if (!is_logged_in() || get_user_type() !== 'customer') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);

// Get user profile
$profile = $user->getUserProfile($user_id);
$customer_id = $profile['id'];

$cart = new Cart($database, $customer_id);

// Handle cart actions (server-side fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        switch ($action) {
            case 'update_quantity':
                $product_id = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                $cart->updateQuantity($product_id, $quantity);
                $_SESSION['success'] = 'Cart updated successfully';
                break;
            case 'remove_item':
                $product_id = intval($_POST['product_id']);
                $cart->removeItem($product_id);
                $_SESSION['success'] = 'Item removed from cart';
                break;
            case 'clear_cart':
                $cart->clear();
                $_SESSION['success'] = 'Cart cleared successfully';
                break;
            case 'update_prices':
                $cart->updatePrices();
                $_SESSION['success'] = 'Prices updated';
                break;
            case 'update_all_quantities':
                // Handle bulk quantity updates
                $quantities = $_POST['quantities'] ?? null;
                if ($quantities) {
                    // If it's a JSON string, decode it (from AJAX)
                    if (is_string($quantities)) {
                        $quantities = json_decode(urldecode($quantities), true);
                    }
                    
                    if (is_array($quantities)) {
                        foreach ($quantities as $product_id => $quantity) {
                            $product_id = intval($product_id);
                            $quantity = intval($quantity);
                            if ($quantity > 0) {
                                $cart->updateQuantity($product_id, $quantity);
                            } else {
                                $cart->removeItem($product_id);
                            }
                        }
                        $_SESSION['success'] = 'Cart updated successfully';
                        
                        // If it's an AJAX request, return success response
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true]);
                            exit;
                        }
                        
                        // If it's a regular form submission, redirect to checkout
                        redirect(base_url('customer/checkout.php'));
                    }
                }
                
                // If it's an AJAX request, return error response
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'No quantities provided']);
                    exit;
                }
                break;
        }
    } catch (Exception $e) {
        // If it's an AJAX request, return error response
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    // For non-AJAX requests, redirect back to cart
    if (!$is_ajax) {
        redirect(base_url('customer/cart.php'));
    }
    
    // For AJAX requests that reach here, return a generic success response
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get cart items grouped by supplier
$suppliers = $cart->getItemsBySupplier();
$cart_total = $cart->getTotal();
$cart_count = $cart->getItemCount();

// Validate cart items
$validation_issues = $cart->validateItems();

$page_title = 'Shopping Cart';
include '../includes/customer_header.php';
?>

<!-- Shopee-inspired Cart Page -->
<main class="cart-page shopee-theme">
    <div class="container-xl py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h2>
            <div>
                <?php if (!empty($suppliers)): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear your cart?')">
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i> Clear Cart</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($validation_issues)): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Cart Issues Detected</h5>
                <ul class="mb-2">
                    <?php foreach ($validation_issues as $issue): ?>
                        <li>
                            <?php if ($issue['type'] === 'price_change'): ?>
                                <strong><?php echo htmlspecialchars($issue['species_name']); ?>:</strong>
                                Price changed from <?php echo format_currency($issue['old_price']); ?> to <?php echo format_currency($issue['new_price']); ?>
                            <?php elseif ($issue['type'] === 'insufficient_stock'): ?>
                                <strong><?php echo htmlspecialchars($issue['species_name']); ?>:</strong>
                                Only <?php echo $issue['available']; ?> items available (you have <?php echo $issue['requested']; ?>)
                            <?php elseif ($issue['type'] === 'below_minimum'): ?>
                                <strong><?php echo htmlspecialchars($issue['species_name']); ?>:</strong>
                                Minimum order is <?php echo $issue['minimum']; ?> (you have <?php echo $issue['quantity']; ?>)
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="update_prices">
                    <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-sync me-1"></i> Update Prices</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (empty($suppliers)): ?>
            <div class="empty-cart text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">Your cart is empty</h3>
                <p class="text-muted mb-3">Start browsing our marketplace to find the best fingerlings for your needs.</p>
                <a href="dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-search me-1"></i> Browse Products</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <?php foreach ($suppliers as $supplier_id => $supplier_data): ?>
                        <div class="supplier-card mb-4">
                            <div class="supplier-card-header">
                                <div>
                                    <h5 class="mb-0"><i class="fas fa-store me-2"></i><?php echo htmlspecialchars($supplier_data['supplier_info']['business_name']); ?></h5>
                                    <div class="small text-muted"><?php echo htmlspecialchars($supplier_data['supplier_info']['location']); ?></div>
                                </div>
                                <div class="supplier-actions">
                                    <a href="suppliers.php?supplier=<?php echo $supplier_id; ?>" class="small text-decoration-none">View Store</a>
                                </div>
                            </div>

                            <div class="supplier-items">
                                <?php foreach ($supplier_data['items'] as $item): ?>
                                    <div class="cart-item row align-items-center">
                                        <div class="col-3 col-md-2">
                                            <?php 
                                            // Image display with fallbacks: inventory image > species image > placeholder
                                            $image_src = asset_url('images/placeholder-fish.jpg'); // default placeholder
                                            
                                            if (!empty($item['image_path'])) {
                                                // Use inventory-specific image
                                                $image_src = base_url($item['image_path']);
                                            } elseif (!empty($item['image_url'])) {
                                                // Fallback to species image
                                                $image_src = $item['image_url'];
                                            }
                                            ?>
                                            <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($item['species_name'] ?? ''); ?>" class="img-fluid rounded" onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                        </div>
                                        <div class="col-5 col-md-4">
                                            <div class="fw-bold"><?php echo htmlspecialchars($item['species_name'] ?? ''); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($item['scientific_name'] ?? ''); ?></div>
                                            <div class="mt-1"><span class="badge bg-info small"><?php echo ucfirst($item['size_category'] ?? 'Standard'); ?></span></div>
                                        </div>
                                        <div class="col-4 col-md-2 text-end">
                                            <div class="fw-bold"><?php echo format_currency($item['price_per_piece']); ?></div>
                                            <div class="small text-muted">per piece</div>
                                        </div>
                                        <div class="col-12 col-md-3 mt-2 mt-md-0">
                                            <div class="d-flex align-items-center justify-content-md-center gap-2">
                                                <div class="input-group qty">
                                                    <button class="btn btn-sm btn-outline-secondary decrease-btn" type="button" data-product-id="<?php echo $item['product_id']; ?>" data-current-qty="<?php echo $item['quantity']; ?>">-</button>
                                                    <input type="number" min="<?php echo $item['minimum_order']; ?>" max="<?php echo $item['stock_quantity']; ?>" value="<?php echo $item['quantity']; ?>" id="qty-<?php echo $item['product_id']; ?>" class="form-control form-control-sm text-center quantity-input" data-product-id="<?php echo $item['product_id']; ?>">
                                                    <button class="btn btn-sm btn-outline-secondary increase-btn" type="button" data-product-id="<?php echo $item['product_id']; ?>" data-current-qty="<?php echo $item['quantity']; ?>" data-max-stock="<?php echo $item['stock_quantity']; ?>">+</button>
                                                </div>
                                                <div class="ms-2 text-center">
                                                    <div class="fw-bold"><?php echo format_currency($item['subtotal']); ?></div>
                                                    <div class="small text-muted">Subtotal</div>
                                                </div>
                                                <div class="ms-2">
                                                    <button class="btn btn-sm btn-outline-danger remove-item-btn" type="button" data-product-id="<?php echo $item['product_id']; ?>"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-1">Min: <?php echo $item['minimum_order']; ?> | Available: <?php echo $item['stock_quantity']; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="supplier-footer mt-3 d-flex justify-content-end">
                                <div class="me-3 text-muted">Supplier Total:</div>
                                <div class="fw-bold"><?php echo format_currency($supplier_data['total']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="col-lg-4">
                    <div class="summary-card sticky-summary">
                        <div class="summary-header">
                            <h5 class="mb-0">Order Summary</h5>
                            <div class="small text-muted"><?php echo $cart_count; ?> item(s) • <?php echo count($suppliers); ?> supplier(s)</div>
                        </div>

                        <div class="summary-body mt-3">
                            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span><?php echo format_currency($cart_total); ?></span></div>
                            <div class="d-flex justify-content-between mb-2"><span>Delivery</span><span class="text-muted">Calculated at checkout</span></div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3"><strong>Total</strong><strong><?php echo format_currency($cart_total); ?></strong></div>

                            <?php if (empty($validation_issues)): ?>
                                <button id="proceed-to-checkout" class="btn btn-primary w-100 btn-lg"><i class="fas fa-credit-card me-1"></i> Proceed to Checkout</button>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100 btn-lg" disabled><i class="fas fa-exclamation-triangle me-1"></i> Resolve Issues First</button>
                            <?php endif; ?>

                            <div class="mt-3 text-center small text-muted">Secure checkout • 256-bit SSL</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.shopee-theme { --accent: #ee4d2d; --accent-dark: #ff7337; }
.cart-page { font-family: 'Poppins', sans-serif; }
.btn-light-outline { background: transparent; border: 1px solid #e6e6e6; color: #333; padding: .4rem .7rem; border-radius: 8px; }
.btn-outline-danger { border: 1px solid #f5c6cb; color: #c82333; background: transparent; padding: .4rem .7rem; border-radius: 8px; }

.supplier-card { background: #fff; border-radius: 12px; padding: 1rem; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
.supplier-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom: .8rem; }
.supplier-items .cart-item { padding: .75rem 0; border-bottom: 1px solid #f2f2f2; }
.supplier-items .cart-item:last-child { border-bottom: none; }
.qty .form-control { width: 72px; }

.summary-card { background: linear-gradient(180deg, #fff 0%, #fff 100%); border-radius: 12px; padding: 1rem; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
.summary-header { border-bottom: 1px solid #f4f4f4; padding-bottom: .5rem; }
.btn-primary { background: linear-gradient(90deg, var(--accent), var(--accent-dark)); border: none; color: #fff; border-radius: 8px; }

/* Sticky summary */
.sticky-summary { position: sticky; top: 90px; }

@media (max-width: 991px) {
    .sticky-summary { position: static; }
    .summary-card { margin-top: 1rem; }
}

/* small screen tweaks */
@media (max-width: 576px) {
    .supplier-items .cart-item { display: grid; grid-template-columns: 80px 1fr; gap: .5rem; align-items: center; }
    .supplier-items .cart-item .col-12 { grid-column: 1 / -1; }
    .qty .form-control { width: 56px; }
}
</style>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle increase button clicks
    document.querySelectorAll('.increase-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const maxStock = parseInt(this.dataset.maxStock) || Infinity;
            const input = document.getElementById('qty-' + productId);
            let val = parseInt(input.value, 10) || 0;
            
            if (val < maxStock) {
                val++;
                input.value = val;
                updateQuantityDisplay(productId, val);
            }
        });
    });

    // Handle decrease button clicks
    document.querySelectorAll('.decrease-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const input = document.getElementById('qty-' + productId);
            let val = parseInt(input.value, 10) || 0;
            
            if (val <= 1) {
                if (confirm('Remove this item from cart?')) {
                    removeItem(productId);
                }
                return;
            }
            
            val--;
            input.value = val;
            updateQuantityDisplay(productId, val);
        });
    });

    // Handle direct input changes
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            let val = parseInt(this.value, 10) || 0;
            const minOrder = parseInt(this.min) || 1;
            const maxStock = parseInt(this.max) || Infinity;
            
            // Validate input
            if (val < minOrder) {
                val = minOrder;
                this.value = val;
                alert('Quantity cannot be less than minimum order: ' + minOrder);
            } else if (val > maxStock) {
                val = maxStock;
                this.value = val;
                alert('Quantity cannot exceed available stock: ' + maxStock);
            }
            
            updateQuantityDisplay(productId, val);
        });
        
        // Add input event listener to prevent typing values below minimum
        input.addEventListener('input', function() {
            const minOrder = parseInt(this.min) || 1;
            const maxStock = parseInt(this.max) || Infinity;
            let val = parseInt(this.value, 10) || 0;
            
            // Prevent typing values below minimum
            if (val < 0) {
                this.value = '';
            }
        });
        
        // Add blur event listener to enforce minimum after user finishes typing
        input.addEventListener('blur', function() {
            const productId = this.dataset.productId;
            let val = parseInt(this.value, 10) || 0;
            const minOrder = parseInt(this.min) || 1;
            const maxStock = parseInt(this.max) || Infinity;
            
            // Enforce minimum order quantity when user leaves the field
            if (val < minOrder) {
                val = minOrder;
                this.value = val;
                alert('Quantity cannot be less than minimum order: ' + minOrder);
            } else if (val > maxStock) {
                val = maxStock;
                this.value = val;
                alert('Quantity cannot exceed available stock: ' + maxStock);
            }
            
            updateQuantityDisplay(productId, val);
        });
    });

    // Handle remove item button clicks
    document.querySelectorAll('.remove-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            if (confirm('Remove this item from cart?')) {
                removeItem(productId);
            }
        });
    });

    // Handle proceed to checkout button
    document.getElementById('proceed-to-checkout').addEventListener('click', function() {
        updateAllQuantitiesAndProceed();
    });
});

// Update quantity display without page refresh
function updateQuantityDisplay(productId, quantity) {
    // Update the subtotal display for this item
    const quantityInput = document.getElementById('qty-' + productId);
    if (quantityInput) {
        // Find the parent cart item
        const cartItem = quantityInput.closest('.cart-item');
        if (cartItem) {
            // Get the price per piece (from the element displaying it)
            const priceElement = cartItem.querySelector('.fw-bold');
            if (priceElement) {
                const priceText = priceElement.textContent;
                const price = parseFloat(priceText.replace(/[^0-9.-]+/g,""));
                if (!isNaN(price)) {
                    const subtotal = price * quantity;
                    // Find the subtotal display element (the fw-bold element after the quantity controls)
                    const subtotalElements = cartItem.querySelectorAll('.fw-bold');
                    if (subtotalElements.length >= 2) {
                        // The second .fw-bold element should be the subtotal
                        const subtotalElement = subtotalElements[1];
                        subtotalElement.textContent = '₱' + subtotal.toFixed(2);
                    }
                }
            }
        }
    }
    
    // Update the cart total
    updateCartTotal();
}

// Update the cart total display
function updateCartTotal() {
    // Calculate the new total
    let total = 0;
    document.querySelectorAll('.quantity-input').forEach(input => {
        const quantity = parseInt(input.value) || 0;
        const cartItem = input.closest('.cart-item');
        if (cartItem) {
            const priceElement = cartItem.querySelector('.fw-bold');
            if (priceElement) {
                const priceText = priceElement.textContent;
                const price = parseFloat(priceText.replace(/[^0-9.-]+/g,""));
                if (!isNaN(price)) {
                    total += price * quantity;
                }
            }
        }
    });
    
    // Update the total display
    const totalElements = document.querySelectorAll('.summary-card .fw-bold');
    totalElements.forEach(element => {
        if (element.textContent.includes('₱') || element.textContent.includes('Total')) {
            element.innerHTML = '₱' + total.toFixed(2);
        }
    });
    
    // Also update the items count
    const itemCount = document.querySelectorAll('.quantity-input').length;
    const countElements = document.querySelectorAll('.summary-header .small');
    countElements.forEach(element => {
        if (element.textContent.includes('item')) {
            element.textContent = itemCount + ' item(s) • ' + document.querySelectorAll('.supplier-card').length + ' supplier(s)';
        }
    });
}

// Remove item without page refresh (using AJAX)
function removeItem(productId) {
    if (!confirm('Remove this item from cart?')) return;
    
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=remove_item&product_id=' + productId
    })
    .then(response => {
        if (response.ok) {
            // Reload the page to reflect changes
            window.location.reload();
        } else {
            alert('Error removing item from cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing item from cart');
    });
}

// Update all quantities and proceed to checkout
function updateAllQuantitiesAndProceed() {
    // First, collect all quantities
    const quantities = {};
    let hasErrors = false;
    
    document.querySelectorAll('.quantity-input').forEach(input => {
        const productId = input.dataset.productId;
        let quantity = parseInt(input.value, 10) || 0;
        const minOrder = parseInt(input.min) || 1;
        const maxStock = parseInt(input.max) || Infinity;
        
        // Validate quantity before sending
        if (quantity < minOrder) {
            quantity = minOrder;
            input.value = quantity;
            alert('Quantity for one or more items was below minimum and has been adjusted to ' + minOrder);
            hasErrors = true;
        } else if (quantity > maxStock) {
            quantity = maxStock;
            input.value = quantity;
            alert('Quantity for one or more items exceeded available stock and has been adjusted to ' + maxStock);
            hasErrors = true;
        }
        
        quantities[productId] = quantity;
    });
    
    // Send AJAX request to update quantities
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_all_quantities&quantities=' + encodeURIComponent(JSON.stringify(quantities))
    })
    .then(response => {
        // Check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.indexOf('application/json') !== -1) {
            return response.json();
        } else {
            // If it's not JSON, redirect to checkout anyway
            window.location.href = 'checkout.php';
            return null;
        }
    })
    .then(data => {
        if (data && data.success) {
            // Redirect to checkout page after successful update
            window.location.href = 'checkout.php';
        } else if (data && data.success === false) {
            alert('Error updating cart: ' + (data.error || 'Unknown error'));
        } else if (data !== null) {
            // For any other case, redirect to checkout
            window.location.href = 'checkout.php';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Even if there's an error, redirect to checkout
        window.location.href = 'checkout.php';
    });
}
</script>

<?php include '../includes/customer_footer.php'; ?>