    <!-- Modern JavaScript -->
    <script src="../assets/js/modern-charts.js"></script>
</div> <!-- Close the main content column -->
</div> <!-- Close the row -->
</div> <!-- Close the container-fluid -->

<!-- Global Footer -->
<footer class="bg-dark text-light mt-5">
    <div class="container py-5">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="brand-badge mb-3">
                    <i class="fas fa-fish fa-2x"></i>
                </div>
                <h5 class="brand-name">Fingerling Marketplace</h5>
                <p class="brand-desc">Connecting aquaculture suppliers with customers for a sustainable future.</p>
            </div>
            <div class="footer-links">
                <h6>Supplier Links</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php
 echo base_url('supplier/dashboard.php'); ?>" class="text-light text-decoration-none">Dashboard</a></li>
                    <li><a href="<?php echo base_url('supplier/inventory.php'); ?>" class="text-light text-decoration-none">Inventory</a></li>
                    <li><a href="<?php echo base_url('supplier/orders.php'); ?>" class="text-light text-decoration-none">Orders</a></li>
                    <li><a href="<?php echo base_url('supplier/help.php'); ?>" class="text-light text-decoration-none">Help</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h6>Contact</h6>
                <ul class="list-unstyled contact-list">
                    <li class="mb-2"><i class="fas fa-envelope me-2"></i> support@fingerling.com</li>
                    <li class="mb-2"><i class="fas fa-phone me-2"></i> +63 900 000 0000</li>
                    <li><i class="fas fa-location-dot me-2"></i> Tangub City, Misamis Occidental</li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="footer-bar d-flex justify-content-between align-items-center">
            <span>© <?php echo date('Y'); ?> Fingerling Marketplace. All rights reserved.</span>
            <div class="footer-legal d-flex gap-3">
                <a href="#" class="text-light text-decoration-none">Privacy</a>
                <span>•</span>
                <a href="#" class="text-light text-decoration-none">Terms</a>
            </div>
        </div>
    </div>
</footer>

<script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        // Function to handle window resize
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                mainContent.classList.add('full-width');
            } else {
                sidebar.classList.remove('hidden');
                mainContent.classList.remove('full-width');
            }
        }
        
        // Initial check
        handleResize();
        
        // Listen for resize events
        window.addEventListener('resize', handleResize);
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>