<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty(trim($_POST['name']))) {
            throw new Exception("Name is required");
        }

        $name = trim($_POST['name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $source = $_POST['source'];
        $assigned_to = $_POST['assigned_to'] ?: null;
        
        // Enhanced lead scoring
        $score = 0;
        if (!empty($email)) $score += 20;
        if (!empty($phone)) $score += 30;
        if ($source == 'referral') $score += 50;
        
        $stmt = $pdo->prepare("INSERT INTO leads 
                             (name, email, phone, source, score, assigned_to, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $phone, $source, $score, $assigned_to]);
        
        $_SESSION['success'] = "Lead captured successfully!";
        header("Location: leads.php");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get sales team
$sales_team = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('manager', 'sales') ORDER BY username")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capture New Lead | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .lead-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .lead-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .score-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">BizAutoPro</a>
            <div class="navbar-text text-white">
                <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '' ?>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card lead-card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-person-plus"></i> Capture New Lead
                        </h4>
                        <a href="leads.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Leads
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" novalidate>
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="bi bi-person-badge"></i> Contact Information
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label required">Full Name</label>
                                    <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                           placeholder="John Doe" required
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                    <div class="invalid-feedback">Please provide the lead's name</div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               placeholder="john@example.com"
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               placeholder="(123) 456-7890"
                                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="bi bi-info-circle"></i> Lead Details
                                </h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label for="source" class="form-label required">Source</label>
                                        <select class="form-select" id="source" name="source" required>
                                            <option value="">Select Source</option>
                                            <option value="website" <?= ($_POST['source'] ?? '') == 'website' ? 'selected' : '' ?>>Website</option>
                                            <option value="referral" <?= ($_POST['source'] ?? '') == 'referral' ? 'selected' : '' ?>>Referral</option>
                                            <option value="social" <?= ($_POST['source'] ?? '') == 'social' ? 'selected' : '' ?>>Social Media</option>
                                            <option value="event" <?= ($_POST['source'] ?? '') == 'event' ? 'selected' : '' ?>>Event</option>
                                            <option value="cold_call" <?= ($_POST['source'] ?? '') == 'cold_call' ? 'selected' : '' ?>>Cold Call</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a source</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">Auto Assign (Recommended)</option>
                                            <?php foreach ($sales_team as $member): ?>
                                                <option value="<?= $member['id'] ?>"
                                                    <?= ($_POST['assigned_to'] ?? '') == $member['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($member['username']) ?>
                                                    <small class="text-muted">(<?= ucfirst($member['role']) ?>)</small>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset Form
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Lead
                                </button>
                            </div>
                        </form>
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
    
    // Auto-focus first field
    document.getElementById('name')?.focus();
    
    // Phone number formatting
    document.getElementById('phone')?.addEventListener('input', function(e) {
        const x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
        e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>