<?php
// urreg.php
// Handles the registration form submitted from signupPage.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Same database connection file the rest of the site uses
include "db_connect.php";

/**
 * Validates the form, creates the user and saves any security questions.
 * Returns an array: ok (bool), pending (bool), title (string), message (string)
 */
function registerUser(mysqli $conn): array
{
    $fail = function (string $title, string $message): array {
        return ["ok" => false, "pending" => false, "title" => $title, "message" => $message];
    };

    // Stop with a clear message if the form wasn't actually submitted
    if (empty($_POST)) {
        return $fail(
            "No form data received",
            "Please fill in the registration form and click Register. Don't open this page directly."
        );
    }

    // Get the values from the form (?? "" avoids "Undefined index" notices if a field is missing)
    $surname       = trim($_POST["surname"] ?? "");
    $firstname     = trim($_POST["firstname"] ?? "");
    $gender        = $_POST["gender"] ?? "";
    $userrole      = $_POST["userrole"] ?? "";
    $dob           = trim($_POST["dob"] ?? "");
    $ward          = $_POST["ward"] ?? "";
    $address       = trim($_POST["physAddress"] ?? "");
    $contactnumber = trim($_POST["contact"] ?? "");
    $email         = trim($_POST["email"] ?? "");
    $username      = trim($_POST["username"] ?? "");
    $password      = $_POST["pword"] ?? "";

    // Make sure a ward was actually selected (avoids the WardID foreign key error)
    if ($ward === "") {
        return $fail("Ward missing", "Please select a ward and try again.");
    }

    // Server-side password strength check
    if (strlen($password) < 8 || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return $fail(
            "Password too weak",
            "Your password must be at least 8 characters long and include at least one special character."
        );
    }

    // Hash the password before saving it
    $hashedpassword = password_hash($password, PASSWORD_DEFAULT);

    // Residents are auto-approved (1). Administrators, Officers and Councillors hold
    // elevated access, so they stay "Pending" (NULL) until an Administrator approves
    // (1) or declines (0) them via displayusers.php. Unknown roles fail safe to Pending.
    $rolesRequiringApproval = ["Administrator", "Officer", "Councillor"];
    $status = ($userrole === "Resident") ? 1 : null;

    $stmt = $conn->prepare(
        "INSERT INTO generalusers
            (FirstName, Surname, DOB, Gender, Username, Password, EmailAddress, UserRole, PhysicalAddress, ContactNumber, Ward, accountstatus)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log("urreg.php prepare failed: " . $conn->error);
        return $fail("Something went wrong", "We couldn't create your account right now. Please try again later.");
    }

    $ward = (int) $ward;
    $stmt->bind_param(
        "ssssssssssii",
        $firstname, $surname, $dob, $gender, $username, $hashedpassword,
        $email, $userrole, $address, $contactnumber, $ward, $status
    );

    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        error_log("urreg.php insert failed: " . $stmt->error);
        $stmt->close();

        if ($errno === 1062) {
            return $fail(
                "Already registered",
                "That username or email address is already in use. Please go back and choose another."
            );
        }
        return $fail("Something went wrong", "We couldn't create your account. Please try again.");
    }

    // ID of the user row we just created, so the security questions link to the right person
    $userID = $conn->insert_id;
    $stmt->close();

    // --- SAVE SECURITY QUESTIONS (optional, 0 to 3) ---
    // Only save a pair if BOTH the question and answer were filled in.
    for ($i = 1; $i <= 3; $i++) {
        $questionKey = isset($_POST["secQuestion$i"]) ? trim($_POST["secQuestion$i"]) : "";
        $answer      = isset($_POST["secAnswer$i"])   ? trim($_POST["secAnswer$i"])   : "";
        if ($questionKey === "" || $answer === "") {
            continue;
        }

        // Never store a security answer in plain text; strtolower() so
        // "Grahamstown" and "grahamstown" count as the same answer later.
        $answerHash = password_hash(strtolower($answer), PASSWORD_DEFAULT);
        $secStmt = $conn->prepare(
            "INSERT INTO security_questions (UserID, QuestionKey, AnswerHash) VALUES (?, ?, ?)"
        );

        if ($secStmt) {
            $secStmt->bind_param("iss", $userID, $questionKey, $answerHash);
            $secStmt->execute();
            $secStmt->close();
        }
    }

    if ($status === 1) {
        return [
            "ok"      => true,
            "pending" => false,
            "title"   => "Registration successful",
            "message" => "Your account has been created. You can now log in.",
        ];
    }

    return [
        "ok"      => true,
        "pending" => true,
        "title"   => "Registration submitted",
        "message" => "Your account (role: " . $userrole . ") must be verified by an Administrator "
                   . "before you can log in. You'll be able to log in once your account is approved.",
    ];
}

$result = registerUser($conn);
$conn->close();

$loggedIn = isset($_SESSION["username"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>User Register</title>

    <style>
        .register-result {
            width: 100%;
            box-sizing: border-box;
            max-width: 560px;
            margin: 40px auto;
            padding: 32px 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12);
            text-align: center;
        }
        .register-result .result-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #fff;
        }
        .result-icon.ok      { background: #25a85a; }
        .result-icon.pending { background: #f0a500; }
        .result-icon.error   { background: #d9534f; }
        .register-result h2  { margin: 0 0 12px; }
        .register-result p   { margin: 0 0 24px; line-height: 1.6; }
        .result-actions a    { margin: 0 4px; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <div class="register-result">
        <?php
        $iconClass = !$result["ok"] ? "error" : ($result["pending"] ? "pending" : "ok");
        $iconChar  = !$result["ok"] ? "&#10007;" : ($result["pending"] ? "&#8987;" : "&#10003;");
        ?>
        <div class="result-icon <?php echo $iconClass; ?>"><?php echo $iconChar; ?></div>

        <h2><?php echo htmlspecialchars($result["title"]); ?></h2>
        <p><?php echo htmlspecialchars($result["message"]); ?></p>

        <div class="result-actions">
            <?php if (!$result["ok"]): ?>
                <a href="javascript:history.back()"><button type="button">Go Back</button></a>
            <?php endif; ?>

            <a href="login.php"><button type="button">Back to Login</button></a>

            <?php if ($loggedIn): ?>
                <a href="dashboard.php"><button type="button">Back to Dashboard</button></a>
            <?php endif; ?>
        </div>
    </div>

    <?php include "footer.php"; ?>
</body>
</html>