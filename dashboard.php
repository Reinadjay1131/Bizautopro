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

// Calculate total revenue from outbound_sales
$total_revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales");
$total_revenue_query->execute();
$total_revenue = $total_revenue_query->fetchColumn() ?: 0;

// Calculate revenue change (current month vs previous month)
$current_month_revenue_query = $pdo->prepare("
    SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales 
    WHERE MONTH(deduction_date) = MONTH(CURDATE()) AND YEAR(deduction_date) = YEAR(CURDATE())
");
$current_month_revenue_query->execute();
$current_month_revenue = $current_month_revenue_query->fetchColumn() ?: 1;

$previous_month_revenue_query = $pdo->prepare("
    SELECT COALESCE(SUM(price * quantity), 1) FROM outbound_sales 
    WHERE MONTH(deduction_date) = MONTH(CURDATE()) - 1 AND YEAR(deduction_date) = YEAR(CURDATE())
");
$previous_month_revenue_query->execute();
$previous_month_revenue = $previous_month_revenue_query->fetchColumn() ?: 1;

$revenue_change = (($current_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100;
$revenue_change_text = $revenue_change >= 0 ? '↗ +' . number_format($revenue_change, 1) . '% from last month' : '↘ ' . number_format($revenue_change, 1) . '% from last month';
$revenue_change_class = $revenue_change >= 0 ? 'positive' : 'negative';

$inventory_alerts = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM workflows WHERE status = 'pending'")->fetchColumn();
$new_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// Get daily revenue data for charts
$daily_revenue_data = [];
$daily_revenue_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_revenue_labels[] = date('M j', strtotime($date));
    
    $revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales WHERE DATE(deduction_date) = ?");
    $revenue_query->execute([$date]);
    $daily_revenue_data[] = $revenue_query->fetchColumn() ?: 0;
}

// Get leads data for charts
$leads_data = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM leads 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
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
            height: 240px;
            margin-bottom: 1rem;
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
            margin-bottom: 0.75rem;
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
        .date-filter {
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .metric-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .metric-label {
            font-size: 0.8rem;
            opacity: 0.9;
            line-height: 1.2;
        }
        .metric-change {
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }
        .metric-change.positive {
            color: #10b981;
        }
        .metric-change.negative {
            color: #ef4444;
        }
        .export-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.35rem 0.7rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            background: var(--secondary-dark);
        }
        /* Compress grid spacing */
        .grid {
            gap: 1rem !important;
        }
        .grid.mb-5 {
            margin-bottom: 1.5rem !important;
        }
        /* Compact page header */
        .page-header {
            padding: 0.5rem 0 0.3rem 0 !important;
            margin-bottom: 0 !important;
        }
        .page-title {
            font-size: 1.8rem !important;
            margin-bottom: 0.25rem !important;
        }
        .page-subtitle {
            font-size: 0.9rem !important;
            margin-bottom: 0 !important;
        }
        /* Reduce space between header and analytics */
        .modern-container.analytics-dashboard {
            padding-top: 0.5rem !important;
        }
        /* Mobile responsiveness for compressed layout */
        @media (max-width: 768px) {
            .analytics-card {
                padding: 0.75rem;
            }
            .analytics-dashboard .chart-container {
                height: 200px;
            }
            .metric-card {
                padding: 0.75rem;
            }
            .metric-value {
                font-size: 1.4rem;
            }
            .chart-title {
                font-size: 0.9rem;
            }
            .chart-subtitle {
                font-size: 0.75rem;
            }
            .grid {
                gap: 0.75rem !important;
            }
        }
        @media (max-width: 480px) {
            .grid.grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .grid.grid-cols-3 {
                grid-template-columns: repeat(1, 1fr) !important;
            }
            .grid.grid-cols-2 {
                grid-template-columns: repeat(1, 1fr) !important;
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
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p class="page-subtitle">Monitor your business operations with comprehensive data insights</p>
                </div>
                <div>
                    <select id="dateRangeFilter" class="date-filter">
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 3 Months</option>
                        <option value="1y">Last Year</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="modern-container analytics-dashboard">
        <!-- Enhanced KPI Metrics -->
        <div class="grid grid-cols-4 mb-5">
            <div class="metric-card">
                <div class="metric-value">₦<?= number_format($total_revenue, 2) ?></div>
                <div class="metric-label">Total Revenue</div>
                <div class="metric-change <?= $revenue_change_class ?>"><?= $revenue_change_text ?></div>
            </div>
            <div class="metric-card clickable-metric" onclick="window.location.href='inventory.php'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                <div class="metric-value"><?= $inventory_alerts ?></div>
                <div class="metric-label">Active Inventory Items</div>
                <div class="metric-change positive">↗ +3.2% from last week</div>
                <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem;">
                    <i class="bi bi-cursor-fill"></i> Click to view inventory
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $new_leads ?></div>
                <div class="metric-label">New Leads</div>
                <div class="metric-change negative">↘ -5.1% from last month</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">94.2%</div>
                <div class="metric-label">System Efficiency</div>
                <div class="metric-change positive">↗ +2.8% from last quarter</div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-2 mb-5">
            <!-- Revenue Trend Chart -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Revenue Trends & Forecasting</h3>
                        <p class="chart-subtitle">Daily revenue with predictive analytics - Showing real data from your sales</p>
                    </div>
                    <button class="export-btn" onclick="window.analytics?.exportChartData('revenue')">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-info" style="margin-top: 0.5rem; padding: 0.5rem; background-color: #f8f9fa; border-radius: 4px; font-size: 0.8rem; color: #6b7280;">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Real Data:</strong> Total revenue ₦<?= number_format($total_revenue, 2) ?>. Chart shows actual daily sales - zeros indicate no sales on those dates.
                </div>
            </div>

            <!-- Inventory Distribution -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Inventory Distribution</h3>
                        <p class="chart-subtitle">Stock levels across categories</p>
                    </div>
                    <button class="export-btn" onclick="window.analytics?.exportChartData('inventory')">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Second Row Charts -->
        <div class="grid grid-cols-2 mb-5">
            <!-- Leads Conversion Analysis -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Leads Conversion Analysis</h3>
                        <p class="chart-subtitle">Monthly lead generation and conversion rates</p>
                    </div>
                    <button class="export-btn" onclick="window.analytics?.exportChartData('leads')">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="leadsChart"></canvas>
                </div>
            </div>

            <!-- Workflow Performance -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Workflow Performance</h3>
                        <p class="chart-subtitle">Task completion and status distribution</p>
                    </div>
                    <button class="export-btn" onclick="window.analytics?.exportChartData('workflow')">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="workflowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Third Row Charts -->
        <div class="grid grid-cols-3 mb-5">
            <!-- User Activity Heatmap -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">User Activity</h3>
                        <p class="chart-subtitle">Daily active users pattern</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="userActivityChart"></canvas>
                </div>
            </div>

            <!-- Performance Radar -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Performance Metrics</h3>
                        <p class="chart-subtitle">Multi-dimensional performance analysis</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>

            <!-- Predictive Analytics -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <div>
                        <h3 class="chart-title">Predictive Insights</h3>
                        <p class="chart-subtitle">AI-powered business forecasting</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="predictiveChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-2">
            <!-- Recent Activities -->
            <div class="modern-card fade-in">
                <div class="modern-card-header">
                    <h3 class="modern-card-title">
                        <i class="bi bi-activity"></i>
                        Recent Activities & Insights
                    </h3>
                </div>
                <div class="modern-card-body p-0">
                    <ul class="activity-list">
                        <?php
                        // Enhanced activity query with more insights
                        $activities_query = "
                            SELECT w.*, u.username, 
                                   CASE 
                                       WHEN w.status = 'completed' THEN 'success'
                                       WHEN w.status = 'pending' THEN 'warning' 
                                       WHEN w.status = 'in_progress' THEN 'info'
                                       ELSE 'secondary'
                                   END as badge_class
                            FROM workflows w 
                            LEFT JOIN users u ON w.created_by = u.id 
                            ORDER BY w.created_at DESC 
                            LIMIT 8
                        ";
                        $activities = $pdo->query($activities_query)->fetchAll();
                        
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
                                        <div class="activity-description">
                                            <?= htmlspecialchars($activity['description']) ?>
                                            <?php if ($activity['username']): ?>
                                                <small style="color: var(--text-light);"> • by <?= htmlspecialchars($activity['username']) ?></small>
                                            <?php endif; ?>
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
                        Quick Actions & Analytics Tools
                    </h3>
                </div>
                <div class="modern-card-body">
                    <div class="grid grid-cols-1" style="gap: var(--space-md);">
                        <a href="module_deduction.php" class="btn-modern btn-success btn-lg">
                            <i class="bi bi-cash-stack"></i>
                            Point of Sale Analytics
                        </a>
                        <a href="reports.php" class="btn-modern btn-info">
                            <i class="bi bi-graph-up"></i>
                            Advanced Reports & Insights
                        </a>
                        <button onclick="window.analytics?.exportAllData()" class="btn-modern btn-primary">
                            <i class="bi bi-download"></i>
                            Export All Analytics Data
                        </button>
                        <a href="create_workflow.php" class="btn-modern btn-outline">
                            <i class="bi bi-plus-circle"></i>
                            Create Performance Task
                        </a>
                        <a href="add_inventory.php" class="btn-modern btn-danger">
                            <i class="bi bi-box-seam"></i>
                            Add Tracked Inventory
                        </a>
                        <a href="new_lead.php" class="btn-modern btn-success">
                            <i class="bi bi-person-plus"></i>
                            Add Lead with Analytics
                        </a>
                        <a href="view_pending_users.php" class="btn-modern btn-warning" style="position: relative;">
                            <i class="bi bi-people"></i>
                            Manage User Analytics
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

    <!-- Analytics JavaScript -->
    <script src="assets/js/theme-manager.js"></script>
    <script>
        // Embed real data from PHP into JavaScript
        const realChartData = {
            revenue: {
                labels: <?= json_encode($daily_revenue_labels) ?>,
                datasets: [{
                    label: 'Daily Revenue (₦)',
                    data: <?= json_encode(array_map('floatval', $daily_revenue_data)) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: '#4f46e520',
                    tension: 0.4,
                    fill: true
                }]
            },
            leads: {
                labels: <?= json_encode(array_keys($leads_data)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($leads_data)) ?>,
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444']
                }]
            }
        };

        // Initialize analytics dashboard with real data
        let analytics;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts with real data immediately
            initializeChartsWithRealData();
        });

        function initializeChartsWithRealData() {
            console.log('📊 Loading charts with real database data:', realChartData);
            
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: realChartData.revenue,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Daily Revenue Trends'
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
                console.log('✅ Revenue chart loaded with real data');
            }

            // Leads Chart  
            const leadsCtx = document.getElementById('leadsChart');
            if (leadsCtx) {
                new Chart(leadsCtx, {
                    type: 'doughnut',
                    data: realChartData.leads,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Lead Status Distribution'
                            }
                        }
                    }
                });
                console.log('✅ Leads chart loaded with real data');
            }
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
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>