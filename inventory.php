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

// Get all inventory items
$inventory = $pdo->query("SELECT * FROM inventory")->fetchAll();

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
            <!-- Table Header -->
            <div class="table-header" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1.5fr; gap: var(--space-md); align-items: center;">
                <div>Product Name</div>
                <div>SKU</div>
                <div>Barcode</div>
                <div>Quantity</div>
                <div>Price</div>
                <div>Actions</div>
            </div>

            <!-- Table Body -->
            <?php if (empty($inventory)): ?>
                <div class="table-row text-center" style="padding: var(--space-2xl);">
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
                    <div class="table-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1.5fr; gap: var(--space-md); align-items: center;">
                        <div class="table-cell">
                            <div style="font-weight: 600; color: var(--text-dark);">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </div>
                        </div>
                        <div class="table-cell">
                            <code style="background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.875rem;">
                                <?= htmlspecialchars($item['sku']) ?>
                            </code>
                        </div>
                        <div class="table-cell">
                            <?php if (!empty($item['sku'])): ?>
                                <button class="btn-modern btn-success btn-sm download-barcode"
                                        data-sku="<?= htmlspecialchars($item['sku']) ?>">
                                    <i class="bi bi-upc-scan"></i>
                                    Generate
                                </button>
                            <?php else: ?>
                                <span style="color: var(--text-light); font-size: 0.875rem;">N/A</span>
                            <?php endif; ?>
                        </div>
                        <div class="table-cell">
                            <span class="activity-badge <?= $item['quantity'] < $item['reorder_level'] ? 'badge-warning' : 'badge-success' ?>">
                                <?= $item['quantity'] ?> units
                            </span>
                        </div>
                        <div class="table-cell">
                            <span style="font-weight: 600; color: var(--text-dark);">
                                ₦<?= number_format($item['price'], 2) ?>
                            </span>
                        </div>
                        <div class="table-cell">
                            <a href="edit_inventory.php?id=<?= $item['id'] ?>" 
                               class="btn-modern btn-outline btn-sm">
                                <i class="bi bi-pencil-square"></i>
                                Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle barcode download with modern loading state
            document.querySelectorAll('.download-barcode').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sku = this.getAttribute('data-sku');
                    const originalHtml = this.innerHTML;
                    
                    // Show loading state
                    this.innerHTML = '<div class="spinner"></div> Generating...';
                    this.classList.add('btn-loading');
                    
                    // Create hidden iframe for download
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = `barcode_generator.php?sku=${encodeURIComponent(sku)}`;
                    
                    // Clean up after download
                    iframe.onload = iframe.onerror = () => {
                        setTimeout(() => {
                            if (document.body.contains(iframe)) {
                                document.body.removeChild(iframe);
                            }
                            this.innerHTML = originalHtml;
                            this.classList.remove('btn-loading');
                        }, 1000);
                    };
                    
                    document.body.appendChild(iframe);
                });
            });

            // Add animation delays for table rows
            document.querySelectorAll('.table-row').forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('slide-up');
            });
        });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>