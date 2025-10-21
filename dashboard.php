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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern.css">
</head>
<body class="fade-in">
    <!-- Modern Navigation -->
    <nav class="modern-navbar">
        <div class="modern-container">
            <div class="navbar-content">
                <a href="#" class="brand">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    BizAutoPro
                </a>
                <div class="nav-user">
                    <div class="user-info">
                        <span>Welcome, <?= htmlspecialchars($user['username']) ?></span>
                        <span class="user-role"><?= ucfirst($user['role']) ?> Dashboard</span>
                    </div>
                    <a href="logout.php" class="btn-modern btn-secondary btn-sm">
                        <i class="bi bi-box-arrow-right"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="modern-container">
            <h1 class="page-title">Dashboard Overview</h1>
            <p class="page-subtitle">Monitor your business operations and key metrics</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="modern-container">
        <!-- Statistics Grid -->
        <div class="grid grid-cols-4 mb-5">
            <div class="stat-card danger slide-up">
                <div class="stat-label">
                    <i class="bi bi-exclamation-triangle"></i>
                    Inventory Alerts
                </div>
                <div class="stat-number"><?= $inventory_alerts ?></div>
                <a href="inventory.php" class="stat-link">
                    View Items <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="stat-card warning slide-up">
                <div class="stat-label">
                    <i class="bi bi-clock"></i>
                    Pending Tasks
                </div>
                <div class="stat-number"><?= $pending_tasks ?></div>
                <a href="workflows.php" class="stat-link">
                    Review Tasks <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="stat-card success slide-up">
                <div class="stat-label">
                    <i class="bi bi-person-plus"></i>
                    New Leads
                </div>
                <div class="stat-number"><?= $new_leads ?></div>
                <a href="leads.php" class="stat-link">
                    Manage Leads <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="stat-card info slide-up">
                <div class="stat-label">
                    <i class="bi bi-people"></i>
                    Pending Users
                </div>
                <div class="stat-number"><?= $pending_users ?></div>
                <a href="view_pending_users.php" class="stat-link">
                    Manage Users <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-2">
            <!-- Recent Activities -->
            <div class="modern-card fade-in">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="bi bi-activity"></i>
                        Recent Activities
                    </h3>
                </div>
                <div class="modern-card-body p-0">
                    <ul class="activity-list">
                        <?php
                        $activities = $pdo->query("SELECT * FROM workflows ORDER BY created_at DESC LIMIT 5")->fetchAll();
                        if (empty($activities)): ?>
                            <li class="activity-item text-center p-4">
                                <div class="activity-content">
                                    <div style="color: var(--text-light);">
                                        <i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No recent activities
                                    </div>
                                </div>
                            </li>
                        <?php else:
                            foreach ($activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                        <div class="activity-description"><?= htmlspecialchars($activity['description']) ?></div>
                                    </div>
                                    <span class="activity-badge badge-<?= $activity['status'] == 'approved' ? 'success' : ($activity['status'] == 'pending' ? 'warning' : 'info') ?>">
                                        <?= ucfirst($activity['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach;
                        endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="modern-card fade-in">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="bi bi-lightning"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="modern-card-body">
                    <div class="grid grid-cols-1" style="gap: var(--space-md);">
                        <a href="module_deduction.php" class="btn-modern btn-success btn-lg">
                            <i class="bi bi-cash-stack"></i>
                            Point of Sale
                        </a>
                        <a href="create_workflow.php" class="btn-modern btn-outline">
                            <i class="bi bi-plus-circle"></i>
                            Create New Task
                        </a>
                        <a href="add_inventory.php" class="btn-modern btn-danger">
                            <i class="bi bi-box-seam"></i>
                            Add Inventory Item
                        </a>
                        <a href="new_lead.php" class="btn-modern btn-success">
                            <i class="bi bi-person-plus"></i>
                            Add New Lead
                        </a>
                        <a href="reports.php" class="btn-modern btn-info">
                            <i class="bi bi-graph-up"></i>
                            Generate Reports
                        </a>
                        <a href="view_pending_users.php" class="btn-modern btn-warning" style="position: relative;">
                            <i class="bi bi-people"></i>
                            Manage User Approvals
                            <?php if ($pending_users > 0): ?>
                                <span class="activity-badge badge-danger" style="position: absolute; top: -8px; right: -8px; min-width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.75rem;">
                                    <?= $pending_users ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => location.reload(), 300000);
        
        // Add loading states to buttons
        document.querySelectorAll('.btn-modern').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.href.includes('#')) {
                    this.classList.add('loading');
                    this.innerHTML = '<div class="spinner"></div> Loading...';
                }
            });
        });
        
        // Add animation delays for stat cards
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    </script>
</body>
</html>