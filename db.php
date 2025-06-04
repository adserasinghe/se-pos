<?php
// Update these with your database credentials
$host = "localhost";
$user = "root";
$pass = "";
$db = "se_pos";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>