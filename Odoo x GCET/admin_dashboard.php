<?php
session_start();
include 'db_connect.php';

// Auth Check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.php");
    exit;
}

// Get Admin's Company ID
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT company_id, company_name FROM users JOIN companies ON users.company_id = companies.id WHERE users.id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
$company_id = $admin_data['company_id'];
$company_name = $admin_data['company_name'];
$stmt->close();

$message = "";
$messageType = "";

// Handle Add Employee
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password']; // In a real app, generate random or force reset
    $role = 'employee';

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "Email $email is already registered.";
        $messageType = "error";
    } else {
        // --- Generate Login ID (Universal Logic) ---
        // 1. Company Prefix
        $co_parts = explode(' ', trim($company_name));
        $co_prefix = (count($co_parts) >= 2) 
            ? strtoupper(substr($co_parts[0], 0, 1) . substr($co_parts[1], 0, 1))
            : strtoupper(substr($company_name, 0, 2));
        if (strlen($co_prefix) < 2) $co_prefix = "XY"; 

        // 2. Name Code
        $name_parts = explode(' ', trim($full_name));
        $fname = $name_parts[0];
        $lname = (count($name_parts) > 1) ? end($name_parts) : $fname;
        $name_code = strtoupper(substr($fname, 0, 2) . substr($lname, 0, 2));
        if (strlen($name_code) < 4) $name_code = str_pad($name_code, 4, "X"); 

        // 3. Serial
        $year = date("Y");
        $count_sql = "SELECT count(*) as count FROM users WHERE year(created_at) = ?";
        $c_stmt = $conn->prepare($count_sql);
        $c_stmt->bind_param("s", $year);
        $c_stmt->execute();
        $row = $c_stmt->get_result()->fetch_assoc();
        $serial = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
        $c_stmt->close();

        $login_id = $co_prefix . $name_code . $year . $serial;
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert
        $ins = $conn->prepare("INSERT INTO users (company_id, login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("issssss", $company_id, $login_id, $full_name, $email, $phone, $hashed_password, $role);
        
        if ($ins->execute()) {
             // Send Email to Employee
             $subject = "Your Dayflow Account Credentials";
             $msg = "Hello $full_name,\n\nYour organization ($company_name) has created an account for you.\n\nLogin ID: $login_id\nPassword: $password\n\nLogin here: http://localhost/Odoo%20x%20GCET/Login.php";
             $headers = "From: no-reply@dayflow.com";
             @mail($email, $subject, $msg, $headers);

             $message = "Employee Added! Login ID: <strong>$login_id</strong>. (Password: $password)";
             $messageType = "success";
        } else {
            $message = "Error: " . $ins->error;
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #f8fafc; display: block; } /* Override flex center from login styles */
        .dash-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .message-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar" style="position: relative;">
        <div class="container nav-container">
            <a href="#" class="logo"><i class="fa fa-layer-group"></i> Dayflow <span style="font-size: 14px; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px; margin-left: 10px;">Admin</span></a>
            <div class="nav-actions">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <a href="Login.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dash-container">
        <div class="dash-header">
            <h1><?php echo htmlspecialchars($company_name); ?> Dashboard</h1>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;"><i class="fa fa-user-plus"></i> Add New Employee</h2>
            
            <?php if (!empty($message)): ?>
                <div class="message-box <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" action="admin_dashboard.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Jane Doe" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="+1..." required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="jane@company.com" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Set Password</label>
                        <input type="text" name="password" placeholder="e.g. employee123" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Employee Account</button>
            </form>
        </div>

        <div class="card" style="margin-top: 40px;">
            <h2>Team Members</h2>
            <?php
            $q = $conn->prepare("SELECT full_name, email, phone, login_id, role FROM users WHERE company_id = ? ORDER BY created_at DESC");
            $q->bind_param("i", $company_id);
            $q->execute();
            $res = $q->get_result();
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Login ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo $row['login_id']; ?></code></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td><span style="color: green;">Active</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
