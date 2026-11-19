<?php
// deletedusers.php
// Administrator-only page that lists everyone archived in the deleted_users table.
// (The password column is deliberately NOT selected or shown.)

session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== "Administrator") {
    http_response_code(403);
    die("Access denied. Administrator privileges required. <a href='dashboard.php'>Return to Dashboard</a>");
}

// Optional search box (matches name, surname, username, email or role)
$search = trim($_GET['q'] ?? '');

$sql = "SELECT d.DeletedID, d.UserID, d.FirstName, d.Surname, d.Username, d.UserRole,
               d.ContactNumber, d.EmailAddress, d.DeletedAt,
               w.WardNumber, a.Username AS DeletedBy
        FROM deleted_users d
        LEFT JOIN ward w ON d.Ward = w.WardID
        LEFT JOIN generalusers a ON d.DeletedByUserID = a.UserID";

if ($search !== '') {
    $sql .= " WHERE d.FirstName LIKE ? OR d.Surname LIKE ? OR d.Username LIKE ?
                 OR d.EmailAddress LIKE ? OR d.UserRole LIKE ?";
}
$sql .= " ORDER BY d.DeletedAt DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL error: " . htmlspecialchars($conn->error) . " (has the deleted_users table been created?)");
}

if ($search !== '') {
    $like = "%" . $search . "%";
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Deleted Users | NathiSiConnected</title>
</head>
<body>
    <?php include "header.php"; ?>

    <main style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
        <h1 style="color: #2c3e50; text-align: center; margin-bottom: 8px;">Deleted Users Archive</h1>
        <p style="text-align: center; color: #7f8c8d; margin-bottom: 25px;">
            Records of accounts that were deleted from the system.
            <?php echo count($rows); ?> record<?php echo count($rows) === 1 ? '' : 's'; ?> shown.
        </p>

        <form method="GET" style="text-align: center; margin-bottom: 20px;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search name, username, email or role"
                   style="padding: 8px; width: 280px; max-width: 100%;">
            <button type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a href="deletedusers.php"><button type="button">Clear</button></a>
            <?php endif; ?>
        </form>

        <?php if (count($rows) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="table-display">
                    <thead>
                        <tr>
                            <th>Archive ID</th>
                            <th>Old User ID</th>
                            <th>First Name</th>
                            <th>Surname</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Ward</th>
                            <th>Deleted On</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['DeletedID']); ?></td>
                                <td><?php echo htmlspecialchars($r['UserID']); ?></td>
                                <td><?php echo htmlspecialchars($r['FirstName']); ?></td>
                                <td><?php echo htmlspecialchars($r['Surname']); ?></td>
                                <td><?php echo htmlspecialchars($r['Username']); ?></td>
                                <td><strong><?php echo htmlspecialchars($r['UserRole']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['ContactNumber']); ?></td>
                                <td><?php echo htmlspecialchars($r['EmailAddress']); ?></td>
                                <td><?php echo htmlspecialchars($r['WardNumber'] ?? "—"); ?></td>
                                <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($r['DeletedAt']))); ?></td>
                                <td><?php echo htmlspecialchars($r['DeletedBy'] ?? "—"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #7f8c8d;">
                <?php echo $search !== '' ? 'No archived users match your search.' : 'No users have been deleted yet.'; ?>
            </p>
        <?php endif; ?>

        <div style="margin-top: 25px; text-align: center;">
            <a href="displayusers.php"><button type="button">Back to User Management</button></a>
            <a href="dashboard.php"><button type="button">Back to Dashboard</button></a>
        </div>
    </main>

    <?php
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>