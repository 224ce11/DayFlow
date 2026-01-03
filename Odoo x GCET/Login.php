<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="login-wrapper">
        <!-- Background Elements -->
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>

        <div class="login-container">
            <div class="login-header">
                <a href="homepage.php" class="logo-center">
                    <i class="fa fa-layer-group"></i> Dayflow
                </a>
                <h2>Welcome</h2>
                <p>Please select your role to proceed.</p>
            </div>

            <div class="role-selection">
                <a href="login_admin.php" class="role-card">
                    <div class="role-icon admin-icon">
                        <i class="fa fa-user-shield"></i>
                    </div>
                    <div class="role-text">
                        <h3>Admin</h3>
                        <span>System Management</span>
                    </div>
                    <i class="fa fa-chevron-right arrow-icon"></i>
                </a>

                <a href="login_employee.php" class="role-card">
                    <div class="role-icon employee-icon">
                        <i class="fa fa-user"></i>
                    </div>
                    <div class="role-text">
                        <h3>Employee</h3>
                        <span>Staff Dashboard</span>
                    </div>
                    <i class="fa fa-chevron-right arrow-icon"></i>
                </a>
            </div>

            <div class="login-footer">
                <p>Having trouble? <a href="#">Contact Support</a></p>
            </div>
        </div>
    </div>

</body>
</html>
