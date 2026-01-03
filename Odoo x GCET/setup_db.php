<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection to MySQL server (without selecting DB yet)
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS dayflow";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db("dayflow");

// sql to create table
// 1. Create Companies Table
$sql = "CREATE TABLE IF NOT EXISTS companies (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL UNIQUE,
    company_code VARCHAR(10) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Table companies created successfully<br>";
} else {
    echo "Error creating companies table: " . $conn->error . "<br>";
}

// 2. Create Users Table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT(11) UNSIGNED,
    login_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- Note: Add FOREIGN KEY manually if needed, skipping strict constraint for simple setup script flexibility
)";

if ($conn->query($sql) === TRUE) {
    echo "Table users created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// 3. Setup Default Data
$result = $conn->query("SELECT * FROM companies WHERE company_name = 'Dayflow Inc'");
if ($result->num_rows == 0) {
    $conn->query("INSERT INTO companies (company_name, company_code) VALUES ('Dayflow Inc', 'DF-001')");
    $company_id = $conn->insert_id;
    
    // Create Admin linked to this company
    $password_hash = password_hash("admin123", PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (company_id, login_id, full_name, email, phone, password, role) 
            VALUES ($company_id, 'ADMIN001', 'System Admin', 'admin@dayflow.com', '1234567890', '$password_hash', 'admin')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Default admin and company created successfully.<br>";
    }
} else {
    echo "Default data already exists.<br>";
}

$conn->close();
echo "<br>Setup complete! You can now <a href='Login.php'>Login</a>.";
?>
