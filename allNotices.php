<?php
include "db_connect.php";

$sql = "SELECT noticeID, noticeTitle, noticeContent, DatePosted
        FROM notices
        WHERE Status ='Approved'
        AND noticeLevel ='community'
        ORDER BY DatePosted DESC";

$result = $conn->query($sql);

if ($result === false) {
    die("Unable to retrieve notices: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="style.css">

    <title>All Community Notices</title>
</head>

<body>

<?php include "header.php"; ?>

<section class="notices-section">

    <h2>All Community Notices</h2>

    <div class="notices-grid">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($notice = $result->fetch_assoc()): ?>

                <article class="notice-card">

                    <div class="notice-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>

                    <div class="notice-date">
                        Posted:
                        <?php echo htmlspecialchars($notice['DatePosted']); ?>
                    </div>

                    <h3>
                        <?php echo htmlspecialchars($notice['noticeTitle']); ?>
                    </h3>

                    <p>
                        <?php echo htmlspecialchars($notice['noticeContent']); ?>
                    </p>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No community notices are currently available.</p>

        <?php endif; ?>

    </div>

    <div class="back-to-home">
        <a href="home.php">Back to Home</a>
    </div>

</section>

<?php include "footer.php"; ?>

</body>
</html>