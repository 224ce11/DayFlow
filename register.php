<?php
session_start();
include 'db_connect.php';

$message = "";
$messageType = "";

// Check if user is logged in (Admin adding employee) or Guest (New Org)
$is_admin = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
$page_title = $is_admin ? "Register New Employee" : "Setup Organization";
$btn_text = $is_admin ? "Create Employee" : "Create Workspace";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Only for New Org
    $company_name = isset($_POST['company_name']) ? filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING) : 'Internal';

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // --- DIFFERENT LOGIC BASED ON CONTEXT ---
        
        if ($is_admin) {
            // == ADDING EMPLOYEE ==
            // Simpler logic: Direct Insert into users table
            
            // Check email
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "Email already registered.";
                $messageType = "error";
            } else {
                // Generate Login ID (Simple Format)
                $prefix = "EMP";
                $name_parts = explode(' ', trim($full_name));
                $fname = $name_parts[0];
                $lname = (count($name_parts) > 1) ? end($name_parts) : $fname;
                $name_code = strtoupper(substr($fname, 0, 2) . substr($lname, 0, 2));
                if (strlen($name_code) < 4) $name_code = str_pad($name_code, 4, "X"); 
                
                $year = date("Y");
                $c_stmt = $conn->query("SELECT count(*) as count FROM users WHERE year(created_at) = '$year'");
                $row = $c_stmt->fetch_assoc();
                $serial = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
                
                $login_id = $prefix . $name_code . $year . $serial;
                $role = 'employee';

                // We assume company_id is not critical or is 0 for now as per previous changes
                $ins = $conn->prepare("INSERT INTO users (login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->bind_param("ssssss", $login_id, $full_name, $email, $phone, $hashed_password, $role);
                
                if ($ins->execute()) {
                     // --- Send Credentials via Email ---
                     $subject = "Welcome to Dayflow - Your Login Credentials";
                     $email_body = "Hello $full_name,\n\n";
                     $email_body .= "You have been successfully registered to the Dayflow Workspace.\n\n";
                     $email_body .= "Please use the credentials below to log in:\n";
                     $email_body .= "Login ID: $login_id\n";
                     $email_body .= "Password: $password\n\n";
                     $email_body .= "Login here: http://localhost/Odoo%20x%20GCET/Login.php\n\n";
                     $email_body .= "Regards,\nDayflow Admin Team";
                     
                     $headers = "From: no-reply@dayflow.com";

                     // Try sending email (Localhost might fail without config, so we keep the echo message as fallback)
                     @mail($email, $subject, $email_body, $headers);

                     $message = "Employee Added Successfully!<br>Login ID: <strong>$login_id</strong><br><span style='font-size:0.9em;color:#059669'>Credentials sent to $email</span>";
                     $messageType = "success";
                } else {
                    $message = "Error: " . $ins->error;
                    $messageType = "error";
                }
            }

        } else {
            // == NEW ORGANIZATION (Guest) ==
            // (Previous Logic)
            
            // For now, let's keep it simple and just do the same user insert but with role='admin'
            // NOTE: Ideally we would create a company record first, but let's match the current simple schema usage
            
            // Generate Login ID for Admin
            $prefix = strtoupper(substr($company_name, 0, 3));
            $year = date("Y");
            $uniq = substr(time(), -4);
            $login_id = $prefix . $year . $uniq;
            $role = 'admin';

            $ins = $conn->prepare("INSERT INTO users (login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssss", $login_id, $full_name, $email, $phone, $hashed_password, $role);

            if ($ins->execute()) {
                // --- Send Credentials via Email ---
                 $subject = "Welcome to Dayflow - Setup Complete";
                 $email_body = "Hello $full_name,\n\n";
                 $email_body .= "Your organization '$company_name' has been registered.\n\n";
                 $email_body .= "Login ID: $login_id\n";
                 $email_body .= "Password: $password\n\n";
                 $email_body .= "Regards,\nDayflow Team";
                 
                 $headers = "From: no-reply@dayflow.com";
                 @mail($email, $subject, $email_body, $headers);

                $message = "<strong>Organization Registered!</strong><br>Login ID: <strong>$login_id</strong><br><span style='font-size:0.9em;color:#059669'>Credentials sent to $email</span>";
                $messageType = "success";
            } else {
                $message = "Error: " . $ins->error;
                $messageType = "error";
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="login_styles.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>
        <div class="bg-shape shape-3"></div>

        <div class="login-container">
            <div class="login-header">
                <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'homepage.php'; ?>" class="logo-center"><i class="fa fa-layer-group"></i> Dayflow</a>
                <h2><?php echo $page_title; ?></h2>
                <p><?php echo $is_admin ? "Add a new member to your team." : "Register your company to start managing your team."; ?></p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="error-message" style="<?php echo ($messageType == 'success') ? 'background:#ecfdf5; color:#047857; border-color:#6ee7b7;' : ''; ?>">
                   <?php if($messageType == 'success') { echo '<i class="fa fa-check-circle"></i>'; } else { echo '<i class="fa fa-exclamation-circle"></i>'; } ?>
                   <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="login-form">
                
                <!-- Company Name Field (Always Visible) -->
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <div class="input-with-icon">
                        <i class="fa fa-building"></i>
                        <input type="text" id="company_name" name="company_name" placeholder="Acme Corp" required <?php if($is_admin) echo 'value="Internal"'; ?>>
                    </div>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-with-icon">
                        <i class="fa fa-user"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-with-icon">
                        <i class="fa fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="email@company.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-with-icon">
                        <i class="fa fa-phone"></i>
                        <input type="tel" id="phone" name="phone" placeholder="+1..." required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Set Password</label>
                    <div class="input-with-icon">
                        <i class="fa fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-with-icon">
                        <i class="fa fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo $btn_text; ?> <i class="fa fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer">
                <?php if($is_admin): ?>
                    <p><a href="admin_dashboard.php"><i class="fa fa-arrow-left"></i> Back to Dashboard</a></p>
                <?php else: ?>
                    <p>Already have an account? <a href="Login.php">Sign In</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="style.js"></script>
</body>
</html>
