<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayflow - Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="#" class="logo">
                <i class="fa fa-layer-group"></i> Dayflow
            </a>
            <div class="nav-actions">
                <a href="Login.php" class="btn btn-secondary">Log In</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Simplified Hero Section -->
    <section class="hero full-height">
        <div class="container hero-content centered">
            <div class="hero-text text-center">
                <span class="badge">Internal Employee Portal</span>
                <h1>Welcome to<br>Dayflow Workspace.</h1>
                <p>Secure access for authorized personnel only. Please log in to access your dashboard and tools.</p>
                
                <div class="cta-group">
                    <a href="Login.php" class="btn btn-primary btn-lg">
                        <i class="fa fa-lock"></i> Secure Login
                    </a>
                </div>

                <div class="security-note">
                    <i class="fa fa-shield-alt"></i> Authorized Use Only • monitored System
                </div>
            </div>
        </div>
    </section>

    <!-- Minimal Footer -->
    <footer class="footer-minimal">
        <div class="container">
            <p>&copy; 2026 Dayflow Inc. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
