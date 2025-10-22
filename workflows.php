<?php
session_start();
require 'config.php';

// Authorization check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user's workflows - ALL USERS CAN SEE ALL WORKFLOWS
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

# Filter by status if requested
$status_filter = $_GET['status'] ?? 'all';
$where_clause = '';
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'overdue') {
        $where_clause = 'WHERE w.due_date IS NOT NULL AND w.due_date < NOW() AND w.status NOT IN ("completed", "cancelled")';
    } else {
        $where_clause = 'WHERE w.status = ?';
        $params[] = $status_filter;
    }
}

// Get all workflows with enhanced information
$query = "
    SELECT w.*, 
           u1.username as creator, 
           u2.username as assignee,
           CASE 
               WHEN w.due_date IS NOT NULL AND w.due_date < NOW() AND w.status NOT IN ('completed', 'cancelled') 
               THEN 1 ELSE 0 
           END as is_overdue,
           CASE 
               WHEN w.due_date IS NOT NULL AND w.due_date < DATE_ADD(NOW(), INTERVAL 24 HOUR) AND w.status NOT IN ('completed', 'cancelled')
               THEN 1 ELSE 0 
           END as is_due_soon
    FROM workflows w
    LEFT JOIN users u1 ON w.created_by = u1.id
    LEFT JOIN users u2 ON w.assigned_to = u2.id
    $where_clause
    ORDER BY 
        CASE w.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        w.due_date ASC,
        w.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$workflows = $stmt->fetchAll();

// Handle status updates with enhanced permission system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $workflow_id = $_POST['workflow_id'];
    $action = $_POST['action'];
    
    // Verify workflow exists
    $stmt = $pdo->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->execute([$workflow_id]);
    $workflow = $stmt->fetch();
    
    if (!$workflow) {
        $_SESSION['error'] = "Workflow not found";
        header("Location: workflows.php");
        exit;
    }
    
    $allowed = false;
    $update_data = [];
    
    // New permission system based on requirements
    if ($user_role === 'admin') {
        // Admin can do everything: assign, cancel, approve, reject
        $allowed = true;
    } elseif ($action === 'complete') {
        // ANY USER can complete a task assigned to them
        if ($workflow['assigned_to'] == $user_id) {
            $allowed = true;
            // Track completion time and user
            $actual_hours = $_POST['actual_hours'] ?? null;
            $completion_notes = $_POST['completion_notes'] ?? '';
            
            $update_data = [
                'status' => 'completed',
                'completed_by' => $user_id,
                'completion_date' => date('Y-m-d H:i:s'),
                'actual_hours' => $actual_hours ? (float)$actual_hours : null,
                'completion_notes' => $completion_notes
            ];
        }
    }
    
    if ($allowed) {
        try {
            $pdo->beginTransaction();
            
            if (!empty($update_data)) {
                // Complex update with multiple fields
                $set_clauses = [];
                $values = [];
                
                foreach ($update_data as $field => $value) {
                    $set_clauses[] = "$field = ?";
                    $values[] = $value;
                }
                $values[] = $workflow_id;
                
                $sql = "UPDATE workflows SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($values);
            } else {
                // Simple status update
                $new_status = match($action) {
                    'approve' => 'approved',
                    'reject' => 'rejected',
                    'cancel' => 'cancelled',
                    default => 'pending'
                };
                
                $pdo->prepare("UPDATE workflows SET status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$new_status, $workflow_id]);
            }
            
            // Enhanced logging
            $action_description = match($action) {
                'complete' => 'marked as completed' . ($update_data['actual_hours'] ?? false ? ' (took ' . $update_data['actual_hours'] . ' hours)' : ''),
                'approve' => 'approved task',
                'reject' => 'rejected task',
                'cancel' => 'cancelled task',
                default => 'updated task'
            };
            
            $pdo->prepare("
                INSERT INTO workflow_history 
                (workflow_id, user_id, action, notes, timestamp) 
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$workflow_id, $user_id, $action, $action_description]);
            
            // Create notification for relevant users
            if ($action === 'complete') {
                // Notify creator and admin
                $notifications = [
                    [$workflow['created_by'], 'completed', "Task '{$workflow['title']}' has been completed"],
                ];
                
                // Add admin notification if completer is not admin
                if ($user_role !== 'admin') {
                    $admin_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                    $admin_stmt->execute();
                    $admin = $admin_stmt->fetch();
                    if ($admin) {
                        $notifications[] = [$admin['id'], 'completed', "Task '{$workflow['title']}' has been completed by " . $_SESSION['username']];
                    }
                }
                
                foreach ($notifications as $notif) {
                    $pdo->prepare("INSERT INTO workflow_notifications (workflow_id, user_id, notification_type, message) VALUES (?, ?, ?, ?)")
                        ->execute([$workflow_id, $notif[0], $notif[1], $notif[2]]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = ucfirst($action_description) . " successfully!";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['error'] = "Failed to update workflow: " . $e->getMessage();
        }
        
        header("Location: workflows.php");
        exit;
    } else {
        $_SESSION['error'] = "You don't have permission to perform this action";
        header("Location: workflows.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bizautopro - Workflow Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .workflow-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
            margin-bottom: 1rem;
        }
        .workflow-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Status Colors */
        .status-pending { border-left-color: #ffc107; }
        .status-approved { border-left-color: #28a745; }
        .status-rejected { border-left-color: #dc3545; }
        .status-completed { border-left-color: #17a2b8; }
        .status-cancelled { border-left-color: #6c757d; }
        
        /* Priority Colors */
        .priority-urgent { 
            border-top: 3px solid #dc3545 !important;
            background: linear-gradient(to right, rgba(220, 53, 69, 0.1), transparent);
        }
        .priority-high { 
            border-top: 3px solid #fd7e14 !important;
            background: linear-gradient(to right, rgba(253, 126, 20, 0.1), transparent);
        }
        .priority-medium { 
            border-top: 3px solid #ffc107 !important;
        }
        .priority-low { 
            border-top: 3px solid #28a745 !important;
        }
        
        /* Overdue and Due Soon Styling */
        .overdue-card {
            border: 2px solid #dc3545 !important;
            animation: pulse-red 2s infinite;
        }
        .due-soon-card {
            border: 2px solid #fd7e14 !important;
        }
        
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .btn-group-sm-vertical {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            .workflow-card .btn {
                font-size: 0.875rem;
                padding: 0.375rem 0.75rem;
            }
            .card-title {
                font-size: 1.1rem;
            }
            .badge-stack {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
            }
        }
        
        /* Touch-friendly buttons */
        @media (hover: none) {
            .btn {
                min-height: 44px;
            }
        }
        
        .workflow-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            background: rgba(0, 0, 0, 0.05);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
        }
        
        .progress-info {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(0, 123, 255, 0.1);
            border-radius: 0.25rem;
            font-size: 0.85rem;
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
                <h2 class="d-inline-block mb-0">Workflow Management</h2>
            </div>
            <?php 
            // Count overdue and due soon tasks
            $overdue_count = array_sum(array_column($workflows, 'is_overdue'));
            $due_soon_count = array_sum(array_column($workflows, 'is_due_soon'));
            ?>
            <div class="text-end">
                <?php if ($overdue_count > 0): ?>
                    <span class="badge bg-danger me-2">
                        <i class="bi bi-exclamation-triangle"></i> <?= $overdue_count ?> Overdue
                    </span>
                <?php endif; ?>
                <?php if ($due_soon_count > 0): ?>
                    <span class="badge bg-warning me-2">
                        <i class="bi bi-clock"></i> <?= $due_soon_count ?> Due Soon
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Filters -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="btn-group btn-group-sm d-flex flex-wrap" role="group">
                    <a href="?status=all" class="btn btn-outline-secondary <?= $status_filter === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-grid"></i> All
                    </a>
                    <a href="?status=overdue" class="btn btn-outline-danger <?= $status_filter === 'overdue' ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle"></i> Overdue
                    </a>
                    <a href="?status=pending" class="btn btn-outline-warning <?= $status_filter === 'pending' ? 'active' : '' ?>">
                        <i class="bi bi-clock"></i> Pending
                    </a>
                    <a href="?status=approved" class="btn btn-outline-success <?= $status_filter === 'approved' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i> Approved
                    </a>
                    <a href="?status=rejected" class="btn btn-outline-danger <?= $status_filter === 'rejected' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle"></i> Rejected
                    </a>
                    <a href="?status=completed" class="btn btn-outline-info <?= $status_filter === 'completed' ? 'active' : '' ?>">
                        <i class="bi bi-check-all"></i> Completed
                    </a>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($user_role === 'admin'): ?>
                    <a href="create_workflow.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">New Workflow</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Workflow List -->
        <div class="row">
            <?php foreach ($workflows as $workflow): 
                $card_classes = ['card', 'workflow-card', "status-{$workflow['status']}"];
                if ($workflow['priority']) {
                    $card_classes[] = "priority-{$workflow['priority']}";
                }
                if ($workflow['is_overdue']) {
                    $card_classes[] = 'overdue-card';
                } elseif ($workflow['is_due_soon']) {
                    $card_classes[] = 'due-soon-card';
                }
            ?>
            <div class="col-lg-6 col-xl-4 mb-3">
                <div class="<?= implode(' ', $card_classes) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-1"><?= htmlspecialchars($workflow['title']) ?></h5>
                            <div class="badge-stack">
                                <span class="badge bg-<?= 
                                    $workflow['status'] == 'approved' ? 'success' : 
                                    ($workflow['status'] == 'rejected' ? 'danger' : 
                                    ($workflow['status'] == 'completed' ? 'info' : 
                                    ($workflow['status'] == 'cancelled' ? 'secondary' : 'warning'))) 
                                ?>">
                                    <?= ucfirst($workflow['status']) ?>
                                </span>
                                
                                <?php if ($workflow['priority']): ?>
                                    <span class="badge bg-<?= 
                                        $workflow['priority'] == 'urgent' ? 'danger' : 
                                        ($workflow['priority'] == 'high' ? 'warning' : 
                                        ($workflow['priority'] == 'medium' ? 'info' : 'success')) 
                                    ?>">
                                        <?= ucfirst($workflow['priority']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($workflow['is_overdue']): ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-exclamation-triangle"></i> Overdue
                                    </span>
                                <?php elseif ($workflow['is_due_soon']): ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-clock"></i> Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($workflow['category']): ?>
                            <small class="text-muted">
                                <i class="bi bi-tag"></i> <?= htmlspecialchars($workflow['category']) ?>
                            </small>
                        <?php endif; ?>
                        
                        <p class="card-text"><?= htmlspecialchars($workflow['description']) ?></p>
                        
                        <!-- Enhanced Metadata -->
                        <div class="workflow-meta">
                            <div class="meta-item">
                                <i class="bi bi-person"></i> 
                                <strong>Creator:</strong> <?= htmlspecialchars($workflow['creator']) ?>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-person-check"></i> 
                                <strong>Assignee:</strong> <?= htmlspecialchars($workflow['assignee']) ?>
                            </div>
                            <?php if ($workflow['due_date']): ?>
                                <div class="meta-item">
                                    <i class="bi bi-calendar"></i> 
                                    <strong>Due:</strong> <?= date('M d, Y', strtotime($workflow['due_date'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($workflow['estimated_hours']): ?>
                                <div class="meta-item">
                                    <i class="bi bi-clock-history"></i> 
                                    <strong>Est:</strong> <?= $workflow['estimated_hours'] ?>h
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Progress Information -->
                        <?php if ($workflow['status'] == 'completed' && ($workflow['actual_hours'] || $workflow['completed_by'])): ?>
                            <div class="progress-info">
                                <?php if ($workflow['completed_by']): 
                                    $completed_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                    $completed_user->execute([$workflow['completed_by']]);
                                    $completed_username = $completed_user->fetchColumn();
                                ?>
                                    <div><strong>Completed by:</strong> <?= htmlspecialchars($completed_username) ?></div>
                                <?php endif; ?>
                                
                                <?php if ($workflow['actual_hours']): ?>
                                    <div><strong>Time taken:</strong> <?= $workflow['actual_hours'] ?>h</div>
                                    <?php if ($workflow['estimated_hours']): ?>
                                        <?php 
                                        $variance = $workflow['actual_hours'] - $workflow['estimated_hours'];
                                        $variance_color = $variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : 'text-muted');
                                        ?>
                                        <div class="<?= $variance_color ?>">
                                            <strong>Variance:</strong> <?= $variance > 0 ? '+' : '' ?><?= $variance ?>h
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($workflow['completion_notes']): ?>
                                    <div><strong>Notes:</strong> <?= htmlspecialchars($workflow['completion_notes']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                <?= date('M d, Y h:i A', strtotime($workflow['created_at'])) ?>
                            </small>
                            
                            <div class="btn-group btn-group-sm btn-group-sm-vertical">
                                <?php if ($workflow['status'] == 'pending' || $workflow['status'] == 'approved'): ?>
                                    <?php if ($user_role === 'admin'): ?>
                                        <?php if ($workflow['status'] == 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" title="Approve Task">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" title="Reject Task">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                            <button type="submit" name="action" value="cancel" class="btn btn-secondary btn-sm" title="Cancel Task" onclick="return confirm('Are you sure you want to cancel this task?')">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($workflow['status'] == 'approved' && $workflow['assigned_to'] == $user_id): ?>
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#completeModal<?= $workflow['id'] ?>" title="Mark as Complete">
                                            <i class="bi bi-check-all"></i> Complete
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="workflow_details.php?id=<?= $workflow['id'] ?>" class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="bi bi-eye"></i> <span class="d-none d-lg-inline">View</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Completion Modal -->
            <?php if ($workflow['status'] == 'approved' && $workflow['assigned_to'] == $user_id): ?>
                <div class="modal fade" id="completeModal<?= $workflow['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <div class="modal-header">
                                    <h5 class="modal-title">Complete Task: <?= htmlspecialchars($workflow['title']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    
                                    <div class="mb-3">
                                        <label for="actual_hours<?= $workflow['id'] ?>" class="form-label">Actual Hours Spent</label>
                                        <input type="number" step="0.5" min="0" class="form-control" 
                                               id="actual_hours<?= $workflow['id'] ?>" name="actual_hours" 
                                               placeholder="<?= $workflow['estimated_hours'] ? 'Estimated: ' . $workflow['estimated_hours'] . 'h' : 'Enter hours spent' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="completion_notes<?= $workflow['id'] ?>" class="form-label">Completion Notes (Optional)</label>
                                        <textarea class="form-control" id="completion_notes<?= $workflow['id'] ?>" 
                                                  name="completion_notes" rows="3" 
                                                  placeholder="Any notes about the completion..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-info">
                                        <i class="bi bi-check-all"></i> Mark Complete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php endforeach; ?>
            
            <?php if (empty($workflows)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No workflows found</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>