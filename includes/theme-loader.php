<?php
/**
 * BizAutoPro Universal Theme Loader
 * Include this in all pages to enable theme switching
 */

function loadThemeSystem() {
    echo '
    <!-- Universal Theme System -->
    <script src="assets/js/theme-manager.js"></script>
    <style>
        /* Additional theme-aware styles for all pages */
        .login-container, .register-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-group label {
            color: var(--text-primary);
        }
        
        .form-control {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        
        .alert {
            border: 1px solid var(--border-color);
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border-color: var(--warning-color);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border-color: var(--info-color);
        }
        
        /* Theme-aware modals */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        /* Theme-aware dropdowns */
        .dropdown-menu {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
        }
        
        .dropdown-item {
            color: var(--text-primary);
        }
        
        .dropdown-item:hover {
            background: var(--bg-secondary);
        }
        
        /* Loading states */
        .spinner {
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--primary-color);
        }
        
        /* Theme toggle in minimal navigation */
        .simple-nav .theme-toggle-btn {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .simple-nav .theme-toggle-btn:hover {
            background: var(--bg-card);
        }
    </style>
    ';
}

function addSimpleThemeToggle() {
    echo '
    <script>
        // Add theme toggle to pages without full navigation
        document.addEventListener("DOMContentLoaded", function() {
            if (!document.querySelector(".modern-navbar") && !document.querySelector("#theme-toggle")) {
                const toggle = document.createElement("button");
                toggle.id = "theme-toggle";
                toggle.className = "theme-toggle-btn simple-nav";
                toggle.style.cssText = "position: fixed; top: 20px; right: 20px; z-index: 1000;";
                toggle.setAttribute("aria-label", "Toggle theme");
                
                const currentTheme = localStorage.getItem("bizautopro-theme") || "light";
                toggle.innerHTML = currentTheme === "dark" ? \'<i class="bi bi-sun-fill"></i>\' : \'<i class="bi bi-moon-fill"></i>\';
                
                document.body.appendChild(toggle);
            }
        });
    </script>
    ';
}
?>