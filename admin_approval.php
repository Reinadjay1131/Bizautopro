<?php
session_start();
require 'config.php';

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'];
    
    if ($action === 'approve') {
        $role = $_POST['role']; // Get the assigned role
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved', role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    $_SESSION['success'] = "Action completed successfully";
    header("Location: admin_approval.php");
    exit;
}

// Get pending users
$pending_users = $pdo->query("SELECT * FROM users WHERE status = 'pending'")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Pending User Approvals</h2>
        
        <?php if (empty($pending_users)): ?>
            <div class="alert alert-info">No pending approvals</div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Requested Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="role" class="form-select d-inline-block w-auto">
                                    <option value="employee">Employee</option>
                                    <option value="manager">Manager</option>
                                </select>
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>