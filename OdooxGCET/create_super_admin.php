<?php
include 'db_connect.php';

$company_name = "Odoo HR Corp"; // Example Company
$company_code = "OHC-2025";
$full_name = "Super Admin HR";
$email = "hr@odoo.com";
$phone = "1234567890";
$login_id = "OIHRC2025";
$password = "HrC@odoo25";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = "admin"; // HR is Admin in this context

// 1. Create Company if not exists
$check_c = $conn->prepare("SELECT id FROM companies WHERE company_name = ?");
$check_c->bind_param("s", $company_name);
$check_c->execute();
$res = $check_c->get_result();

if ($res->num_rows > 0) {
    $company_id = $res->fetch_assoc()['id'];
    echo "Company already exists. ID: $company_id<br>";
} else {
    $ins_c = $conn->prepare("INSERT INTO companies (company_name, company_code) VALUES (?, ?)");
    $ins_c->bind_param("ss", $company_name, $company_code);
    if ($ins_c->execute()) {
        $company_id = $conn->insert_id;
        echo "Company created. ID: $company_id<br>";
    } else {
        die("Error creating company: " . $conn->error);
    }
}

// 2. Create User (Super Admin / HR)
// Convert Login ID to uppercase just to be sure, though user requested specific case
$login_id_insert = strtoupper($login_id); 

$check_u = $conn->prepare("SELECT id FROM users WHERE login_id = ?");
$check_u->bind_param("s", $login_id_insert);
$check_u->execute();

if ($check_u->get_result()->num_rows > 0) {
    // Update password if exists
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE login_id = ?");
    $upd->bind_param("ss", $hashed_password, $login_id_insert);
    $upd->execute();
    echo "User $login_id_insert already exists. Password updated.<br>";
} else {
    $stmt = $conn->prepare("INSERT INTO users (company_id, login_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $company_id, $login_id_insert, $full_name, $email, $phone, $hashed_password, $role);
    
    if ($stmt->execute()) {
        echo "Super Admin Created Successfully!<br>";
    } else {
        echo "Error creating user: " . $conn->error;
    }
}

echo "<hr>";
echo "<strong>Credentials:</strong><br>";
echo "Login ID: $login_id_insert<br>";
echo "Password: $password<br>";
echo "<br><a href='login_admin.php'>Go to Admin Login</a>";
?>
