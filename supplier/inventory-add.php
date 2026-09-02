<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an approved supplier
if (!is_logged_in() || get_user_type() !== 'supplier') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$profile = $user->getUserProfile($user_id);

if ($profile['status'] !== 'approved') {
    redirect(base_url('supplier/dashboard.php'));
}

$supplier_id = $profile['id'];
$supplier = new Supplier($database, $supplier_id);
$product = new Product($database);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $species_name = sanitize_input($_POST['species_name']);
    $size_category = intval($_POST['size_category']); // Convert to integer
    $stock_quantity = intval($_POST['stock_quantity']);
    $description = sanitize_input($_POST['description']);
    
    // Parse price with commas and decimals
    $price_input = str_replace(',', '', $_POST['price_per_piece']); // Remove commas
    $price_per_piece = floatval($price_input); // Convert to float
    $minimum_order = intval($_POST['minimum_order']);
    
    // Handle image upload
    $image_path = '';
    $image_uploaded = false;
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = UPLOAD_PATH . 'products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                $image_path = 'uploads/products/' . $new_filename;
                $image_uploaded = true;
            } else {
                $error = 'Failed to upload image.';
            }
        } else {
            $error = 'Invalid file type. Please upload JPG, JPEG, PNG, or GIF images.';
        }
    } else {
        $error = 'Please upload an image for the product.';
    }

    // Validation
    if (empty($species_name)) {
        $error = 'Please enter a species name.';
    } elseif ($size_category <= 0) { // Validate size category as positive number
        $error = 'Please enter a valid size category (in inches).';
    } elseif ($stock_quantity < 0) {
        $error = 'Stock quantity cannot be negative.';
    } elseif ($price_per_piece <= 0) {
        $error = 'Price must be greater than zero.';
    } elseif ($minimum_order <= 0) {
        $error = 'Minimum order must be at least 1.';
    } elseif (!$image_uploaded) {
        // Error already set above
    } else {
        try {
            // Check if species exists, if not create it
            $species = $database->fetch("SELECT id FROM species WHERE name = ?", [$species_name]);
            $species_id = null;
            
            if ($species) {
                $species_id = $species['id'];
            } else {
                // Create new species
                $sql = "INSERT INTO species (name, status) VALUES (?, 'active')";
                $database->query($sql, [$species_name]);
                $species_id = $database->lastInsertId();
            }
            
            // Add description to the inventory item
            $supplier->addInventoryItem($species_id, $size_category, $stock_quantity, $price_per_piece, $minimum_order, $image_path, $description);
            $_SESSION['success'] = 'Product added to inventory successfully!';
            redirect(base_url('supplier/inventory.php'));
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// GET SPECIES FROM DATABASE — EXACT 8, NO DUPLICATES
$species_list = $product->getAllSpecies();

$page_title = 'Add Product to Inventory';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="add-product-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold">Add Product to Inventory</h3>
                <a href="inventory.php" class="btn btn-outline-secondary btn-sm">
                    Back to Inventory
                </a>
            </div>
        </section>

        <section class="add-product-form">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Add New Product</h5>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                                <div class="row g-3 mb-3">
                                    <!-- SPECIES INPUT FIELD -->
                                    <div class="col-md-6">
                                        <label for="species_name" class="form-label">Species Name *</label>
                                        <input type="text" class="form-control" id="species_name" name="species_name" 
                                               value="<?= htmlspecialchars($_POST['species_name'] ?? '') ?>" 
                                               placeholder="Enter species name" required>
                                        <div class="form-text">Start typing to see suggestions or enter a new species</div>
                                        <div class="invalid-feedback">Please enter a species name.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="size_category" class="form-label">Size Category *</label>
                                        <input type="number" class="form-control" id="size_category" name="size_category" 
                                               value="<?= htmlspecialchars($_POST['size_category'] ?? '') ?>" 
                                               min="1" required>
                                        <div class="form-text">Size in inches (e.g., 1, 2, 3, etc.)</div>
                                        <div class="invalid-feedback">Please enter a size in inches.</div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="stock_quantity" class="form-label">Initial Stock Quantity *</label>
                                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                               value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '') ?>" 
                                               min="0" required>
                                        <div class="form-text">How many items do you have in stock?</div>
                                        <div class="invalid-feedback">Please enter a valid stock quantity.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="price_per_piece" class="form-label">Price per Piece (PHP) *</label>
                                        <input type="text" class="form-control" id="price_per_piece" name="price_per_piece" 
                                               value="<?= htmlspecialchars($_POST['price_per_piece'] ?? '') ?>" 
                                               placeholder="0.00" required>
                                        <div class="form-text">Enter price in PHP (e.g., 1.50)</div>
                                        <div class="invalid-feedback">Please enter a valid price.</div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="minimum_order" class="form-label">Minimum Order Quantity *</label>
                                        <input type="number" class="form-control" id="minimum_order" name="minimum_order" 
                                               value="<?= htmlspecialchars($_POST['minimum_order'] ?? '1') ?>" 
                                               min="1" required>
                                        <div class="form-text">Minimum number of items a customer must order</div>
                                        <div class="invalid-feedback">Please enter a valid minimum order quantity.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="product_image" class="form-label">Product Image *</label>
                                        <input type="file" class="form-control" id="product_image" name="product_image" 
                                               accept=".jpg,.jpeg,.png,.gif" required>
                                        <div class="form-text">Upload an image of your product (JPG, PNG, GIF)</div>
                                        <div class="invalid-feedback">Please upload a product image.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Product Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="3" placeholder="Describe your product..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                    <div class="form-text">Provide additional details about your product (optional)</div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Add to Inventory</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm bg-white mt-4" id="species_info_card" style="display:none;">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Species Information</h6>
                            <div id="species_info_content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<style>
    .alert { border-radius: .5rem; }
    @media (max-width: 576px) {
        .container-fluid { padding: .5rem; }
        .form-label, .form-control, .form-select, .form-text { font-size: .85rem; }
        .btn { font-size: .8rem; padding: .25rem .5rem; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Form validation
(() => {
    'use strict';
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
})();

/* ========== PRICE INPUT: CLEAN, NO DEFAULT, NO STUCK 0 ========== */
const priceInput = document.getElementById('price_per_piece');
let isFormatting = false;

function formatPriceValue() {
    if (isFormatting) return;
    isFormatting = true;

    let value = priceInput.value.replace(/[^\d.]/g, ''); // Strip non-numeric except dot
    if (!value) {
        priceInput.value = '';
        isFormatting = false;
        updateEstimatedRevenue();
        return;
    }

    // Ensure only one decimal point
    const parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }

    let [integer, decimal = ''] = value.split('.');
    integer = integer || '0';
    decimal = decimal.substring(0, 2); // Max 2 decimals

    // Always show 2 decimals if number exists
    if (decimal.length === 1) decimal += '0';
    if (!decimal) decimal = '00';

    // Format integer with commas
    integer = parseInt(integer).toLocaleString('en-US');

    const formatted = `${integer}.${decimal}`;
    const cursorPos = integer.length; // Place cursor before decimal

    priceInput.value = formatted;
    priceInput.setSelectionRange(cursorPos, cursorPos);

    isFormatting = false;
    updateEstimatedRevenue();
}

// Trigger on input
priceInput.addEventListener('input', formatPriceValue);

// On focus: if empty, show placeholder behavior (but don't set value)
priceInput.addEventListener('focus', () => {
    if (!priceInput.value) {
        priceInput.placeholder = '0.00';
    }
});

// On blur: if empty, keep empty (no 0.00)
priceInput.addEventListener('blur', () => {
    if (!priceInput.value) {
        priceInput.placeholder = '0.00';
    }
});

/* ========== ESTIMATED REVENUE ========== */
function updateEstimatedRevenue() {
    const stock = parseFloat(document.getElementById('stock_quantity').value) || 0;
    const priceStr = priceInput.value || '0.00';
    const price = parseFloat(priceStr.replace(/,/g, '')) || 0;
    const revenue = stock * price;

    document.getElementById('estimated_revenue').textContent =
        'PHP' + revenue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.getElementById('stock_quantity').addEventListener('input', updateEstimatedRevenue);
priceInput.addEventListener('input', updateEstimatedRevenue);

/* ========== INITIALIZE ========== */
document.addEventListener('DOMContentLoaded', () => {
    priceInput.value = ''; // Start completely empty
    updateEstimatedRevenue();
});
</script>
</body>
</html>