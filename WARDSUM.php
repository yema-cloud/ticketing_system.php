<?php
session_start();
include "db_connect.php";

// --- WARD DATA ---
$selectedWard = isset($_GET['ward'])? (int)$_GET['ward'] : 0;
$wards = [
    1 => ["councillor" => "Cllr. Glance Bhutikazi", "party" => "ANC", "phone" => "078 794 6992", "email" => "Btikazi@makana.gov.za", "about" => "Ward 1 is part of Makana Municipality...", "map" => "https://www.makana.gov.za/wp-content/uploads/2013/wardmaps/EC104_Ward%201.pdf"],
    2 => ["councillor" => "Cllr. Anelisa Jacob", "party" => "ANC", "phone" => "082 932 1304", "email" => "AnyJacob@makana.gov.za", "about" => "Ward 2 represents a community within Makana...", "map" => "https://www.makana.gov.za/wp-content/uploads/2013/wardmaps/EC104_Ward%202.pdf"],
    3 => ["councillor" => "Cllr. Andile Mpesu", "party" => "ANC", "phone" => "078 285 3179", "email" => "Mpeyi@makana.gov.za", "about" => "Ward 3 is one of the 14 municipal wards...", "map" => "https://www.makana.gov.za/ward-maps/"],
    4 => ["councillor" => "Cllr. Malaika Mayekiso", "party" => "DA", "phone" => "081 712 8975", "email" => "Malaikaa2@makana.gov.za", "about" => "Ward 4 forms part of Makana Municipality...", "map" => "https://www.makana.gov.za/wp-content/uploads/2013/wardmaps/Gazette_EC104.pdf"],
    5 => ["councillor" => "Cllr. Jabulile Mbeji", "party" => "ANC", "phone" => "078 204 8522", "email" => "Mbeji66@makana.gov.za", "about" => "Ward 5 is one of the 14 wards...", "map" => "https://www.makana.gov.za/ward-maps/"],
    6 => ["councillor" => "Cllr.Sivulele Mkhatshwa", "party" => "ANC", "phone" => "083 337 7754", "email" => "Sivulele312@makana.gov.za", "about" => "Ward 6 forms part of Makana...", "map" => "https://www.makana.gov.za/ward-maps/"],
    7 => ["councillor" => "Cllr. Antony Gamnca", "party" => "ANC", "phone" => "073 904 7315", "email" => "Antony67a@makana.gov.za", "about" => "Ward 7 is one of Makana's 14 wards...", "map" => "https://www.makana.gov.za/ward-maps/"],
    8 => ["councillor" => "Cllr. Seluleko Bhenji", "party" => "DA", "phone" => "074 618 8747", "email" => "Bhenji@makana.gov.za", "about" => "Ward 8 is a municipal ward...", "map" => "https://www.makana.gov.za/ward-maps/"],
    9 => ["councillor" => "Cllr. Vuyo Dlamini", "party" => "ANC", "phone" => "078 269 2338", "email" => "Vuyo184@gmail.com", "about" => "Ward 9 forms part of Makana...", "map" => "https://www.makana.gov.za/ward-maps/"],
    10 => ["councillor" => "Cllr. Fezile Dumisa", "party" => "ANC", "phone" => "083 663 4413", "email" => "Dumisa991@makana.gov.za", "about" => "Ward 10 is one of the 14 wards...", "map" => "https://www.makana.gov.za/wp-content/uploads/2013/wardmaps/EC104_Ward_10.pdf"],
    11 => ["councillor" => "Cllr. Mqindezeli Hamba", "party" => "ANC", "phone" => "063 064 6048", "email" => "Hamba300@makana.gov.za", "about" => "Ward 11 forms part of Makana...", "map" => "https://www.makana.gov.za/ward-maps/"],
    12 => ["councillor" => "Cllr. Sonwabile Nxumalo", "party" => "ANC", "phone" => "078 143 7349", "email" => "mnkwentsha@makana.gov.za", "about" => "Ward 12 is one of Makana's 14 wards...", "map" => "https://www.makana.gov.za/ward-maps/"],
    13 => ["councillor" => "Cllr. Maria Sihlobo", "party" => "ANC", "phone" => "076 993 0660", "email" => "Sihlobo2@makana.gov.za", "about" => "Ward 13 forms part of Makana...", "map" => "https://www.makana.gov.za/ward-maps/"],
    14 => ["councillor" => "Cllr. Velani Namisa", "party" => "INDEPENDENT", "phone" => "078 126 5684", "email" => "nambisa@makana.gov.za", "about" => "Ward 14 is one of the 14 wards...", "map" => "https://www.makana.gov.za/ward-maps/"]
];

// Street search (add verified streets later)
$streetToWard = [];
$streetSearch = trim($_GET['street']?? "");
$streetMatches = [];
if ($streetSearch!== "") {
    foreach ($streetToWard as $street => $wardNumber) {
        if (stripos($street, $streetSearch)!== false) $streetMatches[$street] = $wardNumber;
    }
}

// Notices - filtered by user ward if logged in
$userWard = null;
if (isset($_SESSION["user_id"])) {
    $userStmt = $conn->prepare("SELECT Ward FROM generalusers WHERE UserID =?");
    $userStmt->bind_param("i", $_SESSION["user_id"]);
    $userStmt->execute();
    $userStmt->bind_result($userWard);
    $userStmt->fetch();
    $userStmt->close();
}
$noticeStmt = $conn->prepare("SELECT * FROM notices WHERE Status = 'Open' AND (ward IS NULL OR ward =?) ORDER BY DatePosted DESC LIMIT 3");
$noticeStmt->bind_param("i", $userWard);
$noticeStmt->execute();
$notices = $noticeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$countStmt = $conn->prepare("SELECT COUNT(*) FROM notices WHERE Status = 'Open' AND (ward IS NULL OR ward =?)");
$countStmt->bind_param("i", $userWard);
$countStmt->execute();
$countStmt->bind_result($totalNotices);
$countStmt->fetch();
$countStmt->close();

// Events
$eventsResult = $conn->query("SELECT EventID, EventTitle, EventDescription, EventDate, EventTime, EventLocation FROM events WHERE Status = 'Open' ORDER BY EventDate ASC");
$events = $eventsResult? $eventsResult->fetch_all(MYSQLI_ASSOC) : [];

function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>NathiSiConnected - Home</title>
    <style>
        /* Keep your ward styles scoped */
        #ward-section{max-width:1120px;margin:0 auto;padding:50px 24px 70px}
        #ward-section.ward-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:22px}
        #ward-section.ward-card{background:white;border:1px solid #e7edf4;border-radius:18px;overflow:hidden;display:grid;grid-template-columns:43% 57%;min-height:270px;transition:.25s}
        #ward-section.ward-card:hover{transform:translateY(-4px);box-shadow:0 16px 35px rgba(20,33,61,.10)}
        #ward-section.ward-map{min-height:270px;background:linear-gradient(rgba(18,97,160,.12),rgba(18,97,160,.12)),url("makhanda2.jpg") center/cover;display:flex;align-items:center;justify-content:center}
        #ward-section.ward-number{width:85px;height:85px;border-radius:50%;background:white;color:#1261a0;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 10px 25px rgba(0,0,0,.18)}
        #ward-section.ward-info{padding:25px}
        /* Add the rest of your ward css here... */
        @media(max-width:900px){#ward-section.ward-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php include "header.php";?>

<section class="water-alert"><p><b>ATTENTION!! Makhanda is a Water Scarce Town</b></p></section>

<section class="welcome-section">
    <div class="welcome-text">
        <h2><i>Welcome</i></h2>
        <p>Welcome to NathiSiConnected, a central platform connecting the Makhanda community with important local information and municipal services.</p>
    </div>
    <div class="welcome-image"><img src="makhanda2.jpg" alt="Makhanda"></div>
</section>

<section id="ward-section">
    <div class="ward-section-heading" style="text-align:center;margin-bottom:30px">
        <div style="color:#1261a0;font-size:12px;font-weight:800;letter-spacing:2px">MAKANA MUNICIPALITY</div>
        <h2>Explore Your Ward</h2>
        <p style="color:#6d7888">Find your ward and discover your local councillor.</p>
    </div>

    <div class="search-box" style="background:white;border-radius:18px;padding:28px;box-shadow:0 18px 45px rgba(20,33,61,.10);border:1px solid #edf1f5;margin-bottom:40px">
        <form method="GET" action="index.php#ward-section" style="display:flex;gap:12px">
            <input type="text" name="street" value="<?= e($streetSearch)?>" placeholder="e.g. High Street..." style="flex:1;height:54px;border:1px solid #dce4ec;border-radius:10px;padding:0 17px">
            <button type="submit" style="border:none;background:#1261a0;color:white;border-radius:10px;padding:0 26px;font-weight:700;cursor:pointer">Search</button>
        </form>
    </div>

    <div class="ward-grid">
        <?php foreach ($wards as $num => $w):?>
        <div class="ward-card">
            <div class="ward-map"><div class="ward-number"><span>WARD</span><strong><?= $num?></strong></div></div>
            <div class="ward-info">
                <h3>Ward <?= $num?></h3>
                <div><?= e($w['councillor'])?></div>
                <small style="background:#eef5fa;color:#1261a0;padding:4px 8px;border-radius:20px"><?= e($w['party'])?></small>
                <p style="font-size:12px;color:#6b7788;margin-top:10px"><?= e($w['about'])?></p>
                <a href="index.php?ward=<?= $num?>#ward-section" style="color:#1261a0;font-weight:800;font-size:13px">View Ward →</a>
            </div>
        </div>
        <?php endforeach;?>
    </div>

    <?php if ($selectedWard && isset($wards[$selectedWard])): $w = $wards[$selectedWard];?>
    <div style="background:#0d2742;color:white;margin-top:40px;border-radius:18px;padding:40px">
        <h2>Ward <?= e($selectedWard)?> - <?= e($w['councillor'])?></h2>
        <p><?= e($w['about'])?></p>
        <p>Contact: <a href="tel:<?= e($w['phone'])?>" style="color:#9fd5ff"><?= e($w['phone'])?></a> | <a href="mailto:<?= e($w['email'])?>" style="color:#9fd5ff"><?= e($w['email'])?></a></p>
        <a href="<?= e($w['map'])?>" target="_blank" style="display:inline-block;margin-top:15px;background:white;color:#1261a0;padding:12px 18px;border-radius:9px;font-weight:800;text-decoration:none">View Official Map</a>
    </div>
    <?php endif;?>
</section>

<section class="notice-cards">
    <h2>Community Notices</h2>
    <div class="card-grid">
        <?php foreach($notices as $n):?>
        <article class="card"><div class="card-content">
            <h3><?= e($n['NoticeTitle'])?></h3>
            <p><?= e($n['NoticeContent'])?></p>
            <small>Posted: <?= e($n['DatePosted'])?></small>
        </div></article>
        <?php endforeach;?>
    </div>
    <?php if($totalNotices > 3):?><div class="view-notices"><a href="allNotices.php">View All Notices</a></div><?php endif;?>
</section>

<section class="events-section">
    <h2>Upcoming Events</h2>
    <?php if(count($events)>0):?>
    <div class="event-carousel">
        <button class="event-arrow" onclick="moveEvents(-1)">&#10094;</button>
        <div class="event-window" style="overflow:hidden"><div class="event-track" id="eventTrack" style="display:flex;transition:.3s">
            <?php foreach($events as $ev):?>
            <article class="event-card" style="min-width:300px;margin-right:20px"><div class="event-card-content">
                <h3><?= e($ev['EventTitle'])?></h3><p><?= e($ev['EventDescription'])?></p>
                <p><strong>Date:</strong> <?= e($ev['EventDate'])?> | <?= e($ev['EventTime'])?></p>
                <p><strong>Location:</strong> <?= e($ev['EventLocation'])?></p>
            </div></article>
            <?php endforeach;?>
        </div></div>
        <button class="event-arrow" onclick="moveEvents(1)">&#10095;</button>
    </div>
    <div class="event-dots" id="eventDots"></div>
    <?php else:?><p>No upcoming events.</p><?php endif;?>
</section>

<section class="organisations-submit">
    <h2><i>Have Something to Share?</i></h2>
    <p>Share community notices or events with Makhanda.</p>
    <a href="add_event.php" class="submit-event" style="display:inline-block;background:#1261a0;color:white;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:700">Add your Notice or Event</a>
</section>

<div class="weather-container">
    <section class="weather"><h2>Weather - Makhanda</h2><p>Temp: <span id="temperature">Loading...</span>°C</p><p><span id="condition">Loading...</span></p></section>
</div>

<script>
let currentPosition=0;
const track=document.getElementById("eventTrack");
const cards=document.querySelectorAll(".event-card");
function moveEvents(d){currentPosition+=d;if(currentPosition<0)currentPosition=cards.length-1;if(currentPosition>=cards.length)currentPosition=0;const w=cards[0].offsetWidth+20;track.style.transform="translateX(-"+(currentPosition*w)+"px)";}
fetch("https://api.open-meteo.com/v1/forecast?latitude=-33.31&longitude=26.52&current=temperature_2m,weather_code&timezone=Africa%2FJohannesburg").then(r=>r.json()).then(d=>{document.getElementById("temperature").textContent=d.current.temperature_2m;let c=d.current.weather_code;document.getElementById("condition").textContent=c==0?"Clear":c<=3?"Cloudy":c<=67?"Rain":c<=82?"Rain showers":"Thunderstorm";});
</script>

<?php include 'footer.php';?>
</body>
</html>