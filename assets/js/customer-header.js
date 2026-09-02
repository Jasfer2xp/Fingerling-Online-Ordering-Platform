/**
 * Customer Header Functionality
 * Handles search and language toggle features
 */

document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchBar = document.querySelector('.search-bar input');
    const searchButton = document.querySelector('.search-bar button');
    const searchResultsContainer = document.createElement('div');
    
    if (searchBar && searchButton) {
        // Create search results container
        searchResultsContainer.id = 'search-results-container';
        searchResultsContainer.className = 'search-results-dropdown';
        searchResultsContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
            margin-top: 5px;
        `;
        
        // Insert after search bar
        searchBar.parentNode.style.position = 'relative';
        searchBar.parentNode.appendChild(searchResultsContainer);
        
        // Handle search on button click
        searchButton.addEventListener('click', function() {
            performSearch(searchBar.value.trim());
        });
        
        // Handle search on Enter key
        searchBar.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(searchBar.value.trim());
            }
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchBar.contains(e.target) && !searchButton.contains(e.target) && !searchResultsContainer.contains(e.target)) {
                searchResultsContainer.style.display = 'none';
            }
        });
    }
    
    // Language toggle functionality
    const languageToggle = document.querySelector('.top-bar a[href="#"]:first-child');
    if (languageToggle) {
        languageToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get current language from session or default to English
            const currentLang = getLanguage();
            const newLang = currentLang === 'en' ? 'tl' : 'en';
            
            // Save language preference
            setLanguage(newLang);
            
            // Update UI text
            updateLanguage(newLang);
            
            // Update toggle text
            languageToggle.textContent = newLang === 'en' ? 'Tagalog' : 'English';
        });
    }
    
    // Initialize language on page load
    initializeLanguage();
});

/**
 * Perform search
 */
function performSearch(query) {
    const searchResultsContainer = document.getElementById('search-results-container');
    
    if (!query) {
        searchResultsContainer.style.display = 'none';
        return;
    }
    
    // Show loading state
    searchResultsContainer.innerHTML = '<div class="p-3 text-center">Searching...</div>';
    searchResultsContainer.style.display = 'block';
    
    // Perform search
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                displaySearchResults(data.data);
            } else {
                searchResultsContainer.innerHTML = '<div class="p-3 text-center text-muted">No results found</div>';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResultsContainer.innerHTML = '<div class="p-3 text-center text-danger">Search failed. Please try again.</div>';
        });
}

/**
 * Display search results
 */
function displaySearchResults(results) {
    const searchResultsContainer = document.getElementById('search-results-container');
    
    let html = '';
    
    // Group results by type
    const groupedResults = {};
    results.forEach(result => {
        if (!groupedResults[result.type]) {
            groupedResults[result.type] = [];
        }
        groupedResults[result.type].push(result);
    });
    
    // Display results by type
    Object.keys(groupedResults).forEach(type => {
        const items = groupedResults[type];
        
        // Add section header
        let typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
        if (type === 'species') typeLabel = 'Fish Species';
        if (type === 'product') typeLabel = 'Fingerlings';
        
        html += `<div class="search-results-section">
                    <div class="search-results-header p-2 fw-bold text-uppercase small text-muted" style="background-color: #f8f9fa;">${typeLabel}</div>
                    <div class="search-results-list">`;
        
        // Add items
        items.forEach(item => {
            if (type === 'supplier') {
                html += `<a href="/customer/supplier-profile.php?id=${item.id}" class="search-result-item d-block text-decoration-none text-dark">
                            <div class="p-2 border-bottom">
                                <div class="fw-bold">${item.name}</div>
                                <div class="small text-muted">${item.location}</div>
                                ${item.rating ? `<div class="small">Rating: ${item.rating}/5</div>` : ''}
                            </div>
                        </a>`;
            } else if (type === 'barangay') {
                html += `<a href="/customer/browse.php?search=${encodeURIComponent(item.name)}" class="search-result-item d-block text-decoration-none text-dark">
                            <div class="p-2 border-bottom">
                                <div class="fw-bold">${item.name}</div>
                                <div class="small text-muted">${item.location}</div>
                            </div>
                        </a>`;
            } else if (type === 'species') {
                html += `<a href="/customer/browse.php?species=${item.id}" class="search-result-item d-block text-decoration-none text-dark">
                            <div class="p-2 border-bottom">
                                <div class="fw-bold">${item.name}</div>
                                ${item.scientific_name ? `<div class="small text-muted">${item.scientific_name}</div>` : ''}
                            </div>
                        </a>`;
            }
        } else if (type === 'product') {
            html += `<a href="/customer/product-details.php?id=${item.id}" class="search-result-item d-block text-decoration-none text-dark">
                        <div class="p-2 border-bottom">
                            <div class="fw-bold">${item.name}</div>
                            <div class="small text-muted">Supplier: ${item.supplier}</div>
                            <div class="small">Price: ₱${parseFloat(item.price).toFixed(2)} | Size: ${item.size} | Stock: ${item.stock}</div>
                        </div>
                    </a>`;
        }
    });
    
    html += '</div></div>';
}
    
    searchResultsContainer.innerHTML = html;
    
    // Add event listeners to close dropdown when clicking on a result
    const resultItems = searchResultsContainer.querySelectorAll('.search-result-item');
    resultItems.forEach(item => {
        item.addEventListener('click', function() {
            document.getElementById('search-results-container').style.display = 'none';
        });
    });
}

/**
 * Language functionality
 */
function getLanguage() {
    // Check if language is stored in localStorage
    return localStorage.getItem('preferred_language') || 'en';
}

function setLanguage(lang) {
    localStorage.setItem('preferred_language', lang);
}

function initializeLanguage() {
    const currentLang = getLanguage();
    const languageToggle = document.querySelector('.top-bar a[href="#"]:first-child');
    
    if (languageToggle) {
        languageToggle.textContent = currentLang === 'en' ? 'Tagalog' : 'English';
    }
    
    updateLanguage(currentLang);
}

function updateLanguage(lang) {
    // This is a simplified implementation
    // In a full implementation, this would translate all page content
    console.log(`Language switched to: ${lang === 'en' ? 'English' : 'Tagalog'}`);
    
    // Show a message to indicate language change
    const message = document.createElement('div');
    message.style.cssText = `
        position: fixed;
        top: 50px;
        right: 20px;
        background: #198754;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        z-index: 9999;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;
    message.innerHTML = lang === 'en' ? 'Switched to English' : 'Nagpalit sa Tagalog';
    
    document.body.appendChild(message);
    
    // Remove message after 2 seconds
    setTimeout(() => {
        document.body.removeChild(message);
    }, 2000);
}