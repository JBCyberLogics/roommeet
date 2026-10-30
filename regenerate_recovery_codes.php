<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/activity_log.php";
require_once __DIR__ . "/includes/two_factor_crypto.php";
require_once __DIR__ . "/vendor/autoload.php";

use PragmaRX\Google2FA\Google2FA;

/*
 * Prevent sensitive recovery codes from being cached.
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$userId = (int) $_SESSION["user_id"];
$message = "";
$recoveryCodesToShow = [];

/*
 * Load the current account.
 */
$stmt = $conn->prepare(
    "SELECT name, email, two_factor_enabled,
            two_factor_secret, two_factor_recovery_codes
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    http_response_code(404);
    exit("User account not found.");
}

if (
    (int) $user["two_factor_enabled"] !== 1 ||
    empty($user["two_factor_secret"])
) {
    exit("Two-factor authentication must be enabled first.");
}

/*
 * Prepare CSRF protection.
 */
if (empty($_SESSION["csrf_recovery_regenerate"])) {
    $_SESSION["csrf_recovery_regenerate"] =
        bin2hex(random_bytes(32));
}

/*
 * Process the regeneration request.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";
    $code = trim($_POST["code"] ?? "");

    if (
        !is_string($csrfToken) ||
        !hash_equals(
            $_SESSION["csrf_recovery_regenerate"],
            $csrfToken
        )
    ) {
        $message = "Invalid request. Refresh the page and try again.";

    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $message = "Enter the current six-digit authenticator code.";

    } else {

        /*
         * Verify the current authenticator code.
         */
        try {
            $secret = meetsyncDecryptTwoFactorSecret(
                $user["two_factor_secret"]
            );

            $google2fa = new Google2FA();

            $verified = $google2fa->verifyKey(
                $secret,
                $code
            );

        } catch (Throwable $e) {
            error_log(
                "MeetSync recovery-code regeneration verification failed."
            );

            $verified = false;
            $message =
                "Verification is temporarily unavailable. Please try again.";
        }

        if (!$verified && $message === "") {
            $message =
                "Invalid authenticator code. No recovery codes were changed.";
        }

        if ($verified) {

            /*
             * Generate eight new recovery codes.
             * Store only their password hashes.
             */
            $newCodes = [];
            $newHashes = [];

            for ($i = 0; $i < 8; $i++) {
                $newCode = strtoupper(
                    bin2hex(random_bytes(4))
                );

                $newCodes[] = $newCode;
                $newHashes[] = password_hash(
                    $newCode,
                    PASSWORD_DEFAULT
                );
            }

            try {
                $newRecoveryJson = json_encode(
                    $newHashes,
                    JSON_THROW_ON_ERROR
                );

                /*
                 * Replace the existing codes only if the stored
                 * recovery codes and authenticator secret have
                 * not changed since they were loaded.
                 */
                $update = $conn->prepare(
                    "UPDATE users
                     SET two_factor_recovery_codes = ?
                     WHERE id = ?
                       AND two_factor_enabled = 1
                       AND two_factor_secret = ?
                       AND two_factor_recovery_codes <=> ?"
                );

                $update->bind_param(
                    "siss",
                    $newRecoveryJson,
                    $userId,
                    $user["two_factor_secret"],
                    $user["two_factor_recovery_codes"]
                );

                $update->execute();
                $updated = ($update->affected_rows === 1);
                $update->close();

                if ($updated) {

                    logActivity(
                        $conn,
                        $userId,
                        "Security",
                        "User regenerated two-factor recovery codes."
                    );

                    /*
                     * Show the new codes only after redirecting.
                     */
                    $_SESSION["new_recovery_codes_once"] = $newCodes;

                    /*
                     * Rotate the form token after successful use.
                     */
                    $_SESSION["csrf_recovery_regenerate"] =
                        bin2hex(random_bytes(32));

                    header(
                        "Location: regenerate_recovery_codes.php?generated=1"
                    );
                    exit;

                } else {
                    $message =
                        "The recovery codes were not changed. Refresh the page and try again.";
                }

            } catch (Throwable $e) {
                error_log(
                    "MeetSync recovery-code regeneration failed."
                );

                $message =
                    "Unable to regenerate recovery codes. Please try again.";
            }
        }
    }
}

/*
 * Display newly generated codes once.
 * They are removed from the session immediately after loading.
 */
if (
    isset($_GET["generated"]) &&
    $_GET["generated"] === "1"
) {
    $recoveryCodesToShow =
        $_SESSION["new_recovery_codes_once"] ?? [];

    unset($_SESSION["new_recovery_codes_once"]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetSync - Recovery Codes</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js"></script>
    <link rel="stylesheet" href="css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="uploads/profile_pictures/favicon.jpeg">
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="navbar">
            <div>
                <h1>MeetSync</h1>
                <p>Meeting Scheduling and Coordination System</p>
            </div>
        </div>
    </div>
</header>

<main class="container">
    <div class="form-container">
        <div class="card">

            <h2>Recovery Codes</h2>

            <p>
                Account:
                <?= htmlspecialchars($user["email"]) ?>
            </p>

            <?php if ($message !== ""): ?>
                <div class="message message-error">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($recoveryCodesToShow)): ?>

                <h3>Your new recovery codes</h3>

                <p>
                    Save these eight codes in a secure location.
                    Each code can be used only once. These codes
                    will not be displayed again.
                </p>

                <ul>
                    <?php foreach ($recoveryCodesToShow as $recoveryCode): ?>
                        <li>
                            <code><?= htmlspecialchars($recoveryCode) ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p>
                    Generating these codes invalidated all previous
                    recovery codes.
                </p>

                <p>
                    <a href="dashboard.php">
                        Return to dashboard
                    </a>
                </p>

            <?php else: ?>

                <p>
                    Enter the current code from your authenticator
                    app to generate a new set of recovery codes.
                    Your existing recovery codes will be replaced.
                </p>

                <form method="post" action="regenerate_recovery_codes.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION["csrf_recovery_regenerate"]
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="code">
                            Current six-digit authenticator code
                        </label>

                        <input
                            type="text"
                            id="code"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                        >
                    </div>

                    <button type="submit" class="btn">
                        Regenerate Recovery Codes
                    </button>

                </form>

                <p>
                    <a href="dashboard.php">Cancel and return to dashboard</a>
                </p>

            <?php endif; ?>

        </div>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>
            MeetSync &copy; <?= date("Y") ?>
            — Meeting Scheduling and Coordination System
        </p>
    </div>
</footer>

</body>
</html>
