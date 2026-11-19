<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <title>User Management | NathiSiConnected</title>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="main-container" style="width: 100%; max-width: 1400px; box-sizing: border-box; margin: 40px auto; padding: 0 20px;">
        <h1 style="color: #2c3e50; text-align: center; margin-bottom: 25px;">User Management Directory</h1>

        <?php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        include "db_connect.php";

        // Security & Role Guard
        if (!isset($_SESSION['username'])) {
            header("Location: login.php");
            exit();
        }

        if ($_SESSION['role'] !== "Administrator") {
            die("<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>Access denied. Administrator privileges required. <a href='dashboard.php'>Return to Dashboard</a></div>");
        }

        $sql = "SELECT gu.*, w.WardNumber, w.WardName
                FROM generalusers gu
                LEFT JOIN ward w ON gu.Ward = w.WardID";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
        ?>

        <div style="overflow-x: auto;">
            <table class="table-display" style="margin: 0 auto;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Surname</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Ward</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Last Logout</th>
                        <th>Update</th>
                        <th>Delete</th>
                        <th>Verify</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    // Status Badge Labels & Colors
                    if ($row["accountstatus"] === null) {
                        $statusBadge = "<span style='background:#fff3cd; color:#856404; padding:4px 8px; border-radius:4px; font-weight:bold;'>Pending</span>";
                    } elseif ((int)$row["accountstatus"] === 1) {
                        $statusBadge = "<span style='background:#d4edda; color:#155724; padding:4px 8px; border-radius:4px; font-weight:bold;'>Approved</span>";
                    } else {
                        $statusBadge = "<span style='background:#f8d7da; color:#721c24; padding:4px 8px; border-radius:4px; font-weight:bold;'>Declined</span>";
                    }

                    $rolesRequiringApproval = ["Administrator", "Officer", "Councillor"];
                    ?>

                    <tr>
                        <td><?php echo htmlspecialchars($row["UserID"]); ?></td>
                        <td><?php echo htmlspecialchars($row["FirstName"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Surname"]); ?></td>
                        <td><?php echo htmlspecialchars($row["Username"]); ?></td>
                        <td><strong><?php echo htmlspecialchars($row["UserRole"]); ?></strong></td>
                        <td><?php echo htmlspecialchars($row["ContactNumber"]); ?></td>
                        <td><?php echo htmlspecialchars($row["EmailAddress"]); ?></td>
                        <td><?php echo htmlspecialchars($row["WardNumber"] ?? "—"); ?></td>
                        <td><?php echo $statusBadge; ?></td>

                        <td>
                            <?php
                            if (!empty($row["last_login"]) && $row["last_login"] !== "0000-00-00 00:00:00") {
                                echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($row["last_login"])));
                            } else {
                                echo "Never";
                            }
                            ?>
                        </td>

                        <td>
                            <?php
                            if (!empty($row["last_logout"]) && $row["last_logout"] !== "0000-00-00 00:00:00") {
                                echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($row["last_logout"])));
                            } else {
                                echo "Never";
                            }
                            ?>
                        </td>

                        <td>
                            <a href="updateuserrecord.php?userid=<?php echo urlencode($row["UserID"]); ?>">
                                <button type="button" style="background:#3498db; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">
                                    Update
                                </button>
                            </a>
                        </td>

                        <td>
                            <a href="deleteuser.php?userid=<?php echo urlencode($row["UserID"]); ?>" onclick="return confirm('Delete this user account?')">
                                <button type="button" style="background:#e74c3c; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">
                                    Delete
                                </button>
                            </a>
                        </td>

                        <td>
                        <?php if ($row["accountstatus"] === null && in_array($row["UserRole"], $rolesRequiringApproval, true)): ?>
                            <a href="approveuser.php?userid=<?php echo urlencode($row["UserID"]); ?>&action=approve" onclick="return confirm('Approve user privileges?')">
                                <button type="button" style="background:#2ecc71; color:white; border:none; padding:5px 8px; border-radius:4px; cursor:pointer; font-size:11px;">
                                    Approve
                                </button>
                            </a>

                            <a href="approveuser.php?userid=<?php echo urlencode($row["UserID"]); ?>&action=decline" onclick="return confirm('Decline user privileges?')">
                                <button type="button" style="background:#7f8c8d; color:white; border:none; padding:5px 8px; border-radius:4px; cursor:pointer; font-size:11px; margin-top:2px;">
                                    Decline
                                </button>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
            <p style="text-align: center; color: #7f8c8d;">
                No registered accounts found in the system.
            </p>
        <?php endif; ?>

        <?php $conn->close(); ?>

        <div style="margin-top: 25px; text-align: center;">
            <a href="deletedusers.php">
                <button type="button" style="background-color: #34495e; color: white; border: none; padding: 12px 24px; border-radius: 5px; font-weight: bold; cursor: pointer; margin-right: 10px;">
                    <i class="fas fa-user-slash"></i> View Deleted Users
                </button>
            </a>
            <a href="dashboard.php">
                <button type="button" style="background-color: #2ecc71; color: white; border: none; padding: 12px 24px; border-radius: 5px; font-weight: bold; cursor: pointer;">
                    <i class="fas fa-arrow-left"></i> Return to Admin Dashboard
                </button>
            </a>
        </div>
    </main>

    <?php include 'footer.php'; ?>

</body>
</html>