<?php
session_start();
require 'config.php';

if (!in_array($_SESSION['role'], ['admin', 'manager', 'sales'])) {
    header("Location: dashboard.php");
    exit;
}

$lead_id = $_GET['id'] ?? 0;

$lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$lead_id]);
$lead = $lead->fetch();

if (!$lead) {
    die("Lead not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $company = $_POST['company'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    $stmt = $pdo->prepare("
        UPDATE leads 
        SET name = ?, email = ?, phone = ?, company = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $email, $phone, $company, $status, $notes, $lead_id]);
    
    header("Location: leads.php");
    exit;
}

$team = $pdo->query("SELECT id, username FROM users WHERE role IN ('manager', 'sales')")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Lead</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Edit Lead</h2>
        
        <form method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" 
                           value="<?= htmlspecialchars($lead['name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company</label>
                    <input type="text" class="form-control" name="company" 
                           value="<?= htmlspecialchars($lead['company'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" 
                           value="<?= htmlspecialchars($lead['email']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone" 
                           value="<?= htmlspecialchars($lead['phone']) ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <option value="new" <?= $lead['status'] == 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= $lead['status'] == 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="qualified" <?= $lead['status'] == 'qualified' ? 'selected' : '' ?>>Qualified</option>
                        <option value="lost" <?= $lead['status'] == 'lost' ? 'selected' : '' ?>>Lost</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Assigned To</label>
                    <select class="form-select" name="assigned_to">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($team as $member): ?>
                            <option value="<?= $member['id'] ?>" 
                                <?= $member['id'] == $lead['assigned_to'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($member['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Lead</button>
            <a href="leads.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>