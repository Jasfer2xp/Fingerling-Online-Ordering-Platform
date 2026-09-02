/**
 * Dark Mode System - JavaScript
 * Handles theme switching, user preferences, and instant updates
 * Supports all three roles with persistent storage
 */

// Prevent duplicate declarations
if (typeof DarkModeManager === 'undefined') {
class DarkModeManager {
    constructor() {
        this.currentTheme = 'light';
        this.storageKey = 'fingerling-marketplace-theme';
        this.toggleButtons = [];
        this.isInitialized = false;
        
        this.init();
    }
    
    init() {
        // Load saved theme or detect system preference
        this.loadTheme();
        
        // Apply theme immediately
        this.applyTheme(this.currentTheme, false);
        
        // Find and setup toggle buttons
        this.setupToggleButtons();
        
        // Listen for system theme changes
        this.setupSystemThemeListener();
        
        // Mark as initialized
        this.isInitialized = true;
        
        console.log(`Dark Mode Manager initialized with theme: ${this.currentTheme}`);
    }
    
    loadTheme() {
        // Try to load from localStorage first
        const savedTheme = localStorage.getItem(this.storageKey);
        
        if (savedTheme && (savedTheme === 'light' || savedTheme === 'dark')) {
            this.currentTheme = savedTheme;
            return;
        }
        
        // Fall back to system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.currentTheme = 'dark';
        } else {
            this.currentTheme = 'light';
        }
    }
    
    saveTheme() {
        try {
            localStorage.setItem(this.storageKey, this.currentTheme);
        } catch (error) {
            console.warn('Could not save theme preference:', error);
        }
    }
    
    applyTheme(theme, animate = true) {
        const html = document.documentElement;
        const body = document.body;
        
        // Disable transitions during theme switch to prevent flashing
        if (!animate) {
            body.classList.add('theme-switching');
        }
        
        // Set theme attribute
        html.setAttribute('data-theme', theme);
        
        // Update current theme
        this.currentTheme = theme;
        
        // Update toggle buttons
        this.updateToggleButtons();
        
        // Update meta theme-color for mobile browsers
        this.updateMetaThemeColor(theme);
        
        // Re-enable transitions after a short delay
        if (!animate) {
            setTimeout(() => {
                body.classList.remove('theme-switching');
            }, 50);
        }
        
        // Dispatch custom event
        this.dispatchThemeChangeEvent(theme);
        
        console.log(`Theme applied: ${theme}`);
    }
    
    updateMetaThemeColor(theme) {
        let themeColorMeta = document.querySelector('meta[name="theme-color"]');
        
        if (!themeColorMeta) {
            themeColorMeta = document.createElement('meta');
            themeColorMeta.name = 'theme-color';
            document.head.appendChild(themeColorMeta);
        }
        
        // Set appropriate theme color based on current theme and role
        const role = document.documentElement.getAttribute('data-role') || 'admin';
        
        if (theme === 'dark') {
            switch (role) {
                case 'admin':
                    themeColorMeta.content = '#1e40af';
                    break;
                case 'supplier':
                    themeColorMeta.content = '#047857';
                    break;
                case 'customer':
                    themeColorMeta.content = '#dc2626';
                    break;
                default:
                    themeColorMeta.content = '#1e293b';
            }
        } else {
            switch (role) {
                case 'admin':
                    themeColorMeta.content = '#1e40af';
                    break;
                case 'supplier':
                    themeColorMeta.content = '#059669';
                    break;
                case 'customer':
                    themeColorMeta.content = '#ea580c';
                    break;
                default:
                    themeColorMeta.content = '#ffffff';
            }
        }
    }
    
    setupToggleButtons() {
        // Find all dark mode toggle buttons
        this.toggleButtons = document.querySelectorAll('.dark-mode-toggle, [data-toggle="dark-mode"]');
        
        // If no toggle buttons found, create one
        if (this.toggleButtons.length === 0) {
            this.createDefaultToggleButton();
        }
        
        // Setup event listeners
        this.toggleButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        });
        
        // Update button states
        this.updateToggleButtons();
    }
    
    createDefaultToggleButton() {
        // Find a suitable container (navbar, header, etc.)
        const container = document.querySelector('.navbar-nav') || 
                         document.querySelector('.navbar') ||
                         document.querySelector('header') ||
                         document.body;
        
        if (!container) return;
        
        // Create toggle button
        const toggleButton = document.createElement('button');
        toggleButton.className = 'dark-mode-toggle';
        toggleButton.setAttribute('data-toggle', 'dark-mode');
        toggleButton.setAttribute('title', 'Toggle Dark Mode');
        toggleButton.innerHTML = `
            <span class="dark-mode-toggle-icon sun">
                <i class="fas fa-sun"></i>
            </span>
            <span class="dark-mode-toggle-icon moon">
                <i class="fas fa-moon"></i>
            </span>
            <span class="dark-mode-toggle-text">Dark Mode</span>
        `;
        
        // Add to container
        if (container.classList.contains('navbar-nav')) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(toggleButton);
            container.appendChild(li);
        } else {
            container.appendChild(toggleButton);
        }
        
        // Add to toggle buttons array
        this.toggleButtons = [toggleButton];
    }
    
    updateToggleButtons() {
        this.toggleButtons.forEach(button => {
            if (this.currentTheme === 'dark') {
                button.classList.add('active');
                button.setAttribute('aria-pressed', 'true');
                
                const text = button.querySelector('.dark-mode-toggle-text');
                if (text) text.textContent = 'Light Mode';
            } else {
                button.classList.remove('active');
                button.setAttribute('aria-pressed', 'false');
                
                const text = button.querySelector('.dark-mode-toggle-text');
                if (text) text.textContent = 'Dark Mode';
            }
        });
    }
    
    setupSystemThemeListener() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addEventListener('change', (e) => {
                // Only auto-switch if user hasn't manually set a preference
                const savedTheme = localStorage.getItem(this.storageKey);
                if (!savedTheme) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme(newTheme);
                }
            });
        }
    }
    
    dispatchThemeChangeEvent(theme) {
        const event = new CustomEvent('themeChanged', {
            detail: {
                theme: theme,
                previousTheme: this.currentTheme === 'dark' ? 'light' : 'dark'
            }
        });
        document.dispatchEvent(event);
    }
    
    // Public API methods
    toggle() {
        const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }
    
    setTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            console.warn(`Invalid theme: ${theme}. Must be 'light' or 'dark'.`);
            return;
        }
        
        this.applyTheme(theme);
        this.saveTheme();
    }
    
    getTheme() {
        return this.currentTheme;
    }
    
    isDark() {
        return this.currentTheme === 'dark';
    }
    
    isLight() {
        return this.currentTheme === 'light';
    }
    
    // Reset to system preference
    resetToSystem() {
        localStorage.removeItem(this.storageKey);
        this.loadTheme();
        this.applyTheme(this.currentTheme);
    }
    
    // Destroy instance
    destroy() {
        this.toggleButtons.forEach(button => {
            button.removeEventListener('click', this.toggle);
        });
        
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.removeEventListener('change', this.setupSystemThemeListener);
        }
        
        console.log('Dark Mode Manager destroyed');
    }
}

// Global instance
let darkModeManager = null;

// Global functions for backward compatibility
function initDarkMode() {
    if (!darkModeManager) {
        darkModeManager = new DarkModeManager();
    }
    return darkModeManager;
}

function toggleDarkMode() {
    if (darkModeManager) {
        darkModeManager.toggle();
    } else {
        console.warn('Dark Mode Manager not initialized');
    }
}

function setTheme(theme) {
    if (darkModeManager) {
        darkModeManager.setTheme(theme);
    }
}

function getTheme() {
    return darkModeManager ? darkModeManager.getTheme() : 'light';
}

function isDarkMode() {
    return darkModeManager ? darkModeManager.isDark() : false;
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initDarkMode();
});

// Also initialize if script is loaded after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDarkMode);
} else {
    initDarkMode();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        DarkModeManager, 
        initDarkMode, 
        toggleDarkMode, 
        setTheme, 
        getTheme, 
        isDarkMode 
    };
}

// AMD support
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return { 
            DarkModeManager, 
            initDarkMode, 
            toggleDarkMode, 
            setTheme, 
            getTheme, 
            isDarkMode 
        };
    });
}

} // End of DarkModeManager guard
