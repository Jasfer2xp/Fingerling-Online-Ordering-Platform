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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_location') {
        $update_data = [
            'business_address' => $_POST['business_address'] ?? '',
            'purok' => $_POST['purok'] ?? '',
            'barangay' => $_POST['barangay'] ?? '',
            'city' => $_POST['city'] ?? '',
            'province' => $_POST['province'] ?? '',
            'latitude' => $_POST['latitude'] ?? null,
            'longitude' => $_POST['longitude'] ?? null
        ];
        
        if ($supplier->updateLocation($update_data)) {
            $success_message = "Location updated successfully!";
            $profile = $user->getUserProfile($user_id); // Refresh profile data
        } else {
            $error_message = "Failed to update location. Please try again.";
        }
    }
}

$page_title = 'Location & Map';
include '../includes/supplier_header.php';
include '../includes/supplier_sidebar.php';
?>

    <!-- Main Content -->
    <main class="role-main-content supplier-main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-map-marker-alt"></i> Location & Map
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" onclick="getCurrentLocation()">
                            <i class="fas fa-crosshairs"></i> Use Current Location
                        </button>
                        <button class="btn btn-outline-secondary" onclick="searchLocation()">
                            <i class="fas fa-search"></i> Search Location
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Location Form -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-edit"></i> Update Location
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="locationForm">
                                <input type="hidden" name="action" value="update_location">
                                <input type="hidden" name="latitude" id="latitude" value="<?php echo $profile['latitude'] ?? ''; ?>">
                                <input type="hidden" name="longitude" id="longitude" value="<?php echo $profile['longitude'] ?? ''; ?>">
                                
                                <div class="mb-3">
                                    <label for="business_address" class="form-label">Business Address *</label>
                                    <input type="text" class="form-control" id="business_address" name="business_address"
                                           value="<?php echo htmlspecialchars($profile['business_address'] ?? ''); ?>" required>
                                    <div class="form-text">Street address, building number, etc.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="purok" class="form-label">Purok/Street</label>
                                    <input type="text" class="form-control" id="purok" name="purok"
                                           value="<?php echo htmlspecialchars($profile['purok'] ?? ''); ?>">
                                    <div class="form-text">Specific street or purok within the barangay</div>
                                </div>

                                <div class="mb-3">
                                    <label for="barangay" class="form-label">Barangay *</label>
                                    <input type="text" class="form-control" id="barangay" name="barangay"
                                           value="<?php echo htmlspecialchars($profile['barangay'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City *</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="province" class="form-label">Province *</label>
                                        <input type="text" class="form-control" id="province" name="province" 
                                               value="<?php echo htmlspecialchars($profile['province'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="lat_display" class="form-label">Latitude</label>
                                        <input type="text" class="form-control" id="lat_display" readonly 
                                               value="<?php echo $profile['latitude'] ?? 'Not set'; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lng_display" class="form-label">Longitude</label>
                                        <input type="text" class="form-control" id="lng_display" readonly 
                                               value="<?php echo $profile['longitude'] ?? 'Not set'; ?>">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save Location
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Location Tips -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb"></i> Location Tips
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success"></i> Accurate location helps customers find you</li>
                                <li><i class="fas fa-check text-success"></i> Use GPS coordinates for precise mapping</li>
                                <li><i class="fas fa-check text-success"></i> Include landmarks in your address</li>
                                <li><i class="fas fa-check text-success"></i> Update location if you move your business</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Map Display -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-map"></i> Business Location Map
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="map" style="height: 400px; width: 100%;"></div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Click on the map to set your exact location
                            </small>
                        </div>
                    </div>
                    
                    <!-- Current Location Info -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle"></i> Current Location
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Address:</strong><br>
                                <?php echo htmlspecialchars($profile['business_address'] ?? 'Not set'); ?>
                                <?php if (!empty($profile['barangay'])): ?>
                                    <br><?php echo htmlspecialchars($profile['barangay']); ?>
                                <?php endif; ?>
                                <br><?php echo htmlspecialchars($profile['city'] ?? ''); ?>, <?php echo htmlspecialchars($profile['province'] ?? ''); ?>
                            </p>
                            
                            <?php if (!empty($profile['latitude']) && !empty($profile['longitude'])): ?>
                                <p class="mb-0">
                                    <strong>Coordinates:</strong><br>
                                    <?php echo number_format($profile['latitude'], 6); ?>, <?php echo number_format($profile['longitude'], 6); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    GPS coordinates not set. Click on the map to set your location.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<!-- Location Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search"></i> Search Location
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="searchInput" class="form-label">Search for a location</label>
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Enter address, city, or landmark...">
                </div>
                <div id="searchResults"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="performSearch()">Search</button>
            </div>
        </div>
    </div>
</div>

<!-- Include Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<script>
let map;
let marker;

// Initialize map
function initMap() {
    const defaultLat = <?php echo $profile['latitude'] ?? '14.5995'; ?>;
    const defaultLng = <?php echo $profile['longitude'] ?? '120.9842'; ?>;
    
    map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    // Add multiple tile layers for better functionality
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
        minZoom: 3
    });

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri, Maxar, GeoEye, Earthstar Geographics, CNES/Airbus DS, USDA, USGS, AeroGRID, IGN, and the GIS User Community',
        maxZoom: 19,
        minZoom: 3
    });

    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors, Tiles style by Humanitarian OpenStreetMap Team',
        maxZoom: 19,
        minZoom: 3
    });

    // Add default layer
    osmLayer.addTo(map);

    // Add layer control
    const baseLayers = {
        "Street Map": osmLayer,
        "Satellite": satelliteLayer,
        "Detailed Streets": streetLayer
    };

    L.control.layers(baseLayers).addTo(map);
    
    // Add marker if coordinates exist
    if (<?php echo !empty($profile['latitude']) && !empty($profile['longitude']) ? 'true' : 'false'; ?>) {
        marker = L.marker([defaultLat, defaultLng]).addTo(map)
            .bindPopup('<?php echo htmlspecialchars($profile['business_name'] ?? 'Your Business'); ?>')
            .openPopup();
    }
    
    // Add click event to map
    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        console.log('Map clicked at:', lat, lng);

        // Remove existing marker
        if (marker) {
            map.removeLayer(marker);
        }

        // Add new marker with enhanced popup
        marker = L.marker([lat, lng], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(map)
            .bindPopup(`
                <div class="text-center">
                    <strong><i class="fas fa-map-marker-alt text-primary"></i> Selected Location</strong><br>
                    <small>Latitude: ${lat.toFixed(6)}<br>Longitude: ${lng.toFixed(6)}</small><br>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-sm btn-success" onclick="openStreetView(${lat}, ${lng})">
                            <i class="fas fa-street-view"></i> Street View
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="reverseGeocode(${lat}, ${lng})">
                            <i class="fas fa-search"></i> Get Address
                        </button>
                    </div>
                </div>
            `)
            .openPopup();

        // Update form fields
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('lat_display').value = lat.toFixed(6);
        document.getElementById('lng_display').value = lng.toFixed(6);

        showAlert('Location selected! You can view street view or get the address.', 'info');
    });
}

function getCurrentLocation() {
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by this browser.', 'warning');
        return;
    }

    // Show loading state
    const btn = document.querySelector('button[onclick="getCurrentLocation()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting Location...';
    btn.disabled = true;

    console.log('Requesting current location...');

    navigator.geolocation.getCurrentPosition(
        function(position) {
            console.log('Location obtained:', position.coords);
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            try {
                // Update map
                map.setView([lat, lng], 15);

                // Remove existing marker
                if (marker) {
                    map.removeLayer(marker);
                }

                // Add new marker with enhanced popup
                marker = L.marker([lat, lng], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(map)
                    .bindPopup(`
                        <div class="text-center">
                            <strong><i class="fas fa-map-marker-alt text-success"></i> Current Location</strong><br>
                            <small>Latitude: ${lat.toFixed(6)}<br>Longitude: ${lng.toFixed(6)}</small><br>
                            <button class="btn btn-sm btn-success mt-2" onclick="openStreetView(${lat}, ${lng})">
                                <i class="fas fa-street-view"></i> Street View
                            </button>
                        </div>
                    `)
                    .openPopup();

                // Update form fields
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('lat_display').value = lat.toFixed(6);
                document.getElementById('lng_display').value = lng.toFixed(6);

                showAlert('Current location set successfully! Click the marker to view street view.', 'success');

            } catch (mapError) {
                console.error('Error updating map:', mapError);
                showAlert('Error updating map with your location. Please try again.', 'danger');
            }

            // Reset button state
            btn.innerHTML = originalText;
            btn.disabled = false;
        },
        function(error) {
            console.error('Geolocation error:', error);
            let errorMessage = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Please allow location access in your browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information is unavailable. Please check your GPS/location services.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Location request timed out. Please try again.';
                    break;
                default:
                    errorMessage += 'An unknown error occurred.';
                    break;
            }

            showAlert(errorMessage, 'warning');

            // Reset button state
            btn.innerHTML = originalText;
            btn.disabled = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5 minutes
        }
    );
}

function searchLocation() {
    const modal = new bootstrap.Modal(document.getElementById('searchModal'));
    modal.show();
}

function performSearch() {
    const query = document.getElementById('searchInput').value.trim();
    if (!query) {
        showAlert('Please enter a location to search', 'warning');
        return;
    }

    // Show loading state
    const searchBtn = document.querySelector('button[onclick="performSearch()"]');
    const originalText = searchBtn.innerHTML;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    searchBtn.disabled = true;

    console.log('Searching for:', query);

    // Enhanced geocoding using Nominatim (OpenStreetMap)
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1&countrycodes=ph`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                // Show multiple results if available
                if (data.length > 1) {
                    displaySearchResults(data);
                } else {
                    selectSearchResult(data[0]);
                }
            } else {
                showAlert('Location not found. Please try a different search term or be more specific.', 'warning');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            showAlert('Error searching for location. Please check your internet connection and try again.', 'danger');
        })
        .finally(() => {
            // Reset button state
            searchBtn.innerHTML = originalText;
            searchBtn.disabled = false;
        });
}

function displaySearchResults(results) {
    const resultsDiv = document.getElementById('searchResults');
    resultsDiv.innerHTML = '<h6>Search Results:</h6>';

    results.forEach((result, index) => {
        const resultDiv = document.createElement('div');
        resultDiv.className = 'border rounded p-2 mb-2 search-result-item';
        resultDiv.style.cursor = 'pointer';
        resultDiv.innerHTML = `
            <strong>${result.display_name}</strong><br>
            <small class="text-muted">Type: ${result.type || 'Location'}</small>
        `;

        resultDiv.addEventListener('click', () => {
            selectSearchResult(result);
        });

        resultsDiv.appendChild(resultDiv);
    });
}

function selectSearchResult(result) {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    console.log('Selected location:', result.display_name, lat, lng);

    // Update map
    map.setView([lat, lng], 16);

    // Remove existing marker
    if (marker) {
        map.removeLayer(marker);
    }

    // Add new marker with enhanced popup
    marker = L.marker([lat, lng], {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map)
        .bindPopup(`
            <div class="text-center">
                <strong><i class="fas fa-search text-warning"></i> Found Location</strong><br>
                <small>${result.display_name}</small><br>
                <small>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</small><br>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-sm btn-success" onclick="openStreetView(${lat}, ${lng})">
                        <i class="fas fa-street-view"></i> Street View
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="reverseGeocode(${lat}, ${lng})">
                        <i class="fas fa-search"></i> Get Address
                    </button>
                </div>
            </div>
        `)
        .openPopup();

    // Update form fields
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('lat_display').value = lat.toFixed(6);
    document.getElementById('lng_display').value = lng.toFixed(6);

    // Auto-fill address if available
    if (result.address) {
        reverseGeocode(lat, lng);
    }

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('searchModal')).hide();

    showAlert('Location found and set successfully! You can now view street view or save the location.', 'success');
}

function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        location.reload();
    }
}

// Show alert function
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Open street view
function openStreetView(lat, lng) {
    const streetViewUrl = `https://www.google.com/maps/@${lat},${lng},3a,75y,90t/data=!3m6!1e1!3m4!1s0x0:0x0!2e0!7i13312!8i6656`;
    window.open(streetViewUrl, '_blank');
    showAlert('Opening street view in new tab...', 'info');
}

// Reverse geocoding to get address from coordinates
function reverseGeocode(lat, lng) {
    showAlert('Getting address information...', 'info');

    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            if (data && data.address) {
                const address = data.address;

                // Auto-fill form fields if available
                if (address.house_number && address.road) {
                    document.getElementById('business_address').value = `${address.house_number} ${address.road}`;
                } else if (address.road) {
                    document.getElementById('business_address').value = address.road;
                }

                if (address.suburb || address.village || address.neighbourhood) {
                    document.getElementById('barangay').value = address.suburb || address.village || address.neighbourhood;
                }

                if (address.city || address.town || address.municipality) {
                    document.getElementById('city').value = address.city || address.town || address.municipality;
                }

                if (address.state || address.province) {
                    document.getElementById('province').value = address.state || address.province;
                }

                showAlert('Address information filled automatically! Please review and adjust as needed.', 'success');
            } else {
                showAlert('Could not get address information for this location.', 'warning');
            }
        })
        .catch(error => {
            console.error('Reverse geocoding error:', error);
            showAlert('Error getting address information. Please fill manually.', 'warning');
        });
}

// Auto-geocode when address fields change
function autoGeocodeAddress() {
    const address = document.getElementById('business_address').value.trim();
    const barangay = document.getElementById('barangay').value.trim();
    const city = document.getElementById('city').value.trim();
    const province = document.getElementById('province').value.trim();

    // Build full address
    const fullAddress = [address, barangay, city, province].filter(part => part.length > 0).join(', ');

    if (fullAddress.length < 8) return; // Too short to geocode

    console.log('Auto-geocoding address:', fullAddress);

    // Show loading indicator
    showAlert('🔍 Auto-locating address on map...', 'info', 2000);

    // Debounce the geocoding request
    clearTimeout(window.geocodeTimeout);
    window.geocodeTimeout = setTimeout(() => {
        geocodeAddress(fullAddress);
    }, 1500); // Wait 1.5 seconds after user stops typing
}

// Geocode address and update map
function geocodeAddress(address) {
    console.log('🔍 Geocoding address:', address);

    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1&addressdetails=1&countrycodes=ph`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const result = data[0];
                const lat = parseFloat(result.lat);
                const lng = parseFloat(result.lon);

                console.log('✅ Auto-geocoded to:', lat, lng);
                console.log('📍 Location:', result.display_name);

                // Update map with smooth animation
                map.flyTo([lat, lng], 16, {
                    animate: true,
                    duration: 1.5
                });

                // Remove existing marker
                if (marker) {
                    map.removeLayer(marker);
                }

                // Add new marker with enhanced styling
                marker = L.marker([lat, lng], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    }),
                    draggable: true
                }).addTo(map)
                    .bindPopup(`
                        <div class="text-center">
                            <strong><i class="fas fa-map-marker-alt text-success"></i> 🎯 Auto-Located!</strong><br>
                            <small class="text-muted">${result.display_name}</small><br>
                            <small><strong>Coordinates:</strong><br>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</small><br>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-success me-1" onclick="confirmLocation(${lat}, ${lng})">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                                <button class="btn btn-sm btn-info" onclick="reverseGeocode(${lat}, ${lng})">
                                    <i class="fas fa-search"></i> Get Address
                                </button>
                            </div>
                        </div>
                    `)
                    .openPopup();

                // Make marker draggable
                marker.on('dragend', function(e) {
                    const position = e.target.getLatLng();
                    const newLat = position.lat;
                    const newLng = position.lng;

                    // Update coordinates
                    document.getElementById('latitude').value = newLat;
                    document.getElementById('longitude').value = newLng;
                    document.getElementById('lat_display').value = newLat.toFixed(6);
                    document.getElementById('lng_display').value = newLng.toFixed(6);

                    showAlert('📍 Location updated by dragging! You can save changes now.', 'info');
                });

                // Update coordinate displays
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('lat_display').value = lat.toFixed(6);
                document.getElementById('lng_display').value = lng.toFixed(6);

                // Show success message with animation
                showAlert('🎯 Location automatically found and pinpointed on map!', 'success', 4000);

            } else {
                console.log('❌ No geocoding results found for:', address);
                showAlert('📍 Could not find exact location. Try being more specific or use the search function.', 'warning', 3000);
            }
        })
        .catch(error => {
            console.error('❌ Auto-geocoding error:', error);
            showAlert('⚠️ Error finding location. Please check your internet connection.', 'warning', 3000);
        });
}

// Confirm location and save coordinates
function confirmLocation(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('lat_display').value = lat.toFixed(6);
    document.getElementById('lng_display').value = lng.toFixed(6);

    showAlert('Location confirmed! Don\'t forget to save your changes.', 'success');

    // Close popup
    if (marker) {
        marker.closePopup();
    }
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    initMap();

    // Add event listeners for auto-geocoding with enhanced feedback
    const addressFields = ['business_address', 'barangay', 'city', 'province'];
    addressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            console.log('✅ Adding auto-geocoding listener to:', fieldId);

            // Add input event for real-time geocoding
            field.addEventListener('input', function() {
                console.log('📝 Address field changed:', fieldId, '=', this.value);
                autoGeocodeAddress();
            });

            // Add change event for when field loses focus
            field.addEventListener('change', function() {
                console.log('🔄 Address field changed (blur):', fieldId, '=', this.value);
                autoGeocodeAddress();
            });

            // Add visual feedback when typing
            field.addEventListener('keyup', function() {
                if (this.value.length > 3) {
                    this.style.borderColor = '#28a745';
                    this.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        } else {
            console.warn('⚠️ Address field not found:', fieldId);
        }
    });

    console.log('🎯 Auto-geocoding system initialized successfully!');

    // Make marker draggable if it exists
    if (marker) {
        marker.setDraggable(true);
        marker.on('dragend', function(e) {
            const position = e.target.getLatLng();
            const lat = position.lat;
            const lng = position.lng;

            // Update coordinates
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('lat_display').value = lat.toFixed(6);
            document.getElementById('lng_display').value = lng.toFixed(6);

            // Update popup
            marker.bindPopup(`
                <div class="text-center">
                    <strong><i class="fas fa-map-marker-alt text-primary"></i> Dragged Location</strong><br>
                    <small>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</small><br>
                    <button class="btn btn-sm btn-primary mt-2" onclick="reverseGeocode(${lat}, ${lng})">
                        <i class="fas fa-search"></i> Get Address
                    </button>
                </div>
            `).openPopup();

            showAlert('Location updated! You can get the address or save changes.', 'info');
        });
    }
});
</script>

<style>
.search-result-item {
    transition: all 0.3s ease;
}

.search-result-item:hover {
    background-color: #f8f9fa;
    border-color: #007bff !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

#map {
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.leaflet-popup-content-wrapper {
    border-radius: 8px;
}

.leaflet-popup-tip {
    background: white;
}

.card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: none;
}

.card-header {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    border-radius: 10px 10px 0 0 !important;
}

.btn {
    border-radius: 8px;
}

.form-control {
    border-radius: 8px;
}

.alert {
    border-radius: 10px;
    border: none;
}
</style>

<?php include '../includes/supplier_footer.php'; ?>
