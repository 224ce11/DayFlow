<?php
include 'db_connect.php';

$tables = ['users', 'attendance', 'leave_requests'];

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>" . implode("</td><td>", $row) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "Error: Table $table does not exist.<br>";
    }
}
?>
