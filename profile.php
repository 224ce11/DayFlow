<?php
session_start();
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// Decide whose profile to show (Admin can view others)
$target_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;
if ($target_id !== $current_user_id && $current_user_role !== 'admin') {
    $target_id = $current_user_id;
}

$message = "";
$messageType = "";

// --- Handle Profile Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {

    // Collect Inputs
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $location = $_POST['location'] ?? '';
    $private_email = $_POST['private_email'] ?? '';
    $home_address = $_POST['home_address'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $marital_status = $_POST['marital_status'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';

    // Admin Only Inputs
    $salary = $_POST['salary'] ?? null;
    $designation = $_POST['designation'] ?? null;

    // File Uploads
    $profile_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir))
            mkdir($target_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_name = "user_" . $target_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_dir . $new_name)) {
                $profile_photo_path = $target_dir . $new_name;
            }
        } else {
            $message = "Invalid image format.";
            $messageType = "error";
        }
    }

    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir))
            mkdir($target_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION));
        $allowed_docs = ['pdf', 'doc', 'docx'];
        if (in_array($ext, $allowed_docs)) {
            $new_name = "resume_" . $target_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_dir . $new_name)) {
                $resume_path = $target_dir . $new_name;
            }
        }
    }

    if (empty($message)) {
        // Build SQL
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
}

// --- Fetch User Data ---
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user)
    die("User not found.");

// --- Fetch Stats ---
$curr_month = date('Y-m');
$att_res = $conn->query("SELECT status, count(*) as c FROM attendance WHERE user_id = $target_id AND date_format(date, '%Y-%m') = '$curr_month' GROUP BY status");
$stats = ['Present' => 0, 'Absent' => 0, 'Half-day' => 0, 'Leave' => 0];
while ($r = $att_res ? $att_res->fetch_assoc() : null) {
    $stats[$r['status']] = $r['c'];
}

$l_approved = $conn->query("SELECT count(*) as c FROM leave_requests WHERE user_id = $target_id AND status = 'Approved'")->fetch_assoc()['c'];

// View Permissions
$is_admin = ($current_user_role === 'admin');
$disable_if_not_admin = $is_admin ? '' : 'disabled';

// Visual Helpers
$initials = strtoupper(substr($user['full_name'], 0, 1));
if (strpos($user['full_name'], ' ') !== false) {
    $initials .= strtoupper(substr(strrchr($user['full_name'], " "), 1, 1));
}
$hash = md5($user['login_id']);
$hue = hexdec(substr($hash, 0, 2));
$gradient = "linear-gradient(135deg, hsl($hue, 60%, 55%), hsl($hue, 60%, 45%))";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Dayflow</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Custom Profile Styles */
        body {
            background-color: var(--light-bg);
            padding-top: 80px;
        }

        .profile-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 24px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 32px;
            align-items: start;
        }

        /* Identity Card (Left) */
        .identity-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--card-border);
            overflow: hidden;
            position: sticky;
            top: 100px;
            box-shadow: var(--shadow-lg);
        }

        .id-header {
            height: 140px;
            background: var(--hero-gradient);
            position: relative;
        }

        .id-header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent);
        }

        .avatar-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -70px;
            padding: 0 24px 32px;
            position: relative;
            z-index: 10;
        }

        .avatar-wrapper {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 6px solid var(--card-bg);
            box-shadow: var(--shadow-xl);
            position: relative;
            background: var(--surface-100);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-initials {
            font-size: 48px;
            font-weight: 700;
            color: white;
        }

        .edit-avatar-btn {
            position: absolute;
            bottom: 0px;
            right: 0px;
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 4px solid var(--card-bg);
            transition: all 0.2s;
            box-shadow: var(--shadow-md);
        }

        .edit-avatar-btn:hover {
            transform: scale(1.1);
        }

        .user-name-display {
            font-size: 28px;
            font-weight: 800;
            margin-top: 16px;
            text-align: center;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .user-role-badge {
            margin-top: 8px;
            background: var(--primary-50);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid var(--primary-100);
            display: inline-block;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding: 24px;
            border-top: 1px solid var(--card-border);
            margin-top: 8px;
        }

        .q-stat {
            text-align: center;
            padding: 12px 8px;
            background: var(--surface-50);
            border-radius: 12px;
        }

        .q-stat .val {
            display: block;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-dark);
        }

        .q-stat .lbl {
            display: block;
            font-size: 11px;
            color: var(--text-light);
            text-transform: uppercase;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Main Content (Right) */
        .content-area {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .nav-tabs {
            display: flex;
            gap: 12px;
            padding: 6px;
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--card-border);
            width: fit-content;
            box-shadow: var(--shadow-sm);
        }

        .nav-tab {
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-tab:hover {
            background: var(--surface-50);
            color: var(--text-dark);
        }

        .nav-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-primary);
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--card-border);
            padding: 40px;
            box-shadow: var(--shadow-sm);
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .form-card.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary);
            opacity: 0.8;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-main);
            transition: all 0.2s;
            font-size: 15px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }

        .form-control:disabled {
            background: var(--surface-100);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .resume-preview {
            margin-top: 12px;
            padding: 16px;
            background: var(--surface-50);
            border-radius: 12px;
            border: 1px dashed var(--card-border);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .floating-save {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            padding: 16px 32px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            box-shadow: var(--shadow-xl);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s;
            border: none;
        }

        .floating-save:active {
            transform: scale(0.95);
        }

        @media (max-width: 900px) {
            .profile-wrapper {
                grid-template-columns: 1fr;
            }

            .identity-card {
                position: relative;
                top: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container" style="max-width: 100%;">
            <div class="logo-wrapper">
                <a href="<?php echo ($current_user_role == 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php'); ?>"
                    class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>

            <div class="nav-actions">
                <a href="<?php echo ($current_user_role == 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php'); ?>"
                    class="btn btn-secondary btn-sm">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-secondary btn-sm" title="Logout">
                    <i class="fa fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="profile-wrapper">

        <!-- Left Column: Identity -->
        <form method="POST" enctype="multipart/form-data" id="mainForm">
            <input type="hidden" name="update_profile" value="1">

            <div class="identity-card">
                <div class="id-header"></div>
                <div class="avatar-section">
                    <div class="avatar-wrapper">
                        <?php if ($user['profile_photo']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" class="avatar-img"
                                id="avatarPreview">
                        <?php else: ?>
                            <div class="avatar-initials"
                                style="background: <?php echo $gradient; ?>; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <?php echo $initials; ?></div>
                        <?php endif; ?>
                        <label for="u_photo" class="edit-avatar-btn">
                            <i class="fa fa-camera"></i>
                        </label>
                        <input type="file" id="u_photo" name="profile_photo" style="display: none;"
                            onchange="previewImg(this)">
                    </div>

                    <div class="user-name-display">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </div>
                    <div class="user-role-badge">
                        <?php echo $user['designation'] ?: ucfirst($user['role']); ?>
                    </div>

                    <div
                        style="margin-top: 16px; color: var(--text-light); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-id-card"></i> <?php echo $user['login_id']; ?>
                    </div>
                </div>

                <div class="quick-stats">
                    <div class="q-stat">
                        <span class="val" style="color: var(--success);"><?php echo $stats['Present']; ?></span>
                        <span class="lbl">Present</span>
                    </div>
                    <div class="q-stat">
                        <span class="val" style="color: var(--primary);"><?php echo $l_approved; ?></span>
                        <span class="lbl">Leaves</span>
                    </div>
                    <div class="q-stat">
                        <span class="val" style="color: var(--warning);"><?php echo $stats['Half-day']; ?></span>
                        <span class="lbl">Half Days</span>
                    </div>
                </div>
            </div>

        </form>

        <!-- Right Column: Content -->
        <div class="content-area">

            <?php if ($message): ?>
                <div class="alert"
                    style="background: var(<?php echo $messageType == 'success' ? '--success-light' : '--error-light'; ?>); color: white; padding: 16px; border-radius: 12px; margin-bottom: 0;">
                    <i class="fa <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchTab('general')"><i class="fa fa-user"></i>
                    General</button>
                <button class="nav-tab" onclick="switchTab('personal')"><i class="fa fa-home"></i> Personal</button>
                <button class="nav-tab" onclick="switchTab('financial')"><i class="fa fa-wallet"></i> Financial</button>
                <button class="nav-tab" onclick="switchTab('documents')"><i class="fa fa-folder"></i> Documents</button>
            </div>

            <!-- Bind form fields to the main form using form attribute if outside? 
                 No, HTML5 'form' attribute is risky across browsers if form is separate.
                 We will just wrap the whole Right Column inputs within the SAME form as Left Column or 
                 Use JS to submit.
                 
                 Better approach: Let's make the whole page ONE form.
                 The previous <form> tag opened above 'identity-card'.
            -->

            <!-- GENERAL TAB -->
            <div id="tab-general" class="form-card active">
                <div class="section-title"><i class="fa fa-user-circle"></i> Work & Contact Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['designation']); ?>" <?php echo $disable_if_not_admin; ?>>
                    </div>
                    <div class="form-group">
                        <label>Work Email</label>
                        <input type="email" name="email" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Work Phone</label>
                        <input type="text" name="phone" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Office Location</label>
                        <input type="text" name="location" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['location']); ?>" placeholder="e.g. Headquarters">
                    </div>
                    <div class="form-group">
                        <label>Monthly Salary</label>
                        <input type="text" name="salary" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['salary'] ?? ''); ?>" <?php echo $disable_if_not_admin; ?>>
                    </div>
                </div>
            </div>

            <!-- PERSONAL TAB -->
            <div id="tab-personal" class="form-card">
                <div class="section-title"><i class="fa fa-home"></i> Personal details</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Private Email</label>
                        <input type="email" name="private_email" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['private_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" form="mainForm" class="form-control">
                            <option value="">Select...</option>
                            <option value="Male" <?php echo ($user['gender'] == 'Male' ? 'selected' : ''); ?>>Male</option>
                            <option value="Female" <?php echo ($user['gender'] == 'Female' ? 'selected' : ''); ?>>Female
                            </option>
                            <option value="Other" <?php echo ($user['gender'] == 'Other' ? 'selected' : ''); ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status" form="mainForm" class="form-control">
                            <option value="">Select...</option>
                            <option value="Single" <?php echo ($user['marital_status'] == 'Single' ? 'selected' : ''); ?>>
                                Single</option>
                            <option value="Married" <?php echo ($user['marital_status'] == 'Married' ? 'selected' : ''); ?>>
                                Married</option>
                            <option value="Other" <?php echo ($user['marital_status'] == 'Other' ? 'selected' : ''); ?>>Other
                            </option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Home Address</label>
                    <textarea name="home_address" form="mainForm" class="form-control"
                        rows="3"><?php echo htmlspecialchars($user['home_address'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- FINANCIAL TAB -->
            <div id="tab-financial" class="form-card">
                <div class="section-title"><i class="fa fa-university"></i> Financial Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank Account Number</label>
                        <input type="text" name="bank_account_number" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="ifsc_code" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>"
                            style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_number" form="mainForm" class="form-control"
                            value="<?php echo htmlspecialchars($user['pan_number'] ?? ''); ?>"
                            style="text-transform: uppercase;">
                    </div>
                </div>
            </div>

            <!-- DOCUMENTS TAB -->
            <div id="tab-documents" class="form-card">
                <div class="section-title"><i class="fa fa-folder-open"></i> Documents</div>
                <div class="form-group">
                    <label>Resume / CV</label>
                    <input type="file" name="resume" form="mainForm" class="form-control" accept=".pdf,.doc,.docx">
                    <?php if (!empty($user['resume_path'])): ?>
                        <div class="resume-preview">
                            <i class="fa fa-file-pdf" style="color: var(--error); font-size: 24px;"></i>
                            <div>
                                <div style="font-weight: 600;">Current Resume</div>
                                <a href="<?php echo htmlspecialchars($user['resume_path']); ?>" target="_blank"
                                    style="color: var(--primary);">Download / View</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Floating Save Button -->
    <button type="submit" form="mainForm" class="btn btn-primary floating-save">
        <i class="fa fa-save"></i> Save Changes
    </button>

    <script src="style.js"></script>
    <script>
        function switchTab(tabName) {
            // Update buttons
            document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Update cards
            document.querySelectorAll('.form-card').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
        }

        function previewImg(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.getElementById('avatarPreview');
                    const wrapper = document.querySelector('.avatar-wrapper');

                    if (img) {
                        img.src = e.target.result;
                    } else {
                        // If no img tag (was initials), replace contents
                        wrapper.innerHTML = `<img src="${e.target.result}" class="avatar-img" id="avatarPreview">`;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>