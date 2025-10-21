<?php
session_start();
require 'config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login first";
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty(trim($_POST['title']))) {
            throw new Exception("Title is required");
        }

        // Prepare and execute
        $stmt = $pdo->prepare("INSERT INTO workflows 
                             (title, description, assigned_to, created_by, status) 
                             VALUES (?, ?, ?, ?, 'pending')");
        
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            (int)$_POST['assigned_to'],
            $_SESSION['user_id']
        ]);

        $_SESSION['success'] = "Workflow created successfully!";
        header("Location: workflows.php");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users and recent workflows (REMOVED 'active = 1' condition)
$users = $pdo->query("SELECT id, username, role FROM users ORDER BY username")->fetchAll();
$recent_workflows = $pdo->query("
    SELECT w.title, u.username as assigned_to 
    FROM workflows w
    JOIN users u ON w.assigned_to = u.id
    ORDER BY w.created_at DESC LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Workflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .workflow-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .workflow-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
        }
    </style>
</head>

<div class="container-fluid bg-primary text-white p-3 mb-4">
    <div class="container">
        <h1 class="display-6 mb-0">BizAutoPro</h1>
    </div>
</div>

<body>

    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card workflow-card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-diagram-3"></i> Create New Workflow
                        </h4>
                        <a href="workflows.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Workflows
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    <i class="bi bi-card-heading"></i> Title *
                                </label>
                                <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                       placeholder="Enter workflow title" required
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                                <div class="invalid-feedback">Please provide a title</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="bi bi-text-paragraph"></i> Description
                                </label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" placeholder="Detailed description (optional)"><?= 
                                          htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="assigned_to" class="form-label">
                                        <i class="bi bi-person-check"></i> Assign To *
                                    </label>
                                    <select class="form-select" id="assigned_to" name="assigned_to" required>
                                        <option value="">Select Team Member</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"
                                                <?= ($_POST['assigned_to'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['username']) ?> 
                                                <small>(<?= ucfirst($user['role']) ?>)</small>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select an assignee</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i> Created By
                                    </label>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'You') ?></strong>
                                            <div class="text-muted small"><?= date('M j, Y') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send-check"></i> Create Workflow
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Recent Workflows Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-clock-history"></i> Recently Created
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_workflows as $wf): ?>
                            <a href="workflows.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($wf['title']) ?></h6>
                                    <small>Now</small>
                                </div>
                                <small class="text-muted">
                                    Assigned to: <?= htmlspecialchars($wf['assigned_to']) ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-lightning-charge"></i> Quick Actions
                        </h6>
                        <div class="d-grid gap-2">
                            <a href="create_workflow.php" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> New Workflow
                            </a>
                            <a href="workflows.php?filter=pending" class="btn btn-outline-warning">
                                <i class="bi bi-hourglass"></i> Pending Tasks
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    
    // Auto-focus title field
    document.getElementById('title')?.focus();
    </script>
</body>
</html>