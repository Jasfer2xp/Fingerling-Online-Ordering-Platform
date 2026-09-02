        </main>
    </div>

    <!-- Scripts -->
    <!-- Removed dark-mode.js as we're not using dark mode functionality -->
    <script src="<?php
 echo base_url('assets/js/modern-sidebar.js'); ?>"></script>
</body>
</html>

<!-- Modern JavaScript -->
<script src="<?php echo base_url('assets/js/modern-sidebar.js'); ?>"></script>
<!-- Removed dark-mode.js as we're not using dark mode functionality -->

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- DataTables for table management -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Custom Admin Scripts -->
<script>
// Initialize DataTables if present
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
});

// Global admin functions
window.AdminPanel = {
    showAlert: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.role-main-content') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    },
    
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    showLoading: function() {
        const loader = document.createElement('div');
        loader.id = 'admin-loader';
        loader.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        loader.style.backgroundColor = 'rgba(0,0,0,0.5)';
        loader.style.zIndex = '9999';
        loader.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
        document.body.appendChild(loader);
    },
    
    hideLoading: function() {
        const loader = document.getElementById('admin-loader');
        if (loader) {
            loader.remove();
        }
    }
};

// Handle AJAX errors globally
$(document).ajaxError(function(event, xhr, settings, thrownError) {
    AdminPanel.hideLoading();
    AdminPanel.showAlert('An error occurred. Please try again.', 'danger');
    console.error('AJAX Error:', thrownError);
});
</script>

<!-- Global Footer -->
<footer class="global-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="brand-badge"><i class="fas fa-fish"></i></div>
                <h5 class="brand-name"><?php echo APP_NAME; ?></h5>
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
