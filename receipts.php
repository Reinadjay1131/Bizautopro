<?php
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where_conditions = [];
$params = [];

if ($search !== '') {
    $where_conditions[] = "(receipt_number LIKE ? OR product_name LIKE ? OR sku LIKE ? OR deducted_by_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_type !== '') {
    $where_conditions[] = "transaction_type = ?";
    $params[] = $filter_type;
}

if ($filter_date_from !== '') {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to !== '') {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $filter_date_to;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) FROM receipts $where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_receipts = $count_stmt->fetchColumn();
$total_pages = ceil($total_receipts / $per_page);

// Get receipts
$query = "SELECT * FROM receipts $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$receipts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deduction Receipts - BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern.css">
    <style>
        .receipt-card {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        .receipt-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .badge-sale { background-color: #28a745; }
        .badge-damaged { background-color: #dc3545; }
        .badge-internal { background-color: #ffc107; color: #000; }
        .receipt-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Include Responsive Header -->
    <?php require_once 'includes/page-header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="text-muted">View and manage all inventory deduction receipts</p>
            </div>
            <div>
                <a href="<?php echo $_SESSION['role'] === 'admin' ? 'inventory.php' : 'dashboard_me.php'; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Inventory
                </a>
                <button onclick="exportReceipts()" class="btn btn-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Receipt #, Product, SKU..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="sale" <?php echo $filter_type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                        <option value="damaged" <?php echo $filter_type === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                        <option value="internal" <?php echo $filter_type === 'internal' ? 'selected' : ''; ?>>Internal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="receipts.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Stats Summary -->
        <?php
        $stats_query = "SELECT 
            COUNT(*) as total_receipts,
            SUM(CASE WHEN transaction_type = 'sale' THEN total_amount ELSE 0 END) as total_sales,
            SUM(quantity) as total_quantity
            FROM receipts $where_sql";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute($params);
        $stats = $stats_stmt->fetch();
        ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Receipts</h6>
                        <h3><?php echo number_format($stats['total_receipts']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Sales Value</h6>
                        <h3>$<?php echo number_format($stats['total_sales'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Items Deducted</h6>
                        <h3><?php echo number_format($stats['total_quantity']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipts Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Deducted By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($receipts) > 0): ?>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td class="receipt-number"><?php echo htmlspecialchars($receipt['receipt_number']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($receipt['created_at'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $receipt['transaction_type']; ?>">
                                                <?php echo ucfirst($receipt['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($receipt['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($receipt['sku']); ?></td>
                                        <td><?php echo number_format($receipt['quantity']); ?></td>
                                        <td>
                                            <?php if ($receipt['total_amount'] > 0): ?>
                                                $<?php echo number_format($receipt['total_amount'], 2); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($receipt['deducted_by_name']); ?></td>
                                        <td>
                                            <button onclick="viewReceipt(<?php echo $receipt['receipt_id']; ?>)" class="btn btn-sm btn-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button onclick="printReceipt(<?php echo $receipt['receipt_id']; ?>)" class="btn btn-sm btn-secondary" title="Print">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No receipts found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $filter_type; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $filter_type; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $filter_type; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptDetails">
                    <!-- Receipt details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceiptFromModal()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentReceiptId = null;

        function viewReceipt(receiptId) {
            currentReceiptId = receiptId;
            fetch(`api/get_receipt.php?id=${receiptId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const receipt = data.receipt;
                        const html = `
                            <div class="receipt-view" style="font-family: 'Courier New', monospace;">
                                <div class="text-center mb-4">
                                    <h4>BizAutoPro</h4>
                                    <p class="text-muted">Inventory Deduction Receipt</p>
                                </div>
                                <hr>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Receipt Number:</strong></td>
                                        <td>${receipt.receipt_number}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date & Time:</strong></td>
                                        <td>${new Date(receipt.created_at).toLocaleString()}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Transaction Type:</strong></td>
                                        <td><span class="badge badge-${receipt.transaction_type}">${receipt.transaction_type.toUpperCase()}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Product:</strong></td>
                                        <td>${receipt.product_name}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>SKU:</strong></td>
                                        <td>${receipt.sku}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Quantity Deducted:</strong></td>
                                        <td>${receipt.quantity}</td>
                                    </tr>
                                    ${receipt.unit_price > 0 ? `
                                    <tr>
                                        <td><strong>Unit Price:</strong></td>
                                        <td>$${parseFloat(receipt.unit_price).toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Amount:</strong></td>
                                        <td><strong>$${parseFloat(receipt.total_amount).toFixed(2)}</strong></td>
                                    </tr>
                                    ` : ''}
                                    <tr>
                                        <td><strong>Stock Before:</strong></td>
                                        <td>${receipt.stock_before}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stock After:</strong></td>
                                        <td>${receipt.stock_after}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Deducted By:</strong></td>
                                        <td>${receipt.deducted_by_name}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reason:</strong></td>
                                        <td>${receipt.reason || 'N/A'}</td>
                                    </tr>
                                </table>
                                <hr>
                                <p class="text-center text-muted small">This is a computer-generated receipt</p>
                            </div>
                        `;
                        document.getElementById('receiptDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('receiptModal')).show();
                    } else {
                        alert('Failed to load receipt details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading the receipt');
                });
        }

        function printReceipt(receiptId) {
            window.open(`print_receipt.php?id=${receiptId}`, '_blank');
        }

        function printReceiptFromModal() {
            if (currentReceiptId) {
                printReceipt(currentReceiptId);
            }
        }

        function exportReceipts() {
            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = `export_receipts.php?${urlParams.toString()}`;
        }
    </script>
</body>
</html>
