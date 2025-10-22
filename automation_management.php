<?php
session_start();
require 'config.php';
require_once 'automation_engine.php';

// Authorization check - only admins can manage automation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$automation = new WorkflowAutomationEngine($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_rule':
                $conditions = [];
                $actions = [];
                
                // Build conditions array
                if (!empty($_POST['conditions'])) {
                    foreach ($_POST['conditions'] as $condition) {
                        if (!empty($condition['field']) && !empty($condition['value'])) {
                            $conditions[] = [
                                'field' => $condition['field'],
                                'operator' => $condition['operator'] ?? 'equals',
                                'value' => $condition['value']
                            ];
                        }
                    }
                }
                
                // Build actions array
                if (!empty($_POST['actions'])) {
                    foreach ($_POST['actions'] as $action) {
                        if (!empty($action['type'])) {
                            $action_data = ['type' => $action['type']];
                            
                            // Add action-specific data
                            switch ($action['type']) {
                                case 'assign':
                                case 'escalate':
                                    $action_data['user_id'] = $action['user_id'] ?? null;
                                    if ($action['type'] === 'escalate') {
                                        $action_data['message'] = $action['message'] ?? '';
                                    }
                                    break;
                                case 'notify':
                                    $action_data['user_id'] = $action['user_id'] ?? null;
                                    $action_data['message'] = $action['message'] ?? '';
                                    break;
                                case 'change_priority':
                                    $action_data['priority'] = $action['priority'] ?? 'medium';
                                    break;
                                case 'add_comment':
                                    $action_data['comment'] = $action['comment'] ?? '';
                                    break;
                                case 'set_due_date':
                                    $action_data['due_date'] = $action['due_date'] ?? '';
                                    break;
                            }
                            
                            $actions[] = $action_data;
                        }
                    }
                }
                
                if ($automation->createRule(
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['trigger'],
                    $conditions,
                    $actions,
                    $_POST['priority'] ?? 5
                )) {
                    $_SESSION['success'] = "Automation rule created successfully!";
                } else {
                    $_SESSION['error'] = "Failed to create automation rule.";
                }
                break;
                
            case 'toggle_rule':
                $rule_id = $_POST['rule_id'];
                $is_active = $_POST['is_active'] ? 1 : 0;
                $pdo->prepare("UPDATE workflow_automation_rules SET is_active = ? WHERE id = ?")
                    ->execute([$is_active, $rule_id]);
                $_SESSION['success'] = "Rule status updated successfully!";
                break;
                
            case 'run_automation':
                $results = $automation->processAllRules();
                $total_affected = array_sum(array_map(fn($r) => count($r['affected_workflows'] ?? []), $results));
                $_SESSION['success'] = "Automation completed! Processed " . count($results) . " rules, affected {$total_affected} workflows.";
                break;
        }
        
        header("Location: automation_management.php");
        exit;
    }
}

// Get existing rules
$rules = $pdo->query("
    SELECT *, 
           (SELECT COUNT(*) FROM automation_logs WHERE rule_id = workflow_automation_rules.id) as execution_count
    FROM workflow_automation_rules 
    ORDER BY priority ASC, created_at DESC
")->fetchAll();

// Get users for assignment options
$users = $pdo->query("SELECT id, username FROM users WHERE status = 'approved' ORDER BY username")->fetchAll();

// Get recent automation logs
$logs = $pdo->query("
    SELECT al.*, war.name as rule_name
    FROM automation_logs al
    JOIN workflow_automation_rules war ON al.rule_id = war.id
    ORDER BY al.executed_at DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bizautopro - Automation Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .automation-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .automation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .rule-active {
            border-left: 4px solid #28a745;
        }
        
        .rule-inactive {
            border-left: 4px solid #dc3545;
            opacity: 0.7;
        }
        
        .condition-item, .action-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .add-button {
            border: 2px dashed #dee2e6;
            background: transparent;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .add-button:hover {
            border-color: #007bff;
            color: #007bff;
            background: rgba(0, 123, 255, 0.05);
        }
        
        @media (max-width: 768px) {
            .btn-group-mobile {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .btn-group-mobile .btn {
                width: 100%;
            }
            
            .condition-item, .action-item {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="dashboard.php" class="btn btn-secondary btn-sm me-2">
                    <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Back to Dashboard</span>
                </a>
                <h2 class="d-inline-block mb-0">
                    <i class="bi bi-gear-fill"></i> Automation Management
                </h2>
            </div>
            <div class="btn-group btn-group-mobile">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRuleModal">
                    <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Create Rule</span>
                </button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="run_automation">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Run all active automation rules now?')">
                        <i class="bi bi-play-fill"></i> <span class="d-none d-sm-inline">Run Now</span>
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Automation Rules -->
        <div class="row">
            <div class="col-lg-8">
                <h4>Automation Rules</h4>
                
                <?php if (empty($rules)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-gear text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No automation rules created yet</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRuleModal">
                            Create Your First Rule
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($rules as $rule): ?>
                        <div class="automation-card card <?= $rule['is_active'] ? 'rule-active' : 'rule-inactive' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($rule['name']) ?></h5>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($rule['description']) ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> Created: <?= date('M d, Y', strtotime($rule['created_at'])) ?>
                                            | <i class="bi bi-play-circle"></i> Executed: <?= $rule['execution_count'] ?> times
                                        </small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_rule">
                                            <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $rule['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-<?= $rule['is_active'] ? 'warning' : 'success' ?>" 
                                                    title="<?= $rule['is_active'] ? 'Disable' : 'Enable' ?> Rule">
                                                <i class="bi bi-<?= $rule['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-funnel"></i> Conditions</h6>
                                        <?php 
                                        $conditions = json_decode($rule['conditions'], true);
                                        if ($conditions):
                                        ?>
                                            <?php foreach ($conditions as $condition): ?>
                                                <small class="badge bg-light text-dark me-1 mb-1">
                                                    <?= ucfirst($condition['field']) ?>: <?= htmlspecialchars($condition['value']) ?>
                                                </small>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small class="text-muted">No conditions set</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-lightning"></i> Actions</h6>
                                        <?php 
                                        $actions = json_decode($rule['actions'], true);
                                        if ($actions):
                                        ?>
                                            <?php foreach ($actions as $action): ?>
                                                <small class="badge bg-primary me-1 mb-1">
                                                    <?= ucfirst(str_replace('_', ' ', $action['type'])) ?>
                                                </small>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small class="text-muted">No actions set</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Trigger:</strong> <?= ucfirst(str_replace('_', ' ', $rule['trigger_event'])) ?>
                                        | <strong>Priority:</strong> <?= $rule['priority'] ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-4">
                <h4>Recent Activity</h4>
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <p class="text-muted">No automation activity yet</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-2">
                                        <i class="bi bi-<?= $log['success'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($log['rule_name']) ?></div>
                                        <small class="text-muted">
                                            <?= $log['action_type'] ?>
                                            <?php if ($log['affected_workflows']): ?>
                                                | <?= count(json_decode($log['affected_workflows'], true)) ?> workflows
                                            <?php endif; ?>
                                        </small>
                                        <div class="small text-muted">
                                            <?= date('M d, g:i A', strtotime($log['executed_at'])) ?>
                                        </div>
                                        <?php if ($log['error_message']): ?>
                                            <div class="small text-danger">
                                                Error: <?= htmlspecialchars($log['error_message']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Rule Modal -->
    <div class="modal fade" id="createRuleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Automation Rule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_rule">
                        
                        <!-- Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label">Rule Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="1">Highest (1)</option>
                                    <option value="2">High (2)</option>
                                    <option value="3">Medium (3)</option>
                                    <option value="4">Low (4)</option>
                                    <option value="5" selected>Lowest (5)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="trigger" class="form-label">Trigger Event</label>
                            <select class="form-select" id="trigger" name="trigger" required>
                                <option value="scheduled">Scheduled (runs periodically)</option>
                                <option value="workflow_created">When workflow is created</option>
                                <option value="workflow_overdue">When workflow becomes overdue</option>
                                <option value="workflow_assigned">When workflow is assigned</option>
                            </select>
                        </div>
                        
                        <!-- Conditions -->
                        <h6>Conditions</h6>
                        <div id="conditions-container">
                            <!-- Conditions will be added dynamically -->
                        </div>
                        <button type="button" class="btn add-button w-100 mb-3" onclick="addCondition()">
                            <i class="bi bi-plus"></i> Add Condition
                        </button>
                        
                        <!-- Actions -->
                        <h6>Actions</h6>
                        <div id="actions-container">
                            <!-- Actions will be added dynamically -->
                        </div>
                        <button type="button" class="btn add-button w-100" onclick="addAction()">
                            <i class="bi bi-plus"></i> Add Action
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    let conditionCount = 0;
    let actionCount = 0;
    
    const users = <?= json_encode($users) ?>;
    
    function addCondition() {
        const container = document.getElementById('conditions-container');
        const conditionHtml = `
            <div class="condition-item" id="condition-${conditionCount}">
                <div class="row">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="conditions[${conditionCount}][field]" required>
                            <option value="">Select Field</option>
                            <option value="status">Status</option>
                            <option value="priority">Priority</option>
                            <option value="category">Category</option>
                            <option value="overdue">Is Overdue</option>
                            <option value="overdue_days">Overdue Days</option>
                            <option value="assigned_to">Assigned To</option>
                            <option value="created_days_ago">Created Days Ago</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" 
                               name="conditions[${conditionCount}][value]" 
                               placeholder="Value" required>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="removeCondition(${conditionCount})">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', conditionHtml);
        conditionCount++;
    }
    
    function addAction() {
        const container = document.getElementById('actions-container');
        const userOptions = users.map(u => `<option value="${u.id}">${u.username}</option>`).join('');
        
        const actionHtml = `
            <div class="action-item" id="action-${actionCount}">
                <div class="row">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="actions[${actionCount}][type]" 
                                onchange="updateActionFields(${actionCount})" required>
                            <option value="">Select Action</option>
                            <option value="assign">Assign to User</option>
                            <option value="escalate">Escalate to User</option>
                            <option value="notify">Send Notification</option>
                            <option value="change_priority">Change Priority</option>
                            <option value="add_comment">Add Comment</option>
                            <option value="auto_approve">Auto Approve</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="action-fields-${actionCount}">
                        <!-- Dynamic fields based on action type -->
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="removeAction(${actionCount})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', actionHtml);
        actionCount++;
    }
    
    function updateActionFields(actionId) {
        const actionType = document.querySelector(`select[name="actions[${actionId}][type]"]`).value;
        const fieldsContainer = document.getElementById(`action-fields-${actionId}`);
        const userOptions = users.map(u => `<option value="${u.id}">${u.username}</option>`).join('');
        
        let fieldsHtml = '';
        
        switch (actionType) {
            case 'assign':
            case 'escalate':
                fieldsHtml = `
                    <select class="form-select form-select-sm" name="actions[${actionId}][user_id]" required>
                        <option value="">Select User</option>
                        ${userOptions}
                    </select>
                `;
                if (actionType === 'escalate') {
                    fieldsHtml += `
                        <input type="text" class="form-control form-control-sm mt-1" 
                               name="actions[${actionId}][message]" 
                               placeholder="Escalation message">
                    `;
                }
                break;
                
            case 'notify':
                fieldsHtml = `
                    <select class="form-select form-select-sm" name="actions[${actionId}][user_id]" required>
                        <option value="">Select User</option>
                        ${userOptions}
                    </select>
                    <input type="text" class="form-control form-control-sm mt-1" 
                           name="actions[${actionId}][message]" 
                           placeholder="Notification message" required>
                `;
                break;
                
            case 'change_priority':
                fieldsHtml = `
                    <select class="form-select form-select-sm" name="actions[${actionId}][priority]" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                `;
                break;
                
            case 'add_comment':
                fieldsHtml = `
                    <input type="text" class="form-control form-control-sm" 
                           name="actions[${actionId}][comment]" 
                           placeholder="Comment text" required>
                `;
                break;
        }
        
        fieldsContainer.innerHTML = fieldsHtml;
    }
    
    function removeCondition(id) {
        document.getElementById(`condition-${id}`).remove();
    }
    
    function removeAction(id) {
        document.getElementById(`action-${id}`).remove();
    }
    
    // Add initial condition and action
    document.addEventListener('DOMContentLoaded', function() {
        addCondition();
        addAction();
    });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>