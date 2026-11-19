<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    
</head>

<body>

    <?php
    include "header.php";
    ?>

    <div class="dashboard-content">

        <div class="welcome-banner">
            <strong>
                Welcome <?php echo htmlspecialchars($_SESSION['username']); ?><br>
                (<?php echo htmlspecialchars($role); ?>)
            </strong>
        </div>

        <div class="card-grid">

            <?php
            switch ($role) {

                case "Administrator":
                    echo '
                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-orange">  
                                <i>&#128100;</i>
                            </div>
                            Add a new User
                        </div>
                        <a href="signupPage.php">
                            <button>Add user</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-blue">
                                <i>&#128101;</i>
                            </div>
                            Display All Users
                        </div>
                        <a href="displayusers.php">
                            <button> Manage Users</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-pink">
                                <i>&#128203;</i>
                            </div>
                            View All Tickets
                        </div>
                        <a href="reportedIncidences.php">
                            <button>View Report</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-amber">
                                <i>&#128276;</i>
                            </div>
                            Manage Notices
                        </div>
                        <a href="manage_notices.php">
                            <button>Manage Notices</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-blue">
                                <i>&#128276;</i>
                            </div>
                            Manage Events
                        </div>
                        <a href="manage_events.php">
                            <button>Manage Events</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-pink">
                                <i>&#128465;</i>
                            </div>
                            Deleted Users Archive
                        </div>
                        <a href="deletedusers.php">
                            <button>View Archive</button>
                        </a>
                    </div>';

                    break;


                case "Officer":
                    echo '
                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-amber">
                                <i>&#128276;</i>
                            </div>
                            Add a Notice or Event
                        </div>
                        <a href="add_event.php">
                            <button>Add Notice/Event</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-pink">
                                <i>&#128203;</i>
                            </div>
                            Track Tickets System-Wide
                        </div>
                        <a href="reportedIncidences.php">
                            <button>View Report</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-blue">
                                <i>&#128167;</i>
                            </div>
                            Update Dam Levels
                        </div>
                        <a href="update_dam_levels.php">
                            <button>Update Dam Levels</button>
                        </a>
                    </div>';

                    break;


                case "Councillor":
                    echo '
                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-amber">
                                <i>&#9888;</i>
                            </div>
                            Report an Incident
                        </div>
                        <a href="reportIncident.php">
                            <button>Report Incident</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-pink">
                                <i>&#128203;</i>
                            </div>
                            View Ward Tickets
                        </div>
                        <a href="reportedIncidences.php">
                            <button>View Report</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-blue">
                                <i>&#128221;</i>
                            </div>
                            Update Ticket Status
                        </div>
                        <a href="update_status.php">
                            <button>Update Status</button>
                        </a>
                    </div>

                        <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-blue">
                                <i>&#128466;</i>
                            </div>
                            Add comment
                        </div>
                        <a href="comments.php">
                            <button>Add Comment</button>
                        </a>
                        </div>';
                
                

                    break;


                case "Resident":
                    echo '
                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-amber">
                                <i>&#9888;</i>
                            </div>
                            Report an Incident
                        </div>
                        <a href="reportIncident.php">
                            <button>Report Incident</button>
                        </a>
                    </div>

                    <div class="action-card">
                        <div class="card-header">
                            <div class="icon-circle icon-pink">
                                <i>&#128203;</i>
                            </div>
                            View My Reported Incidences
                        </div>
                        <a href="reportedIncidences.php">
                            <button>View Report</button>
                        </a>
                    </div>';

                    break;


                default:
                    echo '
                    <p class="access-warning">
                        Access level not recognised. Please contact administrator.
                    </p>';

                    break;
            }
            ?>

        </div> <!-- card-grid -->

    </div> <!-- dashboard-content -->


</main> <!-- logged-in-page -->

</div> <!-- logged-in-layout -->


<?php include 'footer.php'; ?>


</body>
</html>