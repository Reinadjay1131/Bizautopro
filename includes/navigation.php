<?php
/**
 * Enhanced Navigation Component with Sidebar
 * Include this file in all pages after session_start() and authentication checks
 * Usage: require 'includes/navigation.php';
 */

if (!isset($_SESSION['user_id'])) {
    return; // Don't render navigation if not logged in
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'employee';
$username = $_SESSION['username'] ?? 'User';

// Define menu items based on roles
$menu_items = [
    'admin' => [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'url' => 'dashboard.php', 'badge' => null],
        ['icon' => 'bi-currency-dollar', 'text' => 'Revenue', 'url' => 'reports.php?view=revenue', 'badge' => null],
        ['icon' => 'bi-cash-register', 'text' => 'Point of Sale', 'url' => 'module_deduction.php', 'badge' => null],
        ['icon' => 'bi-boxes', 'text' => 'Inventory', 'url' => 'inventory.php', 'badge' => null],
        ['icon' => 'bi-receipt', 'text' => 'Receipts', 'url' => 'receipts.php', 'badge' => 'new'],
        ['icon' => 'bi-people-fill', 'text' => 'Leads', 'url' => 'leads.php', 'badge' => null],
        ['icon' => 'bi-diagram-3-fill', 'text' => 'Workflows', 'url' => 'workflows.php', 'badge' => null],
        ['icon' => 'bi-graph-up-arrow', 'text' => 'Analytics', 'url' => 'reports.php', 'badge' => null],
        ['icon' => 'bi-bell-fill', 'text' => 'Notifications', 'url' => 'notifications.php', 'badge' => null],
        ['icon' => 'bi-gear-fill', 'text' => 'Settings', 'url' => 'settings.php', 'badge' => null],
    ],
    'manager' => [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'url' => 'dashboard_me.php', 'badge' => null],
        ['icon' => 'bi-currency-dollar', 'text' => 'Revenue', 'url' => 'reports.php?view=revenue', 'badge' => null],
        ['icon' => 'bi-cash-register', 'text' => 'Point of Sale', 'url' => 'module_deduction.php', 'badge' => null],
        ['icon' => 'bi-boxes', 'text' => 'Inventory', 'url' => 'inventory.php', 'badge' => null],
        ['icon' => 'bi-receipt', 'text' => 'Receipts', 'url' => 'receipts.php', 'badge' => 'new'],
        ['icon' => 'bi-people-fill', 'text' => 'Leads', 'url' => 'leads.php', 'badge' => null],
        ['icon' => 'bi-diagram-3-fill', 'text' => 'Workflows', 'url' => 'workflows.php', 'badge' => null],
        ['icon' => 'bi-bell-fill', 'text' => 'Notifications', 'url' => 'notifications.php', 'badge' => null],
    ],
    'employee' => [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'url' => 'dashboard_me.php', 'badge' => null],
        ['icon' => 'bi-cash-register', 'text' => 'Point of Sale', 'url' => 'module_deduction.php', 'badge' => null],
        ['icon' => 'bi-boxes', 'text' => 'Inventory', 'url' => 'inventory.php', 'badge' => null],
        ['icon' => 'bi-receipt', 'text' => 'Receipts', 'url' => 'receipts.php', 'badge' => 'new'],
        ['icon' => 'bi-people-fill', 'text' => 'Leads', 'url' => 'leads.php', 'badge' => null],
        ['icon' => 'bi-bell-fill', 'text' => 'Notifications', 'url' => 'notifications.php', 'badge' => null],
    ],
];

$user_menu = $menu_items[$user_role] ?? $menu_items['employee'];

// Breadcrumb generation
function generate_breadcrumb($current_page) {
    $breadcrumbs = [
        'dashboard.php' => ['Dashboard'],
        'dashboard_me.php' => ['Dashboard'],
        'inventory.php' => ['Dashboard', 'Inventory'],
        'add_inventory.php' => ['Dashboard', 'Inventory', 'Add Item'],
        'edit_inventory.php' => ['Dashboard', 'Inventory', 'Edit Item'],
        'module_deduction.php' => ['Dashboard', 'Inventory', 'Deductions'],
        'receipts.php' => ['Dashboard', 'Receipts'],
        'leads.php' => ['Dashboard', 'Leads'],
        'new_lead.php' => ['Dashboard', 'Leads', 'New Lead'],
        'edit_lead.php' => ['Dashboard', 'Leads', 'Edit Lead'],
        'workflows.php' => ['Dashboard', 'Workflows'],
        'create_workflow.php' => ['Dashboard', 'Workflows', 'Create'],
        'reports.php' => ['Dashboard', 'Reports'],
        'notifications.php' => ['Dashboard', 'Notifications'],
        'settings.php' => ['Dashboard', 'Settings'],
    ];
    
    return $breadcrumbs[$current_page] ?? ['Dashboard'];
}

$breadcrumb_items = generate_breadcrumb($current_page);
?>

<!-- Enhanced Navigation Styles -->
<style>
    :root {
        --sidebar-width: 280px;
        --topbar-height: 70px;
        --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        --sidebar-hover: rgba(59, 130, 246, 0.1);
        --sidebar-active: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        --topbar-bg: #ffffff;
        --accent-color: #3b82f6;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --border-color: rgba(148, 163, 184, 0.1);
    }
    
    body {
        margin: 0;
        padding: 0;
    }
    
    .page-wrapper {
        display: flex;
        min-height: 100vh;
    }
    
    /* Sidebar Styles */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        color: var(--text-primary);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        overflow-y: auto;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        backdrop-filter: blur(10px);
    }
    

    
    .sidebar-header {
        padding: 2rem 1.5rem;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
        background: rgba(59, 130, 246, 0.05);
    }
    
    .sidebar-header h4 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    

    
    .sidebar-menu {
        list-style: none;
        padding: 1.5rem 0;
        margin: 0;
    }
    
    .sidebar-menu li {
        margin: 0.5rem 1rem;
    }
    
    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.95rem;
    }
    
    .sidebar-menu a:hover {
        background: var(--sidebar-hover);
        color: var(--text-primary);
        transform: translateX(4px);
    }
    
    .sidebar-menu a.active {
        background: var(--sidebar-active);
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .sidebar-menu a.active::before {
        content: '';
        position: absolute;
        left: -1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 100%;
        background: #60a5fa;
        border-radius: 0 4px 4px 0;
    }
    
    .sidebar-menu a i {
        font-size: 1.25rem;
        width: 24px;
        margin-right: 1rem;
        transition: all 0.3s ease;
    }
    

    
    .sidebar-menu .badge {
        margin-left: auto;
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border-radius: 12px;
        font-weight: 600;
    }
    
    /* Main Content Area */
    .main-content {
        flex: 1;
        margin-left: calc(var(--sidebar-width) - 110px);
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: 100vh;
        position: relative;
    }
    
    /* Top Bar */
    .top-bar {
        background: var(--topbar-bg);
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    /* Breadcrumb */
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .breadcrumb-nav a {
        color: var(--accent-color);
        text-decoration: none;
    }
    
    .breadcrumb-nav a:hover {
        text-decoration: underline;
    }
    
    .breadcrumb-nav .separator {
        color: #adb5bd;
    }
    
    /* User Menu */
    .user-menu {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .user-menu .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
    }
    
    /* Quick Actions Button */
    .quick-actions-btn {
        background: var(--accent-color);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    
    .quick-actions-btn:hover {
        background: #2e59d9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(78,115,223,0.3);
    }
    
    /* Content Container */
    .content-container {
        padding: 2rem 1rem 2rem 0.5rem;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .mobile-menu-btn {
            display: block !important;
        }
    }
    
    .mobile-menu-btn {
        display: none;
        background: var(--accent-color);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
</style>

<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="bi bi-grid-3x3-gap-fill"></i> <span>BizAutoPro</span></h4>
        <small style="opacity: 0.7; font-size: 0.8rem;"><?php echo ucfirst($user_role); ?></small>
    </div>
    
    <ul class="sidebar-menu">
        <?php foreach ($user_menu as $item): ?>
            <li>
                <a href="<?php echo $item['url']; ?>" class="<?php echo $current_page === $item['url'] ? 'active' : ''; ?>" 
                   title="<?php echo $item['text']; ?>">
                    <i class="bi <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['text']; ?></span>
                    <?php if ($item['badge']): ?>
                        <span class="badge bg-success"><?php echo $item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
        
        <li style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 1rem; padding-top: 1rem;">
            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- Main Content Wrapper -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="mobile-menu-btn" onclick="openMobileSidebar()">
                <i class="bi bi-list"></i> Menu
            </button>
            
            <!-- Breadcrumb -->
            <nav class="breadcrumb-nav">
                <?php foreach ($breadcrumb_items as $index => $crumb): ?>
                    <?php if ($index > 0): ?>
                        <span class="separator">/</span>
                    <?php endif; ?>
                    <?php if ($index === count($breadcrumb_items) - 1): ?>
                        <span><?php echo $crumb; ?></span>
                    <?php else: ?>
                        <a href="javascript:history.back()"><?php echo $crumb; ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <button class="quick-actions-btn" onclick="showQuickActions()">
                <i class="bi bi-lightning-charge"></i>
                <span>Quick Actions</span>
            </button>
            
            <div class="user-avatar" title="<?php echo htmlspecialchars($username); ?>">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($username); ?></strong>
                <small style="color: #6c757d; font-size: 0.8rem;"><?php echo ucfirst($user_role); ?></small>
            </div>
        </div>
    </div>
    
    <!-- Content Container -->
    <div class="content-container">
        <!-- Page content will go here -->
        <?php // Content from the including page will render after this ?>

<script>
// Mobile Sidebar
function openMobileSidebar() {
    document.getElementById('sidebar').classList.add('mobile-open');
    document.getElementById('sidebarOverlay').classList.add('active');
}

function closeMobileSidebar() {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// Quick Actions Modal
function showQuickActions() {
    // This will be implemented in the quick actions component
    if (typeof openQuickActionsModal === 'function') {
        openQuickActionsModal();
    } else {
        alert('Quick actions menu coming soon!');
    }
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for quick actions
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        showQuickActions();
    }
});
</script>
