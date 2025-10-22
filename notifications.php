<?php
session_start();
require 'config.php';

// Authorization check
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header("Location: login.php");
    exit;
}

require_once 'overdue_alert_system.php';

$user_id = $_SESSION['user_id'];
$alert_system = new OverdueAlertSystem($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $notification_id = $_POST['notification_id'];
                $alert_system->markNotificationRead($notification_id, $user_id);
                echo json_encode(['success' => true]);
                exit;
                
            case 'mark_all_read':
                $pdo->prepare("UPDATE workflow_notifications SET is_read = 1 WHERE user_id = ?")
                    ->execute([$user_id]);
                echo json_encode(['success' => true]);
                exit;
        }
    }
    
    if (isset($_GET['ajax'])) {
        // Check for new notifications
        $unread_count = $pdo->prepare("
            SELECT COUNT(*) FROM workflow_notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $unread_count->execute([$user_id]);
        $count = $unread_count->fetchColumn();
        
        echo json_encode(['hasNewNotifications' => $count > 0, 'unread_count' => $count]);
        exit;
    }
}

// Get all notifications for this user
$notifications_query = $pdo->prepare("
    SELECT wn.*, w.title as workflow_title, w.id as workflow_id
    FROM workflow_notifications wn
    JOIN workflows w ON wn.workflow_id = w.id
    WHERE wn.user_id = ?
    ORDER BY wn.created_at DESC
    LIMIT 50
");
$notifications_query->execute([$user_id]);
$notifications = $notifications_query->fetchAll();

// Get unread count
$unread_count = $pdo->prepare("
    SELECT COUNT(*) FROM workflow_notifications 
    WHERE user_id = ? AND is_read = 0
");
$unread_count->execute([$user_id]);
$unread_total = $unread_count->fetchColumn();

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minute(s) ago';
    if ($time < 86400) return floor($time/3600) . ' hour(s) ago';
    if ($time < 2592000) return floor($time/86400) . ' day(s) ago';
    
    return date('M j, Y g:i A', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bizautopro - Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .notification-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .notification-item.unread {
            background: rgba(0, 123, 255, 0.05);
            border-left-color: #007bff;
        }
        
        .notification-item.read {
            background: rgba(0, 0, 0, 0.02);
            border-left-color: #dee2e6;
        }
        
        .notification-item:hover {
            background: rgba(0, 123, 255, 0.1);
            transform: translateX(2px);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .notification-type-overdue { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .notification-type-due_soon { background: rgba(253, 126, 20, 0.1); color: #fd7e14; }
        .notification-type-escalation { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .notification-type-completed { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        @media (max-width: 768px) {
            .btn-group-sm-vertical {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
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
                    <i class="bi bi-bell-fill"></i> Notifications
                    <?php if ($unread_total > 0): ?>
                        <span class="badge bg-danger"><?= $unread_total ?></span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="btn-group btn-group-sm">
                <?php if ($unread_total > 0): ?>
                    <button class="btn btn-outline-primary" onclick="markAllRead()">
                        <i class="bi bi-check-all"></i> Mark All Read
                    </button>
                <?php endif; ?>
                <a href="workflows.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list-task"></i> View Workflows
                </a>
            </div>
        </div>
        
        <!-- Notification Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <h3><?= $unread_total ?></h3>
                        <small>Unread Notifications</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white text-center">
                    <div class="card-body">
                        <?php 
                        $overdue_notifications = array_filter($notifications, fn($n) => $n['notification_type'] === 'overdue' && !$n['is_read']);
                        ?>
                        <h3><?= count($overdue_notifications) ?></h3>
                        <small>Overdue Alerts</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white text-center">
                    <div class="card-body">
                        <?php 
                        $due_soon_notifications = array_filter($notifications, fn($n) => $n['notification_type'] === 'due_soon' && !$n['is_read']);
                        ?>
                        <h3><?= count($due_soon_notifications) ?></h3>
                        <small>Due Soon Alerts</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <?php 
                        $completed_notifications = array_filter($notifications, fn($n) => $n['notification_type'] === 'completed' && !$n['is_read']);
                        ?>
                        <h3><?= count($completed_notifications) ?></h3>
                        <small>Completed Tasks</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notifications List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Notifications</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No notifications yet</p>
                        <a href="workflows.php" class="btn btn-primary">View Workflows</a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>" 
                                 data-notification-id="<?= $notification['id'] ?>">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3 notification-type-<?= $notification['notification_type'] ?>">
                                        <?php
                                        $icon = match($notification['notification_type']) {
                                            'overdue' => 'bi-exclamation-triangle-fill',
                                            'due_soon' => 'bi-clock-fill',
                                            'escalation' => 'bi-arrow-up-circle-fill',
                                            'completed' => 'bi-check-circle-fill',
                                            default => 'bi-info-circle-fill'
                                        };
                                        ?>
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 <?= $notification['is_read'] ? 'text-muted' : 'text-dark' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $notification['notification_type'])) ?> Alert
                                            </h6>
                                            <small class="text-muted"><?= timeAgo($notification['created_at']) ?></small>
                                        </div>
                                        
                                        <p class="mb-1 <?= $notification['is_read'] ? 'text-muted' : 'text-dark' ?>">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-list-task"></i>
                                                <a href="workflow_details.php?id=<?= $notification['workflow_id'] ?>" 
                                                   class="text-decoration-none">
                                                    <?= htmlspecialchars($notification['workflow_title']) ?>
                                                </a>
                                            </small>
                                            
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="btn btn-sm btn-outline-primary mark-read-btn" 
                                                        onclick="markNotificationRead(<?= $notification['id'] ?>)"
                                                        title="Mark as read">
                                                    <i class="bi bi-check"></i> Mark Read
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function markNotificationRead(notificationId) {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_read&notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationElement) {
                    notificationElement.classList.remove('unread');
                    notificationElement.classList.add('read');
                    
                    const markBtn = notificationElement.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                    
                    // Update badge
                    const badge = document.querySelector('.badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent);
                        if (currentCount <= 1) {
                            badge.remove();
                        } else {
                            badge.textContent = currentCount - 1;
                        }
                    }
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    function markAllRead() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>