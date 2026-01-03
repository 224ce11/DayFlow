<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayflow - Secure Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Enhanced Navbar with status indicator -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo-wrapper">
                <a href="#" class="logo">
                    <div class="logo-icon">
                        <i class="fa fa-layer-group"></i>
                    </div>
                    <span>Dayflow</span>
                </a>
                <div class="system-status">
                    <div class="status-indicator active"></div>
                    <span class="status-text">System Active</span>
                </div>
            </div>
            <div class="nav-actions">
                <a href="#" class="nav-link">
                    <i class="fa fa-question-circle"></i> Support
                </a>
                <a href="Login.php" class="btn btn-secondary">
                    <i class="fa fa-sign-in-alt"></i> Log In
                </a>
                <a href="register.php" class="btn btn-primary">
                    <i class="fa fa-rocket"></i> Get Started
                </a>
            </div>
        </div>
    </nav>

    <!-- Enhanced Hero Section -->
    <section class="hero full-height">
        <div class="container hero-content centered">
            <div class="hero-text text-center">
                <span class="badge">
                    <i class="fa fa-shield-alt"></i> Internal Employee Portal
                </span>
                <h1>
                    <span class="gradient-text">Dayflow Workspace</span>
                    <br>Secure Management System
                </h1>
                <p class="lead">Streamlined access to enterprise tools and resources. Secure portal for authorized personnel with role-based permissions and encrypted communications.</p>
                
                <div class="cta-group">
                    <a href="Login.php" class="btn btn-primary btn-lg">
                        <i class="fa fa-lock"></i> Secure Login
                    </a>
                    <a href="#" class="btn btn-outline btn-lg">
                        <i class="fa fa-play-circle"></i> Watch Demo
                    </a>
                </div>

                <!-- Security Features Showcase -->
                <div class="security-features">
                    <div class="feature">
                        <i class="fa fa-user-shield"></i>
                        <span>Role-Based Access</span>
                    </div>
                    <div class="feature">
                        <i class="fa fa-encryption"></i>
                        <span>End-to-End Encrypted</span>
                    </div>
                    <div class="feature">
                        <i class="fa fa-clock"></i>
                        <span>24/7 Monitoring</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hero Background Elements -->
        <div class="hero-bg-elements">
            <div class="bg-shape shape-1"></div>
            <div class="bg-shape shape-2"></div>
            <div class="bg-shape shape-3"></div>
        </div>
    </section>

    <!-- Quick Access Preview Section -->
    <section class="preview-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Unified Workspace Features</h2>
                <p>Access all your tools from a single secure dashboard</p>
            </div>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <h3>Analytics Dashboard</h3>
                    <p>Real-time metrics and performance insights</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa fa-users"></i>
                    </div>
                    <h3>Team Management</h3>
                    <p>Collaborate and manage your team efficiently</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa fa-file-contract"></i>
                    </div>
                    <h3>Document Center</h3>
                    <p>Secure document storage and sharing</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa fa-calendar-alt"></i>
                    </div>
                    <h3>Schedule Planning</h3>
                    <p>Manage projects and timelines seamlessly</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Minimal Footer -->
    <footer class="footer-minimal">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <a href="#" class="logo">
                        <i class="fa fa-layer-group"></i> Dayflow
                    </a>
                    <p class="footer-tagline">Enterprise Management System</p>
                </div>
                
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Compliance</a>
                    <a href="#">System Status</a>
                </div>
                
                <div class="footer-security">
                    <i class="fa fa-lock"></i>
                    <span>SSL Secured • GDPR Compliant • ISO 27001 Certified</span>
                </div>
            </div>
            <p class="copyright">&copy; 2026 Dayflow Inc. All rights reserved. v4.2.1</p>
        </div>
    </footer>

</body>
</html>
