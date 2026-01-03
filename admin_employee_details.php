<?php
session_start();
include 'db_connect.php';

// Auth Check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.php");
    exit;
}

$mapped_emp_id = $_GET['id'] ?? 0;
if ($mapped_emp_id == 0) {
    header("Location: admin_dashboard.php");
    exit;
}

// Fetch User Details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $mapped_emp_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Employee not found.");
}

// Handle Salary Update
$msg = "";
$msgType = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary'])) {
    $new_salary = $_POST['salary'];
    $new_designation = $_POST['designation'];
    $upd = $conn->prepare("UPDATE users SET salary = ?, designation = ? WHERE id = ?");
    $upd->bind_param("dsi", $new_salary, $new_designation, $mapped_emp_id);
    if ($upd->execute()) {
        $user['salary'] = $new_salary;
        $user['designation'] = $new_designation;
        $msg = "Profile updated successfully.";
        $msgType = "success";
    } else {
        $msg = "Error updating profile.";
        $msgType = "error";
    }
}

// Fetch Stats
// 1. Total Approved Leaves
$l_q = $conn->query("SELECT count(*) as c FROM leave_requests WHERE user_id = $mapped_emp_id AND status = 'Approved'");
$total_leaves = $l_q->fetch_assoc()['c'];

// 2. Attendance Stats (Present/Absent/Half-day) for current month
$curr_month = date('Y-m');
$att_stats = $conn->query("SELECT status, count(*) as c FROM attendance WHERE user_id = $mapped_emp_id AND date_format(date, '%Y-%m') = '$curr_month' GROUP BY status");
$stats = ['Present'=>0, 'Absent'=>0, 'Half-day'=>0, 'Leave'=>0];
while($r = $att_stats->fetch_assoc()) {
    $stats[$r['status']] = $r['c'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['full_name']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Page Specific Overrides to match Dashboard vibes */
        body {
            background-color: var(--light-bg);
            padding-top: 80px; /* Space for fixed navbar */
        }
        
        .profile-header-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 32px;
            margin-bottom: 32px;
            border: 1px solid var(--card-border);
            position: relative;
            overflow: hidden;
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, var(--primary-50));
            opacity: 0.5;
            z-index: 0;
        }

        .profile-avatar-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            font-size: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: var(--shadow-primary);
            position: relative;
            z-index: 1;
        }

        .profile-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .profile-name {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .profile-role {
            font-size: 16px;
            color: var(--primary);
            font-weight: 600;
            background: var(--primary-50);
            padding: 6px 16px;
            border-radius: var(--radius-full);
            display: inline-block;
            margin-bottom: 16px;
        }

        .profile-meta {
            display: flex;
            gap: 24px;
            color: var(--text-light);
            font-size: 15px;
        }
        
        .profile-meta i {
            color: var(--primary-light);
            margin-right: 8px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
        }

        .stat-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
        }

        .mini-stat-card {
            background: var(--surface-50);
            padding: 24px;
            border-radius: var(--radius-lg);
            text-align: center;
            border: 1px solid var(--surface-200);
            transition: var(--transition-base);
        }

        .mini-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            background: white;
            border-color: var(--primary-100);
        }

        .mini-stat-val {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .mini-stat-label {
            font-size: 13px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow-2xl);
            transform: translateY(20px);
            transition: transform 0.3s ease;
            position: relative;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--input-border);
            border-radius: var(--radius-md);
            background: var(--input-bg);
            color: var(--text-main);
            margin-bottom: 20px;
            transition: var(--transition-base);
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
            outline: none;
        }

        @media (max-width: 900px) {
            .details-grid { grid-template-columns: 1fr; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .profile-meta { justify-content: center; flex-wrap: wrap; }
            .profile-header-card::before { width: 100%; height: 50%; bottom: 0; top: auto; background: linear-gradient(0deg, transparent, var(--primary-50)); }
        }
    </style>
</head>
<body>

    <!-- Standard Admin Navbar -->
    <nav class="navbar" style="background: var(--glass-bg); backdrop-filter: blur(12px);">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="admin_dashboard.php" class="logo">
                    <div class="logo-icon">
                        <i class="fa fa-layer-group"></i>
                    </div>
                    <span>Dayflow</span>
                </a>
            </div>
            
            <div class="nav-actions">
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

    <div class="container" style="max-width: 1200px; padding-bottom: 60px;">
        
        <!-- Breadcrumb / Back -->
        <div style="margin: 32px 0;">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="message-box <?php echo $msgType; ?>" class="card" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: <?php echo $msgType == 'success' ? '#def7ec' : '#fde8e8'; ?>; color: <?php echo $msgType == 'success' ? '#03543f' : '#9b1c1c'; ?>;">
                <i class="<?php echo $msgType == 'success' ? 'fa fa-check-circle' : 'fa fa-exclamation-circle'; ?>"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-header-card">
            <?php 
            $initials = strtoupper(substr($user['full_name'], 0, 1) . substr(strrchr($user['full_name'], " ") ?: "", 1, 1));
            ?>
            <div class="profile-avatar-lg"><?php echo $initials; ?></div>
            
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <span class="profile-role"><?php echo htmlspecialchars($user['designation']); ?></span>
                
                <div class="profile-meta">
                    <span><i class="fa fa-id-badge"></i> <?php echo htmlspecialchars($user['login_id']); ?></span>
                    <span><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    <span><i class="fa fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
                    <span><i class="fa fa-calendar-alt"></i> Joined <?php echo date("M Y", strtotime($user['joining_date'] ?? $user['created_at'])); ?></span>
                </div>
            </div>

            <div class="profile-actions">
                <button onclick="openModal()" class="btn btn-primary">
                    <i class="fa fa-edit"></i> Edit Details
                </button>
            </div>
        </div>

        <div class="details-grid">
            
            <!-- Left Column -->
            <div style="display: flex; flex-direction: column; gap: 32px;">
                
                <!-- Stats Section -->
                <div class="card" style="padding: 32px; border-radius: var(--radius-lg); background: var(--card-bg); box-shadow: var(--shadow-md);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h3><i class="fa fa-chart-pie" style="color: var(--primary); margin-right: 10px;"></i>Monthly Overview</h3>
                        <span class="badge" style="margin: 0;"><?php echo date('F Y'); ?></span>
                    </div>

                    <div class="stat-card-grid">
                        <div class="mini-stat-card">
                            <div class="mini-stat-val" style="color: var(--success);"><?php echo $stats['Present']; ?></div>
                            <div class="mini-stat-label">Present</div>
                        </div>
                        <div class="mini-stat-card">
                            <div class="mini-stat-val" style="color: var(--warning);"><?php echo $stats['Half-day']; ?></div>
                            <div class="mini-stat-label">Half Day</div>
                        </div>
                        <div class="mini-stat-card">
                            <div class="mini-stat-val" style="color: var(--error);"><?php echo $stats['Absent']; ?></div>
                            <div class="mini-stat-label">Absent</div>
                        </div>
                        <div class="mini-stat-card">
                            <div class="mini-stat-val" style="color: var(--accent);"><?php echo $stats['Leave']; ?></div>
                            <div class="mini-stat-label">Leave</div>
                        </div>
                    </div>
                </div>

                <!-- Compensation Card -->
                <div class="card" style="padding: 32px; border-radius: var(--radius-lg); background: var(--card-bg); box-shadow: var(--shadow-md);">
                    <h3 style="margin-bottom: 24px;"><i class="fa fa-wallet" style="color: var(--success); margin-right: 10px;"></i>Compensation & Role</h3>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; background: var(--surface-50); border-radius: var(--radius-md); border: 1px solid var(--surface-200);">
                        <div>
                            <div style="font-size: 14px; color: var(--text-light); margin-bottom: 4px;">Current Monthly Salary</div>
                            <div style="font-size: 28px; font-weight: 700; color: var(--text-dark);">$<?php echo number_format($user['salary'], 2); ?></div>
                        </div>
                        <div style="text-align: right;">
                             <div style="font-size: 14px; color: var(--text-light); margin-bottom: 4px;">Status</div>
                             <span style="color: var(--success); font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                 <i class="fa fa-check-circle"></i> Active
                             </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div>
                <!-- Leave History -->
                <div class="card" style="padding: 32px; border-radius: var(--radius-lg); background: var(--card-bg); box-shadow: var(--shadow-md); height: 100%;">
                    <h3 style="margin-bottom: 24px;"><i class="fa fa-history" style="color: var(--text-light); margin-right: 10px;"></i>Recent Activity</h3>
                    
                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px; text-transform: uppercase;">Leave History</h4>
                        <?php
                            $hist = $conn->query("SELECT * FROM leave_requests WHERE user_id = $mapped_emp_id ORDER BY created_at DESC LIMIT 5");
                            if($hist->num_rows > 0) {
                                while($row = $hist->fetch_assoc()) {
                                    $sColor = 'var(--text-light)';
                                    $sBg = 'var(--surface-200)';
                                    $sIcon = 'fa-clock';
                                    
                                    if($row['status']=='Approved') { $sColor='var(--success)'; $sBg='rgba(16, 185, 129, 0.1)'; $sIcon='fa-check'; }
                                    if($row['status']=='Rejected') { $sColor='var(--error)'; $sBg='rgba(239, 68, 68, 0.1)'; $sIcon='fa-times'; }
                                    if($row['status']=='Pending') { $sColor='var(--warning)'; $sBg='rgba(245, 158, 11, 0.1)'; $sIcon='fa-hourglass-half'; }
                                    
                                    echo "
                                    <div style='display: flex; align-items: flex-start; gap: 16px; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--surface-100);'>
                                        <div style='width: 36px; height: 36px; border-radius: 50%; background: $sBg; color: $sColor; display: flex; align-items: center; justify-content: center; flex-shrink: 0;'>
                                            <i class='fa $sIcon'></i>
                                        </div>
                                        <div style='flex: 1;'>
                                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                                <span style='font-weight: 600; font-size: 14px;'>Leave Request</span>
                                                <span style='font-size: 12px; color: $sColor; font-weight: 600;'>".$row['status']."</span>
                                            </div>
                                            <p style='font-size: 13px; color: var(--text-light); margin-bottom: 4px;'>".htmlspecialchars($row['reason'])."</p>
                                            <div style='font-size: 12px; color: var(--text-lighter);'>
                                                ".date("M j", strtotime($row['from_date']))." - ".date("M j", strtotime($row['to_date']))."
                                            </div>
                                        </div>
                                    </div>
                                    ";
                                }
                            } else {
                                echo "<div style='text-align: center; color: var(--text-light); padding: 20px;'>No recent leave requests</div>";
                            }
                        ?>
                    </div>
                    
                    <button class="btn btn-outline btn-sm" style="width: 100%;">View Full Activity Log</button>
                </div>
            </div>

        </div>

    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 24px;">Edit Profile</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-light);"><i class="fa fa-times"></i></button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="update_salary" value="1">
                
                <div class="form-group">
                    <label class="form-label">Job Designation</label>
                    <div style="position: relative;">
                        <i class="fa fa-briefcase" style="position: absolute; left: 14px; top: 14px; color: var(--text-light);"></i>
                        <input type="text" name="designation" class="form-input" style="padding-left: 44px;" value="<?php echo htmlspecialchars($user['designation']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Monthly Salary ($)</label>
                    <div style="position: relative;">
                        <i class="fa fa-dollar-sign" style="position: absolute; left: 14px; top: 14px; color: var(--text-light);"></i>
                        <input type="number" step="0.01" name="salary" class="form-input" style="padding-left: 44px;" value="<?php echo $user['salary']; ?>" required>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            const modal = document.getElementById('editModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
            }, 10);
        }

        function closeModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Close on clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    <script src="style.js"></script>
</body>
</html>
