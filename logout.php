<?php

session_start();

require_once "includes/db.php";
require_once "includes/activity_log.php";

if (isset($_SESSION["user_id"])) {

    logActivity(
        $conn,
        $_SESSION["user_id"],
        "User Logout",
        "User logged out of the system."
    );
}

session_unset();
session_destroy();

header("Location: login.php");
exit;

?>
