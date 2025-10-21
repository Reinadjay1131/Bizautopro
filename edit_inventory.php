<?php
session_start();
require 'config.php';

if (!in_array($_SESSION['role'], ['admin', 'inventory_manager'])) {
    header("Location: dashboard.php");
    exit;
}

$item_id = $_GET['id'] ?? 0;

$item = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$item->execute([$item_id]);
$item = $item->fetch();

if (!$item) {
    die("Inventory item not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'];
    $sku = $_POST['sku'];
    $quantity = (int)$_POST['quantity'];
    $reorder_level = (int)$_POST['reorder_level'];
    $price = (float)$_POST['price'];
    $supplier_id = $_POST['supplier_id'] ?: null;
    
    $stmt = $pdo->prepare("
        UPDATE inventory 
        SET product_name = ?, sku = ?, quantity = ?, reorder_level = ?, price = ?, supplier_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$product_name, $sku, $quantity, $reorder_level, $price, $supplier_id, $item_id]);
    
    header("Location: inventory.php");
    exit;
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Edit Inventory Item</h2>
        
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Product Name</label>
                <input type="text" class="form-control" name="product_name" 
                       value="<?= htmlspecialchars($item['product_name']) ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">SKU</label>
                    <input type="text" class="form-control" name="sku" 
                           value="<?= htmlspecialchars($item['sku']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Supplier</label>
                    <select class="form-select" name="supplier_id">
                        <option value="">-- No Supplier --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>" 
                                <?= $supplier['id'] == $item['supplier_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" 
                           value="<?= $item['quantity'] ?>" min="0" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reorder Level</label>
                    <input type="number" class="form-control" name="reorder_level" 
                           value="<?= $item['reorder_level'] ?>" min="1" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" class="form-control" name="price" 
                           value="<?= $item['price'] ?>" min="0" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="inventory.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>