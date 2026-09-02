/* Main JavaScript file for Fingerling Online Ordering Platform */

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize application
function initializeApp() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize AJAX setup
    setupAjax();
    
    // Initialize event listeners
    setupEventListeners();
    
    // Initialize animations
    initializeAnimations();
}

// Initialize Bootstrap tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize form validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

// Setup AJAX defaults
function setupAjax() {
    // Add CSRF token to all AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        fetch.defaults = {
            headers: {
                'X-CSRF-TOKEN': csrfToken.getAttribute('content')
            }
        };
    }
}

// Setup event listeners
function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filter functionality
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', handleFilter);
    });
    
    // Quantity controls
    const quantityControls = document.querySelectorAll('.quantity-control');
    quantityControls.forEach(control => {
        control.addEventListener('click', handleQuantityChange);
    });
    
    // Add to cart buttons
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', handleAddToCart);
    });
    
    // Order status updates
    const statusButtons = document.querySelectorAll('.update-status');
    statusButtons.forEach(button => {
        button.addEventListener('click', handleStatusUpdate);
    });
}

// Initialize animations
function initializeAnimations() {
    // Fade in elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    animatedElements.forEach(el => observer.observe(el));
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showLoading() {
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'spinner-overlay';
    loadingOverlay.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    document.body.appendChild(loadingOverlay);
}

function hideLoading() {
    const loadingOverlay = document.querySelector('.spinner-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer') || document.body;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Search functionality
function handleSearch(event) {
    const query = event.target.value.trim();
    if (query.length >= 2) {
        performSearch(query);
    } else if (query.length === 0) {
        clearSearch();
    }
}

function performSearch(query) {
    showLoading();
    
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => {
            console.error('Search error:', error);
            showAlert('Search failed. Please try again.', 'danger');
        })
        .finally(() => {
            hideLoading();
        });
}

function displaySearchResults(results) {
    const resultsContainer = document.getElementById('searchResults');
    if (!resultsContainer) return;
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<p class="text-muted">No results found.</p>';
        return;
    }
    
    let html = '';
    results.forEach(item => {
        html += createProductCard(item);
    });
    
    resultsContainer.innerHTML = html;
}

function clearSearch() {
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }
}

// Filter functionality
function handleFilter(event) {
    const filters = getActiveFilters();
    applyFilters(filters);
}

function getActiveFilters() {
    const filters = {};
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(select => {
        if (select.value) {
            filters[select.name] = select.value;
        }
    });
    
    return filters;
}

function applyFilters(filters) {
    showLoading();
    
    const params = new URLSearchParams(filters);
    
    fetch(`/api/filter.php?${params}`)
        .then(response => response.json())
        .then(data => {
            displayFilteredResults(data);
        })
        .catch(error => {
            console.error('Filter error:', error);
            showAlert('Filter failed. Please try again.', 'danger');
        })
        .finally(() => {
            hideLoading();
        });
}

// Quantity controls
function handleQuantityChange(event) {
    const button = event.target;
    const input = button.parentNode.querySelector('input[type="number"]');
    const action = button.dataset.action;
    
    let currentValue = parseInt(input.value) || 1;
    const min = parseInt(input.min) || 1;
    const max = parseInt(input.max) || 999;
    
    if (action === 'increase' && currentValue < max) {
        input.value = currentValue + 1;
    } else if (action === 'decrease' && currentValue > min) {
        input.value = currentValue - 1;
    }
    
    // Trigger change event
    input.dispatchEvent(new Event('change'));
}

// Add to cart functionality
function handleAddToCart(event) {
    event.preventDefault();
    
    const button = event.target;
    const productId = button.dataset.productId;
    const quantity = button.closest('.product-card').querySelector('input[type="number"]').value;
    
    addToCart(productId, quantity);
}

// Function to update cart count in header
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        if (count > 0) {
            element.textContent = count;
            element.style.display = 'flex'; // Assuming flex display based on CSS
        } else {
            element.style.display = 'none';
        }
    });
}

// Generic add to cart function that can be used across pages
function addToCart(productId, quantity = 1) {
    // Show loading state on all buttons for this product
    const buttons = document.querySelectorAll(`[data-product-id="${productId}"]`);
    const originalTexts = [];
    
    buttons.forEach((button, index) => {
        originalTexts[index] = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        button.disabled = true;
    });

    fetch('../customer/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${productId}&quantity=${quantity}`
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update cart count in header
            updateCartCount(data.cart_count);
            
            // Show success message
            showAlert('success', data.message || 'Product added to cart successfully!');
        } else {
            showAlert('danger', data.message || 'Failed to add item to cart.');
        }
    })
    .catch(error => {
        console.error('Add to cart error:', error);
        showAlert('danger', 'An error occurred while adding item to cart.');
    })
    .finally(() => {
        // Restore button states
        buttons.forEach((button, index) => {
            button.innerHTML = originalTexts[index];
            button.disabled = false;
        });
    });
}

// Show alert message
function showAlert(type, message) {
    // Remove any existing alerts of the same type
    const existingAlert = document.querySelector(`.alert.alert-${type}`);
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
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

// Order status updates
function handleStatusUpdate(event) {
    const button = event.target;
    const orderId = button.dataset.orderId;
    const newStatus = button.dataset.status;
    
    if (confirm('Are you sure you want to update this order status?')) {
        updateOrderStatus(orderId, newStatus);
    }
}

function updateOrderStatus(orderId, status) {
    showLoading();
    
    const data = {
        order_id: orderId,
        status: status
    };
    
    fetch('/api/orders/update-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Order status updated successfully!', 'success');
            location.reload(); // Refresh to show updated status
        } else {
            showAlert(data.message || 'Failed to update order status.', 'danger');
        }
    })
    .catch(error => {
        console.error('Status update error:', error);
        showAlert('Failed to update order status.', 'danger');
    })
    .finally(() => {
        hideLoading();
    });
}

// Map functionality - Using Leaflet.js instead of Google Maps
// Individual pages handle their own map initialization

// Utility functions for product display
function createProductCard(product) {
    return `
        <div class="col">
            <div class="card product-card h-100">
                <img src="${product.image_url || '/assets/images/placeholder.jpg'}" 
                     class="card-img-top product-image" alt="${product.name}">
                <div class="card-body">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text text-muted">${product.description}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h5 text-primary mb-0">₱${product.price}</span>
                        <small class="text-muted">${product.stock} available</small>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline-secondary quantity-control" data-action="decrease">-</button>
                            <input type="number" class="form-control form-control-sm mx-2" value="1" min="1" max="${product.stock}" style="width: 60px;">
                            <button class="btn btn-sm btn-outline-secondary quantity-control" data-action="increase">+</button>
                        </div>
                        <button class="btn btn-primary btn-sm add-to-cart" data-product-id="${product.id}">
                            <i class="fas fa-cart-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Export functions for global use
window.FingerlingsApp = {
    showLoading,
    hideLoading,
    showAlert,
    updateCartCount
};
