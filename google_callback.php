
<?php

require_once "vendor/autoload.php";
require_once "includes/db.php";
require_once "includes/google_config.php";
require_once "includes/activity_log.php";

session_start();

$client = new Google\Client();

$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri(
    "http://localhost/meetsync/google_callback.php"
);

if (isset($_GET["error"])) {
    error_log(
        "MeetSync Google Sign-In error: " . $_GET["error"]
    );

    die("Google Sign-In was cancelled or could not be completed. Please try again.");
}

if (!isset($_GET["code"])) {
    error_log(
        "MeetSync Google Sign-In: Authorization code missing."
    );

    die("Google authentication failed. No authorization code was received.");
}

$token = $client->fetchAccessTokenWithAuthCode($_GET["code"]);

if (isset($token["error"]) || !isset($token["access_token"])) {
    error_log(
        "MeetSync Google Sign-In token error: "
        . ($token["error_description"] ?? $token["error"] ?? "Access token missing.")
    );

    die("Google authentication failed while obtaining the access token.");
}

$client->setAccessToken($token["access_token"]);

$googleService = new Google\Service\Oauth2($client);
$googleUser = $googleService->userinfo->get();

$email = $googleUser->email ?? "";

if ($email === "") {
    die("Google did not provide an email address. Please try again.");
}

$stmt = $conn->prepare(
    "SELECT id, name, email, role, two_factor_enabled
     FROM users
     WHERE email = ?
     LIMIT 1"
);

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    die("Your Google account is not registered in MeetSync.");
}

/*
 * Clear existing authentication and pending verification state.
 */
unset(
    $_SESSION["user_id"],
    $_SESSION["user_name"],
    $_SESSION["user_email"],
    $_SESSION["user_role"],
    $_SESSION["two_factor_verified_user_id"],
    $_SESSION["pending_2fa_user_id"],
    $_SESSION["pending_2fa_started_at"],
    $_SESSION["csrf_2fa_verify"]
);

session_regenerate_id(true);

/*
 * Require the second factor when enabled for this account.
 */
if ((int) $user["two_factor_enabled"] === 1) {

    $_SESSION["pending_2fa_user_id"] = (int) $user["id"];
    $_SESSION["pending_2fa_started_at"] = time();

    header("Location: verify_two_factor.php");
    exit;
}

/*
 * Complete login for accounts without 2FA.
 */
$_SESSION["user_id"] = (int) $user["id"];
$_SESSION["user_name"] = $user["name"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["user_role"] = $user["role"];

logActivity(
    $conn,
    (int) $user["id"],
    "Login",
    "User logged in using Google Sign-In"
);
header("Location: dashboard.php");
exit;
