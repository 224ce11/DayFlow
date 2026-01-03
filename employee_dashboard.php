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

// --- Handle Check-In / Check-Out (Simulated for UI Layout) ---
$is_checked_in = false;
$check_in_time = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Enhanced Navbar (Proper Header) -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px; justify-content: flex-start; gap: 40px;">
            <div class="logo-wrapper">
                <a href="#" class="logo">
                    <div class="logo-icon">
                        <i class="fa fa-layer-group"></i>
                    </div>
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
                <span id="timeCounter" class="time-holder" style="font-family: monospace; font-size: 14px; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; color: var(--primary); align-self: center;">00:00:00</span>
            </div>

            <div class="nav-actions">
                <!-- Attendance Button Only -->
                <button type="button" id="attendanceToggle" class="check-in-btn" title="Check In" style="margin-right: 16px;">
                    <i class="fa fa-power-off"></i>
                </button>
                
                <!-- User Profile with Avatar -->
                <a href="profile.php" class="user-profile-link" style="display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 4px 8px; border-radius: 24px; transition: background 0.2s;">
                    <div class="nav-avatar" style="width: 36px; height: 36px; background: var(--primary-gradient); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                        <?php 
                        // Generate Initials from Session Name
                        $u_names = explode(' ', $_SESSION['user_name']);
                        echo strtoupper(substr($u_names[0], 0, 1) . (count($u_names) > 1 ? substr($u_names[count($u_names)-1], 0, 1) : ''));
                        ?>
                    </div>
                    <div style="text-align: left; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px; color: var(--text-dark);"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div style="font-size: 12px; color: var(--text-light);">Employee</div>
                    </div>
                </a>
                
                <a href="Login.php" class="btn btn-sm btn-icon-only" title="Logout" style="margin-left: 8px; color: var(--text-light);">
                    <i class="fa fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- Main Content (Full Width) -->
        <main class="main-content" style="margin-left: 0; width: 100%;">
            
            <!-- Content Area -->
            <div class="dash-container" style="padding: 32px; max-width: 1400px; margin: 0 auto;">
                
                <!-- MODULE: EMPLOYEES (Renamed View) -->
                <div id="module-employees" class="module-section active">
                    
                    <div class="card" style="margin-top: 0;">
                        <?php
                        // Updated query to view all employees EXCEPT current user
                        $q = $conn->prepare("SELECT full_name, email, phone, login_id, role FROM users WHERE role = 'employee' AND id != ? ORDER BY created_at DESC");
                        $q->bind_param("i", $user_id);
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
                                        <!-- Duration Badge Hidden -->
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
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>My Attendance History</h3>
                            <span class="badge badge-primary">Last 7 Days</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc; text-align: left;">
                                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Date</th>
                                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Check In</th>
                                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Check Out</th>
                                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $att_q = $conn->prepare("SELECT date, check_in_time, check_out_time, status FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 7");
                                    $att_q->bind_param("i", $user_id);
                                    $att_q->execute();
                                    $att_res = $att_q->get_result();
                                    
                                    if ($att_res->num_rows > 0) {
                                        while ($row = $att_res->fetch_assoc()) {
                                            $statusColor = 'var(--text-light)';
                                            if ($row['status'] == 'Present') $statusColor = 'var(--success)';
                                            elseif ($row['status'] == 'Absent') $statusColor = 'var(--danger)';
                                            elseif ($row['status'] == 'Half-day') $statusColor = 'var(--warning)';
                                            elseif ($row['status'] == 'Leave') $statusColor = 'var(--info)';

                                            echo "<tr style='border-bottom: 1px solid #f1f5f9;'>";
                                            echo "<td style='padding: 12px;'>" . date("M j, Y", strtotime($row['date'])) . "</td>";
                                            echo "<td style='padding: 12px;'>" . ($row['check_in_time'] ? date("h:i A", strtotime($row['check_in_time'])) : '--:--') . "</td>";
                                            echo "<td style='padding: 12px;'>" . ($row['check_out_time'] ? date("h:i A", strtotime($row['check_out_time'])) : '--:--') . "</td>";
                                            echo "<td style='padding: 12px;'><span style='color: white; background: {$statusColor}; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;'>" . $row['status'] . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' style='padding: 20px; text-align: center; color: var(--text-light);'>No records found.</td></tr>";
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
                        <h3 style="margin-bottom: 20px;">Open Positions</h3>
                        <p style="color: var(--text-light); text-align: center; padding: 40px;">No open positions available.</p>
                    </div>
                </div>

                <!-- MODULE: REPORTS -->
                <div id="module-reports" class="module-section">
                    <div class="card">
                        <h3 style="margin-bottom: 20px;">Performance Reports</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Days Present</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary);">0</div>
                            </div>
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Leaves Taken</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--warning);">0</div>
                            </div>
                            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; text-align: center;">
                                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">Overtime</h4>
                                <div style="font-size: 24px; font-weight: 700; color: var(--success);">0h</div>
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
                link.style.background = 'var(--primary-50)'; 
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


        // --- Real Attendance Logic ---
        const toggleBtn = document.getElementById('attendanceToggle');
        const timerDisplay = document.getElementById('timeCounter');
        let timerInterval;
        let startTime = 0;

        function fetchStatus() {
            const formData = new FormData();
            formData.append('action', 'get_status');

            fetch('attendance_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.checked_in) {
                        // User is currently checked in
                        toggleBtn.classList.add('checked-in');
                        toggleBtn.innerHTML = '<i class="fa fa-power-off"></i>';
                        toggleBtn.title = "Check Out";
                        
                        // Parse time (HH:MM:SS) to calculate duration
                        // Assuming check_in_time is server time today
                        const today = new Date(); // Local browser time
                        // Aligning with server time is complex without timezone sync, 
                        // but for UI duration we can approximate or just show static Start Time.
                        // Let's showing running duration from start time
                        
                        const [hours, minutes, seconds] = data.check_in_time.split(':');
                        const checkInDate = new Date();
                        checkInDate.setHours(hours, minutes, seconds);
                        
                        startTime = checkInDate.getTime();
                        startTimer();

                    } else if (data.checked_out) {
                        // User checked out today
                        toggleBtn.classList.remove('checked-in');
                        toggleBtn.disabled = true;
                        toggleBtn.style.opacity = '0.5';
                        toggleBtn.title = "Checked Out for Today";
                        timerDisplay.innerText = "Completed";
                        stopTimer();
                    } else {
                        // Not checked in yet
                        toggleBtn.classList.remove('checked-in');
                        toggleBtn.title = "Check In";
                        timerDisplay.innerText = "00:00:00";
                        stopTimer();
                    }
                }
            })
            .catch(err => {
                console.error(err);
                // Alert only if manual interaction or specific error handling needed. 
                // For init, maybe just log? 
                // If the user says "nothing happening", let's alert to help debug.
                // alert("Failed to load attendance status via AJAX.");
            });
        }

        toggleBtn.addEventListener('click', function() {
            if (this.disabled) return;
            
            const isCheckingOut = this.classList.contains('checked-in');
            const action = isCheckingOut ? 'check_out' : 'check_in';
            
            if (isCheckingOut && !confirm("Are you sure you want to check out? This will end your attendance for today.")) {
                return;
            }

            const formData = new FormData();
            formData.append('action', action);

            fetch('attendance_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                   // Reload to update PHP table and status
                   location.reload(); 
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert("An error occurred. Please try again.");
            });
        });

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            updateDisplay();
            timerInterval = setInterval(updateDisplay, 1000);
        }

        function stopTimer() {
            if (timerInterval) clearInterval(timerInterval);
        }

        function updateDisplay() {
            let now = new Date().getTime();
            // Handle day rollover if needed, for now assume same day
            let diff = now - startTime;
            
            // Should verify diff is positive (server time vs client time sync issue)
            if (diff < 0) diff = 0; 

            let hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((diff % (1000 * 60)) / 1000);

            timerDisplay.innerText = 
                (hours < 10 ? "0" + hours : hours) + ":" + 
                (minutes < 10 ? "0" + minutes : minutes) + ":" + 
                (seconds < 10 ? "0" + seconds : seconds);
        }

        // Initialize
        fetchStatus();
    </script>
</body>
</html>
