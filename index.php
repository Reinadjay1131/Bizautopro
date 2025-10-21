<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to BizAutoPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 5rem 0;
            border-radius: 0 0 20px 20px;
        }
        .feature-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .btn-cta {
            padding: 0.8rem 2rem;
            font-weight: 600;
            border-radius: 50px;
        }
        .auth-box {
            max-width: 400px;
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">
                        <i class="bi bi-shield-lock"></i> BizAutoPro
                    </h1>
                    <p class="lead mb-5">Streamline your business operations with our comprehensive management system</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="register.php" class="btn btn-light btn-cta">
                            <i class="bi bi-person-plus"></i> Create Account
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-cta">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose BizAutoPro?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-speedometer2 fs-1 text-primary"></i>
                            </div>
                            <h4>Efficient Workflows</h4>
                            <p>Automate and optimize your business processes with our intuitive workflow system.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-graph-up fs-1 text-primary"></i>
                            </div>
                            <h4>Real-time Analytics</h4>
                            <p>Make data-driven decisions with comprehensive dashboards and reports.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                                <i class="bi bi-shield-check fs-1 text-primary"></i>
                            </div>
                            <h4>Enterprise Security</h4>
                            <p>Military-grade encryption and security protocols to protect your data.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Transform Your Business?</h2>
            <a href="register.php" class="btn btn-primary btn-lg px-5 py-3">
                <i class="bi bi-rocket"></i> Get Started Now
            </a>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>