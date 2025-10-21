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
    <title>BizAutoPro - Business Automation Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 6rem 0 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="25" cy="25" r="2" fill="white" opacity="0.1"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="50" r="1.5" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="2" fill="white" opacity="0.1"/></svg>');
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: var(--space-lg);
            line-height: 1.1;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: var(--space-2xl);
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: var(--space-lg);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-hero {
            padding: var(--space-md) var(--space-2xl);
            font-size: 1.125rem;
            font-weight: 600;
            border-radius: 50px;
            min-width: 180px;
        }
        
        .btn-hero-primary {
            background: white;
            color: var(--primary-blue);
            border: 2px solid white;
        }
        
        .btn-hero-primary:hover {
            background: var(--off-white);
            color: var(--primary-blue-dark);
            transform: translateY(-2px);
        }
        
        .btn-hero-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-hero-secondary:hover {
            background: white;
            color: var(--primary-blue);
            transform: translateY(-2px);
        }
        
        .features-section {
            padding: 5rem 0;
            background: var(--off-white);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: var(--space-md);
        }
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--space-2xl);
            text-align: center;
            border: 1px solid var(--medium-gray);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--accent-blue);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-lg);
            color: white;
            font-size: 2rem;
        }
        
        .feature-title {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: var(--space-md);
        }
        
        .feature-description {
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .cta-section {
            background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--primary-blue) 100%);
            padding: 4rem 0;
            text-align: center;
            color: white;
        }
        
        .cta-title {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: var(--space-lg);
        }
        
        .cta-subtitle {
            font-size: 1.125rem;
            margin-bottom: var(--space-2xl);
            opacity: 0.9;
        }
        
        .footer-section {
            background: var(--text-dark);
            color: white;
            padding: var(--space-2xl) 0;
            text-align: center;
        }
        
        .animation-delay-1 { animation-delay: 0.1s; }
        .animation-delay-2 { animation-delay: 0.2s; }
        .animation-delay-3 { animation-delay: 0.3s; }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.125rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-hero {
                width: 100%;
                max-width: 280px;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="fade-in">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="modern-container">
            <div class="hero-content">
                <h1 class="hero-title slide-up">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    BizAutoPro
                </h1>
                <p class="hero-subtitle slide-up animation-delay-1">
                    Transform your business operations with our comprehensive automation platform. 
                    Streamline workflows, manage inventory, track leads, and boost productivity.
                </p>
                <div class="hero-buttons slide-up animation-delay-2">
                    <a href="register.php" class="btn-modern btn-hero btn-hero-primary">
                        <i class="bi bi-person-plus"></i>
                        Get Started Free
                    </a>
                    <a href="login.php" class="btn-modern btn-hero btn-hero-secondary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="modern-container">
            <div class="section-header">
                <h2 class="section-title">Powerful Features for Modern Business</h2>
                <p class="section-subtitle">
                    Everything you need to automate, optimize, and scale your business operations
                </p>
            </div>
            
            <div class="grid grid-cols-3">
                <div class="feature-card slide-up animation-delay-1">
                    <div class="feature-icon">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <h3 class="feature-title">Smart Workflows</h3>
                    <p class="feature-description">
                        Automate repetitive tasks and create intelligent workflows that adapt to your business needs. 
                        Reduce manual work and eliminate errors.
                    </p>
                </div>
                
                <div class="feature-card slide-up animation-delay-2">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3 class="feature-title">Real-time Analytics</h3>
                    <p class="feature-description">
                        Get instant insights with comprehensive dashboards and reports. 
                        Make data-driven decisions with real-time performance metrics.
                    </p>
                </div>
                
                <div class="feature-card slide-up animation-delay-3">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="feature-title">Enterprise Security</h3>
                    <p class="feature-description">
                        Bank-level security with advanced encryption, user authentication, 
                        and comprehensive audit trails to protect your business data.
                    </p>
                </div>
                
                <div class="feature-card slide-up animation-delay-1">
                    <div class="feature-icon">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <h3 class="feature-title">Inventory Management</h3>
                    <p class="feature-description">
                        Track stock levels, manage suppliers, and automate reordering. 
                        Real-time inventory tracking with barcode generation.
                    </p>
                </div>
                
                <div class="feature-card slide-up animation-delay-2">
                    <div class="feature-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="feature-title">Lead Management</h3>
                    <p class="feature-description">
                        Capture, track, and nurture leads through your sales pipeline. 
                        Convert more prospects into customers with organized lead tracking.
                    </p>
                </div>
                
                <div class="feature-card slide-up animation-delay-3">
                    <div class="feature-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h3 class="feature-title">Point of Sale</h3>
                    <p class="feature-description">
                        Integrated POS system with inventory deduction, sales tracking, 
                        and real-time financial reporting for seamless operations.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="modern-container">
            <h2 class="cta-title">Ready to Transform Your Business?</h2>
            <p class="cta-subtitle">
                Join thousands of businesses already using BizAutoPro to streamline their operations
            </p>
            <a href="register.php" class="btn-modern btn-hero btn-hero-primary">
                <i class="bi bi-rocket-takeoff"></i>
                Start Your Free Trial
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section">
        <div class="modern-container">
            <p style="margin: 0; opacity: 0.8;">
                <a href="#" style="color: var(--accent-blue);">Privacy Policy</a> | 
                <a href="#" style="color: var(--accent-blue);">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
        // Add scroll animation triggers
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.slide-up').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
    
    <!-- Copyright Footer -->
    <footer class="text-center py-3 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <small class="text-muted">Created by NOYB FUNDAMENTAL 2025 ©</small>
    </footer>
</body>
</html>