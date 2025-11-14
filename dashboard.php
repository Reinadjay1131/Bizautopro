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
require_once 'includes/theme-loader.php';

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

// Get inventory distribution data
$inventory_data = $pdo->query("
    SELECT 
        CASE 
            WHEN quantity <= 10 THEN 'Low Stock'
            WHEN quantity <= 50 THEN 'Medium Stock' 
            WHEN quantity <= 100 THEN 'High Stock'
            ELSE 'Overstocked'
        END as stock_level,
        COUNT(*) as count
    FROM inventory 
    GROUP BY 
        CASE 
            WHEN quantity <= 10 THEN 'Low Stock'
            WHEN quantity <= 50 THEN 'Medium Stock'
            WHEN quantity <= 100 THEN 'High Stock'
            ELSE 'Overstocked'
        END
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Get workflow performance data
$workflow_data = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM workflows 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Enhanced Workflow Analytics
$workflow_analytics = [];

// Completion Rate Analytics
$total_workflows = $pdo->query("SELECT COUNT(*) FROM workflows")->fetchColumn();
$completed_workflows = $pdo->query("SELECT COUNT(*) FROM workflows WHERE status = 'completed'")->fetchColumn();
$workflow_analytics['completion_rate'] = $total_workflows > 0 ? round(($completed_workflows / $total_workflows) * 100, 1) : 0;

// Priority Distribution
$workflow_analytics['priority_distribution'] = $pdo->query("
    SELECT 
        COALESCE(priority, 'unassigned') as priority, 
        COUNT(*) as count 
    FROM workflows 
    GROUP BY priority
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Time Tracking Analytics
$time_analytics = $pdo->query("
    SELECT 
        AVG(estimated_hours) as avg_estimated,
        AVG(actual_hours) as avg_actual,
        COUNT(CASE WHEN actual_hours IS NOT NULL THEN 1 END) as completed_with_time,
        AVG(CASE WHEN actual_hours IS NOT NULL AND estimated_hours IS NOT NULL 
            THEN actual_hours - estimated_hours END) as avg_variance
    FROM workflows 
    WHERE status = 'completed'
")->fetch();

$workflow_analytics['time_tracking'] = [
    'avg_estimated' => round($time_analytics['avg_estimated'] ?? 0, 1),
    'avg_actual' => round($time_analytics['avg_actual'] ?? 0, 1),
    'completion_accuracy' => $time_analytics['completed_with_time'] ?? 0,
    'avg_variance' => round($time_analytics['avg_variance'] ?? 0, 1)
];

// Overdue Analytics
$overdue_analytics = $pdo->query("
    SELECT 
        COUNT(*) as total_overdue,
        AVG(DATEDIFF(NOW(), due_date)) as avg_overdue_days,
        COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_overdue,
        COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_overdue
    FROM workflows 
    WHERE due_date < NOW() AND status NOT IN ('completed', 'cancelled')
")->fetch();

$workflow_analytics['overdue'] = [
    'total' => $overdue_analytics['total_overdue'] ?? 0,
    'avg_days' => round($overdue_analytics['avg_overdue_days'] ?? 0, 1),
    'urgent_count' => $overdue_analytics['urgent_overdue'] ?? 0,
    'high_count' => $overdue_analytics['high_overdue'] ?? 0
];

// Due Today and Due This Week Analytics
$due_today = $pdo->query("
    SELECT COUNT(*) FROM workflows 
    WHERE DATE(due_date) = CURDATE() AND status NOT IN ('completed', 'cancelled')
")->fetchColumn() ?: 0;

$due_this_week = $pdo->query("
    SELECT COUNT(*) FROM workflows 
    WHERE YEARWEEK(due_date, 1) = YEARWEEK(CURDATE(), 1) 
    AND due_date >= CURDATE() 
    AND status NOT IN ('completed', 'cancelled')
")->fetchColumn() ?: 0;

$workflow_analytics['due_summary'] = [
    'today' => $due_today,
    'this_week' => $due_this_week
];

// Daily Workflow Completion Trends (last 7 days)
$workflow_completion_data = [];
$workflow_completion_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $workflow_completion_labels[] = date('M j', strtotime($date));
    
    $completions = $pdo->prepare("
        SELECT COUNT(*) FROM workflows 
        WHERE DATE(completion_date) = ? AND status = 'completed'
    ");
    $completions->execute([$date]);
    $workflow_completion_data[] = $completions->fetchColumn() ?: 0;
}

// Category Performance Analytics
$category_performance = $pdo->query("
    SELECT 
        COALESCE(category, 'Uncategorized') as category,
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        AVG(CASE WHEN status = 'completed' AND actual_hours IS NOT NULL 
            THEN actual_hours END) as avg_completion_time
    FROM workflows 
    GROUP BY category
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

$workflow_analytics['category_performance'] = $category_performance;

// User Performance Analytics
$user_performance = $pdo->query("
    SELECT 
        u.username,
        COUNT(w.id) as assigned_tasks,
        COUNT(CASE WHEN w.status = 'completed' THEN 1 END) as completed_tasks,
        AVG(CASE WHEN w.status = 'completed' AND w.actual_hours IS NOT NULL 
            THEN w.actual_hours END) as avg_completion_time
    FROM users u
    LEFT JOIN workflows w ON u.id = w.assigned_to
    WHERE u.status = 'approved'
    GROUP BY u.id, u.username
    HAVING assigned_tasks > 0
    ORDER BY completed_tasks DESC
    LIMIT 10
")->fetchAll();

$workflow_analytics['user_performance'] = $user_performance;

// Get user activity data (last 7 days)
$user_activity_data = [];
$user_activity_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $user_activity_labels[] = date('M j', strtotime($date));
    
    // Count unique users who performed actions (outbound_sales, leads, workflows)
    $activity_query = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM outbound_sales WHERE DATE(deduction_date) = ?
            UNION 
            SELECT created_by as user_id FROM leads WHERE DATE(created_at) = ?
            UNION
            SELECT created_by as user_id FROM workflows WHERE DATE(created_at) = ?
        ) as combined_activity
    ");
    $activity_query->execute([$date, $date, $date]);
    $user_activity_data[] = $activity_query->fetchColumn() ?: 0;
}

// Get performance metrics data (for radar chart)
$performance_metrics = [
    'Sales' => $pdo->query("SELECT COUNT(*) FROM outbound_sales WHERE DATE(deduction_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    'Leads' => $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn(),
    'Inventory' => $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity > 10")->fetchColumn(),
    'Workflows' => $pdo->query("SELECT COUNT(*) FROM workflows WHERE status = 'completed'")->fetchColumn(),
    'Users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn()
];

// Normalize performance metrics to 0-100 scale
$max_value = max($performance_metrics);
if ($max_value > 0) {
    foreach ($performance_metrics as $key => $value) {
        $performance_metrics[$key] = round(($value / $max_value) * 100);
    }
}

// Get predictive analytics data (revenue forecast)
$predictive_data = [];
$predictive_labels = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $predictive_labels[] = date('M j', strtotime($date));
    
    // Simple forecasting based on average of last 7 days with trend
    $avg_revenue = array_sum($daily_revenue_data) / count($daily_revenue_data);
    $trend = ($daily_revenue_data[count($daily_revenue_data)-1] - $daily_revenue_data[0]) / count($daily_revenue_data);
    $predictive_data[] = round($avg_revenue + ($trend * $i), 2);
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
    <?php loadThemeSystem(); ?>
    <style>
        .analytics-dashboard .chart-container {
            position: relative;
            height: 240px;
            margin-bottom: 1rem;
        }
        .analytics-card {
            background: transparent;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(241,245,249,0.3);
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
            background: rgba(79, 70, 229, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(79, 70, 229, 0.2);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }
        .metric-label {
            font-size: 0.8rem;
            opacity: 0.8;
            line-height: 1.2;
            color: var(--text-light);
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
            display: grid;
            gap: 1rem !important;
        }
        
        .grid-cols-4 {
            grid-template-columns: repeat(4, 1fr) !important;
        }
        
        .grid-cols-3 {
            grid-template-columns: repeat(3, 1fr) !important;
        }
        
        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        
        .grid.mb-5 {
            margin-bottom: 1.5rem !important;
        }
        
        .grid.mb-4 {
            margin-bottom: 1rem !important;
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
        
        /* Compact KPI Cards */
        .metric-card.compact-kpi {
            padding: 0.75rem 0.5rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid transparent;
            transition: all 0.3s ease;
            color: white;
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100px;
        }
        
        .metric-card.compact-kpi .metric-value {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        
        .metric-card.compact-kpi .metric-label {
            font-size: 0.75rem;
            opacity: 0.9;
            line-height: 1.1;
            margin-bottom: 0.15rem;
        }
        
        .metric-card.compact-kpi .metric-change {
            font-size: 0.65rem;
            opacity: 0.8;
        }
        
        /* Theme-aware KPI styles */
        .success-theme {
            background: linear-gradient(135deg, var(--success-color), #16a34a);
        }
        
        .danger-theme {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
        }
        
        .warning-theme {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
        }
        
        .info-theme {
            background: linear-gradient(135deg, var(--info-color), #2563eb);
        }
        
        .primary-theme {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }
        
        .secondary-theme {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-dark));
        }
        
        /* Dark mode adjustments */
        [data-theme="dark"] .metric-card.compact-kpi {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .success-theme {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        [data-theme="dark"] .danger-theme {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        [data-theme="dark"] .warning-theme {
            background: linear-gradient(135deg, #d97706, #b45309);
        }
        
        [data-theme="dark"] .info-theme {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        
        /* Hover effects for clickable KPIs */
        .metric-card.compact-kpi.clickable-metric:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .metric-change.warning {
            color: #fbbf24;
        }
        
        .metric-change.neutral {
            color: rgba(255, 255, 255, 0.8);
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
            .grid.grid-cols-6 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .grid.grid-cols-3 {
                grid-template-columns: repeat(1, 1fr) !important;
            }
            .grid.grid-cols-2 {
                grid-template-columns: repeat(1, 1fr) !important;
            }
        }
        
        @media (max-width: 768px) {
            .grid.grid-cols-6 {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        
        @media (max-width: 992px) {
            .grid.grid-cols-6 {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        
        /* Workflow Analytics Styles */
        .analytics-section {
            border-top: 2px solid var(--border-color);
            padding-top: 2rem;
            margin-top: 2rem;
        }
        
        .performance-table {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .performance-table table {
            margin-bottom: 0;
            font-size: 0.875rem;
        }
        
        .performance-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.8rem;
            padding: 0.5rem;
            border-bottom: 2px solid #dee2e6;
        }
        
        .performance-table td {
            font-size: 0.85rem;
            padding: 0.5rem;
        }
        
        .metric-card .metric-change.positive {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .metric-card .metric-change.negative {
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* Modern Dashboard Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-up {
            animation: slideUp 0.6s ease-out forwards;
        }
        
        /* Hover Effects for KPI Cards */
        .kpi-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        .kpi-card:hover {
            transform: translateY(-8px) scale(1.02) !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2) !important;
        }
        
        /* Chart Card Transitions */
        .chart-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        /* Export Button Hover */
        .export-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2) !important;
        }
        
        /* Date Filter Styling */
        #dateRangeFilter:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        #dateRangeFilter:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        }
        
        /* Loading Animation */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(59, 130, 246, 0.3);
            border-radius: 50%;
            border-top: 3px solid #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .kpi-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            }
            
            .charts-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }
            
            .top-header {
                padding: 1rem !important;
            }
            
            .top-header > div {
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            .dashboard-content {
                padding: 1rem !important;
            }
            
            .kpi-card {
                padding: 1.5rem !important;
            }
        }
        
        /* Enhanced Header Styles */
        .date-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
            border-color: rgba(255,255,255,0.4) !important;
        }
        
        .date-filter:focus {
            outline: none;
            border-color: rgba(255,255,255,0.5) !important;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2) !important;
        }
        
        .user-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
            background: rgba(255,255,255,0.25) !important;
        }
        
        /* Header Animation */
        .top-header {
            animation: headerSlide 0.8s ease-out;
        }
        
        @keyframes headerSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Chart Animations */
        .all-charts-grid {
            animation: chartsSlideIn 1.2s ease-out;
        }
        
        .chart-card, .analytics-card {
            opacity: 0;
            animation: chartFadeInUp 0.8s ease-out forwards;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Staggered animation delays for each chart */
        .chart-card:nth-child(1), .analytics-card:nth-child(1) { animation-delay: 0.1s; }
        .chart-card:nth-child(2), .analytics-card:nth-child(2) { animation-delay: 0.2s; }
        .chart-card:nth-child(3), .analytics-card:nth-child(3) { animation-delay: 0.3s; }
        .chart-card:nth-child(4), .analytics-card:nth-child(4) { animation-delay: 0.4s; }
        .chart-card:nth-child(5), .analytics-card:nth-child(5) { animation-delay: 0.5s; }
        .chart-card:nth-child(6), .analytics-card:nth-child(6) { animation-delay: 0.6s; }
        .chart-card:nth-child(7), .analytics-card:nth-child(7) { animation-delay: 0.7s; }
        .chart-card:nth-child(8), .analytics-card:nth-child(8) { animation-delay: 0.8s; }
        .chart-card:nth-child(9), .analytics-card:nth-child(9) { animation-delay: 0.9s; }
        
        @keyframes chartsSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes chartFadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Hover animations */
        .chart-card:hover, .analytics-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .chart-card:hover .export-btn {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        
        /* Chart container animations */
        .chart-container {
            position: relative;
            overflow: hidden;
        }
        
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s ease-in-out infinite;
            z-index: 1;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }
        
        /* Chart title animations */
        .chart-title, h3 {
            animation: titleSlideIn 0.8s ease-out forwards;
            animation-delay: 0.2s;
            opacity: 0;
        }
        
        .chart-subtitle, p {
            animation: subtitleFadeIn 1s ease-out forwards;
            animation-delay: 0.4s;
            opacity: 0;
        }
        
        @keyframes titleSlideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes subtitleFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Export button animations */
        .export-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .export-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.5s ease;
        }
        
        .export-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .export-btn:active {
            transform: scale(0.95);
        }
        
        /* Loading skeleton animation for charts */
        .chart-loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
    </style>
</head>
<body class="fade-in">
    <!-- Include Dark Sidebar Navigation -->
    <?php require_once 'includes/navigation.php'; ?>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Modern Top Header -->
            <div class="top-header" style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 2rem 1.5rem 2rem 1rem; 
                box-shadow: 0 8px 32px rgba(102, 126, 234, 0.15);
                border-bottom: none;
                position: relative;
                overflow: hidden;
            ">
                <!-- Decorative Background Elements -->
                <div style="
                    position: absolute; 
                    top: -50%; 
                    right: -10%; 
                    width: 200px; 
                    height: 200px; 
                    background: rgba(255,255,255,0.1); 
                    border-radius: 50%; 
                    filter: blur(40px);
                "></div>
                <div style="
                    position: absolute; 
                    bottom: -30%; 
                    left: -5%; 
                    width: 150px; 
                    height: 150px; 
                    background: rgba(255,255,255,0.08); 
                    border-radius: 50%; 
                    filter: blur(30px);
                "></div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 2;">
                    <div style="max-width: 60%;">
                        <h1 style="
                            font-size: 2.5rem; 
                            font-weight: 800; 
                            color: white; 
                            margin: 0; 
                            margin-bottom: 0.75rem;
                            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            letter-spacing: -0.025em;
                        ">Analytics Dashboard</h1>
                        <p style="
                            color: rgba(255,255,255,0.9); 
                            margin: 0; 
                            font-size: 1.125rem;
                            font-weight: 400;
                            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                            line-height: 1.5;
                        ">Monitor your business operations with comprehensive data insights</p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 2;">
                        <select id="dateRangeFilter" class="date-filter" style="
                            padding: 1rem 1.25rem; 
                            border: 2px solid rgba(255,255,255,0.2); 
                            border-radius: 16px; 
                            background: rgba(255,255,255,0.15); 
                            backdrop-filter: blur(10px);
                            font-weight: 600; 
                            color: white; 
                            cursor: pointer; 
                            transition: all 0.3s ease;
                            font-size: 0.95rem;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                        ">
                            <option value="7d" style="background: #667eea; color: white;">Last 7 Days</option>
                            <option value="30d" style="background: #667eea; color: white;">Last 30 Days</option>
                            <option value="90d" style="background: #667eea; color: white;">Last 3 Months</option>
                            <option value="1y" style="background: #667eea; color: white;">Last Year</option>
                        </select>
                        <div class="user-info" style="
                            background: rgba(255,255,255,0.2); 
                            backdrop-filter: blur(10px);
                            color: white; 
                            padding: 1rem 1.5rem; 
                            border-radius: 16px; 
                            font-weight: 600;
                            border: 2px solid rgba(255,255,255,0.15);
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                        ">
                            <i class="bi bi-person-circle" style="font-size: 1.25rem;"></i>
                            <span><?= htmlspecialchars($user['username']) ?> (<?= ucfirst($user['role']) ?>)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content" style="padding: 0rem 1rem 2rem 0rem;">
                <!-- Notification Widget -->
                <?php 
                require_once 'notification_widget.php';
                $notification_widget = new NotificationWidget($pdo, $user_id, $_SESSION['role']);
                echo $notification_widget->render();
                ?>
                
                <!-- Modern KPI Cards -->
                <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1rem; margin-top: 0.5rem;">
                    <!-- Revenue Card -->
                    <div class="kpi-card" style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3); position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); pointer-events: none;"></div>
                        <div style="position: relative; z-index: 2;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <i class="bi bi-currency-dollar" style="font-size: 2rem; opacity: 0.8;"></i>
                                <span style="font-size: 0.875rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px;">Revenue</span>
                            </div>
                            <h3 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem 0;">₦<?= number_format($total_revenue/1000, 0) ?>K</h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
                                <i class="bi bi-<?= $revenue_change >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= $revenue_change >= 0 ? '+' : '' ?><?= number_format($revenue_change, 1) ?>% from last period
                            </p>
                        </div>
                    </div>

                    <!-- Workflows Card -->
                    <div class="kpi-card" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3); position: relative; overflow: hidden; cursor: pointer;" onclick="window.location.href='workflows.php'">
                        <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); pointer-events: none;"></div>
                        <div style="position: relative; z-index: 2;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <i class="bi bi-diagram-3-fill" style="font-size: 2rem; opacity: 0.8;"></i>
                                <span style="font-size: 0.875rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px;">Overdue</span>
                            </div>
                            <h3 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem 0;"><?= $workflow_analytics['overdue']['total'] ?></h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
                                <i class="bi bi-clock"></i>
                                <?= $workflow_analytics['overdue']['avg_days'] ?> avg days overdue
                            </p>
                        </div>
                    </div>

                    <!-- Leads Card -->
                    <div class="kpi-card" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3); position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); pointer-events: none;"></div>
                        <div style="position: relative; z-index: 2;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <i class="bi bi-people-fill" style="font-size: 2rem; opacity: 0.8;"></i>
                                <span style="font-size: 0.875rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px;">Leads</span>
                            </div>
                            <h3 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem 0;"><?= $new_leads ?></h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
                                <i class="bi bi-graph-up"></i>
                                New leads this period
                            </p>
                        </div>
                    </div>

                    <!-- Inventory Card -->
                    <div class="kpi-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3); position: relative; overflow: hidden; cursor: pointer;" onclick="window.location.href='inventory.php'">
                        <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); pointer-events: none;"></div>
                        <div style="position: relative; z-index: 2;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <i class="bi bi-boxes" style="font-size: 2rem; opacity: 0.8;"></i>
                                <span style="font-size: 0.875rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px;">Inventory</span>
                            </div>
                            <h3 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem 0;"><?= $inventory_alerts ?></h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
                                <i class="bi bi-check-circle"></i>
                                Active items in stock
                            </p>
                        </div>
                    </div>
                </div>
        
                
                <!-- All Charts in 3x3 Grid -->
                <div class="all-charts-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 0.5rem; margin-top: 0.5rem;">
                    
                    <!-- Row 1: Revenue, Inventory, Leads -->
                    <!-- Revenue Trends Chart -->
                    <div class="chart-card" style="background: transparent; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid rgba(241,245,249,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem 0;">Revenue Trends & Forecasting</h3>
                                <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">Daily revenue with predictive analytics</p>
                            </div>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 12px; font-size: 0.875rem; color: #64748b; border: 1px solid #e2e8f0;">
                            <i class="bi bi-info-circle" style="color: #3b82f6;"></i> 
                            <strong>Real Data:</strong> Total revenue ₦<?= number_format($total_revenue, 2) ?>. Chart shows actual daily sales.
                        </div>
                    </div>

                    <!-- Inventory Distribution -->
                    <div class="chart-card" style="background: transparent; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid rgba(241,245,249,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem 0;">Inventory Distribution</h3>
                                <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">Stock levels across categories</p>
                            </div>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>

                    <!-- Leads Conversion Analysis -->
                    <div class="chart-card" style="background: transparent; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid rgba(241,245,249,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem 0;">Leads Conversion Analysis</h3>
                                <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">Monthly lead generation and conversion rates</p>
                            </div>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="leadsChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Row 2: User Activity, Performance, Predictive -->
                    <!-- User Activity Heatmap -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3 class="chart-title">User Activity</h3>
                                <p class="chart-subtitle">Daily active users pattern</p>
                            </div>
                        </div>
                        <div class="chart-container" style="display: flex; justify-content: center; align-items: flex-end; padding-bottom: 1rem;">
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
                    
                    <!-- Row 3: Daily Completion, Priority, Workflow Status -->
                    <!-- Daily Completion Trends -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3 class="chart-title">Daily Completion Trends</h3>
                                <p class="chart-subtitle">Tasks completed per day (last 7 days)</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="workflowCompletionChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Priority Distribution -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3 class="chart-title">Priority Distribution</h3>
                                <p class="chart-subtitle">Task breakdown by priority level</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="priorityDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Workflow Status -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3 class="chart-title">Workflow Status</h3>
                                <p class="chart-subtitle">Current task status distribution</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="workflowStatusChart"></canvas>
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

    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Analytics JavaScript -->
    <script src="assets/js/theme-manager.js"></script>
    <script>
        // Enhanced Chart Animations and Loading Effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading skeleton to all charts initially
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(container => {
                container.classList.add('chart-loading');
            });
            
            // Remove loading skeleton after a delay
            setTimeout(() => {
                chartContainers.forEach(container => {
                    container.classList.remove('chart-loading');
                });
            }, 2000);
            
            // Staggered chart initialization
            const charts = document.querySelectorAll('canvas[id*="Chart"]');
            charts.forEach((chart, index) => {
                setTimeout(() => {
                    // Add a subtle glow effect when chart loads
                    chart.style.transition = 'all 0.5s ease';
                    chart.style.filter = 'drop-shadow(0 0 20px rgba(59, 130, 246, 0.3))';
                    
                    // Remove glow after animation
                    setTimeout(() => {
                        chart.style.filter = 'none';
                    }, 1000);
                }, index * 200);
            });
        });
        
        // Global Chart.js animation configuration
        Chart.defaults.animation = {
            duration: 2000,
            easing: 'easeOutQuart'
        };
        
        // Enhanced animation options for all charts
        const chartAnimationConfig = {
            animation: {
                duration: 2000,
                easing: 'easeOutQuart',
                delay: (context) => {
                    let delay = 0;
                    if (context.type === 'data' && context.mode === 'default') {
                        delay = context.dataIndex * 100 + context.datasetIndex * 300;
                    }
                    return delay;
                }
            },
            animations: {
                tension: {
                    duration: 1000,
                    easing: 'linear',
                    from: 0.1,
                    to: 0.4,
                    loop: false
                },
                y: {
                    duration: 2000,
                    easing: 'easeOutBounce',
                    from: (ctx) => ctx.chart.scales.y?.min || 0
                },
                x: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    onHover: function(e, legendItem, legend) {
                        legend.chart.canvas.style.cursor = 'pointer';
                        legend.chart.canvas.style.transform = 'scale(1.02)';
                        legend.chart.canvas.style.transition = 'transform 0.3s ease';
                    },
                    onLeave: function(e, legendItem, legend) {
                        legend.chart.canvas.style.cursor = 'default';
                        legend.chart.canvas.style.transform = 'scale(1)';
                    }
                }
            }
        };
        
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
            },
            inventory: {
                labels: <?= json_encode(array_keys($inventory_data)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($inventory_data)) ?>,
                    backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6']
                }]
            },
            workflow: {
                labels: <?= json_encode(array_keys($workflow_data)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($workflow_data)) ?>,
                    backgroundColor: ['#8b5cf6', '#06b6d4', '#10b981', '#f59e0b']
                }]
            },
            userActivity: {
                labels: <?= json_encode($user_activity_labels) ?>,
                datasets: [{
                    label: 'Active Users',
                    data: <?= json_encode($user_activity_data) ?>,
                    backgroundColor: '#06b6d4',
                    borderColor: '#0891b2',
                    borderWidth: 2
                }]
            },
            performance: {
                labels: <?= json_encode(array_keys($performance_metrics)) ?>,
                datasets: [{
                    label: 'Performance Score',
                    data: <?= json_encode(array_values($performance_metrics)) ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: '#4f46e5',
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#4f46e5'
                }]
            },
            predictive: {
                labels: <?= json_encode($predictive_labels) ?>,
                datasets: [{
                    label: 'Predicted Revenue (₦)',
                    data: <?= json_encode($predictive_data) ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: '#8b5cf620',
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: false
                }]
            }
        };
        
        // Function to enhance chart options with animations
        function enhanceChartOptions(baseOptions) {
            return {
                ...baseOptions,
                ...chartAnimationConfig,
                plugins: {
                    ...baseOptions.plugins,
                    ...chartAnimationConfig.plugins
                }
            };
        }

        // Initialize analytics dashboard with real data
        let analytics;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts with real data immediately
            initializeChartsWithRealData();
        });

        function initializeChartsWithRealData() {
            console.log('📊 Loading charts with real database data:', realChartData);
            console.log('🔍 Chart.js available:', typeof Chart !== 'undefined');
            
            // Check if chart containers exist
            const revenueContainer = document.getElementById('revenueChart');
            const leadsContainer = document.getElementById('leadsChart');
            console.log('🎯 Revenue chart container:', revenueContainer);
            console.log('🎯 Leads chart container:', leadsContainer);
            
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                try {
                    const revenueChart = new Chart(revenueCtx, {
                        type: 'line',
                        data: realChartData.revenue,
                        options: {
                            ...chartAnimationConfig,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Daily Revenue Trends'
                                },
                                legend: {
                                    ...chartAnimationConfig.plugins.legend
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
                    console.log('✅ Revenue chart loaded with real data:', revenueChart);
                } catch (error) {
                    console.error('❌ Revenue chart error:', error);
                }
            } else {
                console.error('❌ Revenue chart container not found');
            }

            // Leads Chart  
            const leadsCtx = document.getElementById('leadsChart');
            if (leadsCtx) {
                try {
                    const leadsChart = new Chart(leadsCtx, {
                        type: 'doughnut',
                        data: realChartData.leads,
                        options: enhanceChartOptions({
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Lead Status Distribution'
                                }
                            }
                        })
                    });
                    console.log('✅ Leads chart loaded with real data:', leadsChart);
                } catch (error) {
                    console.error('❌ Leads chart error:', error);
                }
            } else {
                console.error('❌ Leads chart container not found');
            }

            // Inventory Chart
            const inventoryCtx = document.getElementById('inventoryChart');
            if (inventoryCtx) {
                try {
                    const inventoryChart = new Chart(inventoryCtx, {
                        type: 'pie',
                        data: realChartData.inventory,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Inventory Stock Levels'
                                }
                            }
                        }
                    });
                    console.log('✅ Inventory chart loaded with real data:', inventoryChart);
                } catch (error) {
                    console.error('❌ Inventory chart error:', error);
                }
            }

            // User Activity Chart
            const userActivityCtx = document.getElementById('userActivityChart');
            if (userActivityCtx) {
                try {
                    const userActivityChart = new Chart(userActivityCtx, {
                        type: 'bar',
                        data: realChartData.userActivity,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Daily Active Users'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    console.log('✅ User Activity chart loaded with real data:', userActivityChart);
                } catch (error) {
                    console.error('❌ User Activity chart error:', error);
                }
            }

            // Performance Radar Chart
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                try {
                    const performanceChart = new Chart(performanceCtx, {
                        type: 'radar',
                        data: realChartData.performance,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Performance Metrics'
                                }
                            },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            }
                        }
                    });
                    console.log('✅ Performance chart loaded with real data:', performanceChart);
                } catch (error) {
                    console.error('❌ Performance chart error:', error);
                }
            }

            // Predictive Analytics Chart
            const predictiveCtx = document.getElementById('predictiveChart');
            if (predictiveCtx) {
                try {
                    const predictiveChart = new Chart(predictiveCtx, {
                        type: 'line',
                        data: realChartData.predictive,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Revenue Forecast (Next 7 Days)'
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
                    console.log('✅ Predictive chart loaded with real data:', predictiveChart);
                } catch (error) {
                    console.error('❌ Predictive chart error:', error);
                }
            }
            
            // Initialize workflow charts
            initializeWorkflowCharts();
        }
        
        function initializeWorkflowCharts() {
            console.log('📊 Initializing workflow charts...');
            
            // Workflow Completion Trends Chart
            const workflowCompletionCtx = document.getElementById('workflowCompletionChart');
            if (workflowCompletionCtx) {
                try {
                    new Chart(workflowCompletionCtx, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($workflow_completion_labels) ?>,
                            datasets: [{
                                label: 'Completed Tasks',
                                data: <?= json_encode($workflow_completion_data) ?>,
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                    console.log('✅ Workflow completion chart created');
                } catch (error) {
                    console.error('❌ Workflow completion chart error:', error);
                }
            }
            
            // Priority Distribution Chart
            const priorityCtx = document.getElementById('priorityDistributionChart');
            if (priorityCtx) {
                try {
                    const priorityData = <?= json_encode($workflow_analytics['priority_distribution']) ?>;
                    const colors = {
                        'urgent': '#dc3545',
                        'high': '#fd7e14', 
                        'medium': '#ffc107',
                        'low': '#28a745',
                        'unassigned': '#6c757d'
                    };
                    
                    new Chart(priorityCtx, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(priorityData).map(p => p.charAt(0).toUpperCase() + p.slice(1)),
                            datasets: [{
                                data: Object.values(priorityData),
                                backgroundColor: Object.keys(priorityData).map(p => colors[p] || '#6c757d'),
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { fontSize: 12 }
                                }
                            }
                        }
                    });
                    console.log('✅ Priority distribution chart created');
                } catch (error) {
                    console.error('❌ Priority distribution chart error:', error);
                }
            }
            
            // Workflow Status Chart
            const statusCtx = document.getElementById('workflowStatusChart');
            if (statusCtx) {
                try {
                    const statusData = <?= json_encode($workflow_data) ?>;
                    const statusColors = {
                        'pending': '#ffc107',
                        'approved': '#28a745',
                        'rejected': '#dc3545',
                        'completed': '#17a2b8',
                        'cancelled': '#6c757d'
                    };
                    
                    new Chart(statusCtx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(statusData).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                            datasets: [{
                                label: 'Tasks',
                                data: Object.values(statusData),
                                backgroundColor: Object.keys(statusData).map(s => statusColors[s] || '#6c757d'),
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                    console.log('✅ Workflow status chart created');
                } catch (error) {
                    console.error('❌ Workflow status chart error:', error);
                }
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
        document.querySelectorAll('.kpi-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('slide-up');
        });
        
        // Add hover effects to chart cards
        document.querySelectorAll('.chart-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 20px 60px rgba(0,0,0,0.15)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 10px 40px rgba(0,0,0,0.1)';
            });
        });
    </script>

            </div> <!-- End Dashboard Content -->
        </div> <!-- End Main Content -->
    </div> <!-- End Page Wrapper -->
    
    <!-- Modern Footer -->
    <footer style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #cbd5e1; padding: 2rem; text-align: center; margin-top: auto;">
        <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="bi bi-grid-3x3-gap-fill" style="color: #3b82f6; font-size: 1.25rem;"></i>
                <span style="font-weight: 600; color: white;">BizAutoPro</span>
            </div>
            <span style="opacity: 0.6;">|</span>
            <small style="opacity: 0.8;">Created by NOYB FUNDAMENTAL 2025 ©</small>
        </div>
    </footer>
</body>
</html>