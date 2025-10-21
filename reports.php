<?php
session_start();
require 'config.php';

// Admin only access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Report data
$sales_report = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as leads, 
           SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as conversions
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetchAll();

$inventory_report = $pdo->query("
    SELECT i.product_name, i.quantity, i.reorder_level, s.name as supplier
    FROM inventory i
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.quantity <= i.reorder_level
    ORDER BY i.quantity ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Business Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container-fluid bg-primary text-white p-3 mb-4">
    <div class="container">
        <h1 class="display-6 mb-0">BizAutoPro</h1>
    </div>
</div>

    <div class="container mt-4">
        <h2>Business Intelligence Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Lead Conversion Trends (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <canvas id="leadChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        Inventory Alerts
                    </div>
                    <div class="card-body">
                        <?php if ($inventory_report): ?>
                            <ul class="list-group">
                                <?php foreach ($inventory_report as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                    <span class="badge bg-danger rounded-pill">
                                        <?= $item['quantity'] ?>/<?= $item['reorder_level'] ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-success">No inventory alerts</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                Raw Data Export
            </div>
            <div class="card-body">
                <a href="export.php?type=leads" class="btn btn-outline-primary">Export Leads (CSV)</a>
                <a href="export.php?type=inventory" class="btn btn-outline-secondary">Export Inventory (CSV)</a>
                <a href="export.php?type=workflows" class="btn btn-outline-success">Export Workflows (CSV)</a>
            </div>
        </div>
    </div>

    <script>
        // Lead Conversion Chart
        const ctx = document.getElementById('leadChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($sales_report, 'date')) ?>,
                datasets: [
                    {
                        label: 'New Leads',
                        data: <?= json_encode(array_column($sales_report, 'leads')) ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    },
                    {
                        label: 'Conversions',
                        data: <?= json_encode(array_column($sales_report, 'conversions')) ?>,
                        borderColor: 'rgb(54, 162, 235)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>