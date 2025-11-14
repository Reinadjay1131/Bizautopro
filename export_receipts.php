<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

$allowed_roles = ['admin', 'manager', 'employee'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die('Unauthorized');
}

// Get filters from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with same filters as receipts.php
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

// Get all receipts matching filters
$query = "SELECT * FROM receipts $where_sql ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="receipts_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'Receipt Number',
    'Date & Time',
    'Transaction Type',
    'Product Name',
    'SKU',
    'Quantity',
    'Unit Price',
    'Total Amount',
    'Stock Before',
    'Stock After',
    'Reason',
    'Deducted By',
    'Created At'
]);

// Write data rows
foreach ($receipts as $receipt) {
    fputcsv($output, [
        $receipt['receipt_number'],
        date('Y-m-d H:i:s', strtotime($receipt['created_at'])),
        ucfirst($receipt['transaction_type']),
        $receipt['product_name'],
        $receipt['sku'],
        $receipt['quantity'],
        number_format($receipt['unit_price'], 2),
        number_format($receipt['total_amount'], 2),
        $receipt['stock_before'],
        $receipt['stock_after'],
        $receipt['reason'],
        $receipt['deducted_by_name'],
        $receipt['created_at']
    ]);
}

fclose($output);
exit;
?>
