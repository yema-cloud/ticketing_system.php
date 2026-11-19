<?php
// reports.php
session_start();
include "db_connect.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Residents don't get this page - it's a municipal management view.
if ($_SESSION['role'] === "Resident") {
    header("Location: dashboard.php");
    exit();
}

// ---- Date range filter (7, 30 or 90 days; defaults to 30) ----
$rangeDays = isset($_GET['range']) ? intval($_GET['range']) : 30;
if (!in_array($rangeDays, [7, 30, 90], true)) {
    $rangeDays = 30;
}

$prevWindowStart = $rangeDays * 2;

// ---- Helpers ----

function faultCounts(mysqli $conn, int $fromDays, ?int $toDays = null): array
{
    $sql = "SELECT COUNT(*) AS total,
                   SUM(StatusID = 1) AS open_count,
                   SUM(StatusID = 3) AS resolved_count
            FROM municipal_fault_tickets
            WHERE DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    if ($toDays !== null) {
        $sql .= " AND DateReported < DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    }

    $stmt = $conn->prepare($sql);
    if ($toDays !== null) {
        $stmt->bind_param("ii", $fromDays, $toDays);
    } else {
        $stmt->bind_param("i", $fromDays);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'total'    => (int) $row['total'],
        'open'     => (int) $row['open_count'],
        'resolved' => (int) $row['resolved_count'],
    ];
}

function avgTurnaround(mysqli $conn, int $fromDays, ?int $toDays = null): ?float
{
    $sql = "SELECT AVG(DATEDIFF(DateResolved, DateReported)) AS avg_days
            FROM municipal_fault_tickets
            WHERE StatusID = 3 AND DateResolved IS NOT NULL
              AND DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    if ($toDays !== null) {
        $sql .= " AND DateReported < DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    }

    $stmt = $conn->prepare($sql);
    if ($toDays !== null) {
        $stmt->bind_param("ii", $fromDays, $toDays);
    } else {
        $stmt->bind_param("i", $fromDays);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['avg_days'] !== null ? round((float) $row['avg_days'], 1) : null;
}

// How many resolved tickets (with a recorded DateResolved) feed the turnaround average.
function turnaroundSampleSize(mysqli $conn, int $days): int
{
    $sql = "SELECT COUNT(*) AS n
            FROM municipal_fault_tickets
            WHERE StatusID = 3 AND DateResolved IS NOT NULL
              AND DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) $row['n'];
}

function statusCounts(mysqli $conn, int $days): array
{
    $sql = "SELECT StatusID, COUNT(*) AS total
            FROM municipal_fault_tickets
            WHERE DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY StatusID";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[(int) $row['StatusID']] = (int) $row['total'];
    }
    $stmt->close();

    return $counts;
}

function categoryCounts(mysqli $conn, int $days): array
{
    $sql = "SELECT COALESCE(s.ServiceName, 'Unassigned') AS CategoryName, COUNT(t.TicketID) AS total
            FROM municipal_fault_tickets t
            LEFT JOIN municipal_services s ON t.ServiceID = s.ServiceID
            WHERE t.DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY CategoryName
            ORDER BY total DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'name'  => $row['CategoryName'],
            'count' => (int) $row['total']
        ];
    }
    $stmt->close();

    return $categories;
}

function wardCounts(mysqli $conn, int $days): array
{
    $sql = "SELECT 
                COALESCE(w.WardNumber, t.WardID_municipal, 'Unassigned') AS WardLabel,
                COUNT(t.TicketID) AS total,
                SUM(t.StatusID = 1) AS open_count,
                SUM(t.StatusID = 3) AS resolved_count
            FROM municipal_fault_tickets t
            LEFT JOIN ward w ON t.WardID_municipal = w.WardID
            WHERE t.DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY WardLabel
            ORDER BY total DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $wards = [];
    while ($row = $result->fetch_assoc()) {
        $wards[] = [
            'label'    => is_numeric($row['WardLabel']) ? 'Ward ' . $row['WardLabel'] : $row['WardLabel'],
            'total'    => (int) $row['total'],
            'open'     => (int) $row['open_count'],
            'resolved' => (int) $row['resolved_count']
        ];
    }
    $stmt->close();

    return $wards;
}

// Fetch Hotspot Location aggregations
function hotspotCounts(mysqli $conn, int $days): array
{
    $sql = "SELECT 
                Location, 
                COALESCE(w.WardNumber, t.WardID_municipal, '—') AS WardLabel,
                COUNT(t.TicketID) AS total
            FROM municipal_fault_tickets t
            LEFT JOIN ward w ON t.WardID_municipal = w.WardID
            WHERE t.DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND Location IS NOT NULL AND Location != ''
            GROUP BY Location, WardLabel
            ORDER BY total DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $hotspots = [];
    while ($row = $result->fetch_assoc()) {
        $hotspots[] = [
            'location' => $row['Location'],
            'ward'     => is_numeric($row['WardLabel']) ? 'Ward ' . $row['WardLabel'] : $row['WardLabel'],
            'count'    => (int) $row['total']
        ];
    }
    $stmt->close();

    return $hotspots;
}

function computeDelta($current, $previous, $lowerIsBetter = false)
{
    if ($previous === null || $previous == 0) {
        if ($current == 0) {
            return ['arrow' => '–', 'class' => 'neutral', 'label' => 'No data yet'];
        }
        return ['arrow' => '▲', 'class' => $lowerIsBetter ? 'negative' : 'positive', 'label' => 'New activity'];
    }

    $pct = round((($current - $previous) / $previous) * 100);
    if ($pct == 0) {
        return ['arrow' => '–', 'class' => 'neutral', 'label' => 'No change'];
    }

    $arrow  = $pct > 0 ? '▲' : '▼';
    $isGood = $lowerIsBetter ? ($pct < 0) : ($pct > 0);
    $label  = ($pct > 0 ? '+' : '') . $pct . '% vs. previous period';

    return ['arrow' => $arrow, 'class' => $isGood ? 'positive' : 'negative', 'label' => $label];
}

// ---- KPI numbers ----
$current = faultCounts($conn, $rangeDays);
$prev    = faultCounts($conn, $prevWindowStart, $rangeDays);

$currentResolutionRate = $current['total'] > 0 ? round(($current['resolved'] / $current['total']) * 100) : 0;
$prevResolutionRate    = $prev['total'] > 0 ? round(($prev['resolved'] / $prev['total']) * 100) : 0;

$totalDelta          = computeDelta($current['total'], $prev['total'], true);
$openDelta           = computeDelta($current['open'], $prev['open'], true);
$resolutionRateDelta = computeDelta($currentResolutionRate, $prevResolutionRate, false);

$turnaroundAvailable  = false;
$currentAvgTurnaround = null;
$turnaroundSample     = 0;
$turnaroundDelta      = ['arrow' => '–', 'class' => 'neutral', 'label' => 'Unavailable'];

$colCheck = $conn->query("SHOW COLUMNS FROM municipal_fault_tickets LIKE 'DateResolved'");
if ($colCheck && $colCheck->num_rows > 0) {
    $turnaroundAvailable  = true;
    $currentAvgTurnaround = avgTurnaround($conn, $rangeDays);
    $turnaroundSample     = turnaroundSampleSize($conn, $rangeDays);
    $prevAvgTurnaround    = avgTurnaround($conn, $prevWindowStart, $rangeDays);
    // Only compare when both periods actually have resolved tickets with a DateResolved
    if ($currentAvgTurnaround !== null && $prevAvgTurnaround !== null) {
        $turnaroundDelta = computeDelta($currentAvgTurnaround, $prevAvgTurnaround, true);
    } else {
        $turnaroundDelta = ['arrow' => '–', 'class' => 'neutral', 'label' => 'Not enough data'];
    }
}

// ---- Status bar graph ----
$statusChart = [
    ['label' => 'Open',        'id' => 1, 'class' => 'bar-open'],
    ['label' => 'In Progress', 'id' => 2, 'class' => 'bar-progress'],
    ['label' => 'Resolved',    'id' => 3, 'class' => 'bar-resolved'],
    ['label' => 'Declined',    'id' => 4, 'class' => 'bar-declined'],
];

$statusTotals  = statusCounts($conn, $rangeDays);
$chartMaxPx    = 200;
$chartTotal    = 0;
$chartMaxCount = 1;

foreach ($statusChart as $i => $bar) {
    $count = $statusTotals[$bar['id']] ?? 0;
    $statusChart[$i]['count'] = $count;
    $chartTotal += $count;
    $chartMaxCount = max($chartMaxCount, $count);
}

foreach ($statusChart as $i => $bar) {
    $statusChart[$i]['height']  = (int) round(($bar['count'] / $chartMaxCount) * $chartMaxPx);
    $statusChart[$i]['percent'] = $chartTotal > 0 ? round(($bar['count'] / $chartTotal) * 100) : 0;
}

// ---- Category Statistics ----
$categoryList = categoryCounts($conn, $rangeDays);
$catMaxCount  = 1;
foreach ($categoryList as $cat) {
    $catMaxCount = max($catMaxCount, $cat['count']);
}

// ---- Ward Statistics ----
$wardList = wardCounts($conn, $rangeDays);

// ---- Hotspot Statistics ----
$hotspotList = hotspotCounts($conn, $rangeDays);
$hotspotTotalSum = 0;
foreach ($hotspotList as $hs) {
    $hotspotTotalSum += $hs['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Service Fault Reports</title>
    <style>
        :root {
            --rp-navy: #0a3654;
            --rp-navy-mid: #194975;
            --rp-border: #d7dee5;
            --rp-text: #0a3654;
            --rp-text-muted: #5a6b7d;
        }

        body.reports-page {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            text-align: left;
        }

        .reports-wrap {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 30px 60px;
            color: var(--rp-text);
        }

        .reports-page h1 {
            color: var(--rp-navy);
            text-align: left;
            font-size: 1.9rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .reports-intro {
            color: var(--rp-text-muted);
            margin: 0 0 25px;
            text-align: left;
        }

        .reports-filters {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 30px;
        }

        .reports-filters label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--rp-text-muted);
        }

        .reports-filters select {
            background-color: #ffffff;
            color: var(--rp-navy);
            border: 1px solid var(--rp-border);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.9rem;
        }

        /* KPI tiles */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background-color: var(--rp-navy);
            border-radius: 10px;
            padding: 20px 22px;
        }

        .kpi-label { color: #a9c3de; font-size: 0.85rem; margin-bottom: 10px; }
        .kpi-value { font-size: 2.1rem; font-weight: 700; color: #ffffff; line-height: 1; margin-bottom: 10px; }
        .kpi-delta { font-size: 0.85rem; font-weight: 600; }
        .kpi-delta.positive { color: #6be3a4; }
        .kpi-delta.negative { color: #f5b06b; }
        .kpi-delta.neutral  { color: #a9c3de; }

        /* Chart cards */
        .chart-card {
            background-color: #ffffff;
            border: 1px solid var(--rp-border);
            border-radius: 10px;
            padding: 22px 26px 26px;
            margin-bottom: 30px;
        }

        .chart-card h2 {
            color: var(--rp-navy);
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .chart-sub {
            color: var(--rp-text-muted);
            font-size: 0.85rem;
            margin: 0 0 22px;
        }

        .chart-plot {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            gap: 20px;
            height: 250px;
            border-bottom: 2px solid var(--rp-border);
            padding: 0 10px;
        }

        .chart-col {
            flex: 1;
            max-width: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .chart-value { font-size: 1.1rem; font-weight: 700; color: var(--rp-navy); margin-bottom: 6px; }
        .chart-bar { width: 100%; border-radius: 6px 6px 0 0; }
        .bar-open     { background-color: #e08a2e; }
        .bar-progress { background-color: #194975; }
        .bar-resolved { background-color: #2e9d68; }
        .bar-declined { background-color: #b0413e; }

        .chart-labels { display: flex; justify-content: space-around; gap: 20px; margin-top: 10px; }
        .chart-label { flex: 1; max-width: 160px; text-align: center; font-size: 0.85rem; font-weight: 600; }
        .chart-label span { display: block; font-weight: 400; color: var(--rp-text-muted); font-size: 0.8rem; }

        /* Category Horizontal Bar Styles */
        .category-list { display: flex; flex-direction: column; gap: 16px; }
        .category-row { display: grid; grid-template-columns: 200px 1fr 60px; align-items: center; gap: 15px; }
        .category-name { font-size: 0.9rem; font-weight: 600; color: var(--rp-navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .category-track { background-color: #f0f4f8; border-radius: 6px; height: 24px; width: 100%; overflow: hidden; }
        .category-fill { background-color: var(--rp-navy-mid); height: 100%; border-radius: 6px; transition: width 0.3s ease; }
        .category-count { font-size: 0.95rem; font-weight: 700; color: var(--rp-navy); text-align: right; }

        /* Ward Table Styles */
        .ward-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ward-table th { background-color: #f0f4f8; color: var(--rp-navy); text-align: left; padding: 12px 16px; font-size: 0.85rem; font-weight: 700; border-bottom: 2px solid var(--rp-border); }
        .ward-table td { padding: 12px 16px; font-size: 0.9rem; border-bottom: 1px solid var(--rp-border); }
        .ward-table tr:hover { background-color: #f8fafc; }
        .badge-open { color: #e08a2e; font-weight: 700; }
        .badge-resolved { color: #2e9d68; font-weight: 700; }

        /* Treemap Visualization Styles */
        .treemap-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
            min-height: 380px;
        }

        .treemap-tile {
            border-radius: 6px;
            padding: 14px;
            box-sizing: border-box;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .treemap-tile:hover {
            transform: scale(1.01);
            filter: brightness(1.08);
            cursor: pointer;
        }

        .treemap-location {
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.2;
            word-break: break-word;
        }

        .treemap-ward {
            font-size: 0.75rem;
            opacity: 0.85;
            margin-top: 4px;
        }

        .treemap-count {
            font-size: 1.2rem;
            font-weight: 800;
            margin-top: 10px;
        }
    </style>
</head>
<body class="reports-page">
    <?php include "header.php"; ?>

    <div class="reports-wrap">

        <h1>Service Fault Reports</h1>
        <p class="reports-intro">Showing data for All wards.</p>

        <form method="GET" class="reports-filters">
            <label>
                Date range:
                <select name="range" onchange="this.form.submit()">
                    <option value="7"  <?php echo ($rangeDays == 7)  ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo ($rangeDays == 30) ? 'selected' : ''; ?>>Last 30 days</option>
                    <option value="90" <?php echo ($rangeDays == 90) ? 'selected' : ''; ?>>Last 90 days</option>
                </select>
            </label>
        </form>

        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Total Faults Reported</div>
                <div class="kpi-value"><?php echo (int) $current['total']; ?></div>
                <div class="kpi-delta <?php echo $totalDelta['class']; ?>">
                    <?php echo $totalDelta['arrow']; ?> <?php echo htmlspecialchars($totalDelta['label']); ?>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Currently Open</div>
                <div class="kpi-value"><?php echo (int) $current['open']; ?></div>
                <div class="kpi-delta <?php echo $openDelta['class']; ?>">
                    <?php echo $openDelta['arrow']; ?> <?php echo htmlspecialchars($openDelta['label']); ?>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Resolution Rate</div>
                <div class="kpi-value"><?php echo (int) $currentResolutionRate; ?>%</div>
                <div class="kpi-delta <?php echo $resolutionRateDelta['class']; ?>">
                    <?php echo $resolutionRateDelta['arrow']; ?> <?php echo htmlspecialchars($resolutionRateDelta['label']); ?>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Avg. Turnaround (days)</div>
                <div class="kpi-value">
                    <?php echo ($turnaroundAvailable && $currentAvgTurnaround !== null) ? htmlspecialchars($currentAvgTurnaround) : '—'; ?>
                </div>
                <?php if ($turnaroundAvailable): ?>
                    <div style="font-size: 0.8rem; color: #a9c3de; margin-bottom: 8px;">
                        <?php echo $turnaroundSample > 0
                            ? 'Based on ' . $turnaroundSample . ' resolved ticket' . ($turnaroundSample === 1 ? '' : 's')
                            : 'No resolved tickets with a date yet'; ?>
                    </div>
                <?php endif; ?>
                <div class="kpi-delta <?php echo $turnaroundDelta['class']; ?>">
                    <?php echo $turnaroundDelta['arrow']; ?> <?php echo htmlspecialchars($turnaroundDelta['label']); ?>
                </div>
            </div>
        </div>

        <!-- 1. Faults By Status -->
        <div class="chart-card">
            <h2>Faults by Status</h2>
            <p class="chart-sub">
                <?php echo (int) $chartTotal; ?> fault<?php echo $chartTotal === 1 ? '' : 's'; ?>
                reported in the last <?php echo (int) $rangeDays; ?> days, all wards.
            </p>

            <?php if ($chartTotal > 0): ?>
                <div class="chart-plot">
                    <?php foreach ($statusChart as $bar): ?>
                        <div class="chart-col">
                            <div class="chart-value"><?php echo (int) $bar['count']; ?></div>
                            <div class="chart-bar <?php echo $bar['class']; ?>"
                                 style="height: <?php echo (int) $bar['height']; ?>px;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-labels">
                    <?php foreach ($statusChart as $bar): ?>
                        <div class="chart-label">
                            <?php echo htmlspecialchars($bar['label']); ?>
                            <span><?php echo (int) $bar['percent']; ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No faults were reported in this period.</p>
            <?php endif; ?>
        </div>

        <!-- 2. Faults By Service Category -->
        <div class="chart-card">
            <h2>Faults by Service Category</h2>
            <p class="chart-sub">Distribution of reported faults across municipal departments over the last <?php echo (int) $rangeDays; ?> days.</p>

            <?php if (!empty($categoryList)): ?>
                <div class="category-list">
                    <?php foreach ($categoryList as $cat): 
                        $pctWidth = round(($cat['count'] / $catMaxCount) * 100);
                    ?>
                        <div class="category-row">
                            <div class="category-name" title="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </div>
                            <div class="category-track">
                                <div class="category-fill" style="width: <?php echo $pctWidth; ?>%;"></div>
                            </div>
                            <div class="category-count"><?php echo (int) $cat['count']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No category data available for this period.</p>
            <?php endif; ?>
        </div>

        <!-- 3. Faults By Ward Statistics -->
        <div class="chart-card">
            <h2>Service Fault Statistics per Ward</h2>
            <p class="chart-sub">Breakdown of reported faults across municipal wards over the last <?php echo (int) $rangeDays; ?> days.</p>

            <?php if (!empty($wardList)): ?>
                <table class="ward-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Total Faults</th>
                            <th>Open Faults</th>
                            <th>Resolved Faults</th>
                            <th>Resolution Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wardList as $ward): 
                            $wardResRate = $ward['total'] > 0 ? round(($ward['resolved'] / $ward['total']) * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ward['label']); ?></strong></td>
                                <td><?php echo (int) $ward['total']; ?></td>
                                <td class="badge-open"><?php echo (int) $ward['open']; ?></td>
                                <td class="badge-resolved"><?php echo (int) $ward['resolved']; ?></td>
                                <td><?php echo (int) $wardResRate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No ward data available for this period.</p>
            <?php endif; ?>
        </div>

        <!-- 4. Fault Hot Spot TreeMap -->
        <div class="chart-card">
            <h2>Service Fault Hot Spots (TreeMap)</h2>
            <p class="chart-sub">TreeMap showing geographical hotspot concentrations over the last <?php echo (int) $rangeDays; ?> days. Block size reflects total fault volume; each location has its own colour.</p>

            <?php if (!empty($hotspotList) && $hotspotTotalSum > 0): ?>
                <?php
                // One distinct colour per tile (the query returns at most 10 hotspots).
                // All are dark enough for the white text on the tiles.
                $treemapPalette = [
                    '#194975', // navy
                    '#25845a', // green
                    '#c9711a', // orange
                    '#b0413e', // red
                    '#6f4ba0', // purple
                    '#1c8495', // teal
                    '#b23c85', // magenta
                    '#5f7f1f', // olive
                    '#4b5d67', // slate
                    '#a08a00', // gold
                ];
                ?>
                <div class="treemap-container">
                    <?php foreach ($hotspotList as $i => $hs): 
                        $share = ($hs['count'] / $hotspotTotalSum);
                        $flexGrow = max(1, round($share * 100)); // Flex scaling relative to total share

                        $bgColor = $treemapPalette[$i % count($treemapPalette)];
                    ?>
                        <div class="treemap-tile" style="flex: <?php echo $flexGrow; ?> 1 180px; background-color: <?php echo $bgColor; ?>;">
                            <div>
                                <div class="treemap-location"><?php echo htmlspecialchars($hs['location']); ?></div>
                                <div class="treemap-ward"><?php echo htmlspecialchars($hs['ward']); ?></div>
                            </div>
                            <div class="treemap-count">
                                <?php echo (int) $hs['count']; ?> <span style="font-size:0.75rem; font-weight:400;">Faults</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No location hotspot data available for this period.</p>
            <?php endif; ?>
        </div>

    </div>

    <?php
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>