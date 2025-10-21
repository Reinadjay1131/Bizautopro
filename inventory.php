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
<html>
<head>
    <title>Bizautopro - Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .btn.disabled {
            opacity: 0.7;
            pointer-events: none;
        }
        .bi-hourglass {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="dashboard.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h2>Inventory Management</h2>
            </div>
            <div>
                <a href="outbound.php" class="btn btn-info">
                    <i class="bi bi-box-arrow-up"></i> View Outbound
                </a>
                <a href="module_deduction.php" class="btn btn-warning me-2">
                    <i class="bi bi-box-arrow-down"></i> Bulk Deduction
                </a>
                <a href="add_inventory.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Item
                </a>
            </div>
        </div>

        <?php if ($low_stock): ?>
            <div class="alert alert-warning">
                <h3>Low Stock Items</h3>
                <ul>
                    <?php foreach ($low_stock as $item): ?>
                        <li><?= htmlspecialchars($item['product_name']) ?> (Only <?= $item['quantity'] ?> left)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Barcode</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><?= htmlspecialchars($item['sku']) ?></td>
                        <td>
                            <?php if (!empty($item['sku'])): ?>
                                <a href="barcode_generator.php?sku=<?= urlencode($item['sku']) ?>" 
                                   class="btn btn-sm btn-success download-barcode"
                                   data-sku="<?= htmlspecialchars($item['sku']) ?>">
                                    <i class="bi bi-upc-scan"></i> Download
                                </a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $item['quantity'] ?></td>
                        <td>$<?= number_format($item['price'], 2) ?></td>
                        <td>
                            <a href="edit_inventory.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-info">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No inventory items found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
   document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.download-barcode').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sku = this.getAttribute('data-sku');
            const originalHtml = this.innerHTML;
            
            // Show loading state
            this.innerHTML = '<i class="bi bi-hourglass"></i> Generating...';
            this.classList.add('disabled');
            
            // Create hidden iframe for download
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = `barcode_generator.php?sku=${encodeURIComponent(sku)}`;
            
            // Clean up after download
            iframe.onload = iframe.onerror = () => {
                setTimeout(() => {
                    document.body.removeChild(iframe);
                    this.innerHTML = originalHtml;
                    this.classList.remove('disabled');
                }, 1000);
            };
            
            document.body.appendChild(iframe);
        });
    });
});
    </script>
</body>
</html>