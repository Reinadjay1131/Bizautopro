<?php
/**
 * BizAutoPro Analytics API Endpoint
 * Provides real-time data for dashboard charts and analytics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config.php';

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication check
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get the requested analytics type
$analytics_type = $_GET['type'] ?? '';
$date_range = $_GET['range'] ?? '7d';

try {
    switch ($analytics_type) {
        case 'revenue':
            echo json_encode(getRevenueAnalytics($pdo, $date_range));
            break;
            
        case 'inventory':
            echo json_encode(getInventoryAnalytics($pdo));
            break;
            
        case 'leads':
            echo json_encode(getLeadsAnalytics($pdo, $date_range));
            break;
            
        case 'workflows':
            echo json_encode(getWorkflowAnalytics($pdo, $user_id, $user_role));
            break;
            
        case 'user_activity':
            echo json_encode(getUserActivityAnalytics($pdo));
            break;
            
        case 'performance':
            echo json_encode(getPerformanceMetrics($pdo, $user_id, $user_role));
            break;
            
        case 'predictive':
            echo json_encode(getPredictiveAnalytics($pdo, $date_range));
            break;
            
        case 'personal':
            echo json_encode(getPersonalAnalytics($pdo, $user_id, $date_range));
            break;
            
        case 'team':
            if ($user_role === 'manager' || $user_role === 'admin') {
                echo json_encode(getTeamAnalytics($pdo, $user_id));
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
            }
            break;
            
        case 'dashboard_summary':
            echo json_encode(getDashboardSummary($pdo, $user_id, $user_role));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid analytics type']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Get revenue analytics data from real database transactions
 */
function getRevenueAnalytics($pdo, $date_range) {
    $days = getDaysFromRange($date_range);
    $revenue_data = [];
    $labels = [];
    
    try {
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            
            // Get actual revenue from database for this date
            $revenue_query = $pdo->prepare("
                SELECT COALESCE(SUM(price * quantity), 0) as daily_revenue 
                FROM outbound_sales 
                WHERE DATE(deduction_date) = ?
            ");
            $revenue_query->execute([$date]);
            $daily_revenue = $revenue_query->fetchColumn() ?: 0;
            $revenue_data[] = round($daily_revenue);
        }
        
        // Calculate average for target line
        $average_revenue = count($revenue_data) > 0 ? array_sum($revenue_data) / count($revenue_data) : 0;
        $target_revenue = max(10000, $average_revenue * 1.1); // 10% above average, minimum 10k
        
    } catch (Exception $e) {
        // Fallback to zeros if database fails
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            $revenue_data[] = 0;
        }
        $target_revenue = 10000;
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Daily Revenue (₦)',
                'data' => $revenue_data,
                'borderColor' => '#4f46e5',
                'backgroundColor' => '#4f46e520',
                'tension' => 0.4,
                'fill' => true
            ],
            [
                'label' => 'Target Revenue (₦)',
                'data' => array_fill(0, count($labels), round($target_revenue)),
                'borderColor' => '#f59e0b',
                'borderDash' => [5, 5],
                'backgroundColor' => 'transparent',
                'tension' => 0
            ]
        ]
    ];
}

/**
 * Get inventory analytics data
 */
function getInventoryAnalytics($pdo) {
    try {
        // Get actual inventory counts
        $inventory_stats = $pdo->query("
            SELECT 
                COUNT(CASE WHEN quantity > 10 THEN 1 END) as in_stock,
                COUNT(CASE WHEN quantity BETWEEN 1 AND 10 THEN 1 END) as low_stock,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN quantity < 0 THEN 1 END) as on_order
            FROM inventory
        ")->fetch();
        
        return [
            'labels' => ['In Stock', 'Low Stock', 'Out of Stock', 'On Order'],
            'datasets' => [
                [
                    'data' => [
                        $inventory_stats['in_stock'] ?: 0,
                        $inventory_stats['low_stock'] ?: 0,
                        $inventory_stats['out_of_stock'] ?: 0,
                        $inventory_stats['on_order'] ?: 0
                    ],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff'
                ]
            ]
        ];
    } catch (Exception $e) {
        // Fallback data if inventory table doesn't exist or has issues
        return [
            'labels' => ['In Stock', 'Low Stock', 'Out of Stock', 'On Order'],
            'datasets' => [
                [
                    'data' => [0, 0, 0, 0],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff'
                ]
            ]
        ];
    }
}

/**
 * Get leads analytics data
 */
function getLeadsAnalytics($pdo, $date_range) {
    try {
        // Get actual leads data from database
        $months = [];
        $new_leads = [];
        $converted_leads = [];
        
        // Get last 6 months of actual data
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_label = date('M', strtotime("-$i months"));
            
            $months[] = $month_label;
            
            // Get actual new leads count for this month
            $new_leads_query = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM leads 
                WHERE created_at BETWEEN ? AND ?
            ");
            $new_leads_query->execute([$month_start, $month_end . ' 23:59:59']);
            $new_count = $new_leads_query->fetchColumn() ?: 0;
            $new_leads[] = (int)$new_count;
            
            // Get actual converted leads count for this month
            $converted_query = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM leads 
                WHERE status IN ('converted', 'closed_won') 
                AND updated_at BETWEEN ? AND ?
            ");
            $converted_query->execute([$month_start, $month_end . ' 23:59:59']);
            $converted_count = $converted_query->fetchColumn() ?: 0;
            $converted_leads[] = (int)$converted_count;
        }
        
        return [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'New Leads',
                    'data' => $new_leads,
                    'backgroundColor' => '#3b82f680',
                    'borderColor' => '#3b82f6',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Converted',
                    'data' => $converted_leads,
                    'backgroundColor' => '#10b98180',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1
                ]
            ]
        ];
    } catch (Exception $e) {
        // If leads table doesn't exist, return empty data
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'label' => 'New Leads',
                    'data' => [0, 0, 0, 0, 0, 0],
                    'backgroundColor' => '#3b82f680',
                    'borderColor' => '#3b82f6',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Converted',
                    'data' => [0, 0, 0, 0, 0, 0],
                    'backgroundColor' => '#10b98180',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1
                ]
            ]
        ];
    }
}

/**
 * Get workflow analytics data
 */
function getWorkflowAnalytics($pdo, $user_id, $user_role) {
    try {
        $workflow_stats = $pdo->query("
            SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
            FROM workflows
        ")->fetch();
        
        return [
            'labels' => ['Completed', 'In Progress', 'Pending', 'Cancelled'],
            'datasets' => [
                [
                    'data' => [
                        $workflow_stats['completed'] ?: 0,
                        $workflow_stats['in_progress'] ?: 0,
                        $workflow_stats['pending'] ?: 0,
                        $workflow_stats['cancelled'] ?: 0
                    ],
                    'backgroundColor' => ['#10b98180', '#3b82f680', '#f59e0b80', '#ef444480'],
                    'borderColor' => ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    'borderWidth' => 2
                ]
            ]
        ];
    } catch (Exception $e) {
        return [
            'labels' => ['Completed', 'In Progress', 'Pending', 'Cancelled'],
            'datasets' => [
                [
                    'data' => [0, 0, 0, 0],
                    'backgroundColor' => ['#10b98180', '#3b82f680', '#f59e0b80', '#ef444480'],
                    'borderColor' => ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    'borderWidth' => 2
                ]
            ]
        ];
    }
}

/**
 * Get user activity analytics
 */
function getUserActivityAnalytics($pdo) {
    $hours = ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00'];
    $activity_data = [];
    
    // Get actual user activity data from database
    foreach ($hours as $index => $hour) {
        try {
            // Calculate hour range
            $hour_start = sprintf('%02d:00:00', $index * 4);
            $hour_end = sprintf('%02d:59:59', ($index * 4) + 3);
            
            // Get actual login activity for this time range today
            $activity_query = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as active_users
                FROM `user_sessions` 
                WHERE DATE(login_time) = CURDATE() 
                AND TIME(login_time) BETWEEN ? AND ?
            ");
            $activity_query->execute([$hour_start, $hour_end]);
            $result = $activity_query->fetch();
            $activity_count = $result ? (int)$result['active_users'] : 0;
        } catch (Exception $e) {
            // If no user_sessions table, try alternative approach
            try {
                // Count users who have been active (created/updated records) in this time range
                $alt_query = $pdo->prepare("
                    SELECT COUNT(DISTINCT created_by) as active_users
                    FROM workflows 
                    WHERE DATE(created_at) = CURDATE() 
                    AND TIME(created_at) BETWEEN ? AND ?
                ");
                $alt_query->execute([$hour_start, $hour_end]);
                $result = $alt_query->fetch();
                $activity_count = $result ? (int)$result['active_users'] : 0;
            } catch (Exception $e2) {
                // If no activity data available, return 0
                $activity_count = 0;
            }
        }
        
        $activity_data[] = $activity_count;
    }
    
    return [
        'labels' => $hours,
        'datasets' => [
            [
                'label' => 'Active Users',
                'data' => $activity_data,
                'borderColor' => '#10b981',
                'backgroundColor' => '#10b98120',
                'tension' => 0.4,
                'fill' => true
            ]
        ]
    ];
}

/**
 * Get performance metrics
 */
function getPerformanceMetrics($pdo, $user_id, $user_role) {
    // Calculate actual performance based on real database data
    $performance_data = [];
    $target_data = [];
    
    try {
        // Sales performance (based on actual sales/outbound_sales)
        $sales_query = $pdo->prepare("SELECT COUNT(*) as count FROM outbound_sales WHERE DATE(deduction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $sales_query->execute();
        $sales_count = $sales_query->fetchColumn() ?: 0;
        $sales_performance = min(100, ($sales_count / 30) * 10); // Scale to percentage
        
        // Inventory performance (based on stock levels)
        $inventory_query = $pdo->prepare("SELECT AVG(CASE WHEN quantity > 0 THEN 100 ELSE 0 END) as performance FROM inventory");
        $inventory_query->execute();
        $inventory_performance = $inventory_query->fetchColumn() ?: 0;
        
        // Leads performance (based on conversion rate)
        $leads_total_query = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $leads_total_query->execute();
        $leads_total = $leads_total_query->fetchColumn() ?: 1;
        
        $leads_converted_query = $pdo->prepare("SELECT COUNT(*) as converted FROM leads WHERE status IN ('converted', 'closed_won') AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $leads_converted_query->execute();
        $leads_converted = $leads_converted_query->fetchColumn() ?: 0;
        
        $leads_performance = ($leads_converted / $leads_total) * 100;
        
        // Workflows performance (based on completion rate)
        $workflows_total_query = $pdo->prepare("SELECT COUNT(*) as total FROM workflows WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $workflows_total_query->execute();
        $workflows_total = $workflows_total_query->fetchColumn() ?: 1;
        
        $workflows_completed_query = $pdo->prepare("SELECT COUNT(*) as completed FROM workflows WHERE status = 'completed' AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $workflows_completed_query->execute();
        $workflows_completed = $workflows_completed_query->fetchColumn() ?: 0;
        
        $workflows_performance = ($workflows_completed / $workflows_total) * 100;
        
        // Users performance (based on active vs total users)
        $users_total_query = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'approved'");
        $users_total_query->execute();
        $users_total = $users_total_query->fetchColumn() ?: 1;
        
        $users_active_query = $pdo->prepare("SELECT COUNT(DISTINCT created_by) as active FROM workflows WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $users_active_query->execute();
        $users_active = $users_active_query->fetchColumn() ?: 0;
        
        $users_performance = ($users_active / $users_total) * 100;
        
        // Revenue performance (growth compared to previous period)
        $current_revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) as revenue FROM outbound_sales WHERE deduction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $current_revenue_query->execute();
        $current_revenue = $current_revenue_query->fetchColumn() ?: 0;
        
        $previous_revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 1) as revenue FROM outbound_sales WHERE deduction_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $previous_revenue_query->execute();
        $previous_revenue = $previous_revenue_query->fetchColumn() ?: 1;
        
        $revenue_performance = min(100, ($current_revenue / $previous_revenue) * 50); // Scale to percentage
        
        $performance_data = [
            round($sales_performance, 1),
            round($inventory_performance, 1),
            round($leads_performance, 1),
            round($workflows_performance, 1),
            round($users_performance, 1),
            round($revenue_performance, 1)
        ];
        
        // Set targets as 20% above current performance or minimum standards
        $target_data = [
            max(80, $performance_data[0] * 1.2),
            max(85, $performance_data[1] * 1.2),
            max(75, $performance_data[2] * 1.2),
            max(90, $performance_data[3] * 1.2),
            max(70, $performance_data[4] * 1.2),
            max(80, $performance_data[5] * 1.2)
        ];
        
    } catch (Exception $e) {
        // If database queries fail, return zeros instead of fake data
        $performance_data = [0, 0, 0, 0, 0, 0];
        $target_data = [80, 85, 75, 90, 70, 80]; // Minimum acceptable targets
    }
    
    return [
        'labels' => ['Sales', 'Inventory', 'Leads', 'Workflows', 'Users', 'Revenue'],
        'datasets' => [
            [
                'label' => 'Current Performance',
                'data' => $performance_data,
                'borderColor' => '#4f46e5',
                'backgroundColor' => '#4f46e520',
                'pointBackgroundColor' => '#4f46e5',
                'pointBorderColor' => '#fff'
            ],
            [
                'label' => 'Target Performance',
                'data' => $target_data,
                'borderColor' => '#f59e0b',
                'backgroundColor' => '#f59e0b10',
                'pointBackgroundColor' => '#f59e0b',
                'pointBorderColor' => '#fff',
                'borderDash' => [5, 5]
            ]
        ]
    ];
}

/**
 * Get predictive analytics based on real data trends
 */
function getPredictiveAnalytics($pdo, $date_range) {
    $weeks = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5 (Forecast)', 'Week 6 (Forecast)'];
    
    try {
        // Get actual revenue data for the past 4 weeks
        $actual_revenue = [];
        $predicted_revenue = [null, null, null, null, null, null];
        
        // Query actual revenue for past 4 weeks
        for ($i = 3; $i >= 0; $i--) {
            $start_date = date('Y-m-d', strtotime("-" . ($i + 1) . " weeks"));
            $end_date = date('Y-m-d', strtotime("-$i weeks"));
            
            $revenue_query = $pdo->prepare("
                SELECT COALESCE(SUM(price * quantity), 0) as weekly_revenue 
                FROM outbound_sales 
                WHERE deduction_date BETWEEN ? AND ?
            ");
            $revenue_query->execute([$start_date, $end_date]);
            $weekly_revenue = $revenue_query->fetchColumn() ?: 0;
            $actual_revenue[] = round($weekly_revenue);
        }
        
        // Calculate trend for predictions
        if (count($actual_revenue) >= 2) {
            $recent_avg = array_sum(array_slice($actual_revenue, -2)) / 2;
            $growth_rate = count($actual_revenue) > 1 ? 
                ($actual_revenue[3] - $actual_revenue[0]) / 3 : 0;
            
            // Predict next 2 weeks
            $predicted_revenue[4] = max(0, round($recent_avg + $growth_rate));
            $predicted_revenue[5] = max(0, round($recent_avg + ($growth_rate * 2)));
            
            // Set the bridge point
            $predicted_revenue[3] = $actual_revenue[3];
        } else {
            // Fallback if no data
            $actual_revenue = [0, 0, 0, 0];
            $predicted_revenue[3] = 0;
            $predicted_revenue[4] = 0;
            $predicted_revenue[5] = 0;
        }
        
        // Pad actual revenue with nulls for forecast weeks
        $actual_revenue = array_merge($actual_revenue, [null, null]);
        
    } catch (Exception $e) {
        // Fallback data if database fails
        $actual_revenue = [0, 0, 0, 0, null, null];
        $predicted_revenue = [null, null, null, 0, 0, 0];
    }
    
    return [
        'labels' => $weeks,
        'datasets' => [
            [
                'label' => 'Actual Revenue (₦)',
                'data' => $actual_revenue,
                'borderColor' => '#10b981',
                'backgroundColor' => '#10b98120',
                'tension' => 0.4,
                'fill' => true
            ],
            [
                'label' => 'Predicted Revenue (₦)',
                'data' => $predicted_revenue,
                'borderColor' => '#f59e0b',
                'backgroundColor' => '#f59e0b20',
                'borderDash' => [5, 5],
                'tension' => 0.4,
                'fill' => true
            ]
        ]
    ];
}

/**
 * Get personal analytics for individual users based on real activity
 */
function getPersonalAnalytics($pdo, $user_id, $date_range) {
    $days = getDaysFromRange($date_range);
    $performance_scores = [];
    $task_completion = [];
    $labels = [];
    
    try {
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            
            // Calculate daily performance based on user activities
            $activities_query = $pdo->prepare("
                SELECT COUNT(*) as total_activities FROM (
                    SELECT created_at FROM workflows WHERE created_by = ? AND DATE(created_at) = ?
                    UNION ALL
                    SELECT created_at FROM leads WHERE created_by = ? AND DATE(created_at) = ?
                    UNION ALL
                    SELECT deduction_date as created_at FROM outbound_sales WHERE user_id = ? AND DATE(deduction_date) = ?
                ) as all_activities
            ");
            $activities_query->execute([$user_id, $date, $user_id, $date, $user_id, $date]);
            $daily_activities = $activities_query->fetchColumn() ?: 0;
            
            // Calculate performance score based on activity (scale 0-100)
            $performance_score = min(100, $daily_activities * 15 + 60); // Base score of 60, +15 per activity
            $performance_scores[] = round($performance_score);
            
            // Calculate task completion rate
            $completed_query = $pdo->prepare("
                SELECT COUNT(*) as completed FROM workflows 
                WHERE created_by = ? AND DATE(updated_at) = ? AND status = 'completed'
            ");
            $completed_query->execute([$user_id, $date]);
            $completed_tasks = $completed_query->fetchColumn() ?: 0;
            
            $total_query = $pdo->prepare("
                SELECT COUNT(*) as total FROM workflows 
                WHERE created_by = ? AND DATE(created_at) <= ? AND (DATE(updated_at) = ? OR status != 'completed')
            ");
            $total_query->execute([$user_id, $date, $date]);
            $total_tasks = $total_query->fetchColumn() ?: 1;
            
            $completion_rate = ($completed_tasks / $total_tasks) * 100;
            $task_completion[] = round(min(100, $completion_rate));
        }
        
    } catch (Exception $e) {
        // Fallback to basic scores if database fails
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            $performance_scores[] = 75; // Neutral performance
            $task_completion[] = 80; // Neutral completion rate
        }
    }
    
    return [
        'performance' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Daily Performance Score',
                    'data' => $performance_scores,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => '#6366f120',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ],
        'tasks' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Task Completion Rate (%)',
                    'data' => $task_completion,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98120',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ]
    ];
}

/**
 * Get team analytics for managers based on real data
 */
function getTeamAnalytics($pdo, $user_id) {
    try {
        // Get actual team members and their performance
        $team_query = $pdo->prepare("
            SELECT u.username, u.id,
                COUNT(w.id) as total_workflows,
                COUNT(CASE WHEN w.status = 'completed' THEN 1 END) as completed_workflows,
                COUNT(l.id) as total_leads,
                COUNT(CASE WHEN l.status IN ('converted', 'closed_won') THEN 1 END) as converted_leads
            FROM users u
            LEFT JOIN workflows w ON u.id = w.created_by AND w.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            LEFT JOIN leads l ON u.id = l.created_by AND l.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE u.status = 'approved' AND u.role != 'admin'
            GROUP BY u.id, u.username
            ORDER BY completed_workflows DESC
            LIMIT 5
        ");
        $team_query->execute();
        $team_data = $team_query->fetchAll(PDO::FETCH_ASSOC);
        
        $team_names = [];
        $team_performance = [];
        
        foreach ($team_data as $member) {
            $team_names[] = $member['username'];
            
            // Calculate performance score based on workflow completion and lead conversion
            $workflow_score = $member['total_workflows'] > 0 ? 
                ($member['completed_workflows'] / $member['total_workflows']) * 50 : 0;
            $lead_score = $member['total_leads'] > 0 ? 
                ($member['converted_leads'] / $member['total_leads']) * 50 : 0;
            
            $performance = min(100, $workflow_score + $lead_score + 20); // Base score of 20
            $team_performance[] = round($performance);
        }
        
        // If no team members, provide empty data
        if (empty($team_names)) {
            $team_names = ['No Team Data'];
            $team_performance = [0];
        }
        
        // Get weekly conversion rates
        $conversion_data = [];
        $week_labels = [];
        
        for ($i = 3; $i >= 0; $i--) {
            $start_date = date('Y-m-d', strtotime("-" . ($i + 1) . " weeks"));
            $end_date = date('Y-m-d', strtotime("-$i weeks"));
            $week_labels[] = 'Week ' . (4 - $i);
            
            $total_leads_query = $pdo->prepare("
                SELECT COUNT(*) FROM leads 
                WHERE created_at BETWEEN ? AND ?
            ");
            $total_leads_query->execute([$start_date, $end_date]);
            $total_leads = $total_leads_query->fetchColumn() ?: 1;
            
            $converted_leads_query = $pdo->prepare("
                SELECT COUNT(*) FROM leads 
                WHERE status IN ('converted', 'closed_won') 
                AND updated_at BETWEEN ? AND ?
            ");
            $converted_leads_query->execute([$start_date, $end_date]);
            $converted_leads = $converted_leads_query->fetchColumn() ?: 0;
            
            $conversion_rate = ($converted_leads / $total_leads) * 100;
            $conversion_data[] = round($conversion_rate, 1);
        }
        
    } catch (Exception $e) {
        // Fallback to empty data if database fails
        $team_names = ['No Data Available'];
        $team_performance = [0];
        $conversion_data = [0, 0, 0, 0];
        $week_labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
    }
    
    return [
        'team_performance' => [
            'labels' => $team_names,
            'datasets' => [
                [
                    'label' => 'Team Member Performance (%)',
                    'data' => $team_performance,
                    'backgroundColor' => '#4f46e580',
                    'borderColor' => '#4f46e5',
                    'borderWidth' => 1
                ]
            ]
        ],
        'conversion_rates' => [
            'labels' => $week_labels,
            'datasets' => [
                [
                    'label' => 'Conversion Rate (%)',
                    'data' => $conversion_data,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98120',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ]
    ];
}

/**
 * Get dashboard summary data based on real database information
 */
function getDashboardSummary($pdo, $user_id, $user_role) {
    try {
        // Calculate total revenue from actual sales data
        $revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales");
        $revenue_query->execute();
        $total_revenue = $revenue_query->fetchColumn() ?: 0;
        
        // Calculate revenue change (current month vs previous month)
        $current_month_query = $pdo->prepare("
            SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales 
            WHERE MONTH(deduction_date) = MONTH(CURDATE()) AND YEAR(deduction_date) = YEAR(CURDATE())
        ");
        $current_month_query->execute();
        $current_month_revenue = $current_month_query->fetchColumn() ?: 1;
        
        $previous_month_query = $pdo->prepare("
            SELECT COALESCE(SUM(price * quantity), 1) FROM outbound_sales 
            WHERE MONTH(deduction_date) = MONTH(CURDATE()) - 1 AND YEAR(deduction_date) = YEAR(CURDATE())
        ");
        $previous_month_query->execute();
        $previous_month_revenue = $previous_month_query->fetchColumn() ?: 1;
        
        $revenue_change = (($current_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100;
        
        // Get active inventory count
        $inventory_query = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE quantity > 0");
        $inventory_query->execute();
        $active_inventory = $inventory_query->fetchColumn() ?: 0;
        
        // Calculate inventory change (items added this week vs last week)
        $this_week_query = $pdo->prepare("
            SELECT COUNT(*) FROM inventory 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        ");
        $this_week_query->execute();
        $this_week_inventory = $this_week_query->fetchColumn() ?: 0;
        
        $last_week_query = $pdo->prepare("
            SELECT COUNT(*) FROM inventory 
            WHERE created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 WEEK) AND DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        ");
        $last_week_query->execute();
        $last_week_inventory = $last_week_query->fetchColumn() ?: 1;
        
        $inventory_change = (($this_week_inventory - $last_week_inventory) / $last_week_inventory) * 100;
        
        // Get new leads count
        $new_leads_query = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE status = 'new'");
        $new_leads_query->execute();
        $new_leads = $new_leads_query->fetchColumn() ?: 0;
        
        // Calculate leads change (new leads this week vs last week)
        $this_week_leads_query = $pdo->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE status = 'new' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        ");
        $this_week_leads_query->execute();
        $this_week_leads = $this_week_leads_query->fetchColumn() ?: 0;
        
        $last_week_leads_query = $pdo->prepare("
            SELECT COUNT(*) FROM leads 
            WHERE status = 'new' AND created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 WEEK) AND DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
        ");
        $last_week_leads_query->execute();
        $last_week_leads = $last_week_leads_query->fetchColumn() ?: 1;
        
        $leads_change = (($this_week_leads - $last_week_leads) / $last_week_leads) * 100;
        
        // Calculate system efficiency based on workflow completion rate
        $total_workflows_query = $pdo->prepare("SELECT COUNT(*) FROM workflows");
        $total_workflows_query->execute();
        $total_workflows = $total_workflows_query->fetchColumn() ?: 1;
        
        $completed_workflows_query = $pdo->prepare("SELECT COUNT(*) FROM workflows WHERE status = 'completed'");
        $completed_workflows_query->execute();
        $completed_workflows = $completed_workflows_query->fetchColumn() ?: 0;
        
        $system_efficiency = ($completed_workflows / $total_workflows) * 100;
        
        // Calculate efficiency change (this month vs last month)
        $this_month_total_query = $pdo->prepare("
            SELECT COUNT(*) FROM workflows 
            WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $this_month_total_query->execute();
        $this_month_total = $this_month_total_query->fetchColumn() ?: 1;
        
        $this_month_completed_query = $pdo->prepare("
            SELECT COUNT(*) FROM workflows 
            WHERE status = 'completed' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())
        ");
        $this_month_completed_query->execute();
        $this_month_completed = $this_month_completed_query->fetchColumn() ?: 0;
        
        $last_month_total_query = $pdo->prepare("
            SELECT COUNT(*) FROM workflows 
            WHERE MONTH(created_at) = MONTH(CURDATE()) - 1 AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $last_month_total_query->execute();
        $last_month_total = $last_month_total_query->fetchColumn() ?: 1;
        
        $last_month_completed_query = $pdo->prepare("
            SELECT COUNT(*) FROM workflows 
            WHERE status = 'completed' AND MONTH(updated_at) = MONTH(CURDATE()) - 1 AND YEAR(updated_at) = YEAR(CURDATE())
        ");
        $last_month_completed_query->execute();
        $last_month_completed = $last_month_completed_query->fetchColumn() ?: 0;
        
        $this_month_efficiency = ($this_month_completed / $this_month_total) * 100;
        $last_month_efficiency = ($last_month_completed / $last_month_total) * 100;
        $efficiency_change = $this_month_efficiency - $last_month_efficiency;
        
        $summary = [
            'total_revenue' => round($total_revenue),
            'revenue_change' => round($revenue_change, 1),
            'active_inventory' => $active_inventory,
            'inventory_change' => round($inventory_change, 1),
            'new_leads' => $new_leads,
            'leads_change' => round($leads_change, 1),
            'system_efficiency' => round($system_efficiency, 1),
            'efficiency_change' => round($efficiency_change, 1),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $summary;
    } catch (Exception $e) {
        // Conservative fallback if database queries fail
        return [
            'total_revenue' => 0,
            'revenue_change' => 0,
            'active_inventory' => 0,
            'inventory_change' => 0,
            'new_leads' => 0,
            'leads_change' => 0,
            'system_efficiency' => 0,
            'efficiency_change' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Convert date range to number of days
 */
function getDaysFromRange($range) {
    switch ($range) {
        case '7d': return 7;
        case '30d': return 30;
        case '90d': return 90;
        case '1y': return 365;
        default: return 7;
    }
}
?>