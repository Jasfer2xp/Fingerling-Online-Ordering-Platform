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

// Get user profile
$profile = $user->getUserProfile($user_id);

// Handle species actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_species':
                $name = sanitize_input($_POST['name']);
                $scientific_name = sanitize_input($_POST['scientific_name']);
                $description = sanitize_input($_POST['description']);
                $category = sanitize_input($_POST['category']);
                $image_url = sanitize_input($_POST['image_url']);
                
                if (empty($name)) {
                    throw new Exception('Species name is required');
                }
                
                $admin->addSpecies($name, $scientific_name, $description, $category, $image_url);
                $_SESSION['success'] = 'Species added successfully';
                break;
                
            case 'update_species':
                $species_id = intval($_POST['species_id']);
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'scientific_name' => sanitize_input($_POST['scientific_name']),
                    'description' => sanitize_input($_POST['description']),
                    'category' => sanitize_input($_POST['category']),
                    'image_url' => sanitize_input($_POST['image_url']),
                    'status' => $_POST['status']
                ];
                
                $admin->updateSpecies($species_id, $data);
                $_SESSION['success'] = 'Species updated successfully';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('admin/species.php'));
}

// Get all species
$sql = "SELECT s.*, 
               COUNT(DISTINCT i.id) as suppliers_count,
               COUNT(DISTINCT oi.id) as orders_count,
               SUM(oi.quantity) as total_sold
        FROM species s
        LEFT JOIN inventory i ON s.id = i.species_id
        LEFT JOIN order_items oi ON i.id = oi.inventory_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
        GROUP BY s.id
        ORDER BY s.name";
$species_list = $database->fetchAll($sql);

$page_title = 'Fish Species Management';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <button class="modern-sidebar-toggle d-lg-none" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-fish"></i>
                </div>
                Fish Species Management
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <button type="button" class="modern-btn modern-btn-primary modern-btn-sm" data-bs-toggle="modal"
                data-bs-target="#addSpeciesModal">
                <i class="fas fa-plus"></i> Add New Species
            </button>
        </div>
    </div>

    <!-- Species Grid -->
    <?php if (empty($species_list)): ?>
        <div class="text-center py-5">
            <i class="fas fa-fish fa-4x text-muted mb-4"></i>
            <h3 class="text-muted">No species added yet</h3>
            <p class="text-muted mb-4">Start by adding fish species that suppliers can offer.</p>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addSpeciesModal">
                <i class="fas fa-plus"></i> Add First Species
            </button>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($species_list as $species): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card species-card h-100">
                        <img src="<?php echo $species['image_url'] ?: asset_url('images/placeholder-fish.jpg'); ?>" 
                             class="card-img-top species-image" 
                             alt="<?php echo htmlspecialchars($species['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($species['name']); ?></h5>
                            <?php if ($species['scientific_name']): ?>
                                <p class="card-text text-muted small">
                                    <em><?php echo htmlspecialchars($species['scientific_name']); ?></em>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($species['category']): ?>
                                <span class="badge bg-info mb-2"><?php echo ucfirst($species['category']); ?></span>
                            <?php endif; ?>
                            
                            <span class="badge <?php echo $species['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?> mb-2">
                                <?php echo ucfirst($species['status']); ?>
                            </span>
                            
                            <?php if ($species['description']): ?>
                                <p class="card-text small">
                                    <?php echo htmlspecialchars(substr($species['description'], 0, 100)); ?>
                                    <?php if (strlen($species['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="row text-center mt-3">
                                <div class="col-4">
                                    <strong><?php echo number_format($species['suppliers_count']); ?></strong>
                                    <br><small class="text-muted">Suppliers</small>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo number_format($species['orders_count']); ?></strong>
                                    <br><small class="text-muted">Orders</small>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo number_format($species['total_sold'] ?? 0); ?></strong>
                                    <br><small class="text-muted">Sold</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-outline-primary btn-sm" 
                                        onclick="editSpecies(<?php echo htmlspecialchars(json_encode($species)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-outline-info btn-sm" 
                                        onclick="viewSpeciesDetails(<?php echo $species['id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</div>

<!-- Add Species Modal -->
<div class="modal fade" id="addSpeciesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Fish Species</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add_species">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Common Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please provide a species name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="scientific_name" class="form-label">Scientific Name</label>
                            <input type="text" class="form-control" id="scientific_name" name="scientific_name">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Select category</option>
                                <option value="freshwater">Freshwater</option>
                                <option value="saltwater">Saltwater</option>
                                <option value="brackish">Brackish Water</option>
                                <option value="ornamental">Ornamental</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="image_url" class="form-label">Image URL</label>
                            <input type="url" class="form-control" id="image_url" name="image_url">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Describe the species, its characteristics, care requirements, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Species</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Species Modal -->
<div class="modal fade" id="editSpeciesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Fish Species</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editSpeciesForm">
                <input type="hidden" name="action" value="update_species">
                <input type="hidden" name="species_id" id="edit_species_id">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Common Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_scientific_name" class="form-label">Scientific Name</label>
                            <input type="text" class="form-control" id="edit_scientific_name" name="scientific_name">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_category" name="category">
                                <option value="">Select category</option>
                                <option value="freshwater">Freshwater</option>
                                <option value="saltwater">Saltwater</option>
                                <option value="brackish">Brackish Water</option>
                                <option value="ornamental">Ornamental</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_image_url" class="form-label">Image URL</label>
                        <input type="url" class="form-control" id="edit_image_url" name="image_url">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Species</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function refreshSpecies() {
    location.reload();
}

function editSpecies(species) {
    document.getElementById('edit_species_id').value = species.id;
    document.getElementById('edit_name').value = species.name;
    document.getElementById('edit_scientific_name').value = species.scientific_name || '';
    document.getElementById('edit_description').value = species.description || '';
    document.getElementById('edit_category').value = species.category || '';
    document.getElementById('edit_image_url').value = species.image_url || '';
    document.getElementById('edit_status').value = species.status;
    
    new bootstrap.Modal(document.getElementById('editSpeciesModal')).show();
}

function viewSpeciesDetails(speciesId) {
    // This would show detailed species information
    window.location.href = `species-details.php?id=${speciesId}`;
}

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

