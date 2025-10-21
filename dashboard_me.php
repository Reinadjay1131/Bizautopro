<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    header("Location: login.php");
    exit;
}

echo "<script>console.log('Dashboard Loaded - Role: ".$_SESSION['role']."')</script>";
require 'config.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Calculate personal revenue impact from outbound_sales where user made the sale
$personal_revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales WHERE user_id = ?");
$personal_revenue_query->execute([$user_id]);
$personal_revenue = $personal_revenue_query->fetchColumn() ?: 0;

// Calculate personal revenue change (current month vs previous month)
$current_month_personal_query = $pdo->prepare("
    SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales 
    WHERE user_id = ? AND MONTH(deduction_date) = MONTH(CURDATE()) AND YEAR(deduction_date) = YEAR(CURDATE())
");
$current_month_personal_query->execute([$user_id]);
$current_month_personal = $current_month_personal_query->fetchColumn() ?: 1;

$previous_month_personal_query = $pdo->prepare("
    SELECT COALESCE(SUM(price * quantity), 1) FROM outbound_sales 
    WHERE user_id = ? AND MONTH(deduction_date) = MONTH(CURDATE()) - 1 AND YEAR(deduction_date) = YEAR(CURDATE())
");
$previous_month_personal_query->execute([$user_id]);
$previous_month_personal = $previous_month_personal_query->fetchColumn() ?: 1;

$personal_revenue_change = (($current_month_personal - $previous_month_personal) / $previous_month_personal) * 100;
$personal_revenue_change_text = $personal_revenue_change >= 0 ? '↗ +' . number_format($personal_revenue_change, 1) . '% this month' : '↘ ' . number_format($personal_revenue_change, 1) . '% this month';

$inventory_alerts = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM workflows WHERE status = 'pending'")->fetchColumn();
$new_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
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
    <style>
        .analytics-dashboard .chart-container {
            position: relative;
            height: 200px;
            margin-bottom: 0.75rem;
        }
        .analytics-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.2;
        }
        .chart-subtitle {
            font-size: 0.8rem;
            color: var(--text-light);
            margin: 0;
            line-height: 1.2;
        }
        .metric-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .metric-value {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .metric-label {
            font-size: 0.8rem;
            opacity: 0.9;
            line-height: 1.2;
        }
        .performance-indicator {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            margin-top: 0.25rem;
            font-size: 0.7rem;
        }
        /* Compress grid spacing for personal dashboard */
        .grid {
            gap: 0.8rem !important;
        }
        .grid.mb-5 {
            margin-bottom: 1.2rem !important;
        }
        /* Compact page header */
        .page-header {
            padding: 0.5rem 0 0.3rem 0 !important;
            margin-bottom: 0 !important;
        }
        .page-title {
            font-size: 1.6rem !important;
            margin-bottom: 0.2rem !important;
        }
        .page-subtitle {
            font-size: 0.85rem !important;
            margin-bottom: 0 !important;
        }
        /* Reduce space between header and analytics */
        .modern-container.analytics-dashboard {
            padding-top: 0.5rem !important;
        }
        /* Compact activity items */
        .activity-item {
            padding: 0.75rem !important;
        }
        .activity-title {
            font-size: 0.9rem !important;
            margin-bottom: 0.25rem !important;
        }
        .activity-description {
            font-size: 0.8rem !important;
            line-height: 1.3 !important;
        }
        /* Compact buttons */
        .btn-modern {
            padding: 0.6rem 1rem !important;
            font-size: 0.85rem !important;
        }
        .btn-modern.btn-lg {
            padding: 0.8rem 1.2rem !important;
            font-size: 0.9rem !important;
        }
        /* Mobile responsiveness for compressed personal dashboard */
        @media (max-width: 768px) {
            .analytics-card {
                padding: 0.75rem;
            }
            .analytics-dashboard .chart-container {
                height: 180px;
            }
            .metric-card {
                padding: 0.75rem;
            }
            .metric-value {
                font-size: 1.2rem;
            }
            .chart-title {
                font-size: 0.9rem;
            }
            .chart-subtitle {
                font-size: 0.75rem;
            }
        }
        @media (max-width: 480px) {
            .grid.grid-cols-3 {
                grid-template-columns: repeat(1, 1fr) !important;
            }
            .grid.grid-cols-2 {
                grid-template-columns: repeat(1, 1fr) !important;
            }
            .analytics-dashboard .chart-container {
                height: 160px;
            }
        }
    </style>
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
            <p class="page-subtitle">Track your performance and key metrics with data insights</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="modern-container analytics-dashboard">
        <!-- Personal Performance Metrics -->
        <div class="grid grid-cols-3 mb-5">
            <div class="metric-card">
                <div class="metric-value">₦<?= number_format($personal_revenue, 2) ?></div>
                <div class="metric-label">Personal Revenue Impact</div>
                <div class="performance-indicator"><?= $personal_revenue_change_text ?></div>
            </div>
            <div class="metric-card clickable-metric" onclick="window.location.href='inventory.php'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                <div class="metric-value"><?= $pending_tasks ?></div>
                <div class="metric-label">Active Tasks</div>
                <div class="performance-indicator">85% completion rate</div>
                <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem;">
                    <i class="bi bi-cursor-fill"></i> Click to view inventory
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $new_leads ?></div>
                <div class="metric-label">Leads Managed</div>
                <div class="performance-indicator">72% conversion rate</div>
            </div>
        </div>

        <!-- Role-Based Analytics Charts -->
        <div class="grid grid-cols-2 mb-5">
            <!-- Personal Performance Trend -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">My Performance Trends</h3>
                        <p class="chart-subtitle">Track your daily productivity and achievements</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="personalPerformanceChart"></canvas>
                </div>
            </div>

            <!-- Task Management Analytics -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Task Management</h3>
                        <p class="chart-subtitle">Your workflow efficiency and completion rates</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="taskAnalyticsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Role-Specific Insights -->
        <?php if ($_SESSION['role'] === 'manager'): ?>
        <div class="grid grid-cols-2 mb-5">
            <!-- Team Performance -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Team Performance Overview</h3>
                        <p class="chart-subtitle">Monitor your team's productivity and growth</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="teamPerformanceChart"></canvas>
                </div>
            </div>

            <!-- Lead Conversion Insights -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Lead Conversion Insights</h3>
                        <p class="chart-subtitle">Track and optimize your conversion strategies</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="conversionChart"></canvas>
                </div>
            </div>
        </div>
        <?php elseif ($_SESSION['role'] === 'inventory_manager'): ?>
        <div class="grid grid-cols-2 mb-5">
            <!-- Inventory Efficiency -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Inventory Efficiency</h3>
                        <p class="chart-subtitle">Track stock movements and optimization</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="inventoryEfficiencyChart"></canvas>
                </div>
            </div>

            <!-- Cost Management -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Cost Management</h3>
                        <p class="chart-subtitle">Monitor inventory costs and savings</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="costManagementChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="grid grid-cols-2">
            <!-- Personal Insights & Activities -->
            <div class="modern-card fade-in">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="bi bi-person-badge"></i>
                        Personal Insights & Activities
                    </h3>
                </div>
                <div class="modern-card-body p-0">
                    <ul class="activity-list">
                        <?php
                        // Personal activities query based on user role and ID
                        $personal_activities_query = "
                            SELECT w.*, 
                                   CASE 
                                       WHEN w.status = 'completed' THEN 'success'
                                       WHEN w.status = 'pending' THEN 'warning' 
                                       WHEN w.status = 'in_progress' THEN 'info'
                                       ELSE 'secondary'
                                   END as badge_class
                            FROM workflows w 
                            WHERE w.created_by = ? OR w.assigned_to = ?
                            ORDER BY w.created_at DESC 
                            LIMIT 6
                        ";
                        $stmt = $pdo->prepare($personal_activities_query);
                        $stmt->execute([$user_id, $user_id]);
                        $activities = $stmt->fetchAll();
                        
                        if (empty($activities)): ?>
                            <li class="activity-item text-center p-4">
                                <div class="activity-content">
                                    <div style="color: var(--text-light);">
                                        <i class="bi bi-graph-up" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        Start tracking your performance!
                                    </div>
                                </div>
                            </li>
                        <?php else:
                            foreach ($activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                        <div class="activity-description">
                                            <?= htmlspecialchars($activity['description']) ?>
                                        </div>
                                        <small style="color: var(--text-light);">
                                            <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                                        </small>
                                    </div>
                                    <span class="activity-badge badge-<?= $activity['badge_class'] ?>">
                                        <?= ucfirst($activity['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach;
                        endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Enhanced Quick Actions -->
            <div class="modern-card fade-in">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="bi bi-lightning"></i>
                        Smart Actions & Tools
                    </h3>
                </div>
                <div class="modern-card-body">
                    <div class="grid grid-cols-1" style="gap: var(--space-md);">
                        <?php if ($_SESSION['role'] === 'manager'): ?>
                            <a href="module_deduction.php" class="btn-modern btn-success btn-lg">
                                <i class="bi bi-cash-stack"></i>
                                Team Sales Analytics
                            </a>
                            <a href="reports.php" class="btn-modern btn-info">
                                <i class="bi bi-graph-up"></i>
                                Team Performance Reports
                            </a>
                            <a href="leads.php" class="btn-modern btn-primary">
                                <i class="bi bi-people"></i>
                                Lead Conversion Dashboard
                            </a>
                        <?php elseif ($_SESSION['role'] === 'inventory_manager'): ?>
                            <a href="inventory.php" class="btn-modern btn-danger btn-lg">
                                <i class="bi bi-box-seam"></i>
                                Inventory Analytics
                            </a>
                            <a href="add_inventory.php" class="btn-modern btn-outline">
                                <i class="bi bi-plus-circle"></i>
                                Add Tracked Items
                            </a>
                            <a href="reports.php" class="btn-modern btn-info">
                                <i class="bi bi-graph-up"></i>
                                Stock Movement Reports
                            </a>
                        <?php else: ?>
                            <a href="module_deduction.php" class="btn-modern btn-success btn-lg">
                                <i class="bi bi-cash-stack"></i>
                                Point of Sale
                            </a>
                            <a href="new_lead.php" class="btn-modern btn-primary">
                                <i class="bi bi-person-plus"></i>
                                Add New Lead
                            </a>
                            <a href="workflows.php" class="btn-modern btn-info">
                                <i class="bi bi-clock"></i>
                                My Tasks & Goals
                            </a>
                        <?php endif; ?>
                        
                        <a href="create_workflow.php" class="btn-modern btn-outline">
                            <i class="bi bi-plus-circle"></i>
                            Create Performance Task
                        </a>
                        
                        <button onclick="window.personalAnalytics?.exportPersonalData()" class="btn-modern btn-secondary">
                            <i class="bi bi-download"></i>
                            Export My Analytics
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Analytics JavaScript -->
    <script src="assets/js/theme-manager.js"></script>
    <script src="assets/js/analytics.js"></script>
    <script>
        // Personal Analytics Dashboard Class Extension
        class PersonalAnalyticsDashboard extends AnalyticsDashboard {
            constructor() {
                super();
                this.userRole = '<?= $_SESSION['role'] ?>';
                this.userId = <?= $user_id ?>;
            }

            renderAllCharts() {
                this.renderPersonalPerformanceChart();
                this.renderTaskAnalyticsChart();
                
                if (this.userRole === 'manager') {
                    this.renderTeamPerformanceChart();
                    this.renderConversionChart();
                } else if (this.userRole === 'inventory_manager') {
                    this.renderInventoryEfficiencyChart();
                    this.renderCostManagementChart();
                }
            }

            renderPersonalPerformanceChart() {
                const ctx = document.getElementById('personalPerformanceChart');
                if (!ctx) return;

                this.charts.personalPerformance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.getLast7Days(),
                        datasets: [{
                            label: 'Daily Performance Score',
                            data: [78, 85, 82, 91, 88, 94, 87],
                            borderColor: this.colors.primary,
                            backgroundColor: this.colors.primary + '20',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Target Performance',
                            data: [80, 80, 80, 80, 80, 80, 80],
                            borderColor: this.colors.warning,
                            borderDash: [5, 5],
                            backgroundColor: 'transparent'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }

            renderTaskAnalyticsChart() {
                const ctx = document.getElementById('taskAnalyticsChart');
                if (!ctx) return;

                this.charts.taskAnalytics = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'In Progress', 'Pending', 'Overdue'],
                        datasets: [{
                            data: [65, 20, 12, 3],
                            backgroundColor: [
                                this.colors.success,
                                this.colors.info,
                                this.colors.warning,
                                this.colors.danger
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            renderTeamPerformanceChart() {
                const ctx = document.getElementById('teamPerformanceChart');
                if (!ctx) return;

                this.charts.teamPerformance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['John D.', 'Sarah M.', 'Mike R.', 'Lisa K.', 'Tom B.'],
                        datasets: [{
                            label: 'Team Member Performance',
                            data: [85, 92, 78, 88, 83],
                            backgroundColor: this.colors.primary + '80',
                            borderColor: this.colors.primary,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }

            renderConversionChart() {
                const ctx = document.getElementById('conversionChart');
                if (!ctx) return;

                this.charts.conversion = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        datasets: [{
                            label: 'Conversion Rate (%)',
                            data: [68, 72, 75, 78],
                            borderColor: this.colors.success,
                            backgroundColor: this.colors.success + '20',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            renderInventoryEfficiencyChart() {
                const ctx = document.getElementById('inventoryEfficiencyChart');
                if (!ctx) return;

                this.charts.inventoryEfficiency = new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: ['Stock Accuracy', 'Turnover Rate', 'Cost Control', 'Order Fulfillment', 'Quality Score'],
                        datasets: [{
                            label: 'Current Performance',
                            data: [88, 76, 82, 94, 79],
                            borderColor: this.colors.primary,
                            backgroundColor: this.colors.primary + '20'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }

            renderCostManagementChart() {
                const ctx = document.getElementById('costManagementChart');
                if (!ctx) return;

                this.charts.costManagement = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Cost Savings (₦)',
                            data: [45000, 52000, 38000, 61000, 48000, 55000],
                            backgroundColor: this.colors.success + '80',
                            borderColor: this.colors.success,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₦' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            exportPersonalData() {
                const personalData = {
                    userId: this.userId,
                    role: this.userRole,
                    performance: this.charts.personalPerformance?.data || null,
                    tasks: this.charts.taskAnalytics?.data || null
                };
                
                const csv = this.convertObjectToCSV(personalData);
                this.downloadCSV(csv, `personal_analytics_${new Date().toISOString().split('T')[0]}.csv`);
            }

            convertObjectToCSV(data) {
                let csv = 'Metric,Value\n';
                csv += `User ID,${data.userId}\n`;
                csv += `Role,${data.role}\n`;
                csv += `Export Date,${new Date().toLocaleDateString()}\n`;
                return csv;
            }
        }

        // Initialize Personal Analytics Dashboard
        let personalAnalytics;
        
        document.addEventListener('DOMContentLoaded', function() {
            personalAnalytics = new PersonalAnalyticsDashboard();
            window.personalAnalytics = personalAnalytics;
        });

        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => location.reload(), 300000);
        
        // Enhanced loading states for buttons
        document.querySelectorAll('.btn-modern').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.href?.includes('#') && !this.onclick) {
                    this.classList.add('loading');
                    this.innerHTML = '<div class="spinner"></div> Loading...';
                }
            });
        });
        
        // Add animation delays for metric cards
        document.querySelectorAll('.metric-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('slide-up');
        });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>