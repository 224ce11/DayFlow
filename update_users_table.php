<?php
include 'db_connect.php';

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS designation VARCHAR(100) DEFAULT 'Employee'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS joining_date DATE DEFAULT CURRENT_DATE"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Column added or already exists.<br>";
    } else {
        // Ignore "Duplicate column name" error if it happens on some mysql versions that allow ADD COLUMN IF NOT EXISTS
        // Standard MySQL 8.0 support IF NOT EXISTS in ALTER TABLE but older might not.
        // If error contains "Duplicate", it's fine.
        if (strpos($conn->error, 'Duplicate') !== false) {
             echo "Column already exists (verified via error).<br>";
        } else {
             // Try without IF NOT EXISTS for older MySQL versions if syntax fails
             $simple_sql = str_replace("ADD COLUMN IF NOT EXISTS", "ADD COLUMN", $sql);
             if ($conn->query($simple_sql) === TRUE) {
                 echo "Column added (fallback).<br>";
             } else {
                 echo "Error/Exists: " . $conn->error . "<br>";
             }
        }
    }
}
$conn->close();
?>
