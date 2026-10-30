
<?php

session_start();

/*
 * Require an authenticated user session.
 */
if (!isset($_SESSION["user_id"])) {
    header("Location: /meetsync/login.php");
    exit;
}

require_once __DIR__ . "/db.php";

$userId = (int) $_SESSION["user_id"];

/*
 * Check whether this account currently requires 2FA.
 */
$stmt = $conn->prepare(
    "SELECT two_factor_enabled
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

/*
 * Reject missing accounts or invalid authentication state.
 */
if (!$user) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $cookieParams = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $cookieParams["path"],
            $cookieParams["domain"],
            $cookieParams["secure"],
            $cookieParams["httponly"]
        );
    }

    session_destroy();

    header("Location: /meetsync/login.php");
    exit;
}

/*
 * Accounts with 2FA enabled must have passed the second factor
 * during the current authenticated session.
 */
if (
    (int) $user["two_factor_enabled"] === 1 &&
    (int) ($_SESSION["two_factor_verified_user_id"] ?? 0) !== $userId
) {
    unset(
        $_SESSION["user_id"],
        $_SESSION["user_name"],
        $_SESSION["user_email"],
        $_SESSION["user_role"],
        $_SESSION["two_factor_verified_user_id"]
    );

    header("Location: /meetsync/login.php");
    exit;
}

?>
