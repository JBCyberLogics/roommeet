
<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/activity_log.php";
require_once __DIR__ . "/includes/two_factor_crypto.php";
require_once __DIR__ . "/vendor/autoload.php";

use PragmaRX\Google2FA\Google2FA;

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_disable_2fa"])) {
    $_SESSION["csrf_disable_2fa"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "error";

$stmt = $conn->prepare(
    "SELECT password, two_factor_enabled,
            two_factor_secret, two_factor_recovery_codes
     FROM users
     WHERE id = ?
     LIMIT 1"
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

if ((int) $user["two_factor_enabled"] !== 1
    || empty($user["two_factor_secret"])) {
    header("Location: two_factor_settings.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";
    $password = $_POST["password"] ?? "";
    $code = trim($_POST["authenticator_code"] ?? "");

    if (!is_string($csrfToken)
        || !hash_equals($_SESSION["csrf_disable_2fa"], $csrfToken)) {

        $message = "Invalid request. Please refresh the page and try again.";

    } elseif (
        !is_string($password)
        || !password_verify($password, $user["password"])
    ) {

        $message = "The account password is incorrect.";

    } elseif (!preg_match('/^\d{6}$/', $code)) {

        $message = "Enter a valid six-digit authenticator code.";

    } else {

        try {
            $secret = meetsyncDecryptTwoFactorSecret(
                $user["two_factor_secret"]
            );

            $google2fa = new Google2FA();

            $codeIsValid = $google2fa->verifyKey($secret, $code);

        } catch (Throwable $exception) {
            $codeIsValid = false;
        }

        if (!$codeIsValid) {

            $message = "The authenticator code is invalid. Please try again.";

        } else {

            $oldSecret = $user["two_factor_secret"];
            $oldRecoveryCodes = $user["two_factor_recovery_codes"];

            $update = $conn->prepare(
                "UPDATE users
                 SET two_factor_enabled = 0,
                     two_factor_secret = NULL,
                     two_factor_recovery_codes = NULL
                 WHERE id = ?
                   AND two_factor_enabled = 1
                   AND two_factor_secret = ?
                   AND two_factor_recovery_codes <=> ?"
            );

            $update->bind_param(
                "iss",
                $userId,
                $oldSecret,
                $oldRecoveryCodes
            );

            $update->execute();
            $updated = $update->affected_rows === 1;
            $update->close();

            if ($updated) {

                logActivity(
                    $conn,
                    $userId,
                    "Two-Factor Authentication Disabled",
                    "User disabled two-factor authentication after password and authenticator verification."
                );

                unset($_SESSION["two_factor_verified_user_id"]);

                unset($_SESSION["csrf_disable_2fa"]);

                $message = "Two-factor authentication has been disabled successfully.";
                $messageType = "success";

            } else {

                $message = "Your security settings could not be updated. Refresh the page and try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetSync - Disable Two-Factor Authentication</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
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

            <h2>Disable Two-Factor Authentication</h2>

            <p>
                To disable this security feature, confirm your account
                password and enter the current six-digit code from
                your authenticator app.
            </p>

            <?php if ($message !== ""): ?>
                <div class="message <?= $messageType === "success"
                    ? "message-success"
                    : "message-error" ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== "success"): ?>

                <form method="POST" action="disable_two_factor.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION["csrf_disable_2fa"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="password">Account Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="authenticator_code">
                            Six-Digit Authenticator Code
                        </label>
                        <input
                            type="text"
                            id="authenticator_code"
                            name="authenticator_code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            minlength="6"
                            required
                            autocomplete="one-time-code"
                        >
                    </div>

                    <button type="submit" class="btn">
                        Confirm and Disable
                    </button>

                </form>

            <?php else: ?>

                <p>
                    Your account no longer requires an authenticator
                    code during login. Your previous recovery codes
                    have also been invalidated.
                </p>

            <?php endif; ?>

            <p>
                <a href="two_factor_settings.php">
                    Return to Security Settings
                </a>
            </p>

            <p>
                <a href="dashboard.php">Return to Dashboard</a>
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
