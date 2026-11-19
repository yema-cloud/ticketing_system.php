<?php
// deleteuser.php
// Called from displayusers.php as deleteuser.php?userid=123
//
// Before a user is removed from generalusers, a full copy of their row is saved
// in the deleted_users table (with the date and the admin who deleted them).
// Both steps happen inside one transaction, so either both happen or neither.

session_start();
include "db_connect.php";

// Make database errors throw exceptions so the try/catch below can catch them
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---- Only a logged-in Administrator may delete users ----
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== "Administrator") {
    http_response_code(403);
    die("Access denied. Administrator privileges required. <a href='dashboard.php'>Return to Dashboard</a>");
}

$adminId  = (int) $_SESSION['user_id'];
$targetId = isset($_GET['userid']) ? (int) $_GET['userid'] : 0;

$success = false;
$message = "";

if ($targetId <= 0) {
    $message = "Invalid user ID.";
} elseif ($targetId === $adminId) {
    $message = "You cannot delete your own account.";
} else {
    try {
        // 1. Make sure the user exists
        $stmt = $conn->prepare("SELECT Username FROM generalusers WHERE UserID = ?");
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$target) {
            throw new Exception("That user no longer exists.");
        }

        // 2. Build the list of columns to copy (works even if generalusers changes later)
        $cols = [];
        $res  = $conn->query("SHOW COLUMNS FROM generalusers");
        while ($c = $res->fetch_assoc()) {
            $cols[] = "`" . $c['Field'] . "`";
        }
        $colList = implode(", ", $cols);

        // 3. Copy the user into deleted_users, then delete from generalusers
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "INSERT INTO deleted_users ($colList, DeletedByUserID)
             SELECT $colList, ? FROM generalusers WHERE UserID = ?"
        );
        $stmt->bind_param("ii", $adminId, $targetId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM generalusers WHERE UserID = ?");
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        $success = true;
        $message = "User \"" . $target['Username'] . "\" was deleted and their record was saved in the deleted users archive.";

    } catch (Throwable $e) {
        // Undo the copy if the delete failed
        try {
            $conn->rollback();
        } catch (Throwable $ignore) {
        }
        $message = "The user was NOT deleted. " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Delete User | NathiSiConnected</title>
</head>
<body>
    <?php include "header.php"; ?>

    <main style="max-width: 700px; margin: 40px auto; padding: 0 20px; text-align: center;">
        <h2>Delete User</h2>

        <?php if ($success): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:6px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php else: ?>
            <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:6px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <p style="margin-top: 20px;">
            <a href="displayusers.php"><button type="button">Back to User Management</button></a>
        </p>
    </main>

    <?php
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>