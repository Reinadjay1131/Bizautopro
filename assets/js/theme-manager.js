/**
 * BizAutoPro Theme Manager
 * Intelligent day/night theme switching system
 */

class ThemeManager {
    constructor() {
        this.currentTheme = this.getStoredTheme() || this.getSystemPreference();
        this.init();
    }

    init() {
        this.createThemeToggle();
        this.applyTheme(this.currentTheme);
        this.setupEventListeners();
        this.watchSystemPreference();
    }

    getSystemPreference() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    getStoredTheme() {
        return localStorage.getItem('bizautopro-theme');
    }

    storeTheme(theme) {
        localStorage.setItem('bizautopro-theme', theme);
    }

    createThemeToggle() {
        // Create theme toggle button
        const themeToggle = document.createElement('button');
        themeToggle.id = 'theme-toggle';
        themeToggle.className = 'theme-toggle-btn';
        themeToggle.setAttribute('aria-label', 'Toggle theme');
        themeToggle.innerHTML = this.getToggleIcon(this.currentTheme);

        // Find the navigation user section and add the toggle
        const navUser = document.querySelector('.nav-user');
        if (navUser) {
            navUser.insertBefore(themeToggle, navUser.firstChild);
        }

        // Add CSS for the toggle button
        this.addThemeToggleStyles();
    }

    getToggleIcon(theme) {
        if (theme === 'dark') {
            return '<i class="bi bi-sun-fill"></i>';
        } else {
            return '<i class="bi bi-moon-fill"></i>';
        }
    }

    addThemeToggleStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .theme-toggle-btn {
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 6px;
                padding: 0.5rem;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-right: 1rem;
                font-size: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
            }
            
            .theme-toggle-btn:hover {
                background: rgba(255, 255, 255, 0.2);
                transform: scale(1.05);
            }
            
            .theme-toggle-btn:active {
                transform: scale(0.95);
            }

            /* Dark theme styles */
            [data-theme="dark"] {
                --bg-primary: #1a1a1a;
                --bg-secondary: #2d2d2d;
                --bg-card: #333333;
                --text-primary: #ffffff;
                --text-secondary: #e0e0e0;
                --text-light: #b0b0b0;
                --border-color: #404040;
                --shadow-color: rgba(0, 0, 0, 0.3);
                --primary-color: #6366f1;
                --primary-dark: #4f46e5;
                --secondary-color: #64748b;
                --secondary-dark: #475569;
                --success-color: #22c55e;
                --warning-color: #f59e0b;
                --danger-color: #ef4444;
                --info-color: #3b82f6;
            }

            /* Light theme styles (default) */
            [data-theme="light"] {
                --bg-primary: #ffffff;
                --bg-secondary: #f8fafc;
                --bg-card: #ffffff;
                --text-primary: #1e293b;
                --text-secondary: #334155;
                --text-light: #64748b;
                --border-color: #e2e8f0;
                --shadow-color: rgba(0, 0, 0, 0.1);
                --primary-color: #6366f1;
                --primary-dark: #4f46e5;
                --secondary-color: #64748b;
                --secondary-dark: #475569;
                --success-color: #22c55e;
                --warning-color: #f59e0b;
                --danger-color: #ef4444;
                --info-color: #3b82f6;
            }

            /* Apply theme to body and main elements */
            body {
                background-color: var(--bg-primary);
                color: var(--text-primary);
                transition: background-color 0.3s ease, color 0.3s ease;
            }

            /* Navigation themes */
            .modern-navbar {
                background: var(--primary-color) !important;
                border-bottom: 1px solid var(--border-color);
            }

            /* Card themes */
            .modern-card, .analytics-card {
                background: var(--bg-card) !important;
                border: 1px solid var(--border-color);
                box-shadow: 0 2px 4px var(--shadow-color) !important;
                color: var(--text-primary);
            }

            .modern-card-header, .analytics-header {
                border-bottom: 1px solid var(--border-color);
                color: var(--text-primary);
            }

            .modern-card-title, .chart-title {
                color: var(--text-primary) !important;
            }

            .chart-subtitle {
                color: var(--text-light) !important;
            }

            /* Form elements */
            .date-filter, input, select, textarea {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                color: var(--text-primary);
            }

            .date-filter:focus, input:focus, select:focus, textarea:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            }

            /* Activity list items */
            .activity-item {
                border-bottom: 1px solid var(--border-color);
                color: var(--text-primary);
            }

            .activity-title {
                color: var(--text-primary);
            }

            .activity-description {
                color: var(--text-secondary);
            }

            /* Page header */
            .page-header {
                background: var(--bg-secondary);
                border-bottom: 1px solid var(--border-color);
            }

            .page-subtitle {
                color: var(--text-light) !important;
            }

            /* Buttons - preserve gradients but adjust for themes */
            .btn-modern {
                border: 1px solid var(--border-color);
                transition: all 0.3s ease;
            }

            .btn-modern.btn-outline {
                background: transparent;
                color: var(--text-primary);
                border: 1px solid var(--border-color);
            }

            .btn-modern.btn-outline:hover {
                background: var(--bg-secondary);
            }

            /* Export button theme */
            .export-btn {
                background: var(--secondary-color) !important;
            }

            .export-btn:hover {
                background: var(--secondary-dark) !important;
            }

            /* Table themes */
            table {
                background: var(--bg-card);
                color: var(--text-primary);
            }

            thead th {
                background: var(--bg-secondary);
                color: var(--text-primary);
                border-bottom: 1px solid var(--border-color);
            }

            tbody td {
                border-bottom: 1px solid var(--border-color);
            }

            tbody tr:hover {
                background: var(--bg-secondary);
            }

            /* Footer theme */
            footer {
                background: var(--bg-secondary) !important;
                border-top: 1px solid var(--border-color) !important;
                color: var(--text-light) !important;
            }

            /* Chart containers - ensure proper background */
            .chart-container {
                background: transparent;
            }

            /* Dark theme specific adjustments */
            [data-theme="dark"] .modern-navbar {
                background: #1e293b !important;
            }

            [data-theme="dark"] .metric-card {
                background: linear-gradient(135deg, #4f46e5, #3730a3) !important;
            }

            [data-theme="dark"] .stat-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                color: var(--text-primary);
            }

            [data-theme="dark"] .stat-number {
                color: var(--primary-color);
            }

            [data-theme="dark"] .stat-label {
                color: var(--text-secondary);
            }

            /* Smooth transitions for theme switching */
            * {
                transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
            }

            /* Preserve chart readability in both themes */
            [data-theme="dark"] canvas {
                filter: brightness(1.1);
            }
        `;
        document.head.appendChild(style);
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;
        this.storeTheme(theme);
        
        // Update toggle button icon
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = this.getToggleIcon(theme);
        }

        // Update chart colors if charts exist
        this.updateChartThemes(theme);
        
        // Dispatch theme change event for other components
        document.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme: theme } 
        }));
        
        // Show brief theme change notification
        this.showThemeNotification(theme);
    }

    showThemeNotification(theme) {
        // Create a subtle notification
        const notification = document.createElement('div');
        notification.textContent = `Switched to ${theme} theme`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px var(--shadow-color);
            z-index: 10000;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateX(100%);
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Animate out and remove
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 2000);
    }

    updateChartThemes(theme) {
        // Update Chart.js default colors based on theme
        if (typeof Chart !== 'undefined') {
            const isDark = theme === 'dark';
            
            Chart.defaults.color = isDark ? '#e0e0e0' : '#374151';
            Chart.defaults.borderColor = isDark ? '#404040' : '#e5e7eb';
            Chart.defaults.backgroundColor = isDark ? '#2d2d2d' : '#ffffff';

            // Update existing charts if they exist
            if (window.analytics && window.analytics.charts) {
                Object.values(window.analytics.charts).forEach(chart => {
                    if (chart && chart.update) {
                        chart.update('none'); // Update without animation
                    }
                });
            }

            if (window.personalAnalytics && window.personalAnalytics.charts) {
                Object.values(window.personalAnalytics.charts).forEach(chart => {
                    if (chart && chart.update) {
                        chart.update('none'); // Update without animation
                    }
                });
            }
        }
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }

    setupEventListeners() {
        // Theme toggle button
        document.addEventListener('click', (e) => {
            if (e.target.closest('#theme-toggle')) {
                this.toggleTheme();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
            
            // Ctrl/Cmd + Shift + D for dark theme
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                this.applyTheme('dark');
            }
            
            // Ctrl/Cmd + Shift + L for light theme  
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                this.applyTheme('light');
            }
            
            // Ctrl/Cmd + Shift + A for auto theme (time-based)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'A') {
                e.preventDefault();
                this.setAutoTheme();
            }
        });
    }

    watchSystemPreference() {
        // Watch for system theme changes
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            // Only auto-switch if user hasn't manually set a preference
            const storedTheme = this.getStoredTheme();
            if (!storedTheme) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    // Public method to set theme programmatically
    setTheme(theme) {
        if (theme === 'light' || theme === 'dark') {
            this.applyTheme(theme);
        }
    }

    // Public method to get current theme
    getCurrentTheme() {
        return this.currentTheme;
    }

    // Auto theme based on time of day
    setAutoTheme() {
        const hour = new Date().getHours();
        const isDayTime = hour >= 6 && hour < 18; // 6 AM to 6 PM is day
        this.applyTheme(isDayTime ? 'light' : 'dark');
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.themeManager = new ThemeManager();
});

// Export for global access
window.ThemeManager = ThemeManager;