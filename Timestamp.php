<?php
session_start();
include "db_connect.php"; // Make sure this has your DB connection

// Ensure only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Administrator') {
    header("Location: login.php");
    exit();
}

// Fetch all users with their last login/logout times
$query = "SELECT username, role, last_login, last_logout FROM users";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<link rel="stylesheet" href="style.css">
<title>User Login/Logout Timestamp Monitor</title>
<style>
  table { border-collapse: collapse; width: 80%; margin: 20px auto; }
  th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
</style>
</head>
<body>
<h2 style="text-align:center;">User Login and Logout Times</h2>
<table>
  <tr>
    <th>Username</th>
    <th>Role</th>
    <th>Last Login</th>
    <th>Last Logout</th>
  </tr>
  <?php while($row = mysqli_fetch_assoc($result)): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['username']); ?></td>
      <td><?php echo htmlspecialchars($row['role']); ?></td>
      <td><?php echo $row['last_login'] ? htmlspecialchars($row['last_login']) : 'N/A'; ?></td>
      <td><?php echo $row['last_logout'] ? htmlspecialchars($row['last_logout']) : 'N/A'; ?></td>
    </tr>
  <?php endwhile; ?>
</table>
</body>
</html>