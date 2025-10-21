<?php
session_start();
require 'config.php';

// Admin only access
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle success messages
$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending User Approvals | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">BizAutoPro Admin</a>
            <div class="navbar-text text-white">
                Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </div>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> Pending User Approvals</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Users Waiting Approval</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Check what columns actually exist in users table
                    $stmt = $pdo->query("DESCRIBE users");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Build query based on available columns
                    $select_columns = ['id', 'username', 'email'];
                    if (in_array('created_at', $columns)) {
                        $select_columns[] = 'created_at';
                    } elseif (in_array('registration_date', $columns)) {
                        $select_columns[] = 'registration_date as created_at';
                    } elseif (in_array('date_created', $columns)) {
                        $select_columns[] = 'date_created as created_at';
                    }
                    
                    $columns_sql = implode(', ', $select_columns);
                    
                    $pending_users = $pdo->query("
                        SELECT $columns_sql 
                        FROM users 
                        WHERE status = 'pending' 
                        ORDER BY id DESC
                    ")->fetchAll();

                    if (empty($pending_users)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No pending user registrations.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <?php if (in_array('created_at', $columns) || in_array('registration_date', $columns) || in_array('date_created', $columns)): ?>
                                            <th>Registration Date</th>
                                        <?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <?php if (isset($user['created_at'])): ?>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('M d, Y g:i A', strtotime($user['created_at'])) ?>
                                                </small>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="approve_user.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-success" 
                                                   title="Approve User">
                                                   <i class="bi bi-check-lg"></i> Approve
                                                </a>
                                                <a href="reject_user.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-danger" 
                                                   title="Reject User"
                                                   onclick="return confirm('Are you sure you want to reject this user?')">
                                                   <i class="bi bi-x-lg"></i> Reject
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Found <?= count($pending_users) ?> pending registration(s)
                            </small>
                        </div>
                    <?php endif;
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error loading users: " . $e->getMessage() . "</div>";
                    // Debug: Show available columns
                    try {
                        $debug_columns = $pdo->query("DESCRIBE users")->fetchAll();
                        echo "<div class='alert alert-info'><strong>Available columns in users table:</strong><br>";
                        foreach ($debug_columns as $col) {
                            echo $col['Field'] . " (" . $col['Type'] . ")<br>";
                        }
                        echo "</div>";
                    } catch (Exception $debug_e) {
                        echo "<div class='alert alert-warning'>Cannot read table structure: " . $debug_e->getMessage() . "</div>";
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>