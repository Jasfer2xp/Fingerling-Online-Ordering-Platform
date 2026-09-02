<?php foreach ($featured_products as $item): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="product-card bg-white p-3 text-center shadow rounded-3">
            <?php
            // Handle image path correctly
            $image_src = '';
            if (!empty($item['image_path'])) {
                // Check if it's a full URL or relative path
                if (filter_var($item['image_path'], FILTER_VALIDATE_URL)) {
                    $image_src = $item['image_path'];
                } else {
                    // For relative paths, make sure they're correctly formed
                    $clean_path = ltrim($item['image_path'], '/');
                    $image_src = base_url($clean_path);
                }
            } elseif (!empty($item['image_url'])) {
                $image_src = $item['image_url'];
            } else {
                // Try to construct image path from species name
                if (!empty($item['species_name'])) {
                    $species_name = strtolower(str_replace([' ', '_'], '-', $item['species_name']));
                    $possible_paths = [
                        "images/{$species_name}.jpg",
                        "images/{$species_name}.jpeg",
                        "images/{$species_name}.png"
                    ];
                    
                    foreach ($possible_paths as $path) {
                        if (file_exists(__DIR__ . "/../{$path}")) {
                            $image_src = base_url($path);
                            break;
                        }
                    }
                }
                
                // If still no image found, use placeholder
                if (empty($image_src)) {
                    $image_src = asset_url('images/placeholder-fish.jpg');
                }
            }
            ?>
            <img src="<?php echo $image_src; ?>"
                alt="<?php echo htmlspecialchars($item['species_name'] ?? 'Product'); ?>"
                class="product-img mb-3 rounded-3"
                onerror="this.src='<?php echo asset_url('images/placeholder-fish.jpg'); ?>'; this.onerror=null;">
            <h6 class="product-name text-truncate fw-semibold"><?php echo htmlspecialchars($item['species_name'] ?? ''); ?></h6>
            <p class="product-price fw-bold text-primary mb-2">₱<?php echo number_format($item['price_per_piece'] ?? 0, 2); ?></p>
            <a href="product-details.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-4">View</a>
        </div>
    </div>
<?php endforeach; ?>