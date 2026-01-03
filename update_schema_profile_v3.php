<?php
include 'db_connect.php';

$alterQueries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS ifsc_code VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS dob DATE DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS gender ENUM('Male', 'Female', 'Other') DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS pan_number VARCHAR(20) DEFAULT NULL"
];

foreach ($alterQueries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Schema updated successfully: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Error updating schema: " . $conn->error . "\n";
    }
}

$conn->close();
?>
