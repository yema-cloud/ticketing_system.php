<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Forgot Password </title>
</head>
<body>

<?php
    include "header.php";
    ?>


    <section class="contact-block">
        <center>
        <h2>Forgot Your Password?</h2>

        <form id="forgotPasswordForm" class="form-body" novalidate>

            <!-- STEP 1: VERIFY USERNAME -->
            <div class="form-section" id="step1">
                <p class="form-section-title">Step 1 of 3: Verify Your Account</p>
                <p class="form-section-desc">Enter the username linked to the account you want to reset.</p>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </div>

                <p id="usernameError" class="field-error" role="alert" style="display:none;">
                    Please enter your username to continue.
                </p>

                <div class="form-buttons">
                    <button type="button" id="continueBtn">Continue</button>
                </div>
            </div>

            <!-- STEP 1.5: ANSWER SECURITY QUESTION (only shown if the account has one) -->
            <div class="form-section" id="stepSecurity" style="display:none;">
                <p class="form-section-title">Step 2 of 3: Answer Your Security Question</p>
                <p class="form-section-desc" id="securityQuestionText"></p>

                <div class="form-group">
                    <label for="securityAnswer">Your Answer</label>
                    <input type="text" id="securityAnswer" name="securityAnswer" required>
                </div>

                <p id="securityError" class="field-error" role="alert" style="display:none;">
                    That answer doesn't match our records. Please try again.
                </p>

                <div class="form-buttons">
                    <button type="button" class="btn-secondary" id="backToStep1Btn">Back</button>
                    <button type="button" id="verifyAnswerBtn">Verify Answer</button>
                </div>
            </div>

             <!-- Shown instead of stepSecurity, only when the account has NO security questions set up -->
            <div class="form-section" id="noSecurityWarning" style="display:none;">
                <p class="form-section-desc" style="color:#c0392b;">
                    This account doesn't have a security question set up, so we can't
                    verify your identity that way. Since anyone who knows this username
                    could otherwise reset the password, please contact a system
                    administrator to reset it for you instead.
                </p>
                <div class="form-buttons">
                    <button type="button" class="btn-secondary" id="backFromWarningBtn">Back</button>
                </div>
            </div>

            <!-- STEP 2: SET NEW PASSWORD (hidden until Step 1 is done) -->
            <div class="form-section" id="step2" style="display:none;">
                <p class="form-section-title">Step 3 of 3: Choose a New Password</p>
                <p class="form-section-desc">
                    Resetting password for: <strong id="confirmedUsername"></strong>
                    &nbsp;(<a href="#" id="changeUsernameLink" class="link">not you?</a>)
                </p>

                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="new_password"
                           autocomplete="new-password" minlength="8" required
                           aria-describedby="passwordHint">
                </div>
                <p id="passwordHint" class="field-hint">Must be at least 8 characters.</p>

                <div class="form-group">
                    <label for="confirmPassword">Re-enter New Password</label>
                    <input type="password" id="confirmPassword" name="confirm_password"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <p id="matchError" class="field-error" role="alert" style="display:none;">
                    Passwords do not match.
                </p>

                <div class="form-buttons">
                    <button type="button" class="btn-secondary" id="backBtn">Back</button>
                    <button type="submit" id="submitBtn" disabled>Reset Password</button>
                </div>
            </div>

        </form>

        <p style="margin-top: 10px;"><a href="login.php" class="link">← Back to Login</a></p>

        </center>
    </section>

    <script>
        const step1 = document.getElementById('step1');
        const stepSecurity = document.getElementById('stepSecurity');
        const noSecurityWarning = document.getElementById('noSecurityWarning');
        const step2 = document.getElementById('step2');
        const usernameInput = document.getElementById('username');
        const usernameError = document.getElementById('usernameError');
        const continueBtn = document.getElementById('continueBtn');
        const securityQuestionText = document.getElementById('securityQuestionText');
        const securityAnswer = document.getElementById('securityAnswer');
        const securityError = document.getElementById('securityError');
        const verifyAnswerBtn = document.getElementById('verifyAnswerBtn');
        const backToStep1Btn = document.getElementById('backToStep1Btn');
        const backFromWarningBtn = document.getElementById('backFromWarningBtn');
        const backBtn = document.getElementById('backBtn');
        const changeUsernameLink = document.getElementById('changeUsernameLink');
        const confirmedUsername = document.getElementById('confirmedUsername');
        const newPassword = document.getElementById('newPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        const matchError = document.getElementById('matchError');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('forgotPasswordForm');


        let currentQuestionKey = null;

        const allSections = [step1, stepSecurity, noSecurityWarning, step2];

        // Show one step and hide all the others
        function showSection(section) {
            allSections.forEach(function (s) {
                s.style.display = (s === section) ? 'block' : 'none';
            });
        }

        // Wipe the new-password step so nothing typed there is left behind
        function clearPasswordStep() {
            newPassword.value = '';
            confirmPassword.value = '';
            matchError.style.display = 'none';
            submitBtn.disabled = true;
        }

        // Wipe the security-question step
        function clearSecurityStep() {
            securityAnswer.value = '';
            securityError.style.display = 'none';
            currentQuestionKey = null;
        }

        // STEP 1 -> fetch security question for this specific username
        continueBtn.addEventListener('click', function () {
            const username = usernameInput.value.trim();

            if (username === '') {
                usernameError.style.display = 'block';
                usernameInput.focus();
                return;
            }

            usernameError.style.display = 'none';
            fetch('get_security_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ username: username })
            })
            .then(res => res.json())
            .then(data => {
                confirmedUsername.textContent = username;

                 if (data.status === 'ok') {
                    currentQuestionKey = data.questionKey;
                    securityQuestionText.textContent = data.questionText;
                    showSection(stepSecurity);
                    securityAnswer.focus();
                } else {
                    // Covers both "username not found" and "no questions set up" -
                    // deliberately the same message either way (see get_security_question.php note).
                    showSection(noSecurityWarning);
                }
            });
        });

        // Security question -> back to username
        // (the answer belongs to that username's question, so it is cleared)
        backToStep1Btn.addEventListener('click', function () {
            clearSecurityStep();
            showSection(step1);
            usernameInput.focus();
        });

        // "No security question" warning -> back to username
        backFromWarningBtn.addEventListener('click', function () {
            showSection(step1);
            usernameInput.focus();
        });

        // New password -> back to the security question
        // (password boxes are cleared; the answer stays so they can just press Verify)
        backBtn.addEventListener('click', function () {
            clearPasswordStep();
            securityError.style.display = 'none';
            showSection(stepSecurity);
            securityAnswer.focus();
        });

        // "not you?" -> start again from the username
        changeUsernameLink.addEventListener('click', function (e) {
            e.preventDefault();
            clearPasswordStep();
            clearSecurityStep();
            showSection(step1);
            usernameInput.focus();
            usernameInput.select();
        });

        // STEP 1.5 -> verify the answer
        verifyAnswerBtn.addEventListener('click', function () {
            const answer = securityAnswer.value.trim();

            if (answer === '') {
                securityError.textContent = 'Please enter an answer.';
                securityError.style.display = 'block';
                return;
            }

            fetch('verify_security_answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    username: confirmedUsername.textContent,
                    questionKey: currentQuestionKey,
                    answer: answer
                })
            })

            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    securityError.style.display = 'none';
                    showSection(step2);
                    newPassword.focus();
                } else {
                    securityError.textContent = "That answer doesn't match our records. Please try again.";
                    securityError.style.display = 'block';
                    securityAnswer.focus();
                }
            });
        });


           
           
        // Real-time match checking, not just on submit, which tells the user
        // immediately instead of after they've filled in everything and clicked submit.
        function checkPasswordsMatch() {
            const match = newPassword.value === confirmPassword.value;
            const bothFilled = newPassword.value.length >= 8 && confirmPassword.value.length >= 8;

            matchError.style.display = (confirmPassword.value.length > 0 && !match) ? 'block' : 'none';
            submitBtn.disabled = !(match && bothFilled);

            return match && bothFilled;
        }

        newPassword.addEventListener('input', checkPasswordsMatch);
        confirmPassword.addEventListener('input', checkPasswordsMatch);

        // Final guard on submit, in case JS state got out of sync somehow
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!checkPasswordsMatch()) {
                confirmPassword.focus();
                return;
            }

            fetch('reset_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        username: confirmedUsername.textContent,
        new_password: newPassword.value,
        confirm_password: confirmPassword.value
    })
}).then(response => {
    if (response.redirected) {
        window.location.href = response.url;
    }
        });
    });
    </script>
     
<?php include "footer.php"; ?>

</body>
</html>