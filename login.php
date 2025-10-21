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
        // Check if user is approved
        if ($user['status'] !== 'approved') {
            if ($user['status'] === 'pending') {
                $error = "Your account is pending admin approval. Please wait for approval before logging in.";
            } elseif ($user['status'] === 'rejected') {
                $error = "Your account has been rejected. Please contact an administrator.";
            } else {
                $error = "Your account is not active. Please contact an administrator.";
            }
        } else {
            // User is approved, proceed with login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_login'] = date('Y-m-d H:i:s');
            
            header("Location: " . ($user['role'] === 'admin' ? "dashboard.php" : "dashboard_me.php"));
            exit;
        }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern.css">
    <?php 
    require_once 'includes/theme-loader.php';
    loadThemeSystem();
    ?>
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            padding: var(--space-lg);
        }
        
        .auth-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            padding: var(--space-2xl);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.6s ease-out;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: var(--space-2xl);
        }
        
        .auth-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-lg);
            color: white;
            font-size: 1.5rem;
        }
        
        .auth-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: var(--space-xs);
        }
        
        .auth-subtitle {
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .error-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        
        .auth-links {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--medium-gray);
        }
        
        .auth-links a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .auth-links a:hover {
            color: var(--primary-blue-dark);
        }
        
        .divider {
            margin: 0 var(--space-sm);
            color: var(--text-light);
        }
        
        @media (max-width: 480px) {
            .auth-card {
                padding: var(--space-xl);
                margin: var(--space-md);
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <h1 class="auth-title">BizAutoPro</h1>
                <p class="auth-subtitle">Sign in to access your dashboard</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="bi bi-person"></i> Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock"></i> Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Enter your password"
                           required>
                </div>
                
                <button type="submit" class="btn-modern btn-primary btn-lg" style="width: 100%;">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In
                </button>
            </form>
            
            <div class="auth-links">
                <a href="register.php">Create an account</a>
                <span class="divider">•</span>
                <a href="password.php">Forgot password?</a>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn-modern btn-secondary">
                    <i class="bi bi-house"></i>
                    Return Home
                </a>
            </div>
        </div>
    </div>

    <script>
        // Focus on username field
        document.getElementById('username')?.focus();
        
        // Add form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Add loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<div class="spinner"></div> Signing in...';
        });
        
        // Add enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
    
    <?php addSimpleThemeToggle(); ?>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>