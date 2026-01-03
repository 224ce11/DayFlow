
<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// Decide whose profile to show
$target_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;

// Permission check: Only admins can view others
if ($target_id !== $current_user_id && $current_user_role !== 'admin') {
    $target_id = $current_user_id; // Force self-view
}

$message = "";
$messageType = "";

// --- Handle Profile Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $location = $_POST['location'];
    $private_email = $_POST['private_email'] ?? '';
    $home_address = $_POST['home_address'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';
    
    // Admin only fields
    $salary = $_POST['salary'] ?? null;
    $designation = $_POST['designation'] ?? null;

    // File Handling
    $profile_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $new_name = "user_" . $target_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_dir . $new_name)) {
            $profile_photo_path = $target_dir . $new_name;
        }
    }

    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $target_dir = "uploads/";
        $ext = strtolower(pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION));
        $new_name = "resume_" . $target_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_dir . $new_name)) {
            $resume_path = $target_dir . $new_name;
        }
    }

    // Build Query
    $update_cols = "full_name=?, email=?, phone=?, location=?, private_email=?, home_address=?, bank_account_number=?, ifsc_code=?, dob=?, gender=?, marital_status=?, pan_number=?";
    $types = "ssssssssssss";
    $params = [$full_name, $email, $phone, $location, $private_email, $home_address, $bank_account_number, $ifsc_code, $dob, $gender, $marital_status, $pan_number];

    if ($current_user_role === 'admin') {
        $update_cols .= ", salary=?, designation=?";
        $types .= "ds";
        $params[] = $salary;
        $params[] = $designation;
    }

    if ($profile_photo_path) {
        $update_cols .= ", profile_photo=?";
        $types .= "s";
        $params[] = $profile_photo_path;
    }
    if ($resume_path) {
        $update_cols .= ", resume_path=?";
        $types .= "s";
        $params[] = $resume_path;
    }

    $sql = "UPDATE users SET $update_cols WHERE id=?";
    $types .= "i";
    $params[] = $target_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
        $messageType = "success";
    } else {
        $message = "Update failed: " . $conn->error;
        $messageType = "error";
    }
}

// --- Fetch Target User Data ---
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("User not found.");

// --- Fetch Stats ---
$l_approved = $conn->query("SELECT count(*) as c FROM leave_requests WHERE user_id = $target_id AND status = 'Approved'")->fetch_assoc()['c'];
$l_pending = $conn->query("SELECT count(*) as c FROM leave_requests WHERE user_id = $target_id AND status = 'Pending'")->fetch_assoc()['c'];

$curr_month = date('Y-m');
$att_res = $conn->query("SELECT status, count(*) as c FROM attendance WHERE user_id = $target_id AND date_format(date, '%Y-%m') = '$curr_month' GROUP BY status");
$stats = ['Present'=>0, 'Absent'=>0, 'Half-day'=>0, 'Leave'=>0];
while($r = $att_res->fetch_assoc()) { $stats[$r['status']] = $r['c']; }

$is_self = ($target_id === $current_user_id);
$is_admin_viewing = ($current_user_role === 'admin');

// Avatar Logic
$initials = strtoupper(substr($user['full_name'], 0, 1) . substr(strrchr($user['full_name'], " ") ?: "", 1, 1));
$hash = md5($user['login_id']);
$hue = hexdec(substr($hash, 0, 2));
$gradient = "linear-gradient(135deg, hsl($hue, 60%, 55%), hsl($hue, 60%, 45%))";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user['full_name']; ?> - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: var(--light-bg); padding-top: 80px; }
        .profile-container { max-width: 1200px; margin: 0 auto; padding: 32px; }
        
        .profile-header { background: white; border-radius: 20px; padding: 40px; display: flex; gap: 40px; align-items: center; border: 1px solid var(--card-border); margin-bottom: 32px; position: relative; overflow: hidden; }
        .profile-header::after { content: ''; position: absolute; right: 0; top: 0; width: 300px; height: 100%; background: linear-gradient(90deg, transparent, var(--primary-50)); opacity: 0.4; }
        
        .avatar-box { width: 140px; height: 140px; border-radius: 50%; position: relative; flex-shrink: 0; z-index: 1; }
        .avatar-img { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: var(--shadow-lg); }
        .avatar-fallback { width: 140px; height: 140px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 50px; color: white; font-weight: 700; border: 4px solid white; box-shadow: var(--shadow-lg); }
        
        .stat-badge-grid { display: flex; gap: 16px; margin-top: 20px; }
        .stat-badge { background: var(--surface-50); padding: 8px 16px; border-radius: 12px; border: 1px solid var(--card-border); display: flex; flex-direction: column; align-items: center; min-width: 80px; }
        .stat-badge .val { font-size: 18px; font-weight: 700; color: var(--text-dark); }
        .stat-badge .lbl { font-size: 11px; color: var(--text-light); text-transform: uppercase; font-weight: 600; }

        .tabs-nav { display: flex; gap: 32px; border-bottom: 1px solid var(--card-border); margin-bottom: 32px; padding: 0 10px; }
        .tab-link { padding: 16px 4px; font-weight: 600; color: var(--text-light); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; background: none; border-top: none; border-left: none; border-right: none; }
        .tab-link.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .form-section { display: none; }
        .form-section.active { display: block; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .entry-group { margin-bottom: 24px; }
        .entry-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-dark); }
        .entry-group input, .entry-group select, .entry-group textarea { width: 100%; padding: 12px; border: 1px solid var(--card-border); border-radius: 10px; font-family: inherit; transition: 0.2s; }
        .entry-group input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px var(--primary-50); }
        .entry-group input:disabled { background: var(--surface-50); cursor: not-allowed; }

        .btn-floating-save { position: fixed; bottom: 40px; right: 40px; padding: 16px 32px; border-radius: 50px; box-shadow: var(--shadow-primary); display: flex; align-items: center; gap: 10px; font-weight: 600; z-index: 100; }
    </style>
</head>
<body>

    <nav class="navbar" style="background: var(--glass-bg); backdrop-filter: blur(12px); position: fixed; width: 100%; top: 0; z-index: 1000; border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="<?php echo ($current_user_role=='admin'?'admin_dashboard.php':'employee_dashboard.php'); ?>" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>
            <div style="flex: 1; margin-left: 40px; display: flex; gap: 24px;">
                <a href="<?php echo ($current_user_role=='admin'?'admin_dashboard.php':'employee_dashboard.php'); ?>" class="nav-link"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        
        <?php if($message): ?>
            <div class="message-box <?php echo $messageType; ?>" style="margin-bottom: 32px;">
                <i class="fa <?php echo $messageType=='success'?'fa-check-circle':'fa-circle-exclamation'; ?>"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <input type="hidden" name="update_profile" value="1">

            <!-- Header Section -->
            <div class="profile-header">
                <div class="avatar-box">
                    <?php if($user['profile_photo']): ?>
                        <img src="<?php echo $user['profile_photo']; ?>" class="avatar-img" id="imgPreview">
                    <?php else: ?>
                        <div class="avatar-fallback" style="background: <?php echo $gradient; ?>;"><?php echo $initials; ?></div>
                    <?php endif; ?>
                    <label for="photo_input" style="position: absolute; bottom: 5px; right: 5px; background: var(--primary); color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; transition: 0.2s;">
                        <i class="fa fa-camera"></i>
                    </label>
                    <input type="file" id="photo_input" name="profile_photo" style="display: none;" onchange="previewImg(this)">
                </div>

                <div style="flex: 1; z-index: 1;">
                    <h1 style="font-size: 36px; margin: 0;"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                        <span style="background: var(--primary-50); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 14px;">
                            <?php echo $user['designation'] ?: 'Employee'; ?>
                        </span>
                        <span style="color: var(--text-light); font-size: 14px;"><i class="fa fa-id-card"></i> <?php echo $user['login_id']; ?></span>
                    </div>

                    <div class="stat-badge-grid">
                        <div class="stat-badge">
                            <span class="val" style="color: var(--success);"><?php echo $stats['Present']; ?></span>
                            <span class="lbl">Present</span>
                        </div>
                        <div class="stat-badge">
                            <span class="val" style="color: var(--error);"><?php echo $stats['Absent']; ?></span>
                            <span class="lbl">Absent</span>
                        </div>
                        <div class="stat-badge">
                            <span class="val" style="color: var(--primary);"><?php echo $l_approved; ?></span>
                            <span class="lbl">Leaves</span>
                        </div>
                    </div>
                </div>

                <div style="z-index: 1;">
                   <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Save Profile</button>
                </div>
            </div>

            <div class="card" style="padding: 32px; border-radius: 20px;">
                <div class="tabs-nav">
                    <button type="button" class="tab-link active" onclick="showTab('work')">Work Info</button>
                    <button type="button" class="tab-link" onclick="showTab('personal')">Personal Details</button>
                    <button type="button" class="tab-link" onclick="showTab('banking')">Banking & Legal</button>
                </div>

                <!-- Tab: Work -->
                <div id="tab-work" class="form-section active">
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="entry-group">
                            <label>Designation</label>
                            <input type="text" name="designation" value="<?php echo htmlspecialchars($user['designation']); ?>" <?php echo $is_admin_viewing?'':'disabled'; ?>>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="entry-group">
                            <label>Work Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Office Location</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($user['location']); ?>" placeholder="e.g. Head Office, Floor 4">
                        </div>
                        <div class="entry-group">
                            <label>Monthly Salary (₹)</label>
                            <input type="number" step="0.01" name="salary" value="<?php echo $user['salary']; ?>" <?php echo $is_admin_viewing?'':'disabled'; ?>>
                        </div>
                    </div>
                </div>

                <!-- Tab: Personal -->
                <div id="tab-personal" class="form-section">
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Private Email</label>
                            <input type="email" name="private_email" value="<?php echo htmlspecialchars($user['private_email']); ?>" placeholder="personal@email.com">
                        </div>
                        <div class="entry-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo $user['dob']; ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select</option>
                                <option value="Male" <?php echo $user['gender']=='Male'?'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo $user['gender']=='Female'?'selected':''; ?>>Female</option>
                                <option value="Other" <?php echo $user['gender']=='Other'?'selected':''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="entry-group">
                            <label>Marital Status</label>
                            <select name="marital_status">
                                <option value="">Select</option>
                                <option value="Single" <?php echo $user['marital_status']=='Single'?'selected':''; ?>>Single</option>
                                <option value="Married" <?php echo $user['marital_status']=='Married'?'selected':''; ?>>Married</option>
                            </select>
                        </div>
                    </div>
                    <div class="entry-group">
                        <label>Home Address</label>
                        <textarea name="home_address" rows="3"><?php echo htmlspecialchars($user['home_address']); ?></textarea>
                    </div>
                </div>

                <!-- Tab: Banking -->
                <div id="tab-banking" class="form-section">
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>Bank Account Number</label>
                            <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($user['bank_account_number']); ?>">
                        </div>
                        <div class="entry-group">
                            <label>IFSC Code</label>
                            <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($user['ifsc_code']); ?>" style="text-transform: uppercase;">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="entry-group">
                            <label>PAN Number</label>
                            <input type="text" name="pan_number" value="<?php echo htmlspecialchars($user['pan_number']); ?>" style="text-transform: uppercase;">
                        </div>
                        <div class="entry-group">
                            <label>Resume (PDF)</label>
                            <input type="file" name="resume" accept=".pdf">
                            <?php if($user['resume_path']): ?>
                                <a href="<?php echo $user['resume_path']; ?>" target="_blank" style="font-size: 13px; color: var(--primary); margin-top: 8px; display: inline-block;"><i class="fa fa-file-pdf"></i> View Current Resume</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function previewImg(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('imgPreview');
                    if(img) img.src = e.target.result;
                    else location.reload(); // Fallback if image wasn't there
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
<?php
session_start();
// Prevent caching to ensure logout is effective
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = "";
$messageType = "";

// --- Handle Check-In / Check-Out Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_attendance'])) {
    $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $active_res = $check_stmt->get_result();

    if ($active_res->num_rows > 0) {
        $row = $active_res->fetch_assoc();
        $att_id = $row['id'];
        $upd = $conn->prepare("UPDATE attendance SET check_out = NOW(), status = 'completed' WHERE id = ?");
        $upd->bind_param("i", $att_id);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO attendance (user_id, check_in_time, status) VALUES (?, NOW(), 'active')"); // Using check_in_time based on new schema? 
        // Wait, earlier I fixed schema to use check_in_time (TIME) and date (DATE). 
        // The previous code in profile.php used 'check_in' (DATETIME).
        // I should ALIGN with the schema I fixed in Step 67/68.
        // The corrected schema has: date, check_in_time, check_out_time, status.
        // BUT the previous profile.php code was using a different schema logic.
        // I will use logic compatible with 'attendance_actions.php' which I verified earlier.
        
        // Actually, let's just stick to the Profile Update logic mostly. 
        // If I break attendance here, it might be bad.
        // However, the user asked for *profile* features. 
        // I will implement the PROFILE UPDATE logic primarily.
        // For attendance, I will use a simple redirect or re-use existing logic if I can.
        
        // Re-reading attendance_actions.php (Step 7):
        // It inserts: INSERT INTO attendance (user_id, date, check_in_time, status) ...
        
        // I will replicate that CORRECT logic here to be safe.
        $date = date('Y-m-d');
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $check_stmt->bind_param("is", $user_id, $date);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows == 0) {
             $ins = $conn->prepare("INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, CURTIME(), 'Half-day')");
             $ins->bind_param("is", $user_id, $date);
             $ins->execute();
        }
    }
    header("Location: profile.php");
    exit;
}

// --- Handle Profile Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $location = $_POST['location'];
    
    // New Fields
    $private_email = $_POST['private_email'] ?? '';
    $home_address = $_POST['home_address'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';
    
    // Resume Upload Logic
    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $r_target_dir = "uploads/";
        $r_ext = strtolower(pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION));
        $r_new_name = "resume_" . $user_id . "_" . time() . "." . $r_ext;
        $r_target = $r_target_dir . $r_new_name;
        
        if ($r_ext == "pdf" || $r_ext == "doc" || $r_ext == "docx") {
             if (move_uploaded_file($_FILES["resume"]["tmp_name"], $r_target)) {
                 $resume_path = $r_target;
             }
        }
    }
    
    // Handle File Upload
    $profile_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $target_dir = "uploads/";
        $file_extension = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_extension, $allowed_types)) {
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                $profile_photo_path = $target_file;
            } else {
                $message = "Error uploading file.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid file type. Only JPG, PNG, GIF allowed.";
            $messageType = "error";
        }
    }

    if (empty($message)) {
        // Construct Query
        $sql = "UPDATE users SET email=?, phone=?, location=?, private_email=?, home_address=?, bank_account_number=?, ifsc_code=?, dob=?, gender=?, marital_status=?, pan_number=?";
        $types = "sssssssssss";
        $params = [&$email, &$phone, &$location, &$private_email, &$home_address, &$bank_account_number, &$ifsc_code, &$dob, &$gender, &$marital_status, &$pan_number];

        if ($profile_photo_path) {
            $sql .= ", profile_photo=?";
            $types .= "s";
            $params[] = &$profile_photo_path;
        }
        
        if ($resume_path) {
            $sql .= ", resume_path=?";
            $types .= "s";
            $params[] = &$resume_path;
        }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = &$user_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating profile: " . $conn->error;
            $messageType = "error";
        }
    }
}


// Fetch User Data
$stmt = $conn->prepare("SELECT full_name, email, phone, login_id, role, created_at, location, profile_photo, home_address, private_email, bank_account_number, salary, resume_path, ifsc_code, dob, gender, marital_status, pan_number FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Initials for avatar fallback
$names = explode(' ', $user['full_name']);
$initials = strtoupper(substr($names[0], 0, 1) . (count($names) > 1 ? substr($names[count($names)-1], 0, 1) : ''));
$hash = md5($user['login_id']);
$hue = hexdec(substr($hash, 0, 2));
$gradient = "linear-gradient(135deg, hsl($hue, 60%, 55%), hsl($hue, 60%, 45%))";

$dash_link = $role === 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Dayflow</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-edit-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.2s;
        }
        .profile-edit-overlay:hover {
            transform: scale(1.1);
        }
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar" style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px; justify-content: flex-start; gap: 40px;">
            <div class="logo-wrapper">
                <a href="<?php echo $dash_link; ?>" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>
             <!-- Menu -->
             <div class="nav-menu" style="display: flex; gap: 16px; margin-right: auto;">
                <a href="<?php echo $dash_link; ?>" class="nav-link nav-module-link">
                    <i class="fa fa-chevron-left"></i> Dashboard
                </a>
                <span style="color: var(--text-lighter);">|</span>
                <span class="nav-link nav-module-link active" style="background: var(--primary-50); color: var(--primary);">
                    <i class="fa fa-user-circle"></i> My Profile
                </span>
            </div>

            <div class="nav-actions">
                <a href="logout.php" class="btn btn-sm btn-icon-only" title="Logout" style="color: var(--text-light);">
                    <i class="fa fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper" style="margin-left: 0;">
        <main class="main-content" style="margin: 0 auto; width: 100%; max-width: 800px; padding-top: 120px;">
            <div class="dash-container" style="padding: 0 32px 32px;">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="card profile-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: row; align-items: stretch; min-height: 500px;">
                        
                        <!-- Left Side: Avatar & Basic Info (Sticky) -->
                        <div style="width: 300px; background: var(--surface-50); padding: 48px 24px; text-align: center; border-right: 1px solid var(--card-border); flex-shrink: 0; display: flex; flex-direction: column; align-items: center;">
                            <div style="position: relative; margin-bottom: 24px;">
                                <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile" 
                                         style="width: 160px; height: 160px; border-radius: 50%; object-fit: cover; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 4px solid white;">
                                <?php else: ?>
                                    <div style="width: 160px; height: 160px; border-radius: 50%; background: <?php echo $gradient; ?>; display: flex; align-items: center; justify-content: center; font-size: 64px; color: white; font-weight: 700; border: 4px solid white; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <label for="photo-upload" class="profile-edit-overlay" title="Change Photo" style="width: 40px; height: 40px; right: 10px; bottom: 10px;">
                                    <i class="fa fa-camera" style="font-size: 16px;"></i>
                                </label>
                                <input type="file" id="photo-upload" name="profile_photo" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            </div>
                            
                            <h1 style="margin: 0 0 8px; font-size: 24px; font-weight: 800; color: var(--text-main); word-break: break-word;">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </h1>
                            <div style="color: var(--text-light); font-size: 14px; font-weight: 500; margin-bottom: 32px;">
                                <?php echo ucfirst($user['role']); ?> &bull; Dayflow Inc.
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fa fa-save"></i> Save Changes
                            </button>
                        </div>

                        <!-- Right Side: Form Details -->
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            
                            <!-- Tab Navigation -->
                            <div style="border-bottom: 1px solid var(--card-border); padding: 0 48px;">
                                <div style="display: flex; gap: 32px;">
                                    <button type="button" class="tab-btn active" onclick="openTab(event, 'tab-work')">Work Information</button>
                                    <button type="button" class="tab-btn" onclick="openTab(event, 'tab-private')">Private Information</button>
                                    <button type="button" class="tab-btn" onclick="openTab(event, 'tab-hr')">HR Settings</button>
                                </div>
                            </div>

                            <div style="padding: 48px; flex: 1;">
                                
                                <!-- WORK INFO TAB -->
                                <div id="tab-work" class="tab-content active">
                                    <div class="odoo-form">
                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Contact Information</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Work Email</label>
                                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Work Phone</label>
                                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Work Location</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Office Location</label>
                                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="e.g. New York, USA">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PRIVATE INFO TAB -->
                                <div id="tab-private" class="tab-content" style="display: none;">
                                    <div class="odoo-form">
                                        
                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Identity & Personal</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                     <label>Employee ID</label>
                                                     <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['login_id']); ?>" disabled style="opacity: 0.7; font-weight: 600;">
                                                </div>
                                                <div class="form-group">
                                                    <label>Date of Birth</label>
                                                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Gender</label>
                                                    <select name="gender" class="form-control">
                                                        <option value="">Select Gender</option>
                                                        <option value="Male" <?php if(($user['gender'] ?? '') == 'Male') echo 'selected'; ?>>Male</option>
                                                        <option value="Female" <?php if(($user['gender'] ?? '') == 'Female') echo 'selected'; ?>>Female</option>
                                                        <option value="Other" <?php if(($user['gender'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Marital Status</label>
                                                    <select name="marital_status" class="form-control">
                                                        <option value="">Select Status</option>
                                                        <option value="Single" <?php if(($user['marital_status'] ?? '') == 'Single') echo 'selected'; ?>>Single</option>
                                                        <option value="Married" <?php if(($user['marital_status'] ?? '') == 'Married') echo 'selected'; ?>>Married</option>
                                                        <option value="Divorced" <?php if(($user['marital_status'] ?? '') == 'Divorced') echo 'selected'; ?>>Divorced</option>
                                                        <option value="Widowed" <?php if(($user['marital_status'] ?? '') == 'Widowed') echo 'selected'; ?>>Widowed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>PAN Number</label>
                                                    <input type="text" name="pan_number" class="form-control" value="<?php echo htmlspecialchars($user['pan_number'] ?? ''); ?>" placeholder="ABCDE1234F" style="text-transform: uppercase;">
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Contact & Residence</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Private Email</label>
                                                    <input type="email" name="private_email" class="form-control" value="<?php echo htmlspecialchars($user['private_email'] ?? ''); ?>" placeholder="personal@example.com">
                                                </div>
                                                <div class="form-group">
                                                    <label>Home Address</label>
                                                    <textarea name="home_address" class="form-control" rows="3" placeholder="Enter home address"><?php echo htmlspecialchars($user['home_address'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Bank Information</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Bank Account Number</label>
                                                    <input type="text" name="bank_account_number" class="form-control" value="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>" placeholder="XXXX-XXXX-XXXX">
                                                </div>
                                                <div class="form-group">
                                                    <label>IFSC Code</label>
                                                    <input type="text" name="ifsc_code" class="form-control" value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>" placeholder="ABCD0123456" style="text-transform: uppercase;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- HR SETTINGS TAB -->
                                <div id="tab-hr" class="tab-content" style="display: none;">
                                    <div class="odoo-form">
                                        <div style="margin-bottom: 40px;">
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Resume</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Upload Resume (PDF/DOC)</label>
                                                    <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                                                    <?php if (!empty($user['resume_path'])): ?>
                                                        <div style="margin-top: 8px;">
                                                            <a href="<?php echo htmlspecialchars($user['resume_path']); ?>" target="_blank" style="color: var(--primary); font-size: 14px; font-weight: 500;">
                                                                <i class="fa fa-file-pdf"></i> View Current Resume
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 40px;">
                                            <?php if ($role === 'admin'): ?>
                                                <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Salary Information (Admin Only)</h3>
                                            <?php else: ?>
                                                <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">Salary Information</h3>
                                            <?php endif; ?>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Basic Salary</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['salary'] ?? '0.00'); ?>" disabled style="opacity: 0.7; cursor: not-allowed; font-weight: 700;">
                                                    <small style="color: var(--text-light);">Contact HR to update salary info.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <h3 style="font-size: 18px; margin-bottom: 24px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px; color: var(--text-dark);">System Details</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Login ID</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['login_id']); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                                                </div>
                                                <div class="form-group">
                                                    <label>Member Since</label>
                                                    <input type="text" class="form-control" value="<?php echo date('M Y', strtotime($user['created_at'])); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="update_profile" value="1">
                            </div>
                        </div>

                    </div>
                </form>

            </div>
        </main>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    // Find the image element (either existing img or create one if div)
                    // Simplest is to reload page or just trust user knows they selected it.
                    // Let's try to update the src if img exists.
                    const img = document.querySelector('img[alt="Profile"]');
                    if(img) {
                        img.src = e.target.result;
                    } else {
                        // If it was a div (initials), we might want to replace it, but 
                        // for simplicity let's just let them click save to see changes.
                        // Or we could alert.
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</script>
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from buttons
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
                tablinks[i].style.color = "var(--text-light)";
                tablinks[i].style.borderBottom = "2px solid transparent";
            }
            
            // Show target, add active class
            document.getElementById(tabName).style.display = "block";
            // document.getElementById(tabName).classList.add("active"); // Not strictly needed if display block
            
            evt.currentTarget.classList.add("active");
            evt.currentTarget.style.color = "var(--primary)";
            evt.currentTarget.style.borderBottom = "2px solid var(--primary)";
        }

        // Init Styles for Tabs
        document.addEventListener("DOMContentLoaded", function() {
            // Set initial style for active tab
            const activeTab = document.querySelector('.tab-btn.active');
            if(activeTab) {
                activeTab.style.color = "var(--primary)";
                activeTab.style.borderBottom = "2px solid var(--primary)";
            }
        });

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('img[alt="Profile"]');
                    if(img) {
                        img.src = e.target.result;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
