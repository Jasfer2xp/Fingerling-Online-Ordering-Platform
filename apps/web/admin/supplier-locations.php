<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);

// Get supplier locations
$locations = $admin->getSupplierLocations();

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
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleMapView()">
                            <i class="fas fa-map"></i> <span id="mapToggleText">Show Map</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Location Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count($locations); ?></h4>
                                    <p class="mb-0">Total Suppliers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-store fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_unique(array_column($locations, 'province'))); ?></h4>
                                    <p class="mb-0">Provinces</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-map-marked-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_unique(array_column($locations, 'city'))); ?></h4>
                                    <p class="mb-0">Cities</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-city fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo count(array_filter($locations, fn($l) => !empty($l['latitude']) && !empty($l['longitude']))); ?></h4>
                                    <p class="mb-0">With Coordinates</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-map-marker-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Container -->
            <div class="card mb-4" id="mapContainer" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">Supplier Locations Map</h5>
                </div>
                <div class="card-body">
                    <div id="suppliersMap" style="height: 500px; width: 100%;"></div>
                </div>
            </div>

            <!-- Locations Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Supplier Locations List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="locationsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Supplier</th>
                                    <th>Address</th>
                                    <th>Location</th>
                                    <th>Coordinates</th>
                                    <th>Products</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $location): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <i class="fas fa-store text-white"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($location['business_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($location['owner_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($location['business_address']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($location['barangay'] . ', ' . $location['city'] . ', ' . $location['province']); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($location['latitude']) && !empty($location['longitude'])): ?>
                                                <small class="text-muted">
                                                    <?php echo number_format($location['latitude'], 6); ?>,<br>
                                                    <?php echo number_format($location['longitude'], 6); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $location['product_count'] ?? 0; ?> products
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $location['status'] === 'approved' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($location['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewSupplier(<?php echo $location['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (!empty($location['latitude']) && !empty($location['longitude'])): ?>
                                                    <button class="btn btn-outline-info" onclick="showOnMap(<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>)" title="Show on Map">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-secondary" onclick="editLocation(<?php echo $location['id']; ?>)" title="Edit Location">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="archiveSupplier(<?php echo $location['id']; ?>)" title="Archive Supplier">
                                                    <i class="fas fa-archive"></i>
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
    </main>

<script>
let suppliersMap;
let mapVisible = false;

function toggleMapView() {
    const mapContainer = document.getElementById('mapContainer');
    const toggleText = document.getElementById('mapToggleText');

    if (mapVisible) {
        mapContainer.style.display = 'none';
        toggleText.textContent = 'Show Map';
        mapVisible = false;
    } else {
        mapContainer.style.display = 'block';
        toggleText.textContent = 'Hide Map';
        mapVisible = true;

        if (!suppliersMap) {
            initializeMap();
        }
    }
}

function initializeMap() {
    // Initialize Leaflet map centered on Philippines
    suppliersMap = L.map('suppliersMap').setView([12.8797, 121.7740], 6);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(suppliersMap);

    // Add markers for suppliers with coordinates
    const suppliers = <?php echo json_encode(array_filter($locations, fn($l) => !empty($l['latitude']) && !empty($l['longitude']))); ?>;
    
    suppliers.forEach(supplier => {
        const lat = parseFloat(supplier.latitude);
        const lng = parseFloat(supplier.longitude);

        const popupContent = `
            <div class="supplier-popup">
                <h6 class="text-primary mb-2">${supplier.business_name}</h6>
                <div class="mb-2">
                    <i class="fas fa-user text-muted me-1"></i>
                    <strong>Owner:</strong> ${supplier.owner_name}
                </div>
                <div class="mb-2">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                    <strong>Barangay:</strong> <span class="badge bg-info">${supplier.barangay}</span>
                </div>
                <div class="mb-2">
                    <i class="fas fa-city text-muted me-1"></i>
                    <strong>City:</strong> ${supplier.city}, ${supplier.province}
                </div>
                <div class="mb-2">
                    <i class="fas fa-home text-muted me-1"></i>
                    <strong>Address:</strong> ${supplier.business_address}
                </div>
                <div class="mb-3">
                    <i class="fas fa-fish text-success me-1"></i>
                    <strong>Products:</strong> <span class="badge bg-success">${supplier.product_count || 0} items</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-primary" onclick="viewSupplier(${supplier.id})">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editLocation(${supplier.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
            </div>
        `;

        L.marker([lat, lng])
            .addTo(suppliersMap)
            .bindPopup(popupContent)
            .bindTooltip(supplier.business_name);
    });

    // Fit map to show all markers if any exist
    if (suppliers.length > 0) {
        const group = new L.featureGroup(Object.values(suppliersMap._layers).filter(layer => layer instanceof L.Marker));
        if (group.getLayers().length > 0) {
            suppliersMap.fitBounds(group.getBounds().pad(0.1));
        }
    }
}

function showOnMap(lat, lng) {
    if (!mapVisible) {
        toggleMapView();
    }
    
    setTimeout(() => {
        if (suppliersMap) {
            suppliersMap.setView([lat, lng], 15);
        }
    }, 500);
}

function viewSupplier(supplierId) {
    window.location.href = `supplier-details.php?id=${supplierId}`;
}

function editLocation(supplierId) {
    window.location.href = `edit-supplier-location.php?id=${supplierId}`;
}
</script>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.supplier-popup {
    min-width: 200px;
}

.supplier-popup h6 {
    color: #0d6efd;
    margin-bottom: 8px;
}
</style>

