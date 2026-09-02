<?php
require_once '../config/config.php';
require_once '../includes/session_guard.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || get_user_type() !== 'admin') {
    redirect(base_url('auth/login.php'));
}

$user_id = get_user_id();
$user = new User($database);

// Get all approved suppliers with location data
$sql = "SELECT s.*, 
               AVG(f.rating) as avg_rating,
               COUNT(f.id) as total_reviews,
               COUNT(DISTINCT i.id) as product_count
        FROM suppliers s
        LEFT JOIN feedback f ON s.id = f.supplier_id
        LEFT JOIN inventory i ON s.id = i.supplier_id AND i.availability_status = 'available'
        WHERE s.latitude IS NOT NULL AND s.longitude IS NOT NULL
        GROUP BY s.id
        ORDER BY s.business_name";
$suppliers = $database->fetchAll($sql);

$page_title = 'Supplier Locations';
include '../includes/modern_admin_header.php';
include '../includes/modern_admin_sidebar.php';
?>

<!-- Main Content -->
<main class="role-main-content admin-main-content">
    <!-- Dashboard Header -->
    <div class="modern-dashboard-header">
        <div>
            <h1 class="modern-dashboard-title">
                <div class="modern-dashboard-title-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                Supplier Locations
            </h1>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
            <div id="suppliers-map" style="height: 600px; width: 100%;"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Supplier List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Location</th>
                            <th>Products</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No suppliers with location data found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php 
                                            $profile_image = asset_url('images/placeholder-fish.jpg');
                                            if (!empty($supplier['logo_url'])) {
                                                $logo = $supplier['logo_url'];
                                                if (filter_var($logo, FILTER_VALIDATE_URL)) {
                                                    $profile_image = $logo;
                                                } else {
                                                    $logo_path = ltrim($logo, '/');
                                                    $profile_image = base_url($logo_path);
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                                 alt="<?php echo htmlspecialchars($supplier['business_name']); ?>"
                                                 class="rounded-circle me-3"
                                                 style="width: 40px; height: 40px; object-fit: cover;"
                                                 onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>'; this.onerror=null;">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($supplier['business_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($supplier['owner_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($supplier['barangay'] . ', ' . $supplier['city'] . ', ' . $supplier['province']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($supplier['product_count']); ?> products
                                    </td>
                                    <td>
                                        <?php if ($supplier['total_reviews'] > 0): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="me-1"><?php echo number_format($supplier['avg_rating'], 1); ?></div>
                                                <div class="text-warning me-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= round($supplier['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="text-muted">(<?php echo $supplier['total_reviews']; ?>)</div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No ratings</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the map centered on Tangub City
    const map = L.map('suppliers-map').setView([8.0500, 123.7500], 12);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Supplier data from PHP
    const suppliers = <?php echo json_encode($suppliers); ?>;
    
    // Add markers for each supplier
    suppliers.forEach(function(supplier) {
        if (supplier.latitude && supplier.longitude) {
            const lat = parseFloat(supplier.latitude);
            const lng = parseFloat(supplier.longitude);
            
            // Create marker with popup
            const marker = L.marker([lat, lng]).addTo(map);
            
            // Create popup content
            let popupContent = `
                <div class="supplier-popup">
                    <h6 class="fw-bold">${supplier.business_name}</h6>
                    <p class="mb-1"><i class="fas fa-map-marker-alt text-danger me-1"></i> ${supplier.barangay}, ${supplier.city}</p>
            `;
            
            if (supplier.product_count > 0) {
                popupContent += `<p class="mb-1"><i class="fas fa-box me-1"></i> ${supplier.product_count} products</p>`;
            }
            
            if (supplier.total_reviews > 0) {
                const stars = '★'.repeat(Math.floor(supplier.avg_rating)) + '☆'.repeat(5 - Math.floor(supplier.avg_rating));
                popupContent += `<p class="mb-0"><i class="fas fa-star text-warning me-1"></i> ${parseFloat(supplier.avg_rating).toFixed(1)} (${supplier.total_reviews} reviews)</p>`;
            }
            
            popupContent += '</div>';
            
            marker.bindPopup(popupContent);
        }
    });
});
</script>

<style>
.supplier-popup {
    min-width: 200px;
}

.supplier-popup h6 {
    margin-bottom: 8px;
    color: #333;
}

.supplier-popup p {
    margin-bottom: 4px;
    font-size: 14px;
}

.leaflet-popup-content {
    margin: 10px;
}
</style>
