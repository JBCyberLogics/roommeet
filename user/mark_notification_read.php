<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];


/*
 * Check that a notification ID was provided.
 */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: notifications.php");
    exit;
}

$notification_id = (int) $_GET["id"];


/*
 * Mark the notification as read.
 *
 * The user_id condition ensures that
 * a user can only update their own notification.
 */
$stmt = $conn->prepare(
    "UPDATE notifications
     SET is_read = TRUE
     WHERE id = ?
     AND user_id = ?"
);

$stmt->bind_param(
    "ii",
    $notification_id,
    $user_id
);

$stmt->execute();

$stmt->close();


/*
 * Return to the notifications page.
 */
header("Location: notifications.php");
exit;

?>