/**
 * Admin Panel Functionality
 * JavaScript functions for admin panel operations
 */

// Global admin object
window.AdminPanel = window.AdminPanel || {};

// Initialize admin functionality
document.addEventListener('DOMContentLoaded', function() {
    AdminPanel.init();
});

AdminPanel = {
    // Initialize all admin functionality
    init: function() {
        this.initDataTables();
        this.initCharts();
        this.initModals();
        this.initFormValidation();
        this.initAjaxForms();
        this.initTooltips();
        this.initConfirmActions();
    },

    // Initialize DataTables
    initDataTables: function() {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('.data-table').each(function() {
                $(this).DataTable({
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
                    },
                    columnDefs: [
                        { orderable: false, targets: 'no-sort' }
                    ]
                });
            });
        }
    },

    // Initialize charts
    initCharts: function() {
        // Revenue Chart
        const revenueChart = document.getElementById('revenueChart');
        if (revenueChart && typeof Chart !== 'undefined') {
            new Chart(revenueChart, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Orders Chart
        const ordersChart = document.getElementById('ordersChart');
        if (ordersChart && typeof Chart !== 'undefined') {
            new Chart(ordersChart, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#198754', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    },

    // Initialize modals
    initModals: function() {
        // Auto-focus first input in modals
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('input:first').focus();
        });
    },

    // Initialize form validation
    initFormValidation: function() {
        // Add validation to forms with .needs-validation class
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    },

    // Initialize AJAX forms
    initAjaxForms: function() {
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action') || window.location.href;
            const method = form.attr('method') || 'POST';
            const data = form.serialize();

            AdminPanel.showLoading();

            $.ajax({
                url: url,
                method: method,
                data: data,
                dataType: 'json',
                success: function(response) {
                    AdminPanel.hideLoading();
                    if (response.success) {
                        AdminPanel.showAlert(response.message, 'success');
                        if (response.redirect) {
                            setTimeout(() => {
                                window.location.href = response.redirect;
                            }, 1500);
                        }
                    } else {
                        AdminPanel.showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    AdminPanel.hideLoading();
                    AdminPanel.showAlert('An error occurred. Please try again.', 'danger');
                }
            });
        });
    },

    // Initialize tooltips
    initTooltips: function() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    },

    // Initialize confirm actions
    initConfirmActions: function() {
        $('.confirm-action').on('click', function(e) {
            e.preventDefault();
            const message = $(this).data('confirm') || 'Are you sure?';
            const href = $(this).attr('href');
            
            if (confirm(message)) {
                window.location.href = href;
            }
        });
    },

    // Show alert message
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

    // Show loading spinner
    showLoading: function() {
        const loader = document.createElement('div');
        loader.id = 'admin-loader';
        loader.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        loader.style.backgroundColor = 'rgba(0,0,0,0.5)';
        loader.style.zIndex = '9999';
        loader.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
        document.body.appendChild(loader);
    },

    // Hide loading spinner
    hideLoading: function() {
        const loader = document.getElementById('admin-loader');
        if (loader) {
            loader.remove();
        }
    },

    // Confirm action with callback
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    // Refresh page data
    refreshData: function() {
        window.location.reload();
    },

    // Export table data
    exportTable: function(tableId, filename = 'export') {
        const table = document.getElementById(tableId);
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [];
            const cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                row.push(cols[j].innerText);
            }
            csv.push(row.join(','));
        }

        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');
        downloadLink.download = filename + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    },

    // Update status via AJAX
    updateStatus: function(id, status, type = 'user') {
        AdminPanel.showLoading();
        
        $.ajax({
            url: 'ajax/update-status.php',
            method: 'POST',
            data: {
                id: id,
                status: status,
                type: type
            },
            dataType: 'json',
            success: function(response) {
                AdminPanel.hideLoading();
                if (response.success) {
                    AdminPanel.showAlert(response.message, 'success');
                    setTimeout(() => {
                        AdminPanel.refreshData();
                    }, 1000);
                } else {
                    AdminPanel.showAlert(response.message, 'danger');
                }
            },
            error: function() {
                AdminPanel.hideLoading();
                AdminPanel.showAlert('Failed to update status.', 'danger');
            }
        });
    },

    // Delete item via AJAX
    deleteItem: function(id, type = 'item') {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            return;
        }

        AdminPanel.showLoading();
        
        $.ajax({
            url: 'ajax/delete-item.php',
            method: 'POST',
            data: {
                id: id,
                type: type
            },
            dataType: 'json',
            success: function(response) {
                AdminPanel.hideLoading();
                if (response.success) {
                    AdminPanel.showAlert(response.message, 'success');
                    setTimeout(() => {
                        AdminPanel.refreshData();
                    }, 1000);
                } else {
                    AdminPanel.showAlert(response.message, 'danger');
                }
            },
            error: function() {
                AdminPanel.hideLoading();
                AdminPanel.showAlert('Failed to delete item.', 'danger');
            }
        });
    }
};

// Global functions for backward compatibility
function showAlert(message, type) {
    AdminPanel.showAlert(message, type);
}

function confirmAction(message, callback) {
    AdminPanel.confirmAction(message, callback);
}

function showLoading() {
    AdminPanel.showLoading();
}

function hideLoading() {
    AdminPanel.hideLoading();
}
