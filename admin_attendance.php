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

// Handle Manual Attendance Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manual_update'])) {
    $u_id = $_POST['user_id'];
    $u_date = $_POST['date'];
    $u_status = $_POST['status'];
    
    // Check if exists
    $check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
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
    $message = "Attendance updated successfully.";
    $messageType = "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Enhanced Navbar -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="admin_dashboard.php" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>

            <!-- Central Navigation -->
            <div class="nav-menu" style="display: flex; gap: 8px;">
                <a href="admin_dashboard.php" class="nav-link nav-module-link">
                    <i class="fa fa-users"></i> Dashboard
                </a>
                <a href="admin_attendance.php" class="nav-link nav-module-link active" style="background: var(--primary-50); color: var(--primary);">
                    <i class="fa fa-calendar-check"></i> Attendance
                </a>
                <a href="admin_payroll.php" class="nav-link">
                    <i class="fa fa-file-invoice-dollar"></i> Payroll
                </a>
            </div>

            <div class="nav-actions">
                <!-- User Profile -->
                <div class="user-profile" style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div style="font-size: 12px; color: var(--text-light);">Administrator</div>
                    </div>
                    <a href="Login.php" class="btn btn-secondary btn-sm" title="Logout"><i class="fa fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <main class="main-content" style="margin-left: 0; width: 100%;">
            <div class="dash-container" style="padding: 32px; max-width: 1400px; margin: 0 auto;">
                
                <?php if (!empty($message)): ?>
                    <div class="message-box <?php echo $messageType; ?>" style="margin-bottom: 24px;">
                        <?php echo $message; ?></div>
                <?php endif; ?>

                <h2>Attendance Management</h2>

                <!-- Manual Update Form -->
                <div class="card" style="margin-bottom: 24px; margin-top: 24px;">
                    <h3>Manual Attendance Update</h3>
                    <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-top: 15px;">
                        <input type="hidden" name="manual_update" value="1">
                        <div>
                            <label style="display: block; font-size: 13px; margin-bottom: 4px;">Employee</label>
                            <select name="user_id" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;">
                                <?php
                                $all_emps = $conn->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name");
                                while($e = $all_emps->fetch_assoc()) {
                                    echo "<option value='".$e['id']."'>".$e['full_name']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; margin-bottom: 4px;">Date</label>
                            <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; margin-bottom: 4px;">Status</label>
                            <select name="status" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                                <option value="Leave">Leave</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" style="margin-bottom: 2px;">Update</button>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>All Employees Attendance</h3>
                        <form method="GET" style="display: flex; gap: 8px;">
                            <select name="filter_user_id" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">All Employees</option>
                                <?php
                                $all_emps->data_seek(0);
                                $selected_user = $_GET['filter_user_id'] ?? '';
                                while($u = $all_emps->fetch_assoc()) {
                                    $sel = ($u['id'] == $selected_user) ? 'selected' : '';
                                    echo "<option value='".$u['id']."' $sel>".$u['full_name']."</option>";
                                }
                                ?>
                            </select>
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
                                $filter_user_id = $_GET['filter_user_id'] ?? '';
                                
                                $att_sql = "SELECT u.id as user_id_actual, u.full_name, u.login_id, a.check_in_time, a.check_out_time, a.status as att_status, l.status as leave_status 
                                            FROM users u 
                                            LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ? 
                                            LEFT JOIN leave_requests l ON u.id = l.user_id AND ? BETWEEN l.from_date AND l.to_date AND l.status = 'Approved'
                                            WHERE u.role = 'employee' ";
                                
                                $params = ["ss", $filter_date, $filter_date];
                                if (!empty($filter_user_id)) {
                                    $att_sql .= " AND u.id = ? ";
                                    $params[0] .= "i";
                                    $params[] = $filter_user_id;
                                }
                                $att_sql .= " ORDER BY u.full_name ASC";
                                
                                $stmt = $conn->prepare($att_sql);
                                $stmt->bind_param(...$params);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                
                                if ($res->num_rows > 0) {
                                    while ($row = $res->fetch_assoc()) {
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
                                        elseif ($status == 'Absent') $statusColor = 'var(--error)';
                                        elseif ($status == 'Half-day') $statusColor = 'var(--warning)';
                                        elseif ($status == 'Leave') $statusColor = 'var(--accent)';

                                        echo "<tr style='border-bottom: 1px solid #f1f5f9;'>";
                                        echo "<td style='padding: 12px;'><a href='profile.php?id=" . $row['user_id_actual'] . "' style='color: inherit; text-decoration: none;'><strong>" . htmlspecialchars($row['full_name']) . "</strong></a><br><span style='font-size:12px; color:#888;'>" . $row['login_id'] . "</span></td>";
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
        </main>
    </div>
    <script src="style.js"></script>
</body>
</html>
