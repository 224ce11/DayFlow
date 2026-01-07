<?php
session_start();
include 'db_connect.php';

// Auth Check: Must be Employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: Login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Fetch Calendar Data for the current logged-in employee
$filter_date = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$cal_data = [];
// 1. Get Attendance
$stmt = $conn->prepare("SELECT date, status FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $user_id, $month_start, $month_end);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $cal_data[$row['date']] = $row['status']; // Present, Absent, Half-day
}

// 2. Get Leaves (Approved) to overwrite/fill gaps
$stmt = $conn->prepare("SELECT from_date, to_date FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ((from_date BETWEEN ? AND ?) OR (to_date BETWEEN ? AND ?))");
$stmt->bind_param("issss", $user_id, $month_start, $month_end, $month_start, $month_end);
$stmt->execute();
$l_res = $stmt->get_result();
while ($l = $l_res->fetch_assoc()) {
    $curr = strtotime($l['from_date']);
    $end = strtotime($l['to_date']);
    while ($curr <= $end) {
        $d = date('Y-m-d', $curr);
        if ($d >= $month_start && $d <= $month_end) {
            if (!isset($cal_data[$d]) || $cal_data[$d] == 'Absent') {
                $cal_data[$d] = 'Leave';
            }
        }
        $curr = strtotime('+1 day', $curr);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Dayflow</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="admin_attendance.css">
    <style>
        /* Employee Specific Overrides */
        .dash-container {
            padding: 32px;
            width: 100%;
            margin: 0;
            max-width: none;
        }

        /* Different Layout for Employee: Calendar on Right */
        .layout-grid-emp {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        @media (min-width: 1400px) {
            .layout-grid-emp {
                grid-template-columns: 1fr 380px;
                /* Swap columns: Content 1fr, Cal 380px */
                align-items: start;
            }
        }
    </style>
</head>

<body>

    <!-- Enhanced Navbar -->
    <nav class="navbar"
        style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container"
            style="max-width: 100%; padding: 0 32px; justify-content: flex-start; gap: 40px;">
            <div class="logo-wrapper">
                <a href="#" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>

            <!-- Left-Aligned Navigation -->
            <div class="nav-menu" style="display: flex; gap: 16px; margin-right: auto;">
                <a href="#" class="nav-link nav-module-link active" data-target="module-employees">
                    <i class="fa fa-users"></i> Colleagues
                </a>
                <a href="#" class="nav-link nav-module-link" data-target="module-attendance">
                    <i class="fa fa-calendar-check"></i> My Attendance
                </a>
                <a href="my_payroll.php" class="nav-link">
                    <i class="fa fa-file-invoice-dollar"></i> My Payroll
                </a>
                <span id="timeCounter" class="time-holder"
                    style="font-family: monospace; font-size: 14px; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; color: var(--primary); align-self: center;">00:00:00</span>
            </div>

            <div class="nav-actions">
                <button type="button" id="attendanceToggle" class="check-in-btn" title="Check In"
                    style="margin-right: 16px;">
                    <i class="fa fa-power-off"></i>
                </button>

                <a href="profile.php" class="user-profile-link"
                    style="display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 4px 8px; border-radius: 24px;">
                    <div class="nav-avatar"
                        style="width: 36px; height: 36px; background: var(--primary-gradient); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                        <?php
                        $u_names = explode(' ', $_SESSION['user_name']);
                        echo strtoupper(substr($u_names[0], 0, 1) . (count($u_names) > 1 ? substr($u_names[count($u_names) - 1], 0, 1) : ''));
                        ?>
                    </div>
                </a>

                <a href="logout.php" class="btn btn-sm btn-icon-only" title="Logout"
                    style="margin-left: 8px; color: var(--text-light);">
                    <i class="fa fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <main class="main-content" style="margin-left: 0; width: 100%; max-width: none;">
            <div class="dash-container">

                <!-- MODULE: EMPLOYEES (Default View) -->
                <div id="module-employees" class="module-section active">
                    <div class="card" style="margin-top: 0;">
                        <?php
                        $q = $conn->prepare("SELECT full_name, email, phone, login_id, role FROM users WHERE role = 'employee' AND id != ? ORDER BY created_at DESC");
                        $q->bind_param("i", $user_id);
                        $q->execute();
                        $res = $q->get_result();
                        ?>
                        <div class="employee-slider-wrapper">
                            <h3 style="margin-bottom: 24px;">Team Members</h3>
                            <div class="employee-slider" id="employeeSlider">
                                <?php while ($row = $res->fetch_assoc()):
                                    $hash = md5($row['login_id']);
                                    $hue = hexdec(substr($hash, 0, 2));
                                    $gradient = "linear-gradient(135deg, hsl($hue, 60%, 55%), hsl($hue, 60%, 45%))";
                                    $names = explode(' ', $row['full_name']);
                                    $initials = '';
                                    if (count($names) >= 2)
                                        $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names) - 1], 0, 1));
                                    else
                                        $initials = strtoupper(substr($names[0], 0, 2));
                                    ?>
                                    <div class="employee-card yt-style">
                                        <div class="yt-thumbnail" style="background: <?php echo $gradient; ?>;">
                                            <span class="thumb-content"><?php echo $initials; ?></span>
                                        </div>
                                        <div class="yt-details">
                                            <div class="channel-avatar"><?php echo $initials; ?></div>
                                            <div class="yt-text-info">
                                                <h3 class="yt-title"><?php echo htmlspecialchars($row['full_name']); ?></h3>
                                                <div class="yt-channel-name"><?php echo ucfirst($row['role']); ?> &bull;
                                                    Active</div>
                                                <div class="yt-meta">
                                                    <span><?php echo htmlspecialchars($row['email']); ?></span></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODULE: ATTENDANCE -->
                <div id="module-attendance" class="module-section">

                    <div class="layout-grid-emp">
                        <!-- Left: Data Table (Full Width) -->
                        <div style="flex: 1;">
                            <div class="card" style="min-height: 600px;">
                                <div
                                    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <h3>My Attendance History</h3>
                                        <p style="font-size: 14px; color: var(--text-light);">Recent activity logs</p>
                                    </div>
                                    <button onclick="openLeaveModal()" class="btn btn-sm btn-outline">
                                        <i class="fa fa-calendar-plus"></i> Request Leave
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $hist_q = $conn->prepare("SELECT date, check_in_time, check_out_time, status FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 30");
                                            $hist_q->bind_param("i", $user_id);
                                            $hist_q->execute();
                                            $hist_res = $hist_q->get_result();

                                            if ($hist_res->num_rows > 0) {
                                                while ($row = $hist_res->fetch_assoc()) {
                                                    $status = $row['status'];
                                                    $bgStyle = '#f1f5f9';
                                                    $textStyle = '#475569';
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

                                                    echo "<tr style='border-bottom: 1px solid var(--primary-50);'>";
                                                    echo "<td style='padding: 16px;'>" . date("M j, Y", strtotime($row['date'])) . "</td>";
                                                    echo "<td style='padding: 16px; font-family: monospace;'>" . ($row['check_in_time'] ? date("h:i A", strtotime($row['check_in_time'])) : '-') . "</td>";
                                                    echo "<td style='padding: 16px; font-family: monospace;'>" . ($row['check_out_time'] ? date("h:i A", strtotime($row['check_out_time'])) : '-') . "</td>";
                                                    echo "<td style='padding: 16px;'><span style='background: {$bgStyle}; color: {$textStyle}; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;'>" . $status . "</span></td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='4' style='padding: 24px; text-align: center; color: var(--text-light);'>No recent records found.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Calendar (Sticky) -->
                        <div>
                            <div class="calendar-wrapper" style="position: sticky; top: 100px;">
                                <div class="calendar-header">
                                    <h3 style="font-size: 18px; margin: 0;"><?php echo date("F Y"); ?></h3>
                                    <div style="display: flex; gap: 4px;">
                                        <div class="legend-item" title="Present">
                                            <div class="legend-dot" style="background: #22c55e;"></div>
                                        </div>
                                        <div class="legend-item" title="Absent">
                                            <div class="legend-dot" style="background: #ef4444;"></div>
                                        </div>
                                        <div class="legend-item" title="Half-day">
                                            <div class="legend-dot" style="background: #f97316;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="calendar-grid">
                                    <div class="cal-day-name">S</div>
                                    <div class="cal-day-name">M</div>
                                    <div class="cal-day-name">T</div>
                                    <div class="cal-day-name">W</div>
                                    <div class="cal-day-name">T</div>
                                    <div class="cal-day-name">F</div>
                                    <div class="cal-day-name">S</div>

                                    <?php
                                    $first_day_ts = strtotime($month_start);
                                    $days_in_month = date('t', $first_day_ts);
                                    $start_padding = date('w', $first_day_ts);

                                    for ($i = 0; $i < $start_padding; $i++)
                                        echo '<div class="cal-day empty"></div>';

                                    for ($day = 1; $day <= $days_in_month; $day++) {
                                        $current_date = date('Y-m-', $first_day_ts) . str_pad($day, 2, '0', STR_PAD_LEFT);
                                        $status_class = '';

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
                                        } elseif ($current_date < date('Y-m-d') && date('N', strtotime($current_date)) <= 6) {
                                            $status_class = 'status-absent';
                                        }

                                        if ($current_date == date('Y-m-d'))
                                            $status_class .= ' status-today';

                                        echo "<div class='cal-day $status_class'>$day</div>";
                                    }
                                    ?>
                                </div>

                                <div class="card"
                                    style="margin-top: 24px; padding: 20px; text-align: center; border: 1px dashed var(--card-border); background: var(--surface-50); box-shadow: none;">
                                    <?php
                                    $present_c = $conn->query("SELECT count(*) as c FROM attendance WHERE user_id = $user_id AND status = 'Present'")->fetch_assoc()['c'];
                                    $leave_c = $conn->query("SELECT count(*) as c FROM attendance WHERE user_id = $user_id AND status = 'Leave'")->fetch_assoc()['c'];
                                    ?>
                                    <div style="display: flex; justify-content: space-around;">
                                        <div>
                                            <div style="font-size: 24px; font-weight: 700; color: var(--success);">
                                                <?php echo $present_c; ?></div>
                                            <div style="font-size: 11px; text-transform: uppercase;">Present</div>
                                        </div>
                                        <div>
                                            <div style="font-size: 24px; font-weight: 700; color: var(--warning);">
                                                <?php echo $leave_c; ?></div>
                                            <div style="font-size: 11px; text-transform: uppercase;">Leaves</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Leave Request Modal -->
    <div id="leaveModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 100%; max-width: 500px; padding: 30px;">
            <h3 style="margin-bottom: 20px;">Apply for Leave</h3>
            <form id="leaveForm">
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500;">From Date</label>
                    <input type="date" name="from_date" required
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500;">To Date</label>
                    <input type="date" name="to_date" required
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500;">Reason</label>
                    <textarea name="reason" required rows="3"
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;"></textarea>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeLeaveModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="style.js"></script>
    <script>
        // --- Module Switching Logic ---
        const navLinks = document.querySelectorAll('.nav-module-link');
        const sections = document.querySelectorAll('.module-section');

        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                navLinks.forEach(l => {
                    l.classList.remove('active');
                    l.style.background = 'transparent';
                    l.style.color = 'var(--text-light)';
                });
                link.classList.add('active');
                link.style.background = 'var(--primary-50)';
                link.style.color = 'var(--primary)';
                const targetId = link.getAttribute('data-target');
                sections.forEach(s => s.classList.remove('active'));
                document.getElementById(targetId).classList.add('active');
            });
        });
        document.querySelector('.nav-module-link.active').style.background = 'var(--primary-50)';
        document.querySelector('.nav-module-link.active').style.color = 'var(--primary)';

        // --- Real-Time Attendance Logic ---
        const toggleBtn = document.getElementById('attendanceToggle');
        const timerDisplay = document.getElementById('timeCounter');
        let isCheckedIn = false;
        let startTime = 0;
        let timerInterval;

        function updateTimerDisplay() {
            if (!isCheckedIn) return;
            let now = new Date().getTime();
            let diff = now - startTime;
            if (diff < 0) diff = 0;
            let hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((diff % (1000 * 60)) / 1000);
            timerDisplay.innerText =
                (hours < 10 ? "0" + hours : hours) + ":" +
                (minutes < 10 ? "0" + minutes : minutes) + ":" +
                (seconds < 10 ? "0" + seconds : seconds);
        }

        function fetchStatus() {
            const fd = new FormData();
            fd.append('action', 'get_status');
            fetch('attendance_actions.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.checked_in && !data.checked_out) {
                        isCheckedIn = true;
                        // Adjust to local time logic or server logic as needed
                        // Simple logic:
                        const today = new Date();
                        const [h, m, s] = data.check_in_time.split(':');
                        const start = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m, s).getTime();
                        startTime = start;
                        toggleBtn.classList.add('checked-in');
                        toggleBtn.title = "Check Out (Status: " + data.status + ")";
                        clearInterval(timerInterval);
                        timerInterval = setInterval(updateTimerDisplay, 1000);
                    } else {
                        isCheckedIn = false;
                        toggleBtn.classList.remove('checked-in');
                        toggleBtn.title = "Check In";
                        clearInterval(timerInterval);
                        timerDisplay.innerText = "00:00:00";
                    }
                });
        }
        fetchStatus();

        toggleBtn.addEventListener('click', function () {
            const action = isCheckedIn ? 'check_out' : 'check_in';
            if (confirm("Are you sure you want to " + (isCheckedIn ? "Check Out?" : "Check In?"))) {
                const fd = new FormData();
                fd.append('action', action);
                fetch('attendance_actions.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            fetchStatus();
                            if (action === 'check_out') setTimeout(() => location.reload(), 1000);
                        }
                    });
            }
        });

        // Leave Modal
        function openLeaveModal() { document.getElementById('leaveModal').style.display = 'flex'; }
        function closeLeaveModal() { document.getElementById('leaveModal').style.display = 'none'; }
        document.getElementById('leaveForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'apply_leave');
            fetch('attendance_actions.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) { closeLeaveModal(); location.reload(); }
                });
        });
    </script>
</body>

</html>