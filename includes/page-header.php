<?php
/**
 * Responsive Page Header Component
 * Include this file to add a consistent responsive header to any page
 * Usage: require 'includes/page-header.php';
 */

if (!isset($_SESSION['user_id'])) {
    return; // Don't render header if not logged in
}

$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'employee';

// Get page title based on current file
$current_file = basename($_SERVER['PHP_SELF']);
$page_titles = [
    'reports.php' => 'Analytics & Reports',
    'receipts.php' => 'Transaction Receipts',
    'inventory.php' => 'Inventory Management',
    'module_deduction.php' => 'Point of Sale',
    'leads.php' => 'Lead Management',
    'workflows.php' => 'Workflow Management',
    'notifications.php' => 'Notifications',
    'settings.php' => 'System Settings',
    'outbound.php' => 'Outbound Operations',
    'login.php' => 'Login',
    'register.php' => 'Register',
    'index.php' => 'BizAutoPro',
];

$page_title = $page_titles[$current_file] ?? 'BizAutoPro';
?>

<style>
/* Responsive Header Styles */
.page-header-component {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 999;
    padding: 1rem 0;
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: #1f2937;
    font-weight: 700;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.brand-logo:hover {
    color: #3b82f6;
    text-decoration: none;
    transform: translateY(-2px);
}

.brand-logo i {
    font-size: 2rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-title-section {
    flex: 1;
    min-width: 200px;
}

.page-title-main {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.page-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0.25rem 0 0 0;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.user-badge {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-weight: 500;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.back-button {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.back-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    color: white;
    text-decoration: none;
}

.logout-button {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.logout-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    color: white;
    text-decoration: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.75rem;
    }
    
    .page-title-section {
        order: 2;
        text-align: center;
    }
    
    .header-actions {
        order: 3;
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .brand-logo {
        order: 1;
        font-size: 1.25rem;
    }
    
    .page-title-main {
        font-size: 1.25rem;
    }
    
    .user-badge, .back-button, .logout-button {
        padding: 0.625rem 1rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .header-content {
        padding: 0 0.5rem;
    }
    
    .header-actions {
        flex-direction: column;
        width: 100%;
        gap: 0.5rem;
    }
    
    .user-badge, .back-button, .logout-button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="page-header-component">
    <div class="header-content">
        <!-- Brand Logo -->
        <a href="<?= $user_role === 'admin' ? 'dashboard.php' : 'dashboard_me.php' ?>" class="brand-logo">
            <i class="bi bi-grid-3x3-gap-fill"></i>
            <span>BizAutoPro</span>
        </a>
        
        <!-- Page Title Section -->
        <div class="page-title-section">
            <h1 class="page-title-main"><?= $page_title ?></h1>
            <?php if ($current_file !== 'index.php'): ?>
            <p class="page-subtitle">Business automation and management platform</p>
            <?php endif; ?>
        </div>
        
        <!-- Header Actions -->
        <div class="header-actions">
            <?php if ($current_file === 'reports.php'): ?>
            <a href="<?= $user_role === 'admin' ? 'dashboard.php' : 'dashboard_me.php' ?>" class="back-button">
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
            <?php endif; ?>
            
            <div class="user-badge">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($username) ?> (<?= ucfirst($user_role) ?>)
            </div>
            
            <a href="logout.php" class="logout-button">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </div>
</div>