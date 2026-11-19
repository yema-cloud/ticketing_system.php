<?php
session_start();
$userRole = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Help and Documentation</title>
</head>
<body>
    
<?php include "header.php"; ?>

<div class ="form-body" style="max-width: 800px;">
    <h1>Help &amp; Frequently Asked Questions</h1>
    <p style="text-align:center; color:#555;">
        Search for FAQs or browse the topics below.
    </p>

    <input type="text" id="helpSearch" class="help-search-box" placeholder="Search for help, e.g. 'report a fault', 'ward notice'..." onkeyup="filterHelp()">

    <div id="helpResults">

    <!--Residents and Ward Councillors only-->
    <div class="help-section" data-roles="resident, councillor, guest">
      <h3> Reporting a Fault</h3>

      <div class="help-item">
        <p class="help-q"> How do I report a municipal fault (water, electricity, roads)?</p>
        <p class="help-a">Make an account or log in, then go to <strong> Report Incident</strong> from your dashboard or control panel. Fill in the form, then submit. Your ward councillor will review it. </p>
      </div>

      <div class="help-item">
        <p class="help-q">How do I check the status of a fault I reported?</p>
        <p class="help-a"> Go to <strong>View Reported Incident</strong> from your dashboard or control panel. Each ticket shows its current status: Open, Reported, or Resolved. </p>

      </div>
    </div>

    <!---Ward Councillor Only--->
    <div class="help-section" data-roles="officer">
        <h3> Managing Ward Tickets</h3>

        <div class="help-item">
            <p class="help-q">How do I update a ticket's status?</p>
            <p class="help-a">Open <strong>Update Status</strong> from your dashboard or control panel, select the ticket, and change it from Open to In Progress or Resolved or Decline. Resolved tickets are considered closed.</p>
        </div>
        
        <div class="help-item">
            <p class="help-q">Can I add notes to a fault ticket?</p>
            <p class="help-a">Yes. Open the ticket from your ward's ticket list and add a note before updating its status. Notes help track what action has been taken.</p>

        </div>
    </div>

    <!--Officer only-->
    <div class="help-section" data-roles="officer">
        <h3>Posting Notices</h3>
        
        <div class="help-item">
            <p class="help-q">How do I post a community notice?</p>
            <p class="help-a">Go to <strong>Add your Notice or Event</strong>, choose <em>Notice</em> as the type, then pick Community-wide or Ward-specific. An Administrator must approve it before it appears publicly.</p>
        </div>

        <div class="help-item">
            <p class="help-q">What is the difference between a community and a ward notice?</p>
            <p class="help-a">Community notices show to every visitor. Ward notices only show to residents registered in that specific ward.</p>
        </div>

    </div>

    <!--Administrator only-->
    <div class="help-section" data-roles="administrator">
            <h3>Managing Users &amp; Content</h3>

            <div class="help-item">
                <p class="help-q">How do I approve a new user account?</p>
                 <p class="help-a">Go to <strong>Display User</strong>, find the pending account, and set their status to Approved or Declined.</p>

            </div>
            <div class="help-item">
                <p class="help-q">How do I approve a submitted event or notice?</p>
                <p class="help-a">Go to <strong>Manage Notices</strong> or <strong>Manage Events</strong> from your dashboard. Pending items show an Approve/Decline option.</p>
            </div>
        </div>

        <!--Everyone who visits the website--->
        <div class="help-section" data-roles="resident,councillor,officer,administrator,guest">
            <h3 id="account-approval-help">Account &amp; Access</h3>

            <div class="help-item">
                <p class="help-q">I signed up but can't log in yet, why?</p>
                <p class="help-a">New accounts need to be approved by a System Administrator first. You'll be able to log in once your account is verified.</p>
            </div>

            <div class="help-item">
                <p class="help-q">How do I find information about my ward?</p>
                <p class="help-a">Click <strong>Wards</strong> in the navigation menu.</p>
            </div>

        </div>

    </div>
    <p id="noResults" style="display:none; text-align:center; color:#888;">
        No results found. Try a different search term.
    </p>
</div>

<script>
function filterHelp() {
    const query = document.getElementById('helpSearch').value.toLowerCase();
    const items = document.querySelectorAll('.help-item');
    let visibleCount = 0;

    items.forEach(function(item) {
        const text = item.textContent.toLowerCase();
        const match = text.includes(query);
        item.style.display = match ? 'block' : 'none';
        if (match) visibleCount++;
    });

     // Hide section headers with no visible items left
    document.querySelectorAll('.help-section').forEach(function(section) {
        const visibleItems = section.querySelectorAll('.help-item[style="display: block;"]');
        section.style.display = visibleItems.length > 0 || query === '' ? 'block' : 'none';
    });

    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
}
   // Show only sections relevant to this user's role on page load
document.addEventListener('DOMContentLoaded', function() {
    const currentRole = "<?php echo strtolower($userRole !== '' ? $userRole : 'guest'); ?>";
    document.querySelectorAll('.help-section').forEach(function(section) {
        const roles = section.dataset.roles.split(',');
        if (!roles.includes(currentRole)) {
            section.style.display = 'none';
        }
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>