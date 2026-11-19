<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Login Page</title>
</head>
<body>
<?php
session_start();

$errorMessage = ""; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    include "db_connect.php";

    $username = trim($_POST["username"] ?? '');
    $password = $_POST["pword"] ?? '';

    $stmt = $conn->prepare("SELECT UserID, Username, Password, UserRole, accountstatus FROM generalusers WHERE Username = ?");

    if (!$stmt) {
        die("SQL error: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["Password"])) {
            if ($user["accountstatus"] === null) {
                $errorMessage = "Your account is still awaiting verification by an Administrator. Please check back later.";
            } elseif ((int)$user["accountstatus"] === 0) {
                $errorMessage = "Your account application was declined. Please contact the municipality for more information.";
            } else {
                $_SESSION["user_id"]  = $user["UserID"];
                $_SESSION["username"] = $user["Username"];
                $_SESSION["role"]     = $user["UserRole"];

                // UPDATE LOGIN TIMESTAMP
                $last_login_time = date('Y-m-d H:i:s');
                $update_stmt = $conn->prepare("UPDATE generalusers SET last_login = ? WHERE Username = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("ss", $last_login_time, $user["Username"]);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                header("Location: dashboard.php");
                exit();
            }
        } else {
            $errorMessage = "Incorrect username or password.";
        }
    } else {
        $errorMessage = "Incorrect username or password.";
    }

    $stmt->close();
    $conn->close();
}
?>

    <?php include "header.php"; ?>

    <div class="form-body">
        <h2>USER LOGIN FORM</h2>

        <?php if (!empty($errorMessage)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>

        <form action="" method="POST" class="forms">
            <div class="form-group">
                <label for="username">User Name:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="pword">Password:</label>
                <input type="password" id="pword" name="pword" required>
            </div>

            <p>
                Forgot Password?
                <a href="forgotPasswordForm.php" class="link">Click Here</a>
            </p>

            <div class="form-buttons">
                <button type="submit">LOGIN</button>
            </div>

            <p>
                <a href="signupPage.php" class="link">Create a User Account</a>
            </p>
        </form>
    </div>

    <?php include "footer.php"; ?>

</body>
</html>