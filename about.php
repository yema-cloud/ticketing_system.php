<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name = "viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <title> About Page </title>

    </head>
<body> 
     <?php
    include "header.php";
    ?>


    <?php
// about.php
// About Us page for NathiSiConnected. Works for logged-in and logged-out visitors.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loggedIn = isset($_SESSION["username"]);

// Fill these in and the Contact section appears automatically. Leave blank to hide a line.
$contactEmail   = "nathisiconnected@gmail.com";
$contactPhone   = "046 603 6131";
$contactAddress = "1921 High Street Makhanda";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>About Us</title>

    <style>
        .about-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px 56px;
            color: #1f2933;
            line-height: 1.6;
        }
        .about-page h1,
        .about-page h2,
        .about-page h3 {
            font-family: Georgia, "Times New Roman", serif;
            color: #0a3654;
            line-height: 1.2;
        }
        .about-page section { margin-top: 56px; }
        .about-page h2 { font-size: 1.7rem; margin: 0 0 8px; }
        .about-lead { margin: 0 0 24px; max-width: 60ch; color: #4a5560; }

        /* ---------- Hero ---------- */
        .about-hero {
    display: grid;
    grid-template-columns: 1fr;   /* was 1.2fr 1fr */
    text-align: center;           /* add this */
    gap: 36px;
    align-items: center;
    background: #0a3654;
    border-radius: 16px;
    padding: 44px;
}
        .about-hero h1 {
            color: #fff;
            font-size: 2.3rem;
            margin: 0 0 16px;
        }
        .about-hero p { color: #d3e2ee; margin: 0 auto; max-width: 46ch; font-size: 1.05rem; }

        /* Example ticket in the hero */
        .hero-ticket {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }
        .hero-ticket-label { font-size: 0.8rem; color: #6b7783; margin: 0 0 4px; }
        .hero-ticket-title { font-weight: 700; color: #0a3654; margin: 0 0 14px; font-size: 1.05rem; }

        .status-track {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .status-chip {
            font-size: 0.78rem;
            padding: 3px 10px;
            border-radius: 999px;
            background: #eef1f4;
            color: #6b7783;
        }
        .status-chip.done   { background: #e3f0fb; color: #0a3654; }
        .status-chip.active { background: #25a85a; color: #fff; font-weight: 700; }
        .status-line { width: 14px; height: 2px; background: #cfd6dd; }

        .hero-bubble {
            background: #f5f6f7;
            border-left: 4px solid #25a85a;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        .hero-bubble small { display: block; color: #6b7783; margin-top: 4px; }

        /* ---------- Journey ---------- */
        .about-steps {
            list-style: none;
            counter-reset: step;
            margin: 24px 0 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .about-steps li {
            counter-increment: step;
            background: #fff;
            border: 1px solid #dfe5ea;
            border-radius: 12px;
            padding: 20px 18px;
        }
        .about-steps li::before {
            content: counter(step);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #0a3654;
            color: #fff;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .about-steps li:last-child::before { background: #0a3654; }
        .about-steps h3 { margin: 0 0 6px; font-size: 1.05rem; }
        .about-steps p  { margin: 0; font-size: 0.93rem; color: #4a5560; }

        /* ---------- What you can report ---------- */
        .about-chips { display: flex; flex-wrap: wrap; gap: 10px; padding: 0; margin: 0; list-style: none; }
        .about-chips li {
            background: #e3f0fb;
            color: #0a3654;
            border-radius: 999px;
            padding: 8px 16px;
            font-size: 0.95rem;
        }

        /* ---------- Roles ---------- */
        .about-roles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 24px;
        }
        .role-card {
            border-radius: 12px;
            padding: 20px 18px;
            background: #fff;
            border: 1px solid #dfe5ea;
        }
        .role-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }
        .role-resident  .role-icon { background: #fff1e0; }
        .role-councillor .role-icon { background: #e3f0fb; }
        .role-officer   .role-icon { background: #fde7ef; }
        .role-admin     .role-icon { background: #e8f6ec; }
        .role-card h3 { margin: 0 0 6px; font-size: 1.05rem; }
        .role-card p  { margin: 0; font-size: 0.93rem; color: #4a5560; }

        /* ---------- Statement ---------- */
        .about-statement {
            border-left: 5px solid #25a85a;
            padding: 8px 0 8px 24px;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.6rem;
            line-height: 1.35;
            color: #0a3654;
            max-width: 32ch;
            margin: 0;
        }
        .about-statement-note { margin: 16px 0 0 29px; max-width: 60ch; color: #4a5560; }

        /* ---------- Call to action ---------- */
        .about-cta {
            background: #f0f4f8;
            border-radius: 16px;
            padding: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
            justify-content: space-between;
        }
        .about-cta h2 { margin: 0 0 4px; }
        .about-cta p  { margin: 0; color: #4a5560; }
        .about-cta a.cta-button {
            display: inline-block;
            background: #25a85a;
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
        }
        .about-cta a.cta-button:hover { background: #1f9250; }

        .about-contact { margin-top: 20px; color: #4a5560; }
        .about-contact p { margin: 4px 0; }

        /* ---------- Small screens ---------- */
        @media (max-width: 900px) {
            .about-hero  { grid-template-columns: 1fr; padding: 32px 24px; }
            .about-steps { grid-template-columns: repeat(2, 1fr); }
            .about-roles { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 520px) {
            .about-hero h1 { font-size: 1.8rem; }
            .about-steps,
            .about-roles { grid-template-columns: 1fr; }
            .about-statement { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
  

    <div class="about-page">

        <!-- Hero -->
        <div class="about-hero">
            <div>
                <h1><center>When something breaks in Makhanda, someone should hear about it.</center></h1>
                <p>
                    Nathi SiConnected puts residents, ward councillors and municipal services on the
                    same page. One report, one trail, and one place to see what happened next. It is a place
                    to give the residents of Makhanda a voice.
                </p>
            </div>
        </div>

           

        <!-- How a report travels -->
        <section>
            <h2>How a report travels</h2>
            <p class="about-lead">
                <center>No black holes. Every report follows the same path, and you can see where yours is.</center>
            </p>

            <ol class="about-steps">
                <li>
                    <h3>You report it</h3>
                    <p><center>Describe the problem, where it is and how urgent it feels. It takes a couple of minutes.</center></p>
                </li>
                <li>
                    <h3>Your councillor sees it</h3>
                    <p><center>The ticket lands with the councillor for that ward, so it reaches someone who knows the area.</center></p>
                </li>
                <li>
                    <h3>The right service is assigned</h3>
                   <p> <center>Electricity, water, fire and rescue, infrastructure and more: the councillor sends it to the team that can fix it.</center></p>
                </li>
                <li>
                    <h3>Progress, in the open</h3>
                    <p><center>Status changes and councillor comments build a trail until the ticket is marked Resolved.</center></p>
                </li>
            </ol>
        </section>

        <!-- What you can report -->
        <section>
            <h2>What you can report</h2>
            <p class="about-lead"><center>If it affects your street, your neighbourhood or your ward, it belongs here.</center></p>

            <ul class="about-chips">
                <li>Electricity and Infrastructure</li>
                <li>Water and Sanitation Crisis</li>
                <li>Roads and Public Works</li>
                <li>Fire and Floods</li>
                <li>Waste and Environment</li>
                <li>Governance and Financial Mismanagement</li>
            </ul>
        </section>

        <!-- Who is here -->
        <section>
            <h2>Everyone has a part to play</h2>
            <p class="about-lead"><center>Four kinds of people keep the platform moving.</center></p>

            <div class="about-roles">
                <div class="role-card role-resident">
                    <div class="role-icon">&#127968;</div>
                    <h3>Residents</h3>
                    <p><center>Report problems and follow each one from Open to Resolved.</center></p>
                </div>
                <div class="role-card role-councillor">
                    <div class="role-icon">&#128203;</div>
                    <h3>Councillors</h3>
                    <p><center>Handle the tickets in their ward: assign services, update statuses and leave comments.</center></p>
                </div>
                <div class="role-card role-officer">
                    <div class="role-icon">&#128276;</div>
                    <h3>Officers</h3>
                    <p><center>Keep the community informed with notices and events.</center></p>
                </div>
                <div class="role-card role-admin">
                    <div class="role-icon">&#9881;</div>
                    <h3>Administrators</h3>
                    <p><center>Look after users and make sure the platform runs smoothly.</center></p>
                </div>
            </div>
        </section>

        <!-- Statement -->
        <section>
           <center><p class="about-statement">
                Fixing a broken tap shouldn't take three phone calls and a favour.
            </p></center>
            <center><p class="about-statement-note">
                That's the whole idea. Make it easy to speak up, make it clear who is responsible,
                and make progress something everyone can see.
            </p></center>
        </section>

        <!-- Call to action + contact -->
        <section>
            <div class="about-cta">
                <div>
                    <h2>Ready to get connected?</h2>
                    <p>
                        <?php echo $loggedIn
                            ? "Head back to your dashboard to pick up where you left off."
                            : "Log in to report an issue or follow one you have already raised."; ?>
                    </p>
                </div>
                <a class="cta-button" href="<?php echo $loggedIn ? 'dashboard.php' : 'login.php'; ?>">
                    <?php echo $loggedIn ? "Go to my dashboard" : "Log in to get started"; ?>
                </a>
            </div>

            <?php if ($contactEmail !== "" || $contactPhone !== "" || $contactAddress !== ""): ?>
                <div class="about-contact">
                    <h3>Get in touch</h3>
                    <?php if ($contactEmail !== ""): ?>
                        <p>Email: <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a></p>
                    <?php endif; ?>
                    <?php if ($contactPhone !== ""): ?>
                        <p>Phone: <?php echo htmlspecialchars($contactPhone); ?></p>
                    <?php endif; ?>
                    <?php if ($contactAddress !== ""): ?>
                        <p>Visit: <?php echo htmlspecialchars($contactAddress); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <?php include "footer.php"; ?>
</body>
</html>

    