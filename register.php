<?php
session_start();
require 'config.php';
require_once 'email/EmailService.php';
require_once 'email/FileEmailService.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validation
        if (empty($username)) {
            throw new Exception("Username is required");
        }
        if (empty($email)) {
            throw new Exception("Email is required");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        if ($password !== $password_confirm) {
            throw new Exception("Passwords do not match");
        }
        if (empty($role)) {
            throw new Exception("Role selection is required");
        }
        
        // Validate role - exclude admin role
        $allowed_roles = ['employee', 'manager', 'inventory_manager'];
        if (!in_array($role, $allowed_roles)) {
            throw new Exception("Invalid role selected");
        }
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            throw new Exception("Username or email already exists");
        }
        
        // Use selected role (admin role is excluded from form options)
        $status = 'pending'; // Requires admin approval
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $email, $role, $status]);
        
        // Send email notifications (using file-based service for local testing)
        try {
            $emailService = new FileEmailService(); // Using file-based service for local testing
            // Notify admins about new registration
            $emailService->notifyAdminNewRegistration($username, $email, $role);
        } catch (Exception $emailError) {
            // Log email error but don't fail registration
            error_log("Email notification failed: " . $emailError->getMessage());
        }
        
        $_SESSION['success'] = "Registration submitted successfully! Please wait for admin approval.";
        header("Location: login.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <?php 
    require_once 'includes/theme-loader.php';
    loadThemeSystem();
    ?>
    <style>
        .auth-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-logo {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="brand-header">
                <div class="brand-logo">
                    <i class="bi bi-shop"></i>
                </div>
                <h2>Staff Registration</h2>
                <p class="text-muted">All registrations require admin approval</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Username" required
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            <label for="username"><i class="bi bi-person"></i> Username</label>
                            <div class="invalid-feedback">Please choose a username</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <label for="email"><i class="bi bi-envelope"></i> Email</label>
                            <div class="invalid-feedback">Please provide a valid email</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-floating mb-3">
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Choose your role...</option>
                        <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="inventory_manager" <?= ($_POST['role'] ?? '') === 'inventory_manager' ? 'selected' : '' ?>>Inventory Manager</option>
                    </select>
                    <label for="role"><i class="bi bi-person-badge"></i> Role</label>
                    <div class="invalid-feedback">Please select a role</div>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required minlength="8">
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                    <div class="invalid-feedback">Password must be at least 8 characters</div>
                    <div class="password-strength mt-2">
                        <div class="password-strength-bar"></div>
                    </div>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                           placeholder="Confirm Password" required>
                    <label for="password_confirm"><i class="bi bi-lock-fill"></i> Confirm Password</label>
                    <div class="invalid-feedback">Passwords must match</div>
                </div>
                
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-person-plus"></i> Submit for Approval
                    </button>
                </div>
                
                <div class="text-center">
                    <p>Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a></p>
                </div>

    <div class="text-center mb-3">
        <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-house"></i> Return Home</a>
    </div>            
            </form>
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
                // Check password match
                const password = document.getElementById('password');
                const passwordConfirm = document.getElementById('password_confirm');
                if (password.value !== passwordConfirm.value) {
                    passwordConfirm.setCustomValidity("Passwords must match");
                } else {
                    passwordConfirm.setCustomValidity("");
                }
                
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
        
        // Password strength indicator
        document.getElementById('password')?.addEventListener('input', function(e) {
            const strengthBar = document.querySelector('.password-strength-bar');
            const strength = calculatePasswordStrength(e.target.value);
            strengthBar.style.width = strength + '%';
            strengthBar.style.backgroundColor = 
                strength < 30 ? '#dc3545' : 
                strength < 70 ? '#ffc107' : '#28a745';
        });
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length > 7) strength += 30;
            if (password.match(/[A-Z]/)) strength += 20;
            if (password.match(/[0-9]/)) strength += 20;
            if (password.match(/[^A-Za-z0-9]/)) strength += 30;
            return Math.min(strength, 100);
        }
    })()
    </script>
    
    <?php addSimpleThemeToggle(); ?>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>