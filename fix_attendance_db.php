<?php
include 'db_connect.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        date DATE NOT NULL,
        check_in_time TIME,
        check_out_time TIME,
        status ENUM('Present', 'Absent', 'Half-day', 'Leave') DEFAULT 'Absent',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (user_id, date)
    )",
    "CREATE TABLE IF NOT EXISTS leave_requests (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        reason TEXT,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created/checked successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}
$conn->close();
?>
