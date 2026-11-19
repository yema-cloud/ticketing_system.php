<?php
session_start();
include "db_connect.php";

/*  WARD DATA
   Format: [councillor, party, phone, email, about, map] */

$wards = [
    1  => ["Cllr. Glance Bhutikazi",   "ANC",         "078 794 6992", "Btikazi@makana.gov.za",    "Ward 1 is part of Makana Municipality and serves its local community with municipal services and community representation.", "WardMap photos/ward1Map.png"],
    2  => ["Cllr. Anelisa Jacob",      "ANC",         "082 932 1304", "AnyJacob@makana.gov.za",   "Ward 2 represents a community within Makana Municipality and is supported through local municipal representation.", "WardMap photos/ward2Map.png"],
    3  => ["Cllr. Andile Mpesu",       "ANC",         "078 285 3179", "Mpeyi@makana.gov.za",      "Ward 3 is one of the 14 municipal wards within Makana Municipality.", "WardMap photos/ward3Map.png"],
    4  => ["Cllr. Malaika Mayekiso",   "DA",          "081 712 8975", "Malaikaa2@makana.gov.za",  "Ward 4 forms part of Makana Municipality and is represented through its elected ward councillor.", "WardMap photos/ward4Map.png"],
    5  => ["Cllr. Jabulile Mbeji",     "ANC",         "078 204 8522", "Mbeji66@makana.gov.za",    "Ward 5 is one of the 14 wards within Makana Municipality and has a designated ward councillor representing the community.", "WardMap photos/ward5Map.png"],
    6  => ["Cllr. Sivulele Mkhatshwa", "ANC",         "083 337 7754", "Sivulele312@makana.gov.za","Ward 6 forms part of Makana Municipality and is represented by its elected ward councillor.", "WardMap photos/ward6Map.png"],
    7  => ["Cllr. Antony Gamnca",      "ANC",         "073 904 7315", "Antony67a@makana.gov.za",  "Ward 7 is one of Makana Municipality's 14 wards and serves its local community.", "WardMap photos/ward7Map.png"],
    8  => ["Cllr. Seluleko Bhenji",    "DA",          "074 618 8747", "Bhenji@makana.gov.za",     "Ward 8 is a municipal ward within Makana Municipality and has an elected councillor representing its residents.", "WardMap photos/ward8Map.png"],
    9  => ["Cllr. Vuyo Dlamini",       "ANC",         "078 269 2338", "Vuyo184@gmail.com",        "Ward 9 forms part of Makana Municipality and is represented through its local ward councillor.", "WardMap photos/ward9Map.png"],
    10 => ["Cllr. Fezile Dumisa",      "ANC",         "083 663 4413", "Dumisa991@makana.gov.za",  "Ward 10 is one of the 14 wards within Makana Municipality.", "WardMap photos/ward10Map.png"],
    11 => ["Cllr. Mqindezeli Hamba",   "ANC",         "063 064 6048", "Hamba300@makana.gov.za",   "Ward 11 forms part of Makana Municipality and is represented by its ward councillor.", "WardMap photos/ward11Map.png"],
    12 => ["Cllr. Sonwabile Nxumalo",  "ANC",         "078 143 7349", "mnkwentsha@makana.gov.za", "Ward 12 is one of Makana Municipality's 14 wards and has a designated ward councillor.", "WardMap photos/ward12Map.png"],
    13 => ["Cllr. Maria Sihlobo",      "ANC",         "076 993 0660", "Sihlobo2@makana.gov.za",   "Ward 13 forms part of Makana Municipality and is represented by its elected ward councillor.", "WardMap photos/ward13Map.png"],
    14 => ["Cllr. Velani Namisa",      "INDEPENDENT", "078 126 5684", "nambisa@makana.gov.za",    "Ward 14 is one of Makana Municipality's 14 wards and is represented by its ward councillor.", "WardMap photos/ward14Map.png"],
];

// Turn each ward's array into a named object so the HTML below stays readable.
foreach ($wards as $num => $w) {
    $wards[$num] = (object)[
        "councillor" => $w[0],
        "party"      => $w[1],
        "phone"      => $w[2],
        "email"      => $w[3],
        "about"      => $w[4],
        "map"        => $w[5],
    ];
}

$streetToWard = [];

$selectedWard = (int)($_GET['ward'] ?? 0);

// This page links back to itself, so the ward links keep working even if the
// file is renamed later.
$self = basename($_SERVER['PHP_SELF']);

// Status codes stored on the ticket, turned into readable labels.
$statusLabels = [
    1 => "Open",
    2 => "In Progress",
    3 => "Resolved",
];

// Pull every incident reported in the selected ward.
$wardIncidents = [];
if ($selectedWard && isset($wards[$selectedWard])) {
    $incStmt = $conn->prepare(
        "SELECT t.TicketID, t.Title, t.Description, t.Location, t.Priority,
                t.StatusID, t.DateReported, s.ServiceName
         FROM municipal_fault_tickets t
         LEFT JOIN municipal_services s ON t.ServiceID = s.ServiceID
         WHERE t.WardID_municipal = ?
         ORDER BY t.DateReported DESC"
    );

    if ($incStmt) {
        $incStmt->bind_param("i", $selectedWard);
        $incStmt->execute();
        $incResult = $incStmt->get_result();
        while ($incRow = $incResult->fetch_assoc()) {
            $wardIncidents[] = $incRow;
        }
        $incStmt->close();
    }
}

// Ward-level summary counts, derived from the tickets already fetched above.
$wardStats = [
    "total"      => count($wardIncidents),
    "open"       => 0,
    "inProgress" => 0,
    "resolved"   => 0,
];

foreach ($wardIncidents as $incident) {
    if ((int)$incident['StatusID'] === 1) $wardStats["open"]++;
    elseif ((int)$incident['StatusID'] === 2) $wardStats["inProgress"]++;
    elseif ((int)$incident['StatusID'] === 3) $wardStats["resolved"]++;
}

$streetSearch = trim($_GET['street'] ?? "");

$streetMatches = [];
if ($streetSearch !== "") {
    foreach ($streetToWard as $street => $wardNumber) {
        if (stripos($street, $streetSearch) !== false) {
            $streetMatches[$street] = $wardNumber;
        }
    }
}

// Escape output for safe HTML display.
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>NathiSiConnected | Explore Your Ward</title>
</head>
<body>

<?php include "header.php"; ?>

<h1>Explore Your Ward</h1>
<p>Find your ward, learn about your local councillor, view ward information and access the official ward map.</p>

<!-- WARD LIST -->
<section class="wards-report">
    
    <p>Select a ward to view its local information and official map.</p>

    <div class="ward-cards">

        <?php foreach ($wards as $num => $w): ?>

            <div class="ward-card">

                <h3>Ward <?= $num ?></h3>

                <div class="councillor-info">

                    <div class="councillor-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>

                    <p><?= e($w->councillor) ?></p>
                    <p><?= e($w->party) ?></p>

                </div>

                <!-- ACTION BAR -->
                <div class="ward-actions">

                    <!-- VIEW MAP -->
                    <a 
                        href="<?= e($w->map) ?>" 
                        class="ward-action map-action"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="fas fa-map"></i>
                        <span>View Map</span>
                    </a>

                    <!-- VIEW WARD SUMMARY -->
                    <a 
                        href="<?= $self ?>?ward=<?= $num ?>#ward-detail" 
                        class="ward-action view-action"
                    >
                        <i class="fas fa-user"></i>
                        <span>View Ward</span>
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>
</section>

<!-- SELECTED WARD -->
<?php if ($selectedWard && isset($wards[$selectedWard])): $w = $wards[$selectedWard]; ?>

    <div class="ward-content" id="ward-detail">
        <div class="ward-image">
            <a href="<?= e($w->map) ?>" target="_blank" rel="noopener noreferrer" class="ward-map-card">
                <i class="fas fa-map-marked-alt"></i>
                <span>View Official Ward <?= e($selectedWard) ?> Map (PDF)</span>
            </a>
        </div>

        <div class="ward-right">
            <h2 style="text-align:left;">Ward <?= e($selectedWard) ?></h2>

            <div class="ward-info">
                <p><?= e($w->about) ?></p>
            </div>

            <div class="ward-councillor">
                <div class="councillor-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="councillor-info">
                    <h4><?= e($w->councillor) ?></h4>
                    <p><strong>Political Party:</strong> <?= e($w->party) ?></p>
                    <p><a class="link" href="tel:<?= e($w->phone) ?>"><?= e($w->phone) ?></a></p>
                    <p><a class="link" href="mailto:<?= e($w->email) ?>"><?= e($w->email) ?></a></p>
                    <p><a class="link" href="<?= e($w->map) ?>" target="_blank" rel="noopener noreferrer">🗺 View Official Ward Map</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- WARD SUMMARY STATS -->
    <section class="wards-report">
        <h2>Ward <?= e($selectedWard) ?> Service Fault Summary</h2>

        <div class="stats-grid" style="max-width:800px; margin:0 auto;">
            <div class="stat-block in-view">
                <div class="stat-icon stat-icon-total"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?= $wardStats['total'] ?></div>
                <div class="stat-label">Total Reported</div>
            </div>
            <div class="stat-block in-view">
                <div class="stat-icon stat-icon-open"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number"><?= $wardStats['open'] ?></div>
                <div class="stat-label">Open</div>
            </div>
            <div class="stat-block in-view">
                <div class="stat-icon stat-icon-progress"><i class="fas fa-tools"></i></div>
                <div class="stat-number"><?= $wardStats['inProgress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-block in-view">
                <div class="stat-icon stat-icon-resolved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $wardStats['resolved'] ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
    </section>

    <!-- INCIDENTS REPORTED IN THIS WARD -->
    <section class="wards-report">
        <h2>Incidents Reported in Ward <?= e($selectedWard) ?></h2>

        <?php if ($wardIncidents): ?>
            <div class="table-display">
                <table>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date Reported</th>
                    </tr>
                    <?php foreach ($wardIncidents as $incident): ?>
                        <tr>
                            <td><?= e($incident['TicketID']) ?></td>
                            <td><?= e($incident['ServiceName'] ?? $incident['Title']) ?></td>
                            <td><?= e($incident['Description']) ?></td>
                            <td><?= e($incident['Location']) ?></td>
                            <td><?= e($incident['Priority']) ?></td>
                            <td><?= e($statusLabels[$incident['StatusID']] ?? "Unknown") ?></td>
                            <td><?= e($incident['DateReported']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php else: ?>
            <p>No incidents have been reported in Ward <?= e($selectedWard) ?> yet.</p>
        <?php endif; ?>
    </section>

<?php endif; ?>

<div style="text-align:center;">
    <button type="button" onclick="window.location.href='home.php'"> Back to Home</button>
</div>

<?php include "footer.php"; ?>

</body>
</html>