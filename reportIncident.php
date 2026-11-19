<?php
// reportIncident.php
// Shows the incident report form and handles its own submission.
// Ties the ticket to the logged-in user via ReportedByUserID.

session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title       = trim($_POST['incident_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = trim($_POST['priority'] ?? '');
    $location    = trim($_POST['physAddress'] ?? '');
    $wardId      = intval($_POST['ward'] ?? 0);

    // The form's "TYPE OF INCIDENCE" dropdown sends text like "Roads and
    // Public Works", not a number. So we look up the matching ServiceID
    // from the database using that text.
    $serviceId = 0;
    if ($title !== '') {
        $svcStmt = $conn->prepare("SELECT ServiceID FROM municipal_services WHERE ServiceName = ?");
        if (!$svcStmt) {
            die("SQL error looking up service: " . $conn->error);
        }
        $svcStmt->bind_param("s", $title);
        $svcStmt->execute();
        $svcRow = $svcStmt->get_result()->fetch_assoc();
        if ($svcRow) {
            $serviceId = $svcRow['ServiceID'];
        }
        $svcStmt->close();
    }

    if ($title === '' || $description === '' || $location === '' || $priority === '' || $serviceId === 0 || $wardId === 0) {
        $errorMessage = "All fields must be filled in.";
    } else {
        $userId = $_SESSION['user_id'];
        $dateReported = date("Y-m-d");
        $statusId = 1; // "Open" - confirmed via the status lookup table

        // Handle the optional picture upload first so we have a filename
        // ready to insert alongside the rest of the ticket, instead of
        // updating the row again afterward.
        $pictureFilename = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . "/images/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($extension, $allowedExtensions, true)) {
                $pictureFilename = uniqid("ticket_", true) . "." . $extension;
                if (!move_uploaded_file($_FILES['picture']['tmp_name'], $uploadDir . $pictureFilename)) {
                    $pictureFilename = null;
                }
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO municipal_fault_tickets
                (Title, Description, DateReported, Priority, Location, WardID_municipal, StatusID, ServiceID, ReportedByUserID, Picture)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $errorMessage = "SQL error: " . $conn->error;
        } else {
            $stmt->bind_param(
                "sssssiiiis",
                $title, $description, $dateReported, $priority, $location, $wardId, $statusId, $serviceId, $userId, $pictureFilename
            );

            if ($stmt->execute()) {
                $ticketId = $stmt->insert_id;
                $successMessage = "Incident successfully reported! Your ticket number is: " . htmlspecialchars($ticketId);
            } else {
                $errorMessage = "Something went wrong: " . $stmt->error;
            }

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
    <title>Incident Reporting</title>
</head>
<body>
    <?php include "header.php"; ?>

    <div class="form-body">
        <h2>Incident Reporting Form</h2>

        <?php if (!empty($errorMessage)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <p style="color: green;"><?php echo $successMessage; ?></p>
        <?php endif; ?>
       
        <form action="" method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="incident_type">Type of Incidence</label>

                <select id="incident_type" name="incident_type" required>
                    <option value="">Incident Type</option>
                    <option value="Electricity and Infrastructure">Electricity and Infrastructure</option>
                    <option value="Fire and Floods">Fire and Floods</option>
                    <option value="Governance and Financial Mismanagement">Governance and Financial Mismanagement</option>
                    <option value="Roads and Public Works">Roads and Public Works</option>
                    <option value="Stray Animals">Stray Animals</option>
                    <option value="Unlawful Land Occupation">Unlawful Land Occupation</option>
                    <option value="Waste and Environment">Waste and Environment</option>
                    <option value="Water and Sanitation Crisis">Water and Sanitation Crisis</option>
                </select>
            </div>


            <div class="form-group">
                <label for="description">Incident Description</label>

                <textarea id="description" name="description" rows="4" maxlength="500" required></textarea>
            </div>


            <div class="form-group">
                <label for="priority">Priority</label>

                <select id="priority" name="priority" required>
                    <option value="">Incident Priority</option>
                    <option value="Low">Low</option>
                    <option value="Normal">Normal</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>


            <div class="form-group">
                <label for="physAddress">Incident Physical Address</label>

                <textarea id="physAddress" rows="3" name="physAddress" required></textarea>
            </div>


            <div class="form-group">
                <label for="ward">Ward</label>

                <select id="ward" name="ward" required>
                    <option value="">-- Select Ward --</option>

                    <?php for ($w = 1; $w <= 14; $w++): ?>
                        <option value="<?php echo $w; ?>">
                            Ward <?php echo $w; ?>
                        </option>
                    <?php endfor; ?>

                </select>
            </div>


            <div class="form-group">
                <label for="picture">Picture</label>

                <input type="file" id="picture" name="picture" accept="image/*">
            </div>


            <div class="form-buttons">
                <button type="reset" class="btn-secondary">Clear Form</button>
                <button type="submit">Report Incident</button>
            </div>

</form>
                        
    </div>
    <p><a href="dashboard.php"><button>Back to Dashboard</button></a></p>

    <?php include "footer.php"; ?>
</body>
</html>