<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$admin = new Admin($database);
$supplier_id = intval($_GET['id'] ?? 0);

if (!$supplier_id) {
    redirect(base_url('admin/supplier-locations.php'));
}

// Get supplier details
$supplier = $admin->getSupplierById($supplier_id);
if (!$supplier) {
    redirect(base_url('admin/supplier-locations.php'));
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        $business_address = trim($_POST['business_address'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        
        // Update supplier location
        $sql = "UPDATE suppliers SET 
                latitude = ?, 
                longitude = ?, 
                business_address = ?, 
                barangay = ?, 
                city = ?, 
                province = ? 
                WHERE id = ?";
        
        if ($database->query($sql, [$latitude, $longitude, $business_address, $barangay, $city, $province, $supplier_id])) {
            $message = "Supplier location updated successfully!";
            // Refresh supplier data
            $supplier = $admin->getSupplierById($supplier_id);
        } else {
            $error = "Failed to update supplier location.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$page_title = 'Edit Supplier Location';
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
                    <i class="fas fa-edit"></i>
                </div>
                Edit Supplier Location
            </h1>
        </div>
        <div class="modern-dashboard-actions">
            <a href="supplier-locations.php" class="modern-btn modern-btn-secondary modern-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Locations
            </a>
        </div>
    </div>

    <div class="container-fluid px-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($supplier['business_name']); ?> - Location Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="business_address" class="form-label">Business Address</label>
                                        <textarea class="form-control" id="business_address" name="business_address" rows="3" required><?php echo htmlspecialchars($supplier['business_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="barangay" class="form-label">Barangay</label>
                                        <input type="text" class="form-control" id="barangay" name="barangay" value="<?php echo htmlspecialchars($supplier['barangay'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($supplier['city'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="province" class="form-label">Province</label>
                                        <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($supplier['province'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="latitude" class="form-label">Latitude</label>
                                        <input type="number" step="any" class="form-control" id="latitude" name="latitude" value="<?php echo $supplier['latitude'] ?? ''; ?>" placeholder="e.g., 14.5995">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="longitude" class="form-label">Longitude</label>
                                        <input type="number" step="any" class="form-control" id="longitude" name="longitude" value="<?php echo $supplier['longitude'] ?? ''; ?>" placeholder="e.g., 120.9842">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-info" onclick="getCurrentLocation()">
                                    <i class="fas fa-crosshairs"></i> Get Current Location
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="geocodeAddress()">
                                    <i class="fas fa-search"></i> Geocode Address
                                </button>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Location
                                </button>
                                <a href="supplier-locations.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Supplier Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Business Name:</strong><br>
                            <?php echo htmlspecialchars($supplier['business_name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Owner:</strong><br>
                            <?php echo htmlspecialchars($supplier['owner_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($supplier['email']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Phone:</strong><br>
                            <?php echo htmlspecialchars($supplier['phone'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?php echo $supplier['status'] === 'approved' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($supplier['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Map Preview -->
                <?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-map"></i> Location Preview
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="locationMap" style="height: 200px;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
let locationMap;

// Initialize map if coordinates exist
<?php if (!empty($supplier['latitude']) && !empty($supplier['longitude'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    locationMap = L.map('locationMap').setView([<?php echo $supplier['latitude']; ?>, <?php echo $supplier['longitude']; ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(locationMap);
    
    L.marker([<?php echo $supplier['latitude']; ?>, <?php echo $supplier['longitude']; ?>])
        .addTo(locationMap)
        .bindPopup('<?php echo htmlspecialchars($supplier['business_name']); ?>');
});
<?php endif; ?>

function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            alert('Location coordinates updated!');
        }, function(error) {
            alert('Error getting location: ' + error.message);
        });
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}

function geocodeAddress() {
    const address = document.getElementById('business_address').value;
    const city = document.getElementById('city').value;
    const province = document.getElementById('province').value;
    
    if (!address || !city || !province) {
        alert('Please fill in the address, city, and province fields first.');
        return;
    }
    
    const fullAddress = `${address}, ${city}, ${province}, Philippines`;
    
    // Using a simple geocoding service (you might want to use a more robust solution)
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullAddress)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                document.getElementById('latitude').value = data[0].lat;
                document.getElementById('longitude').value = data[0].lon;
                alert('Coordinates found and updated!');
            } else {
                alert('Could not find coordinates for this address.');
            }
        })
        .catch(error => {
            alert('Error geocoding address: ' + error.message);
        });
}
</script>
