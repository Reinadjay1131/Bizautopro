<?php
/**
 * Workflow Automation Engine
 * Handles automated actions based on predefined rules
 */

class WorkflowAutomationEngine {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AutomationLogger($pdo);
    }
    
    /**
     * Process all active automation rules
     */
    public function processAllRules() {
        $rules = $this->getActiveRules();
        $results = [];
        
        foreach ($rules as $rule) {
            try {
                $result = $this->processRule($rule);
                $results[] = $result;
                $this->logger->logAutomation($rule['id'], $result['action'], $result['affected_workflows'], $result['success']);
            } catch (Exception $e) {
                $this->logger->logError($rule['id'], $e->getMessage());
                $results[] = [
                    'rule_id' => $rule['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Process a specific automation rule
     */
    public function processRule($rule) {
        $conditions = json_decode($rule['conditions'], true);
        $actions = json_decode($rule['actions'], true);
        
        // Find workflows that match the rule conditions
        $matching_workflows = $this->findMatchingWorkflows($conditions);
        
        if (empty($matching_workflows)) {
            return [
                'rule_id' => $rule['id'],
                'action' => 'none',
                'affected_workflows' => [],
                'success' => true,
                'message' => 'No workflows matched the conditions'
            ];
        }
        
        // Execute actions on matching workflows
        $executed_actions = [];
        foreach ($matching_workflows as $workflow) {
            foreach ($actions as $action) {
                $this->executeAction($workflow, $action);
                $executed_actions[] = [
                    'workflow_id' => $workflow['id'],
                    'action' => $action['type'],
                    'details' => $action
                ];
            }
        }
        
        return [
            'rule_id' => $rule['id'],
            'action' => implode(', ', array_column($actions, 'type')),
            'affected_workflows' => array_column($matching_workflows, 'id'),
            'success' => true,
            'executed_actions' => $executed_actions
        ];
    }
    
    /**
     * Find workflows that match automation conditions
     */
    private function findMatchingWorkflows($conditions) {
        $where_clauses = [];
        $params = [];
        
        foreach ($conditions as $condition) {
            switch ($condition['field']) {
                case 'status':
                    $where_clauses[] = "w.status = ?";
                    $params[] = $condition['value'];
                    break;
                    
                case 'priority':
                    $where_clauses[] = "w.priority = ?";
                    $params[] = $condition['value'];
                    break;
                    
                case 'category':
                    $where_clauses[] = "w.category = ?";
                    $params[] = $condition['value'];
                    break;
                    
                case 'overdue':
                    if ($condition['value'] === true) {
                        $where_clauses[] = "w.due_date < NOW() AND w.status NOT IN ('completed', 'cancelled')";
                    }
                    break;
                    
                case 'overdue_days':
                    $where_clauses[] = "w.due_date < DATE_SUB(NOW(), INTERVAL ? DAY) AND w.status NOT IN ('completed', 'cancelled')";
                    $params[] = $condition['value'];
                    break;
                    
                case 'assigned_to':
                    $where_clauses[] = "w.assigned_to = ?";
                    $params[] = $condition['value'];
                    break;
                    
                case 'created_days_ago':
                    $where_clauses[] = "w.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $params[] = $condition['value'];
                    break;
            }
        }
        
        if (empty($where_clauses)) {
            return [];
        }
        
        $sql = "
            SELECT w.*, u1.username as creator_name, u2.username as assignee_name
            FROM workflows w
            LEFT JOIN users u1 ON w.created_by = u1.id
            LEFT JOIN users u2 ON w.assigned_to = u2.id
            WHERE " . implode(' AND ', $where_clauses);
            
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Execute an automation action on a workflow
     */
    private function executeAction($workflow, $action) {
        switch ($action['type']) {
            case 'assign':
                $this->assignWorkflow($workflow['id'], $action['user_id']);
                break;
                
            case 'escalate':
                $this->escalateWorkflow($workflow, $action['to_user_id'], $action['message'] ?? null);
                break;
                
            case 'notify':
                $this->sendNotification($workflow, $action['user_id'], $action['message']);
                break;
                
            case 'change_priority':
                $this->changePriority($workflow['id'], $action['priority']);
                break;
                
            case 'add_comment':
                $this->addComment($workflow['id'], $action['comment'], $action['user_id'] ?? 1);
                break;
                
            case 'set_due_date':
                $this->setDueDate($workflow['id'], $action['due_date']);
                break;
                
            case 'auto_approve':
                if ($workflow['status'] === 'pending') {
                    $this->changeStatus($workflow['id'], 'approved', 'Auto-approved by automation rule');
                }
                break;
        }
    }
    
    /**
     * Assign workflow to a user
     */
    private function assignWorkflow($workflow_id, $user_id) {
        $this->pdo->prepare("UPDATE workflows SET assigned_to = ? WHERE id = ?")
            ->execute([$user_id, $workflow_id]);
            
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, ?, 'assign', 'Auto-assigned by automation rule', NOW())
        ")->execute([$workflow_id, $user_id]);
    }
    
    /**
     * Escalate workflow to supervisor/admin
     */
    private function escalateWorkflow($workflow, $to_user_id, $message = null) {
        // Change assignment
        $this->assignWorkflow($workflow['id'], $to_user_id);
        
        // Send escalation notification
        $escalation_message = $message ?? "Workflow '{$workflow['title']}' has been escalated due to automation rule";
        $this->sendNotification($workflow, $to_user_id, $escalation_message, 'escalation');
        
        // Change priority to urgent if not already
        if ($workflow['priority'] !== 'urgent') {
            $this->changePriority($workflow['id'], 'urgent');
        }
    }
    
    /**
     * Send notification to user
     */
    private function sendNotification($workflow, $user_id, $message, $type = 'automation') {
        $this->pdo->prepare("
            INSERT INTO workflow_notifications (workflow_id, user_id, notification_type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$workflow['id'], $user_id, $type, $message]);
    }
    
    /**
     * Change workflow priority
     */
    private function changePriority($workflow_id, $priority) {
        $this->pdo->prepare("UPDATE workflows SET priority = ? WHERE id = ?")
            ->execute([$priority, $workflow_id]);
            
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, 1, 'priority_change', 'Priority changed to {$priority} by automation', NOW())
        ")->execute([$workflow_id]);
    }
    
    /**
     * Add comment to workflow
     */
    private function addComment($workflow_id, $comment, $user_id = 1) {
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, ?, 'comment', ?, NOW())
        ")->execute([$workflow_id, $user_id, $comment]);
    }
    
    /**
     * Set due date for workflow
     */
    private function setDueDate($workflow_id, $due_date) {
        $this->pdo->prepare("UPDATE workflows SET due_date = ? WHERE id = ?")
            ->execute([$due_date, $workflow_id]);
            
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, 1, 'due_date_set', 'Due date set to {$due_date} by automation', NOW())
        ")->execute([$workflow_id]);
    }
    
    /**
     * Change workflow status
     */
    private function changeStatus($workflow_id, $status, $notes = null) {
        $this->pdo->prepare("UPDATE workflows SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $workflow_id]);
            
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, 1, 'status_change', ?, NOW())
        ")->execute([$workflow_id, $notes ?? "Status changed to {$status}"]);
    }
    
    /**
     * Get all active automation rules
     */
    private function getActiveRules() {
        return $this->pdo->query("
            SELECT * FROM workflow_automation_rules 
            WHERE is_active = 1 
            ORDER BY priority ASC, id ASC
        ")->fetchAll();
    }
    
    /**
     * Get rules for a specific trigger
     */
    public function getRulesByTrigger($trigger) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM workflow_automation_rules 
            WHERE trigger_event = ? AND is_active = 1 
            ORDER BY priority ASC
        ");
        $stmt->execute([$trigger]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new automation rule
     */
    public function createRule($name, $description, $trigger, $conditions, $actions, $priority = 5) {
        $stmt = $this->pdo->prepare("
            INSERT INTO workflow_automation_rules 
            (name, description, trigger_event, conditions, actions, priority, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        return $stmt->execute([
            $name,
            $description,
            $trigger,
            json_encode($conditions),
            json_encode($actions),
            $priority
        ]);
    }
}

/**
 * Automation Logger
 */
class AutomationLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function logAutomation($rule_id, $action, $affected_workflows, $success) {
        $this->pdo->prepare("
            INSERT INTO automation_logs 
            (rule_id, action_type, affected_workflows, success, executed_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([
            $rule_id,
            $action,
            json_encode($affected_workflows),
            $success ? 1 : 0
        ]);
    }
    
    public function logError($rule_id, $error_message) {
        $this->pdo->prepare("
            INSERT INTO automation_logs 
            (rule_id, action_type, error_message, success, executed_at)
            VALUES (?, 'error', ?, 0, NOW())
        ")->execute([$rule_id, $error_message]);
    }
}
?>