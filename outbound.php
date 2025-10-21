<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

$allowed_roles = ['admin', 'manager'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php");
    exit;
}

// Fetch records (unchanged)
$sales = $pdo->query("SELECT 'sales' as type, os.*, u.username FROM outbound_sales os JOIN users u ON os.user_id = u.id ORDER BY os.deduction_date DESC")->fetchAll();
$damaged = $pdo->query("SELECT 'damaged' as type, od.*, u.username FROM outbound_damaged od JOIN users u ON od.user_id = u.id ORDER BY od.deduction_date DESC")->fetchAll();
$internal = $pdo->query("SELECT 'internal' as type, oi.*, u.username FROM outbound_internal oi JOIN users u ON oi.user_id = u.id ORDER BY oi.deduction_date DESC")->fetchAll();

// Combine and sort (unchanged)
$allRecords = array_merge($sales, $damaged, $internal);
usort($allRecords, function($a, $b) {
    return strtotime($b['deduction_date']) - strtotime($a['deduction_date']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbound Inventory | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .nav-tabs .nav-link.active { font-weight: bold; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .badge-sales { background-color: #28a745; }
        .badge-damaged { background-color: #dc3545; }
        .badge-internal { background-color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-box-arrow-up"></i> Outbound Inventory</h2>
            <a href="inventory.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Inventory
            </a>
        </div>

        <ul class="nav nav-tabs mb-4" id="outboundTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#all">All Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#sales">Sales</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#damaged">Damaged Goods</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#internal">Internal Use</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- All Records Tab (Unchanged) -->
            <div class="tab-pane fade show active" id="all">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Qty</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRecords as $record): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $record['type'] === 'sales' ? 'badge-sales' : ($record['type'] === 'damaged' ? 'badge-damaged' : 'badge-internal') ?>">
                                        <?= ucfirst($record['type']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($record['deduction_date'])) ?></td>
                                <td><?= htmlspecialchars($record['product_name']) ?></td>
                                <td><?= htmlspecialchars($record['sku']) ?></td>
                                <td class="text-end"><?= $record['quantity'] ?></td>
                                <td><?= htmlspecialchars($record['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales Tab (Unchanged) -->
            <div class="tab-pane fade" id="sales">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $record): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($record['deduction_date'])) ?></td>
                                <td><?= htmlspecialchars($record['product_name']) ?></td>
                                <td><?= htmlspecialchars($record['sku']) ?></td>
                                <td class="text-end"><?= $record['quantity'] ?></td>
                                <td class="text-end">$<?= number_format($record['price'], 2) ?></td>
                                <td class="text-end">$<?= number_format($record['price'] * $record['quantity'], 2) ?></td>
                                <td><?= htmlspecialchars($record['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Damaged Goods Tab (Simplified) -->
            <div class="tab-pane fade" id="damaged">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Qty</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($damaged as $record): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($record['deduction_date'])) ?></td>
                                <td><?= htmlspecialchars($record['product_name']) ?></td>
                                <td><?= htmlspecialchars($record['sku']) ?></td>
                                <td class="text-end"><?= $record['quantity'] ?></td>
                                <td><?= htmlspecialchars($record['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Internal Use Tab (Simplified) -->
            <div class="tab-pane fade" id="internal">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Qty</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internal as $record): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($record['deduction_date'])) ?></td>
                                <td><?= htmlspecialchars($record['product_name']) ?></td>
                                <td><?= htmlspecialchars($record['sku']) ?></td>
                                <td class="text-end"><?= $record['quantity'] ?></td>
                                <td><?= htmlspecialchars($record['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tabs (unchanged)
        const tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabElms.forEach(tabEl => {
            tabEl.addEventListener('click', event => {
                event.preventDefault();
                new bootstrap.Tab(event.target).show();
            });
        });
    </script>
</body>
</html>