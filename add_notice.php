<?php
// add_notice.php
// Communications Officer - Create a new community/ward notice.
// New notices are saved as "Draft" and must be approved by an
// Administrator (see manage_notices.php) before the public can see them.

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Officer") {
    http_response_code(403);
    die("Access Denied: Communications Officer privileges required. <a href='dashboard.php'>Return to Dashboard</a>");
}

include "db_connect.php";

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title    = trim($_POST["title"] ?? "");
    $content  = trim($_POST["content"] ?? "");
    // ward_id = 0 means "community-wide" (no specific ward)

    if ($title === ""  || $content === "") {
        $errorMessage = "Title and content are all required.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO notices (NoticeTitle, NoticeContent, OfficerID, Status, noticeLevel)
             VALUES (?, ?, ?, 'Pending', 'community')"
        );

        if (!$stmt) {
            die("SQL error: " . $conn->error);
        }

        $stmt->bind_param("ssi", $title, $content, $_SESSION["user_id"]);

        if ($stmt->execute()) {
            $successMessage = "Notice submitted as a draft. An Administrator must approve it before it becomes public.";
            $stmt->close();
        } else {
            $errorMessage = "Something went wrong: " . $stmt->error;
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Add Community Notice</title>
</head>
<body>
    <?php include "header.php"; ?>

    <div class="form-body">
        <h2>Add a Community Notice</h2>

        <?php if ($errorMessage): ?>
            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <p style="color: green;"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

    <form action="add_notice.php" method="POST" class="signup-form">

        <div class="form-section">

            <p class="form-section-title">Notice Information</p>

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group align-top">
                <label for="content">Content</label>
                <textarea id="content" name="content" rows="5" required></textarea>
            </div>

        </div>

        <div class="form-buttons">
            <button type="reset" class="btn-secondary">Clear Form</button>
            <button type="submit">Submit Notice</button>
        </div>

    </form>

</div>
<p><a href="dashboard.php"><button>Back to Dashboard</button></a></p>
    </div>

    <?php include "footer.php"; ?>
</body>
</html>