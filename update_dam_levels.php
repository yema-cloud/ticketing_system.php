<?php
session_start();

/* Only logged-in Officers may use this page */

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'Officer') {
    header("Location: dashboard.php");
    exit();
}

include_once "db_connect.php";


/* Simple CSRF token for the form */

if (empty($_SESSION['dam_csrf'])) {
    $_SESSION['dam_csrf'] = bin2hex(random_bytes(16));
}


$message     = "";
$messageType = "";      // "success" or "error"
$errors      = [];      // per-dam validation errors, keyed by DamID
$entered     = [];      // values typed by the officer (kept if validation fails)
$dams        = [];      // current rows from dam_levels, keyed by DamID


/* Show the message left by the last successful save */

if (isset($_SESSION['dam_flash'])) {
    $message     = $_SESSION['dam_flash'];
    $messageType = "success";
    unset($_SESSION['dam_flash']);
}


/* Show 87.5 as "87.5" and 100.0 as "100" */

function formatPercent($value)
{
    if ($value === null || $value === "") {
        return "";
    }

    return rtrim(
        rtrim(number_format((float)$value, 1, ".", ""), "0"),
        "."
    );
}


/* LOAD CURRENT DAM LEVELS */

try {

    $result = $conn->query(
        "SELECT DamID, DamName, CapacityPercent, LastUpdated
         FROM dam_levels
         ORDER BY DamID"
    );

    if ($result === false) {
        throw new Exception($conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $dams[(int)$row["DamID"]] = $row;
    }

} catch (Throwable $e) {

    error_log("update_dam_levels.php (load): " . $e->getMessage());

    $message     = "The dam levels could not be loaded. "
                 . "Please contact the administrator.";
    $messageType = "error";
}


/* SAVE SUBMITTED LEVELS */

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($dams)) {

    $token = $_POST["csrf"] ?? "";

    if (!hash_equals($_SESSION["dam_csrf"], (string)$token)) {

        $message     = "Your session expired. Please try again.";
        $messageType = "error";

    } else {

        $submitted = $_POST["level"] ?? [];

        if (!is_array($submitted)) {
            $submitted = [];
        }

        $newValues = [];

        foreach ($dams as $id => $dam) {

            $raw = (isset($submitted[$id]) && is_scalar($submitted[$id]))
                ? trim((string)$submitted[$id])
                : "";

            $entered[$id] = $raw;

            /* Blank = level unavailable */

            if ($raw === "") {
                $newValues[$id] = null;
                continue;
            }

            if (
                !is_numeric($raw)
                || (float)$raw < 0
                || (float)$raw > 100
            ) {
                $errors[$id] =
                    "Enter a number from 0 to 100, "
                    . "or leave blank if unavailable.";
                continue;
            }

            $newValues[$id] = round((float)$raw, 1);
        }


        if (!empty($errors)) {

            $message     = "Please correct the highlighted fields.";
            $messageType = "error";

        } else {

            $userId  = $_SESSION["user_id"] ?? null;
            $changed = 0;

            try {

                $conn->begin_transaction();

                $update = $conn->prepare(
                    "UPDATE dam_levels
                     SET CapacityPercent = ?,
                         LastUpdated = NOW(),
                         UpdatedBy = ?
                     WHERE DamID = ?"
                );

                if ($update === false) {
                    throw new Exception($conn->error);
                }

                foreach ($newValues as $id => $value) {

                    $old = $dams[$id]["CapacityPercent"];

                    $old = ($old === null)
                        ? null
                        : round((float)$old, 1);

                    /* Only touch dams whose level actually changed */

                    $same =
                        ($old === null && $value === null)
                        || (
                            $old !== null
                            && $value !== null
                            && abs($old - $value) < 0.05
                        );

                    if ($same) {
                        continue;
                    }

                    $update->bind_param("dii", $value, $userId, $id);
                    $update->execute();

                    $changed++;
                }

                $update->close();

                $conn->commit();

                $_SESSION["dam_flash"] = ($changed > 0)
                    ? "Dam levels updated successfully."
                    : "No changes were made.";

                /* Redirect so refreshing does not resubmit the form */

                header("Location: update_dam_levels.php");
                exit();

            } catch (Throwable $e) {

                $conn->rollback();

                error_log(
                    "update_dam_levels.php (save): " . $e->getMessage()
                );

                $message     = "The dam levels could not be saved. "
                             . "Please try again.";
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Dam Levels</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php
    include "header.php";
    ?>

    <div class="dashboard-content">

        <div class="form-body dam-update-form">

            <h2>Update Dam Levels</h2>

            <div class="form-section-desc">
                Enter the current capacity of each dam as a percentage.
                Leave a field blank if the level is not available.
                The Makhanda Dam Levels on the home page update as soon
                as you save.
            </div>

            <?php if ($message !== ""): ?>
                <div class="dam-update-message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>


            <?php if (!empty($dams)): ?>

            <form method="post" action="update_dam_levels.php">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?php echo htmlspecialchars($_SESSION['dam_csrf']); ?>"
                >

                <?php foreach ($dams as $id => $dam): ?>

                    <?php
                    $shown = array_key_exists($id, $entered)
                        ? $entered[$id]
                        : formatPercent($dam["CapacityPercent"]);
                    ?>

                    <div class="form-group">

                        <label for="dam_<?php echo $id; ?>">
                            <?php echo htmlspecialchars($dam["DamName"]); ?>
                        </label>

                        <div class="dam-field">

                            <input
                                type="number"
                                id="dam_<?php echo $id; ?>"
                                name="level[<?php echo $id; ?>]"
                                min="0"
                                max="100"
                                step="0.1"
                                placeholder="--"
                                value="<?php echo htmlspecialchars($shown); ?>"
                                <?php if (isset($errors[$id])) echo 'class="has-error"'; ?>
                            >

                            <span class="dam-unit">%</span>

                            <?php if (isset($errors[$id])): ?>
                                <span class="dam-field-error">
                                    <?php echo htmlspecialchars($errors[$id]); ?>
                                </span>
                            <?php endif; ?>

                            <small>
                                <?php
                                if ($dam["LastUpdated"]) {
                                    echo "Last updated "
                                        . date(
                                            "j M Y, H:i",
                                            strtotime($dam["LastUpdated"])
                                        );
                                } else {
                                    echo "Not updated yet";
                                }
                                ?>
                            </small>

                        </div>

                    </div>

                <?php endforeach; ?>


                <div class="form-buttons">

                    <button type="submit">Save Dam Levels</button>

                    <a href="dashboard.php">
                        <button type="button" class="btn-secondary">
                            Back to Dashboard
                        </button>
                    </a>

                </div>

            </form>

            <?php endif; ?>

        </div>

    </div> <!-- dashboard-content -->


</main> <!-- logged-in-page -->

</div> <!-- logged-in-layout -->


<?php include 'footer.php'; ?>


</body>
</html>