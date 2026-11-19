<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Sign-up Page</title>
</head>
<body>

<?php
include "header.php";

$securityQuestions = [
    "mother_maiden_name" => "What is your mother's maiden name?",
    "first_pet"          => "What was the name of your first pet?",
    "birth_town"         => "In what town were you born?",
    "first_school"       => "What was the name of your first school?",
    "favourite_teacher"  => "Who was your favourite teacher?",
];

function renderSecurityQuestion($number, $questions) {
    echo '<div class="form-group">';
    echo '<label for="secQuestion' . $number . '">Security Question ' . $number . '</label>';
    echo '<select id="secQuestion' . $number . '" name="secQuestion' . $number . '" class="sec-question">';
    echo '<option value="">-- Select a Question --</option>';
    foreach ($questions as $value => $label) {
        echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group">';
    echo '<label for="secAnswer' . $number . '">Answer</label>';
    echo '<input type="text" id="secAnswer' . $number . '" name="secAnswer' . $number . '" class="sec-answer">';
    echo '</div>';
}
?>

<div class="form-body">
    <h1>Sign-up Form
        <a href="help.php#account-approval-help" class="field-help" title="What happens after I sign up?">?</a>
    </h1>

    <p class="form-intro" id="stepIntro">This form takes about 2 minutes to complete.</p>
    <p class="form-step-label">Step <span id="stepIndicator">1</span> of 4</p>

    <div class="progress-bar">
        <div class="progress-bar-fill" id="progressFill"></div>
    </div>

    <form action="urreg.php" method="POST" class="signup-form" id="signupForm" novalidate>

        <!-- STEP 1 -->
        <div class="form-section" data-step="1">
            <p class="form-section-title">Personal Information</p>

            <div class="form-group">
                <label for="idnumber">ID Number
                </label>
                <input type="text" id="idnumber" name="idnumber" placeholder="e.g. 0001015800086"  required>
            </div>

            <div class="form-group">
                <label for="surname">Surname</label>
                <input type="text" id="surname" name="surname" placeholder="e.g. Gumatana" pattern="[A-Za-z]+" title="Please enter letters only" required>
            </div>

            <div class="form-group">
                <label for="firstname">First Name</label>
                <input type="text" id="firstname" name="firstname" placeholder="e.g. Philasande" pattern="[A-Za-z]+" title="Please enter letters only" required>
            </div>

            <div class="form-group">
                <label>Gender</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male"> Male</label>
                    <label><input type="radio" name="gender" value="female"> Female</label>
                    <label><input type="radio" name="gender" value="other"> Other</label>
                </div>
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" required>
            </div>

            <div class="form-buttons">
                <button type="button" class="btn-next" onclick="nextStep(2)">Next</button>
            </div>
        </div>

        <!-- STEP 2 -->
        <div class="form-section" data-step="2" style="display:none;">
            <p class="form-section-title">Account Information</p>

            <div class="form-group">
                <label for="userrole">User Role</label>
                <select id="userrole" name="userrole" required>
                    <option value="">-- Select Role --</option>
                    <option value="Administrator">System Administrator</option>
                    <option value="Councillor">Ward Councillor</option>
                    <option value="Resident">Resident</option>
                    <option value="Officer">Communications Officer</option>
                </select>
            </div>

            <div class="form-group">
                <label for="username">User Name</label>
                <input type="text" id="username" name="username" placeholder="Choose a unique username that doesn't begin with a digit." required>
            </div>

            <div class="form-group">
                <label for="pword">Password</label>
                <input type="password" id="pword" name="pword" placeholder="At least 8 characters, 1 special character" minlength="8" pattern="(?=.*[!@#$%^&*(),.?&quot;:{}|&lt;&gt;]).{8,}" title="Must be at least 8 characters and include at least one special character" required>
            </div>

            <div class="form-group">
    <label for="pwordConfirm">Confirm Password</label>
    <input type="password" id="pwordConfirm" name="pwordConfirm" placeholder="Re-enter your password" required>
</div>
<p id="pwordValidationMsg" class="field-error" role="alert" style="display:none;"></p>

            <div class="form-buttons">
                <button type="button" class="btn-secondary" onclick="goToStep(1)">Back</button>
                <button type="button" class="btn-next" onclick="nextStep(3)">Next</button>
            </div>
        </div>

        <!-- STEP 3 -->
        <div class="form-section" data-step="3" style="display:none;">
            <p class="form-section-title">Location &amp; Contact</p>

            <div class="form-group">
                <label for="ward">Ward</label>
                <select id="ward" name="ward" required>
                    <option value="">-- Select Ward --</option>
                    <?php for ($i = 1; $i <= 14; $i++): ?>
                        <option value="<?php echo $i; ?>">Ward <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group align-top">
                <label for="physAddress">Physical Address</label>
                <textarea id="physAddress" rows="4" name="physAddress" placeholder="e.g. 10 African Street"  required></textarea>
            </div>

            <div class="form-group">
                <label for="contact">Contact</label>
                <input type="text" id="contact" name="contact" placeholder="e.g. 0821234567" inputmode="numeric" pattern="0[0-9]{9}" maxlength="10" title="Please enter exactly 10 digits" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="e.g. name@gmail.com" required>
            </div>

            <div class="form-buttons">
                <button type="button" class="btn-secondary" onclick="goToStep(2)">Back</button>
                <button type="button" class="btn-next" onclick="nextStep(4)">Next</button>
            </div>
        </div>

        <!-- STEP 4 -->
        <div class="form-section" data-step="4" style="display:none;">
            <p class="form-section-title">
                Security Questions
                <button type="button" id="securityInfoBtn" class="info-toggle" aria-label="Why set up security questions?">ⓘ</button>
            </p>

            <div id="securityInfoPopup" class="info-popup" style="display:none;">
                Security questions help you get back into your account if you ever
                forget your password. Fill in at least one: you choose which,
                and how many, up to three.
            </div>

            <p class="form-section-desc">Fill in at least one question and answer. The other two are optional.</p>

            <?php
                renderSecurityQuestion(1, $securityQuestions);
                renderSecurityQuestion(2, $securityQuestions);
                renderSecurityQuestion(3, $securityQuestions);
            ?>

            <p id="secValidationMsg" class="field-error" role="alert" style="display:none;"></p>

            <div class="form-buttons">
                <button type="button" class="btn-secondary" onclick="goToStep(3)">Back</button>
                <button type="submit">Create Account</button>
            </div>
        </div>

    </form>
</div>

<script>
var currentStep = 1;

/* Returns the first field on this step that is not filled in correctly
   (or the confirm-password box if the two passwords differ). Returns null if the step is fine. */
function findStepProblem(stepNumber) {
    var section = document.querySelector('.form-section[data-step="' + stepNumber + '"]');
    var fields = section.querySelectorAll('input, select, textarea');

    for (var i = 0; i < fields.length; i++) {
        var field = fields[i];

        // A required box containing only spaces counts as empty
        if (field.required && field.value.trim() === '') {
            field.value = '';
        }

        if (!field.checkValidity()) {
            return field;
        }
    }

    if (stepNumber === 2 &&
        document.getElementById('pword').value !== document.getElementById('pwordConfirm').value) {
        return document.getElementById('pwordConfirm');
    }

    return null;
}

/* Live check: shows "Passwords do not match." as soon as the confirm box
   differs from the password, and hides it again the moment they match. */
function checkPasswordMatch() {
    var pword = document.getElementById('pword').value;
    var pwordConfirm = document.getElementById('pwordConfirm');
    var pwordMsg = document.getElementById('pwordValidationMsg');

    if (pwordConfirm.value !== '' && pword !== pwordConfirm.value) {
        pwordMsg.textContent = 'Passwords do not match.';
        pwordMsg.style.display = 'block';
        pwordConfirm.style.borderColor = '#c0392b';
    } else {
        pwordMsg.style.display = 'none';
        pwordConfirm.style.borderColor = '';
    }
}

/* Checks the step and shows the problem to the user. Returns true if it is OK to continue. */
function stepIsValid(stepNumber) {
    var problem = findStepProblem(stepNumber);

    checkPasswordMatch();   // keep the mismatch message in step with the fields

    if (!problem) {
        return true;
    }

    if (problem.id === 'pwordConfirm' && problem.checkValidity()) {
        // Filled in, but does not match the password: the message is already showing
        problem.focus();
    } else {
        problem.reportValidity();   // shows the browser message and focuses the field
    }

    return false;
}


/* Used by the Next buttons: only move on if the current step is complete */
function nextStep(targetStep) {
    if (stepIsValid(targetStep - 1)) {
        goToStep(targetStep);
    }
}

function goToStep(stepNumber) {
    currentStep = stepNumber;

    document.querySelectorAll('.form-section').forEach(function (section) {
        section.style.display = (parseInt(section.dataset.step) === stepNumber) ? 'block' : 'none';
    });

    document.getElementById('stepIndicator').textContent = stepNumber;
    document.getElementById('progressFill').style.width = (stepNumber / 4 * 100) + '%';

    // Only show the "2 minutes" message on Step 1
    document.getElementById('stepIntro').style.display = (stepNumber === 1) ? 'block' : 'none';

    document.querySelector('.form-body').scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('signupForm').addEventListener('keydown', function (e) {
    var tag = e.target.tagName;

    if (e.key === 'Enter' && currentStep < 4 && (tag === 'INPUT' || tag === 'SELECT')) {
        e.preventDefault();
        nextStep(currentStep + 1);
    }
});

document.getElementById('pword').addEventListener('input', checkPasswordMatch);
document.getElementById('pwordConfirm').addEventListener('input', checkPasswordMatch);

document.getElementById('securityInfoBtn').addEventListener('click', function () {
    var popup = document.getElementById('securityInfoPopup');
    popup.style.display = (popup.style.display === 'none') ? 'block' : 'none';
});

document.getElementById('signupForm').addEventListener('submit', function (e) {
    // Make sure steps 1-3 are still complete; if not, go back to the first problem
    for (var step = 1; step <= 3; step++) {
        if (findStepProblem(step)) {
            e.preventDefault();
            goToStep(step);
            stepIsValid(step);
            return;
        }
    }

    var msg = document.getElementById('secValidationMsg');
    msg.style.display = 'none';

    var completedCount = 0;

    for (var i = 1; i <= 3; i++) {
        var question = document.getElementById('secQuestion' + i).value;
        var answer = document.getElementById('secAnswer' + i).value.trim();

        if (question && !answer || !question && answer) {
            e.preventDefault();
            msg.textContent = 'Security Question ' + i + ' is incomplete. Please fill in both the question and the answer, or leave both blank.';
            msg.style.display = 'block';
            return;
        }

        if (question && answer) {
            completedCount++;
        }
    }

    if (completedCount === 0) {
        e.preventDefault();
        msg.textContent = 'Please fill in at least one security question and answer before submitting.';
        msg.style.display = 'block';
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>