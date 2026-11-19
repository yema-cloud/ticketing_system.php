<?php
// Start the session only if one is not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['username']);
?>


<?php if ($isLoggedIn): ?>

<div class="header-title">

    <h1>
        <img src="images/logo.jpeg"> </img> 
        <span class="title-black">Nathi</span>
        <span class="title-green">SiConnected</span>
    </h1>

    <section> 
        <ul>

            <li>
                <a href="home.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>"> Home </a>
            </li>

            <li>
                <a href="reportIncident.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reportIncident.php' ? 'active' : ''; ?>"> Report an Incident </a>
            </li>

            <li>
                <a href="RENAME.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'RENAME.php' ? 'active' : ''; ?>"> Wards </a>
            </li>

            <li>
                <a href="services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>"> Services </a>
            </li>

            <li>
                <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>"> Contact </a>
            </li>

            <li>
                <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>"> About </a>
            </li>

        </ul>
    </section> 

    <button class="header-button">
        <a href="logout.php">Log Out</a>
    </button>

</div>


<div class="logged-in-layout"> 

    <aside class="logged-in-sidebar" id="sidebar"> 
          

        <!-- HAMBURGER -->
        <button type="button" class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <!-- ARROW -->
        <span class="menu-arrow" onclick="toggleSidebar()">
            <i class="fas fa-arrow-right"></i>
</span>
    <li> 

                <a href="profile.php"
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>"> 

                    <i class="fas fa-user"></i> 
                    <span>Profile</span> 

                </a> 

            </li>

        <ul> 

            <!-- DASHBOARD -->
            <li class="has-submenu">

                <a href="dashboard.php"
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">

                    <i class="fas fa-home"></i> 
                    <span>Dashboard</span>

                </a>


                <!-- ROLE-BASED SUBMENU -->
                <ul class="submenu">

                    <?php if ($_SESSION['role'] == 'Administrator'): ?>

                         <li>
                            <a href="signupPage.php">
                                <i class="fas fa-user-edit"></i>
                                <span>Add New Users</span>
                            </a>
                        </li>

                        <li>
                            <a href="displayusers.php">
                                <i class="fas fa-user-friends"></i>
                                <span>Manage All Users</span>
                            </a>
                        </li>
                         <li>
                            <a href="reportedIncidences.php">
                                <i class="fas fa-ticket-alt"></i>
                                <span> View All Tickets </span>
                            </a>
                        </li>

                        <li>
                            <a href="manage_notices.php">
                                <i class="fas fa-bell"></i>
                                <span>Manage Notices</span>
                            </a>
                        </li>

                        <li>
                            <a href="manage_events.php">
                                <i class="fas fa-calendar"></i>
                                <span>Manage Events</span>
                            </a>
                        </li>
                        <li>
                            <a href="deletedusers.php">
                                <i class="fas fa-user-slash"></i>
                                <span> Deleted Users </span>
                            </a>
                        </li>


                    <?php elseif ($_SESSION['role'] == 'Officer'): ?>

                        <li>
                            <a href="add_event.php">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Add Notice/Event</span>
                            </a>
                        </li>

                        <li>
                            <a href="reportedIncidences.php">
                                <i class="fas fa-ticket-alt"></i>
                                <span>Track Tickets System-Wide</span>
                            </a>
                        </li>
                    
                         <li>
                            <a href="update_dam_levels.php">
                                <i class="fa-solid fa-droplet"></i>
                                <span>Update Dam Levels</span>
                            </a>
                        </li>


                    <?php elseif ($_SESSION['role'] == 'Councillor'): ?>

                        <li>
                            <a href="reportIncident.php">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Report Incident</span>
                            </a>
                        </li>

                        <li>
                            <a href="reportedIncidences.php">
                                <i class="fas fa-ticket-alt"></i>
                                <span>View Ward Tickets</span>
                            </a>
                        </li>

                        <li>
                            <a href="update_status.php">
                                <i class="fas fa-edit"></i>
                                <span>Update Status</span>
                            </a>
                        </li>


                    <?php elseif ($_SESSION['role'] == 'Resident'): ?>

                        <li>
                            <a href="reportIncident.php">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Report Incident</span>
                            </a>
                        </li>

                        <li>
                            <a href="reportedIncidences.php">
                                <i class="fas fa-ticket-alt"></i>
                                <span>My Reports</span>
                            </a>
                        </li>

                    <?php endif; ?>

                </ul>

            </li>


            <!-- SYSTEM REPORTS -->
            <li> 

                <a href="reports.php"
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>"> 

                    <i class="fas fa-chart-bar"></i> 
                    <span>Systems Reports</span> 

                </a> 

            </li>

            <!-- LOG OUT -->
            <li class="logout-link"> 

                <a href="logout.php"> 

                    <i class="fas fa-sign-out-alt"></i> 
                    <span>Log Out</span> 

                </a> 

            </li>

        </ul> 

    </aside>


    <main class="logged-in-page">


<?php else: ?>


<div class="header-title">

    <h1>
        <img src="images/logo.jpeg"> </img> 
        <span class="title-black">Nathi</span>
        <span class="title-green">SiConnected</span>
    </h1>

    <section> 

        <ul>

            <li>
                <a href="home.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">
                    Home
                </a>
            </li>

            <li>
                <a href="RENAME.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'RENAME.php' ? 'active' : ''; ?>">
                    Wards
                </a>
            </li>

            <li>
                <a href="services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                    Services
                </a>
            </li>

            <li>
                <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
                    Contact
                </a>
            </li>

            <li>
                <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
                    About
                </a>
            </li>

        </ul>

    </section>


    <button class="header-button">
        <a href="login.php">Log In/ Register</a>
    </button>

</div>


<?php endif; ?>


<script>
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");

    sidebar.classList.toggle("expanded");
}
</script>