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

// Handle location updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_location':
                $supplier_id = intval($_POST['supplier_id']);
                $latitude = floatval($_POST['latitude']);
                $longitude = floatval($_POST['longitude']);
                
                $supplier = new Supplier($database, $supplier_id);
                $supplier->updateProfile([
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
                
                $_SESSION['success'] = 'Supplier location updated successfully';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect(base_url('admin/locations.php'));
}

// Get all approved suppliers with locations
$sql = "SELECT s.*, u.email FROM suppliers s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.status = 'approved' 
        ORDER BY s.business_name";
$suppliers = $database->fetchAll($sql);

$page_title = 'Supplier Locations';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content admin-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Supplier Locations</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshMap()">
                            <i class="fas fa-sync"></i> Refresh Map
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="centerMap()">
                            <i class="fas fa-crosshairs"></i> Center Map
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSatellite()">
                            <i class="fas fa-satellite"></i> Toggle View
                        </button>
                    </div>
                </div>
            </div>

            <!-- Map Controls -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">Map Legend</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <span><i class="fas fa-map-marker-alt text-success"></i> Active Suppliers</span>
                                <span><i class="fas fa-map-marker-alt text-warning"></i> No Location Set</span>
                                <span><i class="fas fa-map-marker-alt text-danger"></i> Suspended</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">Quick Stats</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <strong><?php echo count(array_filter($suppliers, fn($s) => $s['latitude'] && $s['longitude'])); ?></strong>
                                    <br><small class="text-muted">Mapped</small>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo count(array_filter($suppliers, fn($s) => !$s['latitude'] || !$s['longitude'])); ?></strong>
                                    <br><small class="text-muted">No Location</small>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo count($suppliers); ?></strong>
                                    <br><small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive Map -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-map"></i> Supplier Locations Map
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="suppliersMap" style="height: 600px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suppliers Without Location -->
            <?php 
            $suppliers_without_location = array_filter($suppliers, function($s) {
                return !$s['latitude'] || !$s['longitude'];
            });
            ?>
            
            <?php if (!empty($suppliers_without_location)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle text-warning"></i> 
                                    Suppliers Without Location (<?php echo count($suppliers_without_location); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Business Name</th>
                                                <th>Owner</th>
                                                <th>Address</th>
                                                <th>Contact</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers_without_location as $supplier): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($supplier['business_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($supplier['owner_name']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($supplier['business_address']); ?><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($supplier['barangay'] . ', ' . $supplier['city'] . ', ' . $supplier['province']); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($supplier['supplier_contact']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="setSupplierLocation(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['business_name']); ?>')">
                                                            <i class="fas fa-map-marker-alt"></i> Set Location
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
    </main>

<!-- Set Location Modal -->
<div class="modal fade" id="setLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setLocationModalTitle">Set Supplier Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="setLocationForm">
                <input type="hidden" name="action" value="update_location">
                <input type="hidden" name="supplier_id" id="location_supplier_id">
                <input type="hidden" name="latitude" id="location_latitude">
                <input type="hidden" name="longitude" id="location_longitude">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Click on the map to set the supplier's location:</label>
                        <div id="setLocationMap" style="height: 400px;"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="display_latitude" class="form-label">Latitude</label>
                            <input type="text" class="form-control" id="display_latitude" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="display_longitude" class="form-label">Longitude</label>
                            <input type="text" class="form-control" id="display_longitude" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveLocationBtn" disabled>
                        <i class="fas fa-save"></i> Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let mainMap;
let setLocationMap;
let currentMarker;

// Initialize main map
document.addEventListener('DOMContentLoaded', function() {
    initializeMainMap();
});

function initializeMainMap() {
    // Center on Philippines
    mainMap = AdminApp.initializeLeafletMap('suppliersMap', [12.8797, 121.7740], 6);
    
    // Add suppliers to map
    const suppliers = <?php echo json_encode($suppliers); ?>;
    
    suppliers.forEach(supplier => {
        if (supplier.latitude && supplier.longitude) {
            const lat = parseFloat(supplier.latitude);
            const lng = parseFloat(supplier.longitude);
            
            // Determine marker color based on status
            let markerColor = 'green';
            const status = supplier.supplier_status || supplier.status || 'approved';
            if (status === 'suspended') markerColor = 'red';
            else if (status === 'pending') markerColor = 'orange';

            const popupContent = `
                <div class="supplier-popup">
                    <h6>${supplier.business_name || ''}</h6>
                    <p class="mb-1"><strong>Owner:</strong> ${supplier.owner_name || ''}</p>
                    <p class="mb-1"><strong>Contact:</strong> ${supplier.supplier_contact || ''}</p>
                    <p class="mb-1"><strong>Location:</strong> ${supplier.barangay || ''}, ${supplier.city || ''}</p>
                    <p class="mb-2"><strong>Status:</strong>
                        <span class="badge bg-${status === 'approved' ? 'success' : (status === 'pending' ? 'warning' : 'danger')}">
                            ${status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown'}
                        </span>
                    </p>
                    <div class="d-flex gap-1">
                        <a href="supplier-details.php?id=${supplier.id}" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editLocation(${supplier.id}, ${lat}, ${lng}, '${supplier.business_name}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                </div>
            `;
            
            AdminApp.addLeafletMarker(mainMap, [lat, lng], supplier.business_name, popupContent);
        }
    });
}

function refreshMap() {
    location.reload();
}

function centerMap() {
    mainMap.setView([12.8797, 121.7740], 6);
}

function toggleSatellite() {
    // This would toggle between street and satellite view
    // For now, we'll just show an alert
    alert('Satellite view toggle - to be implemented with satellite tile layer');
}

function setSupplierLocation(supplierId, supplierName) {
    document.getElementById('location_supplier_id').value = supplierId;
    document.getElementById('setLocationModalTitle').textContent = `Set Location - ${supplierName}`;
    
    const modal = new bootstrap.Modal(document.getElementById('setLocationModal'));
    modal.show();
    
    // Initialize location setting map
    setTimeout(() => {
        setLocationMap = AdminApp.initializeLeafletMap('setLocationMap', [14.5995, 120.9842], 10);
        
        // Add click handler
        setLocationMap.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            
            // Remove existing marker
            if (currentMarker) {
                setLocationMap.removeLayer(currentMarker);
            }
            
            // Add new marker
            currentMarker = AdminApp.addLeafletMarker(setLocationMap, [lat, lng], supplierName, 
                `<strong>${supplierName}</strong><br>New Location`);
            
            // Update form fields
            document.getElementById('location_latitude').value = lat;
            document.getElementById('location_longitude').value = lng;
            document.getElementById('display_latitude').value = lat.toFixed(6);
            document.getElementById('display_longitude').value = lng.toFixed(6);
            
            // Enable save button
            document.getElementById('saveLocationBtn').disabled = false;
        });
    }, 300);
}

function editLocation(supplierId, currentLat, currentLng, supplierName) {
    setSupplierLocation(supplierId, supplierName);
    
    // Pre-set current location
    setTimeout(() => {
        if (setLocationMap) {
            setLocationMap.setView([currentLat, currentLng], 15);
            
            currentMarker = AdminApp.addLeafletMarker(setLocationMap, [currentLat, currentLng], supplierName, 
                `<strong>${supplierName}</strong><br>Current Location`);
            
            document.getElementById('location_latitude').value = currentLat;
            document.getElementById('location_longitude').value = currentLng;
            document.getElementById('display_latitude').value = currentLat.toFixed(6);
            document.getElementById('display_longitude').value = currentLng.toFixed(6);
            document.getElementById('saveLocationBtn').disabled = false;
        }
    }, 500);
}
</script>

