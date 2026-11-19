<?php
session_start();

include "db_connect.php";

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $last_logout_time = date('Y-m-d H:i:s');

    // UPDATE LOGOUT TIMESTAMP BEFORE DESTROYING SESSION
    $stmt = $conn->prepare("UPDATE generalusers SET last_logout = ? WHERE Username = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $last_logout_time, $username);
        $stmt->execute();
        $stmt->close();
    }
}

// Clear and destroy session
$_SESSION = [];
session_destroy();

header("Location: login.php");
exit();
?>