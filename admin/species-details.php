<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

if (!defined('SECRET_KEY')) {
    $envSecret = env('SECRET_KEY', '');
    define('SECRET_KEY', $envSecret ?: hash('sha256', APP_NAME . '_secret'));
}

if (!function_exists('generateFileToken')) {
    function generateFileToken($filepath, $ttlSeconds = 86400)
    {
        $filepath = ltrim((string)$filepath, '/');
        if ($filepath === '') {
            return '';
        }

        $expiresAt = time() + $ttlSeconds;
        $payload = $filepath . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, SECRET_KEY);

        return urlencode(base64_encode($payload . '::' . $signature));
    }
}

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

// Get supplier ID from URL
$supplier_id = $_GET['id'] ?? null;
if (!$supplier_id) {
    redirect(base_url('admin/suppliers.php'));
}

$admin = new Admin($database);
$user = new User($database);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                $sql = "UPDATE suppliers SET status = ?, updated_at = NOW() WHERE id = ?";
                $database->query($sql, [$status, $supplier_id]);
                
                // Log status change
                if ($notes) {
                    $sql = "INSERT INTO supplier_notes (supplier_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())";
                    $database->query($sql, [$supplier_id, get_user_id(), "Status changed to: $status. Notes: $notes"]);
                }
                
                $_SESSION['success'] = 'Supplier status updated successfully';
                break;
                
            case 'update_location':
                $location_data = [
                    'business_address' => $_POST['business_address'] ?? '',
                    'barangay' => $_POST['barangay'] ?? '',
                    'city' => $_POST['city'] ?? '',
                    'province' => $_POST['province'] ?? '',
                    'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
                    'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null
                ];
                
                $updates = [];
                $values = [];
                
                foreach ($location_data as $field => $value) {
                    $updates[] = "$field = ?";
                    $values[] = $value;
                }
                
                $values[] = $supplier_id;
                $sql = "UPDATE suppliers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                $database->query($sql, $values);
                
                // Log location update
                $sql = "INSERT INTO supplier_notes (supplier_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())";
                $database->query($sql, [$supplier_id, get_user_id(), "Location updated by admin"]);
                
                $_SESSION['success'] = 'Supplier location updated successfully';
                break;
                
            case 'add_note':
                $note = $_POST['note'];
                $sql = "INSERT INTO supplier_notes (supplier_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())";
                $database->query($sql, [$supplier_id, get_user_id(), $note]);
                
                $_SESSION['success'] = 'Note added successfully';
                break;
        }
        
        // Redirect to prevent form resubmission
        redirect(base_url("admin/supplier-details.php?id=$supplier_id"));
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Get supplier details including valid_id
$sql = "SELECT s.*, u.email, u.status as user_status, u.created_at as registered_at
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?";
$supplier = $database->fetch($sql, [$supplier_id]);

if (!$supplier) {
    $_SESSION['error'] = 'Supplier not found';
    redirect(base_url('admin/suppliers.php'));
}

// Get supplier inventory
$sql = "SELECT i.*, sp.name as species_name, sp.scientific_name
        FROM inventory i
        JOIN species sp ON i.species_id = sp.id
        WHERE i.supplier_id = ?
        ORDER BY sp.name";
$inventory = $database->fetchAll($sql, [$supplier_id]);

// Get supplier orders
$sql = "SELECT o.*, c.first_name, c.last_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.supplier_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10";
$recent_orders = $database->fetchAll($sql, [$supplier_id]);

// Get supplier notes
$sql = "SELECT sn.*, a.full_name as admin_name
        FROM supplier_notes sn
        LEFT JOIN admins a ON sn.admin_id = a.user_id
        WHERE sn.supplier_id = ?
        ORDER BY sn.created_at DESC
        LIMIT 10";
$notes = $database->fetchAll($sql, [$supplier_id]);

// Get supplier statistics
$sql = "SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
            (SELECT IFNULL(SUM(o2.total_amount), 0) FROM orders o2 WHERE o2.supplier_id = s.id AND o2.status = 'delivered') as total_revenue,
            COUNT(DISTINCT i.id) as total_products,
            COUNT(DISTINCT CASE WHEN i.availability_status = 'available' THEN i.id END) as active_products
        FROM suppliers s
        LEFT JOIN orders o ON s.id = o.supplier_id
        LEFT JOIN inventory i ON s.id = i.supplier_id
        WHERE s.id = ?";
$stats = $database->fetch($sql, [$supplier_id]);

// Prepare valid ID display (using valid_id column)
$permit_path = null;
$permit_filename = null;
$permit_extension = null;
$permit_token = null;
$permit_secure_url = null;
$permit_download_url = null;

if (!empty($supplier['valid_id'])) {
    $relative_path = ltrim($supplier['valid_id'], '/');
    if (strpos($relative_path, 'uploads/permits/') === 0) {
        $uploads_root = realpath(__DIR__ . '/../uploads/permits');
        $absolute_path = realpath(__DIR__ . '/../' . $relative_path);

        if (
            $uploads_root &&
            $absolute_path &&
            strncmp($absolute_path, $uploads_root, strlen($uploads_root)) === 0 &&
            is_file($absolute_path)
        ) {
            $permit_path = $relative_path;
            $permit_filename = basename($absolute_path);
            $permit_extension = strtolower(pathinfo($permit_filename, PATHINFO_EXTENSION));
            $permit_token = generateFileToken($permit_path);
            if ($permit_token) {
                $baseViewer = base_url('file-viewer.php');
                $permit_secure_url = $baseViewer . '?token=' . $permit_token;
                $permit_download_url = $permit_secure_url . '&download=1';
            }
        }
    }
}

$page_title = 'Supplier Details - ' . $supplier['business_name'];
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid px-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h1 class="h3 fw-bold text-dark mb-0"><?php echo htmlspecialchars($supplier['business_name']); ?></h1>
            <a href="suppliers.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                Back to Suppliers
            </a>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Supplier Overview -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="modern-stat-card bg-white">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Supplier Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Business Name</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($supplier['business_name']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Owner Name</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($supplier['owner_name']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Email</strong>
                                    <p class="mb-0"><a href="mailto:<?php echo $supplier['email']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($supplier['email']); ?></a></p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Contact Number</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($supplier['contact_number'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Status</strong>
                                    <span class="badge rounded-pill px-3 py-2 <?php 
                                        echo $supplier['status'] === 'approved' ? 'bg-success' : 
                                            ($supplier['status'] === 'pending' ? 'bg-warning' : 
                                            ($supplier['status'] === 'suspended' ? 'bg-danger' : 'bg-secondary')); 
                                    ?>">
                                        <?php echo ucfirst($supplier['status']); ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Registered</strong>
                                    <p class="mb-0"><?php echo date('M j, Y', strtotime($supplier['registered_at'])); ?></p>
                                </div>

                                <!-- Valid ID - Enhanced Display -->
                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Valid ID</strong>
                                    <?php if ($permit_secure_url): ?>
                                        <div class="border rounded p-3 bg-light">
                                            <?php if (in_array($permit_extension, ['jpg', 'jpeg', 'png'])): ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $permit_secure_url; ?>" target="_blank" rel="noopener">
                                                        <img src="<?php echo $permit_secure_url; ?>" alt="Valid ID" 
                                                             class="img-fluid rounded" style="max-height: 150px;">
                                                    </a>
                                                </div>
                                            <?php elseif ($permit_extension === 'pdf'): ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $permit_secure_url; ?>" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm">
                                                        View PDF
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <a href="<?php echo $permit_secure_url; ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                                        View File
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <a href="<?php echo $permit_download_url; ?>" 
                                               class="btn btn-success btn-sm">
                                                Download File
                                            </a>
                                            <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($permit_filename); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not uploaded</span>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Rating</strong>
                                    <?php if ($supplier['rating'] > 0): ?>
                                        <div class="d-flex align-items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-muted'; ?> me-1"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2 text-muted small"><?php echo number_format($supplier['rating'], 1); ?> (<?php echo $supplier['total_ratings']; ?> reviews)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No ratings yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($supplier['description']): ?>
                            <hr class="my-4">
                            <div>
                                <strong class="text-muted small text-uppercase d-block mb-1">Business Description</strong>
                                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($supplier['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="modern-stat-card bg-white h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-6">
                                <h4 class="mb-1 text-primary fw-bold"><?php echo number_format($stats['total_orders']); ?></h4>
                                <small class="text-muted">Total Orders</small>
                            </div>
                            <div class="col-6">
                                <h4 class="mb-1 text-success fw-bold"><?php echo number_format($stats['completed_orders']); ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-6">
                                <h4 class="mb-1 text-info fw-bold"><?php echo number_format($stats['active_products']); ?></h4>
                                <small class="text-muted">Active Products</small>
                            </div>
                            <div class="col-6">
                                <h4 class="mb-1 text-warning fw-bold"><?php echo format_currency($stats['total_revenue']); ?></h4>
                                <small class="text-muted">Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="modern-stat-card bg-white">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Location Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <strong class="text-muted small text-uppercase d-block mb-1">Business Address</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($supplier['business_address'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong class="text-muted small text-uppercase d-block mb-1">Barangay</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($supplier['barangay'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong class="text-muted small text-uppercase d-block mb-1">City</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($supplier['city'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong class="text-muted small text-uppercase d-block mb-1">Province</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($supplier['province'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                        <?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
                            <hr class="my-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Latitude</strong>
                                    <p class="mb-0 small"><?php echo number_format($supplier['latitude'], 6); ?></p>
                                </div>
                                <div class="col-6">
                                    <strong class="text-muted small text-uppercase d-block mb-1">Longitude</strong>
                                    <p class="mb-0 small"><?php echo number_format($supplier['longitude'], 6); ?></p>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-info mt-2" onclick="openStreetView(<?php echo $supplier['latitude']; ?>, <?php echo $supplier['longitude']; ?>)">
                                Street View
                            </button>
                        <?php else: ?>
                            <p class="text-muted mt-3 mb-0">GPS coordinates not set</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
                    <div class="modern-stat-card bg-white h-100">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0 fw-semibold text-dark">Location Map</h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="supplierLocationMap" style="height: 280px; border-radius: 0 0 16px 16px;"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="modern-stat-card bg-white text-center py-5 h-100 d-flex align-items-center justify-content-center">
                        <div>
                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted mb-2">No Location Set</h5>
                            <p class="text-muted mb-3">This supplier hasn't set their business location yet.</p>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editLocationModal">
                                Set Location
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="modern-stat-card bg-white h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-center text-muted py-4 mb-0">No orders yet</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th>#</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $o): ?>
                                        <tr>
                                            <td class="small">#<?php echo $o['order_number']; ?></td>
                                            <td class="small"><?php echo htmlspecialchars($o['first_name'] . ' ' . $o['last_name']); ?></td>
                                            <td class="small"><?php echo format_currency($o['total_amount']); ?></td>
                                            <td>
                                                <span class="badge rounded-pill px-2 py-1 small <?php echo $o['status'] === 'delivered' ? 'bg-success' : ($o['status'] === 'pending' ? 'bg-warning' : 'bg-info'); ?>">
                                                    <?php echo ucfirst($o['status']); ?>
                                                </span>
                                            </td>
                                            <td class="small"><?php echo date('M j', strtotime($o['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let supplierLocationMap;
let editLocationMap;
let editMarker;

// Init read-only map
function initSupplierLocationMap() {
    <?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
        const lat = <?php echo $supplier['latitude']; ?>;
        const lng = <?php echo $supplier['longitude']; ?>;

        supplierLocationMap = L.map('supplierLocationMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(supplierLocationMap);
        L.marker([lat, lng]).addTo(supplierLocationMap)
            .bindPopup('<?php echo addslashes($supplier['business_name']); ?>')
            .openPopup();
    <?php endif; ?>
}

// Init edit map
function initEditLocationMap() {
    const defaultLat = <?php echo !empty($supplier['latitude']) ? $supplier['latitude'] : '14.5995'; ?>;
    const defaultLng = <?php echo !empty($supplier['longitude']) ? $supplier['longitude'] : '120.9842'; ?>;

    editLocationMap = L.map('editLocationMap').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editLocationMap);

    <?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
        editMarker = L.marker([defaultLat, defaultLng]).addTo(editLocationMap).bindPopup('Current Location').openPopup();
    <?php endif; ?>

    editLocationMap.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        if (editMarker) editLocationMap.removeLayer(editMarker);
        editMarker = L.marker([lat, lng]).addTo(editLocationMap)
            .bindPopup(`Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('lat_display').value = lat.toFixed(6);
        document.getElementById('lng_display').value = lng.toFixed(6);
        reverseGeocode(lat, lng);
    });
}

// GPS
function getCurrentLocation() {
    if (!navigator.geolocation) return showAlert('Geolocation not supported', 'warning');
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.innerHTML = 'Getting...';
    btn.disabled = true;
    navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        editLocationMap.setView([lat, lng], 15);
        if (editMarker) editLocationMap.removeLayer(editMarker);
        editMarker = L.marker([lat, lng]).addTo(editLocationMap).bindPopup('Your Location').openPopup();
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('lat_display').value = lat.toFixed(6);
        document.getElementById('lng_display').value = lng.toFixed(6);
        reverseGeocode(lat, lng);
        btn.innerHTML = orig;
        btn.disabled = false;
    }, err => {
        showAlert('Location access denied', 'warning');
        btn.innerHTML = orig;
        btn.disabled = false;
    });
}

// Search
function searchLocation() {
    const modal = new bootstrap.Modal('#searchModal');
    modal.show();
}

function performSearch() {
    const query = document.getElementById('searchInput').value.trim();
    if (!query) return showAlert('Enter a location', 'warning');
    const btn = event.target;
    const orig = btn.innerHTML;
    btn.innerHTML = 'Searching...';
    btn.disabled = true;
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=ph`)
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) return showAlert('No results', 'warning');
            if (data.length > 1) displaySearchResults(data);
            else selectSearchResult(data[0]);
        })
        .finally(() => {
            btn.innerHTML = orig;
            btn.disabled = false;
        });
}

function displaySearchResults(results) {
    const div = document.getElementById('searchResults');
    div.innerHTML = '<h6 class="mt-3">Results:</h6>';
    results.forEach(r => {
        const item = document.createElement('div');
        item.className = 'p-2 border rounded mb-2 cursor-pointer';
        item.innerHTML = `<strong>${r.display_name}</strong>`;
        item.onclick = () => selectSearchResult(r);
        div.appendChild(item);
    });
}

function selectSearchResult(r) {
    const lat = parseFloat(r.lat);
    const lng = parseFloat(r.lon);
    editLocationMap.setView([lat, lng], 16);
    if (editMarker) editLocationMap.removeLayer(editMarker);
    editMarker = L.marker([lat, lng]).addTo(editLocationMap).bindPopup(r.display_name).openPopup();
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('lat_display').value = lat.toFixed(6);
    document.getElementById('lng_display').value = lng.toFixed(6);
    reverseGeocode(lat, lng);
    bootstrap.Modal.getInstance('#searchModal').hide();
}

// Reverse geocode
function reverseGeocode(lat, lng) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
        .then(r => r.json())
        .then(d => {
            if (d.address) {
                const a = d.address;
                if (!document.getElementById('business_address').value) document.getElementById('business_address').value = a.road || a.neighbourhood || '';
                if (!document.getElementById('barangay').value) document.getElementById('barangay').value = a.suburb || a.village || '';
                if (!document.getElementById('city').value) document.getElementById('city').value = a.city || a.town || '';
                if (!document.getElementById('province').value) document.getElementById('province').value = a.state || '';
            }
        });
}

// Street View
function openStreetView(lat, lng) {
    window.open(`https://www.google.com/maps/@${lat},${lng},3a,75y,90t/data=!3m6!1e1!3m4!1s0:0!2e0!7i13312!8i6656`, '_blank');
}

// Alert
function showAlert(msg, type = 'info') {
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    div.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}

// Init
document.addEventListener('DOMContentLoaded', initSupplierLocationMap);
document.getElementById('editLocationModal')?.addEventListener('shown.bs.modal', () => setTimeout(initEditLocationMap, 100));
</script>

<style>
/* Minimal borders */
.modern-stat-card {
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    overflow: hidden;
}
.modern-stat-card .card-header {
    background: #fff;
}

/* Clean spacing */
.row.g-4 { --bs-gutter-x: 1.5rem; --bs-gutter-y: 1.5rem; }

/* Table */
.table th { font-weight: 600; color: #4b5563; }
.table td { vertical-align: middle; }

/* Map */
#supplierLocationMap, #editLocationMap { border-radius: 16px; }

/* Modal */
.modal-content { border-radius: 16px; }
</style>

</body>
</html>