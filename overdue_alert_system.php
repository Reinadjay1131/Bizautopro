<?php
/**
 * Overdue Alert System
 * Handles detection and notification of overdue tasks
 * Can be run via cron job for automated alerts
 */

require 'config.php';

class OverdueAlertSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check for overdue tasks and send notifications
     */
    public function processOverdueTasks() {
        $overdue_tasks = $this->getOverdueTasks();
        $due_soon_tasks = $this->getDueSoonTasks();
        
        $results = [
            'overdue_processed' => 0,
            'due_soon_processed' => 0,
            'notifications_sent' => 0,
            'escalations_created' => 0
        ];
        
        // Process overdue tasks
        foreach ($overdue_tasks as $task) {
            $this->processOverdueTask($task);
            $results['overdue_processed']++;
        }
        
        // Process due soon tasks
        foreach ($due_soon_tasks as $task) {
            $this->processDueSoonTask($task);
            $results['due_soon_processed']++;
        }
        
        return $results;
    }
    
    /**
     * Get overdue tasks (past due date and not completed/cancelled)
     */
    private function getOverdueTasks() {
        $query = "
            SELECT w.*, 
                   u1.username as creator_name, 
                   u1.email as creator_email,
                   u2.username as assignee_name, 
                   u2.email as assignee_email,
                   DATEDIFF(NOW(), w.due_date) as days_overdue
            FROM workflows w
            LEFT JOIN users u1 ON w.created_by = u1.id
            LEFT JOIN users u2 ON w.assigned_to = u2.id
            WHERE w.due_date IS NOT NULL 
                AND w.due_date < NOW() 
                AND w.status NOT IN ('completed', 'cancelled')
        ";
        
        return $this->pdo->query($query)->fetchAll();
    }
    
    /**
     * Get tasks due within 24 hours
     */
    private function getDueSoonTasks() {
        $query = "
            SELECT w.*, 
                   u1.username as creator_name, 
                   u1.email as creator_email,
                   u2.username as assignee_name, 
                   u2.email as assignee_email,
                   TIMESTAMPDIFF(HOUR, NOW(), w.due_date) as hours_until_due
            FROM workflows w
            LEFT JOIN users u1 ON w.created_by = u1.id
            LEFT JOIN users u2 ON w.assigned_to = u2.id
            WHERE w.due_date IS NOT NULL 
                AND w.due_date > NOW() 
                AND w.due_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                AND w.status NOT IN ('completed', 'cancelled')
                AND NOT EXISTS (
                    SELECT 1 FROM workflow_notifications wn 
                    WHERE wn.workflow_id = w.id 
                        AND wn.notification_type = 'due_soon' 
                        AND wn.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
        ";
        
        return $this->pdo->query($query)->fetchAll();
    }
    
    /**
     * Process an individual overdue task
     */
    private function processOverdueTask($task) {
        $days_overdue = $task['days_overdue'];
        
        // Create overdue notification if not already sent today
        $existing_notification = $this->pdo->prepare("
            SELECT id FROM workflow_notifications 
            WHERE workflow_id = ? 
                AND notification_type = 'overdue' 
                AND DATE(created_at) = CURDATE()
        ");
        $existing_notification->execute([$task['id']]);
        
        if (!$existing_notification->fetch()) {
            // Send notification to assignee
            $this->createNotification($task['id'], $task['assigned_to'], 'overdue', 
                "Task '{$task['title']}' is {$days_overdue} day(s) overdue!");
            
            // Send notification to creator if different from assignee
            if ($task['created_by'] != $task['assigned_to']) {
                $this->createNotification($task['id'], $task['created_by'], 'overdue', 
                    "Task '{$task['title']}' assigned to {$task['assignee_name']} is {$days_overdue} day(s) overdue!");
            }
            
            // Escalate based on priority and days overdue
            $this->handleEscalation($task, $days_overdue);
        }
    }
    
    /**
     * Process a task that's due soon
     */
    private function processDueSoonTask($task) {
        $hours_until_due = $task['hours_until_due'];
        
        // Send due soon notification to assignee
        $this->createNotification($task['id'], $task['assigned_to'], 'due_soon', 
            "Task '{$task['title']}' is due in {$hours_until_due} hour(s)!");
        
        // Send notification to creator if different from assignee
        if ($task['created_by'] != $task['assigned_to']) {
            $this->createNotification($task['id'], $task['created_by'], 'due_soon', 
                "Task '{$task['title']}' assigned to {$task['assignee_name']} is due in {$hours_until_due} hour(s)!");
        }
    }
    
    /**
     * Handle escalation based on task priority and overdue duration
     */
    private function handleEscalation($task, $days_overdue) {
        $escalation_needed = false;
        
        // Escalation rules based on priority
        switch ($task['priority']) {
            case 'urgent':
                $escalation_needed = $days_overdue >= 1; // Escalate after 1 day
                break;
            case 'high':
                $escalation_needed = $days_overdue >= 2; // Escalate after 2 days
                break;
            case 'medium':
                $escalation_needed = $days_overdue >= 5; // Escalate after 5 days
                break;
            case 'low':
                $escalation_needed = $days_overdue >= 10; // Escalate after 10 days
                break;
            default:
                $escalation_needed = $days_overdue >= 7; // Default: escalate after 7 days
        }
        
        if ($escalation_needed) {
            // Check if escalation already exists
            $existing_escalation = $this->pdo->prepare("
                SELECT id FROM workflow_notifications 
                WHERE workflow_id = ? 
                    AND notification_type = 'escalation' 
                    AND DATE(created_at) = CURDATE()
            ");
            $existing_escalation->execute([$task['id']]);
            
            if (!$existing_escalation->fetch()) {
                // Send escalation to admin
                $admin_query = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $admin = $admin_query->fetch();
                
                if ($admin) {
                    $this->createNotification($task['id'], $admin['id'], 'escalation', 
                        "ESCALATION: {$task['priority']} priority task '{$task['title']}' is {$days_overdue} day(s) overdue and requires attention!");
                }
                
                // Log escalation in workflow history
                $this->pdo->prepare("
                    INSERT INTO workflow_history 
                    (workflow_id, user_id, action, notes, timestamp) 
                    VALUES (?, 1, 'escalated', 'Task escalated due to {$days_overdue} day(s) overdue', NOW())
                ")->execute([$task['id']]);
            }
        }
    }
    
    /**
     * Create a notification record
     */
    private function createNotification($workflow_id, $user_id, $type, $message) {
        $this->pdo->prepare("
            INSERT INTO workflow_notifications 
            (workflow_id, user_id, notification_type, message) 
            VALUES (?, ?, ?, ?)
        ")->execute([$workflow_id, $user_id, $type, $message]);
    }
    
    /**
     * Get overdue statistics for dashboard
     */
    public function getOverdueStats() {
        $stats = [
            'total_overdue' => 0,
            'urgent_overdue' => 0,
            'high_overdue' => 0,
            'due_today' => 0,
            'due_this_week' => 0,
            'avg_overdue_days' => 0
        ];
        
        // Total overdue
        $query = $this->pdo->query("
            SELECT COUNT(*) as count,
                   AVG(DATEDIFF(NOW(), due_date)) as avg_days
            FROM workflows 
            WHERE due_date < NOW() 
                AND status NOT IN ('completed', 'cancelled')
        ");
        $result = $query->fetch();
        $stats['total_overdue'] = $result['count'];
        $stats['avg_overdue_days'] = round($result['avg_days'] ?? 0, 1);
        
        // Priority-based overdue counts
        $priority_query = $this->pdo->query("
            SELECT priority, COUNT(*) as count
            FROM workflows 
            WHERE due_date < NOW() 
                AND status NOT IN ('completed', 'cancelled')
                AND priority IN ('urgent', 'high')
            GROUP BY priority
        ");
        while ($row = $priority_query->fetch()) {
            $stats[$row['priority'] . '_overdue'] = $row['count'];
        }
        
        // Due today
        $stats['due_today'] = $this->pdo->query("
            SELECT COUNT(*) 
            FROM workflows 
            WHERE DATE(due_date) = CURDATE() 
                AND status NOT IN ('completed', 'cancelled')
        ")->fetchColumn();
        
        // Due this week
        $stats['due_this_week'] = $this->pdo->query("
            SELECT COUNT(*) 
            FROM workflows 
            WHERE due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND status NOT IN ('completed', 'cancelled')
        ")->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Get recent notifications for a user
     */
    public function getUserNotifications($user_id, $limit = 10) {
        $query = $this->pdo->prepare("
            SELECT wn.*, w.title as workflow_title
            FROM workflow_notifications wn
            JOIN workflows w ON wn.workflow_id = w.id
            WHERE wn.user_id = ?
                AND wn.is_read = 0
            ORDER BY wn.created_at DESC
            LIMIT ?
        ");
        $query->execute([$user_id, $limit]);
        return $query->fetchAll();
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationRead($notification_id, $user_id) {
        $this->pdo->prepare("
            UPDATE workflow_notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ")->execute([$notification_id, $user_id]);
    }
}

// If called directly (for cron job)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $alert_system = new OverdueAlertSystem($pdo);
    $results = $alert_system->processOverdueTasks();
    
    echo "Overdue Alert System Results:\n";
    echo "- Overdue tasks processed: {$results['overdue_processed']}\n";
    echo "- Due soon tasks processed: {$results['due_soon_processed']}\n";
    echo "- Notifications sent: {$results['notifications_sent']}\n";
    echo "- Escalations created: {$results['escalations_created']}\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
}
?>