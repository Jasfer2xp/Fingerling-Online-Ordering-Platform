<?php
require_once '../config/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$order = null;

if (empty($token)) {
    die("<h3>Access Denied</h3><p>No delivery token provided.</p>");
}

// Validate Token
// Validate Token
try {
    // Fix: Use the wrapper's fetch method instead of calling prepare() directly on the wrapper
    $order = $database->fetch("SELECT * FROM orders WHERE delivery_token = ?", [$token]);

    if (!$order) {
        die("<h3>Invalid Token</h3><p>This delivery link is invalid.</p>");
    }

    // Check expiration
    if (strtotime($order['token_expires_at']) < time()) {
        die("<h3>Link Expired</h3><p>This delivery link has expired (12 hours validity).</p>");
    }

    // Check if already used
    if ($order['token_used']) {
        $success = "Delivery proof has already been submitted for this order.";
    }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof_image']) && !$order['token_used']) {
    try {
        if ($_FILES['proof_image']['error'] !== 0) {
            $error = "File upload error code: " . $_FILES['proof_image']['error'];
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $file_info = pathinfo($_FILES['proof_image']['name']);
            $ext = strtolower($file_info['extension'] ?? '');
            
            // Validate Type
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file type. Only JPG, JPEG, PNG, WEBP are allowed.";
            } 
            // Validate Size (e.g. 10MB limit)
            elseif ($_FILES['proof_image']['size'] > 10 * 1024 * 1024) { 
                 $error = "File size too large. Max 10MB.";
            } else {
                // Save File
                $upload_dir = '../uploads/delivery_proofs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = 'proof_' . $order['id'] . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_path)) {
                    // Update DB
                    $db_path = 'uploads/delivery_proofs/' . $filename; // Relative to project root
                    $now = date('Y-m-d H:i:s');
                    
                    // 1. Insert into delivery_proofs
                    $database->query(
                        "INSERT INTO delivery_proofs (order_id, rider_name, rider_phone, image_path, submitted_at) VALUES (?, ?, ?, ?, ?)", 
                        [$order['id'], $order['delivery_worker'], $order['delivery_phone'], $db_path, $now]
                    );
                    
                    // 2. Update Order
                    $database->query(
                        "UPDATE orders SET status = 'pending_supplier_review', token_used = 1, updated_at = ? WHERE id = ?", 
                        [$now, $order['id']]
                    );
                    
                    // 3. Create Notifications (The Connection)
                    // Get User IDs for Customer and Supplier
                    $user_sql = "SELECT 
                                    (SELECT user_id FROM customers WHERE id = ?) as customer_user_id,
                                    (SELECT user_id FROM suppliers WHERE id = ?) as supplier_user_id";
                    $users = $database->fetch($user_sql, [$order['customer_id'], $order['supplier_id']]);
                    
                    if ($users) {
                        // Notify Customer
                        if ($users['customer_user_id']) {
                            $cust_title = "Order Arrived! - Order #" . $order['order_number'];
                            $cust_msg = "The rider has uploaded proof of delivery. Please check and confirm receipt to complete your order.";
                            $cust_link = "/customer/order-details.php?id=" . $order['id'];
                            
                            $database->query(
                                "INSERT INTO notifications (user_id, customer_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, ?, 'order', ?)",
                                [$users['customer_user_id'], $order['customer_id'], $cust_title, $cust_msg, $cust_link, $now]
                            );
                        }
                        
                        // Notify Supplier
                        if ($users['supplier_user_id']) {
                            $supp_title = "Proof Uploaded - Order #" . $order['order_number'];
                            $supp_msg = "Rider has submitted proof of delivery. Please review it.";
                            $supp_link = "/supplier/orders.php"; // Or specific order link
                            
                            $database->query(
                                "INSERT INTO notifications (user_id, title, message, link, type, created_at) VALUES (?, ?, ?, ?, 'order', ?)",
                                [$users['supplier_user_id'], $supp_title, $supp_msg, $supp_link, $now]
                            );
                        }
                    }
                    
                    $success = "Proof submitted successfully! The supplier has been notified.";
                    $order['token_used'] = 1; // Update local state to hide form
                    
                } else {
                    $error = "Failed to save file to server.";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error processing submission: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Delivery Proof</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { max-width: 500px; margin: 2rem auto; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background: #2c3e50; color: white; border-radius: 12px 12px 0 0 !important; }
        .btn-primary { background: #2c3e50; border: none; }
        .btn-primary:hover { background: #1a252f; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header text-center py-3">
                <h4 class="mb-0">Delivery Proof Submission</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if ($success): ?>
                    <div class="alert alert-success text-center">
                        <h4 class="alert-heading">Success!</h4>
                        <p><?= htmlspecialchars($success) ?></p>
                        <hr>
                        <p class="mb-0">Thank you for your service.</p>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!$order['token_used'] || ($error && !$success)): ?>
                    <div class="text-center mb-4">
                        <h5>Order #<?= htmlspecialchars($order['order_number']) ?></h5>
                        <p class="text-muted">Rider: <?= htmlspecialchars($order['delivery_worker']) ?></p>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="proof_image" class="form-label">Upload Proof of Delivery</label>
                            <input type="file" class="form-control" id="proof_image" name="proof_image" accept="image/*" required>
                            <div class="form-text">Please ensure the image is clear.</div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="mb-3 text-center d-none" id="preview-container">
                            <img id="image-preview" src="#" alt="Preview" class="img-fluid rounded border" style="max-height: 200px;">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Submit Proof</button>
                        </div>
                    </form>
                <?php endif; ?>
                
            </div>
            <div class="card-footer text-center text-muted py-3">
                <small>&copy; <?= date('Y') ?> Fingerlings Market</small>
            </div>
        </div>
    </div>

    <script>
        // Simple Image Preview
        document.getElementById('proof_image')?.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('image-preview');
                    preview.src = e.target.result;
                    document.getElementById('preview-container').classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
