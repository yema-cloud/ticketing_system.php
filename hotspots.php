<?php
// hotspots.php
// Separate tab for ward "hot spot" visualisation: which wards have the
// most municipal service faults reported. We don't have per-ward
// geographic boundary data (GeoJSON) in the system, so instead of a
// literal street map, this renders a colour-graded ward grid — the
// standard fallback for hot-spot reporting when only ward-level
// aggregates are available. Darker/red tiles = more faults. A ranked bar
// chart and a plain-text ranking table are included alongside it so the
// same information is available as both a graphic and a text report.

session_start();
include "db_connect.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
if ($role === "Resident") {
    header("Location: dashboard.php");
    exit();
}

$rangeDays = isset($_GET['range']) ? intval($_GET['range']) : 30;
if (!in_array($rangeDays, [7, 30, 90, 365], true)) {
    $rangeDays = 30;
}

$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Ward totals for the hot spot grid + ranking
$sql = "SELECT t.WardID_municipal AS Ward, COUNT(*) AS FaultCount
        FROM municipal_fault_tickets t
        LEFT JOIN municipal_services s ON t.ServiceID = s.ServiceID
        WHERE t.DateReported >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
if ($categoryFilter !== '') {
    $sql .= " AND s.ServiceName = ?";
}
$sql .= " GROUP BY t.WardID_municipal ORDER BY FaultCount DESC";

$stmt = $conn->prepare($sql);
if ($categoryFilter !== '') {
    $stmt->bind_param("is", $rangeDays, $categoryFilter);
} else {
    $stmt->bind_param("i", $rangeDays);
}
$stmt->execute();
$wardCountsRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fill in every ward 1-14, even ones with zero faults, so the grid stays complete
$wardCounts = array_fill(1, 14, 0);
foreach ($wardCountsRows as $row) {
    if ($row['Ward'] >= 1 && $row['Ward'] <= 14) {
        $wardCounts[$row['Ward']] = (int) $row['FaultCount'];
    }
}
$maxCount = max($wardCounts) ?: 1;

// Categories for the filter dropdown
$catResult = $conn->query("SELECT DISTINCT ServiceName FROM municipal_services ORDER BY ServiceName ASC");
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

function heatColor($count, $max) {
    if ($count === 0) {
        return "#eef1f4"; // neutral, no faults
    }
    $ratio = min(1, $count / $max);
    // Interpolate from yellow (low) to red (high)
    $r = 255;
    $g = round(220 - (170 * $ratio));
    $b = round(80 - (80 * $ratio));
    $b = max(0, $b);
    return "rgb($r,$g,$b)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
    <title>Fault Hot Spots</title>
    <style>
        .hotspots-wrap { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .hotspot-filters { margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; }
        .hotspot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 10px;
            margin-bottom: 30px;
        }
        .hotspot-tile {
            border-radius: 8px;
            padding: 18px 10px;
            text-align: center;
            color: #2c3e50;
            font-weight: bold;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15);
        }
        .hotspot-tile .tile-ward { display: block; font-size: 1.1em; }
        .hotspot-tile .tile-count { display: block; font-size: 1.6em; margin-top: 6px; }
        .hotspot-legend { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 0.9em; }
        .legend-swatch { width: 20px; height: 14px; display: inline-block; border-radius: 3px; }
        table.report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.report-table th, table.report-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table.report-table th { background: #2c3e50; color: #fff; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <div class="hotspots-wrap">

        <h1>Service Fault Hot Spots</h1>
        <p>
            Wards shaded darker have reported more service faults in the selected
            period. This highlights where faults are concentrated so resources
            can be prioritised accordingly.
        </p>

        <form method="GET" class="hotspot-filters">
            <label>
                Period:
                <select name="range" onchange="this.form.submit()">
                    <option value="7" <?php echo $rangeDays == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo $rangeDays == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                    <option value="90" <?php echo $rangeDays == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                    <option value="365" <?php echo $rangeDays == 365 ? 'selected' : ''; ?>>Last 12 months</option>
                </select>
            </label>
            <label>
                Category:
                <select name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['ServiceName']); ?>"
                            <?php echo ($categoryFilter === $cat['ServiceName']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['ServiceName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <div class="hotspot-legend">
            Fewer faults
            <span class="legend-swatch" style="background:#eef1f4;"></span>
            <span class="legend-swatch" style="background:rgb(255,180,55);"></span>
            <span class="legend-swatch" style="background:rgb(255,50,0);"></span>
            More faults
        </div>

        <div class="hotspot-grid">
            <?php foreach ($wardCounts as $ward => $count): ?>
                <div class="hotspot-tile" style="background: <?php echo heatColor($count, $maxCount); ?>;">
                    <span class="tile-ward">Ward <?php echo $ward; ?></span>
                    <span class="tile-count"><?php echo $count; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Ward Ranking</h2>
        <div style="max-width:700px;">
            <canvas id="hotspotBar" height="120"></canvas>
        </div>

        <details>
            <summary>View as table</summary>
            <table class="report-table">
                <tr><th>Rank</th><th>Ward</th><th>Faults Reported</th></tr>
                <?php
                arsort($wardCounts);
                $rank = 1;
                foreach ($wardCounts as $ward => $count):
                ?>
                    <tr>
                        <td><?php echo $rank++; ?></td>
                        <td>Ward <?php echo $ward; ?></td>
                        <td><?php echo $count; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </details>

        <p><a href="reports.php">&larr; Back to Full Reports</a></p>
        <p><a href="dashboard.php"><button>Back to Dashboard</button></a></p>

    </div>

    <script>
        const wardLabels = <?php echo json_encode(array_map(fn($w) => "Ward $w", array_keys($wardCounts))); ?>;
        const wardData = <?php echo json_encode(array_values($wardCounts)); ?>;

        new Chart(document.getElementById('hotspotBar'), {
            type: 'bar',
            data: {
                labels: wardLabels,
                datasets: [{
                    label: 'Faults Reported',
                    data: wardData,
                    backgroundColor: wardData.map(v => {
                        const max = Math.max(...wardData, 1);
                        const ratio = Math.min(1, v / max);
                        const g = Math.round(220 - 170 * ratio);
                        const b = Math.max(0, Math.round(80 - 80 * ratio));
                        return v === 0 ? '#eef1f4' : `rgb(255,${g},${b})`;
                    })
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    </script>

    <?php
    $conn->close();
    include "footer.php";
    ?>
</body>
</html>
