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

// Get personal performance data (last 7 days)
$personal_performance_data = [];
$personal_performance_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $personal_performance_labels[] = date('M j', strtotime($date));
    
    // Count personal activities (sales, leads created, tasks completed)
    $personal_query = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM outbound_sales WHERE user_id = ? AND DATE(deduction_date) = ?) +
            (SELECT COUNT(*) FROM leads WHERE created_by = ? AND DATE(created_at) = ?) +
            (SELECT COUNT(*) FROM workflows WHERE created_by = ? AND DATE(created_at) = ? AND status = 'completed') as total_activity
    ");
    $personal_query->execute([$user_id, $date, $user_id, $date, $user_id, $date]);
    $personal_performance_data[] = $personal_query->fetchColumn() ?: 0;
}

// Get task management data - Personal workflow analytics
$task_data = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM workflows 
    WHERE assigned_to = ? OR completed_by = ?
    GROUP BY status
");
$task_data->execute([$user_id, $user_id]);
$task_analytics = $task_data->fetchAll(PDO::FETCH_KEY_PAIR);

// Enhanced Personal Workflow Analytics
$personal_workflow_analytics = [];

// Personal completion metrics
$personal_completion = $pdo->prepare("
    SELECT 
        COUNT(*) as total_assigned,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN due_date < NOW() AND status NOT IN ('completed', 'cancelled') THEN 1 END) as overdue,
        AVG(CASE WHEN status = 'completed' AND actual_hours IS NOT NULL THEN actual_hours END) as avg_completion_time,
        AVG(CASE WHEN status = 'completed' AND estimated_hours IS NOT NULL AND actual_hours IS NOT NULL 
            THEN actual_hours - estimated_hours END) as avg_time_variance
    FROM workflows 
    WHERE assigned_to = ?
");
$personal_completion->execute([$user_id]);
$completion_metrics = $personal_completion->fetch();

$personal_workflow_analytics['total_assigned'] = $completion_metrics['total_assigned'] ?? 0;
$personal_workflow_analytics['completed'] = $completion_metrics['completed'] ?? 0;
$personal_workflow_analytics['completion_rate'] = $completion_metrics['total_assigned'] > 0 ? 
    round(($completion_metrics['completed'] / $completion_metrics['total_assigned']) * 100, 1) : 0;
$personal_workflow_analytics['overdue'] = $completion_metrics['overdue'] ?? 0;
$personal_workflow_analytics['avg_completion_time'] = round($completion_metrics['avg_completion_time'] ?? 0, 1);
$personal_workflow_analytics['avg_time_variance'] = round($completion_metrics['avg_time_variance'] ?? 0, 1);

// Personal priority distribution
$priority_stmt = $pdo->prepare("
    SELECT priority, COUNT(*) as count 
    FROM workflows 
    WHERE assigned_to = ?
    GROUP BY priority
");
$priority_stmt->execute([$user_id]);
$personal_workflow_analytics['priority_distribution'] = $priority_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Personal weekly completion trend
$personal_weekly_data = [];
$personal_weekly_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $personal_weekly_labels[] = date('M j', strtotime($date));
    
    $daily_completion = $pdo->prepare("
        SELECT COUNT(*) FROM workflows 
        WHERE (assigned_to = ? OR completed_by = ?) 
        AND status = 'completed' 
        AND DATE(completion_date) = ?
    ");
    $daily_completion->execute([$user_id, $user_id, $date]);
    $personal_weekly_data[] = $daily_completion->fetchColumn() ?: 0;
}

// Role-specific analytics
$role_specific_data = [];
if ($_SESSION['role'] === 'manager') {
    // Team performance data
    $team_performance = $pdo->prepare("
        SELECT 
            u.username,
            COUNT(DISTINCT os.id) as sales_count,
            COALESCE(SUM(os.price * os.quantity), 0) as revenue
        FROM users u
        LEFT JOIN outbound_sales os ON u.id = os.user_id AND DATE(os.deduction_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        WHERE u.role IN ('employee', 'inventory_manager')
        GROUP BY u.id, u.username
        LIMIT 5
    ");
    $team_performance->execute();
    $role_specific_data['team'] = $team_performance->fetchAll(PDO::FETCH_ASSOC);
    
    // Conversion analytics
    $conversion_data = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM leads 
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY status
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $role_specific_data['conversion'] = $conversion_data;
    
} elseif ($_SESSION['role'] === 'inventory_manager') {
    // Inventory efficiency data
    $inventory_efficiency = $pdo->query("
        SELECT 
            CASE 
                WHEN quantity <= 10 THEN 'Critical'
                WHEN quantity <= 30 THEN 'Low'
                WHEN quantity <= 100 THEN 'Optimal'
                ELSE 'Excess'
            END as efficiency_level,
            COUNT(*) as count
        FROM inventory
        GROUP BY 
            CASE 
                WHEN quantity <= 10 THEN 'Critical'
                WHEN quantity <= 30 THEN 'Low'
                WHEN quantity <= 100 THEN 'Optimal'
                ELSE 'Excess'
            END
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $role_specific_data['inventory'] = $inventory_efficiency;
    
    // Cost management data (last 7 days inventory movements)
    $cost_data = [];
    $cost_labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $cost_labels[] = date('M j', strtotime($date));
        
        $cost_query = $pdo->prepare("
            SELECT COALESCE(SUM(price * quantity), 0) 
            FROM inventory_transactions 
            WHERE DATE(transaction_date) = ? AND transaction_type = 'outbound'
        ");
        $cost_query->execute([$date]);
        $cost_data[] = $cost_query->fetchColumn() ?: 0;
    }
    $role_specific_data['cost_data'] = $cost_data;
    $role_specific_data['cost_labels'] = $cost_labels;
}
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
        .performance-indicator.positive {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .performance-indicator.negative {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
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
        <!-- Personal Workflow Performance Metrics -->
        <div class="grid grid-cols-4 mb-5">
            <div class="metric-card">
                <div class="metric-value">₦<?= number_format($personal_revenue, 2) ?></div>
                <div class="metric-label">Personal Revenue Impact</div>
                <div class="performance-indicator"><?= $personal_revenue_change_text ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $personal_workflow_analytics['total_assigned'] ?></div>
                <div class="metric-label">Assigned Tasks</div>
                <div class="performance-indicator"><?= $personal_workflow_analytics['completion_rate'] ?>% completion rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $personal_workflow_analytics['avg_completion_time'] ?>h</div>
                <div class="metric-label">Avg Completion Time</div>
                <div class="performance-indicator <?= $personal_workflow_analytics['avg_time_variance'] >= 0 ? 'negative' : 'positive' ?>">
                    <?= $personal_workflow_analytics['avg_time_variance'] >= 0 ? '+' : '' ?><?= $personal_workflow_analytics['avg_time_variance'] ?>h variance
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $personal_workflow_analytics['overdue'] ?></div>
                <div class="metric-label">Overdue Tasks</div>
                <div class="performance-indicator <?= $personal_workflow_analytics['overdue'] > 0 ? 'negative' : 'positive' ?>">
                    <?= $personal_workflow_analytics['overdue'] > 0 ? 'Action needed' : 'On track' ?>
                </div>
            </div>
        </div>

        <!-- Role-Based Analytics Charts -->
        <!-- Personal Analytics Charts -->
        <div class="grid grid-cols-3 mb-5">
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
                        <h3 class="chart-title">Task Status Distribution</h3>
                        <p class="chart-subtitle">Your workflow efficiency and completion rates</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="taskAnalyticsChart"></canvas>
                </div>
            </div>

            <!-- Priority Distribution -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Task Priorities</h3>
                        <p class="chart-subtitle">Distribution of assigned task priorities</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="priorityChart"></canvas>
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

    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Personal Analytics JavaScript -->
    <script src="assets/js/theme-manager.js"></script>
    <script>
        // Embed personal analytics data from PHP into JavaScript
        const personalChartData = {
            weeklyCompletion: {
                labels: <?= json_encode($personal_weekly_labels) ?>,
                datasets: [{
                    label: 'Completed Tasks',
                    data: <?= json_encode($personal_weekly_data) ?>,
                    borderColor: '#10b981',
                    backgroundColor: '#10b98120',
                    tension: 0.4,
                    fill: true
                }]
            },
            tasks: {
                labels: <?= json_encode(array_keys($task_analytics)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($task_analytics)) ?>,
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6']
                }]
            },
            priority: {
                labels: <?= json_encode(array_keys($personal_workflow_analytics['priority_distribution'])) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($personal_workflow_analytics['priority_distribution'])) ?>,
                    backgroundColor: ['#22c55e', '#fbbf24', '#f97316', '#ef4444']
                }]
            }
            <?php if ($_SESSION['role'] === 'manager'): ?>
            ,team: {
                labels: <?= json_encode(array_column($role_specific_data['team'], 'username')) ?>,
                datasets: [{
                    label: 'Sales Count',
                    data: <?= json_encode(array_column($role_specific_data['team'], 'sales_count')) ?>,
                    backgroundColor: '#06b6d4'
                }]
            },
            conversion: {
                labels: <?= json_encode(array_keys($role_specific_data['conversion'])) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($role_specific_data['conversion'])) ?>,
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444']
                }]
            }
            <?php elseif ($_SESSION['role'] === 'inventory_manager'): ?>
            ,inventory: {
                labels: <?= json_encode(array_keys($role_specific_data['inventory'])) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($role_specific_data['inventory'])) ?>,
                    backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6']
                }]
            },
            cost: {
                labels: <?= json_encode($role_specific_data['cost_labels']) ?>,
                datasets: [{
                    label: 'Daily Costs (₦)',
                    data: <?= json_encode($role_specific_data['cost_data']) ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: '#f59e0b20',
                    tension: 0.4,
                    fill: true
                }]
            }
            <?php endif; ?>
        };

        // Initialize personal analytics dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializePersonalCharts();
        });

        function initializePersonalCharts() {
            console.log('📊 Loading personal charts with real data:', personalChartData);
            
            // Personal Performance Chart (Weekly Completion Trend)
            const personalCtx = document.getElementById('personalPerformanceChart');
            if (personalCtx) {
                try {
                    new Chart(personalCtx, {
                        type: 'line',
                        data: personalChartData.weeklyCompletion,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'My Weekly Task Completions'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    console.log('✅ Personal performance chart loaded');
                } catch (error) {
                    console.error('❌ Personal performance chart error:', error);
                }
            }

            // Task Analytics Chart
            const taskCtx = document.getElementById('taskAnalyticsChart');
            if (taskCtx) {
                try {
                    new Chart(taskCtx, {
                        type: 'doughnut',
                        data: personalChartData.tasks,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'My Task Distribution'
                                }
                            }
                        }
                    });
                    console.log('✅ Task analytics chart loaded');
                } catch (error) {
                    console.error('❌ Task analytics chart error:', error);
                }
            }

            // Priority Distribution Chart
            const priorityCtx = document.getElementById('priorityChart');
            if (priorityCtx) {
                try {
                    new Chart(priorityCtx, {
                        type: 'doughnut',
                        data: personalChartData.priority,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Task Priority Distribution'
                                }
                            }
                        }
                    });
                    console.log('✅ Priority chart loaded');
                } catch (error) {
                    console.error('❌ Priority chart error:', error);
                }
            }

            <?php if ($_SESSION['role'] === 'manager'): ?>
            // Team Performance Chart
            const teamCtx = document.getElementById('teamPerformanceChart');
            if (teamCtx) {
                try {
                    new Chart(teamCtx, {
                        type: 'bar',
                        data: personalChartData.team,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Team Sales Performance'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    console.log('✅ Team performance chart loaded');
                } catch (error) {
                    console.error('❌ Team performance chart error:', error);
                }
            }

            // Conversion Chart
            const conversionCtx = document.getElementById('conversionChart');
            if (conversionCtx) {
                try {
                    new Chart(conversionCtx, {
                        type: 'pie',
                        data: personalChartData.conversion,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Lead Conversion Status'
                                }
                            }
                        }
                    });
                    console.log('✅ Conversion chart loaded');
                } catch (error) {
                    console.error('❌ Conversion chart error:', error);
                }
            }
            <?php elseif ($_SESSION['role'] === 'inventory_manager'): ?>
            // Inventory Efficiency Chart
            const inventoryCtx = document.getElementById('inventoryEfficiencyChart');
            if (inventoryCtx) {
                try {
                    new Chart(inventoryCtx, {
                        type: 'doughnut',
                        data: personalChartData.inventory,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Inventory Efficiency Levels'
                                }
                            }
                        }
                    });
                    console.log('✅ Inventory efficiency chart loaded');
                } catch (error) {
                    console.error('❌ Inventory efficiency chart error:', error);
                }
            }

            // Cost Management Chart
            const costCtx = document.getElementById('costManagementChart');
            if (costCtx) {
                try {
                    new Chart(costCtx, {
                        type: 'line',
                        data: personalChartData.cost,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Daily Cost Management'
                                }
                            },
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
                    console.log('✅ Cost management chart loaded');
                } catch (error) {
                    console.error('❌ Cost management chart error:', error);
                }
            }
            <?php endif; ?>
        }

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
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>