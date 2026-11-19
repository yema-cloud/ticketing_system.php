<?php
// verify_security_answer.php
// Checks the submitted answer against the stored hash. On success, marks
// the session as verified for THAT username only - this is what stops
// someone skipping straight to reset_password.php without proving identity.

session_start();
header("Content-Type: application/json");
include "db_connect.php";

$username    = isset($_POST["username"])    ? trim($_POST["username"])    : "";
$questionKey = isset($_POST["questionKey"]) ? trim($_POST["questionKey"]) : "";
$answer      = isset($_POST["answer"])      ? trim($_POST["answer"])      : "";

if ($username === "" || $questionKey === "" || $answer === "") {
    echo json_encode(["status" => "error", "message" => "Missing fields."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT sq.AnswerHash
     FROM security_questions sq
     JOIN generalusers gu ON gu.UserID = sq.UserID
     WHERE gu.Username = ? AND sq.QuestionKey = ?"
);
$stmt->bind_param("ss", $username, $questionKey);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "fail"]);
    exit();
}

$row = $result->fetch_assoc();

// Same strtolower() used at signup, so casing differences don't cause a false failure
if (password_verify(strtolower($answer), $row["AnswerHash"])) {
    // Mark this specific username as verified for this browser session.
    // reset_password.php checks this before allowing the actual reset.
    $_SESSION["password_reset_verified_user"] = $username;
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "fail"]);
}

$stmt->close();
$conn->close();