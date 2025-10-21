<?php
// Strict admin-only access control
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

// ADMIN-ONLY ACCESS CHECK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = "Admin privileges required to access inventory management";
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = [
            'product_name' => 'Product Name',
            'sku' => 'SKU',
            'quantity' => 'Quantity',
            'reorder_level' => 'Reorder Level',
            'price' => 'Price'
        ];
        
        $errors = [];
        foreach ($required as $field => $name) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $errors[] = "$name is required";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

        // Prepare and execute insert
        $stmt = $pdo->prepare("INSERT INTO inventory 
                      (product_name, sku, quantity, reorder_level, price, supplier_id) 
                      VALUES (?, ?, ?, ?, ?, ?)");

// And update execute():
        $stmt->execute([
            trim($_POST['product_name']),
            trim($_POST['sku']),
            (int)$_POST['quantity'],
            (int)$_POST['reorder_level'],
            round((float)$_POST['price'], 2),
            !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null
            // Removed: $_SESSION['user_id']
        ]);

        $_SESSION['success'] = "Inventory item added successfully!";
        header("Location: inventory.php");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get suppliers and recent items
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$recent_items = $pdo->query("SELECT product_name, sku FROM inventory ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-panel {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
        }
        .required-field:after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid bg-primary text-white p-3 mb-4">
    <div class="container">
        <h1 class="display-6 mb-0">BizAutoPro</h1>
    </div>
</div>
    <?php @include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto admin-panel p-4 rounded">
                <h2 class="mb-4">
                    <i class="bi bi-box-seam"></i> Add Inventory Item
                    <span class="badge bg-danger float-end">ADMIN</span>
                </h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> Operation Failed</h5>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <!-- Product Info -->
                        <div class="col-md-6">
                            <label class="form-label required-field">Product Name</label>
                            <input type="text" class="form-control" name="product_name" required
                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required-field">SKU</label>
                            <input type="text" class="form-control" name="sku" required
                                   value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
                        </div>
                        
                        <!-- Inventory Details -->
                        <div class="col-md-4">
                            <label class="form-label required-field">Quantity</label>
                            <input type="number" class="form-control" name="quantity" min="0" required
                                   value="<?= htmlspecialchars($_POST['quantity'] ?? 0) ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label required-field">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" min="1" required
                                   value="<?= htmlspecialchars($_POST['reorder_level'] ?? 5) ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label required-field">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" name="price" min="0" required
                                       value="<?= htmlspecialchars($_POST['price'] ?? 0) ?>">
                            </div>
                        </div>
                        
                        <!-- Supplier Selection -->
                        <div class="col-12">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">-- No Supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>"
                                        <?= ($_POST['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-between">
                        <a href="inventory.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Inventory
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Add Inventory Item
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Recent Items Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        Recently Added Items
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_items as $item): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                <div class="text-muted small"><?= htmlspecialchars($item['sku']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Form Validation -->
    <script>
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>