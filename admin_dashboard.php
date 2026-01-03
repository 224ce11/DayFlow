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

// --- Handle Check-In / Check-Out (Simulated for UI Layout) ---
// Since the table doesn't exist yet, we will just simulate the variables for the UI.
$is_checked_in = false;
$check_in_time = null;

// The functionality below is commented out until the table is created.
/*
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_attendance'])) {
    // Database logic commmented out...
}

// Get Attendance Status logic commented out...
*/

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

    <!-- Enhanced Navbar (Proper Header) -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="#" class="logo">
                    <div class="logo-icon">
                        <i class="fa fa-layer-group"></i>
                    </div>
                    <span>Dayflow</span>
                </a>
            </div>
            
            <!-- Central Navigation (Header Format) -->
            <div class="nav-menu" style="display: flex; gap: 8px;">
                <a href="#" class="nav-link nav-module-link active" data-target="module-employees">
                    <i class="fa fa-users"></i> Employees
                </a>
                <a href="#" class="nav-link nav-module-link" data-target="module-attendance">
                    <i class="fa fa-calendar-check"></i> Attendance
                </a>
                <a href="#" class="nav-link nav-module-link" data-target="module-recruitment">
                    <i class="fa fa-briefcase"></i> Recruitment
                </a>
                <a href="#" class="nav-link nav-module-link" data-target="module-reports">
                    <i class="fa fa-chart-pie"></i> Reports
                </a>
            </div>

            <div class="nav-actions">
                <!-- Attendance Widget Removed for Admin -->
                <div class="attendance-widget" style="margin-right: 12px; display: none;"></div>
                
                <!-- Add Employee Button -->
                <a href="register.php" class="btn btn-primary btn-sm" style="margin-right: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-plus"></i> New Employee
                </a>

                <!-- User Profile -->
                <div class="user-profile" style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div style="font-size: 12px; color: var(--text-light);">Administrator</div>
                    </div>
                    <a href="Login.php" class="btn btn-secondary btn-sm" title="Logout">
                        <i class="fa fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- Sidebar Removed -->

        <!-- Main Content (Full Width) -->
        <main class="main-content" style="margin-left: 0; width: 100%;">
            
            <!-- Content Area -->
            <div class="dash-container" style="padding: 32px; max-width: 1400px; margin: 0 auto;">
                
                <!-- MODULE: EMPLOYEES -->
                <div id="module-employees" class="module-section active">
                    <!-- Inline Add Form Removed (User requested redirection to registration) -->
                    <?php if (!empty($message)): ?>
                        <div class="message-box <?php echo $messageType; ?>" style="margin-bottom: 24px;"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <div class="card" style="margin-top: 0;">
                        <?php
                        // Updated query to remove company_id check
                        $q = $conn->prepare("SELECT full_name, email, phone, login_id, role FROM users WHERE role = 'employee' ORDER BY created_at DESC");
                        $q->execute();
                        $res = $q->get_result();
                        ?>
                        <div class="employee-slider-wrapper">
                            <div class="employee-slider" id="employeeSlider">
                                <?php while($row = $res->fetch_assoc()): 
                                    // Generate consistent colors based on name
                                    $hash = md5($row['login_id']);
                                    $hue = hexdec(substr($hash, 0, 2));
                                    $gradient = "linear-gradient(135deg, hsl($hue, 60%, 55%), hsl($hue, 60%, 45%))";
                                    
                                    // Initials
                                    $names = explode(' ', $row['full_name']);
                                    $initials = '';
                                    if (count($names) >= 2) {
                                        $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names)-1], 0, 1));
                                    } else {
                                        $initials = strtoupper(substr($names[0], 0, 2));
                                    }
                                ?>
                                <div class="employee-card yt-style">
                                    <!-- Thumbnail Section -->
                                    <div class="yt-thumbnail" style="background: <?php echo $gradient; ?>;">
                                        <span class="thumb-content"><?php echo $initials; ?></span>
                                        <!-- Duration Badge Removed (Hidden Login ID) -->
                                    </div>
                                    
                                    <!-- Details Section -->
                                    <div class="yt-details">
                                        <div class="channel-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="yt-text-info">
                                            <h3 class="yt-title" title="<?php echo htmlspecialchars($row['full_name']); ?>">
                                                <?php echo htmlspecialchars($row['full_name']); ?>
                                            </h3>
                                            <div class="yt-channel-name">
                                                <?php echo ucfirst($row['role']); ?> &bull; Active
                                            </div>
                                            <div class="yt-meta">
                                                <span><?php echo htmlspecialchars($row['email']); ?></span>
                                                <span class="dot-separator">&bull;</span>
                                                <span><?php echo htmlspecialchars($row['phone']); ?></span>
                                            </div>
                                        </div>
                                        <div class="yt-menu-dots"><i class="fa fa-ellipsis-v"></i></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODULE: ATTENDANCE -->
                <div id="module-attendance" class="module-section">
                    
                    <!-- Manual Attendance / Correction -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3><i class="fa fa-edit"></i> Manual Attendance Update</h3>
                        <form method="POST" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; margin-top: 16px;">
                            <input type="hidden" name="manual_update" value="1">
                            
                            <div class="form-group">
                                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Employee</label>
                                <select name="user_id" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                                    <option value="">Select Employee</option>
                                    <?php
                                    $emp_q = $conn->query("SELECT id, full_name, login_id FROM users WHERE role='employee'");
                                    while($emp = $emp_q->fetch_assoc()) {
                                        echo "<option value='".$emp['id']."'>".$emp['full_name']." (".$emp['login_id'].")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Date</label>
                                <input type="date" name="date" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                            </div>
                            
                            <div class="form-group">
                                <label style="font-size: 12px; margin-bottom: 4px; display: block;">Status</label>
                                <select name="status" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Half-day">Half-day</option>
                                    <option value="Leave">Leave</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" style="height: 38px;">Update Status</button>
                        </form>
                        <?php
                        if (isset($_POST['manual_update'])) {
                            $u_id = $_POST['user_id'];
                            $u_date = $_POST['date'];
                            $u_status = $_POST['status'];
                            
                            // Check if exists
                            $check = $conn->prepare("SELECT id FROM attendance WHERE user_id=? AND date=?");
                            $check->bind_param("is", $u_id, $u_date);
                            $check->execute();
                            if ($check->get_result()->num_rows > 0) {
                                $upd = $conn->prepare("UPDATE attendance SET status=? WHERE user_id=? AND date=?");
                                $upd->bind_param("sis", $u_status, $u_id, $u_date);
                                $upd->execute();
                            } else {
                                $ins = $conn->prepare("INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?)");
                                $ins->bind_param("iss", $u_id, $u_date, $u_status);
                                $ins->execute();
                            }
                            echo "<p style='color: green; margin-top: 10px;'>Attendance updated successfully.</p>";
                        }
                        ?>
                    </div>

                    <!-- Attendance View -->
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>All Employees Attendance</h3>
                            <form method="GET" style="display: flex; gap: 8px;">
                                <input type="date" name="filter_date" value="<?php echo $_GET['filter_date'] ?? date('Y-m-d'); ?>" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                                <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                            </form>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc; text-align: left;">
                                        <th style="padding: 12px;">Employee</th>
                                        <th style="padding: 12px;">Date</th>
                                        <th style="padding: 12px;">Check In</th>
                                        <th style="padding: 12px;">Check Out</th>
                                        <th style="padding: 12px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $filter_date = $_GET['filter_date'] ?? date('Y-m-d');
                                    $att_sql = "SELECT a.*, u.full_name, u.login_id FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ? ORDER BY u.full_name ASC";
                                    
                                    // If looking at today, we might want to see who hasn't checked in too? 
                                    // Valid point, but let's stick to showing records first or left join.
                                    // To see ALL employees status for a day (even if absent/no record), we need RIGHT JOIN with users.
                                    // Let's do a LEFT JOIN from Users to Attendance to show Absentees.
                                    
                                    $att_sql = "SELECT u.full_name, u.login_id, a.check_in_time, a.check_out_time, a.status as att_status, l.status as leave_status 
                                                FROM users u 
                                                LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ? 
                                                LEFT JOIN leave_requests l ON u.id = l.user_id AND ? BETWEEN l.from_date AND l.to_date AND l.status = 'Approved'
                                                WHERE u.role = 'employee' 
                                                ORDER BY u.full_name ASC";
                                    
                                    $stmt = $conn->prepare($att_sql);
                                    $stmt->bind_param("ss", $filter_date, $filter_date);
                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    
                                    if ($res->num_rows > 0) {
                                        while ($row = $res->fetch_assoc()) {
                                            // Determine Status Priority
                                            $status = $row['att_status'];
                                            if (empty($status)) {
                                                if (!empty($row['leave_status'])) {
                                                    $status = 'Leave';
                                                } else {
                                                    $status = 'Absent';
                                                }
                                            }
                                            
                                            $statusColor = 'var(--text-light)';
                                            if ($status == 'Present') $statusColor = 'var(--success)';
                                            elseif ($status == 'Absent') $statusColor = 'var(--danger)';
                                            elseif ($status == 'Half-day') $statusColor = 'var(--warning)';
                                            elseif ($status == 'Leave') $statusColor = 'var(--info)';

                                            echo "<tr style='border-bottom: 1px solid #f1f5f9;'>";
                                            echo "<td style='padding: 12px;'><strong>" . htmlspecialchars($row['full_name']) . "</strong><br><span style='font-size:12px; color:#888;'>" . $row['login_id'] . "</span></td>";
                                            echo "<td style='padding: 12px;'>" . date("M j", strtotime($filter_date)) . "</td>";
                                            echo "<td style='padding: 12px;'>" . ($row['check_in_time'] ? date("h:i A", strtotime($row['check_in_time'])) : '-') . "</td>";
                                            echo "<td style='padding: 12px;'>" . ($row['check_out_time'] ? date("h:i A", strtotime($row['check_out_time'])) : '-') . "</td>";
                                            echo "<td style='padding: 12px;'><span style='color: white; background: {$statusColor}; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;'>" . $status . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' style='padding: 20px; text-align: center;'>No employees found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- MODULE: RECRUITMENT -->
                <div id="module-recruitment" class="module-section">
                    <div class="card">
                        <h3 style="margin-bottom: 20px;">Recruitment Pipeline</h3>
                        <p style="color: var(--text-light); text-align: center; padding: 40px;">No open positions or applications.</p>
                    </div>
                </div>

                <!-- MODULE: REPORTS -->
                <div id="module-reports" class="module-section">
                    <div class="card">
                        <h3 style="margin-bottom: 20px;">Analytics & Reports</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Total Employees</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                                    <?php 
                                    $c = $conn->query("SELECT count(*) as c FROM users WHERE role='employee'");
                                    echo $c->fetch_assoc()['c'];
                                    ?>
                                </div>
                            </div>
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Checked In Today</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--success);">
                                <?php
                                $today = date('Y-m-d');
                                $c_in = $conn->query("SELECT count(*) as c FROM attendance WHERE date='$today'");
                                echo $c_in->fetch_assoc()['c'];
                                ?>
                                </div>
                            </div>
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Pending Leaves</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--warning);">
                                <?php
                                $c_l = $conn->query("SELECT count(*) as c FROM leave_requests WHERE status='Pending'");
                                echo $c_l->fetch_assoc()['c'];
                                ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="style.js"></script>
    <script>
        // --- Module Switching Logic ---
        const navLinks = document.querySelectorAll('.nav-module-link');
        const sections = document.querySelectorAll('.module-section');

        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update Active Link (Navbar Styles)
                navLinks.forEach(l => {
                    l.classList.remove('active');
                    l.style.background = 'transparent';
                    l.style.color = 'var(--text-light)';
                });
                link.classList.add('active');
                link.style.background = 'var(--primary-50)'; // Inline override or handle in CSS
                link.style.color = 'var(--primary)';

                // Show Content Section
                const targetId = link.getAttribute('data-target');
                sections.forEach(s => s.classList.remove('active'));
                document.getElementById(targetId).classList.add('active');
            });
        });

        // Initialize first active link style
        document.querySelector('.nav-module-link.active').style.background = 'var(--primary-50)';
        document.querySelector('.nav-module-link.active').style.color = 'var(--primary)';


        // Admin JS Logic (Cleaned up)
        // No client side simulation for admin attendance needed

        function updateDisplay() {
            if (!isCheckedIn) return;
            
            let now = new Date().getTime();
            let diff = now - startTime;

            let hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((diff % (1000 * 60)) / 1000);

            timerDisplay.innerText = 
                (hours < 10 ? "0" + hours : hours) + ":" + 
                (minutes < 10 ? "0" + minutes : minutes) + ":" + 
                (seconds < 10 ? "0" + seconds : seconds);
        }

        // --- Employee Slider Logic ---
        const slider = document.getElementById('employeeSlider');
        const slideLeftBtn = document.getElementById('slideLeft');
        const slideRightBtn = document.getElementById('slideRight');

        if (slider && slideLeftBtn && slideRightBtn) {
            slideLeftBtn.addEventListener('click', () => {
                slider.scrollBy({ left: -320, behavior: 'smooth' }); // Scroll width approx card width + gap
            });

            slideRightBtn.addEventListener('click', () => {
                slider.scrollBy({ left: 320, behavior: 'smooth' });
            });
        }
    </script>
</body>
</html>
