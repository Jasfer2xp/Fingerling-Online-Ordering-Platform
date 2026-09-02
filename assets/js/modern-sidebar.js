/**
 * Modern Sidebar System - JavaScript
 * Handles responsive sidebar behavior with smooth animations
 * No transparency effects, solid modern design
 */

// Prevent duplicate declarations
if (typeof ModernSidebar === 'undefined') {
class ModernSidebar {
    constructor() {
        this.sidebar = null;
        this.overlay = null;
        this.toggleBtn = null;
        this.closeBtn = null;
        this.isOpen = false;
        this.isMobile = false;
        
        this.init();
    }
    
    init() {
        // Find sidebar elements
        this.sidebar = document.querySelector('.modern-sidebar') || 
                      document.getElementById('sidebarMenu') ||
                      document.querySelector('.sidebar');
        
        this.overlay = document.querySelector('.modern-sidebar-overlay') ||
                      document.getElementById('sidebarOverlay') ||
                      document.querySelector('.sidebar-overlay');
        
        this.toggleBtn = document.querySelector('.modern-sidebar-toggle') ||
                        document.querySelector('.sidebar-toggle') ||
                        document.querySelector('[data-bs-toggle="sidebar"]');
        
        this.closeBtn = document.querySelector('.modern-sidebar-close');
        
        if (!this.sidebar) {
            console.warn('Modern Sidebar: No sidebar element found');
            return;
        }
        
        // Create overlay if it doesn't exist
        if (!this.overlay) {
            this.createOverlay();
        }
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Initialize responsive behavior
        this.updateResponsiveState();
        
        // Set up resize listener
        window.addEventListener('resize', () => this.handleResize());
        
        console.log('Modern Sidebar initialized successfully');
    }
    
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'modern-sidebar-overlay';
        this.overlay.id = 'sidebarOverlay';
        document.body.appendChild(this.overlay);
        
        // Add click listener to overlay
        this.overlay.addEventListener('click', () => this.close());
    }
    
    setupEventListeners() {
        // Toggle button
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
        }
        
        // Close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.close();
            });
        }
        
        // Overlay click
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.close());
        }
        
        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen && this.isMobile) {
                this.close();
            }
        });
        
        // Navigation links (close on mobile)
        const navLinks = this.sidebar.querySelectorAll('.modern-nav-link, .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (this.isMobile && this.isOpen) {
                    // Small delay to allow navigation
                    setTimeout(() => this.close(), 150);
                }
            });
        });
        
        // Prevent sidebar clicks from closing
        this.sidebar.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
    
    updateResponsiveState() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        if (wasMobile !== this.isMobile) {
            if (!this.isMobile) {
                // Switched to desktop
                this.forceClose();
                this.sidebar.classList.remove('show');
                if (this.overlay) {
                    this.overlay.classList.remove('show');
                }
            } else {
                // Switched to mobile
                this.forceClose();
            }
        }
    }
    
    handleResize() {
        // Debounce resize events
        clearTimeout(this.resizeTimeout);
        this.resizeTimeout = setTimeout(() => {
            this.updateResponsiveState();
        }, 100);
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        if (!this.sidebar || !this.isMobile) return;
        
        console.log('Opening modern sidebar');
        
        this.isOpen = true;
        
        // Add classes
        this.sidebar.classList.add('show', 'slide-in');
        this.sidebar.classList.remove('slide-out');
        
        if (this.overlay) {
            this.overlay.classList.add('show');
        }
        
        // Add body class to prevent scrolling
        document.body.classList.add('sidebar-open');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            this.sidebar.classList.remove('slide-in');
        }, 250);
        
        // Focus first nav link for accessibility
        const firstNavLink = this.sidebar.querySelector('.modern-nav-link, .nav-link');
        if (firstNavLink) {
            setTimeout(() => firstNavLink.focus(), 300);
        }
        
        // Dispatch custom event
        this.dispatchEvent('sidebar:opened');
    }
    
    close() {
        if (!this.sidebar || !this.isOpen) return;
        
        console.log('Closing modern sidebar');
        
        this.isOpen = false;
        
        // Add slide out animation
        this.sidebar.classList.add('slide-out');
        this.sidebar.classList.remove('slide-in');
        
        // Remove overlay
        if (this.overlay) {
            this.overlay.classList.remove('show');
        }
        
        // Remove body class
        document.body.classList.remove('sidebar-open');
        
        // Remove show class after animation
        setTimeout(() => {
            this.sidebar.classList.remove('show', 'slide-out');
        }, 250);
        
        // Dispatch custom event
        this.dispatchEvent('sidebar:closed');
    }
    
    forceClose() {
        if (!this.sidebar) return;
        
        this.isOpen = false;
        this.sidebar.classList.remove('show', 'slide-in', 'slide-out');
        
        if (this.overlay) {
            this.overlay.classList.remove('show');
        }
        
        document.body.classList.remove('sidebar-open');
    }
    
    dispatchEvent(eventName) {
        const event = new CustomEvent(eventName, {
            detail: {
                sidebar: this.sidebar,
                isOpen: this.isOpen,
                isMobile: this.isMobile
            }
        });
        document.dispatchEvent(event);
    }
    
    // Public API methods
    isOpened() {
        return this.isOpen;
    }
    
    isMobileMode() {
        return this.isMobile;
    }
    
    destroy() {
        // Remove event listeners
        if (this.toggleBtn) {
            this.toggleBtn.removeEventListener('click', this.toggle);
        }
        
        if (this.closeBtn) {
            this.closeBtn.removeEventListener('click', this.close);
        }
        
        if (this.overlay) {
            this.overlay.removeEventListener('click', this.close);
        }
        
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('keydown', this.handleEscape);
        
        // Clean up
        this.forceClose();
        
        console.log('Modern Sidebar destroyed');
    }
}

// Global functions for backward compatibility
let modernSidebarInstance = null;

function initModernSidebar() {
    if (!modernSidebarInstance) {
        modernSidebarInstance = new ModernSidebar();
    }
    return modernSidebarInstance;
}

function toggleSidebar() {
    if (modernSidebarInstance) {
        modernSidebarInstance.toggle();
    } else {
        console.warn('Modern Sidebar not initialized');
    }
}

function openSidebar() {
    if (modernSidebarInstance) {
        modernSidebarInstance.open();
    }
}

function closeSidebar() {
    if (modernSidebarInstance) {
        modernSidebarInstance.close();
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initModernSidebar();
});

// Also initialize if script is loaded after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModernSidebar);
} else {
    initModernSidebar();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ModernSidebar, initModernSidebar, toggleSidebar, openSidebar, closeSidebar };
}

// AMD support
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return { ModernSidebar, initModernSidebar, toggleSidebar, openSidebar, closeSidebar };
    });
}

} // End of ModernSidebar guard
