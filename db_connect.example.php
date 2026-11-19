<?php

// Copy this file, rename it to db_connect.php, and fill in your own details.
$host    = "your_database_host";
$db_user = "your_username";
$db_pass = "your_password_here";
$db_name = "your_database_name";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>