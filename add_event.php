<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title> Add Event / Notice</title>
</head>

<body>

    <?php
    include "header.php";
    include "db_connect.php";

    // Determine what this logged-in user is allowed to submit.
    // Notices are an Officer/Admin-only workflow per the project spec —
    // residents and councillors are scoped to fault tickets instead.
    $userRole = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
    $canSubmitNotices = in_array($userRole, ["Officer", "Administrator"], true);

    // Fetch wards for the dropdown
    $wardResult = $conn->query("SELECT WardID, WardNumber, WardName FROM ward ORDER BY WardNumber");
    ?>

    <div class="form-body">

        <h1>Submit an <?php echo $canSubmitNotices ? 'Event or Notice' : 'Event'; ?></h1>

        <form action="process_submission.php" method="post" enctype="multipart/form-data" class="signup-form" id="eventForm">

            <div class="form-section">
                <p class="form-section-title">Community Organisation Information</p>

                <div class="form-group">
                    <label for="org_name">Community Organisation Name</label>
                    <input type="text" name="org_name" id="org_name" placeholder="e.g. Grahamstown Primary School">
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">-- Select Category --</option>
                        <option value="schools">Schools</option>
                        <option value="ngos">NGOs</option>
                        <option value="spca">SPCA</option>
                        <option value="neighbourhood_watch">Neighbourhood Watch Group</option>
                    </select>
                </div>

                <?php if ($canSubmitNotices): ?>
                    <div class="form-group">
                        <label for="notice_type">What are you submitting?</label>
                        <select name="notice_type" id="notice_type" onchange="toggleEventFields()">
                            <option value="">-- Select Type --</option>
                            <option value="event">Event</option>
                            <option value="notice">Notice</option>
                        </select>
                    </div>
                <?php else: ?>
                    <!-- Residents and Councillors can only ever submit events -->
                    <input type="hidden" name="notice_type" id="notice_type" value="event">
                <?php endif; ?>
            </div>

            <!-- EVENT-ONLY FIELDS -->
            <div class="form-section" id="event_section" <?php echo $canSubmitNotices ? 'style="display:none;"' : ''; ?>>
                <p class="form-section-title">Event Information</p>

                <div class="form-group" id="event_name_row">
                    <label for="eventTitle">Name of the Event</label>
                    <input type="text" name="eventTitle" id="eventTitle" placeholder="e.g. Charity Fundrasing Event" <?php echo !$canSubmitNotices ? 'required' : ''; ?>>
                </div>

                <div class="form-group align-top" id="event_description_row">
                    <label for="eventDescription">Event Description</label>
                    <textarea rows="4" name="eventDescription" id="eventDescription" maxlength="150" placeholder="Briefly describe the event" <?php echo !$canSubmitNotices ? 'required' : ''; ?>></textarea>
                </div>

                <div class="form-group" id="event_start_row">
                    <label for="event_start_date">Start Date</label>
                    <input type="date" name="event_start_date" id="event_start_date" <?php echo !$canSubmitNotices ? 'required' : ''; ?>>
                </div>

                <div class="form-group" id="event_end_row">
                    <label for="event_end_date">End Date</label>
                    <input type="date" name="event_end_date" id="event_end_date">
                </div>

                <div class="form-group" id="event_time_row">
                    <label for="eventTime">Event Time</label>
                    <input type="time" name="eventTime" id="eventTime">
                </div>

                <div class="form-group" id="event_location_row">
                    <label for="eventLocation">Event Location</label>
                    <input type="text" name="eventLocation" id="eventLocation" maxlength="100" placeholder="e.g. Grahamstown Primary School">
                </div>

                <div class="form-group" id="event_poster_row">
                    <label for="eventPoster">Event Poster (image)</label>
                    <input type="file" name="eventPoster" id="eventPoster" accept="image/png, image/jpeg, image/jpg" required>
                    <p class="field-hint">*Required: use a landscape (wider than tall) photo for best results.</p>
                </div>
            </div>

            <!-- NOTICE-ONLY FIELDS -->
            <?php if ($canSubmitNotices): ?>
            <div class="form-section" id="notice_section" style="display:none;">
                <p class="form-section-title">Notice Information</p>

                <div class="form-group">
                    <label for="noticeTitle">Notice Title</label>
                    <input type="text" name="noticeTitle" id="noticeTitle" maxlength="100" placeholder="e.g. Water Interruptions">
                </div>

                <div class="form-group">
                    <label for="noticeLevel">Notice Level</label>
                    <select name="noticeLevel" id="noticeLevel" onchange="toggleWardField()">
                        <option value="community">Community (visible to everyone)</option>
                        <option value="ward">Ward (visible only to that ward's residents)</option>
                    </select>
                </div>

                <div class="form-group" id="ward_select_row" style="display:none;">
                    <label for="ward">Ward</label>
                    <select name="ward" id="ward">
                        <option value="">-- Select Ward --</option>
                        <?php while ($w = $wardResult->fetch_assoc()): ?>
                            <option value="<?php echo (int)$w['WardID']; ?>">
                                Ward <?php echo htmlspecialchars($w['WardNumber']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group align-top">
                    <label for="review">Notice Content</label>
                    <textarea name="review" id="review" maxlength="250" placeholder="Write the notice content here"></textarea>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-buttons">
                <button type="reset" class="btn-secondary" <?php echo $canSubmitNotices ? 'onclick="toggleEventFields()"' : ''; ?>>Reset Form</button>
                <button type="submit">Submit</button>
            </div>

        </form>

    </div>

    <?php include 'footer.php'; ?>

    <?php if ($canSubmitNotices): ?>
    <script>
        function toggleEventFields() {
            var noticeType = document.getElementById('notice_type').value;
            var isEvent = (noticeType === 'event');
            var isNotice = (noticeType === 'notice');

            document.getElementById('event_section').style.display = isEvent ? 'block' : 'none';
            document.getElementById('notice_section').style.display = isNotice ? 'block' : 'none';

            document.getElementById('eventTitle').required = isEvent;
            document.getElementById('eventDescription').required = isEvent;
            document.getElementById('event_start_date').required = isEvent;
            document.getElementById('eventPoster').required = isEvent;
            document.getElementById('noticeTitle').required = isNotice;
            document.getElementById('review').required = isNotice;

            // Ward field depends on both notice_type AND the level dropdown,
            // so re-run that check too every time this fires.
            toggleWardField();
        }

        function toggleWardField() {
            var noticeType = document.getElementById('notice_type').value;
            var level = document.getElementById('noticeLevel').value;
            var showWard = (noticeType === 'notice' && level === 'ward');

            document.getElementById('ward_select_row').style.display = showWard ? 'grid' : 'none';
            document.getElementById('ward').required = showWard;
        }
    </script>
    <?php endif; ?>

</body> 
</html>