<?php
header("Location: Login.php");
exit;
?>

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_type = $_POST['reg_type']; // 'new_company' or 'join_company'
    
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $company_id = null;
        $company_name = "";
        $is_valid = true;

        if ($reg_type == 'new_company') {
            $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING);
            // Generate Random Company Code (e.g., CMP-8392)
            $company_code = 'CMP-' . strtoupper(substr(md5(time()), 0, 4));
            
            // Check if company name exists
            $check_c = $conn->prepare("SELECT id FROM companies WHERE company_name = ?");
            $check_c->bind_param("s", $company_name);
            $check_c->execute();
            if ($check_c->get_result()->num_rows > 0) {
                $message = "Company Name already registered.";
                $messageType = "error";
                $is_valid = false;
            } else {
                // Create Company
                $ins_c = $conn->prepare("INSERT INTO companies (company_name, company_code) VALUES (?, ?)");
                $ins_c->bind_param("ss", $company_name, $company_code);
                if ($ins_c->execute()) {
                    $company_id = $conn->insert_id;
                    $role = 'admin'; // Creator is admin
                } else {
                    $message = "Error creating company.";
                    $messageType = "error";
                    $is_valid = false;
                }
                $ins_c->close();
            }
            $check_c->close();

        } else { // join_company
            $company_code_input = trim($_POST['company_code']);
            $get_c = $conn->prepare("SELECT id, company_name FROM companies WHERE company_code = ?");
            $get_c->bind_param("s", $company_code_input);
            $get_c->execute();
            $res_c = $get_c->get_result();
            
            if ($row_c = $res_c->fetch_assoc()) {
                $company_id = $row_c['id'];
                $company_name = $row_c['company_name'];
                $role = 'pending'; // Employees are pending
            } else {
                $message = "Invalid Company Code. Ask your admin for the code.";
                $messageType = "error";
                $is_valid = false;
            }
            $get_c->close();
        }

        if ($is_valid && $company_id) {
            // --- Generate Login ID ---
            // 1. Company Prefix
            $co_parts = explode(' ', trim($company_name));
            if (count($co_parts) >= 2) {
                $co_prefix = strtoupper(substr($co_parts[0], 0, 1) . substr($co_parts[1], 0, 1));
            } else {
                $co_prefix = strtoupper(substr($company_name, 0, 2));
            }
            if (strlen($co_prefix) < 2) $co_prefix = "XY"; 

            // 2. Name Code
            $name_parts = explode(' ', trim($full_name));
            $fname = $name_parts[0];
            $lname = (count($name_parts) > 1) ? end($name_parts) : $fname;
            $name_code = strtoupper(substr($fname, 0, 2) . substr($lname, 0, 2));
            if (strlen($name_code) < 4) $name_code = str_pad($name_code, 4, "X"); 

            // 3. Year & Serial
            $year = date("Y");
            $count_sql = "SELECT count(*) as count FROM users WHERE year(created_at) = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param("s", $year);
            $count_stmt->execute();
            $row = $count_stmt->get_result()->fetch_assoc();
            $serial = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
            $count_stmt->close();

            $login_id = $co_prefix . $name_code . $year . $serial;

            // Insert User
            $stmt = $conn->prepare("INSERT INTO users (company_id, login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $company_id, $login_id, $full_name, $email, $phone, $hashed_password, $role);

            if ($stmt->execute()) {
                $extra_msg = ($role == 'admin') 
                    ? "Your Company Code is: <strong>$company_code</strong>. Share this with employees to join." 
                    : "Your request is sent to the company Admin for approval.";
                
                $message = "Success! ID: <strong>$login_id</strong>. $extra_msg";
                $messageType = "success";
            } else {
                $message = "Error registering user: " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
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
    <title>Register - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script>
        function toggleType(type) {
            document.getElementById('new-company-fields').style.display = type === 'new_company' ? 'block' : 'none';
            document.getElementById('join-company-fields').style.display = type === 'join_company' ? 'block' : 'none';
            document.getElementById('reg_type').value = type;
            
            // Update button styles
            document.getElementById('btn-new').classList.toggle('active', type === 'new_company');
            document.getElementById('btn-join').classList.toggle('active', type === 'join_company');
        }
    </script>
    <style>
        .toggle-container {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .toggle-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        .toggle-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>

        <div class="login-container">
            <div class="login-header">
                <a href="homepage.php" class="logo-center"><i class="fa fa-layer-group"></i> Dayflow</a>
                <h2>Create Account</h2>
                <p>Register as a new organization or join an existing one.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message-box <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="toggle-container">
                <div id="btn-new" class="toggle-btn active" onclick="toggleType('new_company')">New Company</div>
                <div id="btn-join" class="toggle-btn" onclick="toggleType('join_company')">Join Team</div>
            </div>

            <form action="register.php" method="POST" class="login-form">
                <input type="hidden" name="reg_type" id="reg_type" value="new_company">

                <!-- New Company Fields -->
                <div id="new-company-fields">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <div class="input-with-icon">
                            <i class="fa fa-building"></i>
                            <input type="text" id="company_name" name="company_name" placeholder="Acme Corp">
                        </div>
                    </div>
                </div>

                <!-- Join Company Fields -->
                <div id="join-company-fields" style="display: none;">
                    <div class="form-group">
                        <label for="company_code">Company Code (Ask Admin)</label>
                        <div class="input-with-icon">
                            <i class="fa fa-key"></i>
                            <input type="text" id="company_code" name="company_code" placeholder="e.g. CMP-1234">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="full_name">Admin Full Name</label>
                    <div class="input-with-icon">
                        <i class="fa fa-user"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fa fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="name@dayflow.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-with-icon">
                        <i class="fa fa-phone"></i>
                        <input type="tel" id="phone" name="phone" placeholder="+1 (555) 000-0000" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
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
                    Complete Registration <i class="fa fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer">
                <p>Already have an account? <a href="Login.php">Sign In</a></p>
            </div>
        </div>
    </div>
</body>
</html>
