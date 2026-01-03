<?php
include 'db_connect.php';

echo "<h2>Fixing Database for Attendance</h2>";

// 1. Update Users table
$cols = [
    'salary' => "DECIMAL(10,2) DEFAULT 0.00",
    'designation' => "VARCHAR(100) DEFAULT 'Employee'",
    'joining_date' => "DATE DEFAULT NULL"
];

foreach ($cols as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD $col $type");
        echo "Added column $col to users table.<br>";
    } else {
        echo "Column $col already exists in users table.<br>";
    }
}

// 2. Create Attendance Table
$sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('Present', 'Absent', 'Half-day', 'Leave') DEFAULT 'Absent',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (user_id, date)
) ENGINE=InnoDB";

if ($conn->query($sql_attendance)) {
    echo "Attendance table fixed/created.<br>";
} else {
    echo "Error creating attendance table: " . $conn->error . "<br>";
}

// 3. Create Leave Requests Table
$sql_leave = "CREATE TABLE IF NOT EXISTS leave_requests (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($sql_leave)) {
    echo "Leave Requests table fixed/created.<br>";
} else {
    echo "Error creating leave_requests table: " . $conn->error . "<br>";
}

echo "<h3>Database Fix Complete!</h3>";
$conn->close();
?>
