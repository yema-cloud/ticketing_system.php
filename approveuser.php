<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title> Approve User </title>
</head>
<body class="reponse-page">
    <div class="response-message">
        <?php
        // approveuser.php
        // Lets a logged-in Administrator approve (Status = 1) or decline (Status = 0)
        // a pending user account (Administrator, Officer, Councillor signups).

        session_start();
        include "db_connect.php";

        if (!isset($_SESSION['username'])) {
            header("Location: login.php");
            exit();
        }
        if ($_SESSION['role'] !== "Administrator") {
            die("Access denied. Administrators only.");
        }

        $userid = $_GET['userid'] ?? '';
        $action = $_GET['action'] ?? '';

        if ($userid === '' || !in_array($action, ['approve', 'decline'], true)) {
            die("Invalid request. <a href='displayusers.php'>Back to Display Users</a>");
        }

        // Safety check: don't let an Administrator re-decide an account that isn't
        // actually pending, and don't let someone approve/decline their own record.
        $check = $conn->prepare("SELECT UserID, UserRole, accountstatus FROM generalusers WHERE UserID = ?");
        $check->bind_param("s", $userid);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows !== 1) {
            die("User not found. <a href='displayusers.php'>Back to Display Users</a>");
        }

        $targetUser = $checkResult->fetch_assoc();
        $check->close();

        // Residents (and any other role that isn't in this list) are auto-approved
        // at signup and are never meant to go through this manual approval step.
        $rolesRequiringApproval = ["Administrator", "Officer", "Councillor"];
        if (!in_array($targetUser["UserRole"], $rolesRequiringApproval, true)) {
            die("This role does not require manual verification. <a href='displayusers.php'>Back to Display Users</a>");
        }

        if ($targetUser["accountstatus"] !== null) {
            die("This user has already been " . ((int)$targetUser["accountstatus"] === 1 ? "approved" : "declined") . ". <a href='displayusers.php'>Back to Display Users</a>");
        }

        $newStatus = ($action === 'approve') ? 1 : 0;

        $stmt = $conn->prepare("UPDATE generalusers SET accountstatus = ? WHERE UserID = ?");
        $stmt->bind_param("is", $newStatus, $userid);

        if ($stmt->execute()) {
            echo $newStatus === 1
                ? "User " . htmlspecialchars($userid) . " has been approved and can now log in."
                : "User " . htmlspecialchars($userid) . " has been declined and cannot log in.";
        } else {
            echo "Something went wrong: " . $conn->error;
        }

        $stmt->close();
        $conn->close();
        ?>

        <p> 
            <a href="displayusers.php"> <button>Back to Display Users</button></a>
        </p>

    </div>
    <?php include "footer.php"; ?>
</body> 
</html>