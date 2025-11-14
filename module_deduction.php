<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

$allowed_roles = ['admin', 'manager', 'employee'];
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error'] = "You don't have permission to access this page";
    header("Location: dashboard_me.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $deduction_type = $_POST['deduction_type'];
        $items = json_decode($_POST['items'], true);
        $user_id = $_SESSION['user_id'];
        
        foreach ($items as $item) {
            $item_id = $item['id'];
            $quantity = (int)$item['qty'];
            
            $stmt = $pdo->prepare("SELECT quantity, product_name, sku FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$item_id]);
            $product = $stmt->fetch();
            
            if (!$product || empty($product['sku'])) {
                throw new Exception("Product data incomplete for ID $item_id");
            }
            
            if ($product['quantity'] < $quantity) {
                throw new Exception("Cannot deduct $quantity items of ID $item_id. Only {$product['quantity']} available.");
            }
            
            $stock_before = $product['quantity'];
            
            $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")
               ->execute([$quantity, $item_id]);
            
            $price = $deduction_type === 'sales' ? $item['price'] : 0.00;
            
            $pdo->prepare("INSERT INTO inventory_transactions 
                          (item_id, user_id, quantity, price, type, reason, created_at)
                          VALUES (?, ?, ?, ?, 'deduction', ?, NOW())")
               ->execute([$item_id, $user_id, $quantity, $price, $deduction_type]);

            switch ($deduction_type) {
                case 'sales':
                    $stmt = $pdo->prepare("INSERT INTO outbound_sales 
                                  (item_id, product_name, sku, quantity, price, user_id, deduction_date)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $item['price'], $user_id]);
                    break;
                    
                case 'damaged':
                    $stmt = $pdo->prepare("INSERT INTO outbound_damaged 
                                  (item_id, product_name, sku, quantity, user_id, deduction_date, price)
                                  VALUES (?, ?, ?, ?, ?, NOW(), 0.00)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user_id]);
                    break;
                    
                case 'internal':
                    $stmt = $pdo->prepare("INSERT INTO outbound_internal 
                                  (item_id, product_name, sku, quantity, user_id, deduction_date, price)
                                  VALUES (?, ?, ?, ?, ?, NOW(), 0.00)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user_id]);
                    break;
            }
            
            if ($stmt->errorCode() !== '00000') {
                throw new Exception("Failed to record $deduction_type: " . implode(", ", $stmt->errorInfo()));
            }
            
            // Auto-generate receipt for this deduction
            $receipt_number = 'RCP-' . date('Ymd') . '-' . strtoupper(substr($deduction_type, 0, 3)) . '-' . str_pad($item_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            $stock_after = $stock_before - $quantity;
            $total_amount = $price * $quantity;
            
            // Get user name
            $user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_name = $user_stmt->fetchColumn() ?: 'Unknown';
            
            // Store receipt data as JSON
            $receipt_data = json_encode([
                'deduction_type' => $deduction_type,
                'timestamp' => date('Y-m-d H:i:s'),
                'stock_movement' => [
                    'before' => $stock_before,
                    'deducted' => $quantity,
                    'after' => $stock_after
                ]
            ]);
            
            // Insert receipt record
            $receipt_stmt = $pdo->prepare("INSERT INTO receipts 
                (receipt_number, transaction_type, inventory_id, product_name, sku, quantity, 
                 unit_price, total_amount, stock_before, stock_after, reason, deducted_by, 
                 deducted_by_name, receipt_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $receipt_stmt->execute([
                $receipt_number,
                $deduction_type === 'sales' ? 'sale' : $deduction_type,
                $item_id,
                $product['product_name'],
                $product['sku'],
                $quantity,
                $price,
                $total_amount,
                $stock_before,
                $stock_after,
                $deduction_type,
                $user_id,
                $user_name,
                $receipt_data
            ]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Deduction processed successfully";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: " . ($_SESSION['role'] === 'admin' ? "inventory.php" : "dashboard_me.php"));
    exit;
}

$items = $pdo->query("SELECT id, product_name, sku, quantity, price FROM inventory ORDER BY product_name")->fetchAll();

// Debug: Log item count for debugging
error_log("Module Deduction - User: " . $_SESSION['username'] . " (" . $_SESSION['role'] . ") - Items loaded: " . count($items));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Deduction | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .barcode-scanner { height: 150px; border: 2px dashed #dee2e6; background: #f8f9fa; }
        .barcode-scanner.active { border-color: #0d6efd; background: #e7f1ff; }
        .stock-info { font-size: 0.9rem; }
        .nav-tabs .nav-link.active { font-weight: bold; }
        .deduction-table { max-height: 300px; overflow-y: auto; }
        .barcode-container { position: relative; margin-bottom: 1rem; }
        #barcode-result { font-size: 0.9rem; color: #666; }
        .totals-row { font-weight: bold; background-color: #f8f9fa; }
        
        /* Responsive Print Button Styles */
        .responsive-print-btn {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .responsive-print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .responsive-print-btn:active {
            transform: translateY(0);
        }
        
        /* Touch device optimizations */
        @media (pointer: coarse) {
            .responsive-print-btn {
                min-height: 44px;
                padding: 12px 16px;
                font-size: 16px;
            }
            
            .responsive-print-btn i {
                font-size: 18px;
            }
        }
        
        /* Mobile specific styles */
        @media (max-width: 576px) {
            .responsive-print-btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .print-btn-text {
                font-size: 14px;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
            }
            
            .d-flex.justify-content-between > div {
                width: 100%;
            }
            
            .btn-danger {
                width: 100%;
            }
        }
        
        /* Tablet specific styles */
        @media (min-width: 577px) and (max-width: 991px) {
            .responsive-print-btn {
                padding: 10px 20px;
                font-size: 15px;
            }
        }
        
        /* Desktop hover effects */
        @media (hover: hover) and (pointer: fine) {
            .responsive-print-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s;
            }
            
            .responsive-print-btn:hover::before {
                left: 100%;
            }
        }
        
        /* High DPI/Retina display optimizations */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .responsive-print-btn i {
                font-weight: 500;
            }
        }
    </style>
</head>
<body>
    <!-- Include Responsive Header -->
    <?php require_once 'includes/page-header.php'; ?>
    
    <div class="container py-4">
        <a href="<?= $_SESSION['role'] === 'admin' ? 'inventory.php' : 'dashboard_me.php' ?>" class="btn btn-outline-secondary mb-4">
            <i class="bi bi-arrow-left"></i> Back to <?= $_SESSION['role'] === 'admin' ? 'Inventory' : 'Dashboard' ?>
        </a>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="bi bi-box-arrow-down"></i> Inventory Deduction</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="barcode-container mb-3">
                    <label class="form-label">Barcode Scanner</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="barcode-input" placeholder="Scan barcode or enter manually" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="barcode-trigger">
                            <i class="bi bi-upc-scan"></i> Scan
                        </button>
                    </div>
                    <div id="barcode-result" class="mt-1"></div>
                </div>

                <ul class="nav nav-tabs mb-4" id="deductionTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#sales">Sales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#damaged">Damaged Goods</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#internal">Internal Use</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="sales">
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">Product</label>
                                <input class="form-control" list="itemOptions" id="salesItem" placeholder="Search product...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="salesPrice" step="0.01" readonly placeholder="Auto-filled">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="salesQty" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock</label>
                                <input type="text" class="form-control" id="salesStock" readonly placeholder="Available">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addItem('sales')">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="deduction-table">
                            <table class="table table-sm table-hover">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Total</th>
                                        <th width="40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="salesTable"></tbody>
                                <tfoot class="table-primary">
                                    <tr class="totals-row">
                                        <th colspan="2" class="text-end">Grand Total:</th>
                                        <th class="text-end" id="salesTotalQty">0</th>
                                        <th class="text-end" id="salesTotal">₦0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="damaged">
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Product</label>
                                <input class="form-control" list="itemOptions" id="damagedItem" placeholder="Search product...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="damagedQty" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock</label>
                                <input type="text" class="form-control" id="damagedStock" readonly placeholder="Available">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addItem('damaged')">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="deduction-table">
                            <table class="table table-sm table-hover">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th width="40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="damagedTable"></tbody>
                                <tfoot class="table-primary">
                                    <tr class="totals-row">
                                        <th class="text-end">Total Items:</th>
                                        <th class="text-end" id="damagedTotalQty">0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="internal">
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Product</label>
                                <input class="form-control" list="itemOptions" id="internalItem" placeholder="Search product...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="internalQty" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock</label>
                                <input type="text" class="form-control" id="internalStock" readonly placeholder="Available">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addItem('internal')">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="deduction-table">
                            <table class="table table-sm table-hover">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th width="40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="internalTable"></tbody>
                                <tfoot class="table-primary">
                                    <tr class="totals-row">
                                        <th class="text-end">Total Items:</th>
                                        <th class="text-end" id="internalTotalQty">0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <form id="deductionForm" method="post">
                    <input type="hidden" name="deduction_type" id="deductionType">
                    <input type="hidden" name="items" id="itemsData">
                    <div class="d-flex justify-content-between mt-3">
                        <div>
                            <button type="button" class="btn btn-outline-primary responsive-print-btn" id="printReceiptBtn" onclick="manualPrintReceipt()">
                                <i class="bi bi-printer"></i> <span class="print-btn-text">Print Receipt</span>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-box-arrow-down"></i> Confirm Deduction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <datalist id="itemOptions">
        <?php foreach ($items as $item): ?>
            <option value="<?= htmlspecialchars($item['product_name']) ?> (SKU: <?= htmlspecialchars($item['sku']) ?>)"
                    data-id="<?= $item['id'] ?>"
                    data-sku="<?= htmlspecialchars($item['sku']) ?>"
                    data-price="<?= $item['price'] ?>"
                    data-stock="<?= $item['quantity'] ?>">
        <?php endforeach; ?>
    </datalist>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('🚀 Module Deduction Script Starting...');
        
        // Global storage for items
        var itemsData = { sales: [], damaged: [], internal: [] };
        
        // Product selection and auto-fill
        function updateProductFields(type, selectedValue) {
            console.log('🔍 Updating fields for:', type, 'with value:', selectedValue);
            
            if (!selectedValue) {
                clearFields(type);
                return;
            }
            
            var options = document.querySelectorAll('#itemOptions option');
            var matchedOption = null;
            
            for (var i = 0; i < options.length; i++) {
                if (options[i].value === selectedValue) {
                    matchedOption = options[i];
                    break;
                }
            }
            
            if (matchedOption) {
                if (type === 'sales') {
                    var priceField = document.getElementById('salesPrice');
                    if (priceField) {
                        priceField.value = matchedOption.dataset.price;
                        priceField.style.backgroundColor = '#e8f5e8';
                        setTimeout(function() { priceField.style.backgroundColor = ''; }, 1000);
                    }
                }
                
                var stockField = document.getElementById(type + 'Stock');
                if (stockField) {
                    stockField.value = matchedOption.dataset.stock;
                    stockField.style.backgroundColor = '#e8f5e8';
                    setTimeout(function() { stockField.style.backgroundColor = ''; }, 1000);
                }
            } else {
                clearFields(type);
            }
        }
        
        function clearFields(type) {
            if (type === 'sales') {
                var priceField = document.getElementById('salesPrice');
                if (priceField) priceField.value = '';
            }
            var stockField = document.getElementById(type + 'Stock');
            if (stockField) stockField.value = '';
        }
        
        // Add item to table
        function addItem(type) {
            console.log('➕ Adding item for:', type);
            
            try {
                var itemInput = document.getElementById(type + 'Item');
                var qtyInput = document.getElementById(type + 'Qty');
                
                if (!itemInput || !qtyInput) {
                    alert('❌ Error: Required form fields not found');
                    return;
                }
                
                var selectedValue = itemInput.value.trim();
                var quantity = parseInt(qtyInput.value) || 1;
                
                if (!selectedValue) {
                    alert('⚠️ Please select a product first');
                    itemInput.focus();
                    return;
                }
                
                var options = document.querySelectorAll('#itemOptions option');
                var matchedOption = null;
                
                for (var i = 0; i < options.length; i++) {
                    if (options[i].value === selectedValue) {
                        matchedOption = options[i];
                        break;
                    }
                }
                
                if (!matchedOption) {
                    alert('❌ Please select a valid product from the list');
                    return;
                }
                
                var newItem = {
                    id: matchedOption.dataset.id,
                    name: selectedValue,
                    price: type === 'sales' ? parseFloat(matchedOption.dataset.price) || 0 : 0,
                    qty: quantity,
                    stock: parseInt(matchedOption.dataset.stock) || 0
                };
                
                if (newItem.qty > newItem.stock) {
                    alert('🚫 Not enough stock available!\nRequested: ' + newItem.qty + '\nAvailable: ' + newItem.stock);
                    qtyInput.focus();
                    return;
                }
                
                itemsData[type].push(newItem);
                updateTable(type);
                updateTotals(type);
                
                itemInput.value = '';
                qtyInput.value = 1;
                clearFields(type);
                itemInput.focus();
                
            } catch (error) {
                console.error('💥 Error adding item:', error);
                alert('Error: ' + error.message);
            }
        }
        
        // Update table display
        function updateTable(type) {
            var tableBody = document.getElementById(type + 'Table');
            if (!tableBody) {
                console.error('❌ Table not found:', type + 'Table');
                return;
            }
            
            tableBody.innerHTML = '';
            
            for (var i = 0; i < itemsData[type].length; i++) {
                var item = itemsData[type][i];
                var row = tableBody.insertRow();
                
                var nameCell = row.insertCell(0);
                nameCell.textContent = item.name.split(' (SKU:')[0];
                
                if (type === 'sales') {
                    var priceCell = row.insertCell(1);
                    priceCell.textContent = '₦' + item.price.toFixed(2);
                    priceCell.className = 'text-end';
                }
                
                var qtyCell = row.insertCell(type === 'sales' ? 2 : 1);
                qtyCell.textContent = item.qty;
                qtyCell.className = 'text-end';
                
                if (type === 'sales') {
                    var totalCell = row.insertCell(3);
                    totalCell.textContent = '₦' + (item.price * item.qty).toFixed(2);
                    totalCell.className = 'text-end';
                }
                
                var actionCell = row.insertCell(type === 'sales' ? 4 : 2);
                actionCell.innerHTML = '<button class="btn btn-sm btn-outline-danger" onclick="removeItem(\'' + type + '\', ' + i + ')" title="Remove item"><i class="bi bi-trash"></i></button>';
                actionCell.className = 'text-center';
            }
        }
        
        // Update totals
        function updateTotals(type) {
            var totalQty = 0;
            var totalPrice = 0;
            
            for (var i = 0; i < itemsData[type].length; i++) {
                totalQty += itemsData[type][i].qty;
                totalPrice += itemsData[type][i].price * itemsData[type][i].qty;
            }
            
            var qtyElement = document.getElementById(type === 'sales' ? 'salesTotalQty' : type + 'TotalQty');
            if (qtyElement) {
                qtyElement.textContent = totalQty;
            }
            
            if (type === 'sales') {
                var priceElement = document.getElementById('salesTotal');
                if (priceElement) {
                    priceElement.textContent = '₦' + totalPrice.toFixed(2);
                }
            }
        }
        
        // Remove item
        function removeItem(type, index) {
            if (confirm('Remove this item from the list?')) {
                itemsData[type].splice(index, 1);
                updateTable(type);
                updateTotals(type);
            }
        }
        
        // Enhanced Print Receipt Function with device responsiveness
        function manualPrintReceipt() {
            console.log('🖨️ Print receipt function called');
            
            try {
                var activeTab = document.querySelector('.tab-pane.active').id;
                var items = itemsData[activeTab];
                
                if (!items || items.length === 0) {
                    alert('⚠️ No items to print. Please add some items first.');
                    return;
                }
                
                console.log('📄 Preparing receipt for:', activeTab, 'with', items.length, 'items');
                
                // Detect device type for optimal window sizing
                var deviceInfo = detectDeviceType();
                var windowFeatures = getOptimalWindowFeatures(deviceInfo);
                
                var receiptContent = generateReceiptHTML(activeTab, items);
                
                var printWindow = window.open('', '_blank', windowFeatures);
                
                if (!printWindow) {
                    alert('❌ Print window blocked! Please allow popups for this site and try again.');
                    return;
                }
                
                printWindow.document.write(receiptContent);
                printWindow.document.close();
                printWindow.focus();
                
                // Auto-adjust window size after content loads (for desktop)
                if (deviceInfo.type === 'desktop') {
                    setTimeout(function() {
                        try {
                            printWindow.resizeTo(Math.min(900, screen.width * 0.8), Math.min(700, screen.height * 0.8));
                            printWindow.moveTo(
                                (screen.width - printWindow.outerWidth) / 2,
                                (screen.height - printWindow.outerHeight) / 2
                            );
                        } catch (e) {
                            console.log('Window resize not available');
                        }
                    }, 100);
                }
                
                console.log('✅ Receipt window opened successfully for', deviceInfo.type, 'device');
                
            } catch (error) {
                console.error('💥 Print error:', error);
                alert('❌ Print failed: ' + error.message);
            }
        }

        // Get optimal window features based on device type
        function getOptimalWindowFeatures(deviceInfo) {
            if (deviceInfo.isMobile || deviceInfo.isPhone) {
                // Mobile devices - use full screen or large modal
                return 'width=' + Math.min(400, screen.width) + ',height=' + Math.min(600, screen.height) + 
                       ',scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';
            } else if (deviceInfo.isTablet) {
                // Tablet devices - medium sized window
                return 'width=' + Math.min(600, screen.width * 0.9) + ',height=' + Math.min(800, screen.height * 0.9) + 
                       ',scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';
            } else {
                // Desktop - original size with centering
                return 'width=800,height=600,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';
            }
        }
        
        // Device detection utility
        function detectDeviceType() {
            var userAgent = navigator.userAgent || navigator.vendor || window.opera;
            var screenWidth = window.screen.width;
            var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            // Mobile devices
            if (/android/i.test(userAgent) || (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream)) {
                return {
                    type: 'mobile',
                    isMobile: true,
                    isTablet: /iPad/.test(userAgent) || (screenWidth >= 768 && screenWidth <= 1024),
                    isPhone: screenWidth < 768,
                    hasTouch: isTouchDevice,
                    orientation: screenWidth > window.screen.height ? 'landscape' : 'portrait'
                };
            }
            
            // Tablet devices
            if (isTouchDevice && screenWidth >= 768 && screenWidth <= 1024) {
                return {
                    type: 'tablet',
                    isMobile: false,
                    isTablet: true,
                    isPhone: false,
                    hasTouch: true,
                    orientation: screenWidth > window.screen.height ? 'landscape' : 'portrait'
                };
            }
            
            // Desktop/Laptop
            return {
                type: 'desktop',
                isMobile: false,
                isTablet: false,
                isPhone: false,
                hasTouch: isTouchDevice,
                orientation: 'landscape'
            };
        }

        // Generate responsive CSS based on device type
        function generateResponsiveCSS(deviceInfo) {
            var baseCSS = `
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.4; 
                    color: #333;
                }
                .receipt-header { 
                    text-align: center; 
                    border-bottom: 2px solid #000; 
                    padding-bottom: 15px; 
                    margin-bottom: 20px; 
                }
                .receipt-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 15px 0; 
                }
                .receipt-table th, .receipt-table td { 
                    border: 1px solid #ddd; 
                    text-align: left; 
                    vertical-align: middle;
                }
                .receipt-table th { 
                    background-color: #f8f9fa; 
                    font-weight: bold; 
                }
                .receipt-footer { 
                    margin-top: 25px; 
                    border-top: 1px solid #000; 
                    padding-top: 15px; 
                    text-align: center;
                }
                .totals-row { 
                    font-weight: bold; 
                    background-color: #e9ecef; 
                }
                .no-print { 
                    text-align: center; 
                    margin-top: 20px; 
                }
                .btn { 
                    margin: 5px; 
                    border: none; 
                    border-radius: 5px; 
                    cursor: pointer; 
                    transition: all 0.3s ease;
                }
                .btn-primary { 
                    background-color: #0d6efd; 
                    color: white; 
                }
                .btn-secondary { 
                    background-color: #6c757d; 
                    color: white; 
                }
                .btn:hover { 
                    opacity: 0.9; 
                    transform: translateY(-1px); 
                }
            `;

            var deviceSpecificCSS = '';

            if (deviceInfo.isMobile || deviceInfo.isPhone) {
                // Mobile phone specific styling
                deviceSpecificCSS = `
                    body { 
                        padding: 10px; 
                        font-size: 12px; 
                    }
                    .receipt-header h2 { 
                        font-size: 18px; 
                        margin: 10px 0; 
                    }
                    .receipt-header h4 { 
                        font-size: 14px; 
                        margin: 8px 0; 
                    }
                    .receipt-header p { 
                        font-size: 11px; 
                        margin: 3px 0; 
                    }
                    .receipt-table th, .receipt-table td { 
                        padding: 6px 4px; 
                        font-size: 11px; 
                    }
                    .receipt-table th:first-child { 
                        width: 25px; 
                    }
                    .receipt-table th:nth-child(3) { 
                        width: 45px; 
                    }
                    .receipt-table th:nth-child(4), 
                    .receipt-table th:nth-child(5) { 
                        width: 60px; 
                    }
                    .btn { 
                        padding: 12px 20px; 
                        font-size: 14px; 
                        min-height: 44px; 
                        width: 45%; 
                        display: inline-block; 
                    }
                    .receipt-footer { 
                        font-size: 10px; 
                    }
                `;
            } else if (deviceInfo.isTablet) {
                // Tablet specific styling
                deviceSpecificCSS = `
                    body { 
                        padding: 15px; 
                        font-size: 14px; 
                    }
                    .receipt-header h2 { 
                        font-size: 22px; 
                        margin: 12px 0; 
                    }
                    .receipt-header h4 { 
                        font-size: 16px; 
                        margin: 10px 0; 
                    }
                    .receipt-table th, .receipt-table td { 
                        padding: 10px 8px; 
                    }
                    .receipt-table th:first-child { 
                        width: 35px; 
                    }
                    .receipt-table th:nth-child(3) { 
                        width: 70px; 
                    }
                    .receipt-table th:nth-child(4), 
                    .receipt-table th:nth-child(5) { 
                        width: 90px; 
                    }
                    .btn { 
                        padding: 12px 24px; 
                        font-size: 16px; 
                        min-width: 120px; 
                    }
                `;
            } else {
                // Desktop/Laptop styling (original)
                deviceSpecificCSS = `
                    body { 
                        padding: 20px; 
                        font-size: 14px; 
                    }
                    .receipt-header h2 { 
                        font-size: 24px; 
                        margin: 15px 0; 
                    }
                    .receipt-header h4 { 
                        font-size: 18px; 
                        margin: 12px 0; 
                    }
                    .receipt-table th, .receipt-table td { 
                        padding: 8px; 
                    }
                    .receipt-table th:first-child { 
                        width: 40px; 
                    }
                    .receipt-table th:nth-child(3) { 
                        width: 80px; 
                    }
                    .receipt-table th:nth-child(4), 
                    .receipt-table th:nth-child(5) { 
                        width: 100px; 
                    }
                    .btn { 
                        padding: 10px 20px; 
                        font-size: 14px; 
                        min-width: 100px; 
                    }
                `;
            }

            // Print media query - optimized for all devices
            var printCSS = `
                @media print { 
                    body { 
                        padding: 5px !important; 
                        font-size: 12px !important; 
                        color: black !important; 
                        background: white !important; 
                    }
                    .no-print { 
                        display: none !important; 
                    }
                    .receipt-table th, .receipt-table td { 
                        border: 1px solid #000 !important; 
                        padding: 4px !important; 
                    }
                    .receipt-header { 
                        border-bottom: 2px solid #000 !important; 
                    }
                    .receipt-footer { 
                        border-top: 1px solid #000 !important; 
                    }
                    page-break-inside: avoid; 
                }
                
                @page { 
                    margin: 0.5cm; 
                    size: auto; 
                }
            `;

            return baseCSS + deviceSpecificCSS + printCSS;
        }

        // Generate receipt HTML with responsive design
        function generateReceiptHTML(type, items) {
            var now = new Date();
            var dateStr = now.toLocaleDateString('en-NG');
            var timeStr = now.toLocaleTimeString('en-NG');
            
            var totalQty = 0;
            var totalPrice = 0;
            var itemsHTML = '';
            
            // Detect device type for responsive styling
            var deviceInfo = detectDeviceType();
            
            // Generate items HTML
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                totalQty += item.qty;
                if (type === 'sales') {
                    totalPrice += (item.price * item.qty);
                }
                
                var productName = item.name.split(' (SKU:')[0];
                var itemTotal = type === 'sales' ? (item.price * item.qty) : 0;
                
                // Truncate product name on mobile devices
                if (deviceInfo.isPhone && productName.length > 20) {
                    productName = productName.substring(0, 20) + '...';
                }
                
                itemsHTML += '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td>' + productName + '</td>' +
                    '<td style="text-align: right;">' + item.qty + '</td>' +
                    '<td style="text-align: right;">' + (type === 'sales' ? '₦' + item.price.toFixed(2) : 'N/A') + '</td>' +
                    '<td style="text-align: right;">' + (type === 'sales' ? '₦' + itemTotal.toFixed(2) : 'N/A') + '</td>' +
                    '</tr>';
            }
            
            var typeDisplay = type.charAt(0).toUpperCase() + type.slice(1);
            var deviceTypeClass = 'device-' + deviceInfo.type;
            
            // Enhanced receipt HTML with responsive design
            return '<!DOCTYPE html>' +
                '<html lang="en">' +
                '<head>' +
                    '<meta charset="UTF-8">' +
                    '<meta name="viewport" content="width=device-width, initial-scale=1.0">' +
                    '<title>Inventory Deduction Receipt - ' + typeDisplay + '</title>' +
                    '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' +
                    '<style>' + generateResponsiveCSS(deviceInfo) + '</style>' +
                '</head>' +
                '<body class="' + deviceTypeClass + '">' +
                    '<div class="receipt-container">' +
                        '<div class="receipt-header">' +
                            '<h2>BizAutoPro</h2>' +
                            '<h4>Inventory Deduction Receipt</h4>' +
                            '<p><strong>Date:</strong> ' + dateStr + ' <strong>Time:</strong> ' + timeStr + '</p>' +
                            '<p><strong>Type:</strong> ' + typeDisplay + '</p>' +
                            '<p><strong>Processed by:</strong> <?= $_SESSION["username"] ?? "System" ?></p>' +
                            '<p><small>Device: ' + deviceInfo.type.charAt(0).toUpperCase() + deviceInfo.type.slice(1) + 
                            (deviceInfo.hasTouch ? ' (Touch)' : '') + '</small></p>' +
                        '</div>' +
                        '<table class="receipt-table">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th>#</th>' +
                                    '<th>Product</th>' +
                                    '<th style="text-align: right;">Qty</th>' +
                                    '<th style="text-align: right;">Price</th>' +
                                    '<th style="text-align: right;">Total</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>' + itemsHTML + '</tbody>' +
                            '<tfoot>' +
                                '<tr class="totals-row">' +
                                    '<th colspan="2">GRAND TOTAL</th>' +
                                    '<th style="text-align: right;">' + totalQty + '</th>' +
                                    '<th style="text-align: right;">' + (type === 'sales' ? '₦' + totalPrice.toFixed(2) : 'N/A') + '</th>' +
                                    '<th style="text-align: right;">' + (type === 'sales' ? '₦' + totalPrice.toFixed(2) : 'N/A') + '</th>' +
                                '</tr>' +
                            '</tfoot>' +
                        '</table>' +
                        '<div class="receipt-footer">' +
                            '<p><strong>Thank you for using BizAutoPro!</strong></p>' +
                            '<p><small>Generated on ' + dateStr + ' at ' + timeStr + '</small></p>' +
                        '</div>' +
                        '<div class="no-print">' +
                            '<button onclick="window.print()" class="btn btn-primary">' +
                                (deviceInfo.hasTouch ? '🖨️ ' : '') + 'Print Receipt' +
                            '</button>' +
                            '<button onclick="window.close()" class="btn btn-secondary">' +
                                (deviceInfo.hasTouch ? '✖️ ' : '') + 'Close' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</body>' +
                '</html>';
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Initializing page...');
            
            var optionCount = document.querySelectorAll('#itemOptions option').length;
            console.log('📦 Products available:', optionCount);
            
            if (optionCount === 0) {
                console.warn('⚠️ No products found in inventory!');
            }
            
            document.getElementById('deductionType').value = 'sales';
            
            var tabs = document.querySelectorAll('a[data-bs-toggle="tab"]');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].addEventListener('shown.bs.tab', function(event) {
                    var tabName = event.target.getAttribute('href').substring(1);
                    document.getElementById('deductionType').value = tabName;
                    console.log('📑 Switched to tab:', tabName);
                });
            }
            
            document.getElementById('barcode-trigger').addEventListener('click', function() {
                var barcodeInput = document.getElementById('barcode-input');
                barcodeInput.focus();
                document.getElementById('barcode-result').textContent = "Ready to scan...";
            });

            document.getElementById('barcode-input').addEventListener('input', function(e) {
                var barcode = e.target.value.trim();
                if (barcode.length > 3) {
                    var option = document.querySelector('#itemOptions option[data-sku="' + barcode + '"]');
                    if (option) {
                        var currentTab = document.querySelector('.tab-pane.active').id;
                        document.getElementById(currentTab + 'Item').value = option.value;
                        updateProductFields(currentTab, option.value);
                        document.getElementById('barcode-result').textContent = 'Scanned: ' + option.value;
                        e.target.value = '';
                    }
                }
            });
            
            var types = ['sales', 'damaged', 'internal'];
            for (var t = 0; t < types.length; t++) {
                var type = types[t];
                var input = document.getElementById(type + 'Item');
                
                if (input) {
                    (function(currentType) {
                        input.addEventListener('input', function() {
                            updateProductFields(currentType, this.value);
                        });
                        
                        input.addEventListener('change', function() {
                            updateProductFields(currentType, this.value);
                        });
                    })(type);
                }
            }
            
            var form = document.getElementById('deductionForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var activeTab = document.querySelector('.tab-pane.active').id;
                    
                    if (itemsData[activeTab].length === 0) {
                        e.preventDefault();
                        alert('⚠️ Please add at least one item before submitting');
                        return false;
                    }
                    
                    document.getElementById('itemsData').value = JSON.stringify(itemsData[activeTab]);
                    console.log('📤 Submitting', itemsData[activeTab].length, 'items for', activeTab);
                    
                    return true;
                });
            }
            
            // Enhanced device-specific initialization
            var currentDevice = detectDeviceType();
            console.log('🎯 Device detected:', currentDevice.type, '- Touch:', currentDevice.hasTouch);
            
            // Add device-specific class to body for CSS targeting
            document.body.classList.add('device-' + currentDevice.type);
            if (currentDevice.hasTouch) {
                document.body.classList.add('touch-device');
            }
            
            // Enhance print button for touch devices
            var printBtn = document.getElementById('printReceiptBtn');
            if (printBtn && currentDevice.hasTouch) {
                // Add haptic feedback for supported devices
                printBtn.addEventListener('touchstart', function() {
                    if (navigator.vibrate) {
                        navigator.vibrate(50); // Brief vibration feedback
                    }
                });
                
                // Improve touch target size
                printBtn.style.minHeight = '44px';
                printBtn.style.padding = '12px 16px';
                
                // Add visual feedback
                printBtn.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                printBtn.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            }
            
            // Mobile-specific optimizations
            if (currentDevice.isMobile) {
                // Prevent zoom on input focus for mobile Safari
                var inputs = document.querySelectorAll('input, select');
                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].addEventListener('focus', function() {
                        this.style.fontSize = '16px'; // Prevent zoom
                    });
                }
                
                // Optimize barcode scanner for mobile
                var barcodeInput = document.getElementById('barcode-input');
                if (barcodeInput) {
                    barcodeInput.setAttribute('inputmode', 'numeric');
                    barcodeInput.setAttribute('pattern', '[0-9]*');
                }
            }
            
            console.log('✅ Page initialization complete with device optimizations!');
        });
        
        // Add device info to console for debugging
        window.addEventListener('load', function() {
            var device = detectDeviceType();
            console.log('🔍 Device Analysis:', {
                type: device.type,
                isMobile: device.isMobile,
                isTablet: device.isTablet,
                hasTouch: device.hasTouch,
                orientation: device.orientation,
                screenSize: window.screen.width + 'x' + window.screen.height,
                viewport: window.innerWidth + 'x' + window.innerHeight,
                userAgent: navigator.userAgent.substring(0, 50) + '...'
            });
        });
        
        console.log('🎯 Script loaded successfully - Ready for use!');
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>