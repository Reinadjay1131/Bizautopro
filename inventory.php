<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'config.php';

// Authorization
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'inventory_manager') {
    header("Location: dashboard.php");
    exit;
}

// Get all inventory items with supplier information
$inventory = $pdo->query("
    SELECT i.*, s.name as supplier_name 
    FROM inventory i 
    LEFT JOIN suppliers s ON i.supplier_id = s.id 
    ORDER BY i.product_name
")->fetchAll();

// Check for low stock
$low_stock = $pdo->query("SELECT * FROM inventory WHERE quantity < reorder_level")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - BizAutoPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern.css">
    <?php 
    require_once 'includes/theme-loader.php';
    loadThemeSystem();
    ?>
    <style>
        .inventory-table {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--medium-gray);
        }
        
        .table-header {
            background: var(--primary-blue);
            color: white;
            padding: var(--space-lg);
            font-weight: 600;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .table-row {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--medium-gray);
            transition: background-color 0.2s ease;
        }
        
        .table-row:hover {
            background: var(--light-gray);
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .table-cell {
            padding: var(--space-sm) 0;
            vertical-align: middle;
        }

        /* Desktop Table Layout */
        .desktop-table-layout {
            display: block;
        }

        .inventory-item {
            background: white;
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .item-header {
            background: var(--primary-blue);
            color: white;
            padding: var(--space-md) var(--space-lg);
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            padding: var(--space-lg);
        }

        .data-field {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .field-label {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .field-value {
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        /* Mobile Card Layout */
        .mobile-card-layout {
            display: none;
        }

        .inventory-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--medium-gray);
            margin-bottom: var(--space-md);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .inventory-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header {
            background: var(--primary-blue);
            color: white;
            padding: var(--space-md);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-content {
            padding: var(--space-md);
        }

        .card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .card-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .card-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .card-value {
            color: var(--text-light);
            font-size: 0.9rem;
            text-align: right;
        }

        .card-actions {
            padding: var(--space-md);
            background: var(--light-gray);
            border-top: 1px solid var(--medium-gray);
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        .card-actions .btn-modern {
            flex: 1;
            min-width: 80px;
            font-size: 0.85rem;
            padding: 8px 12px;
        }

        /* Responsive adjustments for card layout */
        @media (max-width: 900px) {
            .item-data-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 600px) {
            .item-data-grid {
                grid-template-columns: 1fr;
            }
            
            .item-header {
                flex-direction: column;
                gap: var(--space-md);
                align-items: flex-start;
            }
        }

        /* Hide mobile cards on desktop */
        @media (min-width: 769px) {
            .mobile-card-layout {
                display: none;
            }
        }

        @media (max-width: 768px) {
            /* Switch to mobile card layout */
            .desktop-table-layout {
                display: none;
            }
            
            .mobile-card-layout {
                display: block;
            }

            .table-header {
                display: none;
            }

            .inventory-table {
                background: transparent;
                box-shadow: none;
                border: none;
                border-radius: 0;
            }

            /* Mobile-specific adjustments */
            .container {
                padding: var(--space-md);
            }

            .stat-card {
                margin-bottom: var(--space-md);
            }
        }

        @media (max-width: 576px) {
            .card-actions {
                flex-direction: column;
            }

            .card-actions .btn-modern {
                width: 100%;
                margin-bottom: var(--space-xs);
            }

            .card-actions .btn-modern:last-child {
                margin-bottom: 0;
            }

            .container {
                padding: var(--space-sm);
            }

            .stat-card h2 {
                font-size: 1.5rem;
            }

            .card-header {
                font-size: 1rem;
            }
        }

        /* Touch device optimizations */
        @media (pointer: coarse) {
            .btn-modern {
                min-height: 44px;
                padding: 12px 16px;
            }

            .card-actions .btn-modern {
                min-height: 40px;
            }
        }
        
        .low-stock-alert {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
            color: #92400e;
        }
        
        .low-stock-title {
            font-weight: 600;
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        
        .low-stock-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .low-stock-item {
            padding: var(--space-xs) 0;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }
        
        .btn-loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .page-actions {
                flex-direction: column;
                gap: var(--space-md);
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: stretch;
            }
            
            .btn-modern {
                width: 100%;
            }
        }
    </style>
</head>
<body class="fade-in">
    <!-- Modern Navigation -->
    <nav class="modern-navbar">
        <div class="modern-container">
            <div class="navbar-content">
                <a href="dashboard.php" class="brand">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    BizAutoPro
                </a>
                <div class="nav-user">
                    <span>Inventory Management</span>
                    <a href="dashboard.php" class="btn-modern btn-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="modern-container">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-lg);" class="page-actions">
                <div>
                    <h1 class="page-title">Inventory Management</h1>
                    <p class="page-subtitle">Monitor stock levels, manage products, and track inventory</p>
                </div>
                <div class="action-buttons">
                    <a href="outbound.php" class="btn-modern btn-info">
                        <i class="bi bi-box-arrow-up"></i>
                        View Outbound
                    </a>
                    <a href="module_deduction.php" class="btn-modern btn-warning">
                        <i class="bi bi-box-arrow-down"></i>
                        Bulk Deduction
                    </a>
                    <a href="add_inventory.php" class="btn-modern btn-primary">
                        <i class="bi bi-plus-circle"></i>
                        Add New Item
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="modern-container">
        <?php if ($low_stock): ?>
            <div class="low-stock-alert slide-up">
                <div class="low-stock-title">
                    <i class="bi bi-exclamation-triangle"></i>
                    Low Stock Alert
                </div>
                <ul class="low-stock-list">
                    <?php foreach ($low_stock as $item): ?>
                        <li class="low-stock-item">
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong> 
                            - Only <?= $item['quantity'] ?> remaining (reorder at <?= $item['reorder_level'] ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="inventory-table slide-up">
            <!-- Inventory Items Header -->
            <div class="table-header" style="text-align: center; padding: var(--space-lg);">
                <h3 style="margin: 0; color: white;">
                    <i class="bi bi-boxes"></i>
                    Inventory Items (<?= count($inventory) ?> total)
                </h3>
            </div>

            <!-- Desktop Table Body -->
            <div class="desktop-table-layout">
                <?php if (empty($inventory)): ?>
                    <div class="table-row text-center" style="grid-column: 1 / -1; padding: var(--space-2xl);">
                        <div style="color: var(--text-light);">
                            <i class="bi bi-inbox" style="font-size: 3rem; margin-bottom: var(--space-lg); display: block;"></i>
                            <h3 style="margin-bottom: var(--space-sm);">No Inventory Items</h3>
                            <p>Start by adding your first product to the inventory.</p>
                            <a href="add_inventory.php" class="btn-modern btn-primary mt-3">
                                <i class="bi bi-plus-circle"></i>
                                Add First Item
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <div class="inventory-item">
                            <div class="item-header">
                                <div>
                                    <i class="bi bi-box-seam"></i>
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </div>
                                <div style="display: flex; gap: var(--space-sm);">
                                    <a href="edit_inventory.php?id=<?= $item['id'] ?>" class="btn-modern btn-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                        Edit
                                    </a>
                                    <button class="btn-modern btn-secondary btn-sm download-barcode"
                                        data-sku="<?= htmlspecialchars($item['sku']) ?>">
                                        <i class="bi bi-upc-scan"></i>
                                        Barcode
                                    </button>
                                </div>
                            </div>
                            
                            <div class="item-data-grid">
                                <div class="data-field">
                                    <div class="field-label">SKU Code</div>
                                    <div class="field-value">
                                        <code style="background: var(--light-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.9rem;">
                                            <?= htmlspecialchars($item['sku']) ?>
                                        </code>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Barcode</div>
                                    <div class="field-value">
                                        <?php if (!empty($item['barcode'])): ?>
                                            <code style="background: var(--light-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.9rem;">
                                                <?= htmlspecialchars($item['barcode']) ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-style: italic;">No barcode</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Current Stock</div>
                                    <div class="field-value">
                                        <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                                            <span style="color: #dc3545; font-weight: bold; font-size: 1.1rem;">
                                                <?= $item['quantity'] ?> units ⚠️
                                            </span>
                                            <div style="font-size: 0.8rem; color: #dc3545; margin-top: 2px;">
                                                Low Stock Warning
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--success-green); font-weight: 600; font-size: 1.1rem;">
                                                <?= $item['quantity'] ?> units
                                            </span>
                                            <div style="font-size: 0.8rem; color: var(--success-green); margin-top: 2px;">
                                                Adequate Stock
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Reorder Level</div>
                                    <div class="field-value">
                                        <span style="color: var(--text-dark);"><?= $item['reorder_level'] ?> units</span>
                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 2px;">
                                            Reorder when stock reaches this level
                                        </div>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Unit Price</div>
                                    <div class="field-value">
                                        <span style="font-weight: 600; font-size: 1.1rem; color: var(--text-dark);">
                                            ₦<?= number_format($item['price'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Supplier</div>
                                    <div class="field-value">
                                        <?php if (!empty($item['supplier_name'])): ?>
                                            <span style="color: var(--text-dark);">
                                                <?= htmlspecialchars($item['supplier_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-style: italic;">No supplier assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Last Updated</div>
                                    <div class="field-value">
                                        <span><?= date('M j, Y', strtotime($item['last_updated'])) ?></span>
                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 2px;">
                                            <?= date('g:i A', strtotime($item['last_updated'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Total Value</div>
                                    <div class="field-value">
                                        <span style="font-weight: 600; color: var(--primary-blue); font-size: 1.1rem;">
                                            ₦<?= number_format($item['quantity'] * $item['price'], 2) ?>
                                        </span>
                                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 2px;">
                                            (<?= $item['quantity'] ?> × ₦<?= number_format($item['price'], 2) ?>)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Mobile Card Layout -->
            <div class="mobile-card-layout">
                <?php if (empty($inventory)): ?>
                    <div class="text-center" style="padding: var(--space-2xl);">
                        <div style="color: var(--text-light);">
                            <i class="bi bi-inbox" style="font-size: 3rem; margin-bottom: var(--space-lg); display: block;"></i>
                            <h3 style="margin-bottom: var(--space-sm);">No Inventory Items</h3>
                            <p>Start by adding your first product to the inventory.</p>
                            <a href="add_inventory.php" class="btn-modern btn-primary mt-3">
                                <i class="bi bi-plus-circle"></i>
                                Add First Item
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <div class="inventory-card">
                            <div class="card-header">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </div>
                            <div class="card-content">
                                <div class="card-row">
                                    <span class="card-label">SKU:</span>
                                    <span class="card-value">
                                        <code style="background: var(--light-gray); padding: 2px 6px; border-radius: 3px; font-size: 0.8rem;">
                                            <?= htmlspecialchars($item['sku']) ?>
                                        </code>
                                    </span>
                                </div>
                                <?php if (!empty($item['barcode'])): ?>
                                <div class="card-row">
                                    <span class="card-label">Barcode:</span>
                                    <span class="card-value">
                                        <code style="background: var(--light-gray); padding: 2px 6px; border-radius: 3px; font-size: 0.8rem;">
                                            <?= htmlspecialchars($item['barcode']) ?>
                                        </code>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="card-row">
                                    <span class="card-label">Quantity:</span>
                                    <span class="card-value">
                                        <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                                            <span style="color: #dc3545; font-weight: bold;">
                                                <?= $item['quantity'] ?> ⚠️ (Low Stock)
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--success-green); font-weight: 600;">
                                                <?= $item['quantity'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Price:</span>
                                    <span class="card-value" style="font-weight: 600; color: var(--text-dark);">
                                        ₦<?= number_format($item['price'], 2) ?>
                                    </span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Reorder Level:</span>
                                    <span class="card-value">
                                        <?= $item['reorder_level'] ?> units
                                    </span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Supplier:</span>
                                    <span class="card-value">
                                        <?php if (!empty($item['supplier_name'])): ?>
                                            <?= htmlspecialchars($item['supplier_name']) ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-style: italic;">No supplier</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Last Updated:</span>
                                    <span class="card-value" style="font-size: 0.85rem;">
                                        <?= date('M j, Y g:i A', strtotime($item['last_updated'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <a href="edit_inventory.php?id=<?= $item['id'] ?>" class="btn-modern btn-primary">
                                    <i class="bi bi-pencil"></i>
                                    Edit Item
                                </a>
                                <button class="btn-modern btn-secondary download-barcode"
                                        data-sku="<?= htmlspecialchars($item['sku']) ?>">
                                    <i class="bi bi-upc-scan"></i>
                                    Generate Barcode
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Device detection and responsive enhancements
            function detectMobileDevice() {
                return window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            }

            // Add device-specific classes
            function updateDeviceClasses() {
                const isMobile = detectMobileDevice();
                document.body.classList.toggle('mobile-device', isMobile);
                document.body.classList.toggle('desktop-device', !isMobile);
            }

            // Initial setup
            updateDeviceClasses();

            // Update on window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateDeviceClasses, 250);
            });

            // Enhanced touch interactions for mobile
            if (detectMobileDevice()) {
                // Add touch feedback for cards
                document.querySelectorAll('.inventory-card').forEach(card => {
                    card.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.98)';
                    });
                    
                    card.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                    });
                });

                // Improve button touch targets
                document.querySelectorAll('.btn-modern').forEach(btn => {
                    if (btn.offsetHeight < 44) {
                        btn.style.minHeight = '44px';
                        btn.style.padding = '12px 16px';
                    }
                });
            }

            // Animation delays for cards and rows
            document.querySelectorAll('.inventory-card, .table-row').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.05}s`;
                element.classList.add('slide-up');
            });

            // Enhanced loading states for buttons
            document.querySelectorAll('.btn-modern').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('#')) {
                        this.classList.add('btn-loading');
                        const originalText = this.innerHTML;
                        this.innerHTML = '<div class="spinner"></div> Loading...';
                        
                        // Reset after 2 seconds if still on page
                        setTimeout(() => {
                            if (document.body.contains(this)) {
                                this.innerHTML = originalText;
                                this.classList.remove('btn-loading');
                            }
                        }, 2000);
                    }
                });
            });

            // Handle barcode generation buttons
            document.querySelectorAll('.download-barcode').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const sku = this.getAttribute('data-sku');
                    if (!sku) {
                        alert('No SKU found for this item');
                        return;
                    }
                    
                    const originalHtml = this.innerHTML;
                    
                    // Show loading state
                    this.innerHTML = '<div class="spinner"></div> Generating...';
                    this.classList.add('btn-loading');
                    this.disabled = true;
                    
                    // Create download link and trigger download
                    const downloadUrl = `barcode_generator.php?sku=${encodeURIComponent(sku)}`;
                    
                    // Create invisible link to trigger download
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = `barcode_${sku}.png`;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Reset button state after short delay
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.classList.remove('btn-loading');
                        this.disabled = false;
                    }, 1500);
                });
            });

            // Smooth scrolling for mobile navigation
            if (detectMobileDevice()) {
                document.documentElement.style.scrollBehavior = 'smooth';
            }

            // Add swipe gestures for mobile cards (optional enhancement)
            if (detectMobileDevice() && 'ontouchstart' in window) {
                let startX, startY, distX, distY;
                
                document.querySelectorAll('.inventory-card').forEach(card => {
                    card.addEventListener('touchstart', function(e) {
                        startX = e.touches[0].clientX;
                        startY = e.touches[0].clientY;
                    });
                    
                    card.addEventListener('touchend', function(e) {
                        if (!startX || !startY) return;
                        
                        distX = e.changedTouches[0].clientX - startX;
                        distY = e.changedTouches[0].clientY - startY;
                        
                        // Simple swipe detection (can be enhanced further)
                        if (Math.abs(distX) > Math.abs(distY) && Math.abs(distX) > 50) {
                            // Add subtle visual feedback for swipe
                            this.style.transition = 'transform 0.3s ease';
                            this.style.transform = `translateX(${distX > 0 ? '10px' : '-10px'})`;
                            
                            setTimeout(() => {
                                this.style.transform = 'translateX(0)';
                                setTimeout(() => {
                                    this.style.transition = '';
                                }, 300);
                            }, 150);
                        }
                        
                        startX = startY = null;
                    });
                });
            }

            console.log('✅ Responsive inventory table initialized');
        });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>