<?php
include "header.php";
include "db_connect.php";

   //USER / WARD INFORMATION

$userRole = isset($_SESSION["role"]) ? $_SESSION["role"] : "";

$canSubmitNotices = in_array(
    $userRole,
    ["Officer", "Administrator"],
    true
);

$userWard = null;

if (isset($_SESSION["user_id"])) {

    $userStmt = $conn->prepare(
        "SELECT Ward FROM generalusers WHERE UserID = ?"
    );

    if ($userStmt) {

        $userStmt->bind_param(
            "i",
            $_SESSION["user_id"]
        );

        $userStmt->execute();

        $userStmt->bind_result($userWard);

        $userStmt->fetch();

        $userStmt->close();
    }
}


/* WARD-SPECIFIC NOTICE */

$wardNotice = null;

if ($userWard !== null) {

    $wardNoticeStmt = $conn->prepare(
        "SELECT noticeTitle, noticeContent
         FROM notices
         WHERE Status = 'Approved'
         AND noticeLevel = 'ward'
         AND ward = ?
         ORDER BY DatePosted DESC
         LIMIT 1"
    );

    if ($wardNoticeStmt === false) {

        die(
            "Prepare failed (ward notice query): "
            . $conn->error
        );
    }

    $wardNoticeStmt->bind_param(
        "i",
        $userWard
    );

    $wardNoticeStmt->execute();

    $wardNoticeResult =
        $wardNoticeStmt->get_result();

    $wardNotice =
        $wardNoticeResult->fetch_assoc();

    $wardNoticeStmt->close();
}


/*COMMUNITY NOTICES */

$stmt = $conn->prepare(
    "SELECT *
     FROM notices
     WHERE Status = 'Approved'
     AND noticeLevel = 'community'
     ORDER BY DatePosted DESC
     LIMIT 3"
);

if ($stmt === false) {

    die(
        "Prepare failed: "
        . $conn->error
    );
}

$stmt->execute();

$result = $stmt->get_result();

if ($result === false) {

    die(
        "Unable to retrieve notices: "
        . $conn->error
    );
}


/* Total community notices */

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM notices
     WHERE Status = 'Approved'
     AND noticeLevel = 'community'"
);

$countStmt->execute();

$countStmt->bind_result($totalNotices);

$countStmt->fetch();

$countStmt->close();


/*SERVICE FAULT REPORT STATISTICS */

$ticketStatsSql = "
    SELECT
        COUNT(*) AS TotalTickets,

        SUM(
            CASE
                WHEN StatusID = 1
                THEN 1
                ELSE 0
            END
        ) AS OpenTickets,

        SUM(
            CASE
                WHEN StatusID = 2
                THEN 1
                ELSE 0
            END
        ) AS InProgressTickets,

        SUM(
            CASE
                WHEN StatusID = 3
                THEN 1
                ELSE 0
            END
        ) AS ResolvedTickets

    FROM municipal_fault_tickets
";

$ticketStatsResult =
    $conn->query($ticketStatsSql);


$ticketStats = [

    "TotalTickets" => 0,

    "OpenTickets" => 0,

    "InProgressTickets" => 0,

    "ResolvedTickets" => 0

];


if ($ticketStatsResult) {

    $row =
        $ticketStatsResult->fetch_assoc();

    if ($row) {

        $ticketStats["TotalTickets"] =
            (int)$row["TotalTickets"];

        $ticketStats["OpenTickets"] =
            (int)$row["OpenTickets"];

        $ticketStats["InProgressTickets"] =
            (int)$row["InProgressTickets"];

        $ticketStats["ResolvedTickets"] =
            (int)$row["ResolvedTickets"];
    }
}


/* EVENTS */

$sql = "
    SELECT
        EventID,
        EventTitle,
        EventDescription,
        EventDate,
        EventTime,
        EventLocation,
        posterImage

    FROM events

    WHERE status = 'Accepted'

    ORDER BY EventDate ASC
";

$eventResult = $conn->query($sql);

if ($eventResult === false) {

    die(
        "Unable to retrieve events: "
        . $conn->error
    );
}

$events = [];

while ($event = $eventResult->fetch_assoc()) {

    $events[] = $event;
}


/* DAM LEVELS (updated by Officers on update_dam_levels.php) */

$damLevels = [];

try {

    $damResult = $conn->query(
        "SELECT DamName, CapacityPercent, LastUpdated
         FROM dam_levels"
    );

    if ($damResult) {

        while ($damRow = $damResult->fetch_assoc()) {

            $damLevels[$damRow["DamName"]] = [

                "percent" => ($damRow["CapacityPercent"] === null)
                    ? null
                    : (float)$damRow["CapacityPercent"],

                "updated" => $damRow["LastUpdated"]
            ];
        }
    }
}
catch (Throwable $e) {

    /* Table missing or unreadable: every dam shows "Update pending" */

    $damLevels = [];
}


/* Card colour + description for each dam (display order) */

$damDisplay = [

    "Howiesonspoort Dam" => [
        "card" => "dam-blue",
        "fill" => "blue-fill",
        "info" => "One of the main dams supplying the Makhanda West water system."
    ],

    "Settlers Dam" => [
        "card" => "dam-green",
        "fill" => "green-fill",
        "info" => "Supplies water to the Makhanda West water system."
    ],

    "Jameson Dam" => [
        "card" => "dam-orange",
        "fill" => "orange-fill",
        "info" => "A smaller dam that supplements the Makhanda West water supply."
    ],

    "Milner Dam" => [
        "card" => "dam-purple",
        "fill" => "purple-fill",
        "info" => "A smaller dam that supplements the Makhanda West water supply."
    ],

    "Glen Melville Dam" => [
        "card" => "dam-teal",
        "fill" => "teal-fill",
        "info" => "Main raw-water source supplying the Makhanda East water system."
    ]

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Nathi SiConnected | Makhanda Community Platform
    </title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- Your main stylesheet -->
    <link
        rel="stylesheet"
        href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>"
    >

</head>

<body class="home-page">


<!-- =====================================================
     WELCOME / HERO SECTION
====================================================== -->

<section
    class="welcome-section"
    id="home"
>

    <div class="welcome-overlay"></div>

    <div class="welcome-content">

        <div class="welcome-tag">

            <i class="fa-solid fa-location-dot"></i>

            Makhanda Community Platform

        </div>


        <h1>

            Welcome to
            <span class="title-black">Nathi</span>
            <span class="title-green">SiConnected</span>
        </h1>


        <h2>

            Connecting Residents, Wards
            and the Makhanda Community

        </h2>


        <p>

            Welcome to NathiSiConnected, Stay connected with your ward, access important community notices, discover local events and services, and report municipal problems affecting your community.

        </p>


        <div class="hero-buttons">

            <a
                href="RENAME.php"
                class="hero-btn primary-btn"
            >

                <i class="fa-solid fa-map-location-dot"></i>

                Explore Your Ward

            </a>


            <a
                href="#notices"
                class="hero-btn secondary-btn"
            >

                <i class="fa-solid fa-bell"></i>

                Community Notices

            </a>

        </div>

    </div>


    <!-- WEATHER -->

    <div class="weather-card">

        <div class="weather-title">

            <i class="fa-solid fa-cloud-sun"></i>

            <span>Makhanda Weather</span>

        </div>


        <div
            id="temperature"
            class="weather-temperature"
        >
            Loading...
        </div>


        <div
            id="condition"
            class="weather-condition"
        >
            Checking current weather...
        </div>

    </div>

</section>



<!-- =====================================================
     COMMUNITY NOTICES
====================================================== -->

<section
    class="section notices-section"
    id="notices"
>

    <div class="section-heading">

        <h2>

            Community Notices

        </h2>

        <p>

            Stay informed about important updates and
            information affecting the Makhanda community.

        </p>

    </div>


    <!-- WARD NOTICE -->

    <?php if ($wardNotice): ?>

        <div class="ward-notice-banner">

            <div class="ward-notice-icon">

                <i class="fa-solid fa-bullhorn"></i>

            </div>


            <div class="ward-notice-text">

                <span class="notice-label">
                    Your Ward Notice
                </span>

                <h4>

                    <?php
                    echo htmlspecialchars(
                        $wardNotice['noticeTitle']
                    );
                    ?>

                </h4>


                <p>

                    <?php
                    echo htmlspecialchars(
                        $wardNotice['noticeContent']
                    );
                    ?>

                </p>

            </div>


            <div class="ward-notice-badge">

                Approved Notice

            </div>

        </div>

    <?php endif; ?>


    <!-- COMMUNITY NOTICE CARDS -->

    <div class="notices-grid">

        <?php while (
            $notice = $result->fetch_assoc()
        ): ?>

            <article class="notice-card">

                <div class="notice-icon">

                    <i class="fa-solid fa-circle-info"></i>

                </div>


                <div class="notice-date">

                    <i class="fa-regular fa-calendar"></i>

                    <?php
                    echo htmlspecialchars(
                        $notice['DatePosted']
                    );
                    ?>

                </div>


                <h3>

                    <?php
                    echo htmlspecialchars(
                        $notice['noticeTitle']
                    );
                    ?>

                </h3>


                <p>

                    <?php
                    echo htmlspecialchars(
                        $notice['noticeContent']
                    );
                    ?>

                </p>

            </article>

        <?php endwhile; ?>

    </div>


    <?php if ($totalNotices > 3): ?>

        <div class="view-notices">

            <a href="allNotices.php">

                View All Notices

                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </div>

    <?php endif; ?>

</section>



    <!---SERVICE FAULT STATISTICS--->

<section
    class="section stats-section"
    id="services"
>

    <div class="stats-content">

        <div class="section-heading stats-heading">

            <h2>

                Service Fault Reports Overview

            </h2>

            <p>

                A live snapshot of municipal service
                fault tickets reported across
                Makhanda's wards.

            </p>

        </div>


        <div class="stats-grid">


            <!-- TOTAL -->

            <div
                class="stat-block"
                data-target="<?php
                    echo $ticketStats['TotalTickets'];
                ?>"
            >

                <div class="stat-icon" style="background: #3a7bd5;">

                    <i class="fa-solid fa-clipboard-list"></i>

                </div>


                <div class="stat-number">

                    0

                </div>


                <div class="stat-label">

                    Total Reported

                </div>

            </div>



            <!-- OPEN -->

            <div
                class="stat-block"
                data-target="<?php
                    echo $ticketStats['OpenTickets'];
                ?>"
            >

                <div class="stat-icon" style="background: #e07b39;">

                    <i class="fa-solid fa-folder-open"></i>

                </div>


                <div class="stat-number">

                    0

                </div>


                <div class="stat-label">

                    Open Issues

                </div>

            </div>



            <!-- IN PROGRESS -->

            <div
                class="stat-block"
                data-target="<?php
                    echo $ticketStats['InProgressTickets'];
                ?>"
            >

                <div class="stat-icon" style="background: #f0b429; color: #222;">

                    <i class="fa-solid fa-spinner"></i>

                </div>


                <div class="stat-number">

                    0

                </div>


                <div class="stat-label">

                    In Progress

                </div>

            </div>



            <!-- RESOLVED -->

            <div
                class="stat-block"
                data-target="<?php
                    echo $ticketStats['ResolvedTickets'];
                ?>"
            >

                <div class="stat-icon" style="background: #25A85A;">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div class="stat-number">

                    0

                </div>


                <div class="stat-label">

                    Resolved

                </div>

            </div>

        </div>

    </div>

</section>



<!-- =====================================================
     EXPLORE WARD CTA
====================================================== -->

<section class="section ward-section">

    <div class="ward-container">

        <div class="ward-icon">

            <i class="fa-solid fa-map-location-dot"></i>

        </div>


        <h2>

            Your Ward, Your Community,
            SiConnected Sonke

        </h2>


        <p>

            Explore your ward and discover the people,
            places and services that make your community home.

        </p>


        <a
            href="RENAME.php"
            class="ward-button"
        >

            Explore the Wards

            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>



<!-- =====================================================
     UPCOMING EVENTS
====================================================== -->

<section
    class="section events-section"
    id="events"
>

    <div class="section-heading">

        <h2>

            Upcoming Community Events

        </h2>

        <p>

            Discover events and activities happening
            across the Makhanda community.

        </p>

    </div>


    <?php if (count($events) > 0): ?>

        <div class="event-carousel">


            <button
                class="event-arrow left-arrow"
                onclick="moveEvents(-1)"
                aria-label="Previous event"
            >

                <i class="fa-solid fa-chevron-left"></i>

            </button>


            <div class="event-window">

                <div
                    class="event-track"
                    id="eventTrack"
                >

                    <?php foreach (
                        $events as $event
                    ): ?>

                        <article class="event-card">


                            <?php if (
                                !empty(
                                    $event['posterImage']
                                )
                            ): ?>

                                <img
                                    src="uploads/<?php
                                        echo htmlspecialchars(
                                            $event['posterImage']
                                        );
                                    ?>"
                                    alt="<?php
                                        echo htmlspecialchars(
                                            $event['EventTitle']
                                        );
                                    ?> poster"
                                    class="event-poster"
                                >

                            <?php else: ?>

                                <div class="event-placeholder">

                                    <i class="fa-regular fa-calendar"></i>

                                </div>

                            <?php endif; ?>


                            <div class="event-card-content">

                                <h3 class="event-title">

                                    <?php
                                    echo htmlspecialchars(
                                        $event['EventTitle']
                                    );
                                    ?>

                                </h3>


                                <p class="event-description">

                                    <?php
                                    echo htmlspecialchars(
                                        $event['EventDescription']
                                    );
                                    ?>

                                </p>


                                <div class="event-details">


                                    <p>

                                        <i class="fa-regular fa-calendar"></i>

                                        <strong>Date:</strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $event['EventDate']
                                        );
                                        ?>

                                    </p>


                                    <p>

                                        <i class="fa-regular fa-clock"></i>

                                        <strong>Time:</strong>

                                        <?php
                                        echo htmlspecialchars(
                                            date(
                                                "H:i",
                                                strtotime(
                                                    $event['EventTime']
                                                )
                                            )
                                        );
                                        ?>

                                    </p>


                                    <p>

                                        <i class="fa-solid fa-location-dot"></i>

                                        <strong>Location:</strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $event['EventLocation']
                                        );
                                        ?>

                                    </p>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </div>


            <button
                class="event-arrow right-arrow"
                onclick="moveEvents(1)"
                aria-label="Next event"
            >

                <i class="fa-solid fa-chevron-right"></i>

            </button>

        </div>


        <div
            class="event-dots"
            id="eventDots"
        ></div>


    <?php else: ?>

        <div class="no-events">

            <i class="fa-regular fa-calendar-xmark"></i>

            <p>
                No upcoming events are currently available.
            </p>

        </div>

    <?php endif; ?>

</section>


<!-- =====================================================
     EVENT SUBMISSION
====================================================== -->

<section class="section submission-section">

    <div class="submission-container">

        <div class="submission-icon">

            <i class="fa-solid fa-calendar-plus"></i>

        </div>


        <h2>

            Do you have Something to Share?

        </h2>


        <p>

            Do you have an upcoming event you'd like
            to share with the Makhanda community?

        </p>


        <p>

            Click below to add your event.
            All submissions will be reviewed before
            being published.

        </p>


        <a
            href="add_event.php"
            class="submission-button"
        >

            Add Your Event

            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>
<!-- =====================================================
     REPORT ISSUE
====================================================== -->

<section class="section report-section">

    <div class="report-container">

        <div class="report-icon">

            <i class="fa-solid fa-flag"></i>

        </div>


        <h2>

            See a Problem in Your Community?

        </h2>


        <p>

            Help improve Makhanda by reporting municipal
            service problems such as potholes, water leaks,
            electricity issues and other community concerns.

        </p>


        <a
            href="reportIssue.php"
            class="report-button"
        >

            <i class="fa-solid fa-flag"></i>

            Report an Issue

        </a>

    </div>

</section>




<!-- =========================
     MAKHANDA DAM LEVELS
========================= -->

<section class="dam-levels-section">

    <div class="dam-section-heading">

        <div class="dam-icon">
            <i class="fa-solid fa-droplet"></i>
        </div>

        <div>
            <h2>Makhanda Dam Levels</h2>
            <p>Current dam capacity information for Makhanda</p>
        </div>

    </div>


    <div class="dam-cards">

        <?php foreach ($damDisplay as $damName => $damStyle): ?>

            <?php
            $damPercent = $damLevels[$damName]["percent"] ?? null;

            $damUpdated = $damLevels[$damName]["updated"] ?? null;

            $hasLevel = ($damPercent !== null);

            $damWidth = $hasLevel
                ? max(0, min(100, $damPercent))
                : 0;

            $damText = $hasLevel
                ? rtrim(
                    rtrim(
                        number_format($damPercent, 1, ".", ""),
                        "0"
                    ),
                    "."
                  ) . "%"
                : "--%";

            if ($hasLevel && $damUpdated) {
                $damStatus = "Updated "
                    . date("j M Y", strtotime($damUpdated));
            }
            else {
                $damStatus = "Update pending";
            }
            ?>

            <div class="dam-card <?php echo $damStyle['card']; ?>">

                <div class="dam-card-top">

                    <div class="dam-name">
                        <i class="fa-solid fa-water"></i>
                        <h3><?php echo htmlspecialchars($damName); ?></h3>
                    </div>

                    <span class="dam-status">
                        <?php echo htmlspecialchars($damStatus); ?>
                    </span>

                </div>


                <div class="dam-percentage">
                    <strong><?php echo $damText; ?></strong>
                    <span>
                        <?php echo $hasLevel
                            ? "Dam capacity"
                            : "Capacity unavailable"; ?>
                    </span>
                </div>


                <div class="dam-progress">
                    <div class="progress-fill <?php echo $damStyle['fill']; ?>"
                         style="width: <?php echo $damWidth; ?>%;">
                    </div>
                </div>


                <p class="dam-info">
                    <?php echo htmlspecialchars($damStyle['info']); ?>
                </p>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- SOURCE INFORMATION -->

    <div class="dam-source">

        <i class="fa-solid fa-circle-info"></i>

        <span>
            Dam capacity information sourced from
            Makana Municipality.
        </span>

    </div>

</section>




<!-- =====================================================
     FOOTER
====================================================== -->

<?php include "footer.php"; ?>



<!-- =====================================================
     WEATHER JAVASCRIPT
====================================================== -->

<script>

const temperatureElement =
    document.getElementById("temperature");

const conditionElement =
    document.getElementById("condition");


const weatherCodes = {

    0: "Clear sky",

    1: "Mainly clear",

    2: "Partly cloudy",

    3: "Overcast",

    45: "Foggy",

    48: "Foggy",

    51: "Light drizzle",

    53: "Drizzle",

    55: "Heavy drizzle",

    61: "Light rain",

    63: "Rain",

    65: "Heavy rain",

    71: "Light snow",

    73: "Snow",

    75: "Heavy snow",

    80: "Rain showers",

    81: "Rain showers",

    82: "Heavy rain showers",

    95: "Thunderstorm",

    96: "Thunderstorm with hail",

    99: "Thunderstorm with hail"

};


async function getWeather() {

    try {

        const response = await fetch(
            "https://api.open-meteo.com/v1/forecast" +
            "?latitude=-33.31" +
            "&longitude=26.52" +
            "&current=temperature_2m,weather_code" +
            "&timezone=Africa%2FJohannesburg"
        );


        if (!response.ok) {

            throw new Error(
                "Weather unavailable"
            );

        }


        const data =
            await response.json();


        const temperature =
            Math.round(
                data.current.temperature_2m
            );


        const code =
            data.current.weather_code;


        temperatureElement.textContent =
            temperature + "°C";


        conditionElement.textContent =
            weatherCodes[code] ||
            "Weather information available";

    }

    catch (error) {

        temperatureElement.textContent =
            "Unavailable";

        conditionElement.textContent =
            "Weather information unavailable";

    }

}


getWeather();

</script>



<!-- =====================================================
     STATISTICS ANIMATION
====================================================== -->

<script>

(function () {

    const statBlocks =
        document.querySelectorAll(
            ".stat-block"
        );


    function animateCount(block) {

        const target =
            parseInt(
                block.getAttribute(
                    "data-target"
                ),
                10
            ) || 0;


        const numberElement =
            block.querySelector(
                ".stat-number"
            );


        const duration = 1200;

        const startTime =
            performance.now();


        function step(now) {

            const progress =
                Math.min(
                    (now - startTime) /
                    duration,
                    1
                );


            const eased =
                1 -
                Math.pow(
                    1 - progress,
                    3
                );


            numberElement.textContent =
                Math.floor(
                    eased * target
                );


            if (progress < 1) {

                requestAnimationFrame(step);

            }
            else {

                numberElement.textContent =
                    target;

            }

        }


        requestAnimationFrame(step);

    }


    const observer =
        new IntersectionObserver(
            function (entries) {

                entries.forEach(
                    function (entry) {

                        if (
                            entry.isIntersecting
                        ) {

                            entry.target
                                .classList
                                .add("in-view");


                            animateCount(
                                entry.target
                            );


                            observer.unobserve(
                                entry.target
                            );

                        }

                    }
                );

            },
            {
                threshold: 0.4
            }
        );


    statBlocks.forEach(
        function (block) {

            observer.observe(block);

        }
    );

})();

</script>



<!-- =====================================================
     EVENT CAROUSEL
====================================================== -->

<script>

const eventTrack =
    document.getElementById(
        "eventTrack"
    );


const eventCards =
    document.querySelectorAll(
        ".event-card"
    );


const eventDots =
    document.getElementById(
        "eventDots"
    );


let currentPosition = 0;


const totalEvents =
    eventCards.length;


if (
    eventTrack &&
    eventCards.length > 0 &&
    eventDots
) {


    /* Create dots */

    for (
        let i = 0;
        i < totalEvents;
        i++
    ) {

        const dot =
            document.createElement(
                "span"
            );


        dot.classList.add(
            "event-dot"
        );


        if (i === 0) {

            dot.classList.add(
                "active"
            );

        }


        dot.addEventListener(
            "click",
            function () {

                currentPosition = i;

                updateCarousel();

            }
        );


        eventDots.appendChild(
            dot
        );

    }


    /* Move carousel */

    window.moveEvents =
        function (direction) {

            currentPosition += direction;


            if (
                currentPosition < 0
            ) {

                currentPosition =
                    totalEvents - 1;

            }


            if (
                currentPosition >=
                totalEvents
            ) {

                currentPosition = 0;

            }


            updateCarousel();

        };


    /* Update carousel */

    function updateCarousel() {

        const cardWidth =
            eventCards[0].offsetWidth;


        const gap = 20;


        eventTrack.style.transform =
            "translateX(-" +
            (
                currentPosition *
                (cardWidth + gap)
            ) +
            "px)";


        const dots =
            document.querySelectorAll(
                ".event-dot"
            );


        dots.forEach(
            function (dot) {

                dot.classList.remove(
                    "active"
                );

            }
        );


        if (
            dots[currentPosition]
        ) {

            dots[currentPosition]
                .classList
                .add("active");

        }

    }


    window.addEventListener(
        "resize",
        updateCarousel
    );

}

</script>


</body>

</html>