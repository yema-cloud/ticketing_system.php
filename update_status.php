<?php
// update_status.php
// Councillor Ticket Status Update Endpoint for NathiSiConnected
//
// GET  -> shows a form listing tickets in the councillor's own ward,
//         each with a dropdown to change its status.
// POST -> applies the status change (only if the ticket really belongs
//         to that councillor's ward) and records DateResolved.

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

$userId = $_SESSION["user_id"];

// Status labels used elsewhere in the app (reportedIncidences.php)
$statusLabels = [
    1 => "Open",
    2 => "In Progress",
    3 => "Resolved",
    4 => "Declined",
];

// Find which ward this councillor belongs to (same lookup reportedIncidences.php uses)
$wardStmt = $conn->prepare("SELECT Ward FROM generalusers WHERE UserID = ?");
$wardStmt->bind_param("i", $userId);
$wardStmt->execute();
$wardRow = $wardStmt->get_result()->fetch_assoc();
$wardStmt->close();
$councillorWard = $wardRow ? intval($wardRow["Ward"]) : 0;

$feedback = "";

// 2. PROCESS POST UPDATE REQUEST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ticketId  = isset($_POST["ticket_id"]) ? (int)$_POST["ticket_id"] : 0;
    $newStatus = isset($_POST["status"]) ? (int)$_POST["status"] : 0;

    if ($ticketId <= 0 || !isset($statusLabels[$newStatus])) {
        die("Error: Invalid ticket ID or status selection. <a href='update_status.php'>Go Back</a>");
    }

    // Only allow updating a ticket that is actually in this councillor's own ward.
    // DateResolved is stamped the first time the ticket becomes Resolved (3)
    // and cleared if the ticket moves to any other status.
    $stmt = $conn->prepare(
        "UPDATE municipal_fault_tickets
         SET StatusID = ?,
             DateResolved = CASE WHEN ? = 3 THEN COALESCE(DateResolved, CURDATE()) ELSE NULL END
         WHERE TicketID = ? AND WardID_municipal = ?"
    );
    $stmt->bind_param("iiii", $newStatus, $newStatus, $ticketId, $councillorWard);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $feedback = "Ticket #" . $ticketId . " updated to \"" . $statusLabels[$newStatus] . "\".";
    } else {
        $feedback = "No matching ticket found in your ward, or the status was unchanged.";
    }

    $stmt->close();
}

// 3. LOAD TICKETS FOR THIS COUNCILLOR'S WARD (shown on both GET and after a POST)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Update Ticket Status</title>
</head>
<body>
    <?php include "header.php"; ?>

    <div>
        <h2>Update Ticket Status - Ward <?php echo htmlspecialchars($councillorWard); ?></h2>

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
                        <th>New Status</th>
                    </tr>
                    <?php while ($row = $tickets->fetch_assoc()): ?>
                        <tr>
                            <form action="update_status.php" method="POST">
                                <td><?php echo htmlspecialchars($row["TicketID"]); ?></td>
                                <td><?php echo htmlspecialchars($row["ServiceName"] ?? $row["Title"]); ?></td>
                                <td><?php echo htmlspecialchars($row["Description"]); ?></td>
                                <td><?php echo htmlspecialchars($row["Location"]); ?></td>
                                <td><?php echo htmlspecialchars($statusLabels[$row["StatusID"]] ?? "Unknown"); ?></td>
                                <td>
                                    <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($row["TicketID"]); ?>">
                                    <select name="status">
                                        <?php foreach ($statusLabels as $id => $label): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ((int)$row["StatusID"] === $id) ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Save</button>
                                </td>
                            </form>
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