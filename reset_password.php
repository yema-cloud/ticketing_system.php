<?php
// reset_password.php
// Verifies a username exists, then updates that account's password.
// NOTE: on its own, this lets anyone who knows a username reset that
// account's password - see the security flag at the end before using
// this in anything beyond a class project.

session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Error: Invalid request method.");
}

$username        = isset($_POST["username"])        ? trim($_POST["username"])        : "";
$newPassword     = isset($_POST["new_password"])     ? $_POST["new_password"]          : "";
$confirmPassword = isset($_POST["confirm_password"]) ? $_POST["confirm_password"]       : "";

if (
    !isset($_SESSION["password_reset_verified_user"]) ||
    $_SESSION["password_reset_verified_user"] !== $username
) {
    die("Error: Identity not verified. Please answer your security question first. <a href='forgotPasswordForm.html'>Go back</a>");
}

// --- Basic validation (mirrors the checks already done in the browser,
//     but never trust the browser alone)

if ($username === "" || $newPassword === "" || $confirmPassword === "") {
    die("Error: All fields are required. <a href='javascript:history.back()'>Go back</a>");
}

if ($newPassword !== $confirmPassword) {
    die("Error: Passwords do not match. <a href='javascript:history.back()'>Go back</a>");
}

if (strlen($newPassword) < 8) {
    die("Error: Password must be at least 8 characters. <a href='javascript:history.back()'>Go back</a>");
}


// --- Confirm the username actually exists ---

$stmt = $conn->prepare("SELECT UserID FROM generalusers WHERE Username = ?");

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    die("Error: No account found for that username. <a href='javascript:history.back()'>Go back</a>");
}

$stmt->bind_result($userID);
$stmt->fetch();
$stmt->close();


// --- Hash and save the new password ---
// password_hash() salts and hashes automatically 

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$updateStmt = $conn->prepare("UPDATE generalusers SET Password = ? WHERE UserID = ?");

if (!$updateStmt) {
    die("SQL Error: " . $conn->error);
}

$updateStmt->bind_param("si", $hashedPassword, $userID);

if ($updateStmt->execute()) {
    $updateStmt->close();
    $conn->close();
    unset($_SESSION["password_reset_verified_user"]);
    header("Location: login.php?reset=success");
    exit();
} else {
    die("Error resetting password: " . $updateStmt->error);
}
?>