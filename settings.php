<?php
session_start();
require 'config.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real system, you would update settings in database or config file
    $_SESSION['alert'] = 'Settings updated successfully';
    header("Location: settings.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Settings - BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Include Responsive Header -->
    <?php require_once 'includes/page-header.php'; ?>
    
    <div class="container mt-4">        
        <?php if (isset($_SESSION['alert'])): ?>
            <div class="alert alert-success"><?= $_SESSION['alert'] ?></div>
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>
        
        <form method="post">
            <div class="card mb-4">
                <div class="card-header">General Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">System Name</label>
                        <input type="text" class="form-control" name="system_name" value="BizAutoPro">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Timezone</label>
                        <select class="form-select" name="timezone">
                            <option value="UTC">UTC</option>
                            <option value="America/New_York" selected>Eastern Time (ET)</option>
                            <option value="Europe/London">London</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">Email Notifications</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="email_alerts" checked>
                        <label class="form-check-label">Enable email notifications</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" value="admin@example.com">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>