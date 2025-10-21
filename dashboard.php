<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

echo "<script>console.log('Dashboard Loaded - Role: ".$_SESSION['role']."')</script>";
require 'config.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$inventory_alerts = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM workflows WHERE status = 'pending'")->fetchColumn();
$new_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BizAutoPro Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card { transition: transform 0.3s; }
        .card:hover { transform: translateY(-5px); }
        .alert-count { font-size: 2rem; font-weight: bold; }
        .btn-pos { background-color: #28a745; color: white; border: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">BizAutoPro [Dashboard]</a>
            <div class="navbar-text ms-auto">
                Welcome, <?= htmlspecialchars($user['username']) ?> (<?= $user['role'] ?>)
            </div>
            <a href="logout.php" class="btn btn-outline-light ms-3">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-danger h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Inventory Alerts</h5>
                        <div class="alert-count"><?= $inventory_alerts ?></div>
                        <a href="inventory.php" class="text-white">View Items</a>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="card text-white bg-warning h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pending Tasks</h5>
                        <div class="alert-count"><?= $pending_tasks ?></div>
                        <a href="workflows.php" class="text-white">Review Tasks</a>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="card text-white bg-success h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">New Leads</h5>
                        <div class="alert-count"><?= $new_leads ?></div>
                        <a href="leads.php" class="text-white">Manage Leads</a>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="card text-white bg-info h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pending Users</h5>
                        <div class="alert-count"><?= $pending_users ?></div>
                        <a href="view_pending_users.php" class="text-white">Manage Users</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">Recent Activities</div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php
                            $activities = $pdo->query("SELECT * FROM workflows ORDER BY created_at DESC LIMIT 5")->fetchAll();
                            foreach ($activities as $activity): ?>
                                <li class="list-group-item">
                                    <strong><?= htmlspecialchars($activity['title']) ?></strong>
                                    <span class="badge bg-<?= $activity['status'] == 'approved' ? 'success' : 'warning' ?> float-end">
                                        <?= ucfirst($activity['status']) ?>
                                    </span>
                                    <br>
                                    <small><?= $activity['description'] ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">Quick Actions</div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="module_deduction.php" class="btn btn-pos">
                                <i class="bi bi-cash-stack"></i> Point of Sale
                            </a>
                            <a href="create_workflow.php" class="btn btn-outline-primary">
                                Create New Task
                            </a>
                            <button class="btn btn-danger" onclick="window.location.href='add_inventory.php'">
                                Add Inventory Item
                            </button>
                            <a href="new_lead.php" class="btn btn-outline-success">
                                Add New Lead
                            </a>
                            <a href="reports.php" class="btn btn-outline-info">
                                Generate Reports
                            </a>
                            <a href="view_pending_users.php" class="btn btn-warning">
                                <i class="bi bi-people"></i> Manage User Approvals
                                <?php if ($pending_users > 0): ?>
                                    <span class="badge bg-danger"><?= $pending_users ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => location.reload(), 300000);
    </script>
</body>
</html>