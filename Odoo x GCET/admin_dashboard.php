<?php
session_start();
include 'db_connect.php';

// Auth Check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "";

// --- Handle Check-In / Check-Out ---
// This logic should ideally be in a separate AJAX handler, but kept here for simplicity as requested.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_attendance'])) {
    $today = date("Y-m-d");
    
    // Check if there is an active check-in (check_out is NULL)
    $check_stmt = $conn->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND date(check_in) = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
    $check_stmt->bind_param("is", $user_id, $today);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    
    if ($res->num_rows > 0) {
        // Active session found -> Check Out
        $row = $res->fetch_assoc();
        $att_id = $row['id'];
        $upd = $conn->prepare("UPDATE attendance SET check_out = NOW(), status = 'completed' WHERE id = ?");
        $upd->bind_param("i", $att_id);
        if ($upd->execute()) {
             // success check out
        }
    } else {
        // No active session -> Check In
        $ins = $conn->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, NOW())");
        $ins->bind_param("i", $user_id);
        if ($ins->execute()) {
             // success check in
        }
    }
    // Refresh to update UI
    header("Location: admin_dashboard.php");
    exit;
}

// --- Get Current Attendance Status ---
$is_checked_in = false;
$check_in_time = null;
$today = date("Y-m-d");
$status_q = $conn->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND date(check_in) = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
$status_q->bind_param("is", $user_id, $today);
$status_q->execute();
$status_res = $status_q->get_result();
if ($status_res->num_rows > 0) {
    $is_checked_in = true;
    $row = $status_res->fetch_assoc();
    $check_in_time = strtotime($row['check_in']);
}

// Handle Add Employee (Existing Logic)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    // ... (Keep existing logic but wrap it to ensure it triggers only on correct form) ...
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password']; 
    $role = 'employee';

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "Email already registered.";
        $messageType = "error";
    } else {
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
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $ins = $conn->prepare("INSERT INTO users (login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssssss", $login_id, $full_name, $email, $phone, $hashed_password, $role);
        
        if ($ins->execute()) {
             $message = "Employee Added! ID: <strong>$login_id</strong>";
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
    <title>Dashboard - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="#" class="sidebar-brand">
                    <i class="fa fa-layer-group"></i> Dayflow
                </a>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item"><a href="#" class="menu-link active"><i class="fa fa-users menu-icon"></i> Employees</a></li>
                <li class="menu-item"><a href="#" class="menu-link"><i class="fa fa-calendar-check menu-icon"></i> Attendance</a></li>
                <li class="menu-item"><a href="#" class="menu-link"><i class="fa fa-chart-pie menu-icon"></i> Reports</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h2>Employees</h2>
                </div>
                <div class="header-right">
                    <div class="attendance-widget">
                        <span id="timeCounter" class="time-counter">00:00:00</span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="toggle_attendance" value="1">
                            <button type="submit" class="check-in-btn <?php echo $is_checked_in ? 'checked-in' : ''; ?>" title="<?php echo $is_checked_in ? 'Check Out' : 'Check In'; ?>">
                                <i class="fa fa-power-off"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="user-profile">
                        <span style="font-weight: 500; margin-right: 12px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="Login.php" class="btn btn-sm btn-outline"><i class="fa fa-sign-out-alt"></i></a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="dash-container" style="padding: 32px;">
                
                <!-- Add Employee Form -->
                <div class="card">
                    <h3 style="margin-bottom: 20px; font-size: 18px;">Add New Employee</h3>
                    <?php if (!empty($message)): ?>
                        <div class="message-box <?php echo $messageType; ?>"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="add_employee" value="1">
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
                        <button type="submit" class="btn btn-primary">Create Employee</button>
                    </form>
                </div>

                <!-- Employee Table -->
                <div class="card" style="margin-top: 24px;">
                    <h3 style="margin-bottom: 20px; font-size: 18px;">Employee Data</h3>
                    <?php
                    $q = $conn->prepare("SELECT full_name, email, phone, login_id, role FROM users WHERE role = 'employee' ORDER BY created_at DESC");
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
        </main>
    </div>

    <script src="style.js"></script>
    <script>
        // Timer Logic
        let updateTimer = function() {
            <?php if($is_checked_in && $check_in_time): ?>
                let checkInTime = <?php echo $check_in_time * 1000; ?>; // PHP to JS timestamp
                let now = new Date().getTime();
                let diff = now - checkInTime;
                
                let hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((diff % (1000 * 60)) / 1000);

                document.getElementById('timeCounter').innerText = 
                    (hours < 10 ? "0" + hours : hours) + ":" + 
                    (minutes < 10 ? "0" + minutes : minutes) + ":" + 
                    (seconds < 10 ? "0" + seconds : seconds);
            <?php else: ?>
                document.getElementById('timeCounter').innerText = "00:00:00";
            <?php endif; ?>
        };

        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>
