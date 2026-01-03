<?php
include 'db_connect.php';

$alterQueries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS resume_path VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(15,2) DEFAULT 0.00",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS home_address TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS private_email VARCHAR(100) DEFAULT NULL"
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
