<?php
session_start();
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Check if input is email or login_id
    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ? OR login_id = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $full_name, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($role === 'employee') {
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['role'] = $role;
                header("Location: employee_dashboard.php"); // Create this later
                exit;
            } elseif ($role === 'pending') {
                $message = "Your account is pending admin approval.";
            } else {
                 // Even if admin tries to login here, they might be allowed or denied. 
                 // User request says "employee is not able to login with his id in admin login". 
                 // Typically, separation is strict. 
                 if ($role === 'admin') {
                     $message = "Admins should use the Admin Portal.";
                 } else {
                     $message = "Access Denied.";
                 }
            }
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "No account found with that email.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
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
                <h2>Employee Portal</h2>
                <p>Sign in to access your dashboard and tasks.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="login_employee.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email or Login ID</label>
                    <div class="input-with-icon">
                        <i class="fa fa-envelope"></i>
                        <input type="text" id="email" name="email" placeholder="ID or Email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fa fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-actions">
                    <label class="checkbox-container">
                        <input type="checkbox">
                        <span class="checkmark"></span>
                        Keep me signed in
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Sign In <i class="fa fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer">
                <p>Wrong portal? <a href="Login.php">Switch Role</a></p>
                <p style="margin-top: 5px;">New Staff? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>

</body>
</html>
