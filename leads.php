<?php
session_start();
require 'config.php';

// Authorization check
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    header("Location: dashboard.php");
    exit;
}

// Fetch leads with optional filtering
$status = $_GET['status'] ?? 'all';
$query = "SELECT * FROM leads";
if (in_array($status, ['new', 'contacted', 'qualified', 'lost'])) {
    $query .= " WHERE status = ?";
    $leads = $pdo->prepare($query);
    $leads->execute([$status]);
} else {
    $leads = $pdo->query($query);
}
$leads = $leads->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bizautopro - Lead Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="dashboard.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h2>Lead Management</h2>
            </div>
            <a href="new_lead.php" class="btn btn-primary">Add New Lead</a>
        </div>
        
        <!-- Status Filter -->
        <div class="mb-3">
            <a href="?status=all" class="btn btn-sm <?= $status == 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="?status=new" class="btn btn-sm <?= $status == 'new' ? 'btn-primary' : 'btn-outline-primary' ?>">New</a>
            <a href="?status=contacted" class="btn btn-sm <?= $status == 'contacted' ? 'btn-primary' : 'btn-outline-primary' ?>">Contacted</a>
            <a href="?status=qualified" class="btn btn-sm <?= $status == 'qualified' ? 'btn-primary' : 'btn-outline-primary' ?>">Qualified</a>
            <a href="?status=lost" class="btn btn-sm <?= $status == 'lost' ? 'btn-primary' : 'btn-outline-primary' ?>">Lost</a>
        </div>

        <!-- Leads Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td><?= htmlspecialchars($lead['name']) ?></td>
                    <td>
                        <?= htmlspecialchars($lead['email']) ?><br>
                        <?= htmlspecialchars($lead['phone']) ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= 
                            $lead['status'] == 'qualified' ? 'success' : 
                            ($lead['status'] == 'lost' ? 'danger' : 'warning') 
                        ?>">
                            <?= ucfirst($lead['status']) ?>
                        </span>
                    </td>
                    <td><?= $lead['score'] ?></td>
                    <td>
                        <a href="edit_lead.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="lead_actions.php?action=convert&id=<?= $lead['id'] ?>" class="btn btn-sm btn-success">Convert</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>