<<<<<<< HEAD:db_connect.php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dayflow";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
=======
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dayflow";


$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
>>>>>>> 288e7d7cb167e12006cdab05d2e8860859f93ad1:Odoo x GCET/db_connect.php
