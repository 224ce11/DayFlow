<?php
include 'db_connect.php';

// Drop companies table if exists
$conn->query("DROP TABLE IF EXISTS companies");

// Remove foreign key check temporarily to allow modifying users table
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// Update users table: Remove company_id column if it exists
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'company_id'");
if ($check_col->num_rows > 0) {
    $conn->query("ALTER TABLE users DROP COLUMN company_id");
}

$conn->query("SET FOREIGN_KEY_CHECKS=1");

// Create Super Admin HR directly in Users table
$full_name = "Super Admin HR";
$email = "hr@odoo.com";
$phone = "1234567890";
$login_id = "OIHRC2025";
$password = "HrC@odoo25";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = "admin";

$check_u = $conn->prepare("SELECT id FROM users WHERE login_id = ?");
$check_u->bind_param("s", $login_id);
$check_u->execute();

if ($check_u->get_result()->num_rows > 0) {
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE login_id = ?");
    $upd->bind_param("ss", $hashed_password, $login_id);
    $upd->execute();
    echo "User updated.<br>";
} else {
    $stmt = $conn->prepare("INSERT INTO users (login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $login_id, $full_name, $email, $phone, $hashed_password, $role);
    if ($stmt->execute()) {
        echo "Super Admin Created Successfully!<br>";
    } else {
        echo "Error: " . $conn->error;
    }
}

echo "Database Update Complete: Companies table removed. <a href='login_admin.php'>Login</a>";
?>
