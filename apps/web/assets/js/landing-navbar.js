/**
 * Landing Page Navbar Toggle JavaScript
 * Fixes toggle behavior and prevents auto-close issues
 */

(function() {
    'use strict';
    
    let navbarInitialized = false;
    
    // Initialize navbar toggle functionality
    function initializeLandingNavbar() {
        if (navbarInitialized) return;
        
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        const navbarNav = document.querySelector('#navbarNav');
        
        if (!navbarToggler || !navbarCollapse) {
            console.warn('Navbar elements not found');
            return;
        }
        
        // Remove Bootstrap's default data attributes to prevent conflicts
        navbarToggler.removeAttribute('data-bs-toggle');
        navbarToggler.removeAttribute('data-bs-target');
        
        // Custom toggle functionality
        navbarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isExpanded = navbarToggler.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                closeNavbar();
            } else {
                openNavbar();
            }
        });
        
        function openNavbar() {
            navbarCollapse.classList.add('show');
            navbarToggler.setAttribute('aria-expanded', 'true');
            navbarToggler.classList.add('collapsed');
            
            // Prevent background scroll on mobile
            if (window.innerWidth < 992) {
                document.body.style.overflow = 'hidden';
            }
            
            // Add click outside to close (with delay to prevent immediate close)
            setTimeout(() => {
                document.addEventListener('click', handleOutsideClick);
            }, 100);
            
            console.log('Navbar opened');
        }
        
        function closeNavbar() {
            navbarCollapse.classList.remove('show');
            navbarToggler.setAttribute('aria-expanded', 'false');
            navbarToggler.classList.remove('collapsed');
            
            // Restore background scroll
            document.body.style.overflow = '';
            
            // Remove click outside listener
            document.removeEventListener('click', handleOutsideClick);
            
            console.log('Navbar closed');
        }
        
        function handleOutsideClick(e) {
            // Only close if clicking outside both navbar and toggler
            if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                closeNavbar();
            }
        }
        
        // Close navbar when clicking on navigation links (mobile only)
        const navLinks = navbarCollapse.querySelectorAll('.nav-link, .btn');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    // Small delay for better UX
                    setTimeout(() => closeNavbar(), 200);
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                // Desktop view - close mobile navbar
                closeNavbar();
            }
        });
        
        // Prevent navbar collapse from closing when clicking inside it
        navbarCollapse.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        navbarInitialized = true;
        console.log('Landing navbar initialized successfully');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLandingNavbar);
    } else {
        initializeLandingNavbar();
    }
    
    // Fallback initialization
    window.addEventListener('load', function() {
        if (!navbarInitialized) {
            setTimeout(initializeLandingNavbar, 100);
        }
    });
    
    // Expose initialization function globally
    window.initializeLandingNavbar = initializeLandingNavbar;
    
})();
