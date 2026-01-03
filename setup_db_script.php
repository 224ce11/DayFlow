<?php
include 'db_connect.php';

// Read the SQL file
$sql = file_get_contents('database_setup.sql');

// Execute multi-query
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // Check if there are more results
    } while ($conn->next_result());
    echo "Database setup executed successfully.";
} else {
    echo "Error executing database setup: " . $conn->error;
}

// Add columns manually if they don't exist (multi_query might fail on existing tables if not handled perfectly in SQL)
// We use a safe approach here to ensure columns exist even if table was already there
$alter_queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS designation VARCHAR(100) DEFAULT 'Employee'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS joining_date DATE DEFAULT NULL"
];

foreach ($alter_queries as $q) {
    if (!$conn->query($q)) {
        // Ignore duplicate column errors, report others
        if (strpos($conn->error, 'Duplicate') === false) {
             // echo "Column alter warning: " . $conn->error . "<br>";
        }
    }
}

$conn->close();
?>
