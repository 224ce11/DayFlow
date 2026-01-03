<?php
include 'db_connect.php';

// Enable error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. Create Salary Structures Table
    $sql1 = "CREATE TABLE IF NOT EXISTS salary_structures (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        basic_salary DECIMAL(10,2) DEFAULT 0.00,
        hra DECIMAL(10,2) DEFAULT 0.00,
        allowances DECIMAL(10,2) DEFAULT 0.00,
        deductions DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_salary (user_id)
    )";
    $conn->query($sql1);
    echo "Table 'salary_structures' checked/created.<br>";

    // 2. Create Payroll Records Table
    $sql2 = "CREATE TABLE IF NOT EXISTS payroll_records (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        month INT(2) NOT NULL,
        year INT(4) NOT NULL,
        basic_salary DECIMAL(10,2) NOT NULL,
        hra DECIMAL(10,2) NOT NULL,
        allowances DECIMAL(10,2) NOT NULL,
        deductions DECIMAL(10,2) NOT NULL,
        net_pay DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'paid') DEFAULT 'pending',
        payment_date DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql2);
    echo "Table 'payroll_records' checked/created.<br>";

    // 3. Insert Sample Data for Employees
    echo "<h3>Populating Sample Data...</h3>";
    $employees = $conn->query("SELECT id, full_name FROM users WHERE role = 'employee'");

    if ($employees->num_rows > 0) {
        while ($emp = $employees->fetch_assoc()) {
            $uid = $emp['id'];

            // Check if salary structure exists
            $check = $conn->query("SELECT id FROM salary_structures WHERE user_id = $uid");
            if ($check->num_rows == 0) {
                // Insert default salary structure
                $basic = 5000 + (rand(1, 10) * 100);
                $hra = $basic * 0.4;
                $allow = 1500;
                $deduct = 200;

                $stmt = $conn->prepare("INSERT INTO salary_structures (user_id, basic_salary, hra, allowances, deductions) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("idddd", $uid, $basic, $hra, $allow, $deduct);
                $stmt->execute();
                echo "Added salary structure for {$emp['full_name']}<br>";

                // Insert a past payroll record (Paid)
                $net = $basic + $hra + $allow - $deduct;
                $m = date('n') - 1; // Last month
                $y = date('Y');
                if ($m < 1) {
                    $m = 12;
                    $y--;
                }

                $stmt2 = $conn->prepare("INSERT INTO payroll_records (user_id, month, year, basic_salary, hra, allowances, deductions, net_pay, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())");
                $stmt2->bind_param("iiiddddd", $uid, $m, $y, $basic, $hra, $allow, $deduct, $net);
                $stmt2->execute();
                echo "Added past payroll record for {$emp['full_name']}<br>";
            } else {
                echo "Salary structure already exists for {$emp['full_name']}, skipping.<br>";
            }
        }
    } else {
        echo "No employees found to add sample data.<br>";
    }

    echo "<h2 style='color:green'>Database Setup & Population Completed Successfully!</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}

$conn->close();
?>