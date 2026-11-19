<?php

session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

$canAddEvents    = in_array($role, ["Administrator", "Officer"], true);
$maxPictureBytes = 2 * 1024 * 1024;
$statusLabels    = [1 => "Open", 2 => "In Progress", 3 => "Resolved", 4 => "Declined"];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function flash(string $type, string $text): void
{
    $_SESSION['flash_' . $type] = $text;
    header("Location: profile.php");
    exit();
}

$message = $_SESSION['flash_ok'] ?? "";
$error   = $_SESSION['flash_err'] ?? "";
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash("err", "Your session expired. Please try again.");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        $stmt = $conn->prepare("UPDATE generalusers SET last_logout = NOW() WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $_SESSION = [];
        session_destroy();
        header("Location: login.php");
        exit();
    }

    if ($action === 'upload_picture') {

        if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
            flash("err", "Please choose a picture to upload.");
        }

        if ($_FILES['picture']['size'] > $maxPictureBytes) {
            flash("err", "The picture is too big. Maximum size is 2 MB.");
        }

        $tmpPath = $_FILES['picture']['tmp_name'];
        $info    = @getimagesize($tmpPath);
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

        if ($info === false) {
            flash("err", "PHP could not read that file as a picture. It may be damaged, or PHP may not be able to read its temporary upload folder.");
        }

        if (!isset($allowed[$info[2]])) {
            flash("err", "That file is really a " . image_type_to_mime_type($info[2]) . " picture. Only JPG, PNG, GIF or WebP are allowed.");
        }

        $uploadDir = __DIR__ . "/images/profiles/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newName = "user_" . $userId . "_" . bin2hex(random_bytes(6)) . "." . $allowed[$info[2]];

        if (!move_uploaded_file($tmpPath, $uploadDir . $newName)) {
            flash("err", "Could not save the picture. Check that the images/profiles folder is writable.");
        }

        $stmt = $conn->prepare("SELECT ProfilePicture FROM generalusers WHERE UserID = ?");
        if (!$stmt) {
            unlink($uploadDir . $newName);
            flash("err", "Could not save the picture: " . $conn->error . ". Has the ProfilePicture column been added to the generalusers table in THIS database?");
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $oldRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE generalusers SET ProfilePicture = ? WHERE UserID = ?");
        if (!$stmt) {
            unlink($uploadDir . $newName);
            flash("err", "Could not save the picture: " . $conn->error);
        }
        $stmt->bind_param("si", $newName, $userId);
        $stmt->execute();
        $stmt->close();

        if (!empty($oldRow['ProfilePicture'])) {
            $oldFile = $uploadDir . basename($oldRow['ProfilePicture']);
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        flash("ok", "Profile picture updated.");
    }

    if ($action === 'add_event') {

        if (!$canAddEvents) {
            flash("err", "You do not have permission to add events.");
        }

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = trim($_POST['event_date'] ?? '');
        $location    = trim($_POST['location'] ?? '');

        $dateCheck = DateTime::createFromFormat('Y-m-d', $eventDate);
        $dateValid = $dateCheck && $dateCheck->format('Y-m-d') === $eventDate;

        if ($title === '' || $description === '' || $location === '' || !$dateValid) {
            flash("err", "Please fill in every event field with a valid date.");
        }

        $stmt = $conn->prepare(
            "INSERT INTO events (Title, Description, EventDate, Location, CreatedByUserID)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssi", $title, $description, $eventDate, $location, $userId);
        $stmt->execute();
        $stmt->close();

        flash("ok", "Event \"" . $title . "\" added.");
    }
}

$stmt = $conn->prepare(
    "SELECT gu.*, w.WardNumber, w.WardName
     FROM generalusers gu
     LEFT JOIN ward w ON gu.Ward = w.WardID
     WHERE gu.UserID = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT TicketID, Title, Location, Priority, DateReported, StatusID
     FROM municipal_fault_tickets
     WHERE ReportedByUserID = ?
     ORDER BY DateReported DESC, TicketID DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($tickets as $t) {
    $sid = (int) $t['StatusID'];
    if (isset($counts[$sid])) {
        $counts[$sid]++;
    }
}

$progressPercent = [1 => 33, 2 => 66, 3 => 100, 4 => 100];
$progressClass   = [1 => 'p-open', 2 => 'p-progress', 3 => 'p-resolved', 4 => 'p-declined'];

$avatarSrc = "";
if (!empty($user['ProfilePicture'])) {
    $picPath = __DIR__ . "/images/profiles/" . basename($user['ProfilePicture']);
    if (is_file($picPath)) {
        $picInfo = @getimagesize($picPath);
        if ($picInfo !== false) {
            $avatarSrc = "data:" . $picInfo['mime'] . ";base64," . base64_encode(file_get_contents($picPath));
        }
    }
}

$fullName  = trim(($user['FirstName'] ?? '') . ' ' . ($user['Surname'] ?? ''));
$initials  = strtoupper(substr($user['FirstName'] ?? '?', 0, 1) . substr($user['Surname'] ?? '', 0, 1));
$wardText  = !empty($user['WardNumber'])
    ? 'Ward ' . $user['WardNumber'] . (!empty($user['WardName']) ? ' - ' . $user['WardName'] : '')
    : 'Not set';
$lastLogin = (!empty($user['last_login']) && $user['last_login'] !== "0000-00-00 00:00:00")
    ? date("Y-m-d H:i", strtotime($user['last_login']))
    : "Never";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>My Profile | NathiSiConnected</title>
    <style>
        .profile-wrap { max-width: 1000px; margin: 30px auto; padding: 0 20px 50px; color: #0a3654; text-align: left; }
        .profile-wrap h1 { text-align: left; margin-bottom: 20px; }

        .profile-wrap div.card {
            background: #eef2f6 !important;
            color: #0a3654 !important;
            border: 1px solid #d7dee5;
            border-radius: 10px;
            padding: 22px 26px;
            margin-bottom: 24px;
        }
        .profile-wrap .card h2 { margin: 0 0 14px; font-size: 1.15rem; color: #0a3654 !important; }

        .msg-ok  { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .msg-err { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }

        .top-row { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
        .avatar { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; border: 3px solid #d7dee5; }
        .avatar-initials { width: 130px; height: 130px; border-radius: 50%; background: #194975; color: #fff;
                           display: flex; align-items: center; justify-content: center; font-size: 2.6rem; font-weight: 700; }
        .avatar-box { text-align: center; }
        .avatar-box form { margin-top: 10px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .avatar-box input[type=file] { max-width: 190px; font-size: 0.8rem; }

        .details { flex: 1; min-width: 260px; }
        .details table { border-collapse: collapse; width: 100%; }
        .details td { padding: 8px 6px; border-bottom: 1px solid #d7dee5; color: #0a3654 !important; }
        .details td:first-child { font-weight: 700; width: 130px; color: #33475a !important; }

        .summary { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
        .summary div { flex: 1; min-width: 110px; background: #fff; border: 1px solid #d7dee5; border-radius: 8px; padding: 12px; text-align: center; color: #0a3654 !important; }
        .summary strong { display: block; font-size: 1.6rem; }

        .ticket-table { width: 100%; border-collapse: collapse; }
        .ticket-table th { background: #dde6ee; text-align: left; padding: 10px 12px; font-size: 0.85rem; color: #0a3654 !important; }
        .ticket-table td { padding: 10px 12px; border-bottom: 1px solid #d7dee5; font-size: 0.9rem; color: #0a3654 !important; background: #fff; }

        .bar-track { background: #e6ebf0; border-radius: 6px; height: 12px; width: 140px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 6px; }
        .p-open     { background: #e08a2e; }
        .p-progress { background: #194975; }
        .p-resolved { background: #2e9d68; }
        .p-declined { background: #b0413e; }

        .form-row { margin-bottom: 12px; }
        .form-row label { display: block; font-weight: 700; margin-bottom: 4px; font-size: 0.9rem; color: #0a3654; }
        .form-row input, .form-row textarea { width: 100%; padding: 8px; border: 1px solid #d7dee5; border-radius: 6px; box-sizing: border-box; }

        .profile-wrap button.btn {
            display: inline-block !important;
            width: auto !important;
            height: auto !important;
            padding: 10px 22px !important;
            border-radius: 6px !important;
            border: none !important;
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            font-family: inherit !important;
            cursor: pointer !important;
            text-align: center !important;
            box-shadow: none !important;
            line-height: normal !important;
            transition: background 0.15s ease;
        }
        .profile-wrap .btn-primary   { background: #25a85a; color: #fff; }
        .profile-wrap .btn-primary:hover   { background: #1f9250; }
        .profile-wrap .btn-secondary {
            display: inline-block !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            background: #fff !important;
            color: #194975 !important;
            border: 2px solid #194975 !important;
            padding: 8px 20px !important;
            box-shadow: none !important;
            line-height: normal !important;
        }
        .profile-wrap .btn-secondary:hover { background: #eaf1f8 !important; }
        .profile-wrap .btn-danger    { background: #b0413e; color: #fff; }
        .profile-wrap .btn-danger:hover    { background: #96322f; }

        .action-row { margin-top: 10px; display: flex; gap: 12px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <div class="profile-wrap">
        <h1>My Profile</h1>

        <?php if ($message !== ""): ?>
            <div class="msg-ok"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="msg-err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="top-row">
                <div class="avatar-box">
                    <?php if ($avatarSrc !== ""): ?>
                        <img class="avatar" src="<?php echo $avatarSrc; ?>" alt="Profile picture">
                    <?php else: ?>
                        <div class="avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="upload_picture">
                        <input type="file" name="picture" accept="image/png, image/jpeg, image/gif, image/webp" required>
                        <button type="submit" class="btn btn-primary">Upload picture</button>
                    </form>
                </div>

                <div class="details">
                    <h2><?php echo htmlspecialchars($fullName); ?></h2>
                    <table>
                        <tr><td>Username</td><td><?php echo htmlspecialchars($user['Username']); ?></td></tr>
                        <tr><td>Role</td><td><?php echo htmlspecialchars($user['UserRole']); ?></td></tr>
                        <tr><td>Email</td><td><?php echo htmlspecialchars($user['EmailAddress']); ?></td></tr>
                        <tr><td>Contact</td><td><?php echo htmlspecialchars($user['ContactNumber']); ?></td></tr>
                        <tr><td>Ward</td><td><?php echo htmlspecialchars($wardText); ?></td></tr>
                        <tr><td>Last login</td><td><?php echo htmlspecialchars($lastLogin); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>My Ticket Progress</h2>

            <div class="summary">
                <div><strong><?php echo count($tickets); ?></strong>Total</div>
                <div><strong><?php echo $counts[1]; ?></strong>Open</div>
                <div><strong><?php echo $counts[2]; ?></strong>In Progress</div>
                <div><strong><?php echo $counts[3]; ?></strong>Resolved</div>
                <div><strong><?php echo $counts[4]; ?></strong>Declined</div>
            </div>

            <?php if (count($tickets) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="ticket-table">
                        <tr>
                            <th>Ticket</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Priority</th>
                            <th>Reported</th>
                            <th>Status</th>
                            <th>Progress</th>
                        </tr>
                        <?php foreach ($tickets as $t): ?>
                            <?php $sid = (int) $t['StatusID']; ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($t['TicketID']); ?></td>
                                <td><?php echo htmlspecialchars($t['Title']); ?></td>
                                <td><?php echo htmlspecialchars($t['Location']); ?></td>
                                <td><?php echo htmlspecialchars($t['Priority']); ?></td>
                                <td><?php echo htmlspecialchars($t['DateReported']); ?></td>
                                <td><strong><?php echo htmlspecialchars($statusLabels[$sid] ?? "Unknown"); ?></strong></td>
                                <td>
                                    <div class="bar-track">
                                        <div class="bar-fill <?php echo $progressClass[$sid] ?? 'p-open'; ?>"
                                             style="width: <?php echo (int) ($progressPercent[$sid] ?? 0); ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <p>You have not reported any tickets yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($canAddEvents): ?>
            <div class="card">
                <h2>Add an Event</h2>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="add_event">

                    <div class="form-row">
                        <label for="title">Event title</label>
                        <input type="text" id="title" name="title" maxlength="150" required>
                    </div>
                    <div class="form-row">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>
                    <div class="form-row">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" maxlength="200" required>
                    </div>
                    <div class="form-row">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" maxlength="500" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Add event</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="action-row">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-danger">Log out</button>
            </form>
            <form method="GET" action="dashboard.php">
                <button type="submit" class="btn btn-secondary">Back to Dashboard</button>
            </form>
        </div>
    </div>

    <?php
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>