<?php
// comments.php
// Councillor Ticket Comments for NathiSiConnected
//
// GET  -> lists tickets in the councillor's own ward, each with its existing
//         comments and a box to add a new one.
// POST -> saves the comment (only if the ticket really belongs to that
//         councillor's ward).

session_start();

// 1. SECURITY & ROLE CHECK: Strictly restrict access to logged-in Councillors
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Councillor") {
    http_response_code(403);
    die("Access Denied: Councillor privileges required. <a href='dashboard.php'>Return to Dashboard</a>");
}

include "db_connect.php";

$userId = (int) $_SESSION["user_id"];

// Status labels used elsewhere in the app (update_status.php, reportedIncidences.php)
$statusLabels = [
    1 => "Open",
    2 => "In Progress",
    3 => "Resolved",
    4 => "Declined",
];

// Simple CSRF token
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(16));
}

// Find which ward this councillor belongs to (same lookup update_status.php uses)
$wardStmt = $conn->prepare("SELECT Ward FROM generalusers WHERE UserID = ?");
$wardStmt->bind_param("i", $userId);
$wardStmt->execute();
$wardRow = $wardStmt->get_result()->fetch_assoc();
$wardStmt->close();
$councillorWard = $wardRow ? intval($wardRow["Ward"]) : 0;

$feedback = "";

if (isset($_SESSION["flash"])) {
    $feedback = $_SESSION["flash"];
    unset($_SESSION["flash"]);
}

// 2. PROCESS POST REQUEST 
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ticketId    = isset($_POST["ticket_id"]) ? (int) $_POST["ticket_id"] : 0;
    $commentText = trim($_POST["comment"] ?? "");

    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $feedback = "Invalid request. Please try again.";
    } elseif ($ticketId <= 0 || $commentText === "") {
        $feedback = "Please write a comment before saving.";
    } elseif (mb_strlen($commentText) > 1000) {
        $feedback = "Comments can be up to 1000 characters.";
    } else {
        // Only saves if the ticket is actually in this councillor's own ward
        $stmt = $conn->prepare(
            "INSERT INTO comments (ticketID, councillorID, commentText, commentDate)
             SELECT TicketID, ?, ?, NOW()
             FROM municipal_fault_tickets
             WHERE TicketID = ? AND WardID_municipal = ?"
        );
        $stmt->bind_param("isii", $userId, $commentText, $ticketId, $councillorWard);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $stmt->close();
            $_SESSION["flash"] = "Comment added to ticket #" . $ticketId . ".";
            // Reload so refreshing doesn't resubmit the form
            header("Location: comments.php");
            exit();
        }

        $feedback = "No matching ticket found in your ward, so the comment was not saved.";
        $stmt->close();
    }
}

// 3. LOAD TICKETS FOR THIS COUNCILLOR'S WARD
$listStmt = $conn->prepare(
    "SELECT t.TicketID, t.Title, t.Description, t.Location, t.StatusID, s.ServiceName
     FROM municipal_fault_tickets t
     LEFT JOIN municipal_services s ON t.ServiceID = s.ServiceID
     WHERE t.WardID_municipal = ?
     ORDER BY t.DateReported DESC"
);
$listStmt->bind_param("i", $councillorWard);
$listStmt->execute();
$tickets = $listStmt->get_result();

// 4. LOAD COMMENTS FOR THIS WARD'S TICKETS, GROUPED BY TICKET
$comments = [];
$commentStmt = $conn->prepare(
    "SELECT c.ticketID, c.commentText, c.commentDate, u.Username
     FROM comments c
     JOIN municipal_fault_tickets t ON t.TicketID = c.ticketID
     LEFT JOIN generalusers u ON u.UserID = c.councillorID
     WHERE t.WardID_municipal = ?
     ORDER BY c.commentDate ASC"
);
$commentStmt->bind_param("i", $councillorWard);
$commentStmt->execute();
$commentResult = $commentStmt->get_result();
while ($c = $commentResult->fetch_assoc()) {
    $comments[$c["ticketID"]][] = $c;
}
$commentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Comment on Tickets</title>
    <style>
        /* Let the table scroll sideways instead of cutting off the last column */
        .table-display { overflow-x: auto; }

        /* Comment box with the Save button stacked underneath it */
        .table-display .comment-form textarea {
            display: block;
            width: 100%;
            min-width: 180px;
            max-width: 260px;
            min-height: 60px;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .table-display .comment-form button {
            display: block;
            width: auto;
            margin: 8px auto 0;
            padding: 6px 16px;
            background: #25a85a;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .table-display .comment-form button:hover { background: #1f9250; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <div>
        <h2>Comment on Tickets - Ward <?php echo htmlspecialchars($councillorWard); ?></h2>

        <?php if ($feedback): ?>
            <p><?php echo htmlspecialchars($feedback); ?></p>
        <?php endif; ?>

        <?php if ($tickets && $tickets->num_rows > 0): ?>
            <div class="table-display">
                <table>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Current Status</th>
                        <th>Comments</th>
                        <th>Add Comment</th>
                    </tr>
                    <?php while ($row = $tickets->fetch_assoc()): ?>
                        <?php $id = (int) $row["TicketID"]; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["TicketID"]); ?></td>
                            <td><?php echo htmlspecialchars($row["ServiceName"] ?? $row["Title"]); ?></td>
                            <td><?php echo htmlspecialchars($row["Description"]); ?></td>
                            <td><?php echo htmlspecialchars($row["Location"]); ?></td>
                            <td><?php echo htmlspecialchars($statusLabels[$row["StatusID"]] ?? "Unknown"); ?></td>
                            <td style="text-align:left;">
                                <?php if (!empty($comments[$id])): ?>
                                    <?php foreach ($comments[$id] as $c): ?>
                                        <div style="margin-bottom:8px;">
                                            <?php echo nl2br(htmlspecialchars($c["commentText"])); ?><br>
                                            <small style="color:#777;">
                                                <?php echo htmlspecialchars($c["Username"] ?? "Councillor"); ?>,
                                                <?php echo htmlspecialchars($c["commentDate"]); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em>No comments yet</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form class="comment-form" action="comments.php" method="POST">
                                    <input type="hidden" name="csrf" value="<?php echo $_SESSION["csrf"]; ?>">
                                    <input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
                                    <textarea name="comment" maxlength="1000" placeholder="Write a comment..." required></textarea>
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <p>No tickets found for your ward.</p>
        <?php endif; ?>

        <p><a href="dashboard.php"><button>Back to Dashboard</button></a></p>
    </div>

    <?php
    $listStmt->close();
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>