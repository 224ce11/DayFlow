<?php
session_start();
include 'db_connect.php';

// Auth Check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.php");
    exit;
}

$message = "";
$messageType = "";

// Handle Manual Attendance Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manual_update'])) {
    $u_id = $_POST['user_id'];
    $u_date = $_POST['date'];
    $u_status = $_POST['status'];

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

// User Selection for Calendar
$filter_user_id = $_GET['filter_user_id'] ?? '';
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$month_start = date('Y-m-01', strtotime($filter_date));
$month_end = date('Y-m-t', strtotime($filter_date));

// Fetch Calendar Data if User Selected
$cal_data = [];
if ($filter_user_id) {
    // 1. Get Attendance
    $stmt = $conn->prepare("SELECT date, status FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $filter_user_id, $month_start, $month_end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cal_data[$row['date']] = $row['status']; // Present, Absent, Half-day
    }

    // 2. Get Leaves (Approved) to overwrite/fill gaps
    $stmt = $conn->prepare("SELECT from_date, to_date FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ((from_date BETWEEN ? AND ?) OR (to_date BETWEEN ? AND ?))");
    $stmt->bind_param("isssss", $filter_user_id, $month_start, $month_end, $month_start, $month_end);
    $stmt->execute();
    $l_res = $stmt->get_result();
    while ($l = $l_res->fetch_assoc()) {
        $curr = strtotime($l['from_date']);
        $end = strtotime($l['to_date']);
        while ($curr <= $end) {
            $d = date('Y-m-d', $curr);
            if ($d >= $month_start && $d <= $month_end) {
                // Priority: If attendance exists (e.g. they came on a leave day?), keep attendance? 
                // Usually Leave overrides 'Absent' but not 'Present'.
                if (!isset($cal_data[$d]) || $cal_data[$d] == 'Absent') {
                    $cal_data[$d] = 'Leave';
                }
            }
            $curr = strtotime('+1 day', $curr);
        }
    }
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
    <link rel="stylesheet" href="admin_attendance.css">
    <style>
        .dash-container { padding: 32px; width: 100%; margin: 0; max-width: none; }
        
        .layout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        
        @media (min-width: 1400px) {
            .layout-grid {
                grid-template-columns: 400px 1fr;
                align-items: start;
            }
        }
        
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-light); }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="admin_dashboard.php" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>

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
                <div class="user-profile" style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div style="font-size: 12px; color: var(--text-light);">Administrator</div>
                    </div>
                    <a href="logout.php" class="btn btn-secondary btn-sm" title="Logout"><i class="fa fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <main class="main-content" style="margin-left: 0; width: 100%; max-width: none;">
            <div class="dash-container">

                <?php if (!empty($message)): ?>
                        <div class="message-box <?php echo $messageType; ?>" style="margin-bottom: 24px;">
                            <?php echo $message; ?>
                        </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                    <div>
                        <h2 style="margin-bottom: 8px;">Attendance Management</h2>
                        <p style="color: var(--text-light);">Monitor and manage employee attendance records.</p>
                    </div>
                    
                    <!-- Global Filter Form -->
                    <form method="GET" style="display: flex; gap: 12px; background: white; padding: 8px; border-radius: 12px; border: 1px solid var(--card-border); box-shadow: var(--shadow-sm);">
                        <select name="filter_user_id" style="padding: 10px; border: 1px solid var(--surface-200); border-radius: 8px; min-width: 200px; outline: none; font-family: inherit;" onchange="this.form.submit()">
                            <option value="">-- All Employees --</option>
                            <?php
                            $all_emps = $conn->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name");
                            if ($all_emps->num_rows > 0) {
                                while ($e = $all_emps->fetch_assoc()) {
                                    $sel = ($e['id'] == $filter_user_id) ? 'selected' : '';
                                    echo "<option value='" . $e['id'] . "' $sel>" . $e['full_name'] . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" style="padding: 10px; border: 1px solid var(--surface-200); border-radius: 8px; outline: none; font-family: inherit;" onchange="this.form.submit()">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    </form>
                </div>

                <div class="layout-grid">
                    
                    <!-- Left Column: Calendar (Sticky on large screens) -->
                    <div style="position: relative;">
                        <div class="calendar-wrapper">
                            <?php if ($filter_user_id): ?>
                                    <div class="calendar-header">
                                        <h3 style="font-size: 18px; margin: 0;"><?php echo date("F Y", strtotime($filter_date)); ?></h3>
                                        <div style="display: flex; gap: 8px;">
                                            <!-- Simple Month Nav - could implement if needed, relying on date picker for now -->
                                            <div class="legend-item"><div class="legend-dot" style="background: #22c55e;"></div> P</div>
                                            <div class="legend-item"><div class="legend-dot" style="background: #ef4444;"></div> A</div>
                                            <div class="legend-item"><div class="legend-dot" style="background: #f97316;"></div> HD</div>
                                        </div>
                                    </div>

                                    <div class="calendar-grid">
                                        <div class="cal-day-name">Sun</div>
                                        <div class="cal-day-name">Mon</div>
                                        <div class="cal-day-name">Tue</div>
                                        <div class="cal-day-name">Wed</div>
                                        <div class="cal-day-name">Thu</div>
                                        <div class="cal-day-name">Fri</div>
                                        <div class="cal-day-name">Sat</div>

                                        <?php
                                        $first_day_ts = strtotime($month_start);
                                        $days_in_month = date('t', $first_day_ts);
                                        $start_padding = date('w', $first_day_ts);

                                        // Empty padding days
                                        for ($i = 0; $i < $start_padding; $i++) {
                                            echo '<div class="cal-day empty"></div>';
                                        }

                                        // Days
                                        for ($day = 1; $day <= $days_in_month; $day++) {
                                            $current_date = date('Y-m-', $first_day_ts) . str_pad($day, 2, '0', STR_PAD_LEFT);
                                            $status_class = '';
                                            $status_text = '';

                                            // Logic for marking Absent vs Empty
                                            // If date is in past and no status -> Absent? Or just empty?
                                            // "Red as absent". 
                                    
                                            if (isset($cal_data[$current_date])) {
                                                $s = $cal_data[$current_date];
                                                if ($s == 'Present')
                                                    $status_class = 'status-present';
                                                elseif ($s == 'Absent')
                                                    $status_class = 'status-absent';
                                                elseif ($s == 'Half-day')
                                                    $status_class = 'status-half-day';
                                                elseif ($s == 'Leave')
                                                    $status_class = 'status-leave';
                                            } elseif ($current_date < date('Y-m-d') && date('N', strtotime($current_date)) <= 6) { // Past weekdays
                                                // Optional: Mark unspecified past days as Absent? 
                                                // The user demanded Red as absent.
                                                // If there is NO record, it usually implies absent in these systems.
                                                $status_class = 'status-absent';
                                            }

                                            if ($current_date == date('Y-m-d'))
                                                $status_class .= ' status-today';

                                            echo "<div class='cal-day $status_class' title='$current_date'>";
                                            echo $day;
                                            echo "</div>";
                                        }
                                        ?>
                                    </div>
                                    <div style="margin-top: 16px; font-size: 13px; color: var(--text-light); text-align: center;">
                                        Select a date above to filter the list.
                                    </div>
                            <?php else: ?>
                                    <div style="text-align: center; padding: 40px 20px; color: var(--text-light);">
                                        <div style="background: var(--surface-100); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                            <i class="fa fa-user-check" style="font-size: 24px;"></i>
                                        </div>
                                        <h4 style="margin-bottom: 8px; color: var(--text-dark);">Select an Employee</h4>
                                        <p style="font-size: 14px;">Select an employee from the filter above to view their monthly attendance calendar.</p>
                                    </div>
                            <?php endif; ?>
                        </div>

                        <!-- Manual Add Section (Moving here for better layout) -->
                        <div class="card" style="margin-top: 24px;">
                            <h3 style="font-size: 16px; margin-bottom: 16px;">Quick Update</h3>
                            <form method="POST">
                                <input type="hidden" name="manual_update" value="1">
                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 12px;">Employee</label>
                                    <select name="user_id" required style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 8px;">
                                        <?php
                                        // Reuse iterator logic - need to reset pointer if already used?
                                        // The previous loop was for the Filter dropdown. We need to query again or reset.
                                        $all_emps->data_seek(0);
                                        while ($e = $all_emps->fetch_assoc()) {
                                            $sel = ($e['id'] == $filter_user_id) ? 'selected' : '';
                                            echo "<option value='" . $e['id'] . "' $sel>" . $e['full_name'] . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                    <div>
                                        <label style="font-size: 12px;">Date</label>
                                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 8px;">
                                    </div>
                                    <div>
                                        <label style="font-size: 12px;">Status</label>
                                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 8px;">
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                            <option value="Half-day">Half-day</option>
                                            <option value="Leave">Leave</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary" style="width: 100%;">Update Record</button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Data Table -->
                    <div style="flex: 1;">
                        <div class="card" style="min-height: 600px;">
                            <div style="margin-bottom: 24px;">
                                <h3>Daily Attendance Log</h3>
                                <p style="font-size: 14px; color: var(--text-light);">Records for <?php echo date("F j, Y", strtotime($filter_date)); ?></p>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr style="background: var(--surface-50);">
                                            <th>Employee</th>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
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
                                                $statusClass = 'text-light'; // Fallback
                                                $bgStyle = 'var(--surface-200)';
                                                $textStyle = 'var(--text-light)';

                                                if ($status == 'Present') {
                                                    $bgStyle = '#dcfce7';
                                                    $textStyle = '#166534';
                                                } elseif ($status == 'Absent') {
                                                    $bgStyle = '#fee2e2';
                                                    $textStyle = '#991b1b';
                                                } elseif ($status == 'Half-day') {
                                                    $bgStyle = '#ffedd5';
                                                    $textStyle = '#9a3412';
                                                } elseif ($status == 'Leave') {
                                                    $bgStyle = '#f1f5f9';
                                                    $textStyle = '#475569';
                                                }

                                                echo "<tr style='border-bottom: 1px solid var(--primary-50); transition: background 0.2s;'>";
                                                echo "<td>
                                                        <div style='display: flex; align-items: center; gap: 12px;'>
                                                            <div style='width: 32px; height: 32px; background: var(--primary-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 600; font-size: 12px;'>" . substr($row['full_name'], 0, 1) . "</div>
                                                            <div>
                                                                <a href='profile.php?id=" . $row['user_id_actual'] . "' style='color: var(--text-main); font-weight: 600; text-decoration: none;'>" . htmlspecialchars($row['full_name']) . "</a>
                                                                <div style='font-size: 12px; color: var(--text-light);'>" . $row['login_id'] . "</div>
                                                            </div>
                                                        </div>
                                                      </td>";
                                                echo "<td>" . date("M j", strtotime($filter_date)) . "</td>";
                                                echo "<td>" . ($row['check_in_time'] ? date("h:i A", strtotime($row['check_in_time'])) : '-') . "</td>";
                                                echo "<td>" . ($row['check_out_time'] ? date("h:i A", strtotime($row['check_out_time'])) : '-') . "</td>";
                                                echo "<td><span style='background: {$bgStyle}; color: {$textStyle}; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; min-width: 80px; text-align: center;'>" . $status . "</span></td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' style='padding: 32px; text-align: center; color: var(--text-light);'>No employees found for this criteria.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script src="style.js"></script>
</body>
</html>