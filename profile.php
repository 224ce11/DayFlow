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
