<?php
// reportedIncidences.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include "db_connect.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role   = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Optional search: lets a councillor look up one ticket by its ID
$searchTicketId = (isset($_GET['ticket_id']) && $_GET['ticket_id'] !== '')
    ? (int) $_GET['ticket_id']
    : null;

// Services (ID => full name)
$services = [
    1 => "Makana Local Municipality Electricity Department",
    2 => "Local Eskom Contact Centre",
    3 => "Makana Fire & Rescue Services",
    4 => "Public protector South Africa",
    5 => "Makana Local Municipality infrastructure",
    6 => "SPCA Grahamstown",
    7 => "Makana Local Municipality Human Settlements and Land Directorate",
    8 => "Community and social services",
    9 => "Water and Sanitation department",
];

// Short labels for the narrow dropdown
$shortNames = [
   1 => "Makana Electricity Department",
    2 => "Local Eskom Contact Centre",
    3 => "Makana Fire & Rescue Services",
    4 => "Public protector South Africa",
    5 => "Makana infrastructure",
    6 => "SPCA Grahamstown",
    7 => "Makana Human Settlements and Land Directorate",
    8 => "Community and social services",
    9 => "Water and Sanitation department"
];

// Ticket type (Title) => suggested ServiceID.
// This only marks a suggestion in the dropdown. It never assigns anything by itself.
$typeToService = [
    "Roads and Public Works"         => 5,  // Infrastructure
    "Waste and Environment"          => 8,  // Community Services
    "Water and Sanitation Crisis"    => 9,  // Water & Sanitation
    "Fire and Floods"                => 3,  // Fire & Rescue
    "Electricity and Infrastructure" => 1,  // Electricity Dept
];

// Status labels map
$statusLabels = [
    1 => "Open",
    2 => "In Progress",
    3 => "Resolved",
];

// Anyone except a Resident may assign a ticket (councillors do this)
$canAssign   = ($role !== "Resident");
$updateError = "";

// Process assignment submitted with the Assign button
if ($canAssign && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ticket'], $_POST['ticket_id'])) {
    $ticketIdToAssign = intval($_POST['ticket_id']);
    $newServiceID     = !empty($_POST['service_id']) ? intval($_POST['service_id']) : null;

    try {
        $updateStmt = $conn->prepare("UPDATE municipal_fault_tickets SET ServiceID = ? WHERE TicketID = ?");
        if (!$updateStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $updateStmt->bind_param("ii", $newServiceID, $ticketIdToAssign);
        if (!$updateStmt->execute()) {
            throw new Exception("Update failed: " . $updateStmt->error);
        }
        $updateStmt->close();

        // Remember (front-end only) that this ticket was assigned by the councillor,
        // so the page can show a tick. Picking "Unassigned" removes the tick again.
        if ($newServiceID !== null) {
            $_SESSION['assigned_tickets'][$ticketIdToAssign] = true;
        } else {
            unset($_SESSION['assigned_tickets'][$ticketIdToAssign]);
        }

        // Reload the page so refreshing doesn't resubmit the form
        header("Location: reportedIncidences.php");
        exit();
    } catch (Throwable $e) {
        // Shown above the table so you can see why it failed
        $updateError = $e->getMessage();
    }
}

// Base SQL query
$sql = "SELECT t.TicketID, t.Title, t.Description, t.DateReported, t.Priority,
               t.Location, t.WardID_municipal, t.StatusID, t.ServiceID,
               s.ServiceName, u.Username AS ReportedBy
        FROM municipal_fault_tickets t
        LEFT JOIN municipal_services s ON t.ServiceID = s.ServiceID
        LEFT JOIN generalusers u ON t.ReportedByUserID = u.UserID";

if ($role === "Resident") {
    $sql .= " WHERE t.ReportedByUserID = ?";
    $stmt = $conn->prepare($sql . " ORDER BY t.DateReported DESC");
    $stmt->bind_param("i", $userId);

} elseif ($role === "Councillor") {
    $wardStmt = $conn->prepare("SELECT Ward FROM generalusers WHERE UserID = ?");
    $wardStmt->bind_param("i", $userId);
    $wardStmt->execute();
    $wardRow = $wardStmt->get_result()->fetch_assoc();
    $wardStmt->close();
    $councillorWard = $wardRow ? intval($wardRow['Ward']) : 0;

    if ($searchTicketId !== null) {
        // Still scoped to this councillor's own ward - they can't look up
        // a ticket ID belonging to another ward.
        $sql .= " WHERE t.WardID_municipal = ? AND t.TicketID = ?";
        $stmt = $conn->prepare($sql . " ORDER BY t.DateReported DESC");
        $stmt->bind_param("ii", $councillorWard, $searchTicketId);
    } else {
        $sql .= " WHERE t.WardID_municipal = ?";
        $stmt = $conn->prepare($sql . " ORDER BY t.DateReported DESC");
        $stmt->bind_param("i", $councillorWard);
    }

} else {
    $stmt = $conn->prepare($sql . " ORDER BY t.DateReported DESC");
}

if (!$stmt) {
    die("SQL error: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Load comments for whichever tickets are being shown above, grouped by ticket.
// Everyone who can see a ticket in the table can also see its comments.
$commentsByTicket = [];
if (!empty($rows)) {
    $ticketIds = array_map(fn($r) => (int) $r["TicketID"], $rows);
    $placeholders = implode(",", array_fill(0, count($ticketIds), "?"));
    $types = str_repeat("i", count($ticketIds));

    $commentStmt = $conn->prepare(
        "SELECT c.ticketID, c.commentText, c.commentDate, u.Username
         FROM comments c
         LEFT JOIN generalusers u ON u.UserID = c.councillorID
         WHERE c.ticketID IN ($placeholders)
         ORDER BY c.commentDate ASC"
    );
    $commentStmt->bind_param($types, ...$ticketIds);
    $commentStmt->execute();
    $commentResult = $commentStmt->get_result();
    while ($c = $commentResult->fetch_assoc()) {
        $commentsByTicket[$c["ticketID"]][] = $c;
    }
    $commentStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Reported Incidents</title>
    <style>
        /* Let the table scroll sideways instead of squeezing every column
           to fit, or cutting content off. */
        .table-scroll {
            width: 100%;
            max-height: 70vh;
            overflow-x: scroll;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #d7dee5;
            border-radius: 6px;
        }
        table.table-display {
            min-width: 1480px;
            border-collapse: collapse;
            font-size: 0.85rem;
            table-layout: fixed;
        }
        table.table-display th,
        table.table-display td {
            padding: 8px 10px;
            box-sizing: border-box;
            vertical-align: top;
            border-bottom: 1px solid #eef1f4;
            overflow-wrap: break-word;
        }
        table.table-display th {
            position: sticky;
            top: 0;
            background: #0a3654;
            color: #fff;
            text-align: left;
            white-space: nowrap;
        }

        /* Fixed, sensible width per column so nothing looks randomly wide
           or squashed. Columns still scroll as a whole if they don't fit. */
        table.table-display th:nth-child(1),  table.table-display td:nth-child(1)  { width: 70px; }  /* Ticket ID */
        table.table-display th:nth-child(2),  table.table-display td:nth-child(2)  { width: 150px; } /* Type */
        table.table-display th:nth-child(3),  table.table-display td:nth-child(3)  { width: 220px; } /* Description */
        table.table-display th:nth-child(4),  table.table-display td:nth-child(4)  { width: 140px; } /* Location */
        table.table-display th:nth-child(5),  table.table-display td:nth-child(5)  { width: 60px; }  /* Ward */
        table.table-display th:nth-child(6),  table.table-display td:nth-child(6)  { width: 80px; }  /* Priority */
        table.table-display th:nth-child(7),  table.table-display td:nth-child(7)  { width: 90px; }  /* Status */
        table.table-display th:nth-child(8),  table.table-display td:nth-child(8)  { width: 220px; } /* Assigned To */
        table.table-display th:nth-child(9),  table.table-display td:nth-child(9)  { width: 240px; } /* Comments */
        table.table-display th:nth-child(10), table.table-display td:nth-child(10) { width: 110px; } /* Date Reported */
        table.table-display th:nth-child(11), table.table-display td:nth-child(11) { width: 100px; } /* Reported By */

        table.table-display select { max-width: 150px; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <h2>Reported Incidents</h2>

    <?php if ($role === "Councillor"): ?>
        <form method="GET" action="reportedIncidences.php" style="margin-bottom:15px;">
            <label for="ticket_id">Search by Ticket ID:</label>
            <input type="number" id="ticket_id" name="ticket_id" min="1"
                   value="<?php echo htmlspecialchars($searchTicketId ?? ''); ?>"
                   placeholder="e.g. 42">
            <button type="submit">Search</button>
            <?php if ($searchTicketId !== null): ?>
                <a href="reportedIncidences.php" style="margin-left:8px;">Clear</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($updateError !== ""): ?>
        <p style="color:#b00020; font-weight:bold;">
            Could not save the assignment: <?php echo htmlspecialchars($updateError); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($rows)): ?>
        <div class="table-scroll">
            <table class="table-display">
                <tr>
                    <th>Ticket ID</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Location</th>
                    <th>Ward</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Comments</th>
                    <th>Date Reported</th>
                    <th>Reported By</th>
                </tr>
                <?php foreach ($rows as $row): ?>
                    <?php $ticketComments = $commentsByTicket[$row["TicketID"]] ?? []; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row["TicketID"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Title"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Description"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Location"]); ?></td>
                        <td><?php echo htmlspecialchars($row["WardID_municipal"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Priority"]); ?></td>
                        <td><?php echo htmlspecialchars($statusLabels[$row["StatusID"]] ?? "Unknown"); ?></td>

                        <td>
                            <?php if ($canAssign && !empty($_SESSION['assigned_tickets'][$row['TicketID']]) && !empty($row['ServiceID'])): ?>
                                <!-- Already assigned: tick replaces the Assign button -->
                                <span style="color:#2e7d32; font-weight:bold; font-size:16px;">&#10003;</span>
                                <span style="font-size:12px;"
                                      title="<?php echo htmlspecialchars($services[$row['ServiceID']] ?? ''); ?>">
                                    <?php echo htmlspecialchars($shortNames[$row['ServiceID']] ?? 'Assigned'); ?>
                                </span>
                            <?php elseif ($canAssign): ?>
                                <?php $suggestedId = $typeToService[$row['Title']] ?? null; ?>
                                <form method="POST" action="" style="display:inline-block;">
                                    <input type="hidden" name="ticket_id" value="<?php echo (int)$row['TicketID']; ?>">
                                    <input type="hidden" name="assign_ticket" value="1">

                                    <select name="service_id"
                                            style="font-size: 11px; padding: 1px 2px; max-width: 160px; cursor: pointer;">
                                        <option value="" selected>Unassigned</option>
                                        <?php foreach ($services as $svcId => $svcName): ?>
                                            <option value="<?php echo $svcId; ?>"
                                                title="<?php echo htmlspecialchars($svcName); ?>"
                                                >
                                                <?php
                                                echo $svcId . ' - ' . htmlspecialchars($shortNames[$svcId]);
                                                if ($svcId === $suggestedId) { echo ' (suggested)'; }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit"
                                            style="font-size: 11px; padding: 2px 8px; margin-top: 3px; cursor: pointer;">
                                        Assign
                                    </button>
                                </form>
                            <?php else: ?>
                                <?php echo htmlspecialchars($services[$row['ServiceID']] ?? "Unassigned"); ?>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:left;">
                            <?php if (!empty($ticketComments)): ?>
                                <?php foreach ($ticketComments as $c): ?>
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

                        <td><?php echo htmlspecialchars($row["DateReported"]); ?></td>
                        <td><?php echo htmlspecialchars($row["ReportedBy"] ?? "—"); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php else: ?>
        <p>
            <?php
            echo $searchTicketId !== null
                ? "No ticket #" . htmlspecialchars($searchTicketId) . " was found in your ward."
                : "No incidents found.";
            ?>
        </p>
    <?php endif; ?>

    <p><a href="dashboard.php"><button>Back to Dashboard</button></a></p>

    <?php
    $stmt->close();
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>