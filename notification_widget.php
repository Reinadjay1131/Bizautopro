<?php
/**
 * Notification Widget Component
 * Displays overdue alerts and notifications for dashboard
 */

require_once 'overdue_alert_system.php';

class NotificationWidget {
    private $alert_system;
    private $user_id;
    private $user_role;
    
    public function __construct($pdo, $user_id, $user_role) {
        $this->alert_system = new OverdueAlertSystem($pdo);
        $this->user_id = $user_id;
        $this->user_role = $user_role;
    }
    
    /**
     * Render the notification widget
     */
    public function render() {
        $notifications = $this->alert_system->getUserNotifications($this->user_id, 5);
        $overdue_stats = $this->alert_system->getOverdueStats();
        
        ob_start();
        ?>
        
        <!-- Notification Widget -->
        <div class="row mb-4">
            <!-- Overdue Alerts Summary -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            Overdue Task Summary
                        </h5>
                        
                        <div class="row text-center g-2" style="display: flex; flex-wrap: nowrap;">
                            <div class="col-3" style="flex: 1;">
                                <div class="metric-card compact-kpi danger-theme">
                                    <div class="metric-value"><?= $overdue_stats['total_overdue'] ?></div>
                                    <div class="metric-label">Total Overdue</div>
                                </div>
                            </div>
                            <div class="col-3" style="flex: 1;">
                                <div class="metric-card compact-kpi warning-theme">
                                    <div class="metric-value"><?= $overdue_stats['urgent_overdue'] ?></div>
                                    <div class="metric-label">Urgent</div>
                                </div>
                            </div>
                            <div class="col-3" style="flex: 1;">
                                <div class="metric-card compact-kpi info-theme">
                                    <div class="metric-value"><?= $overdue_stats['due_today'] ?></div>
                                    <div class="metric-label">Due Today</div>
                                </div>
                            </div>
                            <div class="col-3" style="flex: 1;">
                                <div class="metric-card compact-kpi primary-theme">
                                    <div class="metric-value"><?= $overdue_stats['due_this_week'] ?></div>
                                    <div class="metric-label">Due This Week</div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($overdue_stats['total_overdue'] > 0): ?>
                            <div class="alert alert-danger mt-3">
                                <strong>Action Required:</strong> 
                                <?= $overdue_stats['total_overdue'] ?> task(s) are overdue 
                                (avg: <?= $overdue_stats['avg_overdue_days'] ?> days).
                                <a href="workflows.php?status=overdue" class="alert-link">View overdue tasks</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bell-fill text-primary"></i>
                            Recent Notifications
                            <?php if (count($notifications) > 0): ?>
                                <span class="badge bg-danger"><?= count($notifications) ?></span>
                            <?php endif; ?>
                        </h5>
                        
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted">No new notifications</p>
                        <?php else: ?>
                            <div class="notification-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item mb-2 p-2 rounded" 
                                         data-notification-id="<?= $notification['id'] ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-icon me-2">
                                                <?php
                                                $icon_class = match($notification['notification_type']) {
                                                    'overdue' => 'bi-exclamation-triangle-fill text-danger',
                                                    'due_soon' => 'bi-clock-fill text-warning',
                                                    'escalation' => 'bi-arrow-up-circle-fill text-danger',
                                                    'completed' => 'bi-check-circle-fill text-success',
                                                    default => 'bi-info-circle-fill text-info'
                                                };
                                                ?>
                                                <i class="bi <?= $icon_class ?>"></i>
                                            </div>
                                            <div class="notification-content flex-grow-1">
                                                <div class="notification-message">
                                                    <?= htmlspecialchars($notification['message']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= $this->timeAgo($notification['created_at']) ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-secondary mark-read-btn" 
                                                    onclick="markNotificationRead(<?= $notification['id'] ?>)"
                                                    title="Mark as read">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">
                                    View All Notifications
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .metric-card {
            padding: 1rem;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Ensure side-by-side layout */
        .row.text-center.g-2 {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 0.5rem;
        }
        
        .row.text-center.g-2 > [class*="col-"] {
            flex: 1 !important;
            min-width: 0;
        }
        
        /* Compact KPI Cards - matching dashboard style */
        .metric-card.compact-kpi {
            padding: 0.75rem 0.5rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid transparent;
            transition: all 0.3s ease;
            color: white !important;
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100px;
        }
        
        .metric-card.compact-kpi .metric-value {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.2rem;
            color: white !important;
        }
        
        .metric-card.compact-kpi .metric-label {
            font-size: 0.75rem;
            opacity: 0.9;
            line-height: 1.1;
            margin-bottom: 0.15rem;
            color: white !important;
        }
        
        /* Theme-aware KPI styles */
        .success-theme {
            background: linear-gradient(135deg, #10b981, #16a34a);
        }
        
        .danger-theme {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .warning-theme {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .info-theme {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .primary-theme {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }
        
        .secondary-theme {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        
        /* Dark mode adjustments */
        [data-theme="dark"] .metric-card.compact-kpi {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="dark"] .success-theme {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        [data-theme="dark"] .danger-theme {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        [data-theme="dark"] .warning-theme {
            background: linear-gradient(135deg, #d97706, #b45309);
        }
        
        [data-theme="dark"] .info-theme {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        
        [data-theme="dark"] .primary-theme {
            background: linear-gradient(135deg, #4f46e5, #3730a3);
        }
        
        .compact-overdue-card {
            padding: 0.75rem 0.5rem;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .compact-overdue-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        
        .metric-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .notification-item {
            background: rgba(0, 123, 255, 0.05);
            border-left: 3px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(0, 123, 255, 0.1);
            transform: translateX(2px);
        }
        
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Dark mode support for compact overdue cards */
        [data-theme="dark"] .compact-overdue-card {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2) !important;
        }
        
        [data-theme="dark"] .compact-overdue-card:hover {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        /* Ensure responsive side-by-side layout */
        @media (max-width: 992px) {
            .compact-overdue-card, .metric-card.compact-kpi {
                min-height: 70px;
                padding: 0.5rem 0.25rem;
            }
        }
        
        @media (max-width: 768px) {
            .metric-value {
                font-size: 1.5rem;
            }
            .metric-label {
                font-size: 0.75rem;
            }
            .compact-overdue-card, .metric-card.compact-kpi {
                min-height: 60px;
                padding: 0.5rem 0.25rem;
            }
        }
        
        @media (max-width: 576px) {
            .row.g-2 > [class*="col-"] .compact-overdue-card,
            .row.g-2 > [class*="col-"] .metric-card.compact-kpi {
                margin-bottom: 0.5rem;
            }
        }
        </style>
        
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
                        notificationElement.style.opacity = '0.5';
                        setTimeout(() => notificationElement.remove(), 300);
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            if (document.hidden) return; // Don't refresh if tab is not active
            
            fetch('notifications.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.hasNewNotifications) {
                        location.reload(); // Simple refresh for now
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 30000);
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Convert timestamp to human-readable time ago
     */
    private function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . 'm ago';
        if ($time < 86400) return floor($time/3600) . 'h ago';
        if ($time < 2592000) return floor($time/86400) . 'd ago';
        
        return date('M j, Y', strtotime($datetime));
    }
}
?>