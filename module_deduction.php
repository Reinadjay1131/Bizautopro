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
            
            $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")
               ->execute([$quantity, $item_id]);
            
            $price = $deduction_type === 'sales' ? $item['price'] : 0.00;
            
            $pdo->prepare("INSERT INTO inventory_transactions 
                          (item_id, user_id, quantity, price, type, created_at)
                          VALUES (?, ?, ?, ?, ?, NOW())")
               ->execute([$item_id, $user_id, $quantity, $price, $deduction_type]);

            switch ($deduction_type) {
                case 'sales':
                    $stmt = $pdo->prepare("INSERT INTO outbound_sales 
                                  (item_id, product_name, sku, quantity, price, user_id)
                                  VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $item['price'], $user_id]);
                    break;
                    
                case 'damaged':
                    $stmt = $pdo->prepare("INSERT INTO outbound_damaged 
                                  (item_id, product_name, sku, quantity, user_id)
                                  VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user_id]);
                    break;
                    
                case 'internal':
                    $stmt = $pdo->prepare("INSERT INTO outbound_internal 
                                  (item_id, product_name, sku, quantity, user_id)
                                  VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user_id]);
                    break;
            }
            
            if ($stmt->errorCode() !== '00000') {
                throw new Exception("Failed to record $deduction_type: " . implode(", ", $stmt->errorInfo()));
            }
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
        @media print {
            body * { visibility: hidden; }
            .print-section, .print-section * { visibility: visible; }
            .print-section { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
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
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="salesPrice" step="0.01" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="salesQty" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock</label>
                                <input type="text" class="form-control" id="salesStock" readonly>
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
                                        <th class="text-end" id="salesTotal">$0.00</th>
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
                                <input type="text" class="form-control" id="damagedStock" readonly>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addItem('damaged')">
                                    <i class="bi bi-plus-lg"></i> Add
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
                                <input type="text" class="form-control" id="internalStock" readonly>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addItem('internal')">
                                    <i class="bi bi-plus-lg"></i> Add
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

                <!-- Enhanced Print Receipt Section -->
                <div id="printReceipt" class="d-none">
                    <div class="receipt-header text-center mb-3">
                        <h4>Inventory Deduction Receipt</h4>
                        <div><strong>Date:</strong> <span id="receiptDate"></span></div>
                        <div><strong>Type:</strong> <span id="receiptType"></span></div>
                    </div>
                    <table class="receipt-table table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="receiptItems"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total Items</th>
                                <th class="text-end" id="receiptTotalQty">0</th>
                                <th colspan="2" class="text-end" id="receiptGrandTotal">$0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="receipt-footer mt-3">
                        <div>Processed by: <?= $_SESSION['username'] ?? 'System' ?></div>
                    </div>
                </div>

                <form id="deductionForm" method="post">
                    <input type="hidden" name="deduction_type" id="deductionType">
                    <input type="hidden" name="items" id="itemsData">
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-primary" id="printReceiptBtn">
                            <i class="bi bi-printer"></i> Print Receipt
                        </button>
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
        const itemsData = { sales: [], damaged: [], internal: [] };

        document.getElementById('deductionType').value = 'sales';

        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', event => {
                document.getElementById('deductionType').value = event.target.getAttribute('href').substring(1);
            });
        });

        document.getElementById('barcode-trigger').addEventListener('click', function() {
            const barcodeInput = document.getElementById('barcode-input');
            barcodeInput.focus();
            document.getElementById('barcode-result').textContent = "Ready to scan...";
        });

        document.getElementById('barcode-input').addEventListener('input', function(e) {
            const barcode = e.target.value.trim();
            if (barcode.length > 3) {
                const option = document.querySelector(`#itemOptions option[data-sku="${barcode}"]`);
                if (option) {
                    const currentTab = document.querySelector('.tab-pane.active').id;
                    document.getElementById(`${currentTab}Item`).value = option.value;
                    document.getElementById(`${currentTab}Price`).value = option.dataset.price;
                    document.getElementById(`${currentTab}Stock`).value = option.dataset.stock;
                    document.getElementById('barcode-result').textContent = `Scanned: ${option.value}`;
                    e.target.value = '';
                }
            }
        });

        function addItem(type) {
            const itemInput = document.getElementById(`${type}Item`);
            const option = document.querySelector(`#itemOptions option[value="${itemInput.value}"]`);
            
            if (!option) {
                alert('Please select a valid product');
                return;
            }

            const newItem = {
                id: option.dataset.id,
                name: itemInput.value,
                price: type === 'sales' ? parseFloat(option.dataset.price) : 0,
                qty: parseInt(document.getElementById(`${type}Qty`).value),
                stock: parseInt(option.dataset.stock)
            };

            if (newItem.qty > newItem.stock) {
                alert(`Cannot deduct ${newItem.qty} items. Only ${newItem.stock} available.`);
                return;
            }

            itemsData[type].push(newItem);
            updateTable(type);
            updateTotals(type);
            itemInput.value = '';
            document.getElementById(`${type}Qty`).value = 1;
        }

        function updateTable(type) {
            const tableBody = document.getElementById(`${type}Table`);
            tableBody.innerHTML = '';
            itemsData[type].forEach((item, index) => {
                const rowTotal = item.price * item.qty;
                tableBody.innerHTML += `
                    <tr>
                        <td>${item.name}</td>
                        ${type === 'sales' ? `<td class="text-end">$${item.price.toFixed(2)}</td>` : ''}
                        <td class="text-end">${item.qty}</td>
                        ${type === 'sales' ? `<td class="text-end">$${rowTotal.toFixed(2)}</td>` : ''}
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeItem('${type}', ${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        function updateTotals(type) {
            let totalQty = 0;
            let totalPrice = 0;

            itemsData[type].forEach(item => {
                totalQty += item.qty;
                totalPrice += item.price * item.qty;
            });

            if (type === 'sales') {
                document.getElementById('salesTotalQty').textContent = totalQty;
                document.getElementById('salesTotal').textContent = `$${totalPrice.toFixed(2)}`;
            } else {
                document.getElementById(`${type}TotalQty`).textContent = totalQty;
            }
        }

        function removeItem(type, index) {
            itemsData[type].splice(index, 1);
            updateTable(type);
            updateTotals(type);
        }

        document.getElementById('deductionForm').addEventListener('submit', function(e) {
            const activeTab = document.querySelector('.tab-pane.active').id;
            if (itemsData[activeTab].length === 0) {
                e.preventDefault();
                alert(`Please add at least one item to ${activeTab}`);
                return;
            }
            
            document.getElementById('itemsData').value = JSON.stringify(itemsData[activeTab]);
        });

        document.getElementById('printReceiptBtn').addEventListener('click', function() {
            const activeTab = document.querySelector('.tab-pane.active').id;
            const receipt = document.getElementById('printReceipt');
            
            // Populate receipt header
            document.getElementById('receiptDate').textContent = new Date().toLocaleString();
            document.getElementById('receiptType').textContent = 
                activeTab.charAt(0).toUpperCase() + activeTab.slice(1);
            
            // Populate items in predefined order
            const receiptItems = document.getElementById('receiptItems');
            receiptItems.innerHTML = '';
            
            itemsData[activeTab].forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${item.name.split(' (SKU:')[0]}</td>
                    <td class="text-end">${item.qty}</td>
                    <td class="text-end">${activeTab === 'sales' ? '$'+item.price.toFixed(2) : 'N/A'}</td>
                    <td class="text-end">${activeTab === 'sales' ? '$'+(item.price * item.qty).toFixed(2) : 'N/A'}</td>
                `;
                receiptItems.appendChild(row);
            });
            
            // Update totals
            document.getElementById('receiptTotalQty').textContent = 
                document.getElementById(`${activeTab}TotalQty`).textContent;
            
            if (activeTab === 'sales') {
                document.getElementById('receiptGrandTotal').textContent = 
                    document.getElementById('salesTotal').textContent;
            } else {
                document.getElementById('receiptGrandTotal').textContent = 'N/A';
            }
            
            // Print only the receipt
            const printWindow = window.open('', '', 'width=600,height=600');
            printWindow.document.write('<html><head><title>Receipt</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style>body{padding:20px} .receipt-table{width:100%} @media print{body{padding:0}}</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(receipt.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        });
    </script>
</body>
</html>