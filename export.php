<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$type = $_GET['type'] ?? '';
$allowed_types = ['leads', 'inventory', 'workflows'];

if (!in_array($type, $allowed_types)) {
    die("Invalid export type");
}

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'leads':
        $stmt = $pdo->query("
            SELECT l.name, l.email, l.phone, l.company, l.status, 
                   l.score, u.username as assigned_to, l.created_at
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id
        ");
        fputcsv($output, ['Name', 'Email', 'Phone', 'Company', 'Status', 'Score', 'Assigned To', 'Date Created']);
        break;
        
    case 'inventory':
        $stmt = $pdo->query("
            SELECT i.product_name, i.sku, i.quantity, i.reorder_level, 
                   i.price, s.name as supplier, i.last_updated
            FROM inventory i
            LEFT JOIN suppliers s ON i.supplier_id = s.id
        ");
        fputcsv($output, ['Product', 'SKU', 'Quantity', 'Reorder Level', 'Price', 'Supplier', 'Last Updated']);
        break;
        
    case 'workflows':
        $stmt = $pdo->query("
            SELECT w.title, w.status, 
                   creator.username as created_by,
                   assignee.username as assigned_to,
                   w.created_at
            FROM workflows w
            LEFT JOIN users creator ON w.created_by = creator.id
            LEFT JOIN users assignee ON w.assigned_to = assignee.id
        ");
        fputcsv($output, ['Title', 'Status', 'Created By', 'Assigned To', 'Date Created']);
        break;
}

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;