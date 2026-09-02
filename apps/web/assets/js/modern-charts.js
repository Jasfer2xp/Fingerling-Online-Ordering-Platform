/**
 * Modern Chart.js Integration System
 * Responsive charts with role-based theming and dark mode support
 * Auto-resizing and alignment with modern dashboard components
 */

// Prevent duplicate class declaration
if (typeof window.ModernChartManager === 'undefined') {

class ModernChartManager {
    constructor() {
        this.charts = new Map();
        this.defaultOptions = {};
        this.roleThemes = {};
        this.currentTheme = 'light';
        this.currentRole = 'admin';
        
        this.init();
    }
    
    init() {
        // Detect current theme and role
        this.detectThemeAndRole();
        
        // Setup role-based themes
        this.setupRoleThemes();
        
        // Setup default chart options
        this.setupDefaultOptions();
        
        // Listen for theme changes
        this.setupThemeListener();
        
        // Setup resize listener
        this.setupResizeListener();
        
        console.log('Modern Chart Manager initialized');
    }
    
    detectThemeAndRole() {
        const html = document.documentElement;
        this.currentTheme = html.getAttribute('data-theme') || 'light';
        this.currentRole = html.getAttribute('data-role') || 'admin';
    }
    
    setupRoleThemes() {
        this.roleThemes = {
            admin: {
                light: {
                    primary: '#1e40af',
                    secondary: '#374151',
                    success: '#059669',
                    warning: '#d97706',
                    danger: '#dc2626',
                    info: '#0891b2',
                    background: '#ffffff',
                    text: '#1f2937',
                    grid: '#e5e7eb'
                },
                dark: {
                    primary: '#3b82f6',
                    secondary: '#6b7280',
                    success: '#10b981',
                    warning: '#f59e0b',
                    danger: '#ef4444',
                    info: '#06b6d4',
                    background: '#1e293b',
                    text: '#f8fafc',
                    grid: '#374151'
                }
            },
            supplier: {
                light: {
                    primary: '#059669',
                    secondary: '#0d9488',
                    success: '#10b981',
                    warning: '#f59e0b',
                    danger: '#ef4444',
                    info: '#06b6d4',
                    background: '#ffffff',
                    text: '#1f2937',
                    grid: '#e5e7eb'
                },
                dark: {
                    primary: '#10b981',
                    secondary: '#14b8a6',
                    success: '#22c55e',
                    warning: '#fbbf24',
                    danger: '#f87171',
                    info: '#38bdf8',
                    background: '#1e293b',
                    text: '#f8fafc',
                    grid: '#374151'
                }
            },
            customer: {
                light: {
                    primary: '#ea580c',
                    secondary: '#f97316',
                    success: '#22c55e',
                    warning: '#eab308',
                    danger: '#ef4444',
                    info: '#3b82f6',
                    background: '#ffffff',
                    text: '#1f2937',
                    grid: '#e5e7eb'
                },
                dark: {
                    primary: '#fb923c',
                    secondary: '#fdba74',
                    success: '#4ade80',
                    warning: '#facc15',
                    danger: '#f87171',
                    info: '#60a5fa',
                    background: '#1e293b',
                    text: '#f8fafc',
                    grid: '#374151'
                }
            }
        };
    }
    
    setupDefaultOptions() {
        const theme = this.getCurrentTheme();
        
        this.defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: theme.text,
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            family: "'Inter', 'Segoe UI', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: theme.background,
                    titleColor: theme.text,
                    bodyColor: theme.text,
                    borderColor: theme.grid,
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                x: {
                    grid: {
                        color: theme.grid,
                        drawBorder: false,
                    },
                    ticks: {
                        color: theme.text,
                        font: {
                            size: 11,
                            family: "'Inter', 'Segoe UI', sans-serif"
                        }
                    }
                },
                y: {
                    grid: {
                        color: theme.grid,
                        drawBorder: false,
                    },
                    ticks: {
                        color: theme.text,
                        font: {
                            size: 11,
                            family: "'Inter', 'Segoe UI', sans-serif"
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6,
                    borderWidth: 2
                },
                line: {
                    borderWidth: 3,
                    tension: 0.4
                },
                bar: {
                    borderRadius: 4,
                    borderSkipped: false,
                }
            },
            animation: {
                duration: 750,
                easing: 'easeInOutQuart'
            }
        };
    }
    
    getCurrentTheme() {
        return this.roleThemes[this.currentRole][this.currentTheme];
    }
    
    setupThemeListener() {
        document.addEventListener('themeChanged', (event) => {
            this.currentTheme = event.detail.theme;
            this.updateAllCharts();
        });
    }
    
    setupResizeListener() {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.resizeAllCharts();
            }, 250);
        });
    }
    
    createChart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn(`Canvas element with ID '${canvasId}' not found`);
            return null;
        }
        
        // Merge with default options
        const mergedConfig = this.mergeConfig(config);
        
        // Create chart
        const chart = new Chart(canvas, mergedConfig);
        
        // Store chart reference
        this.charts.set(canvasId, chart);
        
        // Add loading state management
        this.setupLoadingState(canvasId, chart);
        
        console.log(`Chart created: ${canvasId}`);
        return chart;
    }
    
    mergeConfig(config) {
        const theme = this.getCurrentTheme();
        
        // Deep merge configuration
        const merged = JSON.parse(JSON.stringify(this.defaultOptions));
        
        // Merge datasets with theme colors
        if (config.data && config.data.datasets) {
            config.data.datasets = config.data.datasets.map((dataset, index) => {
                if (!dataset.borderColor) {
                    const colors = [theme.primary, theme.secondary, theme.success, theme.warning, theme.danger, theme.info];
                    dataset.borderColor = colors[index % colors.length];
                }
                if (!dataset.backgroundColor && dataset.borderColor) {
                    dataset.backgroundColor = dataset.borderColor.replace('rgb', 'rgba').replace(')', ', 0.1)');
                }
                return dataset;
            });
        }
        
        // Merge options
        this.deepMerge(merged, config);
        
        return merged;
    }
    
    deepMerge(target, source) {
        for (const key in source) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                if (!target[key]) target[key] = {};
                this.deepMerge(target[key], source[key]);
            } else {
                target[key] = source[key];
            }
        }
    }
    
    setupLoadingState(canvasId, chart) {
        const container = document.querySelector(`#${canvasId}`).closest('.modern-chart-container');
        if (!container) return;
        
        // Create loading overlay
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'modern-chart-loading';
        loadingOverlay.innerHTML = `
            <div class="modern-chart-spinner"></div>
            <span>Loading chart...</span>
        `;
        
        // Show loading initially
        container.appendChild(loadingOverlay);
        
        // Hide loading when chart is ready
        chart.options.onComplete = () => {
            setTimeout(() => {
                if (loadingOverlay.parentNode) {
                    loadingOverlay.remove();
                }
            }, 500);
        };
    }
    
    updateChart(canvasId, newData, newOptions = {}) {
        const chart = this.charts.get(canvasId);
        if (!chart) {
            console.warn(`Chart with ID '${canvasId}' not found`);
            return;
        }
        
        // Update data
        if (newData) {
            chart.data = newData;
        }
        
        // Update options
        if (Object.keys(newOptions).length > 0) {
            this.deepMerge(chart.options, newOptions);
        }
        
        // Update chart
        chart.update('active');
    }
    
    updateAllCharts() {
        this.setupDefaultOptions(); // Refresh default options with new theme
        
        this.charts.forEach((chart, canvasId) => {
            const theme = this.getCurrentTheme();
            
            // Update chart options with new theme
            chart.options.plugins.legend.labels.color = theme.text;
            chart.options.plugins.tooltip.backgroundColor = theme.background;
            chart.options.plugins.tooltip.titleColor = theme.text;
            chart.options.plugins.tooltip.bodyColor = theme.text;
            chart.options.plugins.tooltip.borderColor = theme.grid;
            
            if (chart.options.scales.x) {
                chart.options.scales.x.grid.color = theme.grid;
                chart.options.scales.x.ticks.color = theme.text;
            }
            
            if (chart.options.scales.y) {
                chart.options.scales.y.grid.color = theme.grid;
                chart.options.scales.y.ticks.color = theme.text;
            }
            
            // Update chart
            chart.update('none');
        });
    }
    
    resizeAllCharts() {
        this.charts.forEach((chart) => {
            chart.resize();
        });
    }
    
    destroyChart(canvasId) {
        const chart = this.charts.get(canvasId);
        if (chart) {
            chart.destroy();
            this.charts.delete(canvasId);
            console.log(`Chart destroyed: ${canvasId}`);
        }
    }
    
    destroyAllCharts() {
        this.charts.forEach((chart, canvasId) => {
            chart.destroy();
        });
        this.charts.clear();
        console.log('All charts destroyed');
    }
    
    // Utility methods for common chart types
    createLineChart(canvasId, data, options = {}) {
        return this.createChart(canvasId, {
            type: 'line',
            data: data,
            options: options
        });
    }
    
    createBarChart(canvasId, data, options = {}) {
        return this.createChart(canvasId, {
            type: 'bar',
            data: data,
            options: options
        });
    }
    
    createDoughnutChart(canvasId, data, options = {}) {
        return this.createChart(canvasId, {
            type: 'doughnut',
            data: data,
            options: {
                ...options,
                cutout: '60%'
            }
        });
    }
    
    createPieChart(canvasId, data, options = {}) {
        return this.createChart(canvasId, {
            type: 'pie',
            data: data,
            options: options
        });
    }
    
    // Data formatting utilities
    formatCurrency(value) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(value);
    }
    
    formatNumber(value) {
        return new Intl.NumberFormat('en-PH').format(value);
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-PH', { 
            month: 'short', 
            day: 'numeric' 
        });
    }
}

// Global instance
let modernChartManager = null;

// Global functions for easy access
function initModernCharts() {
    if (!modernChartManager) {
        modernChartManager = new ModernChartManager();
    }
    return modernChartManager;
}

function createModernChart(canvasId, config) {
    if (!modernChartManager) {
        initModernCharts();
    }
    return modernChartManager.createChart(canvasId, config);
}

function updateModernChart(canvasId, newData, newOptions = {}) {
    if (modernChartManager) {
        modernChartManager.updateChart(canvasId, newData, newOptions);
    }
}

function destroyModernChart(canvasId) {
    if (modernChartManager) {
        modernChartManager.destroyChart(canvasId);
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initModernCharts();
});

// Also initialize if script is loaded after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModernCharts);
} else {
    initModernCharts();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        ModernChartManager, 
        initModernCharts, 
        createModernChart, 
        updateModernChart, 
        destroyModernChart 
    };
}

// AMD support
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return { 
            ModernChartManager, 
            initModernCharts, 
            createModernChart, 
            updateModernChart, 
            destroyModernChart 
        };
    });
}

// Assign to window to prevent redeclaration
window.ModernChartManager = ModernChartManager;

} // End of ModernChartManager class check
