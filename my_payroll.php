<?php
session_start();
include 'db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: Login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payroll - Dayflow</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <nav class="navbar"
        style="position: fixed; width: 100%; top: 0; z-index: 1000; background: var(--glass-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--card-border); height: 80px;">
        <div class="container nav-container" style="max-width: 100%; padding: 0 32px;">
            <div class="logo-wrapper">
                <a href="employee_dashboard.php" class="logo">
                    <div class="logo-icon"><i class="fa fa-layer-group"></i></div>
                    <span>Dayflow</span>
                </a>
            </div>
            <div class="nav-menu" style="display: flex; gap: 8px;">
                <a href="employee_dashboard.php" class="nav-link"><i class="fa fa-arrow-left"></i> Dashboard</a>
                <a href="#" class="nav-link active"><i class="fa fa-file-invoice-dollar"></i> My Payroll</a>
            </div>
            <div class="nav-actions">
                <div class="user-profile" style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-weight: 600; font-size: 14px;">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-light);">Employee</div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <main class="main-content" style="margin-left: 0; width: 100%;">
            <div class="dash-container" style="padding: 32px; max-width: 1000px; margin: 0 auto;">

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">

                    <!-- Salary Structure Card -->
                    <div class="card">
                        <h3 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Salary
                            Structure</h3>
                        <?php
                        $s_query = $conn->query("SELECT * FROM salary_structures WHERE user_id = '$user_id'");
                        if ($s_query->num_rows > 0):
                            $struct = $s_query->fetch_assoc();
                            $net_est = $struct['basic_salary'] + $struct['hra'] + $struct['allowances'] - $struct['deductions'];
                            ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">Basic Salary</span>
                                    <span style="font-weight: 600;">
                                        <?php echo number_format($struct['basic_salary'], 2); ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">HRA</span>
                                    <span style="font-weight: 600;">
                                        <?php echo number_format($struct['hra'], 2); ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-light);">Allowances</span>
                                    <span style="font-weight: 600;">
                                        <?php echo number_format($struct['allowances'], 2); ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; color: var(--error);">
                                    <span>Deductions</span>
                                    <span style="font-weight: 600;">-
                                        <?php echo number_format($struct['deductions'], 2); ?>
                                    </span>
                                </div>
                                <div
                                    style="border-top: 1px solid #eee; margin-top: 8px; padding-top: 12px; display: flex; justify-content: space-between; font-size: 18px;">
                                    <span style="color: var(--primary); font-weight: 700;">Net Salary</span>
                                    <span style="color: var(--primary); font-weight: 700;">
                                        <?php echo number_format($net_est, 2); ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-light); font-style: italic;">Salary structure not yet defined.
                                Contact HR.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Payslip History -->
                    <div class="card">
                        <h3 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Payment
                            History</h3>
                        <?php
                        $hist = $conn->query("SELECT * FROM payroll_records WHERE user_id = '$user_id' ORDER BY year DESC, month DESC");
                        if ($hist->num_rows > 0):
                            ?>
                            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                                <thead>
                                    <tr style="color: var(--text-light); font-size: 14px; border-bottom: 1px solid #eee;">
                                        <th style="padding: 10px;">Month</th>
                                        <th style="padding: 10px;">Net Pay</th>
                                        <th style="padding: 10px;">Paid On</th>
                                        <th style="padding: 10px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $hist->fetch_assoc()):
                                        $monthName = date('F', mktime(0, 0, 0, $row['month'], 10));
                                        ?>
                                        <tr style="border-bottom: 1px solid #f5f5f5;">
                                            <td style="padding: 12px; font-weight: 500;">
                                                <?php echo $monthName . ' ' . $row['year']; ?>
                                            </td>
                                            <td style="padding: 12px; font-weight: 600;">
                                                <?php echo number_format($row['net_pay'], 2); ?>
                                            </td>
                                            <td style="padding: 12px; color: var(--text-light); font-size: 14px;">
                                                <?php echo ($row['payment_date']) ? date('M j, Y', strtotime($row['payment_date'])) : '-'; ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <span class="system-status"
                                                    style="background: rgba(var(--success-rgb), 0.1); color: var(--success); text-transform: capitalize;">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 20px; text-align: center; color: var(--text-light);">No payment history
                                found.</p>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </main>
    </div>
</body>

</html>