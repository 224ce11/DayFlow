<?php
include 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED,
    check_in DATETIME DEFAULT NULL,
    check_out DATETIME DEFAULT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'attendance' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
$conn->close();
?>
