    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables for enhanced tables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Modern JavaScript -->
    <script src="../assets/js/modern-sidebar.js"></script>
    <!-- Removed dark-mode.js as we're not using dark mode functionality -->
    <script src="../assets/js/modern-charts.js"></script>
    <script src="../assets/js/admin-functionality.js"></script>

    <!-- Global Footer -->
    <footer class="global-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="brand-badge"><i class="fas fa-fish"></i></div>
                    <h5 class="brand-name"><?php
 echo APP_NAME; ?></h5>
                    <p class="brand-desc">Admin panel for managing suppliers, orders, and platform health.</p>
                </div>
                <div class="footer-links">
                    <h6>Admin Links</h6>
                    <ul>
                        <li><a href="<?php echo base_url('admin/dashboard.php'); ?>">Dashboard</a></li>
                        <li><a href="<?php echo base_url('admin/suppliers.php'); ?>">Suppliers</a></li>
                        <li><a href="<?php echo base_url('admin/orders.php'); ?>">Orders</a></li>
                        <li><a href="<?php echo base_url('admin/reports.php'); ?>">Reports</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h6>Contact</h6>
                    <ul class="contact-list">
                        <li><i class="fas fa-envelope"></i> admin@fingerling.com</li>
                        <li><i class="fas fa-phone"></i> +63 900 000 0000</li>
                    </ul>
                </div>
            </div>
            <hr class="footer-divider" />
            <div class="footer-bar">
                <span>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
                <div class="footer-legal">
                    <a href="#">Privacy</a>
                    <span>•</span>
                    <a href="#">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .global-footer { background:#0f172a; color:#e5e7eb; padding: 3rem 0 1.25rem; margin-top: 2rem; }
        .global-footer .container { max-width: 1140px; margin: 0 auto; padding: 0 1rem; }
        .footer-grid { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 2rem; align-items: start; }
        .brand-badge { width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:inline-flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(37,99,235,.35); }
        .brand-badge i{ color:#fff; font-size:18px; line-height:1; }
        .brand-name { margin: .75rem 0 .5rem; font-weight:800; }
        .brand-desc { color:#cbd5e1; margin:0; }
        .footer-links h6, .footer-contact h6{ font-weight:700; margin-bottom:.75rem; }
        .footer-links ul, .footer-contact ul{ list-style:none; padding:0; margin:0; }
        .footer-links li{ margin:.4rem 0; }
        .footer-links a{ color:#e5e7eb; text-decoration:none; }
        .footer-links a:hover{ color:#fff; text-decoration:underline; }
        .contact-list li{ margin:.4rem 0; display:flex; align-items:center; gap:.5rem; color:#cbd5e1; }
        .footer-divider{ border-color: rgba(255,255,255,.08); margin:1.5rem 0; }
        .footer-bar{ display:flex; justify-content:space-between; align-items:center; color:#cbd5e1; font-size:.95rem; }
        .footer-legal{ display:flex; align-items:center; gap:.5rem; }
        .footer-legal a{ color:#e5e7eb; text-decoration:none; }
        .footer-legal a:hover{ color:#fff; text-decoration:underline; }
        @media (max-width: 991.98px){ .footer-grid{ grid-template-columns: 1fr 1fr; } }
        @media (max-width: 575.98px){ .footer-grid{ grid-template-columns: 1fr; } .footer-bar{ flex-direction:column; gap:.5rem; text-align:center; } }
    </style>
</body>
</html>
