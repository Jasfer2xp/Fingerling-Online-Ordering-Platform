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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_stock':
                $inventory_id = intval($_POST['inventory_id']);
                
                // Handle Species: Find ID or Create New
                $species_name = trim($_POST['species_name']);
                $species_id = 0;
                
                if (!empty($species_name)) {
                    // Check if exists
                    $check_species = "SELECT id FROM species WHERE name LIKE ?";
                    $existing = $database->fetch($check_species, [$species_name]);
                    
                    if ($existing) {
                        $species_id = $existing['id'];
                    } else {
                        // Create new
                        $insert_species = "INSERT INTO species (name, scientific_name, description, category_id) VALUES (?, '', 'Custom species', 1)";
                        // Note: Defaulting valid_category_id to 1 (usually Fish/Seafood). Adjust if needed.
                        $database->query($insert_species, [$species_name]);
                        $species_id = $database->lastInsertId();
                    }
                }

                $data = [
                    'species_id' => $species_id,
                    'size_category' => $_POST['size_category'],
                    'stock_quantity' => intval($_POST['stock_quantity']),
                    'price_per_piece' => floatval($_POST['price_per_piece']),
                    'minimum_order' => intval($_POST['minimum_order']),
                    'availability_status' => $_POST['availability_status'] ?? 'available'
                ];
                $supplier->updateInventoryItem($inventory_id, $data);
                $_SESSION['success'] = 'Inventory item updated successfully';
                break;
                
            case 'delete_item':
                $inventory_id = intval($_POST['inventory_id']);
                $supplier->deleteInventoryItem($inventory_id);
                $_SESSION['success'] = 'Inventory item deleted successfully';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('supplier/inventory.php'));
}

// Get filters and inventory
$filters = [
    'species' => $_GET['species'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$inventory = $supplier->getInventory($filters);
$species_list = $product->getAllSpecies();

// Map species for display
$species_map = [];
foreach ($species_list as $species) {
    $species_map[$species['id']] = $species['name'];
}

// Get inventory alerts for low stock items
// Get inventory alerts for low stock items
$low_stock_items = $supplier->getInventoryAlerts(10);
$inventory_stats = $supplier->getInventoryStats();

$page_title = 'Inventory Management';
include '../includes/supplier_header.php';
?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php include '../includes/supplier_sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid p-4">
        <section class="inventory-header mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="fw-bold">Inventory Management</h3>
                <div class="btn-group">
                    <a href="inventory-add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-2"></i>Add Product
                    </a>
                    <a href="stock-alerts.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>Stock Alerts 
                        <?php if (!empty($low_stock_items)): ?>
                            <span class="badge bg-danger"><?php echo count($low_stock_items); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Low Stock Alert Banner -->
        <?php if (!empty($low_stock_items)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Low Stock Alert!</strong> You have <?php echo count($low_stock_items); ?> items with low stock.
                <a href="stock-alerts.php" class="alert-link">View details</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Charts Section -->
        <div class="row mb-4">
            <div class="col-12 mb-4">
                <form method="GET" class="d-flex gap-2 bg-white p-3 rounded shadow-sm">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search species..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </form>
            </div>
            
            <!-- Charts -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="card-title fw-bold mb-0">Stock Distribution</h5>
                    </div>
                    <div class="card-body">
                         <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="stockChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="card-title fw-bold mb-0">Inventory Value</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="valueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Modal -->
        <div class="modal fade" id="filtersModal" tabindex="-1" aria-labelledby="filtersModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="filtersModalLabel">Filter Inventory</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="GET">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="species" class="form-label">Species</label>
                                <select class="form-select" id="species" name="species">
                                    <option value="">All Species</option>
                                    <?php foreach ($species_list as $species): ?>
                                        <option value="<?php echo $species['id']; ?>" <?php echo ($filters['species'] == $species['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($species['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="available" <?php echo ($filters['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="out_of_stock" <?php echo ($filters['status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="discontinued" <?php echo ($filters['status'] == 'discontinued') ? 'selected' : ''; ?>>Discontinued</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Search by species name...">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (empty($inventory)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No inventory items found</h4>
                    <p class="text-muted">Add your first product to get started.</p>
                    <a href="inventory-add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Product
                    </a>
                </div>
            </div>
        <?php else: ?>
            <section class="inventory-table">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Species</th>
                                        <th>Size Category</th>
                                        <th>Stock</th>
                                        <th>Price/Piece</th>
                                        <th>Min Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($species_map[$item['species_id']] ?? $item['species_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['size_category']); ?></td>
                                            <td>
                                                <span class="stock-indicator <?php 
                                                    echo match(true) {
                                                        $item['stock_quantity'] <= 0 => 'stock-out',
                                                        $item['stock_quantity'] <= 10 => 'stock-low',
                                                        $item['stock_quantity'] <= 50 => 'stock-medium',
                                                        default => 'stock-high'
                                                    }; ?>"></span>
                                                <?php echo number_format($item['stock_quantity']); ?> pcs
                                            </td>
                                            <td>₱<?php echo number_format($item['price_per_piece'], 2); ?></td>
                                            <td><?php echo number_format($item['minimum_order']); ?> pcs</td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($item['availability_status']) {
                                                        'available' => 'success',
                                                        'out_of_stock' => 'danger',
                                                        'discontinued' => 'secondary',
                                                        default => 'secondary'
                                                    }; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['availability_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $item['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['id']; ?>">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

        <?php endif; ?>
        </section>

        <!-- Edit Modals -->
        <?php foreach ($inventory as $item): ?>
            <div class="modal fade" id="editModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_stock">
                            <input type="hidden" name="inventory_id" value="<?php echo $item['id']; ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editModalLabel<?php echo $item['id']; ?>">Edit Inventory Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="species_name<?php echo $item['id']; ?>" class="form-label">Species Name</label>
                                    <input type="text" class="form-control" id="species_name<?php echo $item['id']; ?>" name="species_name" list="species_list<?php echo $item['id']; ?>" value="<?php echo htmlspecialchars($species_map[$item['species_id']] ?? $item['species_name']); ?>" required>
                                    <datalist id="species_list<?php echo $item['id']; ?>">
                                        <?php foreach ($species_list as $species): ?>
                                            <option value="<?php echo htmlspecialchars($species['name']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="mb-3">
                                    <label for="size_category<?php echo $item['id']; ?>" class="form-label">Size (Number/Unit)</label>
                                    <input type="text" class="form-control" id="size_category<?php echo $item['id']; ?>" name="size_category" value="<?php echo htmlspecialchars($item['size_category']); ?>" placeholder="e.g. 500g, 1kg, 10" required>
                                </div>
                                <div class="mb-3">
                                    <label for="stock_quantity<?php echo $item['id']; ?>" class="form-label">Stock Quantity</label>
                                    <input type="number" class="form-control" id="stock_quantity<?php echo $item['id']; ?>" name="stock_quantity" min="0" value="<?php echo $item['stock_quantity']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="price_per_piece<?php echo $item['id']; ?>" class="form-label">Price per Piece (₱)</label>
                                    <input type="number" class="form-control" id="price_per_piece<?php echo $item['id']; ?>" name="price_per_piece" min="0.01" step="0.01" value="<?php echo $item['price_per_piece']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="minimum_order<?php echo $item['id']; ?>" class="form-label">Minimum Order Quantity</label>
                                    <input type="number" class="form-control" id="minimum_order<?php echo $item['id']; ?>" name="minimum_order" min="1" value="<?php echo $item['minimum_order']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="availability_status<?php echo $item['id']; ?>" class="form-label">Status</label>
                                    <select class="form-select" id="availability_status<?php echo $item['id']; ?>" name="availability_status">
                                        <option value="available" <?php echo ($item['availability_status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                        <option value="out_of_stock" <?php echo ($item['availability_status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                                        <option value="discontinued" <?php echo ($item['availability_status'] == 'discontinued') ? 'selected' : ''; ?>>Discontinued</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Delete Modals -->
        <?php foreach ($inventory as $item): ?>
            <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="delete_item">
                            <input type="hidden" name="inventory_id" value="<?php echo $item['id']; ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel<?php echo $item['id']; ?>">Delete Inventory Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this inventory item?</p>
                                <p><strong><?php echo htmlspecialchars($species_map[$item['species_id']] ?? $item['species_name']); ?> (<?php echo htmlspecialchars($item['size_category']); ?>)</strong></p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    This action cannot be undone.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.stock-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}
.stock-out { background-color: #dc3545; }
.stock-low { background-color: #ffc107; }
.stock-medium { background-color: #198754; }
.stock-high { background-color: #20c997; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inventory Charts
    document.addEventListener('DOMContentLoaded', function() {
        const stats = <?php echo json_encode($inventory_stats); ?>;
        
        // Stock Distribution (Doughnut)
        new Chart(document.getElementById('stockChart'), {
            type: 'doughnut',
            data: {
                labels: stats.map(i => i.species_name),
                datasets: [{
                    data: stats.map(i => i.total_stock),
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#858796', '#5a5c69', '#f8f9fc', '#2e59d9', '#17a673'
                    ]
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });

        // Value Chart (Bar)
        new Chart(document.getElementById('valueChart'), {
            type: 'bar',
            data: {
                labels: stats.map(i => i.species_name),
                datasets: [{
                    label: 'Total Value (₱)',
                    data: stats.map(i => i.total_value),
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>
</body>
</html>