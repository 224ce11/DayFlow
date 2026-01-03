<?php
include 'db_connect.php';

// Salary Structures Table
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

// Payroll Records Table (History)
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

if ($conn->query($sql1) === TRUE) {
    echo "Table 'salary_structures' created/checked successfully.\n";
} else {
    echo "Error creating table 'salary_structures': " . $conn->error . "\n";
}

if ($conn->query($sql2) === TRUE) {
    echo "Table 'payroll_records' created/checked successfully.\n";
} else {
    echo "Error creating table 'payroll_records': " . $conn->error . "\n";
}

$conn->close();
?>
