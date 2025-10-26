<?php
// export_inventory.php
// Exports inventory as CSV. No auth or filter logic added (matches current inventory.php logic).
require_once 'config.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="inventory_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');

// Column headers (match visible fields in inventory.php)
$headers = [
    'SKU', 'Product Name', 'Description', 'Quantity', 'Unit Price', 'Total Value', 'Supplier', 'Reorder Level', 'Last Updated'
];
fputcsv($out, $headers);

$sql = "SELECT i.*, s.name AS supplier_name FROM inventory i LEFT JOIN suppliers s ON i.supplier_id = s.id ORDER BY i.product_name";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $unitPrice = $row['unit_price'] ?? 0;
    $totalValue = $row['quantity'] * $unitPrice;
        fputcsv($out, [
            $row['sku'],
            $row['product_name'],
            $row['description'] ?? '',
            $row['quantity'],
            $unitPrice,
            $totalValue,
            $row['supplier_name'],
            $row['reorder_level'],
            $row['last_updated'],
        ]);
}
fclose($out);
exit;
