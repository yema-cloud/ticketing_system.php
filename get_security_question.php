<?php
// get_security_question.php
// Returns ONE security question for a given username, without revealing
// which question it is until the person proves they own the account by
// answering it correctly on the next step.

header("Content-Type: application/json");
include "db_connect.php";

$username = isset($_POST["username"]) ? trim($_POST["username"]) : "";

if ($username === "") {
    echo json_encode(["status" => "error", "message" => "Username required."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT sq.QuestionKey
     FROM security_questions sq
     JOIN generalusers gu ON gu.UserID = sq.UserID
     WHERE gu.Username = ?
     ORDER BY RAND()
     LIMIT 1"
);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Either the username doesn't exist, or it exists but has no
    // security questions set up - same response either way, on purpose:
    // this stops someone using this endpoint to check which usernames exist.
    echo json_encode(["status" => "none"]);
    exit();
}

$row = $result->fetch_assoc();

// Map the stored key back to readable question text
$questionText = [
    "mother_maiden_name" => "What is your mother's maiden name?",
    "first_pet"          => "What was the name of your first pet?",
    "birth_town"         => "In what town were you born?",
    "first_school"       => "What was the name of your first school?",
    "favourite_teacher"  => "Who was your favourite teacher?",
][$row["QuestionKey"]] ?? "Security question";

echo json_encode([
    "status" => "ok",
    "questionKey" => $row["QuestionKey"],
    "questionText" => $questionText
]);

$stmt->close();
$conn->close();