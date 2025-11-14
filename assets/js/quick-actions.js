/**
 * Quick Actions Modal
 * Provides quick access to common actions via keyboard shortcut or button
 */

class QuickActionsModal {
    constructor() {
        this.modal = null;
        this.userRole = document.body.getAttribute('data-user-role') || 'employee';
        this.init();
    }
    
    init() {
        this.createModal();
        this.injectStyles();
        this.attachEvents();
    }
    
    createModal() {
        this.modal = document.createElement('div');
        this.modal.id = 'quick-actions-modal';
        this.modal.className = 'quick-actions-modal';
        this.modal.innerHTML = `
            <div class="qa-overlay"></div>
            <div class="qa-dialog">
                <div class="qa-header">
                    <h5>⚡ Quick Actions</h5>
                    <span class="qa-shortcut">Ctrl+K</span>
                    <button class="qa-close">×</button>
                </div>
                <div class="qa-search">
                    <input type="text" placeholder="Search actions..." id="qa-search-input">
                </div>
                <div class="qa-actions" id="qa-actions-list">
                    <!-- Actions will be loaded here -->
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }
    
    injectStyles() {
        if (document.getElementById('qa-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'qa-styles';
        style.textContent = `
            .quick-actions-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                display: none;
                align-items: flex-start;
                justify-content: center;
                z-index: 10003;
                padding-top: 10vh;
            }
            
            .quick-actions-modal.active {
                display: flex;
            }
            
            .qa-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
            }
            
            .qa-dialog {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.4);
                width: 600px;
                max-width: 90vw;
                max-height: 70vh;
                position: relative;
                animation: qaSlideDown 0.3s ease-out;
                display: flex;
                flex-direction: column;
            }
            
            @keyframes qaSlideDown {
                from {
                    transform: translateY(-100px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .qa-header {
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .qa-header h5 {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
            }
            
            .qa-shortcut {
                background: #f1f3f5;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-size: 0.75rem;
                font-family: monospace;
                color: #6c757d;
            }
            
            .qa-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #6c757d;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
            }
            
            .qa-close:hover {
                background: #f1f3f5;
                color: #333;
            }
            
            .qa-search {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #e9ecef;
            }
            
            .qa-search input {
                width: 100%;
                padding: 0.75rem 1rem;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                font-size: 1rem;
                transition: all 0.2s ease;
            }
            
            .qa-search input:focus {
                outline: none;
                border-color: #4e73df;
            }
            
            .qa-actions {
                padding: 0.5rem;
                overflow-y: auto;
                max-height: 50vh;
            }
            
            .qa-action-item {
                display: flex;
                align-items: center;
                padding: 0.875rem 1rem;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                color: inherit;
                gap: 1rem;
            }
            
            .qa-action-item:hover {
                background: #f8f9fa;
            }
            
            .qa-action-item.selected {
                background: #e7f3ff;
            }
            
            .qa-action-icon {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                flex-shrink: 0;
            }
            
            .qa-action-icon.primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
            .qa-action-icon.success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
            .qa-action-icon.warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); }
            .qa-action-icon.danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); }
            .qa-action-icon.info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }
            
            .qa-action-content {
                flex: 1;
            }
            
            .qa-action-title {
                font-weight: 600;
                margin: 0 0 0.25rem 0;
                font-size: 0.95rem;
            }
            
            .qa-action-desc {
                margin: 0;
                font-size: 0.8rem;
                color: #6c757d;
            }
            
            .qa-no-results {
                text-align: center;
                padding: 3rem 1rem;
                color: #6c757d;
            }
            
            @media (max-width: 768px) {
                .qa-dialog {
                    width: 95vw;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    attachEvents() {
        const overlay = this.modal.querySelector('.qa-overlay');
        const closeBtn = this.modal.querySelector('.qa-close');
        const searchInput = this.modal.querySelector('#qa-search-input');
        
        overlay.addEventListener('click', () => this.hide());
        closeBtn.addEventListener('click', () => this.hide());
        
        searchInput.addEventListener('input', (e) => this.filterActions(e.target.value));
        searchInput.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Global keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.show();
            }
            
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.hide();
            }
        });
    }
    
    getActions() {
        const commonActions = [
            { icon: 'bi-plus-circle', title: 'Add Inventory Item', desc: 'Add new product to inventory', url: 'add_inventory.php', color: 'success' },
            { icon: 'bi-dash-circle', title: 'Deduct Inventory', desc: 'Process inventory deduction', url: 'module_deduction.php', color: 'warning' },
            { icon: 'bi-receipt', title: 'View Receipts', desc: 'See all deduction receipts', url: 'receipts.php', color: 'info' },
            { icon: 'bi-person-plus', title: 'New Lead', desc: 'Add new customer lead', url: 'new_lead.php', color: 'primary' },
            { icon: 'bi-boxes', title: 'View Inventory', desc: 'Browse inventory items', url: 'inventory.php', color: 'primary' },
            { icon: 'bi-people', title: 'Manage Leads', desc: 'View and manage leads', url: 'leads.php', color: 'primary' },
        ];
        
        const adminActions = [
            { icon: 'bi-diagram-3', title: 'Create Workflow', desc: 'Set up automation workflow', url: 'create_workflow.php', color: 'success' },
            { icon: 'bi-graph-up', title: 'View Reports', desc: 'Access system reports', url: 'reports.php', color: 'info' },
            { icon: 'bi-gear', title: 'Settings', desc: 'Configure system settings', url: 'settings.php', color: 'primary' },
            { icon: 'bi-people-fill', title: 'User Management', desc: 'Manage system users', url: 'view_pending_users.php', color: 'warning' },
        ];
        
        if (this.userRole === 'admin') {
            return [...commonActions, ...adminActions];
        } else if (this.userRole === 'manager') {
            return [...commonActions, adminActions[0], adminActions[1]]; // Workflows and reports
        }
        
        return commonActions;
    }
    
    renderActions(actions = null) {
        const actionsToRender = actions || this.getActions();
        const container = this.modal.querySelector('#qa-actions-list');
        
        if (actionsToRender.length === 0) {
            container.innerHTML = `
                <div class="qa-no-results">
                    <i class="bi bi-search" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <p>No actions found</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = actionsToRender.map((action, index) => `
            <a href="${action.url}" class="qa-action-item" data-index="${index}">
                <div class="qa-action-icon ${action.color}">
                    <i class="bi ${action.icon}"></i>
                </div>
                <div class="qa-action-content">
                    <div class="qa-action-title">${action.title}</div>
                    <div class="qa-action-desc">${action.desc}</div>
                </div>
            </a>
        `).join('');
    }
    
    filterActions(query) {
        const actions = this.getActions();
        const filtered = actions.filter(action => 
            action.title.toLowerCase().includes(query.toLowerCase()) ||
            action.desc.toLowerCase().includes(query.toLowerCase())
        );
        this.renderActions(filtered);
    }
    
    handleKeyboard(e) {
        const items = this.modal.querySelectorAll('.qa-action-item');
        const selected = this.modal.querySelector('.qa-action-item.selected');
        let index = selected ? parseInt(selected.getAttribute('data-index')) : -1;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = Math.min(index + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = Math.max(index - 1, 0);
        } else if (e.key === 'Enter' && index >= 0) {
            e.preventDefault();
            items[index].click();
            return;
        } else {
            return;
        }
        
        items.forEach(item => item.classList.remove('selected'));
        if (items[index]) {
            items[index].classList.add('selected');
            items[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
    
    show() {
        this.renderActions();
        this.modal.classList.add('active');
        setTimeout(() => {
            this.modal.querySelector('#qa-search-input').focus();
        }, 100);
    }
    
    hide() {
        this.modal.classList.remove('active');
        this.modal.querySelector('#qa-search-input').value = '';
    }
}

// Initialize Quick Actions
const QuickActions = new QuickActionsModal();

// Make it globally accessible
window.openQuickActionsModal = () => QuickActions.show();
