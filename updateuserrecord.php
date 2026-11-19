<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db_connect.php";

// 1. SECURITY & ROLE GUARD
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "Administrator") {
    die("Access denied. Administrators only.");
}

// 2. PROCESS UPDATE ON POST REQUEST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $conn->close();
        die("Invalid or missing CSRF token.");
    }

    // Re-authentication Check
    $confirmPassword = $_POST['confirmpassword'] ?? '';

    $authStmt = $conn->prepare("SELECT UserID, Password FROM generalusers WHERE Username = ?");
    $authStmt->bind_param("s", $_SESSION['username']);
    $authStmt->execute();
    $authResult = $authStmt->get_result();
    $userRow = $authResult->fetch_assoc();
    $authStmt->close();

    if (!$userRow || !isset($userRow['Password']) || !password_verify($confirmPassword, $userRow['Password'])) {
        $conn->close();
        die("Password confirmation failed. No changes were made. <a href='displayusers.php'>Go Back</a>");
    }

    // Inputs
    $userid    = intval($_POST['userid']);
    $firstname = trim($_POST['firstname']);
    $surname   = trim($_POST['surname']);
    $ward      = intval($_POST['ward']);
    $contact   = trim($_POST['contact']);
    $email     = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    $allowedRoles = ["Administrator", "Officer", "Councillor", "Resident"];
    $userrole = trim($_POST['userrole']);
    if (!in_array($userrole, $allowedRoles, true)) {
        $conn->close();
        die("Invalid user role.");
    }

    // Update Query
    $stmt = $conn->prepare("UPDATE generalusers SET FirstName=?, Surname=?, UserRole=?, ContactNumber=?, EmailAddress=?, Ward=? WHERE UserID=?");
    if (!$stmt) {
        $conn->close();
        die("SQL error: " . $conn->error);
    }

    $stmt->bind_param("sssssii", $firstname, $surname, $userrole, $contact, $email, $ward, $userid);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: displayusers.php?update=success");
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        die("Error updating record: " . $error);
    }

} else {
    // 3. FETCH RECORD FOR FORM PRE-FILL
    if (isset($_GET['userid'])) {
        $userid = intval($_GET['userid']);

        $stmt = $conn->prepare("SELECT * FROM generalusers WHERE UserID = ?");
        if (!$stmt) {
            $conn->close();
            die("SQL error: " . $conn->error);
        }

        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
        } else {
            $stmt->close();
            $conn->close();
            die("Record not found.");
        }
        $stmt->close();
    } else {
        $conn->close();
        die("No user ID supplied.");
    }
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
    <title> Update User Records</title>

</head> 
<body> 
<?php include'header.php'; ?>

        <h2> Update User Record</h2> 

        <div class="form-body">

    <form action="updateuserrecord.php" method="POST" class="signup-form">

        <input type="hidden" name="userid" value="<?php echo htmlspecialchars($row['UserID'] ?? ''); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <!-- User Information -->
        <div class="form-section">

            <p class="form-section-title">
                User Information
            </p>

            <div class="form-group">
                <label for="firstname"> First Name </label>
                <input type="text" id="firstname" name="firstname"  value="<?php echo htmlspecialchars($row['FirstName'] ?? ''); ?>"  required>
            </div>


            <div class="form-group">
                <label for="surname"> Surname </label>
                <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($row['Surname'] ?? ''); ?>" required>
            </div>

            <div class="form-group">

                <label for="userrole"> User Role </label>
                <select id="userrole" name="userrole" required>

                    <?php
                    $roles = ["Administrator", "Officer", "Councillor", "Resident"];
                    $currentRole = $row['UserRole'] ?? '';

                    foreach ($roles as $role) {

                        $selected = ($currentRole === $role) ? "selected" : "";

                        echo "<option value=\"" . htmlspecialchars($role) . "\" $selected>"
                            . htmlspecialchars($role) .
                            "</option>";
                    }
                    ?>

                </select>

            </div>


            <div class="form-group">
                <label for="ward"> Ward ID </label>
                <input type="number" id="ward" name="ward" min="0"  max="14" value="<?php echo htmlspecialchars($row['Ward'] ?? '0'); ?>"  required>
            </div>

            <div class="form-group">
                <label for="contact"> Contact Number</label>
                <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($row['ContactNumber'] ?? ''); ?>"  required >
            </div>

            <div class="form-group">
                <label for="email"> Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($row['EmailAddress'] ?? ''); ?>"  required>
            </div>

        </div>
        <!-- Security Re-auth -->
        <div class="form-section">

            <p class="form-section-title">
                <i class="fas fa-lock"></i> Password Confirmation
            </p>

            <div class="form-group">
                <label for="confirmpassword"> Confrim Password </label>
                <input   type="password"  id="confirmpassword" name="confirmpassword"  placeholder="Re-enter your admin password"  required>
            </div>

        </div>

        <div class="form-buttons">
            <button type="reset" class="btn-secondary"> Reset Form </button>
            <button type="submit"> Save Changes </button>
        </div>

    </form>

</div>
</body> 
</html>