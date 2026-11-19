<?php
// manage_events.php
// Administrator Content Management & Approval Handler for NathiSiConnected

session_start();

// 1. SECURITY & ROLE CHECK
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Administrator") {
    http_response_code(403);
    die("Access Denied: System Administrator privileges required to manage events. <a href='dashboard.php'>Return to Dashboard</a>");
}

include "db_connect.php";

$feedback = "";

$allowedStatuses = ["Pending", "Accepted", "Declined"];


// 2. PROCESS POST MANAGEMENT REQUEST

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"]) ? trim($_POST["action"]) : "";
    $eventId = isset($_POST["event_id"]) ? (int)$_POST["event_id"] : 0;

    if ($eventId <= 0) {
        die("Error: Invalid Event ID specified. <a href='manage_events.php'>Go back</a>");
    }


    // --- ACTION 1: APPROVE OR DECLINE EVENT ---

    if ($action === "update_status") {

        $status = isset($_POST["approval_status"])
            ? trim($_POST["approval_status"])
            : "";

        if (!in_array($status, $allowedStatuses, true)) {
            die("Error: Invalid approval status. <a href='manage_events.php'>Go back</a>");
        }

        // Only when an admin approves do we stamp dateposted with "now".
        // That's the moment the event is supposed to start showing on the
        // homepage, not the moment it was originally submitted - so we
        // deliberately don't touch dateposted for Pending/Declined.
        if ($status === "Accepted") {

            $stmt = $conn->prepare(
                "UPDATE events
                 SET status = ?, dateposted = NOW()
                 WHERE eventID = ?"
            );

        } else {

            $stmt = $conn->prepare(
                "UPDATE events
                 SET status = ?
                 WHERE eventID = ?"
            );
        }

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("si", $status, $eventId);

        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_events.php?event_status=updated");
            exit();

        } else {

            die("Error updating event status: " . $stmt->error);
        }
    }


    // --- ACTION 2: UPDATE EVENT CONTENT ---

    elseif ($action === "edit_content") {

        $title       = isset($_POST["eventTitle"])       ? trim($_POST["eventTitle"])       : "";
        $description = isset($_POST["eventDescription"]) ? trim($_POST["eventDescription"]) : "";
        $eventDate   = isset($_POST["eventDate"])         ? trim($_POST["eventDate"])         : "";
        $eventTime   = isset($_POST["eventTime"])         ? trim($_POST["eventTime"])         : "";
        $location    = isset($_POST["eventLocation"])     ? trim($_POST["eventLocation"])     : "";

        if ($title === "" || $description === "") {
            die("Error: Title and description cannot be empty. <a href='javascript:history.back()'>Go back</a>");
        }

        $stmt = $conn->prepare(
            "UPDATE events
             SET eventTitle = ?, eventDescription = ?, eventDate = ?, eventTime = ?, eventLocation = ?
             WHERE eventID = ?"
        );

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssi",
            $title,
            $description,
            $eventDate,
            $eventTime,
            $location,
            $eventId
        );

        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_events.php?event_edit=success");
            exit();

        } else {

            die("Error updating event content: " . $stmt->error);
        }
    }


    // --- ACTION 3: DELETE EVENT ---

    elseif ($action === "delete") {

        $stmt = $conn->prepare(
            "DELETE FROM events
             WHERE eventID = ?"
        );

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("i", $eventId);

        if ($stmt->execute()) {

            $stmt->close();
            $conn->close();

            header("Location: manage_events.php?event_delete=success");
            exit();

        } else {

            die("Error deleting event: " . $stmt->error);
        }
    }


    else {

        die("Error: Unrecognized admin action. <a href='manage_events.php'>Go back</a>");
    }
}


// 3. LOAD ALL EVENTS FOR DISPLAY

$events = $conn->query(
    "SELECT *
     FROM events
     ORDER BY eventID DESC"
);


if (!$events) {
    die("Error loading events: " . $conn->error);
}


if (isset($_GET["event_status"])) {
    $feedback = "Event status updated.";
}

if (isset($_GET["event_edit"])) {
    $feedback = "Event content updated.";
}

if (isset($_GET["event_delete"])) {
    $feedback = "Event deleted.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Manage Events</title>
</head>

<body>

<?php include "header.php"; ?>

<h2>Manage Community Events</h2>

<?php if ($feedback): ?>
    <p style="color: green;">
        <?php echo htmlspecialchars($feedback); ?>
    </p>
<?php endif; ?>

<?php if ($events && $events->num_rows > 0): ?>

    <div class="table-display">

        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
                <th>Time</th>
                <th>Location</th>
                <th>Poster</th>
                <th>Date Posted</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

            <?php while ($e = $events->fetch_assoc()): ?>

                <tr>
                    <td><?php echo htmlspecialchars($e["eventID"]); ?></td>
                    <td><?php echo htmlspecialchars($e["eventTitle"]); ?></td>
                    <td><?php echo htmlspecialchars($e["eventDescription"]); ?></td>
                    <td><?php echo htmlspecialchars($e["eventDate"]); ?></td>
                    <td><?php echo htmlspecialchars($e["eventTime"]); ?></td>
                    <td><?php echo htmlspecialchars($e["eventLocation"]); ?></td>

                    <td>
                        <?php if (!empty($e["posterImage"])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($e["posterImage"]); ?>"
                                 alt="Event poster" style="max-width:80px;">
                        <?php else: ?>
                            No poster
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php
                        if ($e["dateposted"] === NULL) {
                            echo "Not yet posted";
                        } else {
                            echo htmlspecialchars($e["dateposted"]);
                        }
                        ?>
                    </td>

                    <td><?php echo htmlspecialchars($e["status"]); ?></td>

                    <td>

                        <?php if ($e["status"] === "Pending"): ?>

                            <form action="manage_events.php" method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="event_id" value="<?php echo (int)$e["eventID"]; ?>">
                                <button type="submit" name="approval_status" value="Accepted" style="background:#2ecc71; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;"> Approve </button>
                                <button type="submit" name="approval_status" value="Declined" style="background:#7f8c8d; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;"> Decline </button>
                            </form>

                        <?php endif; ?>

                        <form
                            action="manage_events.php"
                            method="POST"
                            style="display:inline"
                            onsubmit="return confirm('Delete this event?')"
                        >
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?php echo (int)$e["eventID"]; ?>">
                            <button type="submit" style="background:red; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Delete</button>
                        </form>

                    </td>

                </tr>

            <?php endwhile; ?>

        </table>

    </div>

<?php else: ?>

    <p>No events found.</p>

<?php endif; ?>

<p>
    <a href="dashboard.php">
        <button type="button" style="background-color: #2ecc71; color: white; border: none; padding: 12px 24px; border-radius: 5px; font-weight: bold; cursor: pointer;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </button>
    </a>
</p>

<?php

$conn->close();

include "footer.php";

?>

</body>

</html>