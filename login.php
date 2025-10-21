<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "dashboard.php" : "dashboard_me.php"));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_login'] = date('Y-m-d H:i:s');
        
        header("Location: " . ($user['role'] === 'admin' ? "dashboard.php" : "dashboard_me.php"));
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .auth-container {
            max-width: 400px;
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
        .form-floating label {
            padding: 1rem 0.75rem;
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
                <h2>BizAutoPro</h2>
                <p class="text-muted">Please sign in to continue</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Username" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <label for="username"><i class="bi bi-person"></i> Username</label>
                    <div class="invalid-feedback">Please enter your username</div>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                    <div class="invalid-feedback">Please enter your password</div>
                </div>
                
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </div>
                
                <div class="text-center">
                    <a href="register.php" class="text-decoration-none">Create an account</a>
                    <span class="mx-2">•</span>
                    <a href="forgot_password.php" class="text-decoration-none">Forgot password?</a>
                </div>

                <div class="text-center mb-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> Return Home
                    </a>
                </div>    
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    document.getElementById('username')?.focus();
    </script>
</body>
</html>