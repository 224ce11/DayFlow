<?php
session_start();
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    // Check if input is email or login_id
    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ? OR login_id = ?");
    $stmt->bind_param("ss", $email, $email); // Use $email variable for both placeholders
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $full_name, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($role === 'admin') {
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['role'] = $role;
                header("Location: admin_dashboard.php"); // Create this later or redirect to home for now
                exit;
            } else {
                $message = "Access Denied. You do not have Admin privileges.";
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
    <title>Admin Login - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="login_styles.css">
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
                <h2>Admin Portal</h2>
                <p>Sign in to manage the system and resources.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="login_admin.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email or Login ID</label>
                    <div class="input-with-icon">
                        <i class="fa fa-user-shield"></i>
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
                        Remember device
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Admin Sign In <i class="fa fa-arrow-right"></i>
                </button>
                
                <div class="divider">OR</div>
                
                <a href="#" class="btn btn-google btn-block" onclick="alert('Google Login requires Google Cloud Console setup. Please configure CLIENT_ID and enable Google Identity API.'); return false;">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google" class="google-icon-svg">
                    Sign in with Google
                </a>
            </form>

            <div class="login-footer">
                <p>Not an admin? <a href="Login.php">Switch Role</a></p>
                <p style="margin-top: 5px;"><a href="#">Contact Support</a> for access</p>
            </div>
        </div>
    </div>
    <script src="style.js"></script>
</body>
</html>
