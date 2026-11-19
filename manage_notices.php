<?php
// manage_notices.php
// Administrator Content Management & Approval Handler for NathiSiConnected

session_start();

// 1. SECURITY & ROLE CHECK
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Administrator") {
    http_response_code(403);
    die("Access Denied: System Administrator privileges required to manage notices. <a href='dashboard.php'>Return to Dashboard</a>");
}

include "db_connect.php";

$feedback = "";

$allowedStatuses = ["Pending", "Approved", "Rejected"];


// 2. PROCESS POST MANAGEMENT REQUEST

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"]) ? trim($_POST["action"]) : "";
    $noticeId = isset($_POST["notice_id"]) ? (int)$_POST["notice_id"] : 0;

    if ($noticeId <= 0) {
        die("Error: Invalid Notice ID specified. <a href='manage_notices.php'>Go back</a>");
    }


    // --- ACTION 1: APPROVE OR DECLINE NOTICE ---

    if ($action === "update_status") {

        $status = isset($_POST["approval_status"])
            ? trim($_POST["approval_status"])
            : "";

        if (!in_array($status, $allowedStatuses, true)) {
            die("Error: Invalid approval status. <a href='manage_notices.php'>Go back</a>");
        }

        // Get the logged-in Administrator's ID
        $adminID = $_SESSION["user_id"];

        // Update Status and AdminID
        $stmt = $conn->prepare(
            "UPDATE notices
             SET Status = ?, AdminID = ?
             WHERE NoticeID = ?"
        );

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param(
            "sii",
            $status,
            $adminID,
            $noticeId
        );

        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_notices.php?notice_status=updated");
            exit();

        } else {

            die("Error updating notice status: " . $stmt->error);
        }
    }


    // --- ACTION 2: UPDATE NOTICE CONTENT ---

    elseif ($action === "edit_content") {

        $title = isset($_POST["title"])
            ? trim($_POST["title"])
            : "";

        $content = isset($_POST["content"])
            ? trim($_POST["content"])
            : "";


        if ($title === "" || $content === "") {

            die("Error: Title and content cannot be empty. <a href='javascript:history.back()'>Go back</a>");
        }


        // Use the CURRENT column names
        $stmt = $conn->prepare(
            "UPDATE notices
             SET NoticeTitle = ?, NoticeContent = ?
             WHERE NoticeID = ?"
        );

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }


        $stmt->bind_param(
            "ssi",
            $title,
            $content,
            $noticeId
        );


        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_notices.php?notice_edit=success");
            exit();

        } else {

            die("Error updating notice content: " . $stmt->error);
        }
    }


    // --- ACTION 3: DELETE NOTICE ---

    elseif ($action === "delete") {

        $stmt = $conn->prepare(
            "DELETE FROM notices
             WHERE NoticeID = ?"
        );

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("i", $noticeId);


        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_notices.php?notice_delete=success");
            exit();

        } else {

            die("Error deleting notice: " . $stmt->error);
        }
    }


    else {

        die("Error: Unrecognized admin action. <a href='manage_notices.php'>Go back</a>");
    }
}


// 3. LOAD ALL NOTICES FOR DISPLAY

$notices = $conn->query(
    "SELECT *
     FROM notices
     ORDER BY NoticeID DESC"
);


if (!$notices) {
    die("Error loading notices: " . $conn->error);
}


if (isset($_GET["notice_status"])) {
    $feedback = "Notice status updated.";
}

if (isset($_GET["notice_edit"])) {
    $feedback = "Notice content updated.";
}

if (isset($_GET["notice_delete"])) {
    $feedback = "Notice deleted.";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="style.css">

    <title>Manage Notices</title>

</head>


<body>

<?php include "header.php"; ?>


<h2>Manage Community Notices</h2>


<?php if ($feedback): ?>

    <p style="color: green;">
        <?php echo htmlspecialchars($feedback); ?>
    </p>

<?php endif; ?>


<?php if ($notices && $notices->num_rows > 0): ?>

    <div class="table-display">

        <table>
            <tr>
                <th>ID</th>
                <th>Notice Title</th>
                <th>Notice Content</th>
                <th>Date Posted</th>
                <th>Officer ID</th>
                <th>Admin ID</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>


            <?php while ($n = $notices->fetch_assoc()): ?>

                <tr>
                    <td>
                        <?php echo htmlspecialchars($n["noticeID"]); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($n["noticeTitle"]); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($n["noticeContent"]); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($n["DatePosted"]); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($n["officerID"]); ?>
                    </td>
                    <td>
                        <?php

                        if ($n["adminID"] === NULL) {
                            echo "Not reviewed";
                        } else {
                            echo htmlspecialchars($n["adminID"]);
                        }

                        ?>
                    </td>


                    <!-- Status -->

                    <td>
                        <?php echo htmlspecialchars($n["Status"]); ?>
                    </td>


                    <!-- Actions -->

                    <td>

                        <?php if ($n["Status"] === "Pending"): ?>

                            <form action="manage_notices.php" method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="notice_id" value="<?php echo (int)$n["noticeID"]; ?>">
                                <button type="submit" name="approval_status" value="Approved"> Approve </button>
                                 <button type="submit" name="approval_status" style="background-color: red; color: white;" value="Rejected"> Decline </button>
                            </form>

                        <?php endif; ?>


                        <!-- Delete -->

                        <form
                            action="manage_notices.php"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this notice?')"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="notice_id"
                                value="<?php echo (int)$n["noticeID"]; ?>"
                            >


                             <button 
        type="submit"
        style="background-color:red; color:white; border:none; padding:5px 8px; border-radius:4px; cursor:pointer; font-size:11px; margin-top:2px;">
        Delete
    </button>

                        </form>

                    </td>

                </tr>

            <?php endwhile; ?>

        </table>

    </div>


<?php else: ?>

    <p>No notices found.</p>

<?php endif; ?>


<p>
    <a href="dashboard.php">
        <button>Back to Dashboard</button>
    </a>
</p>


<?php

$conn->close();

include "footer.php";

?>

</body>

</html>