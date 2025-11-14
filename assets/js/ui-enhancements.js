/**
 * UI Enhancements Library
 * Includes: Loading spinners, confirmation modals, tooltips
 */

// ==================== LOADING SPINNER ====================
class LoadingSpinner {
    constructor() {
        this.overlay = null;
        this.init();
    }
    
    init() {
        if (document.getElementById('loading-overlay')) return;
        
        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.id = 'loading-overlay';
        this.overlay.className = 'loading-overlay';
        this.overlay.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <div class="loading-text">Loading...</div>
            </div>
        `;
        document.body.appendChild(this.overlay);
        
        this.injectStyles();
    }
    
    injectStyles() {
        if (document.getElementById('loading-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'loading-styles';
        style.textContent = `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                backdrop-filter: blur(4px);
            }
            
            .loading-overlay.active {
                display: flex;
            }
            
            .loading-spinner {
                text-align: center;
                color: white;
            }
            
            .spinner {
                border: 4px solid rgba(255, 255, 255, 0.3);
                border-top: 4px solid #fff;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .loading-text {
                font-size: 1rem;
                font-weight: 500;
            }
        `;
        document.head.appendChild(style);
    }
    
    show(text = 'Loading...') {
        this.overlay.querySelector('.loading-text').textContent = text;
        this.overlay.classList.add('active');
    }
    
    hide() {
        this.overlay.classList.remove('active');
    }
}

const Loading = new LoadingSpinner();

// ==================== CONFIRMATION MODAL ====================
class ConfirmationModal {
    constructor() {
        this.modal = null;
        this.callback = null;
        this.init();
    }
    
    init() {
        if (document.getElementById('confirm-modal')) return;
        
        this.modal = document.createElement('div');
        this.modal.id = 'confirm-modal';
        this.modal.className = 'confirm-modal';
        this.modal.innerHTML = `
            <div class="confirm-overlay"></div>
            <div class="confirm-dialog">
                <div class="confirm-header">
                    <h5 class="confirm-title">Confirm Action</h5>
                </div>
                <div class="confirm-body">
                    <div class="confirm-icon">⚠</div>
                    <p class="confirm-message">Are you sure you want to proceed?</p>
                </div>
                <div class="confirm-footer">
                    <button class="btn-confirm-cancel">Cancel</button>
                    <button class="btn-confirm-ok">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
        
        this.injectStyles();
        this.attachEvents();
    }
    
    injectStyles() {
        if (document.getElementById('confirm-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'confirm-styles';
        style.textContent = `
            .confirm-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }
            
            .confirm-modal.active {
                display: flex;
            }
            
            .confirm-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
            }
            
            .confirm-dialog {
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 90%;
                position: relative;
                animation: confirmSlideIn 0.3s ease-out;
            }
            
            @keyframes confirmSlideIn {
                from {
                    transform: translateY(-50px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .confirm-header {
                padding: 1.5rem;
                border-bottom: 1px solid #e9ecef;
            }
            
            .confirm-title {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: #333;
            }
            
            .confirm-body {
                padding: 2rem 1.5rem;
                text-align: center;
            }
            
            .confirm-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            
            .confirm-icon.warning { color: #ffc107; }
            .confirm-icon.danger { color: #dc3545; }
            .confirm-icon.info { color: #17a2b8; }
            
            .confirm-message {
                margin: 0;
                font-size: 1rem;
                color: #666;
                line-height: 1.5;
            }
            
            .confirm-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid #e9ecef;
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
            }
            
            .confirm-footer button {
                padding: 0.625rem 1.5rem;
                border: none;
                border-radius: 6px;
                font-size: 0.95rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                min-width: 100px;
            }
            
            .btn-confirm-cancel {
                background: #6c757d;
                color: white;
            }
            
            .btn-confirm-cancel:hover {
                background: #5a6268;
            }
            
            .btn-confirm-ok {
                background: #dc3545;
                color: white;
            }
            
            .btn-confirm-ok:hover {
                background: #c82333;
            }
            
            .btn-confirm-ok.primary {
                background: #007bff;
            }
            
            .btn-confirm-ok.primary:hover {
                background: #0056b3;
            }
        `;
        document.head.appendChild(style);
    }
    
    attachEvents() {
        const overlay = this.modal.querySelector('.confirm-overlay');
        const cancelBtn = this.modal.querySelector('.btn-confirm-cancel');
        const okBtn = this.modal.querySelector('.btn-confirm-ok');
        
        overlay.addEventListener('click', () => this.hide());
        cancelBtn.addEventListener('click', () => this.hide());
        okBtn.addEventListener('click', () => this.confirm());
    }
    
    show(options = {}) {
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            icon = 'warning',
            okText = 'Confirm',
            cancelText = 'Cancel',
            okClass = '',
            onConfirm = null
        } = options;
        
        this.callback = onConfirm;
        
        this.modal.querySelector('.confirm-title').textContent = title;
        this.modal.querySelector('.confirm-message').textContent = message;
        this.modal.querySelector('.btn-confirm-ok').textContent = okText;
        this.modal.querySelector('.btn-confirm-cancel').textContent = cancelText;
        
        const iconElement = this.modal.querySelector('.confirm-icon');
        iconElement.className = `confirm-icon ${icon}`;
        iconElement.textContent = icon === 'danger' ? '🗑' : icon === 'warning' ? '⚠' : 'ℹ';
        
        const okBtn = this.modal.querySelector('.btn-confirm-ok');
        okBtn.className = `btn-confirm-ok ${okClass}`;
        
        this.modal.classList.add('active');
    }
    
    hide() {
        this.modal.classList.remove('active');
        this.callback = null;
    }
    
    confirm() {
        if (typeof this.callback === 'function') {
            this.callback();
        }
        this.hide();
    }
}

const Confirm = new ConfirmationModal();

// ==================== TOOLTIP SYSTEM ====================
class TooltipSystem {
    constructor() {
        this.tooltip = null;
        this.init();
    }
    
    init() {
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'custom-tooltip';
        document.body.appendChild(this.tooltip);
        
        this.injectStyles();
        this.attachGlobalListeners();
    }
    
    injectStyles() {
        if (document.getElementById('tooltip-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'tooltip-styles';
        style.textContent = `
            .custom-tooltip {
                position: absolute;
                background: #333;
                color: white;
                padding: 0.5rem 0.75rem;
                border-radius: 6px;
                font-size: 0.85rem;
                pointer-events: none;
                z-index: 10002;
                opacity: 0;
                transition: opacity 0.2s ease;
                max-width: 300px;
                word-wrap: break-word;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            }
            
            .custom-tooltip.active {
                opacity: 1;
            }
            
            .custom-tooltip::before {
                content: '';
                position: absolute;
                top: -5px;
                left: 50%;
                transform: translateX(-50%);
                border-left: 5px solid transparent;
                border-right: 5px solid transparent;
                border-bottom: 5px solid #333;
            }
        `;
        document.head.appendChild(style);
    }
    
    attachGlobalListeners() {
        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                this.show(target, target.getAttribute('data-tooltip'));
            }
        });
        
        document.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                this.hide();
            }
        });
    }
    
    show(element, text) {
        this.tooltip.textContent = text;
        this.tooltip.classList.add('active');
        
        const rect = element.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();
        
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 10;
        
        // Keep tooltip within viewport
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        
        this.tooltip.style.left = left + 'px';
        this.tooltip.style.top = top + 'px';
    }
    
    hide() {
        this.tooltip.classList.remove('active');
    }
}

const Tooltips = new TooltipSystem();

// ==================== FORM ENHANCEMENT ====================
// Auto-add loading spinner to forms
document.addEventListener('DOMContentLoaded', function() {
    // Add loading to forms
    document.querySelectorAll('form[data-loading]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const loadingText = this.getAttribute('data-loading') || 'Processing...';
            Loading.show(loadingText);
        });
    });
    
    // Add confirmation to delete/critical buttons
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm');
            const title = this.getAttribute('data-confirm-title') || 'Confirm Action';
            const icon = this.getAttribute('data-confirm-icon') || 'warning';
            
            Confirm.show({
                title: title,
                message: message,
                icon: icon,
                onConfirm: () => {
                    if (this.tagName === 'A') {
                        window.location.href = this.href;
                    } else if (this.tagName === 'BUTTON' && this.form) {
                        this.form.submit();
                    } else if (this.onclick) {
                        this.onclick();
                    }
                }
            });
        });
    });
});

// Export for global use
window.Loading = Loading;
window.Confirm = Confirm;
window.Tooltips = Tooltips;
