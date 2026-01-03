<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Handle Salary Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_salary'])) {
    $emp_id = $_POST['emp_id'];
    $basic = $_POST['basic_salary'];
    $hra = $_POST['hra'];
    $allowances = $_POST['allowances'];
    $deductions = $_POST['deductions'];

    // Check if structure exists
    $check = $conn->query("SELECT id FROM salary_structures WHERE user_id = '$emp_id'");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE salary_structures SET basic_salary=?, hra=?, allowances=?, deductions=? WHERE user_id=?");
        $stmt->bind_param("ddddd", $basic, $hra, $allowances, $deductions, $emp_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO salary_structures (basic_salary, hra, allowances, deductions, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ddddd", $basic, $hra, $allowances, $deductions, $emp_id);
    }

    if ($stmt->execute()) {
        $message = "Salary structure updated successfully.";
        $messageType = "success";
    } else {
        $message = "Error updating salary: " . $conn->error;
        $messageType = "error";
    }
}

// Handle Process Payroll
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_payroll'])) {
    $emp_id = $_POST['emp_id'];
    $month = date('n');
    $year = date('Y');

    // Get current structure
    $s_query = $conn->query("SELECT * FROM salary_structures WHERE user_id = '$emp_id'");
    if ($s_query->num_rows > 0) {
        $struct = $s_query->fetch_assoc();
        $net_pay = $struct['basic_salary'] + $struct['hra'] + $struct['allowances'] - $struct['deductions'];

        // Check if already paid this month
        $chk_pay = $conn->query("SELECT id FROM payroll_records WHERE user_id='$emp_id' AND month='$month' AND year='$year'");
        if ($chk_pay->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO payroll_records (user_id, month, year, basic_salary, hra, allowances, deductions, net_pay, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())");
            $ins->bind_param("iiiddddd", $emp_id, $month, $year, $struct['basic_salary'], $struct['hra'], $struct['allowances'], $struct['deductions'], $net_pay);
            if ($ins->execute()) {
                $message = "Payroll processed for " . date('F') . ".";
                $messageType = "success";
            } else {
                $message = "Error processing payroll: " . $conn->error;
                $messageType = "error";
            }
        } else {
            $message = "Payroll already processed for this month.";
            $messageType = "error";
        }
    } else {
        $message = "Salary structure not set for this employee.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - Dayflow</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .salary-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            /* Center horizontally */
            align-items: center;
            /* Center vertically */
        }

        .salary-modal.active {
            display: flex;
            /* Changed to flex for centering */
        }

        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            /* Responsive width */
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            /* Removed margin: 100px auto to rely on flexbox centering */
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <nav class="navbar"
        style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="admin_dashboard.php" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>
            <div class="nav-menu" style="display: flex; gap: 8px;">
                <a href="admin_dashboard.php" class="nav-link nav-module-link"><i class="fa fa-arrow-left"></i> Back to
                    Dashboard</a>
                <a href="#" class="nav-link nav-module-link active"><i class="fa fa-file-invoice-dollar"></i>
                    Payroll</a>
            </div>
            <div class="nav-actions">
                <div class="user-profile" style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px;">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div style="font-size: 12px; color: var(--text-light);">Administrator</div>
                    </div>
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

                <div class="card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h3>Employee Payroll Overview</h3>
                        <span style="color: var(--text-light); font-size: 14px;">Month:
                            <?php echo date('F Y'); ?></span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid #eee; text-align: left;">
                                    <th style="padding: 12px;">Employee</th>
                                    <th style="padding: 12px;">Basic</th>
                                    <th style="padding: 12px;">HRA</th>
                                    <th style="padding: 12px;">Allowances</th>
                                    <th style="padding: 12px;">Deductions</th>
                                    <th style="padding: 12px;">Net Salary</th>
                                    <th style="padding: 12px;">Status (<?php echo date('M'); ?>)</th>
                                    <th style="padding: 12px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $users = $conn->query("SELECT u.id, u.full_name, u.email, s.basic_salary, s.hra, s.allowances, s.deductions 
                                                       FROM users u 
                                                       LEFT JOIN salary_structures s ON u.id = s.user_id 
                                                       WHERE u.role = 'employee'");
                                while ($row = $users->fetch_assoc()):
                                    $net = ($row['basic_salary'] ?? 0) + ($row['hra'] ?? 0) + ($row['allowances'] ?? 0) - ($row['deductions'] ?? 0);

                                    // Check status
                                    $m = date('n');
                                    $y = date('Y');
                                    $st_q = $conn->query("SELECT status FROM payroll_records WHERE user_id='" . $row['id'] . "' AND month='$m' AND year='$y'");
                                    $status = ($st_q->num_rows > 0) ? '<span style="color: green; font-weight: 500;">Paid</span>' : '<span style="color: orange; font-weight: 500;">Pending</span>';
                                    ?>
                                    <tr style="border-bottom: 1px solid #f5f5f5;">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 500;">
                                                <?php echo htmlspecialchars($row['full_name']); ?></div>
                                            <div style="font-size: 12px; color: #888;">
                                                <?php echo htmlspecialchars($row['email']); ?></div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php echo number_format($row['basic_salary'] ?? 0, 2); ?></td>
                                        <td style="padding: 12px;"><?php echo number_format($row['hra'] ?? 0, 2); ?></td>
                                        <td style="padding: 12px;"><?php echo number_format($row['allowances'] ?? 0, 2); ?>
                                        </td>
                                        <td style="padding: 12px;"><?php echo number_format($row['deductions'] ?? 0, 2); ?>
                                        </td>
                                        <td style="padding: 12px; font-weight: 600;"><?php echo number_format($net, 2); ?>
                                        </td>
                                        <td style="padding: 12px;"><?php echo $status; ?></td>
                                        <td style="padding: 12px;">
                                            <button class="btn btn-sm btn-secondary"
                                                onclick='openModal(<?php echo json_encode($row); ?>)'><i
                                                    class="fa fa-edit"></i> Edit</button>
                                            <?php if (strpos($status, 'Pending') !== false): ?>
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('Process payroll for <?php echo $row['full_name']; ?>?');">
                                                    <input type="hidden" name="emp_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="process_payroll" value="1">
                                                    <button type="submit" class="btn btn-sm btn-primary"><i
                                                            class="fa fa-check"></i> Pay</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="salaryModal" class="salary-modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Update Salary Structure</h3>
            <form method="POST">
                <input type="hidden" name="update_salary" value="1">
                <input type="hidden" name="emp_id" id="modal_emp_id">

                <h4 id="modal_emp_name" style="margin-bottom: 15px; color: var(--primary);"></h4>

                <div class="form-group">
                    <label>Basic Salary</label>
                    <input type="number" step="0.01" name="basic_salary" id="val_basic" required>
                </div>
                <div class="form-group">
                    <label>HRA</label>
                    <input type="number" step="0.01" name="hra" id="val_hra" required>
                </div>
                <div class="form-group">
                    <label>Allowances</label>
                    <input type="number" step="0.01" name="allowances" id="val_allow" required>
                </div>
                <div class="form-group">
                    <label>Deductions</label>
                    <input type="number" step="0.01" name="deductions" id="val_deduct" required>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(data) {
            document.getElementById('salaryModal').classList.add('active');
            document.getElementById('modal_emp_id').value = data.id;
            document.getElementById('modal_emp_name').innerText = data.full_name;
            document.getElementById('val_basic').value = data.basic_salary || 0;
            document.getElementById('val_hra').value = data.hra || 0;
            document.getElementById('val_allow').value = data.allowances || 0;
            document.getElementById('val_deduct').value = data.deductions || 0;
        }

        function closeModal() {
            document.getElementById('salaryModal').classList.remove('active');
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('salaryModal')) {
                closeModal();
            }
        }
    </script>
</body>

</html>