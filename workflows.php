<?php
session_start();
require 'config.php';

// Authorization check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user's workflows based on role
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($user_role === 'admin') {
    $workflows = $pdo->query("
        SELECT w.*, u1.username as creator, u2.username as assignee 
        FROM workflows w
        LEFT JOIN users u1 ON w.created_by = u1.id
        LEFT JOIN users u2 ON w.assigned_to = u2.id
        ORDER BY w.created_at DESC
    ")->fetchAll();
} else {
    $workflows = $pdo->prepare("
        SELECT w.*, u1.username as creator, u2.username as assignee 
        FROM workflows w
        LEFT JOIN users u1 ON w.created_by = u1.id
        LEFT JOIN users u2 ON w.assigned_to = u2.id
        WHERE w.assigned_to = ? OR w.created_by = ?
        ORDER BY w.created_at DESC
    ");
    $workflows->execute([$user_id, $user_id]);
    $workflows = $workflows->fetchAll();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $workflow_id = $_POST['workflow_id'];
    $action = $_POST['action'];
    
    // Verify user has permission
    $stmt = $pdo->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->execute([$workflow_id]);
    $workflow = $stmt->fetch();
    
    $allowed = false;
    if ($user_role === 'admin') {
        $allowed = true;
    } elseif ($workflow['assigned_to'] == $user_id && in_array($action, ['complete', 'reject'])) {
        $allowed = true;
    } elseif ($workflow['created_by'] == $user_id && $action === 'cancel') {
        $allowed = true;
    }
    
    if ($allowed) {
        $new_status = match($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'complete' => 'completed',
            'cancel' => 'cancelled',
            default => 'pending'
        };
        
        $pdo->prepare("UPDATE workflows SET status = ? WHERE id = ?")
            ->execute([$new_status, $workflow_id]);
            
        // Log this action
        $pdo->prepare("
            INSERT INTO workflow_history 
            (workflow_id, user_id, action, timestamp) 
            VALUES (?, ?, ?, NOW())
        ")->execute([$workflow_id, $user_id, $action]);
        
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
        }
        .workflow-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-pending { border-left-color: #ffc107; }
        .status-approved { border-left-color: #28a745; }
        .status-rejected { border-left-color: #dc3545; }
        .status-completed { border-left-color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="dashboard.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h2>Workflow Management</h2>
            </div>
            <a href="create_workflow.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Workflow
            </a>
        </div>

        <!-- Status Filters -->
        <div class="btn-group mb-4" role="group">
            <a href="?status=all" class="btn btn-outline-secondary">All</a>
            <a href="?status=pending" class="btn btn-outline-warning">Pending</a>
            <a href="?status=approved" class="btn btn-outline-success">Approved</a>
            <a href="?status=rejected" class="btn btn-outline-danger">Rejected</a>
            <a href="?status=completed" class="btn btn-outline-info">Completed</a>
        </div>

        <!-- Workflow List -->
        <div class="row">
            <?php foreach ($workflows as $workflow): ?>
            <div class="col-md-6 mb-4">
                <div class="card workflow-card status-<?= $workflow['status'] ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title"><?= htmlspecialchars($workflow['title']) ?></h5>
                            <span class="badge bg-<?= 
                                $workflow['status'] == 'approved' ? 'success' : 
                                ($workflow['status'] == 'rejected' ? 'danger' : 
                                ($workflow['status'] == 'completed' ? 'info' : 'warning')) 
                            ?>">
                                <?= ucfirst($workflow['status']) ?>
                            </span>
                        </div>
                        
                        <p class="card-text"><?= htmlspecialchars($workflow['description']) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Created by: <?= $workflow['creator'] ?><br>
                                Assigned to: <?= $workflow['assignee'] ?><br>
                                <?= date('M d, Y h:i A', strtotime($workflow['created_at'])) ?>
                            </small>
                            
                            <div class="btn-group">
                                <?php if ($workflow['status'] == 'pending'): ?>
                                    <?php if ($workflow['assigned_to'] == $user_id || $user_role == 'admin'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($workflow['created_by'] == $user_id || $user_role == 'admin'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-secondary">
                                                <i class="bi bi-slash-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($workflow['status'] == 'approved' && $workflow['assigned_to'] == $user_id): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
                                        <button type="submit" name="action" value="complete" class="btn btn-sm btn-info">
                                            <i class="bi bi-check-all"></i> Complete
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="workflow_details.php?id=<?= $workflow['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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