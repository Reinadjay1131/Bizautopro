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

// Get attachments
require_once 'attachment_manager.php';
$attachment_manager = new WorkflowAttachmentManager($pdo);
$attachments = $attachment_manager->getWorkflowAttachments($workflow_id);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bizautopro - Workflow Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .workflow-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        
        .priority-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .priority-urgent { background-color: #dc3545; }
        .priority-high { background-color: #fd7e14; }
        .priority-medium { background-color: #ffc107; }
        .priority-low { background-color: #28a745; }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 10px;
            height: 10px;
            background: #007bff;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #007bff;
        }
        
        .detail-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
        }
        
        .detail-value {
            flex: 1;
            text-align: right;
        }
        
        .overdue-alert {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .workflow-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .detail-label {
                min-width: unset;
            }
            
            .detail-value {
                text-align: left;
                width: 100%;
            }
            
            .timeline {
                padding-left: 1.5rem;
            }
            
            .timeline-item::before {
                left: -1.25rem;
            }
            
            .btn-group-mobile {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .btn-group-mobile .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 0 1rem;
            }
            
            .workflow-header h1 {
                font-size: 1.5rem;
            }
            
            .status-badge {
                font-size: 0.875rem;
                padding: 0.375rem 0.75rem;
            }
        }

        /* Attachment Interface Styling */
        .attachment-item {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: #fff;
        }

        .attachment-item:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
        }

        .attachment-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .attachment-icon.pdf { background: #ff6b6b; color: white; }
        .attachment-icon.doc { background: #4dabf7; color: white; }
        .attachment-icon.xls { background: #51cf66; color: white; }
        .attachment-icon.img { background: #845ef7; color: white; }
        .attachment-icon.txt { background: #ffd43b; color: #495057; }
        .attachment-icon.default { background: #868e96; color: white; }

        .attachment-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.25rem;
            word-break: break-word;
        }

        .attachment-meta {
            font-size: 0.875rem;
            color: #6c757d;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .attachment-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        .attachment-actions .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .upload-zone.dragover {
            border-style: solid;
            background: #bbdefb;
        }

        .upload-progress {
            display: none;
            margin-top: 1rem;
        }

        .file-input-label {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #007bff;
            color: white;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .file-input-label:hover {
            background: #0056b3;
        }

        .upload-instructions {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .empty-attachments {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-attachments i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .attachment-item {
                padding: 0.75rem;
            }

            .attachment-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
                margin-right: 0.75rem;
            }

            .attachment-meta {
                flex-direction: column;
                gap: 0.25rem;
            }

            .attachment-actions {
                margin-top: 0.5rem;
                width: 100%;
                justify-content: stretch;
            }

            .attachment-actions .btn {
                flex: 1;
            }

            .upload-zone {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Enhanced Header -->
        <div class="workflow-header">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h1 class="mb-2"><?= htmlspecialchars($workflow['title']) ?></h1>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <?php if ($workflow['priority']): ?>
                                <div class="d-flex align-items-center">
                                    <span class="priority-indicator priority-<?= $workflow['priority'] ?>"></span>
                                    <span class="badge bg-light text-dark"><?= ucfirst($workflow['priority']) ?> Priority</span>
                                </div>
                            <?php endif; ?>
                            <span class="status-badge badge bg-<?= 
                                $workflow['status'] == 'approved' ? 'success' : 
                                ($workflow['status'] == 'rejected' ? 'danger' : 
                                ($workflow['status'] == 'completed' ? 'info' : 
                                ($workflow['status'] == 'cancelled' ? 'secondary' : 'warning'))) 
                            ?>">
                                <?= ucfirst($workflow['status']) ?>
                            </span>
                            <?php if ($workflow['category']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($workflow['category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 btn-group-mobile">
                        <a href="workflows.php" class="btn btn-light">
                            <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Back to Workflows</span>
                        </a>
                        <?php if ($workflow['attachment_path']): ?>
                            <a href="<?= htmlspecialchars($workflow['attachment_path']) ?>" class="btn btn-outline-light" target="_blank">
                                <i class="bi bi-paperclip"></i> <span class="d-none d-sm-inline">View Attachment</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overdue Alert -->
        <?php 
        $is_overdue = $workflow['due_date'] && $workflow['due_date'] < date('Y-m-d H:i:s') && !in_array($workflow['status'], ['completed', 'cancelled']);
        if ($is_overdue): 
            $overdue_days = ceil((time() - strtotime($workflow['due_date'])) / (60 * 60 * 24));
        ?>
            <div class="overdue-alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>This task is <?= $overdue_days ?> day(s) overdue!</strong>
                        <div>Due date was: <?= date('M d, Y', strtotime($workflow['due_date'])) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Workflow Details -->
            <div class="col-lg-8 mb-4">
                <!-- Description -->
                <div class="detail-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-text"></i> Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?= nl2br(htmlspecialchars($workflow['description'])) ?></p>
                    </div>
                </div>
                
                <!-- Activity Timeline -->
                <div class="detail-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Activity Timeline</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($history): ?>
                            <div class="timeline">
                                <?php foreach ($history as $entry): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong><?= htmlspecialchars($entry['username']) ?></strong>
                                            <small class="text-muted"><?= date('M d, Y g:i A', strtotime($entry['timestamp'])) ?></small>
                                        </div>
                                        <div class="mb-1">
                                            <span class="badge bg-primary"><?= ucfirst($entry['action']) ?></span>
                                        </div>
                                        <?php if (!empty($entry['notes'])): ?>
                                            <div class="text-muted small">
                                                <?= nl2br(htmlspecialchars($entry['notes'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No activity recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attachments -->
                <div class="detail-card card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-paperclip"></i> Attachments</h5>
                            <?php if ($workflow['status'] !== 'completed' && $workflow['status'] !== 'cancelled'): ?>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-upload"></i> Upload
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted">No attachments uploaded yet.</p>
                        <?php else: ?>
                            <div class="attachment-list">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="attachment-item d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <div class="attachment-icon me-2">
                                                <?php
                                                $extension = strtolower(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION));
                                                $icon_class = match($extension) {
                                                    'pdf' => 'bi-file-earmark-pdf text-danger',
                                                    'doc', 'docx' => 'bi-file-earmark-word text-primary',
                                                    'xls', 'xlsx' => 'bi-file-earmark-excel text-success',
                                                    'ppt', 'pptx' => 'bi-file-earmark-ppt text-warning',
                                                    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp' => 'bi-file-earmark-image text-info',
                                                    'zip', 'rar', '7z' => 'bi-file-earmark-zip text-secondary',
                                                    'mp4', 'avi', 'mov', 'wmv' => 'bi-file-earmark-play text-purple',
                                                    default => 'bi-file-earmark text-muted'
                                                };
                                                ?>
                                                <i class="bi <?= $icon_class ?>" style="font-size: 1.5rem;"></i>
                                            </div>
                                            <div class="attachment-details flex-grow-1">
                                                <div class="fw-bold"><?= htmlspecialchars($attachment['original_filename']) ?></div>
                                                <small class="text-muted">
                                                    <?= $attachment_manager->formatFileSize($attachment['file_size']) ?>
                                                    | Uploaded by <?= htmlspecialchars($attachment['uploaded_by_name']) ?>
                                                    | <?= date('M d, Y g:i A', strtotime($attachment['uploaded_at'])) ?>
                                                </small>
                                                <?php if ($attachment['description']): ?>
                                                    <div class="small text-muted mt-1">
                                                        <?= htmlspecialchars($attachment['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="attachment-actions btn-group btn-group-sm">
                                            <?php
                                            $viewable_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'webp'];
                                            if (in_array($extension, $viewable_extensions)):
                                            ?>
                                                <a href="attachment_handler.php?action=view&id=<?= $attachment['id'] ?>" 
                                                   class="btn btn-outline-info" target="_blank" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="attachment_handler.php?action=download&id=<?= $attachment['id'] ?>" 
                                               class="btn btn-outline-primary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin' || $attachment['uploaded_by'] == $_SESSION['user_id']): ?>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="deleteAttachment(<?= $attachment['id'] ?>)"
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Workflow Information Sidebar -->
            <div class="col-lg-4">
                <!-- Basic Information -->
                <div class="detail-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span class="detail-label">Created By:</span>
                            <span class="detail-value"><?= htmlspecialchars($workflow['creator_name']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Assigned To:</span>
                            <span class="detail-value"><?= htmlspecialchars($workflow['assignee_name']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value"><?= date('M d, Y g:i A', strtotime($workflow['created_at'])) ?></span>
                        </div>
                        <?php if ($workflow['due_date']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Due Date:</span>
                                <span class="detail-value <?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('M d, Y', strtotime($workflow['due_date'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($workflow['status'] == 'completed' && $workflow['completion_date']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Completed:</span>
                                <span class="detail-value text-success">
                                    <?= date('M d, Y g:i A', strtotime($workflow['completion_date'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Time Tracking -->
                <?php if ($workflow['estimated_hours'] || $workflow['actual_hours']): ?>
                    <div class="detail-card card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-stopwatch"></i> Time Tracking</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($workflow['estimated_hours']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Estimated:</span>
                                    <span class="detail-value"><?= $workflow['estimated_hours'] ?> hours</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($workflow['actual_hours']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Actual:</span>
                                    <span class="detail-value"><?= $workflow['actual_hours'] ?> hours</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($workflow['estimated_hours'] && $workflow['actual_hours']): ?>
                                <?php 
                                $variance = $workflow['actual_hours'] - $workflow['estimated_hours'];
                                $variance_class = $variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : 'text-muted');
                                ?>
                                <div class="detail-row">
                                    <span class="detail-label">Variance:</span>
                                    <span class="detail-value <?= $variance_class ?>">
                                        <?= $variance > 0 ? '+' : '' ?><?= $variance ?> hours
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Completion Details -->
                <?php if ($workflow['status'] == 'completed'): ?>
                    <div class="detail-card card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Completion Details</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($workflow['completed_by']): 
                                $completed_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                $completed_user->execute([$workflow['completed_by']]);
                                $completed_username = $completed_user->fetchColumn();
                            ?>
                                <div class="detail-row">
                                    <span class="detail-label">Completed By:</span>
                                    <span class="detail-value"><?= htmlspecialchars($completed_username) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($workflow['completion_notes']): ?>
                                <div class="mt-3">
                                    <strong>Completion Notes:</strong>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <?= nl2br(htmlspecialchars($workflow['completion_notes'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    </div>
    
    <!-- Upload Attachment Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="uploadForm" method="post" action="attachment_handler.php" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Attachment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="workflow_id" value="<?= $workflow_id ?>">
                        
                        <div class="mb-3">
                            <label for="attachment" class="form-label">Choose File</label>
                            <input type="file" class="form-control" id="attachment" name="attachment" required>
                            <div class="form-text">
                                Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, JPG, PNG, GIF, ZIP, RAR, MP4<br>
                                Maximum size: 10MB
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Brief description of the attachment..."></textarea>
                        </div>
                        
                        <div class="upload-progress d-none">
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Uploading...</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // File upload with progress
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        const progressContainer = document.querySelector('.upload-progress');
        const progressBar = progressContainer.querySelector('.progress-bar');
        const submitButton = this.querySelector('button[type="submit"]');
        
        // Show progress
        progressContainer.classList.remove('d-none');
        submitButton.disabled = true;
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = Math.round(percentComplete) + '%';
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    location.reload(); // Refresh to show new attachment
                } else {
                    alert('Upload failed: ' + response.error);
                    progressContainer.classList.add('d-none');
                    submitButton.disabled = false;
                }
            } else {
                alert('Upload failed: Server error');
                progressContainer.classList.add('d-none');
                submitButton.disabled = false;
            }
        };
        
        xhr.onerror = function() {
            alert('Upload failed: Network error');
            progressContainer.classList.add('d-none');
            submitButton.disabled = false;
        };
        
        xhr.open('POST', 'attachment_handler.php');
        xhr.send(formData);
    });
    
    // Delete attachment function
    function deleteAttachment(attachmentId) {
        if (!confirm('Are you sure you want to delete this attachment?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('attachment_id', attachmentId);
        formData.append('workflow_id', <?= $workflow_id ?>);
        formData.append('ajax', '1');
        
        fetch('attachment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Refresh to remove deleted attachment
            } else {
                alert('Delete failed: ' + data.error);
            }
        })
        .catch(error => {
            alert('Delete failed: Network error');
        });
    }
    
    // File type validation
    document.getElementById('attachment').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (file.size > maxSize) {
            alert('File too large. Maximum size is 10MB.');
            this.value = '';
            return;
        }
        
        // Basic extension check (MIME type is validated server-side)
        const extension = file.name.split('.').pop().toLowerCase();
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'zip', 'rar', '7z', 'mp4', 'avi', 'mov', 'wmv'];
        
        if (!allowedExtensions.includes(extension)) {
            alert('File type not allowed. Please choose a supported file format.');
            this.value = '';
            return;
        }
    });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>