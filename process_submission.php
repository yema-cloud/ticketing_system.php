<?php
// process_submission.php
// Handles both Event and Notice submissions from add_event.php

session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Error: Invalid request method.");
}

$noticeType = isset($_POST["notice_type"]) ? trim($_POST["notice_type"]) : "";

if ($noticeType !== "event" && $noticeType !== "notice") {
    die("Error: Please choose a Notice Type (Event or Notice). <a href='javascript:history.back()'>Go back</a>");
}



// BRANCH 1: EVENT SUBMISSION -> events table
if ($noticeType === "event") {

    $title       = isset($_POST["eventTitle"])       ? trim($_POST["eventTitle"])       : "";
    $description = isset($_POST["eventDescription"]) ? trim($_POST["eventDescription"]) : "";
    $eventDate   = isset($_POST["event_start_date"])  ? trim($_POST["event_start_date"])  : "";
    $eventTime   = isset($_POST["eventTime"])         ? trim($_POST["eventTime"])         : "";
    $location    = isset($_POST["eventLocation"])     ? trim($_POST["eventLocation"])     : "";

    if ($title === "" || $description === "" || $eventDate === "") {
        die("Error: Event name, description, and start date are required. <a href='javascript:history.back()'>Go back</a>");
    }

    // Poster is compulsory for every event submission.
    if (!isset($_FILES["eventPoster"]) || $_FILES["eventPoster"]["error"] !== UPLOAD_ERR_OK) {
        die("Error: An event poster image is required. <a href='javascript:history.back()'>Go back</a>");
    }

    $imageInfo = getimagesize($_FILES["eventPoster"]["tmp_name"]);

    if ($imageInfo === false) {
        die("Invalid file type. Please upload a valid image.");
    }

    $mimeType = $imageInfo["mime"];
    $allowedTypes = ["image/jpeg", "image/png", "image/gif"];

    if (!in_array($mimeType, $allowedTypes)) {
        die("Invalid file type.");
    }

    // Aspect ratio check for image size
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $aspectRatio = $width / $height;

    if ($aspectRatio < 1.3 || $aspectRatio > 2.2) {
        die("Please upload a landscape-oriented image (wider than it is tall) for the event poster. <a href='javascript:history.back()'>Go back</a>");
    }

    $uploadDir = __DIR__ . "/uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Unique filename so two people's "poster.jpg" don't overwrite each other
    $ext = pathinfo($_FILES["eventPoster"]["name"], PATHINFO_EXTENSION);
    $posterFilename = uniqid("poster_", true) . "." . $ext;

    if (!move_uploaded_file($_FILES["eventPoster"]["tmp_name"], $uploadDir . $posterFilename)) {
        die("Error: Could not save uploaded poster.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO events (eventTitle, eventDescription, eventTime, eventDate, eventLocation, posterImage)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssss",
        $title,
        $description,
        $eventTime,
        $eventDate,
        $location,
        $posterFilename
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: add_event.php?submission=success");
        exit();
    } else {
        die("Error saving event: " . $stmt->error);
    }

}
// BRANCH 2: NOTICE SUBMISSION -> notices table


elseif ($noticeType === "notice") {
     // Server-side enforcement: only Officers and Admins may submit notices.
    // This check matters far more than hiding the dropdown option in
    // add_event.php, because that hidden option only stops the form's UI —
    // anyone could still send notice_type=notice directly via a raw POST
    // request, bypassing the HTML entirely. This is the check that can't
    // be bypassed by editing the page or skipping the form.
    $allowedNoticeRoles = ["Officer", "Administrator"];
    $userRole = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
    if (!in_array($userRole, $allowedNoticeRoles, true)) {
        http_response_code(403);
        die("Error: Only Communications Officers and Administrators may submit notices. <a href='javascript:history.back()'>Go back</a>");
    }

    $title       = isset($_POST["noticeTitle"])   ? trim($_POST["noticeTitle"])   : "";
    $content     = isset($_POST["review"])       ? trim($_POST["review"])       : "";
    $noticeLevel = isset($_POST["noticeLevel"])  ? trim($_POST["noticeLevel"])  : "community";
    $ward        = null;

    if ($title === "" || $content === "") {
        die("Error: Title and content are required. <a href='javascript:history.back()'>Go back</a>");
    }

    if ($noticeLevel === "ward") {
        $ward = isset($_POST["ward"]) ? (int)$_POST["ward"] : 0;

        if ($ward <= 0) {
            die("Error: Please select a ward for a ward-level notice. <a href='javascript:history.back()'>Go back</a>");
        }
    } elseif ($noticeLevel !== "community") {
        die("Error: Invalid notice level. <a href='javascript:history.back()'>Go back</a>");
    }


    // officerID: only meaningful if the person submitting is a logged-in officer.
    // Falls back to NULL (matches the column's default) if nobody's logged in.
    $officerID = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

    $stmt = $conn->prepare(
        "INSERT INTO notices (noticeTitle, noticeContent, officerID, noticeLevel, ward, Status)
         VALUES (?, ?, ?, ?, ?, 'Pending')"
    );

    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("ssiss", $title, $content, $officerID, $noticeLevel, $ward);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: add_event.php?submission=success");
        exit();
    } else {
        die("Error saving notice: " . $stmt->error);
    }
}
?>