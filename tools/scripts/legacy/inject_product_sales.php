<?php
/**
 * Script to inject sales data for a specific product
 * This will make the product appear in "Most sold Fingerlings" section
 * 
 * Usage: Run this script once via browser or CLI
 * Product ID: 78
 * Target: 2000+ units sold
 * 
 * SAFETY: This script uses transactions and checks to ensure it doesn't break existing data
 */

require_once __DIR__ . '/../config/config.php';

// Configuration
$TARGET_PRODUCT_ID = 78;
$TARGET_QUANTITY = 2100; // Slightly over 2000 to ensure it appears
$ORDERS_TO_CREATE = 10; // Number of fake orders to create

// Security: Only allow this script to run in development or with explicit permission
$ALLOW_EXECUTION = true; // Set to false in production, or add password protection

// Check if running via web browser
$is_web = (php_sapi_name() !== 'cli');

if ($is_web) {
    // Add basic HTML output for web execution
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Inject Product Sales</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
    echo ".success{color:#28a745;}.error{color:#dc3545;}.info{color:#17a2b8;}";
    echo "pre{background:#fff;padding:15px;border-radius:5px;border:1px solid #ddd;}</style></head><body>";
    echo "<h2>Product Sales Injection Script</h2>";
    echo "<pre>";
}

if (!$ALLOW_EXECUTION) {
    $msg = "Script execution is disabled. Set ALLOW_EXECUTION to true to run.";
    if ($is_web) {
        echo "<span class='error'>✗ {$msg}</span></pre></body></html>";
    } else {
        die($msg);
    }
    exit(1);
}

try {
    $database->beginTransaction();
    
    // Step 1: Verify product exists
    $sql = "SELECT i.id, i.supplier_id, i.price_per_piece, i.stock_quantity, 
                   s.business_name, sp.name as species_name
            FROM inventory i
            JOIN suppliers s ON i.supplier_id = s.id
            JOIN species sp ON i.species_id = sp.id
            WHERE i.id = ? AND i.availability_status = 'available'";
    
    $product = $database->fetch($sql, [$TARGET_PRODUCT_ID]);
    
    if (!$product) {
        throw new Exception("Product ID {$TARGET_PRODUCT_ID} not found or not available.");
    }
    
    echo "✓ Product found: {$product['species_name']} from {$product['business_name']}\n";
    echo "  Price: " . format_currency($product['price_per_piece']) . "\n";
    echo "  Current stock: {$product['stock_quantity']}\n\n";
    
    // Step 2: Get current sales count
    $sql = "SELECT COALESCE(SUM(oi.quantity), 0) as current_sold
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.inventory_id = ? AND o.status = 'delivered'";
    
    $current_sales = $database->fetch($sql, [$TARGET_PRODUCT_ID]);
    $current_sold = (int)($current_sales['current_sold'] ?? 0);
    
    echo "✓ Current sales: {$current_sold} units\n";
    
    if ($current_sold >= $TARGET_QUANTITY) {
        echo "✓ Product already has {$current_sold} units sold (target: {$TARGET_QUANTITY})\n";
        echo "  No action needed. Product should already appear in 'Most sold Fingerlings'.\n";
        $database->rollback();
        exit(0);
    }
    
    $needed_quantity = $TARGET_QUANTITY - $current_sold;
    echo "  Need to add: {$needed_quantity} more units\n\n";
    
    // Step 3: Get a valid customer (use first available customer)
    $sql = "SELECT c.id, c.user_id, u.email, c.first_name, c.last_name
            FROM customers c
            JOIN users u ON c.user_id = u.id
            WHERE u.status = 'active'
            ORDER BY c.id ASC
            LIMIT 1";
    
    $customer = $database->fetch($sql);
    
    if (!$customer) {
        throw new Exception("No active customer found. Please create a customer account first.");
    }
    
    echo "✓ Using customer: {$customer['first_name']} {$customer['last_name']} (ID: {$customer['id']})\n";
    echo "✓ Supplier: {$product['business_name']} (ID: {$product['supplier_id']})\n\n";
    
    // Step 4: Create fake orders with delivered status
    $orders_created = 0;
    $total_quantity_added = 0;
    $remaining_quantity = $needed_quantity;
    
    // Distribute quantity across multiple orders
    $quantities = [];
    $base_quantity = floor($needed_quantity / $ORDERS_TO_CREATE);
    $extra = $needed_quantity % $ORDERS_TO_CREATE;
    
    for ($i = 0; $i < $ORDERS_TO_CREATE; $i++) {
        $quantities[] = $base_quantity + ($i < $extra ? 1 : 0);
    }
    
    echo "Creating {$ORDERS_TO_CREATE} orders with delivered status...\n";
    
    foreach ($quantities as $index => $qty) {
        if ($qty <= 0) continue;
        
        // Generate unique order number
        $order_number = 'FP-' . date('Ymd') . '-' . str_pad(time() + $index, 8, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        // Calculate order total
        $subtotal = $qty * $product['price_per_piece'];
        $total_amount = $subtotal; // No delivery fee for simplicity
        
        // Create order
        $sql = "INSERT INTO orders (
                    order_number, 
                    customer_id, 
                    supplier_id, 
                    total_amount, 
                    status, 
                    delivery_address,
                    delivery_date,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, 'delivered', ?, CURDATE(), NOW(), NOW())";
        
        $delivery_address = "Test Address for Order #" . ($index + 1);
        $database->query($sql, [
            $order_number,
            $customer['id'],
            $product['supplier_id'],
            $total_amount,
            $delivery_address
        ]);
        
        $order_id = $database->lastInsertId();
        
        if (!$order_id) {
            throw new Exception("Failed to create order #" . ($index + 1));
        }
        
        // Create order item
        $sql = "INSERT INTO order_items (
                    order_id,
                    inventory_id,
                    quantity,
                    unit_price,
                    subtotal
                ) VALUES (?, ?, ?, ?, ?)";
        
        $database->query($sql, [
            $order_id,
            $TARGET_PRODUCT_ID,
            $qty,
            $product['price_per_piece'],
            $subtotal
        ]);
        
        $orders_created++;
        $total_quantity_added += $qty;
        
        echo "  ✓ Order #{$orders_created}: {$qty} units (Total: {$total_quantity_added})\n";
    }
    
    // Step 5: Verify the final count
    $sql = "SELECT COALESCE(SUM(oi.quantity), 0) as total_sold
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.inventory_id = ? AND o.status = 'delivered'";
    
    $final_sales = $database->fetch($sql, [$TARGET_PRODUCT_ID]);
    $final_sold = (int)($final_sales['total_sold'] ?? 0);
    
    echo "\n";
    echo "✓ Verification:\n";
    echo "  Orders created: {$orders_created}\n";
    echo "  Quantity added: {$total_quantity_added} units\n";
    echo "  Total sold now: {$final_sold} units\n";
    
    if ($final_sold >= $TARGET_QUANTITY) {
        echo "  ✓ SUCCESS! Product now has {$final_sold} units sold (target: {$TARGET_QUANTITY})\n";
        echo "  ✓ Product should now appear in 'Most sold Fingerlings' section\n";
        
        $database->commit();
        echo "\n✓ Transaction committed successfully!\n";
    } else {
        throw new Exception("Verification failed. Expected at least {$TARGET_QUANTITY}, got {$final_sold}.");
    }
    
} catch (Exception $e) {
    $database->rollback();
    $error_msg = "\n✗ ERROR: " . $e->getMessage() . "\n✗ Transaction rolled back. No changes were made.\n";
    if ($is_web) {
        echo "</pre>";
        echo "<div style='margin-top:20px;padding:15px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;'>";
        echo "<strong class='error'>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Transaction rolled back. No changes were made.";
        echo "</div></body></html>";
    } else {
        echo $error_msg;
    }
    exit(1);
}

    echo "\n";
    echo "========================================\n";
    echo "Script completed successfully!\n";
    echo "Product ID {$TARGET_PRODUCT_ID} now has {$final_sold} units sold.\n";
    echo "Check: " . base_url('customer/dashboard.php') . "\n";
    echo "========================================\n";
    
    if ($is_web) {
        echo "</pre>";
        echo "<div style='margin-top:20px;padding:15px;background:#d4edda;border:1px solid #c3e6cb;border-radius:5px;'>";
        echo "<strong class='success'>✓ Success!</strong> Product ID {$TARGET_PRODUCT_ID} now has {$final_sold} units sold.<br>";
        $dashboardUrl = base_url('customer/dashboard.php');
        $productUrl = base_url('customer/view.php?id=' . $TARGET_PRODUCT_ID);
        echo "<a href='{$dashboardUrl}' style='color:#2563eb;text-decoration:none;'>→ View Dashboard</a> | ";
        echo "<a href='{$productUrl}' style='color:#2563eb;text-decoration:none;'>→ View Product</a>";
        echo "</div></body></html>";
    }

