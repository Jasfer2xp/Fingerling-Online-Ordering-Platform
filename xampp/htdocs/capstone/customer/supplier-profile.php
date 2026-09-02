        <div class="supplier-info">
            <h1><?php echo htmlspecialchars($supplier['business_name']); ?></h1>
            <div class="rating-display">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= round($supplier['avg_rating'] ?? 0)): ?>
                        <i class="fas fa-star rating-stars"></i>
                    <?php else: ?>
                        <i class="far fa-star rating-stars"></i>
                    <?php endif; ?>
                <?php endfor; ?>
                <span class="fw-bold"><?php echo number_format($supplier['avg_rating'] ?? 0, 1); ?></span>
                <span class="text-muted">(<?php echo $supplier['total_reviews'] ?? 0; ?> reviews)</span>
            </div>

            <!-- Delivery Options Display -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">Delivery Options:</h6>
                <div class="d-flex justify-content-center gap-2">
                    <?php if (!empty($supplier['supports_truck'])): ?>
                        <span class="badge bg-primary">
                            <i class="fas fa-truck me-1"></i>Truck
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($supplier['supports_boat'])): ?>
                        <span class="badge bg-info">
                            <i class="fas fa-ship me-1"></i>Boat
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mb-2">
                <span class="badge bg-primary"><?php echo $supplier['product_count'] ?? 0; ?> Products</span>
                <span class="badge bg-success"><?php echo $supplier['city']; ?>, <?php echo $supplier['province']; ?></span>
            </div>
            <p class="supplier-description"><?php echo htmlspecialchars($supplier['description'] ?? ''); ?></p>
        </div>
