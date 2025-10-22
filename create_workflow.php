<?php
session_start();
require 'config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login first";
    header("Location: login.php");
    exit;
}

// Authorization check - Only admin can create workflows
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. Only administrators can create workflows.";
    header("Location: workflows.php");
    exit;
}

// Handle file upload function
function handleFileUpload($workflow_id) {
    global $pdo;
    
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $upload_dir = 'uploads/workflows/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['attachment'];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = 'workflow_' . $workflow_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $safe_filename;
    
    // Validate file type and size
    $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'xlsx', 'xls'];
    if (!in_array(strtolower($file_extension), $allowed_types)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed_types));
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        throw new Exception("File size exceeds 10MB limit");
    }
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Save attachment info to database
        $stmt = $pdo->prepare("INSERT INTO workflow_attachments 
                              (workflow_id, filename, original_name, file_path, file_size, mime_type, uploaded_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $workflow_id,
            $safe_filename,
            $file['name'],
            $file_path,
            $file['size'],
            $file['type'],
            $_SESSION['user_id']
        ]);
        
        return $file_path;
    }
    
    throw new Exception("Failed to upload file");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty(trim($_POST['title']))) {
            throw new Exception("Title is required");
        }
        
        if (empty($_POST['assigned_to'])) {
            throw new Exception("Assigned user is required");
        }
        
        // Validate due date
        $due_date = null;
        if (!empty($_POST['due_date'])) {
            $due_date = $_POST['due_date'];
            if (strtotime($due_date) < time()) {
                throw new Exception("Due date cannot be in the past");
            }
        }
        
        // Validate estimated hours
        $estimated_hours = null;
        if (!empty($_POST['estimated_hours']) && is_numeric($_POST['estimated_hours'])) {
            $estimated_hours = (float)$_POST['estimated_hours'];
            if ($estimated_hours <= 0) {
                throw new Exception("Estimated hours must be greater than 0");
            }
        }

        $pdo->beginTransaction();
        
        // Insert workflow
        $stmt = $pdo->prepare("INSERT INTO workflows 
                             (title, description, assigned_to, created_by, status, priority, due_date, estimated_hours, category) 
                             VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
        
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            (int)$_POST['assigned_to'],
            $_SESSION['user_id'],
            $_POST['priority'] ?? 'medium',
            $due_date,
            $estimated_hours,
            $_POST['category'] ?? 'General'
        ]);
        
        $workflow_id = $pdo->lastInsertId();
        
        // Handle file upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            handleFileUpload($workflow_id);
        }
        
        // Log workflow creation
        $stmt = $pdo->prepare("INSERT INTO workflow_history 
                              (workflow_id, user_id, action, notes, timestamp) 
                              VALUES (?, ?, 'created', ?, NOW())");
        $stmt->execute([$workflow_id, $_SESSION['user_id'], 'Workflow created with priority: ' . ($_POST['priority'] ?? 'medium')]);
        
        // Create notification for assigned user
        $stmt = $pdo->prepare("INSERT INTO workflow_notifications 
                              (workflow_id, user_id, notification_type, message) 
                              VALUES (?, ?, 'assigned', ?)");
        $notification_message = "You have been assigned a new task: " . trim($_POST['title']);
        $stmt->execute([$workflow_id, (int)$_POST['assigned_to'], $notification_message]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Workflow created successfully!";
        header("Location: workflows.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollback();
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
        .priority-low { border-left-color: #28a745; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-high { border-left-color: #fd7e14; }
        .priority-urgent { border-left-color: #dc3545; }
        
        /* Mobile-responsive design */
        @media (max-width: 768px) {
            .container-fluid h1 { font-size: 1.5rem; }
            .card { margin-bottom: 1rem; }
            .btn { padding: 0.5rem 1rem; font-size: 0.9rem; }
            .form-control, .form-select { font-size: 1rem; }
            .workflow-card:hover { transform: none; }
            .d-md-flex { flex-direction: column; }
            .me-md-2 { margin-right: 0 !important; margin-bottom: 0.5rem; }
        }
        
        @media (max-width: 576px) {
            .container { padding-left: 10px; padding-right: 10px; }
            .card-body { padding: 1rem; }
            .row > * { margin-bottom: 1rem; }
        }
        
        /* Priority indicators */
        .priority-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .priority-low .priority-indicator { background-color: #28a745; }
        .priority-medium .priority-indicator { background-color: #ffc107; }
        .priority-high .priority-indicator { background-color: #fd7e14; }
        .priority-urgent .priority-indicator { background-color: #dc3545; }
        
        /* Touch-friendly buttons */
        @media (pointer: coarse) {
            .btn { min-height: 44px; }
            .form-control, .form-select { min-height: 44px; }
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

                        <form method="post" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <div class="row">
                                <div class="col-lg-8">
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
                                            <label for="category" class="form-label">
                                                <i class="bi bi-tags"></i> Category
                                            </label>
                                            <select class="form-select" id="category" name="category">
                                                <option value="General" <?= ($_POST['category'] ?? '') == 'General' ? 'selected' : '' ?>>General</option>
                                                <option value="IT" <?= ($_POST['category'] ?? '') == 'IT' ? 'selected' : '' ?>>IT & Technology</option>
                                                <option value="Operations" <?= ($_POST['category'] ?? '') == 'Operations' ? 'selected' : '' ?>>Operations</option>
                                                <option value="Sales" <?= ($_POST['category'] ?? '') == 'Sales' ? 'selected' : '' ?>>Sales & Marketing</option>
                                                <option value="HR" <?= ($_POST['category'] ?? '') == 'HR' ? 'selected' : '' ?>>Human Resources</option>
                                                <option value="Finance" <?= ($_POST['category'] ?? '') == 'Finance' ? 'selected' : '' ?>>Finance</option>
                                                <option value="Quality" <?= ($_POST['category'] ?? '') == 'Quality' ? 'selected' : '' ?>>Quality Control</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-4">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-gear"></i> Task Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="priority" class="form-label">
                                                    <i class="bi bi-exclamation-triangle"></i> Priority
                                                </label>
                                                <select class="form-select" id="priority" name="priority">
                                                    <option value="low" <?= ($_POST['priority'] ?? '') == 'low' ? 'selected' : '' ?>>
                                                        🟢 Low
                                                    </option>
                                                    <option value="medium" <?= ($_POST['priority'] ?? 'medium') == 'medium' ? 'selected' : '' ?>>
                                                        🟡 Medium
                                                    </option>
                                                    <option value="high" <?= ($_POST['priority'] ?? '') == 'high' ? 'selected' : '' ?>>
                                                        🟠 High
                                                    </option>
                                                    <option value="urgent" <?= ($_POST['priority'] ?? '') == 'urgent' ? 'selected' : '' ?>>
                                                        🔴 Urgent
                                                    </option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="due_date" class="form-label">
                                                    <i class="bi bi-calendar-event"></i> Due Date
                                                </label>
                                                <input type="datetime-local" class="form-control" id="due_date" name="due_date"
                                                       min="<?= date('Y-m-d\TH:i') ?>"
                                                       value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                                                <div class="form-text">Optional deadline for completion</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="estimated_hours" class="form-label">
                                                    <i class="bi bi-clock"></i> Estimated Hours
                                                </label>
                                                <input type="number" class="form-control" id="estimated_hours" name="estimated_hours"
                                                       min="0.5" max="1000" step="0.5"
                                                       placeholder="e.g., 2.5"
                                                       value="<?= htmlspecialchars($_POST['estimated_hours'] ?? '') ?>">
                                                <div class="form-text">Expected time to complete</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="attachment" class="form-label">
                                                    <i class="bi bi-paperclip"></i> Attachment
                                                </label>
                                                <input type="file" class="form-control" id="attachment" name="attachment"
                                                       accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.xlsx,.xls">
                                                <div class="form-text">Max 10MB. Allowed: PDF, DOC, Images, Excel</div>
                                            </div>
                                            
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="user-avatar me-2">
                                                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <strong>Created By:</strong><br>
                                                    <small><?= htmlspecialchars($_SESSION['username'] ?? 'You') ?></small><br>
                                                    <small class="text-muted"><?= date('M j, Y') ?></small>
                                                </div>
                                            </div>
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
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>