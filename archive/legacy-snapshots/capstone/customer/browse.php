<div class="card-body">
    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
    <p class="card-text">
        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['location']); ?>
    </p>
    <p class="card-text">
        <strong>₱<?php echo number_format($product['price_per_piece'], 2); ?></strong>
        <span class="badge bg-secondary ms-2"><?php echo $product['stock_quantity']; ?> available</span>
    </p>
    <p class="card-text">
        <small class="text-muted"><?php echo htmlspecialchars($product['size_category']); ?></small>
        <br>
        <small class="text-danger">
            <i class="fas fa-info-circle"></i> Minimum order: <?php echo $product['minimum_order']; ?> units
        </small>
    </p>
    
    <!-- Image display with fallback -->
    <?php if (!empty($product['image_path'])): ?>
        <img src="<?php echo base_url($product['image_path']); ?>" 
             alt="<?php echo htmlspecialchars($product['name']); ?>" 
             class="img-fluid rounded mb-3" 
             style="max-height: 200px; object-fit: cover;">
    <?php else: ?>
        <!-- Default placeholder image -->
        <img src="<?php echo base_url('assets/images/default-fish.png'); ?>" 
             alt="Default fish" 
             class="img-fluid rounded mb-3" 
             style="max-height: 200px; object-fit: cover;">
    <?php endif; ?>
    
    <div class="d-flex justify-content-between">
        <a href="<?php echo base_url('customer/product-detail.php?id=' . $product['id']); ?>" 
           class="btn btn-outline-primary btn-sm">
            <i class="fas fa-eye"></i> View
        </a>
        <button type="button" class="btn btn-primary btn-sm add-to-cart-btn"
                data-product-id="<?php echo $product['id']; ?>"
                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                data-price="<?php echo $product['price_per_piece']; ?>"
                data-min-order="<?php echo $product['minimum_order']; ?>">
            <i class="fas fa-shopping-cart"></i> Add <?php echo $product['minimum_order']; ?>
        </button>
    </div>
</div>