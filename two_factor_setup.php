<?php
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/two_factor_crypto.php";
require_once __DIR__ . "/vendor/autoload.php";

use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$qrDataUri = "";
$recoveryCodesToShow = [];

/* Load the current user. */
$stmt = $conn->prepare(
    "SELECT name, email, two_factor_enabled
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

if ((int) $user["two_factor_enabled"] === 1) {
    unset($_SESSION["pending_2fa_secret"]);
    exit("Two-factor authentication is already enabled.");
}

/* Prepare a CSRF token for form submission. */
if (empty($_SESSION["csrf_2fa_setup"])) {
    $_SESSION["csrf_2fa_setup"] = bin2hex(random_bytes(32));
}

/* Generate a temporary secret once per setup session. */
$google2fa = new Google2FA();

if (empty($_SESSION["pending_2fa_secret"])) {
    $_SESSION["pending_2fa_secret"] =
        $google2fa->generateSecretKey();
}

$pendingSecret = $_SESSION["pending_2fa_secret"];

/* Verify the authenticator code before enabling 2FA. */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $code = trim($_POST["code"] ?? "");

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_2fa_setup"], $csrfToken)
    ) {
        $message = "Invalid request. Refresh the page and try again.";

    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $message = "Enter the six-digit code from your authenticator app.";

    } elseif (!$google2fa->verifyKey($pendingSecret, $code)) {
        $message = "Invalid code. Check your authenticator app and try again.";

    } else {
        /*
         * Generate eight recovery codes.
         * Only their password hashes will be stored in the database.
         */
        $recoveryCodes = [];
        $recoveryHashes = [];

        for ($i = 0; $i < 8; $i++) {
            $recoveryCode = strtoupper(bin2hex(random_bytes(4)));
            $recoveryCodes[] = $recoveryCode;
            $recoveryHashes[] = password_hash(
                $recoveryCode,
                PASSWORD_DEFAULT
            );
        }

        $encryptedSecret =
            meetsyncEncryptTwoFactorSecret($pendingSecret);

        $recoveryJson = json_encode(
            $recoveryHashes,
            JSON_THROW_ON_ERROR
        );

        $update = $conn->prepare(
            "UPDATE users
             SET two_factor_enabled = 1,
                 two_factor_secret = ?,
                 two_factor_recovery_codes = ?
             WHERE id = ? AND two_factor_enabled = 0"
        );

        $update->bind_param(
            "ssi",
            $encryptedSecret,
            $recoveryJson,
            $userId
        );

        $update->execute();
        $updated = $update->affected_rows;
        $update->close();

        if ($updated === 1) {
            unset($_SESSION["pending_2fa_secret"]);

            $_SESSION["two_factor_recovery_codes_once"] =
                $recoveryCodes;

            header("Location: two_factor_setup.php?enabled=1");
            exit;
        }

        $message = "Unable to enable 2FA. Please try again.";
    }
}

/* Show recovery codes once after successful activation. */
if (isset($_GET["enabled"]) && $_GET["enabled"] === "1") {
    $recoveryCodesToShow =
        $_SESSION["two_factor_recovery_codes_once"] ?? [];

    unset($_SESSION["two_factor_recovery_codes_once"]);
}

/* Generate a local QR image while setup is pending. */
if (
    empty($recoveryCodesToShow) &&
    isset($_SESSION["pending_2fa_secret"])
) {
    $otpauthUrl = $google2fa->getQRCodeUrl(
        "MeetSync",
        $user["email"],
        $_SESSION["pending_2fa_secret"]
    );

    $qrCode = new QrCode(
        data: $otpauthUrl,
        encoding: new Encoding("UTF-8"),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 300,
        margin: 10
    );

    $writer = new PngWriter();
    $qrDataUri = $writer->write($qrCode)->getDataUri();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MeetSync - Two-Factor Authentication</title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>

    <style>
        .two-factor-setup .card {
            max-width: 620px;
            margin: 35px auto;
        }

        .two-factor-intro {
            margin-bottom: 25px;
        }

        .two-factor-step {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border-color, #e5e7eb);
        }

        .two-factor-qr {
            text-align: center;
            margin: 22px 0;
        }

        .two-factor-qr img {
            display: block;
            width: 250px;
            max-width: 100%;
            height: auto;
            margin: 0 auto;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .two-factor-code {
            margin-top: 20px;
        }

        .two-factor-code input {
            width: 100%;
            max-width: 320px;
            box-sizing: border-box;
            letter-spacing: 4px;
            text-align: center;
            font-size: 20px;
        }

        .two-factor-code button {
            margin-top: 18px;
        }

        .two-factor-recovery {
            margin-top: 20px;
        }

        .two-factor-recovery ul {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 0;
            list-style: none;
        }

        .two-factor-recovery li {
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
        }

        .two-factor-recovery code {
            font-size: 15px;
            font-weight: 600;
        }

        .two-factor-note {
            margin-top: 20px;
            padding: 14px;
            border-radius: 8px;
            background: var(--card-bg, #f3f4f6);
        }

        .two-factor-footer {
            text-align: center;
            margin-top: 24px;
        }

        @media (max-width: 480px) {
            .two-factor-recovery ul {
                grid-template-columns: 1fr;
            }

            .two-factor-setup .card {
                margin: 20px auto;
            }
        }
        .meetsync-brand {
    padding-top: 18px;
    padding-bottom: 18px;
}

.meetsync-brand-content h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 750;
    letter-spacing: -0.5px;
    color: var(--text-color, #1e3a8a);
}

.meetsync-brand-content p {
    margin: 5px 0 0;
    font-size: 13px;
    letter-spacing: 0.2px;
    color: var(--text-secondary, #64748b);
}
    </style>
    <link rel="stylesheet" href="css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<header class="site-header">
    <div class="container">
        <div class="navbar meetsync-brand">
            <div class="meetsync-brand-content">
                <h1>MeetSync</h1>
                <p>Meeting Scheduling and Coordination System</p>
            </div>
        </div>
    </div>
</header>

<main class="container two-factor-setup">

    <div class="form-container">
        <div class="card">

            <h2>Two-Factor Authentication</h2>

            <div class="two-factor-intro">
                <p>
                    Add an extra layer of security to your MeetSync account
                    using Google Authenticator or Microsoft Authenticator.
                </p>

                <p>
                    <strong>Account:</strong>
                    <?= htmlspecialchars($user["email"]) ?>
                </p>
            </div>

            <?php if ($message !== ""): ?>
                <div class="message">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($recoveryCodesToShow)): ?>

                <div class="two-factor-step">

                    <h2>Save Your Recovery Codes</h2>

                    <p>
                        Two-factor authentication is enabled successfully.
                        Save the codes below in a secure location.
                    </p>

                    <div class="two-factor-recovery">
                        <ul>
                            <?php foreach ($recoveryCodesToShow as $recoveryCode): ?>
                                <li>
                                    <code><?= htmlspecialchars($recoveryCode) ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="two-factor-note">
                        <strong>Important:</strong>
                        Each recovery code can be used only once.
                        These codes will not be displayed again.
                        Do not share them or store them alongside your password.
                    </div>

                </div>

            <?php elseif ($qrDataUri !== ""): ?>

                <div class="two-factor-step">

                    <h2>Step 1: Scan the QR Code</h2>

                    <p>
                        Open Google Authenticator or Microsoft Authenticator
                        on your phone. Add a new account and scan the QR code
                        below.
                    </p>

                    <div class="two-factor-qr">
                        <img
                            src="<?= htmlspecialchars($qrDataUri) ?>"
                            alt="MeetSync authenticator setup QR code"
                            width="250"
                            height="250"
                        >
                    </div>

                </div>

                <div class="two-factor-step">

                    <h2>Step 2: Verify Your Authenticator</h2>

                    <p>
                        Enter the current six-digit code shown in your
                        authenticator app to complete setup.
                    </p>

                    <form method="post" class="two-factor-code">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION["csrf_2fa_setup"]) ?>"
                        >

                        <div class="form-group">
                            <label for="code">
                                Six-Digit Verification Code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                minlength="6"
                                autocomplete="one-time-code"
                                placeholder="000000"
                                required
                            >
                        </div>

                        <button type="submit" class="btn">
                            Verify and Enable 2FA
                        </button>

                    </form>

                </div>

            <?php else: ?>

                <div class="two-factor-step">
                    <p>
                        Setup is complete or no pending setup is available.
                    </p>
                </div>

            <?php endif; ?>

            <div class="two-factor-footer">
                <a href="two_factor_settings.php">
                    Return to Security Settings
                </a>

                <p>
                    <a href="dashboard.php">Return to Dashboard</a>
                </p>
            </div>

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
