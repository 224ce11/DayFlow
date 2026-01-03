<?php
include 'db_connect.php';

$alterQueries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) DEFAULT 'Gandhinagar, IN'"
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
