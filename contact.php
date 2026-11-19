<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title> Contact Page</title>

</head>

<body>

    <?php
    include "header.php";

    $submitted = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $submitted = true;
    }
    ?>
    <section class="contact-block"> 
        <h1> Municipality Contact </h1>
        <p> Phone Number: 074 660 6131 </p>
        <p> Email Address: <a href="nathisiconnected@gmail.com"> nathisiconnected@gmail.com </a> </p>
        <p> Physical Address: 1921 High Street Makhanda </p>
    </section>
   <div class="form-body">

    <h2>Fill in the Form for any Queries</h2>

    <?php if ($submitted): ?>
        <p style="color: green; text-align: center;">
           <strong>Thank you! Your query has been received. We'll be in touch soon.</strong> 
        </p>
    <?php else: ?>

    <form class="signup-form" action="contact.php" method="POST">

        <div class="form-section">

            <p class="form-section-title">Contact Information</p>

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="surname">Surname</label>
                <input type="text" id="surname" name="surname" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

        </div>

        <div class="form-section">

            <div class="form-group align-top">
                <label for="review">Query</label>
                <textarea id="review" rows="4" name="review" required></textarea>
            </div>

        </div>

        <div class="form-buttons">
            <button type="reset" class="btn-secondary">Reset Form</button>
            <button type="submit">Submit Query</button>
        </div>

    </form>

    <?php endif; ?>

</div>

    <?php include 'footer.php'; ?>
</body> 
</html>