<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$workflow_id = $_GET['id'] ?? 0;

// Get workflow details
$stmt = $pdo->prepare("
    SELECT w.*, 
           creator.username AS creator_name,
           assignee.username AS assignee_name
    FROM workflows w
    LEFT JOIN users creator ON w.created_by = creator.id
    LEFT JOIN users assignee ON w.assigned_to = assignee.id
    WHERE w.id = ?
");
$stmt->execute([$workflow_id]);
$workflow = $stmt->fetch();

if (!$workflow) {
    die("Workflow not found");
}

// Get activity history
$history = $pdo->prepare("
    SELECT h.*, u.username 
    FROM workflow_history h
    JOIN users u ON h.user_id = u.id
    WHERE h.workflow_id = ?
    ORDER BY h.timestamp DESC
");
$history->execute([$workflow_id]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Workflow Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?= htmlspecialchars($workflow['title']) ?></h2>
            <a href="workflows.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= 
                                $workflow['status'] == 'approved' ? 'success' : 
                                ($workflow['status'] == 'rejected' ? 'danger' : 'warning')
                            ?>">
                                <?= ucfirst($workflow['status']) ?>
                            </span>
                        </p>
                        <p><strong>Created By:</strong> <?= $workflow['creator_name'] ?></p>
                        <p><strong>Date Created:</strong> <?= date('M d, Y h:i A', strtotime($workflow['created_at'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Assigned To:</strong> <?= $workflow['assignee_name'] ?></p>
                        <?php if ($workflow['status'] == 'completed'): ?>
                            <p><strong>Completed On:</strong> <?= date('M d, Y', strtotime($workflow['updated_at'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <h5>Description</h5>
                <p><?= nl2br(htmlspecialchars($workflow['description'])) ?></p>
            </div>
        </div>

        <h4>Activity History</h4>
        <div class="list-group">
            <?php foreach ($history as $entry): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <strong><?= $entry['username'] ?></strong>
                    <small class="text-muted"><?= date('M d, Y h:i A', strtotime($entry['timestamp'])) ?></small>
                </div>
                <div class="mt-2">
                    <?= ucfirst($entry['action']) ?>
                    <?php if (!empty($entry['notes'])): ?>
                        <div class="alert alert-light mt-2"><?= nl2br(htmlspecialchars($entry['notes'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>