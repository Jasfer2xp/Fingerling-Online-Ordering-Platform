<script>
    // Function to show suppliers map modal
    function showSuppliersMap() {
        const modal = new bootstrap.Modal(document.getElementById('suppliersMapModal'));
        modal.show();
        
        // Initialize the map after modal is shown
        setTimeout(initSuppliersMap, 100);
    }
    
    // Function to initialize suppliers map
    function initSuppliersMap() {
        // Get the map container
        const mapContainer = document.getElementById('suppliersMap');
        if (!mapContainer) return;
        
        // Create Leaflet map
        const map = L.map(mapContainer).setView([14.5995, 121.0173], 12); // Default to Manila coordinates
        
        // Add OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Fetch supplier data from API
        fetch('../api/suppliers_map_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.suppliers && data.suppliers.length > 0) {
                    // Clear any existing markers
                    map.eachLayer(layer => {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });
                    
                    // Add markers for each supplier
                    data.suppliers.forEach(supplier => {
                        if (supplier.latitude && supplier.longitude) {
                            const marker = L.marker([supplier.latitude, supplier.longitude])
                                .addTo(map)
                                .bindPopup(`
                                    <div class="popup-content">
                                        <h5>${supplier.business_name}</h5>
                                        <p><strong>Address:</strong> ${supplier.barangay}, ${supplier.city}, ${supplier.province}</p>
                                        <p><strong>Contact:</strong> ${supplier.contact_number}</p>
                                        <p><strong>Rating:</strong> ${supplier.rating ? `${supplier.rating}/5` : 'N/A'}</p>
                                        <a href="../customer/supplier-profile.php?id=${supplier.id}" class="btn btn-sm btn-primary">View Profile</a>
                                    </div>
                                `);
                            
                            // Add custom icon with rating stars
                            const ratingStars = Array.from({length: 5}, (_, i) => 
                                i < Math.floor(supplier.rating) ? '★' : 
                                i === Math.floor(supplier.rating) && supplier.rating % 1 >= 0.5 ? '½' : '☆'
                            ).join('');
                            
                            marker.setIcon(L.divIcon({
                                html: `<div style="background-color: #2563eb; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 14px;">${ratingStars}</div>`,
                                className: 'custom-marker',
                                iconSize: [30, 30],
                                iconAnchor: [15, 15]
                            }));
                        }
                    });
                } else {
                    // Show error message if no data
                    map.eachLayer(layer => {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });
                    map.setView([14.5995, 121.0173], 12);
                    map.addLayer(L.circleMarker([14.5995, 121.0173], {
                        radius: 5,
                        fillColor: '#ef4444',
                        color: '#ef4444',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).bindPopup('No supplier locations available.'));
                }
            })
            .catch(error => {
                console.error('Error loading supplier data:', error);
                // Show error message on map
                map.eachLayer(layer => {
                    if (layer instanceof L.Marker) {
                        map.removeLayer(layer);
                    }
                });
                map.setView([14.5995, 121.0173], 12);
                map.addLayer(L.circleMarker([14.5995, 121.0173], {
                    radius: 5,
                    fillColor: '#ef4444',
                    color: '#ef4444',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8
                }).bindPopup('Failed to load supplier locations.'));
            });
    }
    
    // Initialize map when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Check if map should be initialized immediately
        if (document.querySelector('.nav-links a[href="#"]')) {
            // Add click event listener to the "View Supplier Location" link
            document.querySelector('.nav-links a[href="#"]').addEventListener('click', function(e) {
                e.preventDefault();
                showSuppliersMap();
            });
        }
    });
</script>