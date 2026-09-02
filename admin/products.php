<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);
$admin = new Admin($database);
$product = new Product($database);

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle product suspension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suspend_product'])) {
    $product_id = (int)$_POST['product_id'];
    $reason = sanitize_input($_POST['reason'] ?? '');
    
    try {
        $product_details = $product->getProductByIdAdmin($product_id);
        
        if ($product_details) {
            $sql = "UPDATE inventory SET availability_status = 'discontinued', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $database->query($sql, [$product_id]);
            
            $notification_title = "Product Suspended";
            $notification_message = "Your product '{$product_details['species_name']}' has been suspended by the administrator. Reason: " . ($reason ?: "Inappropriate content");
            
            // Use Manila timezone for timestamp
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                                VALUES (?, ?, ?, 'system', 0, ?)";
            
            $supplier_sql = "SELECT user_id FROM suppliers WHERE id = ?";
            $supplier = $database->fetch($supplier_sql, [$product_details['supplier_id']]);
            
            if ($supplier) {
                $database->query($notification_sql, [$supplier['user_id'], $notification_title, $notification_message, $manila_time]);
            }
            
            $_SESSION['success'] = "Product suspended successfully and supplier notified.";
        } else {
            $_SESSION['error'] = "Product not found.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error suspending product: " . $e->getMessage();
    }
    
    redirect(base_url('admin/products.php'));
}

// Handle product unsuspension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsuspend_product'])) {
    $product_id = (int)$_POST['product_id'];
    
    try {
        $product_details = $product->getProductByIdAdmin($product_id);
        
        if ($product_details) {
            $sql = "UPDATE inventory SET availability_status = 'available', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $database->query($sql, [$product_id]);
            
            $notification_title = "Product Reinstated";
            $notification_message = "Your product '{$product_details['species_name']}' has been reinstated by the administrator.";
            
            // Use Manila timezone for timestamp
            date_default_timezone_set('Asia/Manila');
            $manila_time = date('Y-m-d H:i:s');
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                                VALUES (?, ?, ?, 'system', 0, ?)";
            
            $supplier_sql = "SELECT user_id FROM suppliers WHERE id = ?";
            $supplier = $database->fetch($supplier_sql, [$product_details['supplier_id']]);
            
            if ($supplier) {
                $database->query($notification_sql, [$supplier['user_id'], $notification_title, $notification_message, $manila_time]);
            }
            
            $_SESSION['success'] = "Product reinstated successfully and supplier notified.";
        } else {
            $_SESSION['error'] = "Product not found.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error reinstating product: " . $e->getMessage();
    }
    
    redirect(base_url('admin/products.php'));
}

// Get all products with supplier info (including suspended ones)
$all_products = $product->getAllProducts([], 100); // Get first 100 products

// Get total sold for each product
$product_ids = array_column($all_products, 'id');
$total_sold = [];
if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $sold_query = "SELECT inventory_id, SUM(quantity) as total_sold 
                   FROM order_items 
                   WHERE inventory_id IN ($placeholders)
                   GROUP BY inventory_id";
    $sold_results = $database->fetchAll($sold_query, $product_ids);
    foreach ($sold_results as $sold) {
        $total_sold[$sold['inventory_id']] = (int)$sold['total_sold'];
    }
}

$page_title = 'Supplier Products';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- ====================== RESPONSIVE STYLES ====================== -->
<style>
    .admin-main-content { padding: 1rem; }
    .card { border-radius: 12px; overflow: hidden; }
    .table th { font-weight: 600; color: #374151; font-size: 0.875rem; }
    .table td { vertical-align: middle; font-size: 0.92rem; }

    /* Header */
    .modern-dashboard-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
        .modern-dashboard-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }

    /* Mobile: Hide desktop table, show cards */
    @media (max-width: 991.98px) {
        .desktop-table { display: none !important; }
        .mobile-card { display: block !important; }
    }
    .mobile-card { display: none; }

    /* No horizontal scroll */
    body { overflow-x: hidden; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    /* Image */
    .product-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
    }

    /* Buttons */
    .btn-group .btn { padding: 0.35rem 0.5rem; font-size: 0.875rem; }
</style>

<main class="admin-main-content">
    <div class="container-fluid px-0 px-md-4">

        <!-- Dashboard Header -->
        <div class="modern-dashboard-header">
            <div>
                <h1 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-fish text-primary"></i>
                    Supplier Products
                </h1>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Products Table / Mobile Cards -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-none d-lg-block">
                <h5 class="mb-0">All Supplier Products</h5>
            </div>
            <div class="card-body p-0">

                <!-- Desktop Table -->
                <div class="table-responsive desktop-table">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_products as $product_item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $image_path = '';
                                            if (!empty($product_item['image_path'])) {
                                                $image_path = str_replace('../', '', $product_item['image_path']);
                                                $image_src = base_url($image_path);
                                            } elseif (!empty($product_item['image_url'])) {
                                                $image_src = $product_item['image_url'];
                                            } else {
                                                $image_src = asset_url('images/placeholder-fish.jpg');
                                            }
                                            ?>
                                            <img src="<?php echo $image_src; ?>" 
                                                 alt="<?php echo htmlspecialchars($product_item['species_name']); ?>"
                                                 class="product-img me-3"
                                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($product_item['species_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($product_item['scientific_name'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($product_item['business_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars($product_item['city']); ?>, 
                                            <?php echo htmlspecialchars($product_item['province']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo format_currency($product_item['price_per_piece']); ?></td>
                                    <td><?php echo number_format($product_item['stock_quantity']); ?> pcs</td>
                                    <td>
                                        <?php if ($product_item['size_category']): ?>
                                            <span class="badge bg-info"><?php echo ucfirst($product_item['size_category']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product_item['availability_status'] === 'available'): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif ($product_item['availability_status'] === 'out_of_stock'): ?>
                                            <span class="badge bg-warning">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group" role="group">
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $product_item['id']; ?>">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                            <?php if ($product_item['availability_status'] === 'available'): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#suspendModal<?php echo $product_item['id']; ?>">
                                                    <i class="fas fa-ban me-1"></i> Suspend
                                                </button>
                                            <?php elseif ($product_item['availability_status'] === 'discontinued'): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#unsuspendModal<?php echo $product_item['id']; ?>">
                                                    <i class="fas fa-check-circle me-1"></i> Unsuspend
                                                </button>
                                            <?php else: ?>
                                                <span class="btn btn-outline-secondary btn-sm disabled">
                                                    <i class="fas fa-ban me-1"></i> <?php echo ucfirst($product_item['availability_status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View Product Modal -->
                                <div class="modal fade" id="viewModal<?php echo $product_item['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Product Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-4">
                                                    <!-- Product Image -->
                                                    <div class="col-md-5">
                                                        <?php
                                                        $image_path = '';
                                                        if (!empty($product_item['image_path'])) {
                                                            $image_path = str_replace('../', '', $product_item['image_path']);
                                                            $image_src = base_url($image_path);
                                                        } elseif (!empty($product_item['image_url'])) {
                                                            $image_src = $product_item['image_url'];
                                                        } else {
                                                            $image_src = asset_url('images/placeholder-fish.jpg');
                                                        }
                                                        ?>
                                                        <img src="<?php echo $image_src; ?>" 
                                                             alt="<?php echo htmlspecialchars($product_item['species_name']); ?>"
                                                             class="img-fluid rounded"
                                                             style="max-height: 400px; width: 100%; object-fit: cover;"
                                                             onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                                    </div>
                                                    
                                                    <!-- Product Information -->
                                                    <div class="col-md-7">
                                                        <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($product_item['species_name']); ?></h4>
                                                        
                                                        <?php if (!empty($product_item['scientific_name'])): ?>
                                                            <p class="text-muted mb-3">
                                                                <i class="fas fa-fish text-primary me-2"></i>
                                                                <em><?php echo htmlspecialchars($product_item['scientific_name']); ?></em>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="mb-4">
                                                            <h6 class="fw-bold mb-3">Product Information</h6>
                                                            <div class="row g-3">
                                                                <div class="col-6">
                                                                    <div class="border rounded p-3">
                                                                        <div class="small text-muted mb-1">Price</div>
                                                                        <div class="h5 mb-0 text-success fw-bold"><?php echo format_currency($product_item['price_per_piece']); ?></div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="border rounded p-3">
                                                                        <div class="small text-muted mb-1">Current Stock</div>
                                                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($product_item['stock_quantity']); ?> pcs</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="border rounded p-3">
                                                                        <div class="small text-muted mb-1">Total Sold</div>
                                                                        <div class="h5 mb-0 fw-bold text-primary"><?php echo number_format($total_sold[$product_item['id']] ?? 0); ?> pcs</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="border rounded p-3">
                                                                        <div class="small text-muted mb-1">Size</div>
                                                                        <div class="h5 mb-0">
                                                                            <?php if ($product_item['size_category']): ?>
                                                                                <span class="badge bg-info fs-6"><?php echo ucfirst($product_item['size_category']); ?></span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-secondary fs-6">N/A</span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <h6 class="fw-bold mb-2">Supplier</h6>
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-store text-primary me-2"></i>
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($product_item['business_name']); ?></div>
                                                                    <div class="small text-muted">
                                                                        <?php echo htmlspecialchars($product_item['city']); ?>, 
                                                                        <?php echo htmlspecialchars($product_item['province']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <h6 class="fw-bold mb-2">Status</h6>
                                                            <?php if ($product_item['availability_status'] === 'available'): ?>
                                                                <span class="badge bg-success fs-6">Available</span>
                                                            <?php elseif ($product_item['availability_status'] === 'out_of_stock'): ?>
                                                                <span class="badge bg-warning fs-6">Out of Stock</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger fs-6">Suspended</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if (!empty($product_item['description'])): ?>
                                                            <div class="mb-3">
                                                                <h6 class="fw-bold mb-2">Description</h6>
                                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($product_item['description'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Suspend Modal (Desktop) -->
                                <?php if ($product_item['availability_status'] === 'available'): ?>
                                    <div class="modal fade" id="suspendModal<?php echo $product_item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="product_id" value="<?php echo $product_item['id']; ?>">
                                                    <input type="hidden" name="suspend_product" value="1">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Suspend Product</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to suspend this product?</p>
                                                        <p><strong><?php echo htmlspecialchars($product_item['species_name']); ?></strong> by <strong><?php echo htmlspecialchars($product_item['business_name']); ?></strong></p>
                                                        <div class="mb-3">
                                                            <label for="reason<?php echo $product_item['id']; ?>" class="form-label">Reason (optional)</label>
                                                            <textarea class="form-control" id="reason<?php echo $product_item['id']; ?>" name="reason" rows="3" placeholder="Enter reason for suspension..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Suspend Product</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Unsuspend Modal (Desktop) -->
                                <?php if ($product_item['availability_status'] === 'discontinued'): ?>
                                    <div class="modal fade" id="unsuspendModal<?php echo $product_item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="product_id" value="<?php echo $product_item['id']; ?>">
                                                    <input type="hidden" name="unsuspend_product" value="1">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reinstate Product</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reinstate this product?</p>
                                                        <p><strong><?php echo htmlspecialchars($product_item['species_name']); ?></strong> by <strong><?php echo htmlspecialchars($product_item['business_name']); ?></strong></p>
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Supplier will be notified that this product has been reinstated.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">Reinstate Product</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-card">
                    <?php foreach ($all_products as $product_item): ?>
                        <div class="border-bottom p-3">
                            <div class="d-flex align-items-center mb-2">
                                <?php
                                $image_path = '';
                                if (!empty($product_item['image_path'])) {
                                    $image_path = str_replace('../', '', $product_item['image_path']);
                                    $image_src = base_url($image_path);
                                } elseif (!empty($product_item['image_url'])) {
                                    $image_src = $product_item['image_url'];
                                } else {
                                    $image_src = asset_url('images/placeholder-fish.jpg');
                                }
                                ?>
                                <img src="<?php echo $image_src; ?>" 
                                     alt="<?php echo htmlspecialchars($product_item['species_name']); ?>"
                                     class="product-img me-3"
                                     onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($product_item['species_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($product_item['scientific_name'] ?? ''); ?></div>
                                </div>
                                <?php if ($product_item['availability_status'] === 'available'): ?>
                                    <span class="badge bg-success">Available</span>
                                <?php elseif ($product_item['availability_status'] === 'out_of_stock'): ?>
                                    <span class="badge bg-warning">Out of Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Suspended</span>
                                <?php endif; ?>
                            </div>

                            <div class="small text-muted mb-2">
                                <div><strong>Supplier:</strong> <?php echo htmlspecialchars($product_item['business_name']); ?></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($product_item['city']); ?>, <?php echo htmlspecialchars($product_item['province']); ?></div>
                                <div><strong>Price:</strong> <?php echo format_currency($product_item['price_per_piece']); ?> · <strong>Stock:</strong> <?php echo number_format($product_item['stock_quantity']); ?> pcs</div>
                                <div><strong>Size:</strong> 
                                    <?php if ($product_item['size_category']): ?>
                                        <span class="badge bg-info"><?php echo ucfirst($product_item['size_category']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex gap-1">
                                <button type="button" 
                                        class="btn btn-outline-primary btn-sm flex-fill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModalMobile<?php echo $product_item['id']; ?>">
                                    <i class="fas fa-eye me-1"></i> View
                                </button>
                                <?php if ($product_item['availability_status'] === 'available'): ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm flex-fill" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#suspendModalMobile<?php echo $product_item['id']; ?>">
                                        <i class="fas fa-ban me-1"></i> Suspend
                                    </button>
                                <?php elseif ($product_item['availability_status'] === 'discontinued'): ?>
                                    <button type="button" 
                                            class="btn btn-outline-success btn-sm flex-fill" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#unsuspendModalMobile<?php echo $product_item['id']; ?>">
                                        <i class="fas fa-check-circle me-1"></i> Unsuspend
                                    </button>
                                <?php else: ?>
                                    <span class="btn btn-outline-secondary btn-sm flex-fill disabled">
                                        <i class="fas fa-ban me-1"></i> <?php echo ucfirst($product_item['availability_status']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- View Product Modal (Mobile) -->
                        <div class="modal fade" id="viewModalMobile<?php echo $product_item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Product Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Product Image -->
                                        <div class="mb-3">
                                            <?php
                                            $image_path = '';
                                            if (!empty($product_item['image_path'])) {
                                                $image_path = str_replace('../', '', $product_item['image_path']);
                                                $image_src = base_url($image_path);
                                            } elseif (!empty($product_item['image_url'])) {
                                                $image_src = $product_item['image_url'];
                                            } else {
                                                $image_src = asset_url('images/placeholder-fish.jpg');
                                            }
                                            ?>
                                            <img src="<?php echo $image_src; ?>" 
                                                 alt="<?php echo htmlspecialchars($product_item['species_name']); ?>"
                                                 class="img-fluid rounded"
                                                 style="max-height: 250px; width: 100%; object-fit: cover;"
                                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>';">
                                        </div>
                                        
                                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($product_item['species_name']); ?></h5>
                                        
                                        <?php if (!empty($product_item['scientific_name'])): ?>
                                            <p class="text-muted mb-3">
                                                <em><?php echo htmlspecialchars($product_item['scientific_name']); ?></em>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="small text-muted">Price</div>
                                                    <div class="fw-bold text-success"><?php echo format_currency($product_item['price_per_piece']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="small text-muted">Stock</div>
                                                    <div class="fw-bold"><?php echo number_format($product_item['stock_quantity']); ?> pcs</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="small text-muted">Total Sold</div>
                                                    <div class="fw-bold text-primary"><?php echo number_format($total_sold[$product_item['id']] ?? 0); ?> pcs</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="small text-muted">Size</div>
                                                    <div class="fw-bold">
                                                        <?php if ($product_item['size_category']): ?>
                                                            <span class="badge bg-info"><?php echo ucfirst($product_item['size_category']); ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">N/A</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Supplier:</strong> <?php echo htmlspecialchars($product_item['business_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($product_item['city']); ?>, <?php echo htmlspecialchars($product_item['province']); ?></small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Status:</strong> 
                                            <?php if ($product_item['availability_status'] === 'available'): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php elseif ($product_item['availability_status'] === 'out_of_stock'): ?>
                                                <span class="badge bg-warning">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Suspended</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($product_item['description'])): ?>
                                            <div class="mb-3">
                                                <strong>Description:</strong>
                                                <p class="text-muted small mt-1"><?php echo nl2br(htmlspecialchars($product_item['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Suspend Modal (Mobile) -->
                        <?php if ($product_item['availability_status'] === 'available'): ?>
                            <div class="modal fade" id="suspendModalMobile<?php echo $product_item['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product_item['id']; ?>">
                                            <input type="hidden" name="suspend_product" value="1">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Suspend Product</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong><?php echo htmlspecialchars($product_item['species_name']); ?></strong></p>
                                                <div class="mb-3">
                                                    <label class="form-label">Reason (optional)</label>
                                                    <textarea class="form-control" name="reason" rows="2" placeholder="Enter reason..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer flex-column gap-2">
                                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger w-100">Suspend</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Unsuspend Modal (Mobile) -->
                        <?php if ($product_item['availability_status'] === 'discontinued'): ?>
                            <div class="modal fade" id="unsuspendModalMobile<?php echo $product_item['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product_item['id']; ?>">
                                            <input type="hidden" name="unsuspend_product" value="1">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reinstate</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong><?php echo htmlspecialchars($product_item['species_name']); ?></strong></p>
                                                <div class="alert alert-info small">
                                                    Supplier will be notified.
                                                </div>
                                            </div>
                                            <div class="modal-footer flex-column gap-2">
                                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success w-100">Reinstate</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Toggle sidebar on mobile
    document.querySelector('.modern-sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebarMenu').classList.toggle('show');
    });
</script>

</body>
</html>