<?php

session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/activity_log.php";
require_once __DIR__ . "/includes/two_factor_crypto.php";
require_once __DIR__ . "/vendor/autoload.php";

use PragmaRX\Google2FA\Google2FA;

$message = "";

/*
 * A user must arrive here from the password-verification stage.
 * A pending user ID is not a fully authenticated session.
 */
if (empty($_SESSION["pending_2fa_user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["pending_2fa_user_id"];

/* Expire the pending login after five minutes. */
$startedAt = (int) ($_SESSION["pending_2fa_started_at"] ?? 0);

if (
    $startedAt <= 0 ||
    (time() - $startedAt) > 300
) {
    unset(
        $_SESSION["pending_2fa_user_id"],
        $_SESSION["pending_2fa_started_at"],
        $_SESSION["csrf_2fa_verify"]
    );

    header("Location: login.php?message=expired");
    exit;
}

/* Load the account and its encrypted authenticator secret. */
$stmt = $conn->prepare(
    "SELECT id, name, email, role,
            two_factor_enabled,
            two_factor_secret,
            two_factor_recovery_codes
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (
    !$user ||
    (int) $user["two_factor_enabled"] !== 1 ||
    empty($user["two_factor_secret"])
) {
    unset(
        $_SESSION["pending_2fa_user_id"],
        $_SESSION["pending_2fa_started_at"],
        $_SESSION["csrf_2fa_verify"]
    );

    header("Location: login.php");
    exit;
}

/* Prepare CSRF protection for the verification form. */
if (empty($_SESSION["csrf_2fa_verify"])) {
    $_SESSION["csrf_2fa_verify"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";
    $code = trim($_POST["code"] ?? "");

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_2fa_verify"], $csrfToken)
    ) {
        $message = "Invalid request. Refresh the page and try again.";

    } elseif ($code === "" || strlen($code) > 100) {
        $message = "Enter a valid authenticator or recovery code.";

    } else {

        $authenticated = false;
        $usedRecoveryCode = false;

        /*
         * First, check the six-digit authenticator code.
         */
        if (preg_match('/^\d{6}$/', $code)) {

            try {
                $secret = meetsyncDecryptTwoFactorSecret(
                    $user["two_factor_secret"]
                );

                $google2fa = new Google2FA();

                $authenticated = $google2fa->verifyKey(
                    $secret,
                    $code
                );

            } catch (Throwable $e) {
                error_log(
                    "MeetSync 2FA verification error: " .
                    $e->getMessage()
                );

                $message =
                    "Verification is temporarily unavailable. Please try again.";
            }
        }

        /*
         * If the authenticator code did not work, check recovery codes.
         * A recovery code can be used only once.
         */
        if (!$authenticated && $message === "") {

            $storedHashes = json_decode(
                $user["two_factor_recovery_codes"],
                true
            );

            if (is_array($storedHashes)) {

                foreach ($storedHashes as $index => $storedHash) {

                    if (
                        is_string($storedHash) &&
                        password_verify($code, $storedHash)
                    ) {
                        $authenticated = true;
                        $usedRecoveryCode = true;

                        unset($storedHashes[$index]);

                        $storedHashes = array_values($storedHashes);

                        $updatedHashes = json_encode(
                            $storedHashes,
                            JSON_THROW_ON_ERROR
                        );

                        /*
                         * Update only if the stored codes have not
                         * changed since they were read.
                         */
                        $update = $conn->prepare(
                            "UPDATE users
                             SET two_factor_recovery_codes = ?
                             WHERE id = ?
                               AND two_factor_recovery_codes = ?"
                        );

                        $update->bind_param(
                            "sis",
                            $updatedHashes,
                            $userId,
                            $user["two_factor_recovery_codes"]
                        );

                        $update->execute();

                        $recoveryUpdateSucceeded =
                            ($update->affected_rows === 1);

                        $update->close();

                        if (!$recoveryUpdateSucceeded) {
                            $authenticated = false;
                            $message =
                                "The recovery code could not be consumed. Please try again.";
                        }

                        break;
                    }
                }
            }
        }

        if (!$authenticated && $message === "") {
            $message = "Invalid authenticator or recovery code.";
        }

        if ($authenticated) {

            /*
             * Establish a fresh authenticated session only after
             * successful second-factor verification.
             */
            session_regenerate_id(true);

            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

            unset(
                $_SESSION["pending_2fa_user_id"],
                $_SESSION["pending_2fa_started_at"],
                $_SESSION["csrf_2fa_verify"]
            );
/* Record that this session passed two-factor verification. */
$_SESSION["two_factor_verified_user_id"] = $userId;

            $activityDescription = $usedRecoveryCode
                ? "User logged in using a two-factor recovery code."
                : "User logged in using two-factor authentication.";

            logActivity(
                $conn,
                $userId,
                "User Login",
                $activityDescription
            );

            header("Location: dashboard.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>MeetSync - Verify Login</title>

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

                <p>
                    Meeting Scheduling and Coordination System
                </p>
            </div>
        </div>
    </div>
</header>

<main class="container">

    <div class="form-container">
        <div class="card">

            <h2>Verify Your Identity</h2>

            <p>
                Enter the six-digit code from your authenticator app.
                You may also enter an unused recovery code.
            </p>

            <?php if ($message !== ""): ?>
                <div class="message message-error">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["csrf_2fa_verify"]
                    ) ?>"
                >

                <div class="form-group">

                    <label for="code">
                        Authenticator or Recovery Code
                    </label>

                    <input
                        type="text"
                        id="code"
                        name="code"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >

                </div>

                <button type="submit" class="btn">
                    Verify and Sign In
                </button>

            </form>

            <p>
                <a href="login.php">Cancel and return to login</a>
            </p>

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