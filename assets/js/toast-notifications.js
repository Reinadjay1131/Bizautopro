/**
 * Toast Notification System
 * Modern, accessible toast notifications to replace alert()
 * 
 * Usage:
 *   Toast.success('Operation completed successfully!');
 *   Toast.error('Something went wrong');
 *   Toast.info('Here is some information');
 *   Toast.warning('Please be careful');
 */

class ToastNotification {
    constructor() {
        this.container = null;
        this.init();
    }
    
    init() {
        // Create toast container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
            
            // Add styles
            this.injectStyles();
        } else {
            this.container = document.getElementById('toast-container');
        }
    }
    
    injectStyles() {
        if (document.getElementById('toast-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            }
            
            .toast {
                background: white;
                border-radius: 8px;
                padding: 16px 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 300px;
                animation: toastSlideIn 0.3s ease-out;
                border-left: 4px solid #000;
                position: relative;
                overflow: hidden;
            }
            
            .toast.removing {
                animation: toastSlideOut 0.3s ease-out;
            }
            
            @keyframes toastSlideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes toastSlideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
            
            .toast-icon {
                font-size: 24px;
                flex-shrink: 0;
            }
            
            .toast-content {
                flex: 1;
            }
            
            .toast-title {
                font-weight: 600;
                margin: 0 0 4px 0;
                font-size: 14px;
            }
            
            .toast-message {
                margin: 0;
                font-size: 13px;
                color: #666;
                line-height: 1.4;
            }
            
            .toast-close {
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                color: #999;
                flex-shrink: 0;
            }
            
            .toast-close:hover {
                background: rgba(0,0,0,0.05);
                color: #333;
            }
            
            .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: rgba(0,0,0,0.2);
                animation: toastProgress linear;
            }
            
            @keyframes toastProgress {
                from { width: 100%; }
                to { width: 0%; }
            }
            
            /* Toast Types */
            .toast.success {
                border-left-color: #28a745;
            }
            
            .toast.success .toast-icon {
                color: #28a745;
            }
            
            .toast.error {
                border-left-color: #dc3545;
            }
            
            .toast.error .toast-icon {
                color: #dc3545;
            }
            
            .toast.warning {
                border-left-color: #ffc107;
            }
            
            .toast.warning .toast-icon {
                color: #ffc107;
            }
            
            .toast.info {
                border-left-color: #17a2b8;
            }
            
            .toast.info .toast-icon {
                color: #17a2b8;
            }
            
            /* Mobile Responsive */
            @media (max-width: 768px) {
                .toast-container {
                    left: 10px;
                    right: 10px;
                    max-width: none;
                }
                
                .toast {
                    min-width: auto;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    show(message, type = 'info', duration = 4000, title = null) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        
        const icon = icons[type] || icons.info;
        const toastTitle = title || titles[type];
        
        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${toastTitle}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
        `;
        
        this.container.appendChild(toast);
        
        // Auto remove after duration
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }, duration);
        
        return toast;
    }
    
    success(message, title = null, duration = 4000) {
        return this.show(message, 'success', duration, title);
    }
    
    error(message, title = null, duration = 5000) {
        return this.show(message, 'error', duration, title);
    }
    
    warning(message, title = null, duration = 4500) {
        return this.show(message, 'warning', duration, title);
    }
    
    info(message, title = null, duration = 4000) {
        return this.show(message, 'info', duration, title);
    }
    
    // Replace native alert
    replaceAlerts() {
        window.nativeAlert = window.alert;
        window.alert = (message) => {
            this.info(message);
        };
    }
}

// Initialize Toast system
const Toast = new ToastNotification();

// Optionally replace native alerts (uncomment to enable)
// Toast.replaceAlerts();

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Toast;
}
